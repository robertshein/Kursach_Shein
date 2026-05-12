<?php
require_once __DIR__ . '/AppContext.php';

class OrderContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT o.*,
                    u_cl.full_name  AS client_name, u_cl.phone AS client_phone,
                    u_m.full_name   AS mechanic_name,
                    u_ms.full_name  AS master_name,
                    c.brand, c.model, c.gosnumber, c.year
             FROM orders o
             JOIN users u_cl  ON u_cl.id  = o.client_id
             JOIN cars c       ON c.id    = o.car_id
             LEFT JOIN users u_m  ON u_m.id  = o.mechanic_id
             LEFT JOIN users u_ms ON u_ms.id = o.master_id
             WHERE o.id = ? LIMIT 1",
            'i', [$id]
        );
    }

    public function carHasActiveOrder(int $carId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM orders WHERE car_id = ? AND status NOT IN ('completed','cancelled') LIMIT 1",
            'i', [$carId]
        );
    }

    public function serviceExists(int $serviceId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM services WHERE id = ? LIMIT 1",
            'i', [$serviceId]
        );
    }

    public function create(int $clientId, int $carId, string $description, ?int $masterId): int
    {
        return $this->insert(
            "INSERT INTO orders (client_id, car_id, status, description, total_price, master_id) VALUES (?, ?, 'new', ?, 0, ?)",
            'iisi', [$clientId, $carId, $description, $masterId]
        );
    }

    public function addService(int $orderId, int $serviceId, int $quantity, string $comment = ''): bool
    {
        return $this->execute(
            "INSERT INTO order_services (order_id, service_id, quantity, comment) VALUES (?, ?, ?, ?)",
            'iiis', [$orderId, $serviceId, $quantity, $comment]
        );
    }

    public function upsertService(int $orderId, int $serviceId, int $addQty, string $comment = ''): bool
    {
        $existing = $this->fetchOne(
            "SELECT id, quantity FROM order_services WHERE order_id = ? AND service_id = ? LIMIT 1",
            'ii', [$orderId, $serviceId]
        );
        if ($existing) {
            $newQty = (int) $existing['quantity'] + $addQty;
            return $this->execute(
                "UPDATE order_services SET quantity = ?, comment = ? WHERE id = ?",
                'isi', [$newQty, $comment, $existing['id']]
            );
        }
        return $this->addService($orderId, $serviceId, $addQty, $comment);
    }

    public function recalculateTotal(int $orderId): bool
    {
        return $this->execute(
            "UPDATE orders o
             SET o.total_price = (
                 SELECT COALESCE(SUM(os.quantity * s.price), 0)
                 FROM order_services os
                 JOIN services s ON s.id = os.service_id
                 WHERE os.order_id = o.id
             )
             WHERE o.id = ?",
            'i', [$orderId]
        );
    }

    public function assignMechanicToOrder(int $orderId, int $mechanicId, int $masterId): int
    {
        return $this->affectedExecute(
            "UPDATE orders SET mechanic_id = ?, master_id = ?, status = 'assigned' WHERE id = ? AND status = 'new'",
            'iii', [$mechanicId, $masterId, $orderId]
        );
    }

    public function updateStatus(int $orderId, string $newStatus, ?int $mechanicId = null): int
    {
        $extraSql = '';
        if ($newStatus === 'in_progress') {
            $extraSql = ', start_date = COALESCE(start_date, NOW())';
        } elseif ($newStatus === 'completed') {
            $extraSql = ', end_date = NOW()';
        }
        if ($mechanicId !== null) {
            return $this->affectedExecute(
                "UPDATE orders SET status = ?{$extraSql} WHERE id = ? AND mechanic_id = ?",
                'sii', [$newStatus, $orderId, $mechanicId]
            );
        }
        return $this->affectedExecute(
            "UPDATE orders SET status = ?{$extraSql} WHERE id = ?",
            'si', [$newStatus, $orderId]
        );
    }

    public function setWaitingParts(int $orderId): void
    {
        $this->execute(
            "UPDATE orders SET status = 'waiting_parts' WHERE id = ?",
            'i', [$orderId]
        );
    }

    public function getNewOrdersForMaster(int $masterId): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.description, o.status, o.total_price, o.created_at,
                    u.full_name AS client_name, u.phone AS client_phone, u.email AS client_email,
                    c.brand, c.model, c.gosnumber, c.year
             FROM orders o
             JOIN users u ON u.id = o.client_id
             JOIN cars c  ON c.id  = o.car_id
             WHERE o.status = 'new' AND o.master_id = ?
             ORDER BY o.id ASC",
            'i', [$masterId]
        );
    }

    public function getAllOrders(): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.status, o.description, o.total_price,
                    o.start_date, o.end_date, o.created_at,
                    u_cl.full_name AS client_name,
                    c.brand, c.model, c.gosnumber, c.year,
                    u_m.full_name  AS mechanic_name,
                    u_ms.full_name AS master_name,
                    (
                        SELECT GROUP_CONCAT(CONCAT(s.name,' \xc3\x97 ',os.quantity) ORDER BY s.name SEPARATOR ' \xc2\xb7 ')
                        FROM order_services os JOIN services s ON s.id = os.service_id
                        WHERE os.order_id = o.id
                    ) AS services_summary
             FROM orders o
             JOIN users u_cl ON u_cl.id = o.client_id
             JOIN cars c      ON c.id   = o.car_id
             LEFT JOIN users u_m  ON u_m.id  = o.mechanic_id
             LEFT JOIN users u_ms ON u_ms.id = o.master_id
             ORDER BY o.id DESC"
        );
    }

    public function getOrdersByMechanic(int $mechanicId): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.status, o.description, o.start_date, o.end_date,
                    o.total_price, o.created_at,
                    c.brand, c.model, c.gosnumber,
                    u.full_name AS client_name,
                    (SELECT ma.comment FROM mechanic_assignments ma
                     WHERE ma.order_id = o.id AND ma.mechanic_id = o.mechanic_id
                     ORDER BY ma.id DESC LIMIT 1) AS master_comment,
                    (SELECT GROUP_CONCAT(CONCAT(s.name,' \xc3\x97 ',os.quantity) ORDER BY s.name SEPARATOR ' \xc2\xb7 ')
                     FROM order_services os JOIN services s ON s.id = os.service_id
                     WHERE os.order_id = o.id) AS services_summary
             FROM orders o
             JOIN cars c ON c.id = o.car_id
             JOIN users u ON u.id = o.client_id
             WHERE o.mechanic_id = ?
             ORDER BY FIELD(o.status,'in_progress','waiting_parts','assigned','new','completed','cancelled'), o.id DESC",
            'i', [$mechanicId]
        );
    }

    public function getOrdersByClient(int $clientId): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.car_id, o.status, o.total_price,
                    o.start_date, o.end_date, o.description, o.created_at,
                    c.brand, c.model, c.gosnumber,
                    u_m.full_name  AS mechanic_name,
                    u_ms.full_name AS master_name
             FROM orders o
             JOIN cars c ON c.id = o.car_id
             LEFT JOIN users u_m  ON u_m.id  = o.mechanic_id
             LEFT JOIN users u_ms ON u_ms.id = o.master_id
             WHERE o.client_id = ?
             ORDER BY o.id DESC",
            'i', [$clientId]
        );
    }

    public function getOrdersAvailableForPurchase(): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.status, o.description,
                    u.full_name AS client_name, c.brand, c.model, c.gosnumber
             FROM orders o
             JOIN users u ON u.id = o.client_id
             JOIN cars c  ON c.id = o.car_id
             WHERE o.status IN ('assigned','in_progress','waiting_parts')
             ORDER BY o.id DESC"
        );
    }

    public function getStats(): array
    {
        $result = mysqli_query($this->db,
            "SELECT COUNT(*) AS total,
                    SUM(status='new')           AS cnt_new,
                    SUM(status='assigned')      AS cnt_assigned,
                    SUM(status='in_progress')   AS cnt_in_progress,
                    SUM(status='waiting_parts') AS cnt_waiting_parts,
                    SUM(status='completed')     AS cnt_completed,
                    SUM(status='cancelled')     AS cnt_cancelled,
                    COALESCE(SUM(CASE WHEN status='completed' THEN total_price END),0) AS revenue
             FROM orders"
        );
        return $result ? ($result->fetch_assoc() ?? []) : [];
    }

    public function updateDescription(int $orderId, int $clientId, string $description): int
    {
        return $this->affectedExecute(
            "UPDATE orders SET description = ? WHERE id = ? AND client_id = ? AND status = 'new'",
            'sii', [$description, $orderId, $clientId]
        );
    }
}
