<?php
require_once __DIR__ . '/_init.php';

$nav_master_section = 'dashboard';

$new_orders = [];
$pending_requests = [];
$new_r = $master_controller->getNewOrders();
if ($new_r['success'] ?? false) {
    $new_orders = $new_r['data']['orders'] ?? [];
}
$pend_r = $master_controller->getPendingPurchaseRequests();
if ($pend_r['success'] ?? false) {
    $pending_requests = $pend_r['data']['requests'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель мастера — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page">
        <h1 class="sans">Панель мастера</h1>

        <div class="stats sans">
            <div class="stat">
                <div class="num"><?php echo count($new_orders); ?></div>
                <div class="lbl">Новых заявок</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo count($pending_requests); ?></div>
                <div class="lbl">Запросов на закупку (ожидают)</div>
            </div>
        </div>

        <section class="card">
            <h2>Быстрые действия</h2>
            <p class="sans" style="margin: 0 0 12px;">
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_master_new_href); ?>" style="display: inline-block; text-decoration: none; margin-right: 8px;">Новые заявки</a>
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_master_orders_href); ?>" style="display: inline-block; text-decoration: none; margin-right: 8px; background: #fff; color: var(--focus);">Все заявки</a>
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_master_purchases_href); ?>" style="display: inline-block; text-decoration: none; background: #fff; color: var(--focus);">Запросы на закупку</a>
            </p>
        </section>
    </div>
</body>
</html>
