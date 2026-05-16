<?php
require_once __DIR__ . '/_init.php';

$nav_admin_section = 'employees';

$flash_error = null;
$flash_success = null;

$ROLE_LABELS = [
    User::ROLE_MECHANIC => 'Механик',
    User::ROLE_MASTER   => 'Мастер',
    User::ROLE_ADMIN    => 'Администратор',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_employee') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = (string) ($_POST['password'] ?? '');
        $role      = (string) ($_POST['role'] ?? '');

        if ($full_name === '' || $phone === '' || $email === '' || $password === '' || $role === '') {
            $flash_error = 'Заполните все обязательные поля.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_error = 'Укажите корректный email.';
        } elseif (strlen($password) < 6) {
            $flash_error = 'Пароль должен содержать не менее 6 символов.';
        } else {
            $r = $admin_controller->createEmployee($full_name, $phone, $email, $password, $role);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось добавить сотрудника.';
            } else {
                $flash_success = 'Сотрудник добавлен.';
            }
        }
    } elseif ($action === 'toggle_active') {
        $emp_id    = (int) ($_POST['employee_id'] ?? 0);
        $is_active = (int) ($_POST['is_active'] ?? 0);
        if ($emp_id <= 0) {
            $flash_error = 'Некорректный сотрудник.';
        } elseif ($emp_id === $admin_id) {
            $flash_error = 'Нельзя деактивировать себя.';
        } else {
            $r = $admin_controller->setEmployeeActive($emp_id, $is_active);
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось изменить статус.';
            } else {
                $flash_success = $is_active ? 'Сотрудник активирован.' : 'Сотрудник деактивирован.';
            }
        }
    }
}

$employees = [];
$r = $admin_controller->getEmployees();
if ($r['success'] ?? false) {
    $employees = $r['data']['employees'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
    <style>
        .emp-row td { vertical-align: middle; }
        .emp-inactive { opacity: 0.55; }
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
        .btn-sm-ok { color: var(--ok-text); border-color: var(--ok-border); }
        .btn-sm-ok:hover { background: var(--ok-bg); }
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

        <h1 class="sans">Сотрудники</h1>
        <p class="lead">Управление персоналом: добавление и активация/деактивация сотрудников.</p>

        <!-- Добавление сотрудника -->
        <section class="card">
            <h2>Добавить сотрудника</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="create_employee">
                <div class="row2">
                    <div class="field">
                        <label for="full_name">ФИО <span style="color:red;">*</span></label>
                        <input id="full_name" name="full_name" type="text" required maxlength="150" placeholder="Иванов Иван Иванович">
                    </div>
                    <div class="field">
                        <label for="phone">Телефон <span style="color:red;">*</span></label>
                        <input id="phone" name="phone" type="text" required maxlength="30" placeholder="+79001234567">
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="email">Email <span style="color:red;">*</span></label>
                        <input id="email" name="email" type="email" required maxlength="150" placeholder="employee@service.local">
                    </div>
                    <div class="field">
                        <label for="password">Пароль <span style="color:red;">*</span></label>
                        <input id="password" name="password" type="password" required minlength="6" placeholder="Не менее 6 символов">
                    </div>
                </div>
                <div class="field">
                    <label for="role">Роль <span style="color:red;">*</span></label>
                    <select id="role" name="role" required>
                        <option value="">Выберите</option>
                        <?php foreach ($ROLE_LABELS as $val => $lbl): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Добавить сотрудника</button>
            </form>
        </section>

        <!-- Список сотрудников -->
        <section class="card">
            <h2>Список сотрудников</h2>
            <?php if (empty($employees)): ?>
                <p class="empty">Сотрудников пока нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>ФИО</th>
                                <th>Email / Телефон</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Активация</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <?php
                                $eid       = (int) ($emp['id'] ?? 0);
                                $is_active = (int) ($emp['is_active'] ?? 0);
                                $is_self   = ($eid === $admin_id);
                                $row_class = $is_active ? 'emp-row' : 'emp-row emp-inactive';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($emp['full_name'] ?? ''); ?></strong>
                                        <?php if ($is_self): ?>
                                            <span class="badge" style="margin-left:4px;">Вы</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($emp['email'] ?? ''); ?><br>
                                        <span class="hint"><?php echo htmlspecialchars($emp['phone'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge"><?php echo htmlspecialchars($ROLE_LABELS[$emp['role'] ?? ''] ?? ($emp['role'] ?? '')); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-ok" style="background:var(--ok-bg);border-color:var(--ok-border);color:var(--ok-text);">Активен</span>
                                        <?php else: ?>
                                            <span class="badge badge-warn">Деактивирован</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$is_self): ?>
                                            <form method="post" action="" class="inline-form">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="employee_id" value="<?php echo $eid; ?>">
                                                <?php if ($is_active): ?>
                                                    <input type="hidden" name="is_active" value="0">
                                                    <button type="submit" class="btn-sm btn-sm-danger"
                                                        onclick="return confirm('Деактивировать сотрудника?')">Деактивировать</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="is_active" value="1">
                                                    <button type="submit" class="btn-sm btn-sm-ok">Активировать</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="hint">—</span>
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
</body>
</html>
