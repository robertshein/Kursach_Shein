<?php

class Order {
    public $id;
    public $car_id;
    public $mechanic_id;
    public $total_price;
    public $start_date;
    public $end_date;
    public $status;
    
    public function __construct($id, $car_id, $mechanic_id, $total_price, $start_date, $end_date, $status) {
        $this->id = $id;
        $this->car_id = $car_id;
        $this->mechanic_id = $mechanic_id;
        $this->total_price = $total_price;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->status = $status;
    }
}
?>