<?php

class Order {
    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_PARTS = 'waiting_parts';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public $id;
    public $client_id;
    public $car_id;
    public $mechanic_id;
    public $master_id;
    public $total_price;
    public $start_date;
    public $end_date;
    public $status;
    public $description;
    
    public function __construct(
        $id,
        $client_id,
        $car_id,
        $mechanic_id = null,
        $master_id = null,
        $total_price = 0,
        $start_date = null,
        $end_date = null,
        $status = self::STATUS_NEW,
        $description = null
    ) {
        $this->id = $id;
        $this->client_id = $client_id;
        $this->car_id = $car_id;
        $this->mechanic_id = $mechanic_id;
        $this->master_id = $master_id;
        $this->total_price = $total_price;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->status = $status;
        $this->description = $description;
    }

    public static function getAvailableStatuses() {
        return [
            self::STATUS_NEW,
            self::STATUS_ASSIGNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_PARTS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED
        ];
    }

    public static function isValidStatus($status) {
        return in_array($status, self::getAvailableStatuses(), true);
    }
}
?>