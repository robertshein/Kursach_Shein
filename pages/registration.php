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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: radial-gradient(circle at 80% 20%, #0ea5a4 0%, #1f2a44 48%, #121826 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #e5ebff;
        }
        .wrap {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, .35);
        }
        h1 { margin: 0 0 6px; font-size: 28px; }
        .subtitle { margin: 0 0 16px; color: #c9fff5; font-size: 14px; }
        label { display: block; margin: 12px 0 6px; font-weight: 600; color: #d7fff8; }
        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            outline: none;
        }
        input:focus { border-color: #6df7dd; box-shadow: 0 0 0 3px rgba(14, 165, 164, .25); }
        button {
            margin-top: 18px;
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, #13c2c2, #0ea5a4);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        button:hover { transform: translateY(-1px); box-shadow: 0 10px 18px rgba(14, 165, 164, .35); }
        .errors { background: rgba(255, 77, 77, 0.15); border: 1px solid rgba(255, 122, 122, 0.45); color: #ffd3d3; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .success { background: rgba(57, 226, 157, 0.18); border: 1px solid rgba(136, 255, 210, 0.45); color: #d8fff0; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .link { margin-top: 16px; text-align: center; color: #d7fff8; }
        a { color: #97fff0; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Регистрация</h1>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="full_name">ФИО</label>
            <input id="full_name" name="full_name" type="text" required value="<?php echo htmlspecialchars($old['full_name']); ?>">

            <label for="phone">Телефон</label>
            <input id="phone" name="phone" type="text" required value="<?php echo htmlspecialchars($old['phone']); ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($old['email']); ?>">

            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required>

            <label for="password_confirm">Повтор пароля</label>
            <input id="password_confirm" name="password_confirm" type="password" required>

            <button type="submit">Зарегистрироваться</button>
        </form>

        <div class="link">
            Уже есть аккаунт? <a href="authorization.php">Войти</a>
        </div>
    </div>
</body>
</html>