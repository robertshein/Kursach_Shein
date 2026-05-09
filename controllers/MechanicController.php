<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';

class MechanicController extends BaseController {
    public function getMyOrders($mechanic_id) {
        $role_check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "
            SELECT o.id, o.status, o.description, o.start_date, o.end_date, o.total_price,
                   c.brand, c.model, c.gosnumber,
                   u.full_name AS client_name
            FROM orders o
            JOIN cars c ON c.id = o.car_id
            JOIN users u ON u.id = o.client_id
            WHERE o.mechanic_id = ?
            ORDER BY o.id DESC
        ";

        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $mechanic_id);
        mysqli_stmt_execute($stmt);
        $orders = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['orders' => $orders]);
    }

    public function updateOrderStatus($order_id, $new_status) {
        $role_check = $this->requireRole([User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        if (!Order::isValidStatus($new_status)) {
            return $this->fail('Недопустимый статус заявки');
        }

        $sql = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $order_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось обновить статус', 500);
        }

        return $this->ok(['message' => 'Статус заявки обновлен']);
    }
}
?>
