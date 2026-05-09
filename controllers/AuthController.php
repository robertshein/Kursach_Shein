<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends BaseController {
    public function register($full_name, $phone, $email, $password, $role = User::ROLE_CLIENT) {
        if (!User::isValidRole($role)) {
            return $this->fail('Некорректная роль');
        }

        if ($role !== User::ROLE_CLIENT) {
            return $this->fail('Саморегистрация доступна только для клиентов');
        }

        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($this->db, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 's', $email);
        mysqli_stmt_execute($check_stmt);
        $exists = mysqli_stmt_get_result($check_stmt)->fetch_assoc();
        mysqli_stmt_close($check_stmt);

        if ($exists) {
            return $this->fail('Пользователь с таким email уже существует');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert_sql = "INSERT INTO users (full_name, phone, email, role, password, is_active) VALUES (?, ?, ?, ?, ?, 1)";
        $insert_stmt = mysqli_prepare($this->db, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, 'sssss', $full_name, $phone, $email, $role, $hash);
        $ok = mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);

        if (!$ok) {
            return $this->fail('Не удалось зарегистрировать пользователя', 500);
        }

        return $this->ok(['message' => 'Регистрация выполнена']);
    }

    public function login($email, $password) {
        $sql = "SELECT id, full_name, phone, email, role, password, salary, is_active FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->fail('Неверный email или пароль', 401);
        }

        if ((int)$user['is_active'] !== 1) {
            return $this->fail('Пользователь деактивирован', 403);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($user['password']);
        $_SESSION['user'] = $user;

        return $this->ok(['user' => $user]);
    }

    public function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_destroy();
        return $this->ok(['message' => 'Выход выполнен']);
    }
}
?>
