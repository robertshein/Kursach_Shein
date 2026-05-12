<?php
abstract class AppContext
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        if ($types === '' || $params === []) {
            $result = mysqli_query($this->db, $sql);
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    protected function fetchOne(string $sql, string $types, array $params): ?array
    {
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    protected function execute(string $sql, string $types, array $params): bool
    {
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    protected function insert(string $sql, string $types, array $params): int
    {
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $id = (int) mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);
        return $id;
    }

    protected function affectedExecute(string $sql, string $types, array $params): int
    {
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $affected = (int) mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected;
    }
}
