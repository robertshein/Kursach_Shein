<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_admin_section = 'orders';

$flash_error   = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_status') {
    $order_id   = (int) ($_POST['order_id']   ?? 0);
    $new_status = (string) ($_POST['new_status'] ?? '');
    if ($order_id <= 0 || $new_status === '') {
        $flash_error = 'Некорректные данные.';
    } else {
        $r = $admin_controller->forceOrderStatus($order_id, $new_status);
        $flash_error   = ($r['success'] ?? false) ? null : ($r['message'] ?? 'Ошибка.');
        $flash_success = ($r['success'] ?? false) ? 'Статус заявки #' . $order_id . ' изменён.' : null;
    }
}

$all_orders = [];
$r = $admin_controller->getAllOrders();
if ($r['success'] ?? false) {
    $all_orders = $r['data']['orders'] ?? [];
}

$STATUS_LABELS = [
    Order::STATUS_NEW           => 'Новая',
    Order::STATUS_ASSIGNED      => 'Назначен механик',
    Order::STATUS_IN_PROGRESS   => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED     => 'Завершена',
    Order::STATUS_CANCELLED     => 'Отменена',
];

$STATUS_BADGE = [
    Order::STATUS_NEW           => 'badge badge-warn',
    Order::STATUS_ASSIGNED      => 'badge badge-warn',
    Order::STATUS_IN_PROGRESS   => 'badge badge-done',
    Order::STATUS_WAITING_PARTS => 'badge badge-warn',
    Order::STATUS_COMPLETED     => 'badge',
    Order::STATUS_CANCELLED     => 'badge',
];

$filter = $_GET['status'] ?? '';
if (!array_key_exists($filter, $STATUS_LABELS)) {
    $filter = '';
}

$counts = [];
foreach (array_keys($STATUS_LABELS) as $st) {
    $counts[$st] = 0;
}
foreach ($all_orders as $o) {
    $st = $o['status'] ?? '';
    if (isset($counts[$st])) $counts[$st]++;
}

$orders = ($filter === '')
    ? $all_orders
    : array_values(array_filter($all_orders, fn($o) => ($o['status'] ?? '') === $filter));

$terminal = [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .filter-btn {
            padding: 6px 14px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: #fff;
            color: var(--text);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-family: "Segoe UI", system-ui, sans-serif;
            transition: background .15s;
        }
        .filter-btn:hover { background: #f0f4fb; }
        .filter-btn.active { background: var(--focus); color: #fff; border-color: var(--focus); }
        .filter-btn .cnt {
            display: inline-block;
            background: rgba(0,0,0,.12);
            border-radius: 10px;
            padding: 0 6px;
            font-size: 0.75rem;
            margin-left: 4px;
        }
        .filter-btn.active .cnt { background: rgba(255,255,255,.3); }
        .order-desc {
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .inline-form { display: inline; }
        .btn-sm {
            padding: 4px 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #fff;
            color: var(--focus);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-sm:hover { background: #f0f4fb; }
        .btn-sm-danger { color: var(--danger-text); border-color: var(--danger-border); }
        .btn-sm-danger:hover { background: var(--danger-bg); }
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-chip {
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 16px;
            text-align: center;
            min-width: 100px;
            font-family: "Segoe UI", system-ui, sans-serif;
        }
        .stat-chip .num { font-size: 1.5rem; font-weight: 700; color: var(--focus); line-height: 1.1; }
        .stat-chip .lbl { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }
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

        <h1 class="sans">Все заявки</h1>
        <p class="lead">Полный список заявок системы. Можно принудительно изменить статус любой незавершённой заявки.</p>

        <div class="stats-row">
            <?php foreach ($STATUS_LABELS as $st => $lbl): ?>
                <div class="stat-chip">
                    <div class="num"><?php echo $counts[$st]; ?></div>
                    <div class="lbl"><?php echo $lbl; ?></div>
                </div>
            <?php endforeach; ?>
            <div class="stat-chip">
                <div class="num"><?php echo count($all_orders); ?></div>
                <div class="lbl">Всего</div>
            </div>
        </div>

        <div class="filter-bar">
            <a href="orders.php" class="filter-btn <?php echo $filter === '' ? 'active' : ''; ?>">
                Все <span class="cnt"><?php echo count($all_orders); ?></span>
            </a>
            <?php foreach ($STATUS_LABELS as $st => $lbl): ?>
                <?php if ($counts[$st] > 0 || $filter === $st): ?>
                    <a href="orders.php?status=<?php echo urlencode($st); ?>"
                       class="filter-btn <?php echo $filter === $st ? 'active' : ''; ?>">
                        <?php echo $lbl; ?>
                        <span class="cnt"><?php echo $counts[$st]; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <section class="card">
            <h2>
                <?php echo $filter === '' ? 'Все заявки' : $STATUS_LABELS[$filter]; ?>
                <span style="font-weight:400; font-size:0.9rem; color:var(--muted);">(<?php echo count($orders); ?>)</span>
            </h2>

            <?php if (empty($orders)): ?>
                <p class="empty">Заявок не найдено.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Клиент</th>
                                <th>Автомобиль</th>
                                <th>Мастер</th>
                                <th>Механик</th>
                                <th>Статус</th>
                                <th>Услуги</th>
                                <th>Сумма, ₽</th>
                                <th>Создана</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $oid    = (int) ($order['id'] ?? 0);
                                $st     = (string) ($order['status'] ?? '');
                                $badge  = $STATUS_BADGE[$st] ?? 'badge';
                                $is_terminal = in_array($st, $terminal, true);
                                $car    = trim(($order['brand'] ?? '') . ' ' . ($order['model'] ?? ''));
                                $total  = (float) ($order['total_price'] ?? 0);
                                $created = substr((string) ($order['created_at'] ?? ''), 0, 10);
                                ?>
                                <tr>
                                    <td><strong><?php echo $oid; ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['client_name'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($car); ?><br>
                                        <span class="hint"><?php echo htmlspecialchars($order['gosnumber'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) ($order['master_name'] ?? '—')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($order['mechanic_name'] ?? '—')); ?></td>
                                    <td><span class="<?php echo $badge; ?>"><?php echo $STATUS_LABELS[$st] ?? $st; ?></span></td>
                                    <td>
                                        <?php if (!empty($order['services_summary'])): ?>
                                            <span class="hint" style="font-size:0.8rem;"><?php echo htmlspecialchars($order['services_summary']); ?></span>
                                        <?php else: ?>
                                            <span class="hint">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $total > 0 ? number_format($total, 0, '.', ' ') : '—'; ?></td>
                                    <td><span class="hint"><?php echo $created; ?></span></td>
                                    <td>
                                        <?php if (!$is_terminal): ?>
                                            <form method="post" action="" class="inline-form">
                                                <input type="hidden" name="action"     value="force_status">
                                                <input type="hidden" name="order_id"   value="<?php echo $oid; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo Order::STATUS_CANCELLED; ?>">
                                                <button type="submit" class="btn-sm btn-sm-danger"
                                                    onclick="return confirm('Принудительно отменить заявку #<?php echo $oid; ?>?')">
                                                    Отменить
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="hint">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($order['description'])): ?>
                                    <tr>
                                        <td></td>
                                        <td colspan="9" class="order-desc">
                                            <?php echo htmlspecialchars($order['description']); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
