<?php
require_once __DIR__ . '/_init.php';

$nav_master_section = 'new';

$flash_error = null;
$flash_success = null;

$services_catalog = [];
$svc_r = $master_controller->getServices();
if ($svc_r['success'] ?? false) {
    $services_catalog = $svc_r['data']['services'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_mechanic') {
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $mechanic_id = (int) ($_POST['mechanic_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $comment_param = $comment === '' ? null : $comment;
    $mechanic_chooses = isset($_POST['mechanic_chooses']) && $_POST['mechanic_chooses'] === '1';

    $service_ids_post = $_POST['service_ids'] ?? [];
    if (!is_array($service_ids_post)) {
        $service_ids_post = [];
    }
    $service_qty = $_POST['service_qty'] ?? [];
    if (!is_array($service_qty)) {
        $service_qty = [];
    }
    $services_payload = [];
    foreach ($service_ids_post as $sid_raw) {
        $sid = (int) $sid_raw;
        if ($sid <= 0) {
            continue;
        }
        $qty = max(1, (int) ($service_qty[$sid] ?? 1));
        $services_payload[] = ['service_id' => $sid, 'quantity' => $qty];
    }

    if ($order_id <= 0 || $mechanic_id <= 0) {
        $flash_error = 'Выберите заявку и механика.';
    } elseif (!empty($services_catalog) && empty($services_payload) && !$mechanic_chooses) {
        $flash_error = 'Отметьте услуги из списка или включите вариант «Пусть механик сам выбирает».';
    } else {
        if ($mechanic_chooses) {
            $services_payload = [];
        }
        $r = $master_controller->assignMechanic($order_id, $mechanic_id, $master_id, $comment_param, $services_payload);
        if (!($r['success'] ?? false)) {
            $flash_error = $r['message'] ?? 'Не удалось назначить механика.';
        } else {
            $flash_success = $r['data']['message'] ?? 'Механик назначен.';
        }
    }
}

$orders = [];
$new_r = $master_controller->getNewOrders();
if ($new_r['success'] ?? false) {
    $orders = $new_r['data']['orders'] ?? [];
}

$mechanics = [];
$mech_r = $master_controller->getMechanics();
if ($mech_r['success'] ?? false) {
    $mechanics = $mech_r['data']['mechanics'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новые заявки — АвтоПлюс</title>
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

        <h1 class="sans">Новые заявки</h1>

        <?php if (empty($mechanics)): ?>
            <div class="flash flash-err">В системе нет активных механиков. Обратитесь к администратору.</div>
        <?php endif; ?>

        <?php if (empty($services_catalog)): ?>
            <p class="hint">В справочнике пока нет услуг — назначение возможно без выбора работ; после добавления услуг появится список и опция для механика.</p>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <p class="empty">Новых заявок пока нет.</p>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <?php $oid = (int) ($o['id'] ?? 0); ?>
                <section class="card sans">
                    <h2>Заявка № <?php echo $oid; ?></h2>
                    <p class="hint" style="margin-top: 0;">
                        <strong>Клиент:</strong> <?php echo htmlspecialchars($o['client_name'] ?? ''); ?>
                        · <?php echo htmlspecialchars($o['client_phone'] ?? ''); ?>
                        · <?php echo htmlspecialchars($o['client_email'] ?? ''); ?>
                    </p>
                    <p class="hint">
                        <strong>Авто:</strong> <?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['year'] ?? '') . ' г., ' . ($o['gosnumber'] ?? ''))); ?>
                    </p>
                    <p style="margin: 12px 0;"><strong>Описание:</strong> <?php echo nl2br(htmlspecialchars((string) ($o['description'] ?? ''))); ?></p>

                    <form method="post" action="" class="js-assign-form" data-order-id="<?php echo $oid; ?>">
                        <input type="hidden" name="action" value="assign_mechanic">
                        <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                        <div class="row2">
                            <div class="field">
                                <label for="mechanic_<?php echo $oid; ?>">Механик</label>
                                <select id="mechanic_<?php echo $oid; ?>" name="mechanic_id" required <?php echo empty($mechanics) ? 'disabled' : ''; ?>>
                                    <option value="">Выберите</option>
                                    <?php foreach ($mechanics as $m): ?>
                                        <option value="<?php echo (int) $m['id']; ?>"><?php echo htmlspecialchars($m['full_name'] . ' · ' . ($m['email'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="comment_<?php echo $oid; ?>">Комментарий к назначению</label>
                                <input id="comment_<?php echo $oid; ?>" name="comment" type="text" maxlength="500" placeholder="Необязательно">
                            </div>
                        </div>

                        <?php if (!empty($services_catalog)): ?>
                            <div class="master-services-field">
                                <span class="field-label">Услуги по заявке</span>
                                <label class="master-mechanic-chooses">
                                    <input type="checkbox" name="mechanic_chooses" value="1" class="js-mechanic-chooses" data-services-box="master-svc-box-<?php echo $oid; ?>">
                                    <span><strong>Пусть механик сам выбирает услуги</strong></span>
                                </label>
                                <div id="master-svc-box-<?php echo $oid; ?>" class="master-services-box">
                                    <?php foreach ($services_catalog as $s): ?>
                                        <?php
                                        $sid = (int) ($s['id'] ?? 0);
                                        $price = isset($s['price']) ? number_format((float) $s['price'], 0, '.', ' ') . ' ₽' : '—';
                                        ?>
                                        <div class="master-service-row">
                                            <input type="checkbox" name="service_ids[]" value="<?php echo $sid; ?>" class="js-service-cb">
                                            <div class="master-service-name">
                                                <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                                <span class="hint"> · <?php echo htmlspecialchars($price); ?></span>
                                            </div>
                                            <div class="master-service-qty">
                                                <input type="number" name="service_qty[<?php echo $sid; ?>]" min="1" max="999" value="1" class="js-service-qty" title="Количество">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-submit" <?php echo empty($mechanics) ? 'disabled' : ''; ?>>Назначить механика</button>
                    </form>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        function setBoxState(box, disabled) {
            if (!box) return;
            box.classList.toggle('is-disabled', disabled);
            box.querySelectorAll('.js-service-cb, .js-service-qty').forEach(function (el) {
                el.disabled = disabled;
                if (disabled && el.classList.contains('js-service-cb')) {
                    el.checked = false;
                }
            });
        }
        document.querySelectorAll('.js-mechanic-chooses').forEach(function (cb) {
            var id = cb.getAttribute('data-services-box');
            var box = id ? document.getElementById(id) : null;
            function sync() {
                setBoxState(box, cb.checked);
            }
            cb.addEventListener('change', sync);
            sync();
        });
    })();
    </script>
</body>
</html>
