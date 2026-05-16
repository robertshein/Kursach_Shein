<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';

class AdminController extends BaseController
{
    public function getEmployees(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['employees' => $this->users()->getEmployees()]);
    }

    public function getClients(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['clients' => $this->users()->getClients()]);
    }

    public function createEmployee(string $fullName, string $phone, string $email, string $password, string $role): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN], true)) return $this->fail('Некорректная роль сотрудника');
        if ($this->users()->emailExists($email)) return $this->fail('Пользователь с таким email уже существует');

        $id = $this->users()->create($fullName, $phone, $email, $role, password_hash($password, PASSWORD_BCRYPT));
        if (!$id) return $this->fail('Не удалось добавить сотрудника', 500);
        return $this->ok(['employee_id' => $id, 'message' => 'Сотрудник добавлен']);
    }

    public function updateEmployee(int $employeeId, string $fullName, string $phone, string $email, string $role): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN], true)) return $this->fail('Некорректная роль сотрудника');
        if ($this->users()->emailExists($email, $employeeId)) return $this->fail('Пользователь с таким email уже существует');
        if (!$this->users()->update($employeeId, $fullName, $phone, $email, $role)) return $this->fail('Не удалось обновить данные сотрудника', 500);
        return $this->ok(['message' => 'Данные сотрудника обновлены']);
    }

    public function setEmployeeActive(int $employeeId, bool $isActive): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->users()->setActive($employeeId, $isActive)) return $this->fail('Не удалось изменить статус сотрудника', 500);
        return $this->ok(['message' => 'Статус сотрудника обновлён']);
    }

    public function getAllPurchaseRequests(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['requests' => $this->purchases()->getAll()]);
    }

    public function decidePurchaseRequest(int $requestId, int $adminId, bool $approve, ?string $comment = null): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $status = $approve ? PartPurchaseRequest::STATUS_APPROVED : PartPurchaseRequest::STATUS_REJECTED;
        if (!$this->purchases()->decide($requestId, $adminId, $status, $comment)) return $this->fail('Не удалось обработать запрос на закупку', 500);
        return $this->ok(['message' => 'Запрос на закупку обработан']);
    }

    public function getAllOrders(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getAllOrders()]);
    }

    public function forceOrderStatus(int $orderId, string $newStatus): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!Order::isValidStatus($newStatus)) return $this->fail('Недопустимый статус');
        $order = $this->orders()->findById($orderId);
        if (!$order) return $this->fail('Заявка не найдена', 404);
        if ($order['status'] === $newStatus) return $this->fail('Заявка уже имеет этот статус');
        $this->orders()->updateStatus($orderId, $newStatus);
        $this->history()->log($orderId, $order['status'], $newStatus, $this->currentUserId());
        return $this->ok(['message' => 'Статус заявки изменён']);
    }

    public function getServices(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['services' => $this->services()->getAll()]);
    }

    public function createService(string $name, float $price): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($name === '') return $this->fail('Укажите название услуги');
        if ($price < 0) return $this->fail('Цена не может быть отрицательной');
        if ($this->services()->nameExists($name)) return $this->fail('Услуга с таким названием уже существует');
        $id = $this->services()->create($name, $price);
        if (!$id) return $this->fail('Не удалось создать услугу', 500);
        return $this->ok(['service_id' => $id, 'message' => 'Услуга добавлена']);
    }

    public function updateService(int $id, string $name, float $price): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($name === '') return $this->fail('Укажите название услуги');
        if ($price < 0) return $this->fail('Цена не может быть отрицательной');
        if (!$this->services()->exists($id)) return $this->fail('Услуга не найдена', 404);
        if ($this->services()->nameExists($name, $id)) return $this->fail('Услуга с таким названием уже существует');
        if (!$this->services()->update($id, $name, $price)) return $this->fail('Не удалось обновить услугу', 500);
        return $this->ok(['message' => 'Услуга обновлена']);
    }

    public function deleteService(int $id): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->services()->exists($id)) return $this->fail('Услуга не найдена', 404);
        if ($this->services()->isUsedInOrders($id)) return $this->fail('Нельзя удалить услугу: она используется в заявках');
        if (!$this->services()->delete($id)) return $this->fail('Не удалось удалить услугу', 500);
        return $this->ok(['message' => 'Услуга удалена']);
    }

    public function getParts(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['parts' => $this->parts()->getAll()]);
    }

    public function createPart(string $name, string $article, int $quantity, float $price): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($name === '') return $this->fail('Укажите название запчасти');
        if ($article === '') return $this->fail('Укажите артикул');
        if ($quantity < 0) return $this->fail('Количество не может быть отрицательным');
        if ($price < 0) return $this->fail('Цена не может быть отрицательной');
        if ($this->parts()->articleExists($article)) return $this->fail('Запчасть с таким артикулом уже существует');
        $id = $this->parts()->create($name, $article, $quantity, $price);
        if (!$id) return $this->fail('Не удалось создать запчасть', 500);
        return $this->ok(['part_id' => $id, 'message' => 'Запчасть добавлена']);
    }

    public function updatePart(int $id, string $name, string $article, int $quantity, float $price): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if ($name === '') return $this->fail('Укажите название запчасти');
        if ($article === '') return $this->fail('Укажите артикул');
        if ($quantity < 0) return $this->fail('Количество не может быть отрицательным');
        if ($price < 0) return $this->fail('Цена не может быть отрицательной');
        if (!$this->parts()->exists($id)) return $this->fail('Запчасть не найдена', 404);
        if ($this->parts()->articleExists($article, $id)) return $this->fail('Запчасть с таким артикулом уже существует');
        if (!$this->parts()->update($id, $name, $article, $quantity, $price)) return $this->fail('Не удалось обновить запчасть', 500);
        return $this->ok(['message' => 'Запчасть обновлена']);
    }

    public function deletePart(int $id): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->parts()->exists($id)) return $this->fail('Запчасть не найдена', 404);
        if ($this->parts()->isUsedInRequests($id)) return $this->fail('Нельзя удалить запчасть: она фигурирует в запросах на закупку');
        if (!$this->parts()->delete($id)) return $this->fail('Не удалось удалить запчасть', 500);
        return $this->ok(['message' => 'Запчасть удалена']);
    }

    public function getDashboardStats(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $orderStats = $this->orders()->getStats();
        $stats = [
            'total_orders'      => (int) ($orderStats['total']    ?? 0),
            'new_orders'        => (int) ($orderStats['cnt_new']  ?? 0),
            'active_employees'  => 0,
            'total_clients'     => 0,
            'pending_purchases' => $this->purchases()->countPending(),
        ];

        $res = mysqli_query($this->db, "SELECT COUNT(*) AS c FROM users WHERE role = 'client'");
        $stats['total_clients'] = $res ? (int) $res->fetch_assoc()['c'] : 0;

        $res2 = mysqli_query($this->db, "SELECT COUNT(*) AS c FROM users WHERE role != 'client' AND is_active = 1");
        $stats['active_employees'] = $res2 ? (int) $res2->fetch_assoc()['c'] : 0;

        return $this->ok(['stats' => $stats]);
    }
}
