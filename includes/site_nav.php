<?php
$nav_active = $nav_active ?? '';
$nav_show_cabinet = $nav_show_cabinet ?? false;
$nav_show_master = $nav_show_master ?? false;
$nav_master_section = $nav_master_section ?? '';
$nav_master_index_href = $nav_master_index_href ?? 'pages/master/index.php';
$nav_master_new_href = $nav_master_new_href ?? 'pages/master/new_orders.php';
$nav_master_orders_href = $nav_master_orders_href ?? 'pages/master/orders.php';
$nav_master_purchases_href = $nav_master_purchases_href ?? 'pages/master/purchase_requests.php';
$nav_is_guest = $nav_is_guest ?? false;
$nav_login_href = $nav_login_href ?? 'pages/authorization.php';
$nav_register_href = $nav_register_href ?? 'pages/registration.php';
$user_label = htmlspecialchars($_SESSION['user']['full_name'] ?? ($_SESSION['user']['email'] ?? ''));
?>
<nav class="site-nav sans" aria-label="Основное меню">
    <div class="site-nav-inner">
        <div class="site-nav-brand"><a href="<?php echo htmlspecialchars($nav_home_href); ?>">АвтоПлюс</a></div>
        <?php if ($nav_is_guest): ?>
            <div class="site-nav-links">
                <a href="<?php echo htmlspecialchars($nav_login_href); ?>" class="<?php echo $nav_active === 'login' ? 'nav-active' : ''; ?>">Вход</a>
                <a href="<?php echo htmlspecialchars($nav_register_href); ?>" class="<?php echo $nav_active === 'register' ? 'nav-active' : ''; ?>">Регистрация</a>
            </div>
        <?php else: ?>
            <div class="site-nav-links">
                <a href="<?php echo htmlspecialchars($nav_home_href); ?>" class="<?php echo $nav_active === 'home' ? 'nav-active' : ''; ?>">Главная</a>
                <?php if ($nav_show_master): ?>
                    <a href="<?php echo htmlspecialchars($nav_master_index_href); ?>" class="<?php echo $nav_master_section === 'dashboard' ? 'nav-active' : ''; ?>">Панель мастера</a>
                    <a href="<?php echo htmlspecialchars($nav_master_new_href); ?>" class="<?php echo $nav_master_section === 'new' ? 'nav-active' : ''; ?>">Новые заявки</a>
                    <a href="<?php echo htmlspecialchars($nav_master_orders_href); ?>" class="<?php echo $nav_master_section === 'orders' ? 'nav-active' : ''; ?>">Все заявки</a>
                    <a href="<?php echo htmlspecialchars($nav_master_purchases_href); ?>" class="<?php echo $nav_master_section === 'purchases' ? 'nav-active' : ''; ?>">Закупки</a>
                <?php endif; ?>
                <?php if ($nav_show_cabinet): ?>
                    <a href="<?php echo htmlspecialchars($nav_cabinet_href); ?>" class="<?php echo $nav_active === 'cabinet' ? 'nav-active' : ''; ?>">Личный кабинет</a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($nav_logout_href); ?>">Выйти</a>
            </div>
            <span class="site-nav-user sans" title="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>"><?php echo $user_label; ?></span>
        <?php endif; ?>
    </div>
</nav>
