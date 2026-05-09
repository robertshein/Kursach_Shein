<?php

class PartPurchaseRequest {
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_RECEIVED = 'received';

    public $id;
    public $order_id;
    public $part_id;
    public $quantity;
    public $requested_by_master_id;
    public $approved_by_admin_id;
    public $status;
    public $comment;
    public $created_at;
    public $resolved_at;

    public function __construct(
        $id,
        $order_id,
        $part_id,
        $quantity,
        $requested_by_master_id,
        $approved_by_admin_id = null,
        $status = self::STATUS_PENDING,
        $comment = null,
        $created_at = null,
        $resolved_at = null
    ) {
        $this->id = $id;
        $this->order_id = $order_id;
        $this->part_id = $part_id;
        $this->quantity = $quantity;
        $this->requested_by_master_id = $requested_by_master_id;
        $this->approved_by_admin_id = $approved_by_admin_id;
        $this->status = $status;
        $this->comment = $comment;
        $this->created_at = $created_at;
        $this->resolved_at = $resolved_at;
    }

    public static function getAvailableStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_ORDERED,
            self::STATUS_RECEIVED
        ];
    }
}
?>
