<?php
require_once __DIR__ . '/AppContext.php';

class PartContext extends AppContext
{
    public function getAll(int $limit = 500): array
    {
        return $this->fetchAll(
            "SELECT id, name, article, quantity, price FROM parts ORDER BY name ASC LIMIT {$limit}"
        );
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT id, name, article, quantity, price FROM parts WHERE id = ? LIMIT 1",
            'i', [$id]
        );
    }

    public function exists(int $id): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM parts WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function articleExists(string $article, int $excludeId = 0): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM parts WHERE article = ? AND id != ? LIMIT 1",
            'si', [$article, $excludeId]
        );
    }

    public function isUsedInRequests(int $id): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM part_purchase_requests WHERE part_id = ? LIMIT 1",
            'i', [$id]
        );
    }

    public function create(string $name, string $article, int $quantity, float $price): int
    {
        return $this->insert(
            "INSERT INTO parts (name, article, quantity, price) VALUES (?, ?, ?, ?)",
            'ssid', [$name, $article, $quantity, $price]
        );
    }

    public function update(int $id, string $name, string $article, int $quantity, float $price): bool
    {
        return $this->execute(
            "UPDATE parts SET name = ?, article = ?, quantity = ?, price = ? WHERE id = ?",
            'sssdi', [$name, $article, $quantity, $price, $id]
        );
    }

    public function delete(int $id): bool
    {
        return $this->execute("DELETE FROM parts WHERE id = ?", 'i', [$id]);
    }
}
