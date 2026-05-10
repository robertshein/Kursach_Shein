<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_master_section = 'orders';

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW => 'Новая',
    Order::STATUS_ASSIGNED => 'Назначен механик',
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

$orders = [];
$r = $master_controller->getAllOrders();
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
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
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page">
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
                                ? (mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '…' : $desc)
                                : (strlen($desc) > 80 ? substr($desc, 0, 80) . '…' : $desc);
                            ?>
                            <tr>
                                <td><?php echo (int) ($o['id'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($o['client_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))); ?></td>
                                <td><span class="hint"><?php echo htmlspecialchars($desc_short); ?></span></td>
                                <td><span class="hint"><?php echo htmlspecialchars((string) ($o['services_summary'] ?? '') ?: '—'); ?></span></td>
                                <td><span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars(master_status_label($st, $ORDER_STATUS_LABELS)); ?></span></td>
                                <td><?php echo !empty($o['mechanic_name']) ? htmlspecialchars($o['mechanic_name']) : '—'; ?></td>
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
