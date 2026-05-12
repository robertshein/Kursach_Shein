<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_mechanic_section = 'dashboard';

$orders = [];
$r = $mechanic_controller->getMyOrders($mechanic_id);
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

$in_progress_count = 0;
$waiting_parts_count = 0;
$completed_count = 0;
foreach ($orders as $o) {
    $st = (string) ($o['status'] ?? '');
    if ($st === Order::STATUS_IN_PROGRESS) {
        $in_progress_count++;
    } elseif ($st === Order::STATUS_WAITING_PARTS) {
        $waiting_parts_count++;
    } elseif ($st === Order::STATUS_COMPLETED) {
        $completed_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель механика — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page">
        <h1 class="sans">Панель механика</h1>
        <p class="lead">Здравствуйте, <?php echo htmlspecialchars($mechanic_user['full_name'] ?? ''); ?>. Здесь собраны ваши заявки и текущая загрузка.</p>

        <div class="stats sans">
            <div class="stat">
                <div class="num"><?php echo count($orders); ?></div>
                <div class="lbl">Всего назначено</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo $in_progress_count; ?></div>
                <div class="lbl">В работе</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo $waiting_parts_count; ?></div>
                <div class="lbl">Ожидание запчастей</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo $completed_count; ?></div>
                <div class="lbl">Завершено</div>
            </div>
        </div>

        <section class="card">
            <h2>Рабочая лента</h2>
            <p class="sans" style="margin: 0;">
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_mechanic_orders_href); ?>" style="display:inline-block; text-decoration:none;">Открыть мои заявки</a>
            </p>
        </section>
    </div>
</body>
</html>
