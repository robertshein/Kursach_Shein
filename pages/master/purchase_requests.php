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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_purchase_request') {
    $request_id = (int) ($_POST['request_id'] ?? 0);
    if ($request_id <= 0) {
        $flash_error = 'Некорректный запрос.';
    } else {
        $r = $master_controller->cancelPurchaseRequest($request_id, $master_id);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось отменить запрос.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Запрос отменён.';
        }
    }
}

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

$my_requests = [];
$my_r = $master_controller->getMyPurchaseRequests($master_id);
if ($my_r['success'] ?? false) {
    $my_requests = $my_r['data']['requests'] ?? [];
}

$REQUEST_STATUS_LABELS = [
    'pending'  => 'Ожидает',
    'approved' => 'Одобрено',
    'rejected' => 'Отклонено',
    'ordered'  => 'Заказано',
    'received' => 'Получено',
];
$REQUEST_STATUS_BADGE = [
    'pending'  => 'badge-warn',
    'approved' => 'badge-done',
    'ordered'  => 'badge-done',
    'received' => 'badge-done',
    'rejected' => 'badge-err',
];

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
                    <div class="field">
                        <label for="part_search">Запчасть</label>
                        <input type="text" id="part_search" autocomplete="off"
                               placeholder="Поиск по названию или артикулу…"
                               style="margin-bottom:4px;">
                        <select id="part_id" name="part_id" required size="6"
                                style="width:100%; height:auto; border-radius:4px;">
                            <option value="">— начните ввод или выберите —</option>
                            <?php foreach ($parts as $p): ?>
                                <option value="<?php echo (int) $p['id']; ?>">
                                    <?php echo htmlspecialchars(
                                        ($p['name'] ?? '') . ' · арт. ' . ($p['article'] ?? '')
                                        . ' · ' . number_format((float)($p['price'] ?? 0), 0, '.', ' ') . ' ₽'
                                        . '  (склад: ' . (int)($p['quantity'] ?? 0) . ' шт.)'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="part_search_hint" style="font-size:0.78rem; color:#6b7280; margin-top:2px; display:block;"></span>
                    </div>
                    <div class="row2">
                        <div class="field">
                            <label for="quantity">Количество</label>
                            <input id="quantity" name="quantity" type="number" required min="1" max="9999" value="1">
                        </div>
                        <div class="field" style="align-self:flex-end;">
                            <button type="submit" class="btn-submit" style="width:100%; margin-bottom:0;">Создать запрос</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="comment">Комментарий</label>
                        <input id="comment" name="comment" type="text" maxlength="500" placeholder="Необязательно">
                    </div>
                    <p class="hint" style="margin-top:4px;">После создания заявка получит статус «Ожидание запчастей» до решения администратора.</p>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Ожидают решения администратора</h2>
            <p class="hint" style="margin-top:0; margin-bottom:12px;">Все запросы от всех мастеров, которые ещё не обработаны.</p>
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
                                <?php
                                $role_labels = ['master' => 'Мастер', 'mechanic' => 'Механик'];
                                $role_lbl = $role_labels[$req['requester_role'] ?? ''] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo (int) ($req['id'] ?? 0); ?></td>
                                    <td><?php echo (int) ($req['order_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($req['part_name'] ?? '') . ' · ' . ($req['article'] ?? ''))); ?></td>
                                    <td><?php echo (int) ($req['quantity'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['requester_name'] ?? ''); ?>
                                        <?php if ($role_lbl): ?>
                                            <span class="hint"> (<?php echo $role_lbl; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['comment'] ?? '—')); ?></span></td>
                                    <td><span class="hint"><?php echo $req['created_at'] ? date('d.m.Y H:i', strtotime($req['created_at'])) : '—'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <section class="card">
            <h2>Мои закупки — история</h2>
            <?php if (empty($my_requests)): ?>
                <p class="empty">Вы ещё не создавали запросов на закупку.</p>
            <?php else: ?>
                <?php
                $pending_cnt  = 0;
                $approved_cnt = 0;
                $rejected_cnt = 0;
                foreach ($my_requests as $req) {
                    $s = $req['status'] ?? '';
                    if ($s === 'pending')  $pending_cnt++;
                    elseif ($s === 'approved' || $s === 'ordered' || $s === 'received') $approved_cnt++;
                    elseif ($s === 'rejected') $rejected_cnt++;
                }
                ?>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:8px 16px; text-align:center; min-width:90px;">
                        <div style="font-size:1.3rem; font-weight:700;"><?php echo count($my_requests); ?></div>
                        <div style="font-size:0.75rem; color:#6b7280;">Всего</div>
                    </div>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:8px 16px; text-align:center; min-width:90px;">
                        <div style="font-size:1.3rem; font-weight:700; color:#92400e;"><?php echo $pending_cnt; ?></div>
                        <div style="font-size:0.75rem; color:#6b7280;">Ожидают</div>
                    </div>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:8px 16px; text-align:center; min-width:90px;">
                        <div style="font-size:1.3rem; font-weight:700; color:#15803d;"><?php echo $approved_cnt; ?></div>
                        <div style="font-size:0.75rem; color:#6b7280;">Одобрено</div>
                    </div>
                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:8px 16px; text-align:center; min-width:90px;">
                        <div style="font-size:1.3rem; font-weight:700; color:#b91c1c;"><?php echo $rejected_cnt; ?></div>
                        <div style="font-size:0.75rem; color:#6b7280;">Отклонено</div>
                    </div>
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
                                <th>Администратор</th>
                                <th>Комментарий</th>
                                <th>Создан</th>
                                <th>Решение</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $req): ?>
                                <?php
                                $st       = $req['status'] ?? 'pending';
                                $st_lbl   = $REQUEST_STATUS_LABELS[$st] ?? $st;
                                $st_badge = $REQUEST_STATUS_BADGE[$st]  ?? 'badge';
                                $is_pending = $st === 'pending';
                                ?>
                                <tr>
                                    <td><?php echo (int) ($req['id'] ?? 0); ?></td>
                                    <td><?php echo (int) ($req['order_id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($req['part_name'] ?? ''); ?>
                                        <span class="hint"> · <?php echo htmlspecialchars($req['article'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo (int) ($req['quantity'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge <?php echo $st_badge; ?>">
                                            <?php echo htmlspecialchars($st_lbl); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $req['approved_by_admin'] ? htmlspecialchars($req['approved_by_admin']) : '<span class="hint">—</span>'; ?></td>
                                    <td><span class="hint"><?php echo htmlspecialchars((string) ($req['comment'] ?? '—')); ?></span></td>
                                    <td><span class="hint"><?php echo $req['created_at'] ? date('d.m.Y H:i', strtotime($req['created_at'])) : '—'; ?></span></td>
                                    <td>
                                        <?php if ($req['resolved_at']): ?>
                                            <span class="hint"><?php echo date('d.m.Y H:i', strtotime($req['resolved_at'])); ?></span>
                                        <?php elseif ($is_pending): ?>
                                            <form method="post" action="" style="margin:0;">
                                                <input type="hidden" name="action"     value="cancel_purchase_request">
                                                <input type="hidden" name="request_id" value="<?php echo (int) ($req['id'] ?? 0); ?>">
                                                <button type="submit"
                                                        style="padding:3px 10px; border:1px solid #ef4444; border-radius:4px; background:#fff; color:#dc2626; font-size:0.8rem; font-weight:600; cursor:pointer; font-family:inherit; white-space:nowrap;"
                                                        onclick="return confirm('Отменить запрос №<?php echo (int)($req['id']??0); ?>? Заявка вернётся в статус «В работе».')">
                                                    Отменить
                                                </button>
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
        var searchInput = document.getElementById('part_search');
        var partSelect  = document.getElementById('part_id');
        var hint        = document.getElementById('part_search_hint');
        if (!searchInput || !partSelect) return;

        /* Снимок исходных опций при загрузке страницы */
        var allOptions = Array.from(partSelect.options).map(function (o) {
            return { value: o.value, text: o.text, searchText: o.text.toLowerCase() };
        });
        var total = allOptions.filter(function (o) { return o.value !== ''; }).length;

        function rebuild(query) {
            var q      = query.trim().toLowerCase();
            var kept   = allOptions.filter(function (o) {
                return o.value === '' || q === '' || o.searchText.indexOf(q) !== -1;
            });

            /* Запоминаем текущий выбор */
            var currentVal = partSelect.value;

            partSelect.innerHTML = '';
            kept.forEach(function (o) {
                var opt       = document.createElement('option');
                opt.value     = o.value;
                opt.textContent = o.text;
                if (o.value && o.value === currentVal) opt.selected = true;
                partSelect.appendChild(opt);
            });

            /* Подсказка */
            var found = kept.filter(function (o) { return o.value !== ''; }).length;
            if (q !== '') {
                hint.textContent = found > 0
                    ? 'Найдено: ' + found + ' из ' + total
                    : 'Ничего не найдено';
                hint.style.color = found > 0 ? '#6b7280' : '#b91c1c';

                /* Автовыбор единственного совпадения */
                if (found === 1) {
                    partSelect.value = kept.filter(function (o) { return o.value !== ''; })[0].value;
                }
            } else {
                hint.textContent = '';
            }
        }

        searchInput.addEventListener('input', function () {
            rebuild(this.value);
        });

        /* Клик на опцию очищает поле поиска (удобно для повторного выбора) */
        partSelect.addEventListener('change', function () {
            if (this.value) searchInput.placeholder = 'Поиск по названию или артикулу…';
        });
    })();
    </script>
</body>
</html>
