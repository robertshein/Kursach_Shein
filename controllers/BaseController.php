<?php

class BaseController {
    protected $db;

    public function __construct($db) {
        $this->db = $db;
    }

    protected function requireRole(array $allowed_roles) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['user'])) {
            return [false, ['status' => 401, 'message' => 'Необходима авторизация']];
        }

        $role = $_SESSION['user']['role'] ?? null;
        if (!in_array($role, $allowed_roles, true)) {
            return [false, ['status' => 403, 'message' => 'Недостаточно прав']];
        }

        return [true, null];
    }

    protected function ok($data = []) {
        return ['success' => true, 'data' => $data];
    }

    protected function fail($message, $status = 400) {
        return ['success' => false, 'status' => $status, 'message' => $message];
    }
}
?>
