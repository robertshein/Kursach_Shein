<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/PartPurchaseRequest.php';

$nav_admin_section = 'purchases';

$flash_error = null;
$flash_success = null;

$STATUS_LABELS = [
    PartPurchaseRequest::STATUS_PENDING  => 'Ожидает',
    PartPurchaseRequest::STATUS_APPROVED => 'Одобрен',
    PartPurchaseRequest::STATUS_REJECTED => 'Отклонён',
    'ordered'  => 'Заказан',
    'received' => 'Получен',
];

$STATUS_BADGE = [
    PartPurchaseRequest::STATUS_PENDING  => 'badge badge-warn',
    PartPurchaseRequest::STATUS_APPROVED => 'badge badge-done',
    PartPurchaseRequest::STATUS_REJECTED => 'badge',
    'ordered'  => 'badge badge-warn',
    'received' => 'badge badge-done',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decide') {
    $request_id = (int) ($_POST['request_id'] ?? 0);
    $decision   = (string) ($_POST['decision'] ?? '');
    $comment    = trim($_POST['comment'] ?? '');
    $approve    = ($decision === 'approve');

    if ($request_id <= 0) {
        $flash_error = 'Некорректный запрос.';
    } elseif (!in_array($decision, ['approve', 'reject'], true)) {
        $flash_error = 'Некорректное решение.';
    } else {
        $comment_param = $comment === '' ? null : $comment;
        $r = $admin_controller->decidePurchaseRequest($request_id, $admin_id, $approve, $comment_param);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось обработать запрос.';
        } else {
            $flash_success = $approve ? 'Запрос одобрен.' : 'Запрос отклонён.';
        }
    }
}

$requests = [];
$r = $admin_controller->getAllPurchaseRequests();
if ($r['success'] ?? false) {
    $requests = $r['data']['requests'] ?? [];
}

$pending = array_filter($requests, fn($req) => ($req['status'] ?? '') === PartPurchaseRequest::STATUS_PENDING);
$resolved = array_filter($requests, fn($req) => ($req['status'] ?? '') !== PartPurchaseRequest::STATUS_PENDING);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Запросы на закупку — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .decide-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-sm {
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-approve { color: var(--ok-text); border-color: var(--ok-border); }
        .btn-approve:hover { background: var(--ok-bg); }
        .btn-reject  { color: var(--danger-text); border-color: var(--danger-border); }
        .btn-reject:hover { background: var(--danger-bg); }
        .comment-inline { width: 180px; padding: 5px 8px; font-size: 0.82rem; }
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

        <h1 class="sans">Запросы на закупку запчастей</h1>
        <p class="lead">Одобряйте или отклоняйте запросы мастеров на покупку запчастей для заявок.</p>

        <!-- Ожидающие решения -->
        <section class="card">
            <h2>Ожидают вашего решения <?php if (!empty($pending)): ?><span class="badge badge-warn"><?php echo count($pending); ?></span><?php endif; ?></h2>
            <?php if (empty($pending)): ?>
                <p class="empty">Запросов, ожидающих решения, нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Заявка</th>
                                <th>Запчасть</th>
                                <th>Цена</th>
                                <th>Кол-во</th>
                                <th>Мастер</th>
                                <th>Комментарий мастера</th>
                                <th>Создан</th>
                                <th>Решение</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $req): ?>
                                <?php
                                $rid = (int) ($req['id'] ?? 0);
                                $qty = (int) ($req['quantity'] ?? 0);
                                $price = isset($req['part_price'])
                                    ? number_format((float) $req['part_price'], 0, '.', ' ') . ' ₽'
                                    : '—';
                                $total = isset($req['part_price'])
                                    ? number_format((float) $req['part_price'] * $qty, 0, '.', ' ') . ' ₽'
                                    : '—';
                                ?>
                                <tr>
                                    <td><?php echo $rid; ?></td>
                                    <td>#<?php echo (int) ($req['order_id'] ?? 0); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['part_name'] ?? ''); ?></strong><br>
                                        <span class="hint"><?php echo htmlspecialchars($req['article'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo $price; ?></td>
                                    <td><?php echo $qty; ?> шт.<br><span class="hint">= <?php echo $total; ?></span></td>
                                    <td><?php echo htmlspecialchars($req['requested_by_master'] ?? ''); ?></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['comment'] ?? '—')); ?></span></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['created_at'] ?? '')); ?></span></td>
                                    <td>
                                        <form method="post" action="" class="decide-form">
                                            <input type="hidden" name="action" value="decide">
                                            <input type="hidden" name="request_id" value="<?php echo $rid; ?>">
                                            <input type="text" name="comment" class="comment-inline" placeholder="Комментарий (необяз.)">
                                            <button type="submit" name="decision" value="approve" class="btn-sm btn-approve">Одобрить</button>
                                            <button type="submit" name="decision" value="reject" class="btn-sm btn-reject"
                                                onclick="return confirm('Отклонить запрос?')">Отклонить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- История -->
        <section class="card">
            <h2>История обработанных запросов</h2>
            <?php if (empty($resolved)): ?>
                <p class="empty">Обработанных запросов пока нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Заявка</th>
                                <th>Запчасть</th>
                                <th>Кол-во</th>
                                <th>Статус</th>
                                <th>Мастер</th>
                                <th>Решил</th>
                                <th>Решено</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resolved as $req): ?>
                                <?php
                                $st = (string) ($req['status'] ?? '');
                                $badge = $STATUS_BADGE[$st] ?? 'badge';
                                ?>
                                <tr>
                                    <td><?php echo (int) ($req['id'] ?? 0); ?></td>
                                    <td>#<?php echo (int) ($req['order_id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['part_name'] ?? ''); ?><br>
                                        <span class="hint"><?php echo htmlspecialchars($req['article'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo (int) ($req['quantity'] ?? 0); ?></td>
                                    <td><span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($STATUS_LABELS[$st] ?? $st); ?></span></td>
                                    <td><?php echo htmlspecialchars($req['requested_by_master'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($req['approved_by_admin'] ?? '—')); ?></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['resolved_at'] ?? '—')); ?></span></td>
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
