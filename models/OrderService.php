<?php

class OrderService {
    public $id;
    public $order_id;
    public $service_id;
    public $quantity;
    public $comment;

    public function __construct($id, $order_id, $service_id, $quantity = 1) {
        $this->id = $id;
        $this->order_id = $order_id;
        $this->service_id = $service_id;
        $this->quantity = $quantity;
    }
}
?>
