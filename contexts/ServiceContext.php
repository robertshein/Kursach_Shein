<?php
require_once __DIR__ . '/AppContext.php';

class ServiceContext extends AppContext
{
    public function getAll(): array
    {
        return $this->fetchAll("SELECT id, name, price FROM services ORDER BY name ASC");
    }

    public function exists(int $id): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM services WHERE id = ? LIMIT 1", 'i', [$id]);
    }
}
