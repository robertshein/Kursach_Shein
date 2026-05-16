<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';

class ClientController extends BaseController
{
    public function createOrder(int $clientId, int $carId, string $description, array $services = []): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if ($this->orders()->carHasActiveOrder($carId)) {
            return $this->fail('Этот автомобиль уже указан в активной заявке. Дождитесь её завершения или отмены.');
        }

        $master   = $this->users()->getLeastBusyMaster();
        $masterId = $master ? (int) $master['id'] : null;

        mysqli_begin_transaction($this->db);

        $orderId = $this->orders()->create($clientId, $carId, $description, $masterId);
        if (!$orderId) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось создать заявку', 500);
        }

        foreach ($services as $svc) {
            $svcId    = (int) ($svc['service_id'] ?? 0);
            $quantity = max(1, (int) ($svc['quantity'] ?? 1));
            if ($svcId <= 0) continue;
            if (!$this->orders()->addService($orderId, $svcId, $quantity, (string) ($svc['comment'] ?? ''))) {
                mysqli_rollback($this->db);
                return $this->fail('Не удалось добавить услуги к заявке', 500);
            }
        }

        if (!empty($services)) $this->orders()->recalculateTotal($orderId);

        mysqli_commit($this->db);

        $this->history()->log($orderId, null, Order::STATUS_NEW, $clientId);

        return $this->ok([
            'order_id'    => $orderId,
            'master_id'   => $masterId,
            'master_name' => $master['full_name'] ?? null,
            'message'     => 'Заявка создана' . ($masterId ? ' и назначена мастеру' : ''),
        ]);
    }

    public function getClientOrders(int $clientId): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN, User::ROLE_MASTER]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getOrdersByClient($clientId)]);
    }

    public function updateClientOrderDescription(int $clientId, int $orderId, string $description): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        $description = trim($description);
        if ($description === '') return $this->fail('Укажите описание заявки.');
        $affected = $this->orders()->updateDescription($orderId, $clientId, $description);
        if ($affected < 1) return $this->fail('Изменить описание можно только у новой заявки, которую мастер ещё не принял.');
        return $this->ok(['message' => 'Описание заявки обновлено.']);
    }

    public function getClientCars(int $clientId): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN, User::ROLE_MASTER]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['cars' => $this->cars()->getByClient($clientId)]);
    }

    public function addClientCar(int $clientId, string $vin, string $brand, string $model, int $year, string $gosnumber): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($this->cars()->vinExists($vin)) return $this->fail('Автомобиль с таким VIN уже зарегистрирован');
        $carId = $this->cars()->create($clientId, $vin, $brand, $model, $year, $gosnumber);
        if (!$carId) return $this->fail('Не удалось добавить автомобиль', 500);
        return $this->ok(['car_id' => $carId, 'message' => 'Автомобиль добавлен']);
    }

    public function updateClientCar(int $clientId, int $carId, string $vin, string $brand, string $model, int $year, string $gosnumber): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->cars()->belongsToClient($carId, $clientId)) return $this->fail('Автомобиль не найден.');
        if ($this->orders()->carHasActiveOrder($carId)) return $this->fail('Нельзя редактировать автомобиль, пока он участвует в активной заявке.');
        if ($this->cars()->vinExists($vin, $carId)) return $this->fail('Автомобиль с таким VIN уже зарегистрирован.');
        if (!$this->cars()->update($carId, $vin, $brand, $model, $year, $gosnumber)) return $this->fail('Не удалось сохранить данные автомобиля', 500);
        return $this->ok(['message' => 'Данные автомобиля сохранены.']);
    }

    public function updateClientProfile(int $clientId, string $fullName, string $phone, string $email, string $newPassword = ''): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($this->users()->emailExists($email, $clientId)) return $this->fail('Пользователь с таким email уже существует');

        $this->users()->updateProfile($clientId, $fullName, $phone, $email);

        $newPassword = trim($newPassword);
        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) return $this->fail('Новый пароль должен быть не короче 6 символов');
            $this->users()->updatePassword($clientId, password_hash($newPassword, PASSWORD_BCRYPT));
        }

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!empty($_SESSION['user'])) {
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['phone']     = $phone;
            $_SESSION['user']['email']     = $email;
        }

        return $this->ok(['message' => 'Данные сохранены', 'full_name' => $fullName, 'phone' => $phone, 'email' => $email]);
    }

    public function cancelOrder(int $clientId, int $orderId): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $order = $this->orders()->findById($orderId);
        if (!$order || (int) $order['client_id'] !== $clientId) {
            return $this->fail('Заявка не найдена.', 404);
        }
        if ($order['status'] !== Order::STATUS_NEW) {
            return $this->fail('Отменить можно только новую заявку, которую мастер ещё не принял в работу.');
        }

        $this->orders()->updateStatus($orderId, Order::STATUS_CANCELLED);
        $this->history()->log($orderId, Order::STATUS_NEW, Order::STATUS_CANCELLED, $clientId);
        return $this->ok(['message' => 'Заявка отменена.']);
    }

    public function getOrderComposition(int $clientId): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok([
            'services' => $this->orders()->getServicesForClientOrders($clientId),
            'parts'    => $this->orders()->getPartsForClientOrders($clientId),
        ]);
    }

    public function getOrderHistory(int $clientId): array
    {
        $check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['history' => $this->history()->getForClientOrders($clientId)]);
    }

    public function carHasActiveOrder(int $carId): bool   { return $this->orders()->carHasActiveOrder($carId); }
    public function carBelongsToClient(int $carId, int $clientId): bool { return $this->cars()->belongsToClient($carId, $clientId); }
}
