<?php
require_once __DIR__ . '/AppContext.php';

class ServiceContext extends AppContext
{
    public function getAll(): array
    {
        return $this->fetchAll("SELECT id, name, price FROM services ORDER BY name ASC");
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT id, name, price FROM services WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function exists(int $id): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM services WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function nameExists(string $name, int $excludeId = 0): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM services WHERE name = ? AND id != ? LIMIT 1",
            'si', [$name, $excludeId]
        );
    }

    public function isUsedInOrders(int $id): bool
    {
        return (bool) $this->fetchOne(
            "SELECT order_id FROM order_services WHERE service_id = ? LIMIT 1",
            'i', [$id]
        );
    }

    public function create(string $name, float $price): int
    {
        return $this->insert(
            "INSERT INTO services (name, price) VALUES (?, ?)",
            'sd', [$name, $price]
        );
    }

    public function update(int $id, string $name, float $price): bool
    {
        return $this->execute(
            "UPDATE services SET name = ?, price = ? WHERE id = ?",
            'sdi', [$name, $price, $id]
        );
    }

    public function delete(int $id): bool
    {
        return $this->execute("DELETE FROM services WHERE id = ?", 'i', [$id]);
    }
}
