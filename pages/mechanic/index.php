<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_mechanic_section = 'dashboard';

$orders = [];
$r = $mechanic_controller->getMyOrders($mechanic_id);
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

$archived_orders = [];
$ra = $mechanic_controller->getMyArchivedOrders($mechanic_id);
if ($ra['success'] ?? false) {
    $archived_orders = $ra['data']['orders'] ?? [];
}

$in_progress_count   = 0;
$waiting_parts_count = 0;
$assigned_count      = 0;
foreach ($orders as $o) {
    $st = (string) ($o['status'] ?? '');
    if ($st === Order::STATUS_IN_PROGRESS)   $in_progress_count++;
    elseif ($st === Order::STATUS_WAITING_PARTS) $waiting_parts_count++;
    elseif ($st === Order::STATUS_ASSIGNED)   $assigned_count++;
}
$archived_count = count($archived_orders);
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
                <div class="lbl">Активных заявок</div>
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
                <div class="num"><?php echo $assigned_count; ?></div>
                <div class="lbl">Назначена</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo $archived_count; ?></div>
                <div class="lbl">В архиве</div>
            </div>
        </div>

        <section class="card">
            <h2>Быстрые действия</h2>
            <p class="sans" style="margin:0; display:flex; flex-wrap:wrap; gap:10px;">
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_mechanic_orders_href); ?>"
                   style="display:inline-block; text-decoration:none;">
                    Мои заявки
                    <?php if (count($orders) > 0): ?>
                        <span style="background:#fff;color:var(--focus);border-radius:10px;padding:1px 7px;font-size:0.75rem;margin-left:4px;">
                            <?php echo count($orders); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_mechanic_archive_href); ?>"
                   style="display:inline-block; text-decoration:none; background:#fff; color:var(--focus);">
                    Архив заявок
                    <?php if ($archived_count > 0): ?>
                        <span style="background:var(--focus);color:#fff;border-radius:10px;padding:1px 7px;font-size:0.75rem;margin-left:4px;">
                            <?php echo $archived_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </p>
        </section>
    </div>
</body>
</html>
