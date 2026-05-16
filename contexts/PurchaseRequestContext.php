<?php
require_once __DIR__ . '/AppContext.php';

class PurchaseRequestContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM part_purchase_requests WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    /* ── Создание ── */

    public function create(int $orderId, int $partId, int $quantity, int $masterId, string $comment = ''): int
    {
        return $this->insert(
            "INSERT INTO part_purchase_requests
                (order_id, part_id, quantity, requested_by_master_id, status, comment)
             VALUES (?, ?, ?, ?, 'pending', ?)",
            'iiiis', [$orderId, $partId, $quantity, $masterId, $comment]
        );
    }

    public function createByMechanic(int $orderId, int $partId, int $quantity, int $mechanicId, string $comment = ''): int
    {
        return $this->insert(
            "INSERT INTO part_purchase_requests
                (order_id, part_id, quantity, requested_by_mechanic_id, status, comment)
             VALUES (?, ?, ?, ?, 'pending', ?)",
            'iiiis', [$orderId, $partId, $quantity, $mechanicId, $comment]
        );
    }

    /* ── Решение (администратор) ── */

    public function decide(int $id, int $adminId, string $status, ?string $comment): bool
    {
        return $this->execute(
            "UPDATE part_purchase_requests
             SET approved_by_admin_id = ?, status = ?, comment = ?, resolved_at = NOW()
             WHERE id = ?",
            'issi', [$adminId, $status, $comment, $id]
        );
    }

    /* ── Выборки ── */

    /** Все запросы (для администратора) */
    public function getAll(): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at, ppr.resolved_at,
                    p.name  AS part_name, p.article, p.price AS part_price,
                    COALESCE(u_m.full_name, u_mech.full_name) AS requester_name,
                    CASE WHEN ppr.requested_by_master_id IS NOT NULL THEN 'master' ELSE 'mechanic' END
                        AS requester_role,
                    u_a.full_name AS approved_by_admin
             FROM part_purchase_requests ppr
             JOIN   parts  p      ON p.id    = ppr.part_id
             LEFT JOIN users u_m    ON u_m.id    = ppr.requested_by_master_id
             LEFT JOIN users u_mech ON u_mech.id = ppr.requested_by_mechanic_id
             LEFT JOIN users u_a    ON u_a.id    = ppr.approved_by_admin_id
             ORDER BY ppr.created_at DESC"
        );
    }

    /** Только ожидающие (для мастера — все pending независимо от автора) */
    public function getPending(): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at,
                    ppr.requested_by_master_id,
                    ppr.requested_by_mechanic_id,
                    p.name  AS part_name, p.article, p.price AS part_price,
                    COALESCE(u_m.full_name, u_mech.full_name) AS requester_name,
                    CASE WHEN ppr.requested_by_master_id IS NOT NULL THEN 'master' ELSE 'mechanic' END
                        AS requester_role
             FROM part_purchase_requests ppr
             JOIN   parts  p      ON p.id    = ppr.part_id
             LEFT JOIN users u_m    ON u_m.id    = ppr.requested_by_master_id
             LEFT JOIN users u_mech ON u_mech.id = ppr.requested_by_mechanic_id
             WHERE ppr.status = 'pending'
             ORDER BY ppr.created_at DESC"
        );
    }

    /** Все запросы конкретного мастера */
    public function getAllByMaster(int $masterId): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at, ppr.resolved_at,
                    p.name AS part_name, p.article, p.price AS part_price,
                    u_a.full_name AS approved_by_admin
             FROM part_purchase_requests ppr
             JOIN   parts  p   ON p.id    = ppr.part_id
             LEFT JOIN users u_a ON u_a.id = ppr.approved_by_admin_id
             WHERE ppr.requested_by_master_id = ?
             ORDER BY ppr.created_at DESC",
            'i', [$masterId]
        );
    }

    /** Все запросы конкретного механика */
    public function getAllByMechanic(int $mechanicId): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at, ppr.resolved_at,
                    p.name AS part_name, p.article, p.price AS part_price,
                    u_a.full_name AS approved_by_admin
             FROM part_purchase_requests ppr
             JOIN   parts  p   ON p.id    = ppr.part_id
             LEFT JOIN users u_a ON u_a.id = ppr.approved_by_admin_id
             WHERE ppr.requested_by_mechanic_id = ?
             ORDER BY ppr.created_at DESC",
            'i', [$mechanicId]
        );
    }

    /* ── Отмена ── */

    public function deleteIfPendingByMaster(int $requestId, int $masterId): ?int
    {
        $row = $this->fetchOne(
            "SELECT id, order_id FROM part_purchase_requests
             WHERE id = ? AND requested_by_master_id = ? AND status = 'pending' LIMIT 1",
            'ii', [$requestId, $masterId]
        );
        if (!$row) return null;
        $this->execute("DELETE FROM part_purchase_requests WHERE id = ?", 'i', [$requestId]);
        return (int) $row['order_id'];
    }

    public function deleteIfPendingByMechanic(int $requestId, int $mechanicId): bool
    {
        $row = $this->fetchOne(
            "SELECT id FROM part_purchase_requests
             WHERE id = ? AND requested_by_mechanic_id = ? AND status = 'pending' LIMIT 1",
            'ii', [$requestId, $mechanicId]
        );
        if (!$row) return false;
        return $this->execute("DELETE FROM part_purchase_requests WHERE id = ?", 'i', [$requestId]);
    }

    /* ── Вспомогательные ── */

    public function hasPendingForOrder(int $orderId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM part_purchase_requests WHERE order_id = ? AND status = 'pending' LIMIT 1",
            'i', [$orderId]
        );
    }

    public function countPending(): int
    {
        $result = mysqli_query($this->db, "SELECT COUNT(*) AS cnt FROM part_purchase_requests WHERE status = 'pending'");
        return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
    }
}
