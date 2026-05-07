<?php

class ServicePart {
    public $id;
    public $service_id;
    public $part_id;
    public $quantity;

    public function __construct($id, $service_id, $part_id, $quantity = 1) {
        $this->id = $id;
        $this->service_id = $service_id;
        $this->part_id = $part_id;
        $this->quantity = $quantity;
    }
}
?>
