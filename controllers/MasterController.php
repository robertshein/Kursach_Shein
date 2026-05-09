<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/MechanicAssignment.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';

class MasterController extends BaseController {
    public function assignMechanic($order_id, $mechanic_id, $master_id, $comment = null) {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $order_status = Order::STATUS_ASSIGNED;
        $order_sql = "UPDATE orders SET mechanic_id = ?, master_id = ?, status = ? WHERE id = ?";
        $order_stmt = mysqli_prepare($this->db, $order_sql);
        mysqli_stmt_bind_param($order_stmt, 'iisi', $mechanic_id, $master_id, $order_status, $order_id);
        $ok_order = mysqli_stmt_execute($order_stmt);
        mysqli_stmt_close($order_stmt);

        if (!$ok_order) {
            return $this->fail('Не удалось назначить механика', 500);
        }

        $assignment_status = MechanicAssignment::STATUS_ASSIGNED;
        $ma_sql = "INSERT INTO mechanic_assignments (order_id, mechanic_id, assigned_by_master_id, status, comment) VALUES (?, ?, ?, ?, ?)";
        $ma_stmt = mysqli_prepare($this->db, $ma_sql);
        mysqli_stmt_bind_param($ma_stmt, 'iiiss', $order_id, $mechanic_id, $master_id, $assignment_status, $comment);
        mysqli_stmt_execute($ma_stmt);
        mysqli_stmt_close($ma_stmt);

        return $this->ok(['message' => 'Механик назначен']);
    }

    public function createPartPurchaseRequest($order_id, $part_id, $quantity, $master_id, $comment = null) {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $status = PartPurchaseRequest::STATUS_PENDING;
        $sql = "INSERT INTO part_purchase_requests (order_id, part_id, quantity, requested_by_master_id, status, comment) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiiss', $order_id, $part_id, $quantity, $master_id, $status, $comment);
        $ok = mysqli_stmt_execute($stmt);
        $request_id = mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось создать запрос на закупку', 500);
        }

        $waiting_status = Order::STATUS_WAITING_PARTS;
        $update_order_sql = "UPDATE orders SET status = ? WHERE id = ?";
        $uo_stmt = mysqli_prepare($this->db, $update_order_sql);
        mysqli_stmt_bind_param($uo_stmt, 'si', $waiting_status, $order_id);
        mysqli_stmt_execute($uo_stmt);
        mysqli_stmt_close($uo_stmt);

        return $this->ok(['request_id' => $request_id, 'message' => 'Запрос на закупку создан']);
    }

    public function getPendingPurchaseRequests() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $pending_status = PartPurchaseRequest::STATUS_PENDING;
        $sql = "
            SELECT ppr.id, ppr.order_id, ppr.quantity, ppr.status, ppr.comment, ppr.created_at,
                   p.name AS part_name, p.article,
                   u.full_name AS requested_by_master
            FROM part_purchase_requests ppr
            JOIN parts p ON p.id = ppr.part_id
            JOIN users u ON u.id = ppr.requested_by_master_id
            WHERE ppr.status = ?
            ORDER BY ppr.created_at DESC
        ";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 's', $pending_status);
        mysqli_stmt_execute($stmt);
        $requests = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['requests' => $requests]);
    }
}
?>
