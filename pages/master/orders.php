<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_master_section = 'orders';

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW           => 'Новая',
    Order::STATUS_ASSIGNED      => 'Назначен механик',
    Order::STATUS_IN_PROGRESS   => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED     => 'Завершена',
    Order::STATUS_CANCELLED     => 'Отменена',
];

$flash_error   = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reassign_mechanic') {
    $order_id    = (int) ($_POST['order_id']    ?? 0);
    $mechanic_id = (int) ($_POST['mechanic_id'] ?? 0);
    $comment     = trim($_POST['comment'] ?? '');

    if ($order_id <= 0 || $mechanic_id <= 0) {
        $flash_error = 'Укажите заявку и нового механика.';
    } else {
        $r = $master_controller->reassignMechanic($order_id, $mechanic_id, $master_id, $comment);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось переназначить механика.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Механик переназначен.';
        }
    }
}

$orders = [];
$r = $master_controller->getAllOrders();
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

$mechanics = [];
$mech_r = $master_controller->getMechanics();
if ($mech_r['success'] ?? false) {
    $mechanics = $mech_r['data']['mechanics'] ?? [];
}

function master_status_label($status, array $labels)
{
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все заявки — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .reassign-wrap { margin-top: 6px; }
        .reassign-wrap summary {
            cursor: pointer; font-size: 0.75rem; color: var(--focus);
            font-weight: 600; user-select: none; list-style: none;
        }
        .reassign-wrap summary::-webkit-details-marker { display: none; }
        .reassign-wrap summary::before { content: '✎ '; }
        .reassign-form {
            margin-top: 6px; padding: 10px 12px;
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 6px; background: #f9fafb;
            display: flex; flex-direction: column; gap: 6px;
            min-width: 200px;
        }
        .reassign-form select,
        .reassign-form input[type="text"] {
            font-size: 0.82rem; padding: 4px 8px;
            border: 1px solid #d1d5db; border-radius: 4px;
            font-family: inherit; width: 100%; box-sizing: border-box;
        }
        .reassign-form button {
            padding: 4px 12px; font-size: 0.82rem; font-weight: 600;
            border: none; border-radius: 4px; cursor: pointer;
            background: var(--focus); color: #fff; font-family: inherit;
            align-self: flex-start;
        }
        .reassign-form button:hover { opacity: .88; }
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

        <?php if (empty($orders)): ?>
            <p class="empty">Заявок в системе пока нет.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="sans">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Клиент</th>
                            <th>Авто</th>
                            <th>Описание</th>
                            <th>Услуги</th>
                            <th>Статус</th>
                            <th>Механик</th>
                            <th>Мастер</th>
                            <th>Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $oid = (int) ($o['id'] ?? 0);
                            $st  = $o['status'] ?? '';
                            $is_terminal = in_array($st, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
                            $has_mechanic = (bool) ($o['mechanic_name'] ?? '');
                            $can_reassign = !$is_terminal && $has_mechanic && !empty($mechanics);

                            $badge_class = 'badge';
                            if ($st === Order::STATUS_COMPLETED) {
                                $badge_class .= ' badge-done';
                            } elseif (in_array($st, [Order::STATUS_WAITING_PARTS, Order::STATUS_NEW, Order::STATUS_ASSIGNED], true)) {
                                $badge_class .= ' badge-warn';
                            }

                            $price = isset($o['total_price'])
                                ? number_format((float) $o['total_price'], 0, '.', ' ') . ' ₽'
                                : '—';
                            $desc = (string) ($o['description'] ?? '');
                            $desc_short = mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '…' : $desc;
                            ?>
                            <tr>
                                <td><?php echo $oid; ?></td>
                                <td><?php echo htmlspecialchars($o['client_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))); ?></td>
                                <td><span class="hint"><?php echo htmlspecialchars($desc_short); ?></span></td>
                                <td><span class="hint"><?php echo htmlspecialchars((string) ($o['services_summary'] ?? '') ?: '—'); ?></span></td>
                                <td><span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars(master_status_label($st, $ORDER_STATUS_LABELS)); ?></span></td>
                                <td>
                                    <?php echo $has_mechanic ? htmlspecialchars($o['mechanic_name']) : '—'; ?>
                                    <?php if ($can_reassign): ?>
                                        <details class="reassign-wrap">
                                            <summary>Переназначить</summary>
                                            <form method="post" action="" class="reassign-form">
                                                <input type="hidden" name="action"   value="reassign_mechanic">
                                                <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                                <select name="mechanic_id" required>
                                                    <option value="">— новый механик —</option>
                                                    <?php foreach ($mechanics as $m): ?>
                                                        <option value="<?php echo (int) $m['id']; ?>">
                                                            <?php echo htmlspecialchars($m['full_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" name="comment" maxlength="500"
                                                       placeholder="Причина (необязательно)">
                                                <button type="submit">Назначить</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($o['master_name']) ? htmlspecialchars($o['master_name']) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($price); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
