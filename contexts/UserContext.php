<?php
require_once __DIR__ . '/AppContext.php';

class UserContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT id, full_name, phone, email, role, salary, is_active, created_at FROM users WHERE id = ? LIMIT 1",
            'i', [$id]
        );
    }

    public function findByEmailWithPassword(string $email): ?array
    {
        return $this->fetchOne(
            "SELECT id, full_name, phone, email, role, password, salary, is_active FROM users WHERE email = ? LIMIT 1",
            's', [$email]
        );
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1",
            'si', [$email, $excludeId]
        );
    }

    public function getEmployees(): array
    {
        return $this->fetchAll(
            "SELECT id, full_name, phone, email, role, salary, is_active, created_at FROM users WHERE role != 'client' ORDER BY role, full_name"
        );
    }

    public function getMechanics(): array
    {
        return $this->fetchAll(
            "SELECT id, full_name, email, phone FROM users WHERE role = 'mechanic' AND is_active = 1 ORDER BY full_name"
        );
    }

    public function getMechanicsWithLoad(): array
    {
        return $this->fetchAll(
            "SELECT u.id, u.full_name, u.email, u.phone,
                    COUNT(o.id) AS active_orders
             FROM users u
             LEFT JOIN orders o ON o.mechanic_id = u.id
               AND o.status NOT IN ('completed','cancelled')
             WHERE u.role = 'mechanic' AND u.is_active = 1
             GROUP BY u.id
             ORDER BY active_orders ASC, u.full_name ASC"
        );
    }

    public function getLeastBusyMaster(): ?array
    {
        $rows = $this->fetchAll(
            "SELECT u.id, u.full_name,
                    COUNT(o.id) AS active_orders
             FROM users u
             LEFT JOIN orders o ON o.master_id = u.id
               AND o.status NOT IN ('completed','cancelled')
             WHERE u.role = 'master' AND u.is_active = 1
             GROUP BY u.id
             ORDER BY active_orders ASC"
        );
        if (empty($rows)) {
            return null;
        }
        $minLoad    = (int) $rows[0]['active_orders'];
        $candidates = array_values(array_filter($rows, fn($r) => (int)$r['active_orders'] === $minLoad));
        return $candidates[array_rand($candidates)];
    }

    public function getClients(): array
    {
        return $this->fetchAll(
            "SELECT id, full_name, phone, email, is_active, created_at FROM users WHERE role = 'client' ORDER BY full_name"
        );
    }

    public function getActiveEmployeesWithSalary(): array
    {
        return $this->fetchAll(
            "SELECT id, full_name, salary FROM users WHERE role != 'client' AND is_active = 1 AND salary > 0"
        );
    }

    public function isActiveMechanic(int $id): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM users WHERE id = ? AND role = 'mechanic' AND is_active = 1 LIMIT 1",
            'i', [$id]
        );
    }

    public function create(string $fullName, string $phone, string $email, string $role, string $passwordHash, float $salary = 0): int
    {
        return $this->insert(
            "INSERT INTO users (full_name, phone, email, role, password, salary, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
            'sssssd', [$fullName, $phone, $email, $role, $passwordHash, $salary]
        );
    }

    public function update(int $id, string $fullName, string $phone, string $email, string $role, float $salary): bool
    {
        return $this->execute(
            "UPDATE users SET full_name = ?, phone = ?, email = ?, role = ?, salary = ? WHERE id = ?",
            'ssssdi', [$fullName, $phone, $email, $role, $salary, $id]
        );
    }

    public function updateProfile(int $id, string $fullName, string $phone, string $email): bool
    {
        return $this->execute(
            "UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?",
            'sssi', [$fullName, $phone, $email, $id]
        );
    }

    public function updatePassword(int $id, string $hash): bool
    {
        return $this->execute(
            "UPDATE users SET password = ? WHERE id = ?",
            'si', [$hash, $id]
        );
    }

    public function setActive(int $id, bool $active): bool
    {
        $val = $active ? 1 : 0;
        return $this->execute(
            "UPDATE users SET is_active = ? WHERE id = ?",
            'ii', [$val, $id]
        );
    }

    public function setSalary(int $id, float $salary): bool
    {
        return $this->execute(
            "UPDATE users SET salary = ? WHERE id = ?",
            'di', [$salary, $id]
        );
    }
}
