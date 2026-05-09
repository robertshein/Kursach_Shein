<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';
require_once __DIR__ . '/../models/SalaryRecord.php';

class AdminController extends BaseController {
    public function createEmployee($full_name, $phone, $email, $password, $role, $salary) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN], true)) {
            return $this->fail('Сотруднику можно назначить только роль mechanic/master/admin');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (full_name, phone, email, role, password, salary, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssd', $full_name, $phone, $email, $role, $hash, $salary);
        $ok = mysqli_stmt_execute($stmt);
        $employee_id = mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось добавить сотрудника', 500);
        }

        return $this->ok(['employee_id' => $employee_id, 'message' => 'Сотрудник добавлен']);
    }

    public function setEmployeeActive($employee_id, $is_active) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $active = $is_active ? 1 : 0;
        $sql = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $active, $employee_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось изменить статус сотрудника', 500);
        }

        return $this->ok(['message' => 'Статус сотрудника обновлен']);
    }

    public function setSalary($employee_id, $salary) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $sql = "UPDATE users SET salary = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'di', $salary, $employee_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось установить зарплату', 500);
        }

        return $this->ok(['message' => 'Зарплата обновлена']);
    }

    public function decidePurchaseRequest($request_id, $admin_id, $approve, $comment = null) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $status = $approve ? PartPurchaseRequest::STATUS_APPROVED : PartPurchaseRequest::STATUS_REJECTED;
        $sql = "UPDATE part_purchase_requests SET approved_by_admin_id = ?, status = ?, comment = ?, resolved_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'issi', $admin_id, $status, $comment, $request_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось обработать запрос на закупку', 500);
        }

        return $this->ok(['message' => 'Запрос на закупку обработан']);
    }

    public function createSalaryRecord($employee_id, $amount, $period_start, $period_end, $admin_id, $comment = null) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        $status = SalaryRecord::STATUS_DRAFT;
        $sql = "INSERT INTO salary_records (employee_id, amount, period_start, period_end, status, comment, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'idssssi', $employee_id, $amount, $period_start, $period_end, $status, $comment, $admin_id);
        $ok = mysqli_stmt_execute($stmt);
        $salary_record_id = mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось создать запись по зарплате', 500);
        }

        return $this->ok(['salary_record_id' => $salary_record_id, 'message' => 'Запись по зарплате создана']);
    }

    public function setSalaryRecordStatus($salary_record_id, $new_status) {
        $role_check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$role_check[0]) {
            return $this->fail($role_check[1]['message'], $role_check[1]['status']);
        }

        if (!in_array($new_status, SalaryRecord::getAvailableStatuses(), true)) {
            return $this->fail('Недопустимый статус зарплатной записи');
        }

        $approved_at = $new_status === SalaryRecord::STATUS_APPROVED ? 'NOW()' : 'NULL';
        $paid_at = $new_status === SalaryRecord::STATUS_PAID ? 'NOW()' : 'NULL';
        $sql = "UPDATE salary_records SET status = ?, approved_at = {$approved_at}, paid_at = {$paid_at} WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $salary_record_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return $this->fail('Не удалось обновить статус зарплатной записи', 500);
        }

        return $this->ok(['message' => 'Статус зарплатной записи обновлен']);
    }
}
?>
