<?php

class SalaryRecord {
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    public $id;
    public $employee_id;
    public $amount;
    public $period_start;
    public $period_end;
    public $status;
    public $comment;
    public $created_by_admin_id;
    public $approved_at;
    public $paid_at;

    public function __construct(
        $id,
        $employee_id,
        $amount,
        $period_start,
        $period_end,
        $created_by_admin_id,
        $status = self::STATUS_DRAFT,
        $comment = null,
        $approved_at = null,
        $paid_at = null
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->amount = $amount;
        $this->period_start = $period_start;
        $this->period_end = $period_end;
        $this->status = $status;
        $this->comment = $comment;
        $this->created_by_admin_id = $created_by_admin_id;
        $this->approved_at = $approved_at;
        $this->paid_at = $paid_at;
    }

    public static function getAvailableStatuses() {
        return [
            self::STATUS_DRAFT,
            self::STATUS_APPROVED,
            self::STATUS_PAID,
            self::STATUS_REJECTED
        ];
    }
}
?>
