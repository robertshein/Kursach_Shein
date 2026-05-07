<?php
class Service {
    public $id;
    public $name;
    public $price;
    public $description;
    public $image;
    public $category;
    public function __construct($id, $name, $price, $description, $image, $category) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
        $this->image = $image;
        $this->category = $category;
    }
}
?>