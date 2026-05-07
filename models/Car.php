<?php
class Car {
    public $id;
    public $user_id;
    public $vin;
    public $brand;
    public $model;
    public $year;
    public $gosnumber;
    
    public function __construct($id, $user_id, $vin, $brand, $model, $year, $gosnumber) {
        $this->id = $id;
        $this->user_id = $user_id;
        $this->vin = $vin;
        $this->brand = $brand;
        $this->model = $model;
        $this->year = $year;
        $this->gosnumber = $gosnumber;
    }
}