<?php
require_once __DIR__ . '/AppContext.php';

class PurchaseRequestContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM part_purchase_requests WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function create(int $orderId, int $partId, int $quantity, int $masterId, string $comment = ''): int
    {
        return $this->insert(
            "INSERT INTO part_purchase_requests (order_id, part_id, quantity, requested_by_master_id, status, comment) VALUES (?, ?, ?, ?, 'pending', ?)",
            'iiiis', [$orderId, $partId, $quantity, $masterId, $comment]
        );
    }

    public function decide(int $id, int $adminId, string $status, ?string $comment): bool
    {
        return $this->execute(
            "UPDATE part_purchase_requests SET approved_by_admin_id = ?, status = ?, comment = ?, resolved_at = NOW() WHERE id = ?",
            'issi', [$adminId, $status, $comment, $id]
        );
    }

    public function getAll(): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at, ppr.resolved_at,
                    p.name AS part_name, p.article, p.price AS part_price,
                    u_m.full_name  AS requested_by_master,
                    u_a.full_name  AS approved_by_admin
             FROM part_purchase_requests ppr
             JOIN parts p        ON p.id  = ppr.part_id
             JOIN users u_m      ON u_m.id = ppr.requested_by_master_id
             LEFT JOIN users u_a ON u_a.id = ppr.approved_by_admin_id
             ORDER BY ppr.created_at DESC"
        );
    }

    public function getPending(): array
    {
        return $this->fetchAll(
            "SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status,
                    ppr.comment, ppr.created_at,
                    p.name AS part_name, p.article, p.price AS part_price,
                    u.full_name AS requested_by_master
             FROM part_purchase_requests ppr
             JOIN parts p ON p.id = ppr.part_id
             JOIN users u ON u.id = ppr.requested_by_master_id
             WHERE ppr.status = 'pending'
             ORDER BY ppr.created_at DESC"
        );
    }

    public function countPending(): int
    {
        $result = mysqli_query($this->db, "SELECT COUNT(*) AS cnt FROM part_purchase_requests WHERE status = 'pending'");
        return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
    }
}
