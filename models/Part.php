<?php

class Part {
    public $id;
    public $name;
    public $article;
    public $price;
    public $quantity;
    public $reserved_quantity;
    public $min_quantity;
    public $description;
    public $image;
    
    public function __construct(
        $id,
        $name,
        $article,
        $price,
        $quantity,
        $description,
        $image,
        $reserved_quantity = 0,
        $min_quantity = 0
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->article = $article;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->description = $description;
        $this->image = $image;
        $this->reserved_quantity = $reserved_quantity;
        $this->min_quantity = $min_quantity;
    }

    public function getAvailableQuantity() {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function isLowStock() {
        return $this->getAvailableQuantity() <= $this->min_quantity;
    }
}
?>