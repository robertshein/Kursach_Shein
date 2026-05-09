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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: radial-gradient(circle at 20% 20%, #2f6feb 0%, #1f2a44 45%, #121826 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #e5ebff;
        }
        .wrap {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, .35);
        }
        h1 { margin: 0 0 6px; font-size: 28px; }
        .subtitle { margin: 0 0 16px; color: #cdd8ff; font-size: 14px; }
        label { display: block; margin: 12px 0 6px; font-weight: 600; color: #dbe4ff; }
        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            outline: none;
        }
        input:focus { border-color: #8fb2ff; box-shadow: 0 0 0 3px rgba(47, 111, 235, .25); }
        button {
            margin-top: 18px;
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #4d8dff, #2f6feb);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        button:hover { transform: translateY(-1px); box-shadow: 0 10px 18px rgba(47, 111, 235, .35); }
        .errors {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid rgba(255, 122, 122, 0.45);
            color: #ffd3d3;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .link { margin-top: 16px; text-align: center; color: #cdd8ff; }
        a { color: #9fc1ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Авторизация</h1>
        <p class="subtitle">Вход в систему автосервиса</p>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($old_email); ?>">

            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Войти</button>
        </form>

        <div class="link">
            Нет аккаунта? <a href="registration.php">Зарегистрироваться</a>
        </div>
    </div>
</body>
</html>