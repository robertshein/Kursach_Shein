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
$is_master = $role === User::ROLE_MASTER;
$is_mechanic = $role === User::ROLE_MECHANIC;

$cars = [];
$orders = [];
$flash_error = null;
$flash_success = null;
$cars_available_for_new_order = [];
$car_ids_in_active_orders = [];

if ($is_client) {
    require_once __DIR__ . '/config/connect_database.php';
    require_once __DIR__ . '/models/Order.php';
    require_once __DIR__ . '/controllers/ClientController.php';

    function index_order_is_terminal($status)
    {
        return in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
    }

    $client_controller = new ClientController($mysql_connection);
    $client_id = (int) $user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_car') {
            $vin = trim($_POST['vin'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $year = (int) ($_POST['year'] ?? 0);
            $gosnumber = trim($_POST['gosnumber'] ?? '');

            if ($vin === '' || $brand === '' || $model === '' || $gosnumber === '') {
                $flash_error = 'Заполните все поля автомобиля.';
            } elseif ($year < 1980 || $year > (int) date('Y') + 1) {
                $flash_error = 'Укажите корректный год выпуска.';
            } else {
                $r = $client_controller->addClientCar($client_id, $vin, $brand, $model, $year, $gosnumber);
                if (!($r['success'] ?? false)) {
                    $flash_error = $r['message'] ?? 'Ошибка при добавлении авто.';
                } else {
                    $flash_success = $r['data']['message'] ?? 'Автомобиль добавлен.';
                }
            }
        } elseif ($action === 'create_order') {
            $car_id = (int) ($_POST['car_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($car_id <= 0) {
                $flash_error = 'Выберите автомобиль.';
            } elseif ($description === '') {
                $flash_error = 'Опишите, что нужно сделать с автомобилем.';
            } elseif (!$client_controller->carBelongsToClient($car_id, $client_id)) {
                $flash_error = 'Выбранный автомобиль не принадлежит вам.';
            } elseif ($client_controller->carHasActiveOrder($car_id)) {
                $flash_error = 'Этот автомобиль уже в активной заявке. Выберите другой или дождитесь завершения текущей.';
            } else {
                $r = $client_controller->createOrder($client_id, $car_id, $description, []);
                if (!($r['success'] ?? false)) {
                    $flash_error = $r['message'] ?? 'Не удалось создать заявку.';
                } else {
                    $flash_success = $r['data']['message'] ?? 'Заявка создана.';
                }
            }
        }
    }

    $cars_r = $client_controller->getClientCars($client_id);
    $orders_r = $client_controller->getClientOrders($client_id);
    if ($cars_r['success'] ?? false) {
        $cars = $cars_r['data']['cars'] ?? [];
    }
    if ($orders_r['success'] ?? false) {
        $orders = $orders_r['data']['orders'] ?? [];
    }

    foreach ($orders as $o) {
        $st = $o['status'] ?? '';
        if (!index_order_is_terminal($st)) {
            $car_ids_in_active_orders[(int) ($o['car_id'] ?? 0)] = true;
        }
    }

    foreach ($cars as $c) {
        $cid = (int) ($c['id'] ?? 0);
        if ($cid > 0 && empty($car_ids_in_active_orders[$cid])) {
            $cars_available_for_new_order[] = $c;
        }
    }
}

$nav_active = 'home';
$nav_home_href = 'index.php';
$nav_cabinet_href = 'pages/cabinet.php';
$nav_logout_href = 'pages/authorization.php?logout=1';
$is_admin = $role === User::ROLE_ADMIN;
$nav_show_cabinet = $is_client;
$nav_show_master = $is_master;
$nav_show_mechanic = $is_mechanic;
$nav_show_admin = $is_admin;
$nav_master_section = '';
$nav_master_index_href = 'pages/master/index.php';
$nav_master_new_href = 'pages/master/new_orders.php';
$nav_master_orders_href = 'pages/master/orders.php';
$nav_master_purchases_href = 'pages/master/purchase_requests.php';
$nav_mechanic_section = '';
$nav_mechanic_index_href = 'pages/mechanic/index.php';
$nav_mechanic_orders_href = 'pages/mechanic/orders.php';
$nav_admin_section = '';
$nav_admin_index_href     = 'pages/admin/index.php';
$nav_admin_orders_href    = 'pages/admin/orders.php';
$nav_admin_employees_href = 'pages/admin/employees.php';
$nav_admin_purchases_href = 'pages/admin/purchase_requests.php';
$nav_admin_services_href  = 'pages/admin/services.php';
$nav_admin_parts_href     = 'pages/admin/parts.php';
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
        <?php if ($is_client && $flash_error): ?>
            <div class="flash flash-err"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if ($is_client && $flash_success): ?>
            <div class="flash flash-ok"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>

        <h1 class="sans">Главная</h1>

        <?php if ($is_client): ?>
            <p class="lead">
                Здравствуйте, <?php echo htmlspecialchars($user['full_name'] ?? ''); ?>.
                Здесь можно добавить свои автомобили и отправить заявку в наш сервис.
                Личные данные, список заявок и автомобилей — в <a href="<?php echo htmlspecialchars($nav_cabinet_href); ?>" style="color: var(--focus); font-weight: 600; text-decoration: none;">личном кабинете</a>.
            </p>

            <div class="grid-split">
                <section class="card">
                    <h2>Новая заявка</h2>
                    <?php if (empty($cars)): ?>
                        <p class="empty">Сначала добавьте автомобиль в форме справа — затем можно будет выбрать его в заявке.</p>
                    <?php elseif (empty($cars_available_for_new_order)): ?>
                        <p class="empty">Все ваши автомобили уже указаны в активных заявках. Новую заявку можно создать после завершения или отмены текущей.</p>
                    <?php else: ?>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="create_order">
                            <div class="field">
                                <label for="car_id">Автомобиль</label>
                                <select id="car_id" name="car_id" required>
                                    <option value="">Выберите</option>
                                    <?php foreach ($cars_available_for_new_order as $c): ?>
                                        <option value="<?php echo (int) $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['brand'] . ' ' . $c['model'] . ' · ' . $c['gosnumber']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (count($cars_available_for_new_order) < count($cars)): ?>
                                <p class="hint">В списке только автомобили без активной заявки.</p>
                            <?php endif; ?>
                            <div class="field">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" required maxlength="4000" placeholder="Опишите симптомы или желаемые работы"></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Отправить заявку</button>
                            <p class="hint">После отправки заявку обработает мастер.</p>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="card">
                    <h2>Добавить автомобиль</h2>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="add_car">
                        <div class="row2">
                            <div class="field">
                                <label for="brand">Марка</label>
                                <input id="brand" name="brand" type="text" required maxlength="80" placeholder="Toyota">
                            </div>
                            <div class="field">
                                <label for="model">Модель</label>
                                <input id="model" name="model" type="text" required maxlength="80" placeholder="Camry">
                            </div>
                        </div>
                        <div class="row2">
                            <div class="field">
                                <label for="year">Год</label>
                                <input id="year" name="year" type="number" required min="1980" max="<?php echo (int) date('Y') + 1; ?>">
                            </div>
                            <div class="field">
                                <label for="gosnumber">Госномер</label>
                                <input id="gosnumber" name="gosnumber" type="text" required maxlength="20" placeholder="А003АА159">
                            </div>
                        </div>
                        <div class="field">
                            <label for="vin">VIN</label>
                            <input id="vin" name="vin" type="text" required maxlength="64" placeholder="Идентификатор транспортного средства">
                        </div>
                        <button type="submit" class="btn-submit">Добавить</button>
                    </form>
                </section>
            </div>
        <?php elseif ($is_master): ?>
            <p class="lead">
                Вы вошли как мастер. Назначайте механиков на новые заявки, смотрите все заявки и оформляйте запросы на закупку запчастей.
            </p>
            <p class="sans" style="margin-top: 0;">
                <a class="btn-submit" href="pages/master/index.php" style="display: inline-block; text-decoration: none;">Открыть панель мастера</a>
            </p>
        <?php elseif ($is_mechanic): ?>
            <p class="lead">
                Вы вошли как механик. Здесь можно открыть ваши назначенные заявки и менять их статус по ходу выполнения работ.
            </p>
            <p class="sans" style="margin-top: 0;">
                <a class="btn-submit" href="pages/mechanic/index.php" style="display: inline-block; text-decoration: none;">Открыть панель механика</a>
            </p>
        <?php elseif ($is_admin): ?>
            <p class="lead">
                Вы вошли как администратор. Управляйте сотрудниками и одобряйте закупки запчастей.
            </p>
            <p class="sans" style="margin-top: 0; display: flex; flex-wrap: wrap; gap: 10px;">
                <a class="btn-submit" href="pages/admin/index.php" style="display: inline-block; text-decoration: none;">Панель администратора</a>
                <a class="btn-submit" href="pages/admin/orders.php" style="display: inline-block; text-decoration: none; background:#fff; color:var(--focus);">Заявки</a>
                <a class="btn-submit" href="pages/admin/employees.php" style="display: inline-block; text-decoration: none; background:#fff; color:var(--focus);">Сотрудники</a>
                <a class="btn-submit" href="pages/admin/purchase_requests.php" style="display: inline-block; text-decoration: none; background:#fff; color:var(--focus);">Закупки</a>
                <a class="btn-submit" href="pages/admin/services.php" style="display: inline-block; text-decoration: none; background:#fff; color:var(--focus);">Услуги</a>
                <a class="btn-submit" href="pages/admin/parts.php" style="display: inline-block; text-decoration: none; background:#fff; color:var(--focus);">Запчасти</a>
            </p>
        <?php else: ?>
            <p class="staff lead">
                Вы вошли с ролью <strong><?php echo htmlspecialchars($role); ?></strong>.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
