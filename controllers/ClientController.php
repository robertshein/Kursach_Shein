<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Order.php';

class ClientController extends BaseController {
    public function carHasActiveOrder($car_id) {
        $car_id = (int) $car_id;
        if ($car_id <= 0) {
            return false;
        }
        $sql = "
            SELECT id FROM orders
            WHERE car_id = ?
              AND status NOT IN (?, ?)
            LIMIT 1
        ";
        $stmt = mysqli_prepare($this->db, $sql);
        $done = Order::STATUS_COMPLETED;
        $cancelled = Order::STATUS_CANCELLED;
        mysqli_stmt_bind_param($stmt, 'iss', $car_id, $done, $cancelled);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        return (bool) $row;
    }

    public function createOrder($client_id, $car_id, $description, array $services = []) {
        $role_check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        if ($this->carHasActiveOrder($car_id)) {
            return $this->fail('Этот автомобиль уже указан в активной заявке. Дождитесь её завершения или отмены.');
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
            SELECT o.id, o.car_id, o.status, o.total_price, o.start_date, o.end_date, o.description,
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

    public function getClientCars($client_id) {
        $role_check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN, User::ROLE_MASTER]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "
            SELECT id, vin, brand, model, year, gosnumber
            FROM cars
            WHERE user_id = ?
            ORDER BY id DESC
        ";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $client_id);
        mysqli_stmt_execute($stmt);
        $cars = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        return $this->ok(['cars' => $cars]);
    }

    public function addClientCar($client_id, $vin, $brand, $model, $year, $gosnumber) {
        $role_check = $this->requireRole([User::ROLE_CLIENT, User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $check_sql = "SELECT id FROM cars WHERE vin = ? LIMIT 1";
        $check_stmt = mysqli_prepare($this->db, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 's', $vin);
        mysqli_stmt_execute($check_stmt);
        $exists = mysqli_stmt_get_result($check_stmt)->fetch_assoc();
        mysqli_stmt_close($check_stmt);

        if ($exists) {
            return $this->fail('Автомобиль с таким VIN уже зарегистрирован');
        }

        $insert_sql = "INSERT INTO cars (user_id, vin, brand, model, year, gosnumber) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($this->db, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, 'isssis', $client_id, $vin, $brand, $model, $year, $gosnumber);
        $ok = mysqli_stmt_execute($insert_stmt);
        $car_id = mysqli_insert_id($this->db);
        mysqli_stmt_close($insert_stmt);

        if (!$ok) {
            return $this->fail('Не удалось добавить автомобиль', 500);
        }

        return $this->ok(['car_id' => $car_id, 'message' => 'Автомобиль добавлен']);
    }

    public function updateClientOrderDescription($client_id, $order_id, $description) {
        $role_check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $description = trim((string) $description);
        if ($description === '') {
            return $this->fail('Укажите описание заявки.');
        }

        $status_new = Order::STATUS_NEW;
        $sql = "UPDATE orders SET description = ? WHERE id = ? AND client_id = ? AND status = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'siis', $description, $order_id, $client_id, $status_new);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected < 1) {
            return $this->fail('Изменить описание можно только у новой заявки, которую мастер ещё не принял.');
        }

        return $this->ok(['message' => 'Описание заявки обновлено.']);
    }

    public function updateClientCar($client_id, $car_id, $vin, $brand, $model, $year, $gosnumber) {
        $role_check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        if (!$this->carBelongsToClient($car_id, $client_id)) {
            return $this->fail('Автомобиль не найден.');
        }

        if ($this->carHasActiveOrder($car_id)) {
            return $this->fail('Нельзя редактировать автомобиль, пока он участвует в активной заявке.');
        }

        $check_sql = "SELECT id FROM cars WHERE vin = ? AND id != ? LIMIT 1";
        $check_stmt = mysqli_prepare($this->db, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 'si', $vin, $car_id);
        mysqli_stmt_execute($check_stmt);
        $exists = mysqli_stmt_get_result($check_stmt)->fetch_assoc();
        mysqli_stmt_close($check_stmt);

        if ($exists) {
            return $this->fail('Автомобиль с таким VIN уже зарегистрирован.');
        }

        $upd_sql = "UPDATE cars SET vin = ?, brand = ?, model = ?, year = ?, gosnumber = ? WHERE id = ? AND user_id = ?";
        $upd_stmt = mysqli_prepare($this->db, $upd_sql);
        mysqli_stmt_bind_param($upd_stmt, 'sssisi', $vin, $brand, $model, $year, $gosnumber, $car_id, $client_id);
        $ok = mysqli_stmt_execute($upd_stmt);
        mysqli_stmt_close($upd_stmt);

        if (!$ok) {
            return $this->fail('Не удалось сохранить данные автомобиля', 500);
        }

        return $this->ok(['message' => 'Данные автомобиля сохранены.']);
    }

    public function carBelongsToClient($car_id, $client_id) {
        $sql = "SELECT id FROM cars WHERE id = ? AND user_id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $car_id, $client_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        return (bool) $row;
    }

    public function updateClientProfile($client_id, $full_name, $phone, $email, $new_password = '') {
        $role_check = $this->requireRole([User::ROLE_CLIENT]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $dup_sql = "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1";
        $dup_stmt = mysqli_prepare($this->db, $dup_sql);
        mysqli_stmt_bind_param($dup_stmt, 'si', $email, $client_id);
        mysqli_stmt_execute($dup_stmt);
        $dup = mysqli_stmt_get_result($dup_stmt)->fetch_assoc();
        mysqli_stmt_close($dup_stmt);

        if ($dup) {
            return $this->fail('Пользователь с таким email уже существует');
        }

        $new_password = is_string($new_password) ? $new_password : '';
        $new_password_trimmed = trim($new_password);

        if ($new_password_trimmed !== '') {
            if (strlen($new_password_trimmed) < 6) {
                return $this->fail('Новый пароль должен быть не короче 6 символов');
            }

            $hash = password_hash($new_password_trimmed, PASSWORD_BCRYPT);
            $sql = "UPDATE users SET full_name = ?, phone = ?, email = ?, password = ? WHERE id = ? AND role = ?";
            $stmt = mysqli_prepare($this->db, $sql);
            $role = User::ROLE_CLIENT;
            mysqli_stmt_bind_param($stmt, 'ssssis', $full_name, $phone, $email, $hash, $client_id, $role);
        } else {
            $sql = "UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ? AND role = ?";
            $stmt = mysqli_prepare($this->db, $sql);
            $role = User::ROLE_CLIENT;
            mysqli_stmt_bind_param($stmt, 'sssis', $full_name, $phone, $email, $client_id, $role);
        }

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось сохранить данные профиля', 500);
        }

        return $this->ok([
            'message' => 'Данные сохранены',
            'full_name' => $full_name,
            'phone' => $phone,
            'email' => $email
        ]);
    }
}
?>
