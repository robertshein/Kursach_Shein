<?php

class User {
    public const ROLE_CLIENT = 'client';
    public const ROLE_MECHANIC = 'mechanic';
    public const ROLE_MASTER = 'master';
    public const ROLE_ADMIN = 'admin';

    public $id;
    public $full_name;
    public $phone;
    public $email;
    public $role;
    public $password;
    public $salary;
    public $is_active;
    
    public function __construct(
        $id,
        $full_name,
        $phone,
        $email,
        $role,
        $password,
        $salary = null,
        $is_active = true
    ) {
        $this->id = $id;
        $this->full_name = $full_name;
        $this->phone = $phone;
        $this->email = $email;
        $this->role = $role;
        $this->password = $password;
        $this->salary = $salary;
        $this->is_active = $is_active;
    }

    public static function getAvailableRoles() {
        return [
            self::ROLE_CLIENT,
            self::ROLE_MECHANIC,
            self::ROLE_MASTER,
            self::ROLE_ADMIN
        ];
    }

    public static function isValidRole($role) {
        return in_array($role, self::getAvailableRoles(), true);
    }

    public function isEmployee() {
        return in_array($this->role, [self::ROLE_MECHANIC, self::ROLE_MASTER, self::ROLE_ADMIN], true);
    }
}
?>