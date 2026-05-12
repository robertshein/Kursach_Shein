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
}
