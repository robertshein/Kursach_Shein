<?php
$nav_active = $nav_active ?? 'home';
$nav_show_cabinet = $nav_show_cabinet ?? false;
$user_label = htmlspecialchars($_SESSION['user']['full_name'] ?? ($_SESSION['user']['email'] ?? ''));
?>
<nav class="site-nav sans" aria-label="Основное меню">
    <div class="site-nav-inner">
        <div class="site-nav-brand"><a href="<?php echo htmlspecialchars($nav_home_href); ?>">АвтоПлюс</a></div>
        <div class="site-nav-links">
            <a href="<?php echo htmlspecialchars($nav_home_href); ?>" class="<?php echo $nav_active === 'home' ? 'nav-active' : ''; ?>">Главная</a>
            <?php if ($nav_show_cabinet): ?>
                <a href="<?php echo htmlspecialchars($nav_cabinet_href); ?>" class="<?php echo $nav_active === 'cabinet' ? 'nav-active' : ''; ?>">Личный кабинет</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($nav_logout_href); ?>">Выйти</a>
        </div>
        <span class="site-nav-user sans" title="<?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?>"><?php echo $user_label; ?></span>
    </div>
</nav>
