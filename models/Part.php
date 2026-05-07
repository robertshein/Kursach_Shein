<?php

class Part {
    public $id;
    public $name;
    public $article;
    public $price;
    public $quantity;
    public $description;
    public $image;
    
    public function __construct($id, $name, $article, $price, $quantity , $description, $image) {
        $this->id = $id;
        $this->name = $name;
        $this->article = $article;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->description = $description;
        $this->image = $image;
    }
}
?>