<?php

class MechanicAssignment {
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_REASSIGNED = 'reassigned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public $id;
    public $order_id;
    public $mechanic_id;
    public $assigned_by_master_id;
    public $status;
    public $comment;
    public $assigned_at;
    public $updated_at;

    public function __construct(
        $id,
        $order_id,
        $mechanic_id,
        $assigned_by_master_id,
        $status = self::STATUS_ASSIGNED,
        $comment = null,
        $assigned_at = null,
        $updated_at = null
    ) {
        $this->id = $id;
        $this->order_id = $order_id;
        $this->mechanic_id = $mechanic_id;
        $this->assigned_by_master_id = $assigned_by_master_id;
        $this->status = $status;
        $this->comment = $comment;
        $this->assigned_at = $assigned_at;
        $this->updated_at = $updated_at;
    }

    public static function getAvailableStatuses() {
        return [
            self::STATUS_ASSIGNED,
            self::STATUS_REASSIGNED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED
        ];
    }
}
?>
