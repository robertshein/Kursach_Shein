<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_mechanic_section = 'orders';

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW => 'Новая',
    Order::STATUS_ASSIGNED => 'Назначена',
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

$AVAILABLE_STATUS_OPTIONS = [
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

$flash_error = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $new_status = (string) ($_POST['status'] ?? '');
        $r = $mechanic_controller->updateOrderStatus($order_id, $mechanic_id, $new_status);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось изменить статус.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Статус обновлен.';
        }
    } elseif ($action === 'add_service') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);
        $comment = (string) ($_POST['service_comment'] ?? '');
        $r = $mechanic_controller->addServiceToOrder($order_id, $mechanic_id, $service_id, $quantity, $comment);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось добавить услугу.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Услуга добавлена.';
        }
    }
}

$services = [];
$services_r = $mechanic_controller->getServices();
if ($services_r['success'] ?? false) {
    $services = $services_r['data']['services'] ?? [];
}

$orders = [];
$r = $mechanic_controller->getMyOrders($mechanic_id);
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

function mechanic_status_label($status, array $labels)
{
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заявки — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
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

        <h1 class="sans">Мои заявки</h1>
        <p class="lead">Список заявок, назначенных вам мастером. Вы можете обновлять их статус по мере выполнения работ.</p>

        <?php if (empty($orders)): ?>
            <p class="empty">У вас пока нет назначенных заявок.</p>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php
                $oid = (int) ($o['id'] ?? 0);
                $st = (string) ($o['status'] ?? '');
                $is_terminal = in_array($st, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
                $price = isset($o['total_price']) ? number_format((float) $o['total_price'], 0, '.', ' ') . ' ₽' : '—';
                ?>
                <section class="card sans">
                    <h2>Заявка № <?php echo $oid; ?></h2>
                    <p class="hint" style="margin-top: 0;">
                        <strong>Клиент:</strong> <?php echo htmlspecialchars($o['client_name'] ?? ''); ?>
                    </p>
                    <p class="hint">
                        <strong>Авто:</strong> <?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))); ?>
                    </p>
                    <?php if (!empty($o['master_comment'])): ?>
                        <p class="hint"><strong>Комментарий мастера:</strong> <?php echo nl2br(htmlspecialchars((string) $o['master_comment'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($o['services_summary'])): ?>
                        <p class="hint"><strong>Услуги:</strong> <?php echo htmlspecialchars((string) $o['services_summary']); ?></p>
                    <?php endif; ?>
                    <p><strong>Описание:</strong> <?php echo nl2br(htmlspecialchars((string) ($o['description'] ?? ''))); ?></p>
                    <p class="hint"><strong>Итоговая сумма:</strong> <?php echo htmlspecialchars($price); ?></p>

                    <form method="post" action="">
                        <input type="hidden" name="action" value="add_service">
                        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                        <div class="row2">
                            <div class="field">
                                <label for="service_<?php echo $oid; ?>">Добавить услугу</label>
                                <select id="service_<?php echo $oid; ?>" name="service_id" required <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                                    <option value="">Выберите</option>
                                    <?php foreach ($services as $service): ?>
                                        <?php
                                        $service_name = (string) ($service['name'] ?? '');
                                        $service_price = isset($service['price'])
                                            ? number_format((float) $service['price'], 0, '.', ' ') . ' ₽'
                                            : '—';
                                        ?>
                                        <option value="<?php echo (int) ($service['id'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($service_name . ' · ' . $service_price); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="qty_<?php echo $oid; ?>">Количество</label>
                                <input id="qty_<?php echo $oid; ?>" type="number" name="quantity" min="1" max="99" value="1" required <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="field">
                            <label for="svc_comment_<?php echo $oid; ?>">Комментарий к услуге</label>
                            <input id="svc_comment_<?php echo $oid; ?>" type="text" name="service_comment" maxlength="255" placeholder="Необязательно" <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                        </div>
                        <?php if (empty($services)): ?>
                            <p class="hint">Справочник услуг пуст. Добавьте услуги в систему.</p>
                        <?php endif; ?>
                        <button type="submit" class="btn-submit" <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>Добавить услугу</button>
                    </form>

                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                        <div class="row2">
                            <div class="field">
                                <label for="status_<?php echo $oid; ?>">Текущий статус</label>
                                <input id="status_<?php echo $oid; ?>" type="text" value="<?php echo htmlspecialchars(mechanic_status_label($st, $ORDER_STATUS_LABELS)); ?>" disabled>
                            </div>
                            <div class="field">
                                <label for="new_status_<?php echo $oid; ?>">Новый статус</label>
                                <select id="new_status_<?php echo $oid; ?>" name="status" required <?php echo $is_terminal ? 'disabled' : ''; ?>>
                                    <option value="">Выберите</option>
                                    <?php foreach ($AVAILABLE_STATUS_OPTIONS as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" <?php echo $is_terminal ? 'disabled' : ''; ?>>
                            <?php echo $is_terminal ? 'Статус зафиксирован' : 'Сохранить статус'; ?>
                        </button>
                    </form>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
