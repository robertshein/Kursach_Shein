<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';

class MasterController extends BaseController
{
    public function getNewOrders(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getNewOrdersForMaster($this->currentUserId())]);
    }

    public function getAllOrders(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getAllOrders()]);
    }

    public function getMechanics(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['mechanics' => $this->users()->getMechanics()]);
    }

    public function getMechanicsWithLoad(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['mechanics' => $this->users()->getMechanicsWithLoad()]);
    }

    public function getParts(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['parts' => $this->parts()->getAll()]);
    }

    public function getServices(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['services' => $this->services()->getAll()]);
    }

    public function getOrdersForPartRequest(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['orders' => $this->orders()->getOrdersAvailableForPurchase()]);
    }

    public function assignMechanic(int $orderId, int $mechanicId, int $masterId, ?string $comment = null, array $orderServices = []): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!$this->users()->isActiveMechanic($mechanicId)) return $this->fail('Механик не найден или недоступен');

        $normalizedServices = $this->normalizeServices($orderServices);
        foreach ($normalizedServices as $svc) {
            if (!$this->services()->exists($svc['service_id'])) return $this->fail('Одна из выбранных услуг не найдена в справочнике');
        }

        mysqli_begin_transaction($this->db);

        $affected = $this->orders()->assignMechanicToOrder($orderId, $mechanicId, $masterId);
        if ($affected < 1) {
            mysqli_rollback($this->db);
            return $this->fail('Заявка не найдена или уже принята другим мастером');
        }

        $commentStr = trim((string) ($comment ?? ''));
        if (!$this->assignments()->create($orderId, $mechanicId, $masterId, $commentStr)) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось сохранить назначение', 500);
        }

        foreach ($normalizedServices as $svc) {
            if (!$this->orders()->addService($orderId, $svc['service_id'], $svc['quantity'])) {
                mysqli_rollback($this->db);
                return $this->fail('Не удалось привязать услуги к заявке', 500);
            }
        }

        if (!empty($normalizedServices)) $this->orders()->recalculateTotal($orderId);

        mysqli_commit($this->db);

        return $this->ok(['message' => empty($normalizedServices) ? 'Механик назначен' : 'Механик назначен, услуги добавлены']);
    }

    public function createPartPurchaseRequest(int $orderId, int $partId, int $quantity, int $masterId, ?string $comment = null): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if ($orderId <= 0 || $partId <= 0 || $quantity < 1) return $this->fail('Укажите заявку, запчасть и количество');

        $order = $this->orders()->findById($orderId);
        if (!$order) return $this->fail('Заявка не найдена');

        $allowedStatuses = [Order::STATUS_ASSIGNED, Order::STATUS_IN_PROGRESS, Order::STATUS_WAITING_PARTS];
        if (!in_array($order['status'], $allowedStatuses, true)) {
            return $this->fail('Для этой заявки нельзя оформить запрос на закупку (механик должен быть назначен).');
        }

        if (!$this->parts()->exists($partId)) return $this->fail('Запчасть не найдена');

        $requestId = $this->purchases()->create($orderId, $partId, $quantity, $masterId, trim((string) ($comment ?? '')));
        if (!$requestId) return $this->fail('Не удалось создать запрос на закупку', 500);

        $this->orders()->setWaitingParts($orderId);

        return $this->ok(['request_id' => $requestId, 'message' => 'Запрос на закупку создан']);
    }

    public function getPendingPurchaseRequests(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['requests' => $this->purchases()->getPending()]);
    }

    private function normalizeServices(array $raw): array
    {
        $result = [];
        foreach ($raw as $row) {
            $sid = (int) ($row['service_id'] ?? 0);
            if ($sid > 0) $result[] = ['service_id' => $sid, 'quantity' => max(1, (int) ($row['quantity'] ?? 1))];
        }
        return $result;
    }
}
