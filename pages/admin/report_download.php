<?php
/**
 * Генерация системного отчёта в формате Excel (.xlsx).
 * Доступно только администратору.
 *
 * Листы:
 *   1. Сводка           — ключевые показатели системы
 *   2. Заявки           — все заявки с деталями
 *   3. Сотрудники       — персонал с окладами и статусами
 *   4. Запросы закупок  — история запросов на запчасти
 *   5. Зарплатные записи— история начислений
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: ../authorization.php');
    exit;
}

require_once __DIR__ . '/../../models/User.php';

$role = $_SESSION['user']['role'] ?? '';
if ($role !== User::ROLE_ADMIN) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/connect_database.php';
require_once __DIR__ . '/../../includes/XlsxWriter.php';

$admin_id = (int)$_SESSION['user']['id'];

/* ======================================================================
   Вспомогательные функции
   ====================================================================== */

function cell_s($val): array { return ['v' => (string)($val ?? ''), 't' => 's']; }
function cell_n($val): array { return ['v' => is_numeric($val) ? (float)$val : 0, 't' => 'n']; }
function cell_m($val): array { return ['v' => is_numeric($val) ? (float)$val : 0, 't' => 'currency']; }
function cell_d($val): array { return ['v' => (string)($val ?? ''), 't' => 'date']; }

$ORDER_STATUS_LABELS = [
    'new'           => 'Новая',
    'assigned'      => 'Назначен механик',
    'in_progress'   => 'В работе',
    'waiting_parts' => 'Ожидание запчастей',
    'completed'     => 'Завершена',
    'cancelled'     => 'Отменена',
];

$PURCHASE_STATUS_LABELS = [
    'pending'  => 'Ожидает',
    'approved' => 'Одобрен',
    'rejected' => 'Отклонён',
    'ordered'  => 'Заказан',
    'received' => 'Получен',
];

$SALARY_STATUS_LABELS = [
    'draft'    => 'Черновик',
    'approved' => 'Утверждена',
    'paid'     => 'Выплачена',
    'rejected' => 'Отклонена',
];

$ROLE_LABELS = [
    'admin'    => 'Администратор',
    'master'   => 'Мастер',
    'mechanic' => 'Механик',
    'client'   => 'Клиент',
];

/* ======================================================================
   Сбор данных из БД
   ====================================================================== */

// --- 1. Сводка ---
$stats = [];

$res = mysqli_query($mysql_connection, "SELECT COUNT(*) AS cnt FROM orders");
$stats['total_orders'] = (int)$res->fetch_assoc()['cnt'];

foreach (['new','assigned','in_progress','waiting_parts','completed','cancelled'] as $st) {
    $stmt = mysqli_prepare($mysql_connection, "SELECT COUNT(*) AS cnt FROM orders WHERE status = ?");
    mysqli_stmt_bind_param($stmt, 's', $st);
    mysqli_stmt_execute($stmt);
    $stats['orders_' . $st] = (int)mysqli_stmt_get_result($stmt)->fetch_assoc()['cnt'];
    mysqli_stmt_close($stmt);
}

$res = mysqli_query($mysql_connection, "SELECT COALESCE(SUM(total_price),0) AS s FROM orders WHERE status='completed'");
$stats['revenue'] = (float)$res->fetch_assoc()['s'];

$res = mysqli_query($mysql_connection, "SELECT COUNT(*) AS cnt FROM users WHERE role='client'");
$stats['total_clients'] = (int)$res->fetch_assoc()['cnt'];

$res = mysqli_query($mysql_connection, "SELECT COUNT(*) AS cnt FROM users WHERE role!='client' AND is_active=1");
$stats['active_employees'] = (int)$res->fetch_assoc()['cnt'];

$res = mysqli_query($mysql_connection, "SELECT COUNT(*) AS cnt FROM part_purchase_requests WHERE status='pending'");
$stats['pending_purchases'] = (int)$res->fetch_assoc()['cnt'];

$res = mysqli_query($mysql_connection, "SELECT COALESCE(SUM(amount),0) AS s FROM salary_records WHERE status='paid'");
$stats['total_salary_paid'] = (float)$res->fetch_assoc()['s'];

$res = mysqli_query($mysql_connection, "SELECT COUNT(*) AS cnt FROM cars");
$stats['total_cars'] = (int)$res->fetch_assoc()['cnt'];

// --- 2. Заявки ---
$orders_res = mysqli_query($mysql_connection, "
    SELECT o.id, o.status, o.description, o.total_price,
           o.start_date, o.end_date, o.created_at,
           u_cl.full_name  AS client_name,  u_cl.phone AS client_phone,
           u_mech.full_name AS mechanic_name,
           u_ms.full_name   AS master_name,
           c.brand, c.model, c.gosnumber, c.year,
           (
               SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
               FROM order_services os JOIN services s ON s.id = os.service_id
               WHERE os.order_id = o.id
           ) AS services_list
    FROM orders o
    JOIN users u_cl   ON u_cl.id  = o.client_id
    JOIN cars c        ON c.id    = o.car_id
    LEFT JOIN users u_mech ON u_mech.id = o.mechanic_id
    LEFT JOIN users u_ms   ON u_ms.id   = o.master_id
    ORDER BY o.id DESC
");
$orders = $orders_res ? $orders_res->fetch_all(MYSQLI_ASSOC) : [];

// --- 3. Сотрудники ---
$employees_res = mysqli_query($mysql_connection, "
    SELECT u.id, u.full_name, u.role, u.email, u.phone,
           u.salary, u.is_active, u.created_at,
           (SELECT COUNT(*) FROM orders o WHERE o.mechanic_id = u.id) AS orders_done
    FROM users u
    WHERE u.role != 'client'
    ORDER BY u.role, u.full_name
");
$employees = $employees_res ? $employees_res->fetch_all(MYSQLI_ASSOC) : [];

// --- 4. Закупки ---
$purchases_res = mysqli_query($mysql_connection, "
    SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
           ppr.comment, ppr.created_at, ppr.resolved_at,
           p.name AS part_name, p.article, p.price AS part_price,
           u_m.full_name  AS requested_by,
           u_a.full_name  AS resolved_by
    FROM part_purchase_requests ppr
    JOIN parts p         ON p.id  = ppr.part_id
    JOIN users u_m       ON u_m.id = ppr.requested_by_master_id
    LEFT JOIN users u_a  ON u_a.id = ppr.approved_by_admin_id
    ORDER BY ppr.created_at DESC
");
$purchases = $purchases_res ? $purchases_res->fetch_all(MYSQLI_ASSOC) : [];

// --- 5. Зарплаты ---
$salary_res = mysqli_query($mysql_connection, "
    SELECT sr.id, sr.amount, sr.period_start, sr.period_end,
           sr.status, sr.comment, sr.approved_at, sr.paid_at,
           u_e.full_name AS employee_name, u_e.role AS employee_role,
           u_a.full_name AS created_by
    FROM salary_records sr
    JOIN users u_e ON u_e.id = sr.employee_id
    JOIN users u_a ON u_a.id = sr.created_by_admin_id
    ORDER BY sr.period_start DESC, u_e.full_name
");
$salary_records = $salary_res ? $salary_res->fetch_all(MYSQLI_ASSOC) : [];

/* ======================================================================
   Построение XLSX
   ====================================================================== */

$xlsx = new XlsxWriter();
$now  = date('d.m.Y H:i');

/* ------ Лист 1: Сводка ------ */
$xlsx->addSheet('Сводка');
$xlsx->writeHeader(['Показатель', 'Значение']);

$xlsx->writeRow([cell_s('Дата формирования отчёта'), cell_s($now)]);
$xlsx->writeRow([cell_s(''), cell_s('')]);

$xlsx->writeRow([cell_s('=== ЗАЯВКИ ==='), cell_s('')]);
$xlsx->writeRow([cell_s('Всего заявок'),                       cell_n($stats['total_orders'])]);
$xlsx->writeRow([cell_s('  Новые'),                            cell_n($stats['orders_new'])]);
$xlsx->writeRow([cell_s('  Назначен механик'),                 cell_n($stats['orders_assigned'])]);
$xlsx->writeRow([cell_s('  В работе'),                         cell_n($stats['orders_in_progress'])]);
$xlsx->writeRow([cell_s('  Ожидание запчастей'),               cell_n($stats['orders_waiting_parts'])]);
$xlsx->writeRow([cell_s('  Завершено'),                        cell_n($stats['orders_completed'])]);
$xlsx->writeRow([cell_s('  Отменено'),                         cell_n($stats['orders_cancelled'])]);
$xlsx->writeRow([cell_s('Выручка по завершённым заявкам, ₽'), cell_m($stats['revenue'])]);

$xlsx->writeRow([cell_s(''), cell_s('')]);
$xlsx->writeRow([cell_s('=== КЛИЕНТЫ И ПЕРСОНАЛ ==='), cell_s('')]);
$xlsx->writeRow([cell_s('Всего клиентов'),                     cell_n($stats['total_clients'])]);
$xlsx->writeRow([cell_s('Зарегистрировано автомобилей'),       cell_n($stats['total_cars'])]);
$xlsx->writeRow([cell_s('Активных сотрудников'),               cell_n($stats['active_employees'])]);

$xlsx->writeRow([cell_s(''), cell_s('')]);
$xlsx->writeRow([cell_s('=== ФИНАНСЫ ==='), cell_s('')]);
$xlsx->writeRow([cell_s('Запросов на закупку (ожидают)'),      cell_n($stats['pending_purchases'])]);
$xlsx->writeRow([cell_s('Суммарно выплачено зарплат, ₽'),     cell_m($stats['total_salary_paid'])]);

/* ------ Лист 2: Заявки ------ */
$xlsx->addSheet('Заявки');
$xlsx->writeHeader([
    '№', 'Статус', 'Клиент', 'Телефон', 'Автомобиль', 'Год',
    'Механик', 'Мастер', 'Услуги', 'Сумма, ₽',
    'Начало работ', 'Завершение', 'Создана',
    'Описание',
]);
foreach ($orders as $o) {
    $car = trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? ''));
    $xlsx->writeRow([
        cell_n($o['id']),
        cell_s($ORDER_STATUS_LABELS[$o['status'] ?? ''] ?? ($o['status'] ?? '')),
        cell_s($o['client_name'] ?? ''),
        cell_s($o['client_phone'] ?? ''),
        cell_s($car . ', ' . ($o['gosnumber'] ?? '')),
        cell_n($o['year'] ?? ''),
        cell_s($o['mechanic_name'] ?? '—'),
        cell_s($o['master_name'] ?? '—'),
        cell_s($o['services_list'] ?? '—'),
        cell_m($o['total_price'] ?? 0),
        cell_s($o['start_date'] ?? ''),
        cell_s($o['end_date'] ?? ''),
        cell_s($o['created_at'] ?? ''),
        cell_s($o['description'] ?? ''),
    ]);
}

/* ------ Лист 3: Сотрудники ------ */
$xlsx->addSheet('Сотрудники');
$xlsx->writeHeader([
    '№', 'ФИО', 'Роль', 'Email', 'Телефон',
    'Оклад, ₽', 'Статус', 'Заявок выполнено', 'Дата регистрации',
]);
foreach ($employees as $emp) {
    $xlsx->writeRow([
        cell_n($emp['id']),
        cell_s($emp['full_name'] ?? ''),
        cell_s($ROLE_LABELS[$emp['role'] ?? ''] ?? ($emp['role'] ?? '')),
        cell_s($emp['email'] ?? ''),
        cell_s($emp['phone'] ?? ''),
        cell_m($emp['salary'] ?? 0),
        cell_s((int)($emp['is_active'] ?? 0) ? 'Активен' : 'Деактивирован'),
        cell_n($emp['orders_done'] ?? 0),
        cell_s(substr((string)($emp['created_at'] ?? ''), 0, 10)),
    ]);
}

/* ------ Лист 4: Закупки ------ */
$xlsx->addSheet('Запросы на закупку');
$xlsx->writeHeader([
    '№', 'Заявка №', 'Запчасть', 'Артикул',
    'Цена ед., ₽', 'Кол-во', 'Сумма, ₽',
    'Статус', 'Запросил', 'Решил', 'Комментарий',
    'Дата запроса', 'Дата решения',
]);
foreach ($purchases as $p) {
    $price = (float)($p['part_price'] ?? 0);
    $qty   = (int)($p['quantity'] ?? 0);
    $xlsx->writeRow([
        cell_n($p['id']),
        cell_n($p['order_id']),
        cell_s($p['part_name'] ?? ''),
        cell_s($p['article'] ?? ''),
        cell_m($price),
        cell_n($qty),
        cell_m($price * $qty),
        cell_s($PURCHASE_STATUS_LABELS[$p['status'] ?? ''] ?? ($p['status'] ?? '')),
        cell_s($p['requested_by'] ?? ''),
        cell_s($p['resolved_by'] ?? '—'),
        cell_s($p['comment'] ?? ''),
        cell_s($p['created_at'] ?? ''),
        cell_s($p['resolved_at'] ?? ''),
    ]);
}

/* ------ Лист 5: Зарплаты ------ */
$xlsx->addSheet('Зарплатные записи');
$xlsx->writeHeader([
    '№', 'Сотрудник', 'Роль', 'Период (с)', 'Период (по)',
    'Сумма, ₽', 'Статус', 'Создал', 'Комментарий',
    'Утверждено', 'Выплачено',
]);
foreach ($salary_records as $sr) {
    $xlsx->writeRow([
        cell_n($sr['id']),
        cell_s($sr['employee_name'] ?? ''),
        cell_s($ROLE_LABELS[$sr['employee_role'] ?? ''] ?? ($sr['employee_role'] ?? '')),
        cell_d($sr['period_start'] ?? ''),
        cell_d($sr['period_end'] ?? ''),
        cell_m($sr['amount'] ?? 0),
        cell_s($SALARY_STATUS_LABELS[$sr['status'] ?? ''] ?? ($sr['status'] ?? '')),
        cell_s($sr['created_by'] ?? ''),
        cell_s($sr['comment'] ?? ''),
        cell_s($sr['approved_at'] ?? ''),
        cell_s($sr['paid_at'] ?? ''),
    ]);
}

/* ======================================================================
   Отдача файла
   ====================================================================== */
$fileName = 'АвтоПлюс_отчёт_' . date('Y-m-d_H-i') . '.xlsx';
$xlsx->download($fileName);
