<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends BaseController
{
    public function register(string $fullName, string $phone, string $email, string $password, string $role = User::ROLE_CLIENT): array
    {
        if (!User::isValidRole($role)) return $this->fail('Некорректная роль');
        if ($role !== User::ROLE_CLIENT) return $this->fail('Саморегистрация доступна только для клиентов');
        if ($this->users()->emailExists($email)) return $this->fail('Пользователь с таким email уже существует');

        $id = $this->users()->create($fullName, $phone, $email, $role, password_hash($password, PASSWORD_BCRYPT), 0);
        if (!$id) return $this->fail('Не удалось зарегистрировать пользователя', 500);
        return $this->ok(['message' => 'Регистрация выполнена']);
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users()->findByEmailWithPassword($email);
        if (!$user || !password_verify($password, $user['password'])) return $this->fail('Неверный email или пароль', 401);
        if ((int) $user['is_active'] !== 1) return $this->fail('Пользователь деактивирован', 403);

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($user['password']);
        $_SESSION['user'] = $user;
        return $this->ok(['user' => $user]);
    }

    public function logout(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_destroy();
        return $this->ok(['message' => 'Выход выполнен']);
    }
}
