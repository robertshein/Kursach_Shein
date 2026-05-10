<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: authorization.php');
    exit();
}

require_once __DIR__ . '/../config/connect_database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../controllers/ClientController.php';

$user = $_SESSION['user'];
$role = $user['role'] ?? '';

if ($role !== User::ROLE_CLIENT) {
    header('Location: ../index.php');
    exit();
}

$ORDER_STATUS_LABELS = [
    Order::STATUS_NEW => 'Новая',
    Order::STATUS_ASSIGNED => 'Назначен механик',
    Order::STATUS_IN_PROGRESS => 'В работе',
    Order::STATUS_WAITING_PARTS => 'Ожидание запчастей',
    Order::STATUS_COMPLETED => 'Завершена',
    Order::STATUS_CANCELLED => 'Отменена',
];

function cabinet_order_status_label($status, array $labels)
{
    return $labels[$status] ?? $status;
}

$client_controller = new ClientController($mysql_connection);
$client_id = (int) $user['id'];

$cars = [];
$orders = [];
$flash_error = null;
$flash_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $new_password_confirm = $_POST['new_password_confirm'] ?? '';

        if ($full_name === '') {
            $flash_error = 'Укажите ФИО.';
        } elseif ($phone === '') {
            $flash_error = 'Укажите телефон.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_error = 'Укажите корректный email.';
        } elseif (trim((string) $new_password) !== '' && $new_password !== $new_password_confirm) {
            $flash_error = 'Пароли не совпадают.';
        } else {
            $r = $client_controller->updateClientProfile(
                $client_id,
                $full_name,
                $phone,
                $email,
                (string) $new_password
            );
            if (!($r['success'] ?? false)) {
                $flash_error = $r['message'] ?? 'Не удалось сохранить профиль.';
            } else {
                $flash_success = $r['data']['message'] ?? 'Данные сохранены.';
                $data = $r['data'] ?? [];
                $_SESSION['user']['full_name'] = $data['full_name'] ?? $full_name;
                $_SESSION['user']['phone'] = $data['phone'] ?? $phone;
                $_SESSION['user']['email'] = $data['email'] ?? $email;
                $user = $_SESSION['user'];
            }
        }
    } elseif ($action === 'add_car') {
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

$active_orders = 0;
foreach ($orders as $o) {
    $st = $o['status'] ?? '';
    if (!in_array($st, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
        $active_orders++;
    }
}

$nav_active = 'cabinet';
$nav_home_href = '../index.php';
$nav_cabinet_href = 'cabinet.php';
$nav_logout_href = 'authorization.php?logout=1';
$nav_show_cabinet = true;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <?php include __DIR__ . '/../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/site_nav.php'; ?>

    <div class="page">
        <?php if ($flash_error): ?>
            <div class="flash flash-err"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
            <div class="flash flash-ok"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>

        <h1 class="sans">Личный кабинет</h1>
        <p class="lead">Здравствуйте, <?php echo htmlspecialchars($user['full_name'] ?? ''); ?>. Управляйте контактными данными, автомобилями и заявками в сервис.</p>

        <div class="stats sans">
            <div class="stat"><div class="num"><?php echo count($cars); ?></div><div class="lbl">Автомобилей</div></div>
            <div class="stat"><div class="num"><?php echo count($orders); ?></div><div class="lbl">Всего заявок</div></div>
            <div class="stat"><div class="num"><?php echo (int) $active_orders; ?></div><div class="lbl">Активных</div></div>
        </div>

        <section class="card" id="profile">
            <h2>Личные данные</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="update_profile">
                <div class="row2">
                    <div class="field">
                        <label for="full_name">ФИО</label>
                        <input id="full_name" name="full_name" type="text" required maxlength="150" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="phone">Телефон</label>
                        <input id="phone" name="phone" type="text" required maxlength="30" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="150" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="new_password">Новый пароль</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password" maxlength="128" placeholder="Оставьте пустым, если не меняете">
                        <p class="hint">Не менее 6 символов, если заполняете.</p>
                    </div>
                    <div class="field">
                        <label for="new_password_confirm">Подтверждение пароля</label>
                        <input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" maxlength="128" placeholder="Повторите новый пароль">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Сохранить изменения</button>
            </form>
        </section>

        <div class="grid-split">
            <section class="card">
                <h2>Новая заявка</h2>
                <?php if (empty($cars)): ?>
                    <p class="empty">Добавьте автомобиль ниже — затем можно будет выбрать его в заявке.</p>
                <?php else: ?>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="create_order">
                        <div class="field">
                            <label for="car_id">Автомобиль</label>
                            <select id="car_id" name="car_id" required>
                                <option value="">Выберите</option>
                                <?php foreach ($cars as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['brand'] . ' ' . $c['model'] . ' · ' . $c['gosnumber']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="description">Описание</label>
                            <textarea id="description" name="description" required placeholder="Опишите симптомы или желаемые работы"></textarea>
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

        <section class="card">
            <h2>Мои заявки</h2>
            <?php if (empty($orders)): ?>
                <p class="empty">Заявок пока нет.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="sans">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Авто</th>
                                <th>Статус</th>
                                <th>Механик</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <?php
                                $st = $o['status'] ?? '';
                                $badge_class = 'badge';
                                if ($st === Order::STATUS_COMPLETED) {
                                    $badge_class .= ' badge-done';
                                } elseif (
                                    $st === Order::STATUS_WAITING_PARTS
                                    || $st === Order::STATUS_NEW
                                    || $st === Order::STATUS_ASSIGNED
                                ) {
                                    $badge_class .= ' badge-warn';
                                }
                                $price = isset($o['total_price'])
                                    ? number_format((float) $o['total_price'], 0, '.', ' ') . ' ₽'
                                    : '—';
                                $desc = (string) ($o['description'] ?? '');
                                $desc_short = function_exists('mb_substr')
                                    ? (mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '…' : $desc)
                                    : (strlen($desc) > 100 ? substr($desc, 0, 100) . '…' : $desc);
                                ?>
                                <tr>
                                    <td><?php echo (int) ($o['id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(trim(($o['brand'] ?? '') . ' ' . ($o['model'] ?? '') . ', ' . ($o['gosnumber'] ?? ''))); ?>
                                        <?php if ($desc !== ''): ?>
                                            <div class="hint" style="max-width: 280px;"><?php echo htmlspecialchars($desc_short); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars(cabinet_order_status_label($st, $ORDER_STATUS_LABELS)); ?></span></td>
                                    <td><?php echo !empty($o['mechanic_name']) ? htmlspecialchars($o['mechanic_name']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($price); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($cars)): ?>
            <section class="card">
                <h2>Мои автомобили</h2>
                <ul class="car-list sans">
                    <?php foreach ($cars as $c): ?>
                        <li>
                            <div class="car-title"><?php echo htmlspecialchars(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ', ' . ($c['year'] ?? '') . ' г.'); ?></div>
                            <div class="car-sub"><?php echo htmlspecialchars($c['gosnumber'] ?? ''); ?> · VIN <?php echo htmlspecialchars($c['vin'] ?? ''); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
