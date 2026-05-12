<?php
function api_json(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_ok(array $data = [], int $status = 200): void
{
    api_json(['success' => true, 'data' => $data], $status);
}

function api_error(string $message, int $status = 400): void
{
    api_json(['success' => false, 'message' => $message], $status);
}

function api_from_controller(array $result, int $successStatus = 200): void
{
    if ($result['success'] ?? false) {
        api_ok($result['data'] ?? [], $successStatus);
    } else {
        api_error($result['message'] ?? 'Ошибка', $result['status'] ?? 400);
    }
}

function api_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_param(array $data, string $key, mixed $default = null): mixed
{
    return array_key_exists($key, $data) ? $data[$key] : $default;
}

function api_require_auth(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['user'])) {
        api_error('Необходима авторизация', 401);
    }
    return $_SESSION['user'];
}

function api_require_role(array $user, array $allowedRoles): void
{
    if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
        api_error('Недостаточно прав', 403);
    }
}
