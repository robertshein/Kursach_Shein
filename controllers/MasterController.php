<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/MechanicAssignment.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';

class MasterController extends BaseController {
    protected function assertActiveMechanic($mechanic_id) {
        $mechanic_id = (int) $mechanic_id;
        if ($mechanic_id <= 0) {
            return [false, 'Укажите механика'];
        }
        $role = User::ROLE_MECHANIC;
        $sql = "SELECT id FROM users WHERE id = ? AND role = ? AND is_active = 1 LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $mechanic_id, $role);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$row) {
            return [false, 'Механик не найден или недоступен'];
        }
        return [true, null];
    }

    public function getNewOrders() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $st = Order::STATUS_NEW;
        $sql = "
            SELECT o.id, o.description, o.status, o.total_price,
                   u.full_name AS client_name, u.phone AS client_phone, u.email AS client_email,
                   c.brand, c.model, c.gosnumber, c.year
            FROM orders o
            JOIN users u ON u.id = o.client_id
            JOIN cars c ON c.id = o.car_id
            WHERE o.status = ?
            ORDER BY o.id ASC
        ";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 's', $st);
        mysqli_stmt_execute($stmt);
        $orders = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['orders' => $orders]);
    }

    public function getAllOrders() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "
            SELECT o.id, o.status, o.description, o.total_price,
                   u.full_name AS client_name,
                   c.brand, c.model, c.gosnumber,
                   m.full_name AS mechanic_name,
                   ms.full_name AS master_name,
                   (
                       SELECT GROUP_CONCAT(CONCAT(s.name, ' × ', os.quantity) ORDER BY s.name SEPARATOR ' · ')
                       FROM order_services os
                       JOIN services s ON s.id = os.service_id
                       WHERE os.order_id = o.id
                   ) AS services_summary
            FROM orders o
            JOIN users u ON u.id = o.client_id
            JOIN cars c ON c.id = o.car_id
            LEFT JOIN users m ON m.id = o.mechanic_id
            LEFT JOIN users ms ON ms.id = o.master_id
            ORDER BY o.id DESC
        ";
        $result = mysqli_query($this->db, $sql);
        $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return $this->ok(['orders' => $orders]);
    }

    public function getMechanics() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $role = User::ROLE_MECHANIC;
        $sql = "SELECT id, full_name, email, phone FROM users WHERE role = ? AND is_active = 1 ORDER BY full_name";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 's', $role);
        mysqli_stmt_execute($stmt);
        $mechanics = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['mechanics' => $mechanics]);
    }

    public function getParts() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "SELECT id, name, article, quantity FROM parts ORDER BY name ASC LIMIT 500";
        $result = mysqli_query($this->db, $sql);
        $parts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return $this->ok(['parts' => $parts]);
    }

    public function getOrdersForPartRequest() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $allowed = [
            Order::STATUS_ASSIGNED,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_WAITING_PARTS,
        ];
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $types = str_repeat('s', count($allowed));
        $sql = "
            SELECT o.id, o.status, o.description,
                   u.full_name AS client_name,
                   c.brand, c.model, c.gosnumber
            FROM orders o
            JOIN users u ON u.id = o.client_id
            JOIN cars c ON c.id = o.car_id
            WHERE o.status IN ($placeholders)
            ORDER BY o.id DESC
        ";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$allowed);
        mysqli_stmt_execute($stmt);
        $orders = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['orders' => $orders]);
    }

    public function getServices() {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "SELECT id, name, price FROM services ORDER BY name ASC LIMIT 500";
        $result = mysqli_query($this->db, $sql);
        $services = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return $this->ok(['services' => $services]);
    }

    protected function normalizeOrderServicesPayload(array $order_services) {
        $normalized = [];
        foreach ($order_services as $row) {
            $service_id = (int) ($row['service_id'] ?? 0);
            if ($service_id <= 0) {
                continue;
            }
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $normalized[] = ['service_id' => $service_id, 'quantity' => $quantity];
        }
        return $normalized;
    }

    protected function assertServicesExist(array $normalized_services) {
        if (empty($normalized_services)) {
            return [true, null];
        }
        $chk_sql = "SELECT id FROM services WHERE id = ? LIMIT 1";
        foreach ($normalized_services as $svc) {
            $sid = (int) $svc['service_id'];
            $chk_stmt = mysqli_prepare($this->db, $chk_sql);
            mysqli_stmt_bind_param($chk_stmt, 'i', $sid);
            mysqli_stmt_execute($chk_stmt);
            $row = mysqli_stmt_get_result($chk_stmt)->fetch_assoc();
            mysqli_stmt_close($chk_stmt);
            if (!$row) {
                return [false, 'Одна из выбранных услуг не найдена в справочнике'];
            }
        }
        return [true, null];
    }

    public function assignMechanic($order_id, $mechanic_id, $master_id, $comment = null, array $order_services = []) {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $order_id = (int) $order_id;
        $mechanic_id = (int) $mechanic_id;
        $master_id = (int) $master_id;

        $mech_ok = $this->assertActiveMechanic($mechanic_id);
        if (!$mech_ok[0]) {
            return $this->fail($mech_ok[1]);
        }

        $normalized_services = $this->normalizeOrderServicesPayload($order_services);
        $svc_ok = $this->assertServicesExist($normalized_services);
        if (!$svc_ok[0]) {
            return $this->fail($svc_ok[1]);
        }

        mysqli_begin_transaction($this->db);

        $order_status = Order::STATUS_ASSIGNED;
        $prev_status = Order::STATUS_NEW;
        $order_sql = "UPDATE orders SET mechanic_id = ?, master_id = ?, status = ? WHERE id = ? AND status = ?";
        $order_stmt = mysqli_prepare($this->db, $order_sql);
        mysqli_stmt_bind_param($order_stmt, 'iisis', $mechanic_id, $master_id, $order_status, $order_id, $prev_status);
        $ok_order = mysqli_stmt_execute($order_stmt);
        $affected = mysqli_stmt_affected_rows($order_stmt);
        mysqli_stmt_close($order_stmt);

        if (!$ok_order) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось назначить механика', 500);
        }
        if ($affected < 1) {
            mysqli_rollback($this->db);
            return $this->fail('Заявка не найдена или уже принята другим мастером');
        }

        $assignment_status = MechanicAssignment::STATUS_ASSIGNED;
        $comment_str = ($comment === null || $comment === '') ? '' : (string) $comment;
        $ma_sql = "INSERT INTO mechanic_assignments (order_id, mechanic_id, assigned_by_master_id, status, comment) VALUES (?, ?, ?, ?, ?)";
        $ma_stmt = mysqli_prepare($this->db, $ma_sql);
        mysqli_stmt_bind_param($ma_stmt, 'iiiss', $order_id, $mechanic_id, $master_id, $assignment_status, $comment_str);
        $ok_ma = mysqli_stmt_execute($ma_stmt);
        mysqli_stmt_close($ma_stmt);

        if (!$ok_ma) {
            mysqli_rollback($this->db);
            return $this->fail('Не удалось сохранить назначение', 500);
        }

        foreach ($normalized_services as $svc) {
            $service_id = (int) $svc['service_id'];
            $quantity = (int) $svc['quantity'];
            $os_comment = '';
            $os_sql = "INSERT INTO order_services (order_id, service_id, quantity, comment) VALUES (?, ?, ?, ?)";
            $os_stmt = mysqli_prepare($this->db, $os_sql);
            mysqli_stmt_bind_param($os_stmt, 'iiis', $order_id, $service_id, $quantity, $os_comment);
            $ok_os = mysqli_stmt_execute($os_stmt);
            mysqli_stmt_close($os_stmt);
            if (!$ok_os) {
                mysqli_rollback($this->db);
                return $this->fail('Не удалось привязать услуги к заявке', 500);
            }
        }

        mysqli_commit($this->db);

        $done_msg = empty($normalized_services)
            ? 'Механик назначен'
            : 'Механик назначен, услуги добавлены в заявку';

        return $this->ok(['message' => $done_msg]);
    }

    public function createPartPurchaseRequest($order_id, $part_id, $quantity, $master_id, $comment = null) {
        $role_check = $this->requireRole([User::ROLE_MASTER, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $order_id = (int) $order_id;
        $part_id = (int) $part_id;
        $quantity = (int) $quantity;
        if ($order_id <= 0 || $part_id <= 0 || $quantity < 1) {
            return $this->fail('Укажите заявку, запчасть и количество');
        }

        $sql_ord = "SELECT status FROM orders WHERE id = ? LIMIT 1";
        $stmt_ord = mysqli_prepare($this->db, $sql_ord);
        mysqli_stmt_bind_param($stmt_ord, 'i', $order_id);
        mysqli_stmt_execute($stmt_ord);
        $ord_row = mysqli_stmt_get_result($stmt_ord)->fetch_assoc();
        mysqli_stmt_close($stmt_ord);
        if (!$ord_row) {
            return $this->fail('Заявка не найдена');
        }
        $allowed_order = [
            Order::STATUS_ASSIGNED,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_WAITING_PARTS,
        ];
        if (!in_array($ord_row['status'], $allowed_order, true)) {
            return $this->fail('Для этой заявки нельзя оформить запрос на закупку (нужен назначенный механик и работа в процессе).');
        }

        $sql_part = "SELECT id FROM parts WHERE id = ? LIMIT 1";
        $stmt_part = mysqli_prepare($this->db, $sql_part);
        mysqli_stmt_bind_param($stmt_part, 'i', $part_id);
        mysqli_stmt_execute($stmt_part);
        $part_row = mysqli_stmt_get_result($stmt_part)->fetch_assoc();
        mysqli_stmt_close($stmt_part);
        if (!$part_row) {
            return $this->fail('Запчасть не найдена');
        }

        $status = PartPurchaseRequest::STATUS_PENDING;
        $comment_str = ($comment === null || $comment === '') ? '' : (string) $comment;
        $sql = "INSERT INTO part_purchase_requests (order_id, part_id, quantity, requested_by_master_id, status, comment) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiiss', $order_id, $part_id, $quantity, $master_id, $status, $comment_str);
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
