<?php
require_once __DIR__ . '/AppContext.php';

class MechanicAssignmentContext extends AppContext
{
    public function create(int $orderId, int $mechanicId, int $masterId, string $comment = ''): int
    {
        return $this->insert(
            "INSERT INTO mechanic_assignments (order_id, mechanic_id, assigned_by_master_id, status, comment) VALUES (?, ?, ?, 'assigned', ?)",
            'iiis', [$orderId, $mechanicId, $masterId, $comment]
        );
    }
}
