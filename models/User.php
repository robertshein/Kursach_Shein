<?php

class User {
    public $id;
    public $full_name;
    public $phone;
    public $email;
    public $role;
    public $password;
    
    public function __construct($id, $full_name, $phone, $email, $role, $password) {
        $this->id = $id;
        $this->full_name = $full_name;
        $this->phone = $phone;
        $this->email = $email;
        $this->role = $role;
        $this->password = $password;
    }
}
?>