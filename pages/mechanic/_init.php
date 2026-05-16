<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: ../authorization.php');
    exit;
}

require_once __DIR__ . '/../../models/User.php';

$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_ADMIN], true)) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/connect_database.php';
require_once __DIR__ . '/../../controllers/MechanicController.php';

$mechanic_user = $_SESSION['user'];
$mechanic_id = (int) $mechanic_user['id'];
$mechanic_controller = new MechanicController($mysql_connection);

$nav_home_href = '../../index.php';
$nav_show_cabinet = false;
$nav_show_master = false;
$nav_show_mechanic = true;
$nav_logout_href = '../authorization.php?logout=1';
$nav_mechanic_index_href     = 'index.php';
$nav_mechanic_orders_href    = 'orders.php';
$nav_mechanic_archive_href   = 'archive.php';
$nav_mechanic_purchases_href = 'purchase_requests.php';
$nav_active = '';
