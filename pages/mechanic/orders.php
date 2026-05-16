<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../../models/Order.php';

$nav_mechanic_section = 'orders';

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW           => 'Новая',
    Order::STATUS_ASSIGNED      => 'Назначена',
    Order::STATUS_IN_PROGRESS   => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED     => 'Завершена',
    Order::STATUS_CANCELLED     => 'Отменена',
];

$AVAILABLE_STATUS_OPTIONS = [
    Order::STATUS_IN_PROGRESS   => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED     => 'Завершена',
    Order::STATUS_CANCELLED     => 'Отменена',
];

$flash_error   = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $order_id     = (int) ($_POST['order_id'] ?? 0);
        $new_status   = (string) ($_POST['status'] ?? '');
        $parts_comment = trim((string) ($_POST['parts_comment'] ?? ''));
        $r = $mechanic_controller->updateOrderStatus($order_id, $mechanic_id, $new_status, $parts_comment);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось изменить статус.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Статус обновлён.';
        }

    } elseif ($action === 'add_service') {
        $order_id   = (int) ($_POST['order_id'] ?? 0);
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $quantity   = (int) ($_POST['quantity'] ?? 1);
        $comment    = (string) ($_POST['service_comment'] ?? '');
        $r = $mechanic_controller->addServiceToOrder($order_id, $mechanic_id, $service_id, $quantity, $comment);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось добавить услугу.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Услуга добавлена.';
        }

    } elseif ($action === 'add_part') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $part_id  = (int) ($_POST['part_id'] ?? 0);
        $quantity = (int) ($_POST['part_quantity'] ?? 1);
        $note     = (string) ($_POST['part_note'] ?? '');
        $r = $mechanic_controller->addPartToOrder($order_id, $mechanic_id, $part_id, $quantity, $note);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось добавить запчасть.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Запчасть добавлена.';
        }

    } elseif ($action === 'remove_part') {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $row_id   = (int) ($_POST['row_id'] ?? 0);
        $r = $mechanic_controller->removePartFromOrder($order_id, $mechanic_id, $row_id);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось удалить запчасть.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Запчасть удалена.';
        }
    }
}

$services = [];
$services_r = $mechanic_controller->getServices();
if ($services_r['success'] ?? false) {
    $services = $services_r['data']['services'] ?? [];
}

$parts_catalog = [];
$parts_r = $mechanic_controller->getParts();
if ($parts_r['success'] ?? false) {
    $parts_catalog = $parts_r['data']['parts'] ?? [];
}

$orders = [];
$r = $mechanic_controller->getMyOrders($mechanic_id);
if ($r['success'] ?? false) {
    $orders = $r['data']['orders'] ?? [];
}

/* Load order_parts per order */
$order_parts_map = [];
foreach ($orders as $o) {
    $oid = (int) ($o['id'] ?? 0);
    $rp = $mechanic_controller->getOrderParts($oid, $mechanic_id);
    $order_parts_map[$oid] = ($rp['success'] ?? false) ? ($rp['data']['parts'] ?? []) : [];
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
    <style>
        .parts-list { margin: 0 0 10px; padding: 0; list-style: none; }
        .parts-list li {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 0; border-bottom: 1px solid var(--border, #e5e7eb);
            font-size: 0.9rem;
        }
        .parts-list li:last-child { border-bottom: none; }
        .parts-list .part-name { flex: 1; }
        .parts-list .part-meta { color: #6b7280; font-size: 0.8rem; }
        .btn-sm-danger {
            padding: 2px 8px; font-size: 0.78rem; border: 1px solid #f87171;
            background: #fff; color: #dc2626; border-radius: 4px; cursor: pointer;
            line-height: 1.4;
        }
        .btn-sm-danger:hover { background: #fef2f2; }
        .parts-comment-row { display: none; margin-top: 8px; }
        .parts-comment-row.visible { display: block; }
        .section-sep { border: none; border-top: 1px solid var(--border, #e5e7eb); margin: 16px 0; }
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

        <h1 class="sans">Мои заявки</h1>
        <p class="lead">
            Активные заявки, назначенные вам мастером. Завершённые и отменённые —
            в <a href="archive.php" style="color:var(--focus);font-weight:600;text-decoration:none;">архиве</a>.
        </p>

        <?php if (empty($orders)): ?>
            <p class="empty">Нет активных заявок. <a href="archive.php">Перейти в архив →</a></p>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php
                $oid       = (int) ($o['id'] ?? 0);
                $st        = (string) ($o['status'] ?? '');
                $is_terminal = in_array($st, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
                $price     = isset($o['total_price']) ? number_format((float) $o['total_price'], 0, '.', ' ') . ' ₽' : '—';
                $order_parts = $order_parts_map[$oid] ?? [];
                $waiting_now = ($st === Order::STATUS_WAITING_PARTS);
                ?>
                <section class="card sans">
                    <h2>Заявка № <?php echo $oid; ?></h2>
                    <p class="hint" style="margin-top:0;">
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

                    <!-- ===== ЗАПЧАСТИ ===== -->
                    <hr class="section-sep">
                    <h3 style="margin:0 0 8px; font-size:1rem;">Запчасти</h3>

                    <?php if (!empty($o['parts_comment'])): ?>
                        <p class="hint" style="margin-bottom:8px;">
                            <strong>Комментарий по запчастям:</strong>
                            <?php echo nl2br(htmlspecialchars((string) $o['parts_comment'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($order_parts)): ?>
                        <ul class="parts-list">
                            <?php foreach ($order_parts as $pt): ?>
                                <li>
                                    <span class="part-name">
                                        <?php echo htmlspecialchars($pt['name'] ?? ''); ?>
                                        <span class="part-meta">
                                            арт. <?php echo htmlspecialchars($pt['article'] ?? ''); ?>
                                            &nbsp;·&nbsp; <?php echo (int) $pt['quantity']; ?> шт.
                                            &nbsp;·&nbsp; <?php echo number_format((float) ($pt['price'] ?? 0), 0, '.', ' '); ?> ₽/шт.
                                        </span>
                                        <?php if (!empty($pt['note'])): ?>
                                            <span class="part-meta">&nbsp;— <?php echo htmlspecialchars($pt['note']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (!$is_terminal): ?>
                                        <form method="post" action="" style="margin:0;">
                                            <input type="hidden" name="action"   value="remove_part">
                                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                            <input type="hidden" name="row_id"   value="<?php echo (int) $pt['id']; ?>">
                                            <button type="submit" class="btn-sm-danger"
                                                    onclick="return confirm('Удалить запчасть из заявки?')">Удалить</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="hint" style="margin-bottom:8px;">Запчасти не добавлены.</p>
                    <?php endif; ?>

                    <?php if (!$is_terminal): ?>
                        <form method="post" action="">
                            <input type="hidden" name="action"   value="add_part">
                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                            <div class="row2">
                                <div class="field">
                                    <label for="part_<?php echo $oid; ?>">Запчасть из каталога</label>
                                    <select id="part_<?php echo $oid; ?>" name="part_id" required <?php echo empty($parts_catalog) ? 'disabled' : ''; ?>>
                                        <option value="">Выберите</option>
                                        <?php foreach ($parts_catalog as $pt): ?>
                                            <option value="<?php echo (int) $pt['id']; ?>">
                                                <?php echo htmlspecialchars(
                                                    $pt['name'] . ' · арт. ' . $pt['article'] . ' · '
                                                    . number_format((float) $pt['price'], 0, '.', ' ') . ' ₽'
                                                    . ' (склад: ' . (int) $pt['quantity'] . ')'
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="part_qty_<?php echo $oid; ?>">Количество</label>
                                    <input id="part_qty_<?php echo $oid; ?>" type="number" name="part_quantity"
                                           min="1" max="999" value="1" required <?php echo empty($parts_catalog) ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="field">
                                <label for="part_note_<?php echo $oid; ?>">Примечание</label>
                                <input id="part_note_<?php echo $oid; ?>" type="text" name="part_note"
                                       maxlength="255" placeholder="Необязательно" <?php echo empty($parts_catalog) ? 'disabled' : ''; ?>>
                            </div>
                            <?php if (empty($parts_catalog)): ?>
                                <p class="hint">Каталог запчастей пуст. Добавьте запчасти в систему.</p>
                            <?php endif; ?>
                            <button type="submit" class="btn-submit" <?php echo empty($parts_catalog) ? 'disabled' : ''; ?>>
                                Добавить запчасть
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- ===== УСЛУГИ ===== -->
                    <hr class="section-sep">
                    <h3 style="margin:0 0 8px; font-size:1rem;">Услуги</h3>

                    <form method="post" action="">
                        <input type="hidden" name="action"   value="add_service">
                        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                        <div class="row2">
                            <div class="field">
                                <label for="service_<?php echo $oid; ?>">Добавить услугу</label>
                                <select id="service_<?php echo $oid; ?>" name="service_id" required
                                        <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                                    <option value="">Выберите</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo (int) ($service['id'] ?? 0); ?>">
                                            <?php echo htmlspecialchars(
                                                ($service['name'] ?? '') . ' · '
                                                . number_format((float) ($service['price'] ?? 0), 0, '.', ' ') . ' ₽'
                                            ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="qty_<?php echo $oid; ?>">Количество</label>
                                <input id="qty_<?php echo $oid; ?>" type="number" name="quantity"
                                       min="1" max="99" value="1" required
                                       <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="field">
                            <label for="svc_comment_<?php echo $oid; ?>">Комментарий к услуге</label>
                            <input id="svc_comment_<?php echo $oid; ?>" type="text" name="service_comment"
                                   maxlength="255" placeholder="Необязательно"
                                   <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                        </div>
                        <?php if (empty($services)): ?>
                            <p class="hint">Справочник услуг пуст.</p>
                        <?php endif; ?>
                        <button type="submit" class="btn-submit"
                                <?php echo ($is_terminal || empty($services)) ? 'disabled' : ''; ?>>
                            Добавить услугу
                        </button>
                    </form>

                    <!-- ===== ИЗМЕНЕНИЕ СТАТУСА ===== -->
                    <hr class="section-sep">
                    <h3 style="margin:0 0 8px; font-size:1rem;">Статус</h3>

                    <form method="post" action="">
                        <input type="hidden" name="action"   value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                        <div class="row2">
                            <div class="field">
                                <label>Текущий статус</label>
                                <input type="text"
                                       value="<?php echo htmlspecialchars(mechanic_status_label($st, $ORDER_STATUS_LABELS)); ?>"
                                       disabled>
                            </div>
                            <div class="field">
                                <label for="new_status_<?php echo $oid; ?>">Новый статус</label>
                                <select id="new_status_<?php echo $oid; ?>" name="status"
                                        required <?php echo $is_terminal ? 'disabled' : ''; ?>
                                        onchange="mechStatusChange(this, <?php echo $oid; ?>)">
                                    <option value="">Выберите</option>
                                    <?php foreach ($AVAILABLE_STATUS_OPTIONS as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Поле комментария — показывается только при выборе «Ожидание запчастей» -->
                        <div id="parts_comment_row_<?php echo $oid; ?>"
                             class="parts-comment-row<?php echo $waiting_now ? ' visible' : ''; ?>">
                            <div class="field">
                                <label for="parts_comment_<?php echo $oid; ?>">Комментарий (какие запчасти нужны)</label>
                                <textarea id="parts_comment_<?php echo $oid; ?>" name="parts_comment"
                                          rows="3" maxlength="2000"
                                          placeholder="Укажите, какие запчасти необходимы для заказа"
                                ><?php echo htmlspecialchars((string) ($o['parts_comment'] ?? '')); ?></textarea>
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

    <script>
    function mechStatusChange(select, orderId) {
        var row = document.getElementById('parts_comment_row_' + orderId);
        if (!row) return;
        if (select.value === '<?php echo Order::STATUS_WAITING_PARTS; ?>') {
            row.classList.add('visible');
        } else {
            row.classList.remove('visible');
        }
    }
    </script>
</body>
</html>
