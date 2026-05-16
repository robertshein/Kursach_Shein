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

        $this->history()->log($orderId, Order::STATUS_NEW, Order::STATUS_ASSIGNED, $masterId);

        return $this->ok(['message' => empty($normalizedServices) ? 'Механик назначен' : 'Механик назначен, услуги добавлены']);
    }

    public function reassignMechanic(int $orderId, int $newMechanicId, int $masterId, string $comment = ''): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $order = $this->orders()->findById($orderId);
        if (!$order) return $this->fail('Заявка не найдена', 404);

        if (in_array($order['status'], [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            return $this->fail('Нельзя переназначить механика на завершённую или отменённую заявку');
        }
        if (!(int) $order['mechanic_id']) {
            return $this->fail('На эту заявку ещё не назначен механик. Используйте страницу новых заявок.');
        }
        if ((int) $order['mechanic_id'] === $newMechanicId) {
            return $this->fail('Этот механик уже назначен на заявку');
        }
        if (!$this->users()->isActiveMechanic($newMechanicId)) {
            return $this->fail('Механик не найден или недоступен');
        }

        $affected = $this->orders()->reassignMechanic($orderId, $newMechanicId);
        if ($affected < 1) return $this->fail('Не удалось переназначить механика');

        $this->assignments()->create($orderId, $newMechanicId, $masterId, trim($comment));

        return $this->ok(['message' => 'Механик переназначен.']);
    }

    public function cancelOrder(int $orderId, int $masterId, string $comment): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $order = $this->orders()->findById($orderId);
        if (!$order) return $this->fail('Заявка не найдена', 404);
        if ((int) $order['master_id'] !== $masterId) return $this->fail('Эта заявка не закреплена за вами');
        if ($order['status'] !== Order::STATUS_NEW) return $this->fail('Отменить можно только новую заявку, которой ещё не назначен механик');

        $comment = trim($comment);
        if ($comment === '') return $this->fail('Укажите причину отмены — клиент её увидит');

        $affected = $this->orders()->cancelByMaster($orderId, $masterId, $comment);
        if ($affected < 1) return $this->fail('Не удалось отменить заявку');

        $this->history()->log($orderId, Order::STATUS_NEW, Order::STATUS_CANCELLED, $masterId, $comment);

        return $this->ok(['message' => 'Заявка отменена.']);
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

        if ($order['status'] !== Order::STATUS_WAITING_PARTS) {
            $this->orders()->setWaitingParts($orderId);
            $this->history()->log($orderId, $order['status'], Order::STATUS_WAITING_PARTS, $masterId);
        } else {
            $this->orders()->setWaitingParts($orderId);
        }

        return $this->ok(['request_id' => $requestId, 'message' => 'Запрос на закупку создан']);
    }

    public function getMyPurchaseRequests(int $masterId): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['requests' => $this->purchases()->getAllByMaster($masterId)]);
    }

    public function getPendingPurchaseRequests(): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['requests' => $this->purchases()->getPending()]);
    }

    public function cancelPurchaseRequest(int $requestId, int $masterId): array
    {
        $check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $orderId = $this->purchases()->deleteIfPendingByMaster($requestId, $masterId);
        if ($orderId === null) {
            return $this->fail('Запрос не найден, не принадлежит вам или уже обработан администратором');
        }

        if (!$this->purchases()->hasPendingForOrder($orderId)) {
            $this->orders()->updateStatus($orderId, Order::STATUS_IN_PROGRESS);
            $this->history()->log($orderId, Order::STATUS_WAITING_PARTS, Order::STATUS_IN_PROGRESS, $masterId);
        }

        return $this->ok(['message' => 'Запрос на закупку отменён. Заявка возвращена в работу.']);
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
