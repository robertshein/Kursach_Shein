<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';

class MechanicController extends BaseController
{
    public function getMyOrders(int $mechanicId): array
    {
        $check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getOrdersByMechanic($mechanicId)]);
    }

    public function updateOrderStatus(int $orderId, int $mechanicId, string $newStatus): array
    {
        $check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $allowed = [Order::STATUS_IN_PROGRESS, Order::STATUS_WAITING_PARTS, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED];
        if (!in_array($newStatus, $allowed, true)) return $this->fail('Механик не может установить этот статус');
        if ($orderId <= 0 || $mechanicId <= 0) return $this->fail('Некорректные параметры');

        $order = $this->orders()->findById($orderId);
        if (!$order || (int) $order['mechanic_id'] !== $mechanicId) return $this->fail('Заявка не найдена или не назначена вам', 404);

        $current = $order['status'];
        if (in_array($current, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            return $this->fail('Статус завершённой или отменённой заявки менять нельзя');
        }

        $transitions = [
            Order::STATUS_ASSIGNED      => [Order::STATUS_IN_PROGRESS],
            Order::STATUS_IN_PROGRESS   => [Order::STATUS_WAITING_PARTS, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
            Order::STATUS_WAITING_PARTS => [Order::STATUS_IN_PROGRESS,   Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        ];
        if (!in_array($newStatus, $transitions[$current] ?? [], true)) {
            return $this->fail("Переход из «{$current}» в «{$newStatus}» недопустим");
        }

        if ($this->orders()->updateStatus($orderId, $newStatus, $mechanicId) < 1) return $this->fail('Статус не изменён');
        return $this->ok(['message' => 'Статус заявки обновлён']);
    }

    public function getServices(): array
    {
        $check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['services' => $this->services()->getAll()]);
    }

    public function addServiceToOrder(int $orderId, int $mechanicId, int $serviceId, int $quantity = 1, string $comment = ''): array
    {
        $check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if ($orderId <= 0 || $mechanicId <= 0 || $serviceId <= 0) return $this->fail('Некорректные параметры');
        $quantity = max(1, $quantity);

        $order = $this->orders()->findById($orderId);
        if (!$order || (int) $order['mechanic_id'] !== $mechanicId) return $this->fail('Заявка не найдена или не назначена вам');
        if (in_array($order['status'], [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            return $this->fail('Нельзя добавлять услуги в завершённую или отменённую заявку');
        }
        if (!$this->services()->exists($serviceId)) return $this->fail('Услуга не найдена');

        mysqli_begin_transaction($this->db);
        if (!$this->orders()->upsertService($orderId, $serviceId, $quantity, trim($comment))) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось добавить услугу в заявку', 500);
        }
        if (!$this->orders()->recalculateTotal($orderId)) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось пересчитать итоговую сумму заявки', 500);
        }
        mysqli_commit($this->db);
        return $this->ok(['message' => 'Услуга добавлена в заявку']);
    }
}
