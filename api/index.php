<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../config/connect_database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/SalaryRecord.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ClientController.php';
require_once __DIR__ . '/../controllers/MasterController.php';
require_once __DIR__ . '/../controllers/MechanicController.php';
require_once __DIR__ . '/../controllers/AdminController.php';


$method   = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo !== '') {
    $basePath = $pathInfo;
} else {
    $uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = preg_replace('#^.*/api(/index\.php)?#', '', $uri);
}
$basePath = rtrim($basePath, '/') ?: '/';

$segments = explode('/', ltrim($basePath, '/'));

$resource = $segments[0] ?? '';
$seg1 = $segments[1] ?? '';   
$seg2 = $segments[2] ?? '';   

$id = is_numeric($seg1) ? (int)$seg1 : 0;

$auth = new AuthController($mysql_connection);
$client = new ClientController($mysql_connection);
$master = new MasterController($mysql_connection);
$mechanic = new MechanicController($mysql_connection);
$admin = new AdminController($mysql_connection);

switch ($resource) {

    case 'auth':
        switch ($seg1) {
            case 'login':
                if ($method !== 'POST') api_error('Метод не поддерживается', 405);
                $b = api_body();
                api_from_controller($auth->login(
                    trim((string) api_param($b, 'email',    '')),
                    trim((string) api_param($b, 'password', ''))
                ));

            case 'logout':
                if ($method !== 'POST') api_error('Метод не поддерживается', 405);
                api_from_controller($auth->logout());

            case 'register':
                if ($method !== 'POST') api_error('Метод не поддерживается', 405);
                $b = api_body();
                api_from_controller($auth->register(
                    trim((string) api_param($b, 'full_name', '')),
                    trim((string) api_param($b, 'phone',     '')),
                    trim((string) api_param($b, 'email',     '')),
                    (string)      api_param($b, 'password',  '')
                ), 201);

            case 'me':
                if ($method !== 'GET') api_error('Метод не поддерживается', 405);
                $user = api_require_auth();
                api_ok(['user' => $user]);

            default:
                api_error('Неизвестный маршрут', 404);
        }
        break;

    case 'orders':
        if ($id === 0 && $seg1 === '') {
            if ($method === 'GET') {
                $user = api_require_auth();
                $role = $user['role'];
                if ($role === User::ROLE_MECHANIC) {
                    api_from_controller($mechanic->getMyOrders((int)$user['id']));
                } elseif (in_array($role, [User::ROLE_MASTER, User::ROLE_ADMIN], true)) {
                    api_from_controller($master->getAllOrders());
                } elseif ($role === User::ROLE_CLIENT) {
                    api_from_controller($client->getClientOrders((int)$user['id']));
                } else {
                    api_error('Недостаточно прав', 403);
                }
            } elseif ($method === 'POST') {
                $user = api_require_auth();
                $b    = api_body();
                api_from_controller($client->createOrder(
                    (int) $user['id'],
                    (int) api_param($b, 'car_id', 0),
                    trim((string) api_param($b, 'description', '')),
                    (array) api_param($b, 'services', [])
                ), 201);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($id > 0 && $seg2 === '') {
            if ($method === 'GET') {
                $user = api_require_auth();
                $order = (new OrderContext($mysql_connection))->findById($id);
                if (!$order) api_error('Заявка не найдена', 404);
                api_ok(['order' => $order]);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($id > 0 && $seg2 === 'assign') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($master->assignMechanic(
                $id,
                (int)    api_param($b, 'mechanic_id', 0),
                (int)    $user['id'],
                (string) api_param($b, 'comment', ''),
                (array)  api_param($b, 'services', [])
            ));
        } elseif ($id > 0 && $seg2 === 'status') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($mechanic->updateOrderStatus(
                $id,
                (int) $user['id'],
                trim((string) api_param($b, 'status', ''))
            ));
        } elseif ($id > 0 && $seg2 === 'services') {
            if ($method !== 'POST') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($mechanic->addServiceToOrder(
                $id,
                (int)    $user['id'],
                (int)    api_param($b, 'service_id', 0),
                (int)    api_param($b, 'quantity', 1),
                (string) api_param($b, 'comment', '')
            ), 201);
        } else {
            api_error('Маршрут не найден', 404);
        }
        break;

    case 'cars':
        if ($id === 0 && $seg1 === '') {
            if ($method === 'GET') {
                $user = api_require_auth();
                api_from_controller($client->getClientCars((int)$user['id']));
            } elseif ($method === 'POST') {
                $user = api_require_auth();
                $b    = api_body();
                api_from_controller($client->addClientCar(
                    (int)    $user['id'],
                    trim((string) api_param($b, 'vin',       '')),
                    trim((string) api_param($b, 'brand',     '')),
                    trim((string) api_param($b, 'model',     '')),
                    (int)    api_param($b, 'year',      0),
                    trim((string) api_param($b, 'gosnumber', ''))
                ), 201);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($id > 0 && $seg2 === '') {
            if ($method !== 'PUT') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($client->updateClientCar(
                (int)    $user['id'],
                $id,
                trim((string) api_param($b, 'vin',       '')),
                trim((string) api_param($b, 'brand',     '')),
                trim((string) api_param($b, 'model',     '')),
                (int)    api_param($b, 'year',      0),
                trim((string) api_param($b, 'gosnumber', ''))
            ));
        } else {
            api_error('Маршрут не найден', 404);
        }
        break;

    case 'mechanics':
        if ($method !== 'GET') api_error('Метод не поддерживается', 405);
        api_require_auth();
        api_from_controller($master->getMechanicsWithLoad());
        break;

    case 'services':
        if ($method !== 'GET') api_error('Метод не поддерживается', 405);
        api_require_auth();
        api_from_controller($master->getServices());
        break;

    case 'parts':
        if ($method !== 'GET') api_error('Метод не поддерживается', 405);
        api_require_auth();
        api_from_controller($master->getParts());
        break;

    case 'employees':
        if ($id === 0 && $seg1 === '') {
            if ($method === 'GET') {
                api_from_controller($admin->getEmployees());
            } elseif ($method === 'POST') {
                $b = api_body();
                api_from_controller($admin->createEmployee(
                    trim((string) api_param($b, 'full_name', '')),
                    trim((string) api_param($b, 'phone',     '')),
                    trim((string) api_param($b, 'email',     '')),
                    (string)      api_param($b, 'password',  ''),
                    trim((string) api_param($b, 'role',      '')),
                    (float)       api_param($b, 'salary',    0)
                ), 201);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($id > 0 && $seg2 === '') {
            if ($method !== 'PUT') api_error('Метод не поддерживается', 405);
            $b = api_body();
            api_from_controller($admin->updateEmployee(
                $id,
                trim((string) api_param($b, 'full_name', '')),
                trim((string) api_param($b, 'phone',     '')),
                trim((string) api_param($b, 'email',     '')),
                trim((string) api_param($b, 'role',      '')),
                (float)       api_param($b, 'salary',    0)
            ));
        } elseif ($id > 0 && $seg2 === 'active') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $b = api_body();
            api_from_controller($admin->setEmployeeActive($id, (bool) api_param($b, 'is_active', true)));
        } elseif ($id > 0 && $seg2 === 'salary') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $b = api_body();
            api_from_controller($admin->setSalary($id, (float) api_param($b, 'salary', 0)));
        } else {
            api_error('Маршрут не найден', 404);
        }
        break;

    case 'purchases':
        if ($id === 0 && $seg1 === '') {
            if ($method === 'GET') {
                $user = api_require_auth();
                $role = $user['role'];
                if ($role === User::ROLE_ADMIN) {
                    api_from_controller($admin->getAllPurchaseRequests());
                } else {
                    api_from_controller($master->getPendingPurchaseRequests());
                }
            } elseif ($method === 'POST') {
                $user = api_require_auth();
                $b    = api_body();
                api_from_controller($master->createPartPurchaseRequest(
                    (int)    api_param($b, 'order_id',  0),
                    (int)    api_param($b, 'part_id',   0),
                    (int)    api_param($b, 'quantity',  1),
                    (int)    $user['id'],
                    (string) api_param($b, 'comment',   '')
                ), 201);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($id > 0 && $seg2 === 'decide') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($admin->decidePurchaseRequest(
                $id,
                (int)    $user['id'],
                (bool)   api_param($b, 'approve', false),
                (string) api_param($b, 'comment', '')
            ));
        } else {
            api_error('Маршрут не найден', 404);
        }
        break;

    case 'salary':
        if ($seg1 === '') {
            if ($method === 'GET') {
                api_from_controller($admin->getSalaryByPeriods());
            } elseif ($method === 'POST') {
                $b = api_body();
                $user = api_require_auth();
                api_from_controller($admin->createSalaryRecord(
                    (int)    api_param($b, 'employee_id',  0),
                    (float)  api_param($b, 'amount',       0),
                    (string) api_param($b, 'period_start', ''),
                    (string) api_param($b, 'period_end',   ''),
                    (int)    $user['id'],
                    (string) api_param($b, 'comment',      '')
                ), 201);
            } else {
                api_error('Метод не поддерживается', 405);
            }
        } elseif ($seg1 === 'generate' && $seg2 === '') {
            if ($method !== 'POST') api_error('Метод не поддерживается', 405);
            $user = api_require_auth();
            $b    = api_body();
            api_from_controller($admin->generatePayroll(
                (string) api_param($b, 'period_start', ''),
                (string) api_param($b, 'period_end',   ''),
                (int)    $user['id']
            ), 201);
        } elseif ($seg1 === 'bulk-status' && $seg2 === '') {
            if ($method !== 'POST') api_error('Метод не поддерживается', 405);
            $b = api_body();
            api_from_controller($admin->bulkSetSalaryStatus(
                (string) api_param($b, 'period_start', ''),
                (string) api_param($b, 'period_end',   ''),
                (string) api_param($b, 'from_status',  ''),
                (string) api_param($b, 'to_status',    '')
            ));
        } elseif ($id > 0 && $seg2 === 'status') {
            if ($method !== 'PATCH') api_error('Метод не поддерживается', 405);
            $b = api_body();
            api_from_controller($admin->setSalaryRecordStatus(
                $id,
                (string) api_param($b, 'status', '')
            ));
        } else {
            api_error('Маршрут не найден', 404);
        }
        break;

    case 'stats':
        if ($method !== 'GET') api_error('Метод не поддерживается', 405);
        api_from_controller($admin->getDashboardStats());
        break;

    case 'docs':
    case '':
        http_response_code(302);
        header('Location: docs.php');
        exit;

    default:
        api_error("Маршрут /{$resource} не найден", 404);
}
