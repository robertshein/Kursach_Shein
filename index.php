<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: pages/authorization.php');
    exit();
}

require_once __DIR__ . '/models/User.php';

$user = $_SESSION['user'];
$role = $user['role'] ?? '';
$is_client = $role === User::ROLE_CLIENT;

$nav_active = 'home';
$nav_home_href = 'index.php';
$nav_cabinet_href = 'pages/cabinet.php';
$nav_logout_href = 'pages/authorization.php?logout=1';
$nav_show_cabinet = $is_client;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>АвтоПлюс</title>
    <?php include __DIR__ . '/includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/site_nav.php'; ?>

    <div class="page">
        <h1 class="sans">Главная</h1>

        <?php if ($is_client): ?>
            <p class="lead">
                Вы вошли в систему автосервиса <strong>АвтоПлюс</strong>.
                Чтобы изменить профиль, добавить автомобиль или подать заявку на ремонт, перейдите в личный кабинет.
            </p>
            <p class="sans" style="margin-top: 0;">
                <a class="btn-submit" href="<?php echo htmlspecialchars($nav_cabinet_href); ?>" style="display: inline-block; text-decoration: none;">Открыть личный кабинет</a>
            </p>
        <?php else: ?>
            <p class="staff lead">
                Вы вошли с ролью <strong><?php echo htmlspecialchars($role); ?></strong>.
                Интерфейс для вашей роли на главной будет расширен позже.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
