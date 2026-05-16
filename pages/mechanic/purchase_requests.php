<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/PartPurchaseRequest.php';

$nav_mechanic_section = 'purchases';

$STATUS_LABELS = [
    'pending'  => 'Ожидает',
    'approved' => 'Одобрено',
    'rejected' => 'Отклонено',
    'ordered'  => 'Заказано',
    'received' => 'Получено',
];
$STATUS_BADGE = [
    'pending'  => 'badge-warn',
    'approved' => 'badge-done',
    'ordered'  => 'badge-done',
    'received' => 'badge-done',
    'rejected' => 'badge-err',
];

$flash_error   = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_purchase_request') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $part_id  = (int) ($_POST['part_id']  ?? 0);
        $quantity = (int) ($_POST['quantity']  ?? 0);
        $comment  = trim($_POST['comment'] ?? '');

        if ($order_id <= 0 || $part_id <= 0 || $quantity < 1) {
            $flash_error = 'Выберите заявку и запчасть, укажите количество.';
        } else {
            $r = $mechanic_controller->createPartPurchaseRequest($order_id, $mechanic_id, $part_id, $quantity, $comment);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось создать запрос.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Запрос создан и отправлен администратору.';
            }
        }

    } elseif ($action === 'cancel_purchase_request') {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        if ($request_id <= 0) {
            $flash_error = 'Некорректный запрос.';
        } else {
            $r = $mechanic_controller->cancelPurchaseRequest($request_id, $mechanic_id);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось отменить запрос.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Запрос отменён.';
            }
        }
    }
}

/* Активные заявки механика для формы создания */
$active_orders = [];
$ord_r = $mechanic_controller->getMyOrders($mechanic_id);
if ($ord_r['success'] ?? false) {
    $active_orders = $ord_r['data']['orders'] ?? [];
}

/* Каталог запчастей */
$parts_catalog = [];
$parts_r = $mechanic_controller->getParts();
if ($parts_r['success'] ?? false) {
    $parts_catalog = $parts_r['data']['parts'] ?? [];
}

/* История закупок механика */
$my_requests = [];
$req_r = $mechanic_controller->getMyPurchaseRequests($mechanic_id);
if ($req_r['success'] ?? false) {
    $my_requests = $req_r['data']['requests'] ?? [];
}

$ORDER_STATUS_LABELS = [
    Order::STATUS_ASSIGNED      => 'Назначена',
    Order::STATUS_IN_PROGRESS   => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
];
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
        <p class="lead">Запросите нужные запчасти — заявка уйдёт напрямую администратору.</p>

        <!-- Форма нового запроса -->
        <section class="card">
            <h2>Новый запрос</h2>
            <?php if (empty($active_orders)): ?>
                <p class="empty">У вас нет активных заявок, для которых можно запросить закупку.</p>
            <?php elseif (empty($parts_catalog)): ?>
                <p class="empty">Каталог запчастей пуст. Обратитесь к администратору.</p>
            <?php else: ?>
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_purchase_request">
                    <div class="field">
                        <label for="order_id">Заявка</label>
                        <select id="order_id" name="order_id" required>
                            <option value="">Выберите</option>
                            <?php foreach ($active_orders as $o): ?>
                                <?php
                                $st_lbl = $ORDER_STATUS_LABELS[$o['status'] ?? ''] ?? ($o['status'] ?? '');
                                $label  = '#' . (int)($o['id'] ?? 0) . ' — '
                                    . trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))
                                    . ' (' . $st_lbl . ')';
                                ?>
                                <option value="<?php echo (int)($o['id'] ?? 0); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="part_search_m">Запчасть</label>
                        <input type="text" id="part_search_m" autocomplete="off"
                               placeholder="Поиск по названию или артикулу…"
                               style="margin-bottom:4px;">
                        <select id="part_id" name="part_id" required size="6"
                                style="width:100%; height:auto; border-radius:4px;">
                            <option value="">— начните ввод или выберите —</option>
                            <?php foreach ($parts_catalog as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>">
                                    <?php echo htmlspecialchars(
                                        ($p['name'] ?? '') . ' · арт. ' . ($p['article'] ?? '')
                                        . ' · ' . number_format((float)($p['price'] ?? 0), 0, '.', ' ') . ' ₽'
                                        . '  (склад: ' . (int)($p['quantity'] ?? 0) . ' шт.)'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="part_search_hint_m" style="font-size:0.78rem; color:#6b7280; margin-top:2px; display:block;"></span>
                    </div>

                    <div class="row2">
                        <div class="field">
                            <label for="quantity">Количество</label>
                            <input id="quantity" name="quantity" type="number" required min="1" max="9999" value="1">
                        </div>
                        <div class="field" style="align-self:flex-end;">
                            <button type="submit" class="btn-submit" style="width:100%; margin-bottom:0;">Отправить запрос</button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="comment">Комментарий</label>
                        <input id="comment" name="comment" type="text" maxlength="500" placeholder="Необязательно">
                    </div>
                    <p class="hint">Запрос уйдёт напрямую администратору. Решение появится в истории ниже.</p>
                </form>
            <?php endif; ?>
        </section>

        <!-- История -->
        <section class="card">
            <h2>Мои запросы — история</h2>
            <?php if (empty($my_requests)): ?>
                <p class="empty">Вы ещё не создавали запросов на закупку.</p>
            <?php else: ?>
                <?php
                $cnt_pending  = count(array_filter($my_requests, fn($r) => $r['status'] === 'pending'));
                $cnt_approved = count(array_filter($my_requests, fn($r) => in_array($r['status'], ['approved','ordered','received'])));
                $cnt_rejected = count(array_filter($my_requests, fn($r) => $r['status'] === 'rejected'));
                ?>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
                    <?php foreach ([
                        ['Всего',     count($my_requests), ''],
                        ['Ожидают',   $cnt_pending,        '#92400e'],
                        ['Одобрено',  $cnt_approved,       '#15803d'],
                        ['Отклонено', $cnt_rejected,       '#b91c1c'],
                    ] as [$lbl, $cnt, $col]): ?>
                        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:8px 16px; text-align:center; min-width:90px;">
                            <div style="font-size:1.3rem; font-weight:700; <?php echo $col ? "color:{$col};" : ''; ?>"><?php echo $cnt; ?></div>
                            <div style="font-size:0.75rem; color:#6b7280;"><?php echo $lbl; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="overflow-x:auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Заявка</th>
                                <th>Запчасть</th>
                                <th>Кол-во</th>
                                <th>Статус</th>
                                <th>Комментарий</th>
                                <th>Администратор</th>
                                <th>Создан</th>
                                <th>Решение</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $req): ?>
                                <?php
                                $st       = $req['status'] ?? 'pending';
                                $st_lbl   = $STATUS_LABELS[$st]  ?? $st;
                                $st_badge = $STATUS_BADGE[$st]   ?? 'badge';
                                ?>
                                <tr>
                                    <td><?php echo (int)($req['id'] ?? 0); ?></td>
                                    <td>#<?php echo (int)($req['order_id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['part_name'] ?? ''); ?>
                                        <span class="hint"> · <?php echo htmlspecialchars($req['article'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo (int)($req['quantity'] ?? 0); ?></td>
                                    <td><span class="badge <?php echo $st_badge; ?>"><?php echo htmlspecialchars($st_lbl); ?></span></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string)($req['comment'] ?? '—')); ?></span></td>
                                    <td><?php echo $req['approved_by_admin'] ? htmlspecialchars($req['approved_by_admin']) : '<span class="hint">—</span>'; ?></td>
                                    <td><span class="hint"><?php echo $req['created_at'] ? date('d.m.Y H:i', strtotime($req['created_at'])) : '—'; ?></span></td>
                                    <td>
                                        <?php if ($req['resolved_at']): ?>
                                            <span class="hint"><?php echo date('d.m.Y H:i', strtotime($req['resolved_at'])); ?></span>
                                        <?php elseif ($st === 'pending'): ?>
                                            <form method="post" action="" style="margin:0;">
                                                <input type="hidden" name="action"     value="cancel_purchase_request">
                                                <input type="hidden" name="request_id" value="<?php echo (int)($req['id'] ?? 0); ?>">
                                                <button type="submit"
                                                        style="padding:3px 10px; border:1px solid #ef4444; border-radius:4px; background:#fff; color:#dc2626; font-size:0.8rem; font-weight:600; cursor:pointer; font-family:inherit;"
                                                        onclick="return confirm('Отменить запрос?')">Отменить</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
    (function () {
        var searchInput = document.getElementById('part_search_m');
        var partSelect  = document.getElementById('part_id');
        var hint        = document.getElementById('part_search_hint_m');
        if (!searchInput || !partSelect) return;

        var allOptions = Array.from(partSelect.options).map(function (o) {
            return { value: o.value, text: o.text, searchText: o.text.toLowerCase() };
        });
        var total = allOptions.filter(function (o) { return o.value !== ''; }).length;

        function rebuild(query) {
            var q    = query.trim().toLowerCase();
            var kept = allOptions.filter(function (o) {
                return o.value === '' || q === '' || o.searchText.indexOf(q) !== -1;
            });
            var currentVal = partSelect.value;
            partSelect.innerHTML = '';
            kept.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.value;
                opt.textContent = o.text;
                if (o.value && o.value === currentVal) opt.selected = true;
                partSelect.appendChild(opt);
            });
            var found = kept.filter(function (o) { return o.value !== ''; }).length;
            if (q !== '') {
                hint.textContent = found > 0 ? 'Найдено: ' + found + ' из ' + total : 'Ничего не найдено';
                hint.style.color = found > 0 ? '#6b7280' : '#b91c1c';
                if (found === 1) partSelect.value = kept.filter(function (o) { return o.value !== ''; })[0].value;
            } else {
                hint.textContent = '';
            }
        }

        searchInput.addEventListener('input', function () { rebuild(this.value); });
    })();
    </script>
</body>
</html>
