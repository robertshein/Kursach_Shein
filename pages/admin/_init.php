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
if ($role !== User::ROLE_ADMIN) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/connect_database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$admin_user = $_SESSION['user'];
$admin_id = (int) $admin_user['id'];
$admin_controller = new AdminController($mysql_connection);

$nav_home_href = '../../index.php';
$nav_show_cabinet = false;
$nav_show_master = false;
$nav_show_mechanic = false;
$nav_show_admin = true;
$nav_logout_href = '../authorization.php?logout=1';
$nav_admin_index_href = 'index.php';
$nav_admin_employees_href = 'employees.php';
$nav_admin_purchases_href = 'purchase_requests.php';
$nav_admin_salary_href = 'salary.php';
$nav_admin_report_href = 'report_download.php';
$nav_active = '';
$nav_admin_section = '';
