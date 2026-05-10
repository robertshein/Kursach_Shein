<?php
require_once __DIR__ . '/../config/connect_database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/User.php';

$errors = [];
$success_message = null;
$old = [
    'full_name' => '',
    'phone' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($old['full_name'] === '') {
        $errors[] = 'Введите ФИО.';
    }
    if ($old['phone'] === '') {
        $errors[] = 'Введите номер телефона.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }
    if (mb_strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не менее 6 символов.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Пароли не совпадают.';
    }

    if (empty($errors)) {
        $auth_controller = new AuthController($mysql_connection);
        $result = $auth_controller->register(
            $old['full_name'],
            $old['phone'],
            $old['email'],
            $password,
            User::ROLE_CLIENT
        );

        if (!($result['success'] ?? false)) {
            $errors[] = $result['message'] ?? 'Ошибка регистрации.';
        } else {
            $success_message = 'Регистрация успешна. Теперь войдите в систему.';
            $old = ['full_name' => '', 'phone' => '', 'email' => ''];
        }
    }
}

$nav_is_guest = true;
$nav_active = 'register';
$nav_home_href = 'authorization.php';
$nav_login_href = 'authorization.php';
$nav_register_href = 'registration.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <?php include __DIR__ . '/../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/site_nav.php'; ?>

    <div class="page page-auth">
        <div class="card auth-card">
            <h1 class="sans">Регистрация</h1>

            <?php if (!empty($errors)): ?>
                <div class="flash flash-err">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="flash flash-ok"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="row2">
                    <div class="field">
                        <label for="full_name">ФИО</label>
                        <input id="full_name" name="full_name" type="text" required maxlength="150" value="<?php echo htmlspecialchars($old['full_name']); ?>">
                    </div>
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" name="phone" type="text" required maxlength="30" value="<?php echo htmlspecialchars($old['phone']); ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="150" value="<?php echo htmlspecialchars($old['email']); ?>">
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="password">Пароль</label>
                        <input id="password" name="password" type="password" required maxlength="128" autocomplete="new-password" placeholder="Не менее 6 символов">
                    </div>
                    <div class="field">
                        <label for="password_confirm">Подтверждение пароля</label>
                        <input id="password_confirm" name="password_confirm" type="password" required maxlength="128" autocomplete="new-password" placeholder="Повторите пароль">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Зарегистрироваться</button>
            </form>

            <p class="auth-switch">Уже есть аккаунт? <a href="authorization.php">Войти</a></p>
        </div>
    </div>
</body>
</html>