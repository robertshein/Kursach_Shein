<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_master_section = 'purchases';

$ORDER_STATUS_LABELS = [
    Order::STATUS_ASSIGNED => 'Назначен механик',
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
];

$flash_error = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_purchase_request') {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $part_id = (int) ($_POST['part_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $comment_param = $comment === '' ? null : $comment;

    if ($order_id <= 0 || $part_id <= 0 || $quantity < 1) {
        $flash_error = 'Выберите заявку и запчасть, укажите количество.';
    } else {
        $r = $master_controller->createPartPurchaseRequest($order_id, $part_id, $quantity, $master_id, $comment_param);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось создать запрос.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Запрос создан. Заявка переведена в «Ожидание запчастей».';
        }
    }
}

$requests = [];
$req_r = $master_controller->getPendingPurchaseRequests();
if ($req_r['success'] ?? false) {
    $requests = $req_r['data']['requests'] ?? [];
}

$orders_for_form = [];
$ord_r = $master_controller->getOrdersForPartRequest();
if ($ord_r['success'] ?? false) {
    $orders_for_form = $ord_r['data']['orders'] ?? [];
}

$parts = [];
$parts_r = $master_controller->getParts();
if ($parts_r['success'] ?? false) {
    $parts = $parts_r['data']['parts'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Запросы на закупку — АвтоПлюс</title>
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

        <h1 class="sans">Запросы на закупку запчастей</h1>
        <p class="lead">Ожидающие решения администратора запросы и форма создания нового запроса по заявке в работе.</p>

        <section class="card">
            <h2>Новый запрос</h2>
            <?php if (empty($orders_for_form)): ?>
                <p class="empty">Нет заявок, для которых можно оформить закупку (нужен назначенный механик). Сначала назначьте механика на <a href="<?php echo htmlspecialchars($nav_master_new_href); ?>" style="color: var(--focus); font-weight: 600;">новой заявке</a>.</p>
            <?php elseif (empty($parts)): ?>
                <p class="empty">В справочнике нет запчастей. Обратитесь к администратору.</p>
            <?php else: ?>
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_purchase_request">
                    <div class="field">
                        <label for="order_id">Заявка</label>
                        <select id="order_id" name="order_id" required>
                            <option value="">Выберите</option>
                            <?php foreach ($orders_for_form as $o): ?>
                                <?php
                                $st = $o['status'] ?? '';
                                $st_lbl = $ORDER_STATUS_LABELS[$st] ?? $st;
                                $label = '#' . (int) ($o['id'] ?? 0) . ' — ' . ($o['client_name'] ?? '') . ' — '
                                    . trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '')) . ' (' . $st_lbl . ')';
                                ?>
                                <option value="<?php echo (int) ($o['id'] ?? 0); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row2">
                        <div class="field">
                            <label for="part_id">Запчасть</label>
                            <select id="part_id" name="part_id" required>
                                <option value="">Выберите</option>
                                <?php foreach ($parts as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>">
                                        <?php echo htmlspecialchars(($p['name'] ?? '') . ' · ' . ($p['article'] ?? '') . ' (на складе: ' . (int) ($p['quantity'] ?? 0) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="quantity">Количество</label>
                            <input id="quantity" name="quantity" type="number" required min="1" max="9999" value="1">
                        </div>
                    </div>
                    <div class="field">
                        <label for="comment">Комментарий</label>
                        <input id="comment" name="comment" type="text" maxlength="500" placeholder="Необязательно">
                    </div>
                    <button type="submit" class="btn-submit">Создать запрос</button>
                    <p class="hint">После создания заявка получит статус «Ожидание запчастей» до решения администратора.</p>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Ожидают решения администратора</h2>
            <?php if (empty($requests)): ?>
                <p class="empty">Таких запросов нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Заявка</th>
                                <th>Запчасть</th>
                                <th>Кол-во</th>
                                <th>Мастер</th>
                                <th>Комментарий</th>
                                <th>Создан</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?php echo (int) ($req['id'] ?? 0); ?></td>
                                    <td><?php echo (int) ($req['order_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($req['part_name'] ?? '') . ' · ' . ($req['article'] ?? ''))); ?></td>
                                    <td><?php echo (int) ($req['quantity'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($req['requested_by_master'] ?? ''); ?></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['comment'] ?? '—')); ?></span></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['created_at'] ?? '')); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
