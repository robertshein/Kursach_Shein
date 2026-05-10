<?php
require_once __DIR__ . '/../config/connect_database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $auth_controller = new AuthController($mysql_connection);
    $auth_controller->logout();
    header('Location: authorization.php');
    exit();
}

if (!empty($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

$errors = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($old_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }
    if ($password === '') {
        $errors[] = 'Введите пароль.';
    }

    if (empty($errors)) {
        $auth_controller = new AuthController($mysql_connection);
        $result = $auth_controller->login($old_email, $password);

        if (!($result['success'] ?? false)) {
            $errors[] = $result['message'] ?? 'Ошибка авторизации.';
        } else {
            header('Location: ../index.php');
            exit();
        }
    }
}

$nav_is_guest = true;
$nav_active = 'login';
$nav_home_href = 'authorization.php';
$nav_login_href = 'authorization.php';
$nav_register_href = 'registration.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <?php include __DIR__ . '/../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/site_nav.php'; ?>

    <div class="page page-auth">
        <div class="card auth-card">
            <h1 class="sans">Вход</h1>

            <?php if (!empty($errors)): ?>
                <div class="flash flash-err">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="150" value="<?php echo htmlspecialchars($old_email); ?>">
                </div>
                <div class="field">
                    <label for="password">Пароль</label>
                    <input id="password" name="password" type="password" required maxlength="128" autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit">Войти</button>
            </form>

            <p class="auth-switch">Нет аккаунта? <a href="registration.php">Зарегистрироваться</a></p>
        </div>
    </div>
</body>
</html>