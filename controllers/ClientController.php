<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';

class ClientController extends BaseController {
    public function createOrder($client_id, $car_id, $description, array $services = []) {
        $role_check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $status = Order::STATUS_NEW;
        $insert_order_sql = "INSERT INTO orders (client_id, car_id, status, description, total_price) VALUES (?, ?, ?, ?, 0)";
        $stmt = mysqli_prepare($this->db, $insert_order_sql);
        mysqli_stmt_bind_param($stmt, 'iiss', $client_id, $car_id, $status, $description);
        $ok = mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось создать заявку', 500);
        }

        if (!empty($services)) {
            foreach ($services as $service) {
                $service_id = (int)($service['service_id'] ?? 0);
                $quantity = max(1, (int)($service['quantity'] ?? 1));
                $comment = $service['comment'] ?? null;
                $os_sql = "INSERT INTO order_services (order_id, service_id, quantity, comment) VALUES (?, ?, ?, ?)";
                $os_stmt = mysqli_prepare($this->db, $os_sql);
                mysqli_stmt_bind_param($os_stmt, 'iiis', $order_id, $service_id, $quantity, $comment);
                mysqli_stmt_execute($os_stmt);
                mysqli_stmt_close($os_stmt);
            }
        }

        return $this->ok(['order_id' => $order_id, 'message' => 'Заявка создана']);
    }

    public function getClientOrders($client_id) {
        $role_check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN, User::ROLE_MASTER]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "
            SELECT o.id, o.status, o.total_price, o.start_date, o.end_date, o.description,
                   c.brand, c.model, c.gosnumber,
                   m.full_name AS mechanic_name,
                   ms.full_name AS master_name
            FROM orders o
            JOIN cars c ON c.id = o.car_id
            LEFT JOIN users m ON m.id = o.mechanic_id
            LEFT JOIN users ms ON ms.id = o.master_id
            WHERE o.client_id = ?
            ORDER BY o.id DESC
        ";

        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $client_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['orders' => $orders]);
    }
}
?>
