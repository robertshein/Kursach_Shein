<?php
require_once __DIR__ . '/_init.php';

$nav_admin_section = 'services';

$flash_error   = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $price = (float) str_replace(',', '.', $_POST['price'] ?? '0');
        if ($name === '') {
            $flash_error = 'Укажите название услуги.';
        } elseif ($price < 0) {
            $flash_error = 'Цена не может быть отрицательной.';
        } else {
            $r = $admin_controller->createService($name, $price);
            $flash_error   = ($r['success'] ?? false) ? null : ($r['message'] ?? 'Ошибка.');
            $flash_success = ($r['success'] ?? false) ? 'Услуга добавлена.' : null;
        }

    } elseif ($action === 'update') {
        $id    = (int) ($_POST['id']    ?? 0);
        $name  = trim($_POST['name']    ?? '');
        $price = (float) str_replace(',', '.', $_POST['price'] ?? '0');
        if ($id <= 0 || $name === '') {
            $flash_error = 'Некорректные данные.';
        } elseif ($price < 0) {
            $flash_error = 'Цена не может быть отрицательной.';
        } else {
            $r = $admin_controller->updateService($id, $name, $price);
            $flash_error   = ($r['success'] ?? false) ? null : ($r['message'] ?? 'Ошибка.');
            $flash_success = ($r['success'] ?? false) ? 'Услуга обновлена.' : null;
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash_error = 'Некорректный идентификатор.';
        } else {
            $r = $admin_controller->deleteService($id);
            $flash_error   = ($r['success'] ?? false) ? null : ($r['message'] ?? 'Ошибка.');
            $flash_success = ($r['success'] ?? false) ? 'Услуга удалена.' : null;
        }
    }
}

$services = [];
$r = $admin_controller->getServices();
if ($r['success'] ?? false) {
    $services = $r['data']['services'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Услуги — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .inline-form { display: inline; }
        .btn-sm {
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #fff;
            color: var(--focus);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-sm:hover { background: #f0f4fb; }
        .btn-sm-danger { color: var(--danger-text); border-color: var(--danger-border); }
        .btn-sm-danger:hover { background: var(--danger-bg); }
        .edit-row { display: none; background: #f7f9fc; }
        .edit-row td { padding: 10px 12px; }
        .edit-row input { padding: 5px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 0.9rem; font-family: inherit; }
        .w-name  { width: 260px; }
        .w-price { width: 100px; }
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

        <h1 class="sans">Услуги</h1>
        <p class="lead">Справочник услуг автосервиса. Используется при назначении работ в заявках.</p>

        <section class="card">
            <h2>Добавить услугу</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="create">
                <div class="row2">
                    <div class="field">
                        <label for="name">Название <span style="color:red;">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="150" placeholder="Замена масла">
                    </div>
                    <div class="field">
                        <label for="price">Цена, ₽ <span style="color:red;">*</span></label>
                        <input id="price" name="price" type="number" required min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Добавить</button>
            </form>
        </section>

        <section class="card">
            <h2>Список услуг (<?php echo count($services); ?>)</h2>
            <?php if (empty($services)): ?>
                <p class="empty">Услуг пока нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Название</th>
                                <th>Цена, ₽</th>
                                <th style="width:160px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $svc): ?>
                                <?php $sid = (int) $svc['id']; ?>
                                <tr id="row-<?php echo $sid; ?>">
                                    <td><?php echo $sid; ?></td>
                                    <td><?php echo htmlspecialchars($svc['name']); ?></td>
                                    <td><?php echo number_format((float) $svc['price'], 2, '.', ' '); ?></td>
                                    <td>
                                        <button type="button" class="btn-sm"
                                            onclick="toggleEdit(<?php echo $sid; ?>)">Изменить</button>
                                        <form method="post" action="" class="inline-form">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                            <button type="submit" class="btn-sm btn-sm-danger"
                                                onclick="return confirm('Удалить услугу «<?php echo htmlspecialchars(addslashes($svc['name'])); ?>»?')">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="edit-row" id="edit-<?php echo $sid; ?>">
                                    <td colspan="4">
                                        <form method="post" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                            <div class="field" style="margin:0;">
                                                <label>Название</label>
                                                <input type="text" name="name" class="w-name" required maxlength="150"
                                                    value="<?php echo htmlspecialchars($svc['name']); ?>">
                                            </div>
                                            <div class="field" style="margin:0;">
                                                <label>Цена, ₽</label>
                                                <input type="number" name="price" class="w-price" required min="0" step="0.01"
                                                    value="<?php echo (float) $svc['price']; ?>">
                                            </div>
                                            <button type="submit" class="btn-submit" style="margin-bottom:0;">Сохранить</button>
                                            <button type="button" class="btn-sm" style="margin-bottom:0;"
                                                onclick="toggleEdit(<?php echo $sid; ?>)">Отмена</button>
                                        </form>
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
    function toggleEdit(id) {
        var row = document.getElementById('edit-' + id);
        row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    }
    </script>
</body>
</html>
