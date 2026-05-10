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
if (!in_array($role, [User::ROLE_MASTER, User::ROLE_ADMIN], true)) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/connect_database.php';
require_once __DIR__ . '/../../controllers/MasterController.php';

$master_user = $_SESSION['user'];
$master_id = (int) $master_user['id'];
$master_controller = new MasterController($mysql_connection);

$nav_home_href = '../../index.php';
$nav_show_cabinet = false;
$nav_show_master = true;
$nav_logout_href = '../authorization.php?logout=1';
$nav_master_index_href = 'index.php';
$nav_master_new_href = 'new_orders.php';
$nav_master_orders_href = 'orders.php';
$nav_master_purchases_href = 'purchase_requests.php';
$nav_active = '';
