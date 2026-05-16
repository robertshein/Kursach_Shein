<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_mechanic_section = 'archive';

$ORDER_STATUS_LABELS = [
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

$orders = [];
$r = $mechanic_controller->getMyArchivedOrders($mechanic_id);
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

/* Подсчёт итогов */
$total_completed = 0;
$total_cancelled = 0;
$total_revenue   = 0.0;
foreach ($orders as $o) {
    if ($o['status'] === Order::STATUS_COMPLETED) {
        $total_completed++;
        $total_revenue += (float) ($o['total_price'] ?? 0);
    } else {
        $total_cancelled++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Архив заявок — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .badge {
            display: inline-block; padding: 2px 9px; border-radius: 10px;
            font-size: 0.78rem; font-weight: 600; line-height: 1.5;
        }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-cancelled  { background: #fee2e2; color: #b91c1c; }
        .archive-stats {
            display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
        }
        .archive-stat {
            background: var(--card-bg, #fff); border: 1px solid var(--border, #e5e7eb);
            border-radius: 8px; padding: 12px 20px; min-width: 130px; text-align: center;
        }
        .archive-stat .num { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .archive-stat .lbl { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
        .archive-card {
            border: 1px solid var(--border, #e5e7eb);
            border-radius: 8px; padding: 16px 20px; margin-bottom: 12px;
            background: var(--card-bg, #fff);
        }
        .archive-card h2 { margin: 0 0 6px; font-size: 1rem; }
        .archive-card .meta { color: #6b7280; font-size: 0.85rem; margin: 2px 0; }
        .archive-card .summary { margin-top: 8px; font-size: 0.88rem; }
        .archive-card .summary strong { color: #374151; }
        .section-sep { border: none; border-top: 1px solid var(--border, #e5e7eb); margin: 10px 0; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page sans">
        <h1>Архив заявок</h1>
        <p class="lead">
            Завершённые и отменённые заявки. Изменения недоступны.
            Активные заявки — на странице <a href="orders.php" style="color:var(--focus);font-weight:600;text-decoration:none;">Мои заявки</a>.
        </p>

        <?php if (empty($orders)): ?>
            <p class="empty">В архиве пока нет заявок.</p>
        <?php else: ?>
            <div class="archive-stats">
                <div class="archive-stat">
                    <div class="num"><?php echo count($orders); ?></div>
                    <div class="lbl">Всего в архиве</div>
                </div>
                <div class="archive-stat">
                    <div class="num"><?php echo $total_completed; ?></div>
                    <div class="lbl">Завершено</div>
                </div>
                <div class="archive-stat">
                    <div class="num"><?php echo $total_cancelled; ?></div>
                    <div class="lbl">Отменено</div>
                </div>
                <div class="archive-stat">
                    <div class="num"><?php echo number_format($total_revenue, 0, '.', ' '); ?> ₽</div>
                    <div class="lbl">Выручка по завершённым</div>
                </div>
            </div>

            <?php foreach ($orders as $o): ?>
                <?php
                $oid    = (int) ($o['id'] ?? 0);
                $st     = (string) ($o['status'] ?? '');
                $label  = $ORDER_STATUS_LABELS[$st] ?? $st;
                $badge  = $st === Order::STATUS_COMPLETED ? 'badge-completed' : 'badge-cancelled';
                $price  = number_format((float) ($o['total_price'] ?? 0), 0, '.', ' ') . ' ₽';
                $start  = $o['start_date'] ? date('d.m.Y', strtotime($o['start_date'])) : '—';
                $end    = $o['end_date']   ? date('d.m.Y', strtotime($o['end_date']))   : '—';
                $created = $o['created_at'] ? date('d.m.Y', strtotime($o['created_at'])) : '—';
                ?>
                <div class="archive-card">
                    <h2>
                        Заявка № <?php echo $oid; ?>
                        <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($label); ?></span>
                    </h2>

                    <p class="meta">
                        <strong>Клиент:</strong> <?php echo htmlspecialchars($o['client_name'] ?? ''); ?>
                        &nbsp;·&nbsp;
                        <strong>Авто:</strong> <?php echo htmlspecialchars(
                            trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))
                        ); ?>
                    </p>
                    <p class="meta">
                        <strong>Создана:</strong> <?php echo $created; ?>
                        &nbsp;·&nbsp;
                        <strong>Начата:</strong> <?php echo $start; ?>
                        &nbsp;·&nbsp;
                        <strong>Завершена:</strong> <?php echo $end; ?>
                        &nbsp;·&nbsp;
                        <strong>Сумма:</strong> <?php echo htmlspecialchars($price); ?>
                    </p>

                    <?php if (!empty($o['description'])): ?>
                        <p class="meta" style="margin-top:6px;">
                            <strong>Описание:</strong> <?php echo nl2br(htmlspecialchars((string) $o['description'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($o['master_comment'])): ?>
                        <p class="meta">
                            <strong>Комментарий мастера:</strong> <?php echo nl2br(htmlspecialchars((string) $o['master_comment'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($o['services_summary']) || !empty($o['parts_summary']) || !empty($o['parts_comment'])): ?>
                        <hr class="section-sep">
                        <div class="summary">
                            <?php if (!empty($o['services_summary'])): ?>
                                <p><strong>Услуги:</strong> <?php echo htmlspecialchars((string) $o['services_summary']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($o['parts_summary'])): ?>
                                <p><strong>Запчасти:</strong> <?php echo htmlspecialchars((string) $o['parts_summary']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($o['parts_comment'])): ?>
                                <p><strong>Комментарий по запчастям:</strong> <?php echo nl2br(htmlspecialchars((string) $o['parts_comment'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
