<?php
require_once __DIR__ . '/AppContext.php';

class OrderStatusHistoryContext extends AppContext
{
    public static function statusLabel(string $status): string
    {
        $map = [
            'new'           => 'Новая',
            'assigned'      => 'Назначен механик',
            'in_progress'   => 'В работе',
            'waiting_parts' => 'Ожидание запчастей',
            'completed'     => 'Завершена',
            'cancelled'     => 'Отменена',
        ];
        return $map[$status] ?? $status;
    }

    public static function roleLabel(string $role): string
    {
        $map = [
            'client'   => 'Клиент',
            'master'   => 'Мастер',
            'mechanic' => 'Механик',
            'admin'    => 'Администратор',
        ];
        return $map[$role] ?? $role;
    }

    public function log(int $orderId, ?string $oldStatus, string $newStatus, int $userId, string $note = ''): void
    {
        $this->execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by_user_id, note)
             VALUES (?, ?, ?, ?, ?)",
            'issis', [$orderId, $oldStatus, $newStatus, $userId, $note === '' ? null : $note]
        );
    }

    public function getForClientOrders(int $clientId): array
    {
        return $this->fetchAll(
            "SELECT osh.id, osh.order_id, osh.old_status, osh.new_status,
                    osh.note, osh.changed_at,
                    u.role AS changed_by_role
             FROM order_status_history osh
             JOIN orders o ON o.id = osh.order_id
             JOIN users  u ON u.id = osh.changed_by_user_id
             WHERE o.client_id = ?
             ORDER BY osh.order_id ASC, osh.id ASC",
            'i', [$clientId]
        );
    }
}
