<?php
require_once __DIR__ . '/AppContext.php';

class CarContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM cars WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function getByClient(int $clientId): array
    {
        return $this->fetchAll(
            "SELECT id, vin, brand, model, year, gosnumber FROM cars WHERE user_id = ? ORDER BY id DESC",
            'i', [$clientId]
        );
    }

    public function vinExists(string $vin, int $excludeId = 0): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM cars WHERE vin = ? AND id != ? LIMIT 1",
            'si', [$vin, $excludeId]
        );
    }

    public function belongsToClient(int $carId, int $clientId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM cars WHERE id = ? AND user_id = ? LIMIT 1",
            'ii', [$carId, $clientId]
        );
    }

    public function create(int $clientId, string $vin, string $brand, string $model, int $year, string $gosnumber): int
    {
        return $this->insert(
            "INSERT INTO cars (user_id, vin, brand, model, year, gosnumber) VALUES (?, ?, ?, ?, ?, ?)",
            'isssis', [$clientId, $vin, $brand, $model, $year, $gosnumber]
        );
    }

    public function update(int $carId, string $vin, string $brand, string $model, int $year, string $gosnumber): bool
    {
        return $this->execute(
            "UPDATE cars SET vin = ?, brand = ?, model = ?, year = ?, gosnumber = ? WHERE id = ?",
            'sssisi', [$vin, $brand, $model, $year, $gosnumber, $carId]
        );
    }
}
