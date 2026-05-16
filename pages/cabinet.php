<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: authorization.php');
    exit();
}

require_once __DIR__ . '/../config/connect_database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../controllers/ClientController.php';

$user = $_SESSION['user'];
$role = $user['role'] ?? '';

if ($role !== User::ROLE_CLIENT) {
    header('Location: ../index.php');
    exit();
}

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW => 'Новая',
    Order::STATUS_ASSIGNED => 'Назначен механик',
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

function cabinet_order_status_label($status, array $labels)
{
    return $labels[$status] ?? $status;
}

function cabinet_order_is_terminal($status)
{
    return in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
}

$client_controller = new ClientController($mysql_connection);
$client_id = (int) $user['id'];

$cars = [];
$orders = [];
$flash_error = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $new_password_confirm = $_POST['new_password_confirm'] ?? '';

        if ($full_name === '') {
            $flash_error = 'Укажите ФИО.';
        } elseif ($phone === '') {
            $flash_error = 'Укажите телефон.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_error = 'Укажите корректный email.';
        } elseif (trim((string) $new_password) !== '' && $new_password !== $new_password_confirm) {
            $flash_error = 'Пароли не совпадают.';
        } else {
            $r = $client_controller->updateClientProfile(
                $client_id,
                $full_name,
                $phone,
                $email,
                (string) $new_password
            );
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось сохранить профиль.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Данные сохранены.';
                $data = $r['data'] ?? [];
                $_SESSION['user']['full_name'] = $data['full_name'] ?? $full_name;
                $_SESSION['user']['phone'] = $data['phone'] ?? $phone;
                $_SESSION['user']['email'] = $data['email'] ?? $email;
                $user = $_SESSION['user'];
            }
        }
    } elseif ($action === 'update_order_description') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($order_id <= 0) {
            $flash_error = 'Некорректная заявка.';
        } else {
            $r = $client_controller->updateClientOrderDescription($client_id, $order_id, $description);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось обновить описание.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Описание сохранено.';
            }
        }
    } elseif ($action === 'cancel_order') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        if ($order_id <= 0) {
            $flash_error = 'Некорректная заявка.';
        } else {
            $r = $client_controller->cancelOrder($client_id, $order_id);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось отменить заявку.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Заявка отменена.';
            }
        }
    } elseif ($action === 'update_car') {
        $car_id = (int) ($_POST['car_id'] ?? 0);
        $vin = trim($_POST['vin'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = (int) ($_POST['year'] ?? 0);
        $gosnumber = trim($_POST['gosnumber'] ?? '');

        if ($car_id <= 0) {
            $flash_error = 'Некорректный автомобиль.';
        } elseif ($vin === '' || $brand === '' || $model === '' || $gosnumber === '') {
            $flash_error = 'Заполните все поля автомобиля.';
        } elseif ($year < 1980 || $year > (int) date('Y') + 1) {
            $flash_error = 'Укажите корректный год выпуска.';
        } else {
            $r = $client_controller->updateClientCar($client_id, $car_id, $vin, $brand, $model, $year, $gosnumber);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось сохранить автомобиль.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Данные сохранены.';
            }
        }
    }
}

$cars_r = $client_controller->getClientCars($client_id);
$orders_r = $client_controller->getClientOrders($client_id);
if ($cars_r['success'] ?? false) {
    $cars = $cars_r['data']['cars'] ?? [];
}
if ($orders_r['success'] ?? false) {
    $orders = $orders_r['data']['orders'] ?? [];
}

$car_ids_in_active_orders = [];
foreach ($orders as $o) {
    $st = $o['status'] ?? '';
    if (!cabinet_order_is_terminal($st)) {
        $car_ids_in_active_orders[(int) ($o['car_id'] ?? 0)] = true;
    }
}

/* Состав заявок (услуги + запчасти) — загружаем двумя запросами, группируем по order_id */
$orders_services = [];
$orders_parts    = [];
$comp_r = $client_controller->getOrderComposition($client_id);
if ($comp_r['success'] ?? false) {
    foreach ($comp_r['data']['services'] ?? [] as $row) {
        $orders_services[(int) $row['order_id']][] = $row;
    }
    foreach ($comp_r['data']['parts'] ?? [] as $row) {
        $orders_parts[(int) $row['order_id']][] = $row;
    }
}

/* История статусов — загружаем один раз, группируем по order_id */
$orders_history = [];
$history_r = $client_controller->getOrderHistory($client_id);
if ($history_r['success'] ?? false) {
    foreach ($history_r['data']['history'] ?? [] as $h) {
        $orders_history[(int) $h['order_id']][] = $h;
    }
}

$nav_active = 'cabinet';
$nav_home_href = '../index.php';
$nav_cabinet_href = 'cabinet.php';
$nav_logout_href = 'authorization.php?logout=1';
$nav_show_cabinet = true;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <?php include __DIR__ . '/../includes/layout_styles.php'; ?>
    <style>
        .btn-sm-danger {
            padding: 4px 12px;
            border: 1px solid var(--danger-border);
            border-radius: 4px;
            background: #fff;
            color: var(--danger-text);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-sm-danger:hover { background: var(--danger-bg); }

        .order-history { margin-top: 6px; }
        .order-history summary {
            cursor: pointer; font-size: 0.78rem; color: var(--focus);
            font-weight: 600; user-select: none; list-style: none;
        }
        .order-history summary::-webkit-details-marker { display: none; }
        .order-history summary::before { content: '▶ '; font-size: 0.65rem; }
        .order-history[open] summary::before { content: '▼ '; }
        .history-timeline { list-style: none; margin: 6px 0 0; padding: 0; }
        .history-timeline li {
            display: flex; gap: 6px; align-items: flex-start;
            font-size: 0.78rem; padding: 3px 0;
            border-left: 2px solid var(--focus); margin-left: 4px; padding-left: 8px;
            color: #374151;
        }
        .history-timeline li:last-child { border-left-color: transparent; }
        .history-timeline .ht-date { color: #9ca3af; white-space: nowrap; flex-shrink: 0; }
        .history-timeline .ht-status { font-weight: 600; }
        .history-timeline .ht-actor { color: #6b7280; }
        .history-timeline .ht-note { color: #b91c1c; font-style: italic; }

        .order-composition { margin-top: 8px; }
        .order-composition summary {
            cursor: pointer; font-size: 0.78rem; color: #059669;
            font-weight: 600; user-select: none; list-style: none;
        }
        .order-composition summary::-webkit-details-marker { display: none; }
        .order-composition summary::before { content: '▶ '; font-size: 0.65rem; }
        .order-composition[open] summary::before { content: '▼ '; }
        .comp-section { margin-top: 6px; }
        .comp-section h4 { margin: 0 0 4px; font-size: 0.78rem; text-transform: uppercase;
                           letter-spacing: .04em; color: #6b7280; }
        .comp-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .comp-table th, .comp-table td {
            padding: 3px 8px; text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        .comp-table th { color: #9ca3af; font-weight: 600; }
        .comp-table tr:last-child td { border-bottom: none; }
        .comp-total { font-size: 0.8rem; font-weight: 700; text-align: right;
                      margin-top: 4px; color: #111827; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/site_nav.php'; ?>

    <div class="page">
        <?php if ($flash_error): ?>
            <div class="flash flash-err"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
            <div class="flash flash-ok"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>

        <h1 class="sans">Личный кабинет</h1>
        <p class="lead">Здравствуйте, <?php echo htmlspecialchars($user['full_name'] ?? ''); ?>. Измените личные данные здесь, а новую заявку и автомобиль можно добавить на <a href="<?php echo htmlspecialchars($nav_home_href); ?>" style="color: var(--focus); font-weight: 600; text-decoration: none;">главной</a>.</p>

        <section class="card" id="profile">
            <h2>Личные данные</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="update_profile">
                <div class="row2">
                    <div class="field">
                        <label for="full_name">ФИО</label>
                        <input id="full_name" name="full_name" type="text" required maxlength="150" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" name="phone" type="text" required maxlength="30" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="150" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="new_password">Новый пароль</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password" maxlength="128" placeholder="Оставьте пустым, если не меняете">
                        <p class="hint">Не менее 6 символов, если заполняете.</p>
                    </div>
                    <div class="field">
                        <label for="new_password_confirm">Подтверждение пароля</label>
                        <input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" maxlength="128" placeholder="Повторите новый пароль">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Сохранить изменения</button>
            </form>
        </section>

        <section class="card">
            <h2>Мои заявки</h2>
            <?php if (empty($orders)): ?>
                <p class="empty">Заявок пока нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Авто</th>
                                <th>Статус</th>
                                <th>Механик</th>
                                <th>Сумма</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <?php
                                $st = $o['status'] ?? '';
                                $badge_class = 'badge';
                                if ($st === Order::STATUS_COMPLETED) {
                                    $badge_class .= ' badge-done';
                                } elseif (
                                    $st === Order::STATUS_WAITING_PARTS
                                    || $st === Order::STATUS_NEW
                                    || $st === Order::STATUS_ASSIGNED
                                ) {
                                    $badge_class .= ' badge-warn';
                                }
                                $price = isset($o['total_price'])
                                    ? number_format((float) $o['total_price'], 0, '.', ' ') . ' ₽'
                                    : '—';
                                $desc = (string) ($o['description'] ?? '');
                                $desc_short = function_exists('mb_substr')
                                    ? (mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '…' : $desc)
                                    : (strlen($desc) > 100 ? substr($desc, 0, 100) . '…' : $desc);
                                $can_edit_order_desc = ($st === Order::STATUS_NEW);
                                $can_cancel = ($st === Order::STATUS_NEW);
                                ?>
                                <tr>
                                    <td><?php echo (int) ($o['id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))); ?>
                                        <?php if ($can_edit_order_desc): ?>
                                            <form method="post" action="" style="margin-top: 10px; max-width: 320px;">
                                                <input type="hidden" name="action" value="update_order_description">
                                                <input type="hidden" name="order_id" value="<?php echo (int) ($o['id'] ?? 0); ?>">
                                                <div class="field" style="margin-bottom: 8px;">
                                                    <label for="order_desc_<?php echo (int) ($o['id'] ?? 0); ?>">Описание</label>
                                                    <textarea id="order_desc_<?php echo (int) ($o['id'] ?? 0); ?>" name="description" required maxlength="4000" rows="3"><?php echo htmlspecialchars($desc); ?></textarea>
                                                </div>
                                                <button type="submit" class="btn-submit">Сохранить описание</button>
                                            </form>
                                        <?php elseif ($desc !== ''): ?>
                                            <div class="hint" style="max-width: 280px;"><?php echo htmlspecialchars($desc_short); ?></div>
                                        <?php endif; ?>

                                        <?php
                                        $oid_c    = (int) ($o['id'] ?? 0);
                                        $svc_rows = $orders_services[$oid_c] ?? [];
                                        $prt_rows = $orders_parts[$oid_c]    ?? [];
                                        if (!empty($svc_rows) || !empty($prt_rows)):
                                            $svc_total = array_sum(array_map(fn($r) => (float)$r['price'] * (int)$r['quantity'], $svc_rows));
                                            $prt_total = array_sum(array_map(fn($r) => (float)$r['price'] * (int)$r['quantity'], $prt_rows));
                                            $grand_total = $svc_total + $prt_total;
                                        ?>
                                        <details class="order-composition">
                                            <summary>Состав заявки</summary>

                                            <?php if (!empty($svc_rows)): ?>
                                            <div class="comp-section">
                                                <h4>Услуги</h4>
                                                <table class="comp-table">
                                                    <thead>
                                                        <tr><th>Наименование</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($svc_rows as $sr): ?>
                                                        <?php $line = (float)$sr['price'] * (int)$sr['quantity']; ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($sr['name'] ?? ''); ?>
                                                                <?php if (!empty($sr['comment'])): ?>
                                                                    <span style="color:#9ca3af"> — <?php echo htmlspecialchars($sr['comment']); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo (int)$sr['quantity']; ?></td>
                                                            <td><?php echo number_format((float)$sr['price'], 0, '.', ' '); ?> ₽</td>
                                                            <td><?php echo number_format($line, 0, '.', ' '); ?> ₽</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($prt_rows)): ?>
                                            <div class="comp-section">
                                                <h4>Запчасти</h4>
                                                <table class="comp-table">
                                                    <thead>
                                                        <tr><th>Наименование</th><th>Артикул</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($prt_rows as $pr): ?>
                                                        <?php $line = (float)$pr['price'] * (int)$pr['quantity']; ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($pr['name'] ?? ''); ?>
                                                                <?php if (!empty($pr['note'])): ?>
                                                                    <span style="color:#9ca3af"> — <?php echo htmlspecialchars($pr['note']); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="color:#9ca3af"><?php echo htmlspecialchars($pr['article'] ?? ''); ?></td>
                                                            <td><?php echo (int)$pr['quantity']; ?></td>
                                                            <td><?php echo number_format((float)$pr['price'], 0, '.', ' '); ?> ₽</td>
                                                            <td><?php echo number_format($line, 0, '.', ' '); ?> ₽</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>

                                            <div class="comp-total">
                                                Итого: <?php echo number_format($grand_total, 0, '.', ' '); ?> ₽
                                            </div>
                                        </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars(cabinet_order_status_label($st, $ORDER_STATUS_LABELS)); ?></span>
                                        <?php if ($st === Order::STATUS_CANCELLED && !empty($o['cancel_comment'])): ?>
                                            <div class="hint" style="margin-top:4px; color:#b91c1c; max-width:240px;">
                                                <?php echo nl2br(htmlspecialchars((string) $o['cancel_comment'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        $oid_h = (int) ($o['id'] ?? 0);
                                        $hist  = $orders_history[$oid_h] ?? [];
                                        if (!empty($hist)):
                                        ?>
                                        <details class="order-history">
                                            <summary>История (<?php echo count($hist); ?>)</summary>
                                            <ul class="history-timeline">
                                                <?php foreach ($hist as $h): ?>
                                                    <?php
                                                    $role_lbl = \OrderStatusHistoryContext::roleLabel($h['changed_by_role'] ?? '');
                                                    $new_lbl  = \OrderStatusHistoryContext::statusLabel($h['new_status'] ?? '');
                                                    $date_lbl = $h['changed_at']
                                                        ? date('d.m.Y H:i', strtotime($h['changed_at']))
                                                        : '';
                                                    ?>
                                                    <li>
                                                        <span class="ht-date"><?php echo htmlspecialchars($date_lbl); ?></span>
                                                        <span>
                                                            <span class="ht-status"><?php echo htmlspecialchars($new_lbl); ?></span>
                                                            <span class="ht-actor"> — <?php echo htmlspecialchars($role_lbl); ?></span>
                                                            <?php if (!empty($h['note'])): ?>
                                                                <br><span class="ht-note"><?php echo htmlspecialchars((string) $h['note']); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($o['mechanic_name']) ? htmlspecialchars($o['mechanic_name']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($price); ?></td>
                                    <td>
                                        <?php if ($can_cancel): ?>
                                            <form method="post" action="" style="display:inline;">
                                                <input type="hidden" name="action"   value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?php echo (int) ($o['id'] ?? 0); ?>">
                                                <button type="submit" class="btn-sm-danger"
                                                    onclick="return confirm('Отменить заявку #<?php echo (int) ($o['id'] ?? 0); ?>? Это действие необратимо.')">
                                                    Отменить
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($cars)): ?>
            <section class="card">
                <h2>Мои автомобили</h2>
                <ul class="car-list sans">
                    <?php foreach ($cars as $c): ?>
                        <?php
                        $cid = (int) ($c['id'] ?? 0);
                        $car_busy = !empty($car_ids_in_active_orders[$cid]);
                        ?>
                        <li>
                            <div class="car-title"><?php echo htmlspecialchars(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ', ' . ($c['year'] ?? '') . ' г.'); ?></div>
                            <div class="car-sub"><?php echo htmlspecialchars($c['gosnumber'] ?? ''); ?> · VIN <?php echo htmlspecialchars($c['vin'] ?? ''); ?></div>
                            <?php if ($car_busy): ?>
                                <p class="hint" style="margin: 10px 0 0;">Редактирование недоступно: автомобиль участвует в активной заявке.</p>
                            <?php else: ?>
                                <form method="post" action="" style="margin-top: 12px;">
                                    <input type="hidden" name="action" value="update_car">
                                    <input type="hidden" name="car_id" value="<?php echo $cid; ?>">
                                    <div class="row2">
                                        <div class="field">
                                            <label for="ebrand_<?php echo $cid; ?>">Марка</label>
                                            <input id="ebrand_<?php echo $cid; ?>" name="brand" type="text" required maxlength="80" value="<?php echo htmlspecialchars($c['brand'] ?? ''); ?>">
                                        </div>
                                        <div class="field">
                                            <label for="emodel_<?php echo $cid; ?>">Модель</label>
                                            <input id="emodel_<?php echo $cid; ?>" name="model" type="text" required maxlength="80" value="<?php echo htmlspecialchars($c['model'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row2">
                                        <div class="field">
                                            <label for="eyear_<?php echo $cid; ?>">Год</label>
                                            <input id="eyear_<?php echo $cid; ?>" name="year" type="number" required min="1980" max="<?php echo (int) date('Y') + 1; ?>" value="<?php echo (int) ($c['year'] ?? 0); ?>">
                                        </div>
                                        <div class="field">
                                            <label for="egos_<?php echo $cid; ?>">Госномер</label>
                                            <input id="egos_<?php echo $cid; ?>" name="gosnumber" type="text" required maxlength="20" value="<?php echo htmlspecialchars($c['gosnumber'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label for="evin_<?php echo $cid; ?>">VIN</label>
                                        <input id="evin_<?php echo $cid; ?>" name="vin" type="text" required maxlength="64" value="<?php echo htmlspecialchars($c['vin'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" class="btn-submit">Сохранить автомобиль</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
