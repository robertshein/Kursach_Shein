<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/SalaryRecord.php';

$nav_admin_section = 'salary';

$flash_error   = null;
$flash_success = null;

$STATUS_LABELS = [
    SalaryRecord::STATUS_DRAFT    => 'Черновик',
    SalaryRecord::STATUS_APPROVED => 'Утверждена',
    SalaryRecord::STATUS_PAID     => 'Выплачена',
    SalaryRecord::STATUS_REJECTED => 'Отклонена',
];

$STATUS_BADGE = [
    SalaryRecord::STATUS_DRAFT    => 'badge badge-warn',
    SalaryRecord::STATUS_APPROVED => 'badge',
    SalaryRecord::STATUS_PAID     => 'badge badge-done',
    SalaryRecord::STATUS_REJECTED => 'badge',
];

// Допустимые переходы статусов для одиночной записи
$STATUS_TRANSITIONS = [
    SalaryRecord::STATUS_DRAFT    => [SalaryRecord::STATUS_APPROVED, SalaryRecord::STATUS_REJECTED],
    SalaryRecord::STATUS_APPROVED => [SalaryRecord::STATUS_PAID, SalaryRecord::STATUS_REJECTED],
    SalaryRecord::STATUS_PAID     => [],
    SalaryRecord::STATUS_REJECTED => [],
];

// --- Обработка POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // 1. Автогенерация ведомости за период
    if ($action === 'generate_payroll') {
        $p_start = trim($_POST['period_start'] ?? '');
        $p_end   = trim($_POST['period_end']   ?? '');

        if ($p_start === '' || $p_end === '') {
            $flash_error = 'Укажите период.';
        } elseif ($p_start > $p_end) {
            $flash_error = 'Начало периода не может быть позже конца.';
        } else {
            $r = $admin_controller->generatePayroll($p_start, $p_end, $admin_id);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Ошибка генерации.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Ведомость сформирована.';
            }
        }

    // 2. Групповая смена статуса для периода
    } elseif ($action === 'bulk_status') {
        $p_start     = trim($_POST['period_start'] ?? '');
        $p_end       = trim($_POST['period_end']   ?? '');
        $from_status = (string)($_POST['from_status'] ?? '');
        $to_status   = (string)($_POST['to_status']   ?? '');

        if ($p_start === '' || $p_end === '' || $from_status === '' || $to_status === '') {
            $flash_error = 'Некорректные параметры группового обновления.';
        } else {
            $r = $admin_controller->bulkSetSalaryStatus($p_start, $p_end, $from_status, $to_status);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Ошибка массового обновления.';
            } else {
                $cnt = (int)($r['data']['affected'] ?? 0);
                $flash_success = 'Статус обновлён для ' . $cnt . ' ' . _plural($cnt, 'записи', 'записей', 'записей') . '.';
            }
        }

    // 3. Одиночная смена статуса
    } elseif ($action === 'set_status') {
        $record_id  = (int)($_POST['record_id']  ?? 0);
        $new_status = (string)($_POST['new_status'] ?? '');

        if ($record_id <= 0) {
            $flash_error = 'Некорректная запись.';
        } elseif (!in_array($new_status, SalaryRecord::getAvailableStatuses(), true)) {
            $flash_error = 'Некорректный статус.';
        } else {
            $r = $admin_controller->setSalaryRecordStatus($record_id, $new_status);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось изменить статус.';
            } else {
                $flash_success = 'Статус обновлён: «' . ($STATUS_LABELS[$new_status] ?? $new_status) . '».';
            }
        }

    // 4. Ручное создание одиночной записи
    } elseif ($action === 'create_salary') {
        $emp_id  = (int)($_POST['employee_id']  ?? 0);
        $amount  = (float)str_replace(',', '.', ($_POST['amount']  ?? '0'));
        $p_start = trim($_POST['period_start'] ?? '');
        $p_end   = trim($_POST['period_end']   ?? '');
        $comment = trim($_POST['comment']      ?? '');

        if ($emp_id <= 0 || $amount <= 0 || $p_start === '' || $p_end === '') {
            $flash_error = 'Заполните все обязательные поля.';
        } elseif ($p_start > $p_end) {
            $flash_error = 'Начало периода не может быть позже конца.';
        } else {
            $r = $admin_controller->createSalaryRecord(
                $emp_id, $amount, $p_start, $p_end, $admin_id,
                $comment !== '' ? $comment : null
            );
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось создать запись.';
            } else {
                $flash_success = 'Запись создана со статусом «Черновик».';
            }
        }
    }
}

// --- Загрузка данных ---
$periods_data = [];
$details      = [];
$r_periods = $admin_controller->getSalaryByPeriods();
if ($r_periods['success'] ?? false) {
    $periods_data = $r_periods['data']['periods'] ?? [];
    $details      = $r_periods['data']['details'] ?? [];
}

$stats = [];
$r_stats = $admin_controller->getSalaryStats();
if ($r_stats['success'] ?? false) {
    $stats = $r_stats['data']['stats'] ?? [];
}

$employees = [];
$r_emp = $admin_controller->getEmployees();
if ($r_emp['success'] ?? false) {
    $employees = $r_emp['data']['employees'] ?? [];
}

// Вспомогательная функция для склонения
function _plural($n, $one, $few, $many) {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n >= 11 && $n <= 19)     return $many;
    if ($n1 === 1)                  return $one;
    if ($n1 >= 2 && $n1 <= 4)      return $few;
    return $many;
}

$ROLE_LABELS = [
    'mechanic' => 'Механик',
    'master'   => 'Мастер',
    'admin'    => 'Администратор',
];

function fmt_money($val) {
    return number_format((float)$val, 0, '.', ' ') . ' ₽';
}

function fmt_period($start, $end) {
    // Пытаемся показать "Апрель 2026" если это полный месяц, иначе "01.04 — 30.04.2026"
    $months_ru = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                  'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    $ts_s = strtotime($start);
    $ts_e = strtotime($end);
    if (!$ts_s || !$ts_e) return htmlspecialchars($start . ' — ' . $end);

    $y_s = (int)date('Y', $ts_s);
    $m_s = (int)date('n', $ts_s);
    $d_s = (int)date('j', $ts_s);
    $y_e = (int)date('Y', $ts_e);
    $m_e = (int)date('n', $ts_e);
    $d_e = (int)date('j', $ts_e);
    $last_day = (int)date('t', $ts_s);

    if ($y_s === $y_e && $m_s === $m_e && $d_s === 1 && $d_e === $last_day) {
        return htmlspecialchars($months_ru[$m_s] . ' ' . $y_s);
    }
    return htmlspecialchars(date('d.m.Y', $ts_s) . ' — ' . date('d.m.Y', $ts_e));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Зарплаты — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        /* ---- кнопки ---- */
        .btn-sm {
            padding: 5px 11px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            color: var(--focus);
            white-space: nowrap;
        }
        .btn-sm:hover { background: #f0f4fb; }
        .btn-sm-ok     { color: var(--ok-text);     border-color: var(--ok-border); }
        .btn-sm-ok:hover { background: var(--ok-bg); }
        .btn-sm-danger { color: var(--danger-text); border-color: var(--danger-border); }
        .btn-sm-danger:hover { background: var(--danger-bg); }
        .btn-sm-primary { color: #fff; background: var(--focus); border-color: #1558b0; }
        .btn-sm-primary:hover { background: #1558b0; }

        /* ---- период-карточка ---- */
        .period-card {
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 4px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .period-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 20px;
            padding: 14px 18px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            user-select: none;
        }
        .period-header:hover { background: #f0f4fb; }
        .period-title {
            font-family: "Segoe UI", system-ui, sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--text);
            flex: 1 1 160px;
        }
        .period-meta {
            font-family: "Segoe UI", system-ui, sans-serif;
            font-size: 0.82rem;
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
        }
        .period-meta span strong { color: var(--text); }
        .period-chevron {
            font-size: 0.85rem;
            color: var(--muted);
            transition: transform .2s;
        }
        .period-chevron.open { transform: rotate(180deg); }
        .period-body { display: none; }
        .period-body.open { display: block; }
        .period-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }
        .period-table-wrap { overflow-x: auto; }

        /* ---- статы ---- */
        .salary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .salary-stat {
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 14px 16px;
            font-family: "Segoe UI", system-ui, sans-serif;
        }
        .salary-stat .num { font-size: 1.3rem; font-weight: 700; }
        .salary-stat .lbl { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
        .salary-stat.ok   { border-color: var(--ok-border); background: var(--ok-bg); }
        .salary-stat.warn { border-color: #ffe082; background: #fff8e1; }

        /* ---- таблица ---- */
        .salary-table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
        .salary-table th, .salary-table td {
            text-align: left; padding: 9px 10px;
            border-bottom: 1px solid var(--border); vertical-align: middle;
        }
        .salary-table th { font-weight: 600; color: var(--muted); font-size: 0.74rem; text-transform: uppercase; }
        .salary-table tr:last-child td { border-bottom: none; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }

        /* ---- генерация ---- */
        .gen-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .gen-form .field { margin-bottom: 0; }
        .gen-field-label { font-size: 0.8rem; font-weight: 600; color: var(--muted); display: block; margin-bottom: 5px; }

        /* ---- ручная форма ---- */
        .collapse-toggle { cursor: pointer; color: var(--focus); font-size: 0.85rem; font-weight: 600; text-decoration: none; font-family: "Segoe UI", system-ui, sans-serif; }
        .collapse-body { display: none; margin-top: 14px; }
        .collapse-body.open { display: block; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page">
        <?php if ($flash_error): ?>
            <div class="flash flash-err"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
            <div class="flash flash-ok"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>

        <h1 class="sans">Зарплаты сотрудников</h1>

        <!-- ===== СТАТИСТИКА ===== -->
        <?php if (!empty($stats)): ?>
        <div class="salary-stats sans">
            <div class="salary-stat warn">
                <div class="num"><?php echo (int)($stats['draft_cnt'] ?? 0); ?></div>
                <div class="lbl">Черновиков</div>
            </div>
            <div class="salary-stat">
                <div class="num"><?php echo fmt_money($stats['total_draft_amount'] ?? 0); ?></div>
                <div class="lbl">Сумма на согласовании</div>
            </div>
            <div class="salary-stat">
                <div class="num"><?php echo (int)($stats['approved_cnt'] ?? 0); ?></div>
                <div class="lbl">Утверждено</div>
            </div>
            <div class="salary-stat">
                <div class="num"><?php echo fmt_money($stats['total_approved_amount'] ?? 0); ?></div>
                <div class="lbl">К выплате (утв.)</div>
            </div>
            <div class="salary-stat ok">
                <div class="num"><?php echo fmt_money($stats['total_paid_amount'] ?? 0); ?></div>
                <div class="lbl">Выплачено всего</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== ГЕНЕРАЦИЯ ВЕДОМОСТИ ===== -->
        <section class="card">
            <h2>Генерация ведомости</h2>
            <p class="hint" style="margin-top:0; margin-bottom:14px;">
                Автоматически создаёт черновые записи для всех активных сотрудников с окладом.
                Сотрудники, у которых уже есть запись за выбранный период, пропускаются.
            </p>
            <form method="post" action="" class="gen-form">
                <input type="hidden" name="action" value="generate_payroll">
                <div>
                    <label class="gen-field-label" for="gen_start">Начало периода</label>
                    <input id="gen_start" name="period_start" type="date" style="width:auto;"
                           value="<?php echo date('Y-m-01'); ?>" required>
                </div>
                <div>
                    <label class="gen-field-label" for="gen_end">Конец периода</label>
                    <input id="gen_end" name="period_end" type="date" style="width:auto;"
                           value="<?php echo date('Y-m-t'); ?>" required>
                </div>
                <div>
                    <label class="gen-field-label">&nbsp;</label>
                    <button type="submit" class="btn-submit" style="margin-top:0;"
                        onclick="return confirm('Сформировать ведомость за выбранный период?')">
                        Сформировать ведомость
                    </button>
                </div>
            </form>

            <?php if (!empty($employees)): ?>
                <p class="hint" style="margin-top: 14px;">
                    Активные сотрудники с окладом:
                    <?php
                    $with_salary = array_filter($employees, fn($e) => (float)($e['salary'] ?? 0) > 0);
                    echo implode(', ', array_map(fn($e) => htmlspecialchars($e['full_name'] . ' (' . fmt_money($e['salary']) . ')'), $with_salary));
                    ?>
                </p>
            <?php endif; ?>

            <!-- Ручное добавление (свёрнуто) -->
            <div style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px;">
                <a class="collapse-toggle" onclick="toggleManual()" href="#" id="manual-toggle">▶ Добавить запись вручную</a>
                <div class="collapse-body" id="manual-form">
                    <?php if (empty($employees)): ?>
                        <p class="empty">Нет сотрудников. <a href="employees.php" style="color:var(--focus);">Добавьте сотрудников</a>.</p>
                    <?php else: ?>
                        <form method="post" action="" style="margin-top:0;">
                            <input type="hidden" name="action" value="create_salary">
                            <div class="row2">
                                <div class="field">
                                    <label for="manual_emp">Сотрудник <span style="color:red">*</span></label>
                                    <select id="manual_emp" name="employee_id" required>
                                        <option value="">Выберите</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <?php
                                            $rl = $ROLE_LABELS[$emp['role'] ?? ''] ?? ($emp['role'] ?? '');
                                            $sal = (float)($emp['salary'] ?? 0) > 0 ? ' · ' . fmt_money($emp['salary']) : '';
                                            $inactive = ((int)($emp['is_active'] ?? 0)) ? '' : ' [деактивирован]';
                                            ?>
                                            <option value="<?php echo (int)$emp['id']; ?>"
                                                data-salary="<?php echo (float)($emp['salary'] ?? 0); ?>">
                                                <?php echo htmlspecialchars($emp['full_name'] . ' · ' . $rl . $sal . $inactive); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="manual_amount">Сумма (руб.) <span style="color:red">*</span></label>
                                    <input id="manual_amount" name="amount" type="number" min="1" step="100" required placeholder="70000">
                                </div>
                            </div>
                            <div class="row2">
                                <div class="field">
                                    <label for="manual_start">Период с <span style="color:red">*</span></label>
                                    <input id="manual_start" name="period_start" type="date" required value="<?php echo date('Y-m-01'); ?>">
                                </div>
                                <div class="field">
                                    <label for="manual_end">Период по <span style="color:red">*</span></label>
                                    <input id="manual_end" name="period_end" type="date" required value="<?php echo date('Y-m-t'); ?>">
                                </div>
                            </div>
                            <div class="field">
                                <label for="manual_comment">Комментарий</label>
                                <input id="manual_comment" name="comment" type="text" maxlength="255" placeholder="Необязательно">
                            </div>
                            <button type="submit" class="btn-submit">Создать запись</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ===== ВЕДОМОСТИ ПО ПЕРИОДАМ ===== -->
        <h2 class="sans" style="margin-top: 28px; margin-bottom: 12px;">Ведомости по периодам</h2>

        <?php if (empty($periods_data)): ?>
            <p class="empty">Зарплатных записей пока нет. Сформируйте первую ведомость выше.</p>
        <?php else: ?>
            <?php foreach ($periods_data as $i => $period): ?>
                <?php
                $pkey   = $period['period_start'] . '_' . $period['period_end'];
                $detail = $details[$pkey] ?? [];
                $cnt_draft    = (int)($period['cnt_draft']    ?? 0);
                $cnt_approved = (int)($period['cnt_approved'] ?? 0);
                $cnt_paid     = (int)($period['cnt_paid']     ?? 0);
                $cnt_rejected = (int)($period['cnt_rejected'] ?? 0);
                $total_amount = (float)($period['total_amount'] ?? 0);
                $paid_amount  = (float)($period['paid_amount']  ?? 0);
                $approved_amount = (float)($period['approved_amount'] ?? 0);
                $total_records   = (int)($period['total_records']    ?? 0);
                $is_open = ($i === 0); // первый период открыт
                $pid = 'period-' . str_replace([' ', '-'], '_', $pkey);
                ?>
                <div class="period-card">
                    <div class="period-header" onclick="togglePeriod('<?php echo $pid; ?>')">
                        <div class="period-title"><?php echo fmt_period($period['period_start'], $period['period_end']); ?></div>
                        <div class="period-meta">
                            <span><strong><?php echo $total_records; ?></strong> чел.</span>
                            <span>Итого: <strong><?php echo fmt_money($total_amount); ?></strong></span>
                            <?php if ($cnt_draft > 0): ?>
                                <span class="badge badge-warn"><?php echo $cnt_draft; ?> черновик</span>
                            <?php endif; ?>
                            <?php if ($cnt_approved > 0): ?>
                                <span class="badge"><?php echo $cnt_approved; ?> утв. — <?php echo fmt_money($approved_amount); ?></span>
                            <?php endif; ?>
                            <?php if ($cnt_paid > 0): ?>
                                <span class="badge badge-done"><?php echo $cnt_paid; ?> выплачено — <?php echo fmt_money($paid_amount); ?></span>
                            <?php endif; ?>
                            <?php if ($cnt_rejected > 0): ?>
                                <span class="badge"><?php echo $cnt_rejected; ?> откл.</span>
                            <?php endif; ?>
                        </div>
                        <span class="period-chevron <?php echo $is_open ? 'open' : ''; ?>" id="<?php echo $pid; ?>-chev">▼</span>
                    </div>

                    <div class="period-body <?php echo $is_open ? 'open' : ''; ?>" id="<?php echo $pid; ?>">

                        <!-- Групповые действия -->
                        <div class="period-actions">
                            <span class="hint" style="align-self:center; margin-right:4px;">Все за период:</span>

                            <?php if ($cnt_draft > 0): ?>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="bulk_status">
                                    <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($period['period_start']); ?>">
                                    <input type="hidden" name="period_end"   value="<?php echo htmlspecialchars($period['period_end']); ?>">
                                    <input type="hidden" name="from_status"  value="draft">
                                    <input type="hidden" name="to_status"    value="approved">
                                    <button type="submit" class="btn-sm btn-sm-ok"
                                        onclick="return confirm('Утвердить все черновики за период?')">
                                        Утвердить черновики (<?php echo $cnt_draft; ?>)
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($cnt_approved > 0): ?>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="bulk_status">
                                    <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($period['period_start']); ?>">
                                    <input type="hidden" name="period_end"   value="<?php echo htmlspecialchars($period['period_end']); ?>">
                                    <input type="hidden" name="from_status"  value="approved">
                                    <input type="hidden" name="to_status"    value="paid">
                                    <button type="submit" class="btn-sm btn-sm-primary"
                                        onclick="return confirm('Отметить все утверждённые как Выплачено за период?')">
                                        Выплатить утверждённые (<?php echo $cnt_approved; ?>) — <?php echo fmt_money($approved_amount); ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($cnt_draft === 0 && $cnt_approved === 0): ?>
                                <span class="hint">Все записи в финальных статусах.</span>
                            <?php endif; ?>
                        </div>

                        <!-- Детальная таблица -->
                        <div class="period-table-wrap">
                            <table class="salary-table sans">
                                <thead>
                                    <tr>
                                        <th>Сотрудник</th>
                                        <th>Роль</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th>Утверждено</th>
                                        <th>Выплачено</th>
                                        <th>Комментарий</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detail as $rec): ?>
                                        <?php
                                        $rid  = (int)($rec['id'] ?? 0);
                                        $st   = (string)($rec['status'] ?? '');
                                        $badge = $STATUS_BADGE[$st] ?? 'badge';
                                        $transitions = $STATUS_TRANSITIONS[$st] ?? [];
                                        $rl = $ROLE_LABELS[$rec['employee_role'] ?? ''] ?? ($rec['employee_role'] ?? '');
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($rec['employee_name'] ?? ''); ?></strong></td>
                                            <td><span class="badge"><?php echo htmlspecialchars($rl); ?></span></td>
                                            <td><strong><?php echo fmt_money($rec['amount']); ?></strong></td>
                                            <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($STATUS_LABELS[$st] ?? $st); ?></span></td>
                                            <td>
                                                <?php if (!empty($rec['approved_at'])): ?>
                                                    <span class="hint"><?php echo htmlspecialchars((string)$rec['approved_at']); ?></span>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($rec['paid_at'])): ?>
                                                    <span class="hint"><?php echo htmlspecialchars((string)$rec['paid_at']); ?></span>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                            <td><span class="hint"><?php echo htmlspecialchars((string)($rec['comment'] ?? '—')); ?></span></td>
                                            <td>
                                                <?php if (!empty($transitions)): ?>
                                                    <div class="action-group">
                                                        <?php foreach ($transitions as $next_st): ?>
                                                            <?php
                                                            $bc = 'btn-sm';
                                                            if ($next_st === SalaryRecord::STATUS_APPROVED || $next_st === SalaryRecord::STATUS_PAID) $bc .= ' btn-sm-ok';
                                                            if ($next_st === SalaryRecord::STATUS_REJECTED) $bc .= ' btn-sm-danger';
                                                            $lbl = match($next_st) {
                                                                SalaryRecord::STATUS_APPROVED => 'Утвердить',
                                                                SalaryRecord::STATUS_PAID     => 'Выплатить',
                                                                SalaryRecord::STATUS_REJECTED => 'Отклонить',
                                                                default => $STATUS_LABELS[$next_st] ?? $next_st,
                                                            };
                                                            ?>
                                                            <form method="post" action="" style="display:inline;">
                                                                <input type="hidden" name="action"     value="set_status">
                                                                <input type="hidden" name="record_id"  value="<?php echo $rid; ?>">
                                                                <input type="hidden" name="new_status" value="<?php echo htmlspecialchars($next_st); ?>">
                                                                <button type="submit" class="<?php echo $bc; ?>"
                                                                    <?php if ($next_st === SalaryRecord::STATUS_REJECTED): ?>
                                                                        onclick="return confirm('Отклонить запись?')"
                                                                    <?php endif; ?>>
                                                                    <?php echo htmlspecialchars($lbl); ?>
                                                                </button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="hint">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function togglePeriod(id) {
        var body = document.getElementById(id);
        var chev = document.getElementById(id + '-chev');
        if (!body) return;
        var open = body.classList.toggle('open');
        if (chev) chev.classList.toggle('open', open);
    }

    function toggleManual() {
        var form = document.getElementById('manual-form');
        var toggle = document.getElementById('manual-toggle');
        if (!form) return;
        var open = form.classList.toggle('open');
        if (toggle) toggle.textContent = (open ? '▼' : '▶') + ' Добавить запись вручную';
        return false;
    }

    // При выборе сотрудника — подставляем его оклад в поле суммы
    (function () {
        var empSel = document.getElementById('manual_emp');
        var amtInp = document.getElementById('manual_amount');
        if (!empSel || !amtInp) return;
        empSel.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt) {
                var sal = parseFloat(opt.getAttribute('data-salary') || '0');
                if (sal > 0) amtInp.value = sal;
            }
        });
    })();

    // Быстрые кнопки для выбора месяца
    (function () {
        function lastDayOfMonth(y, m) {
            return new Date(y, m, 0).getDate();
        }
        function pad(n) { return String(n).padStart(2, '0'); }

        var genStart = document.getElementById('gen_start');
        var genEnd   = document.getElementById('gen_end');
        if (!genStart || !genEnd) return;

        genStart.addEventListener('change', function () {
            var d = new Date(this.value);
            if (!isNaN(d)) {
                var y = d.getUTCFullYear(), m = d.getUTCMonth() + 1;
                genEnd.value = y + '-' + pad(m) + '-' + pad(lastDayOfMonth(y, m));
            }
        });
    })();
    </script>
</body>
</html>
