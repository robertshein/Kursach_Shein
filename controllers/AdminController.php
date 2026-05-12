<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/SalaryRecord.php';
require_once __DIR__ . '/../models/PartPurchaseRequest.php';

class AdminController extends BaseController
{
    public function getEmployees(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['employees' => $this->users()->getEmployees()]);
    }

    public function getClients(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['clients' => $this->users()->getClients()]);
    }

    public function createEmployee(string $fullName, string $phone, string $email, string $password, string $role, float $salary): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN], true)) return $this->fail('Некорректная роль сотрудника');
        if ($this->users()->emailExists($email)) return $this->fail('Пользователь с таким email уже существует');

        $id = $this->users()->create($fullName, $phone, $email, $role, password_hash($password, PASSWORD_BCRYPT), $salary);
        if (!$id) return $this->fail('Не удалось добавить сотрудника', 500);
        return $this->ok(['employee_id' => $id, 'message' => 'Сотрудник добавлен']);
    }

    public function updateEmployee(int $employeeId, string $fullName, string $phone, string $email, string $role, float $salary): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!in_array($role, [User::ROLE_MECHANIC, User::ROLE_MASTER, User::ROLE_ADMIN], true)) return $this->fail('Некорректная роль сотрудника');
        if ($this->users()->emailExists($email, $employeeId)) return $this->fail('Пользователь с таким email уже существует');
        if (!$this->users()->update($employeeId, $fullName, $phone, $email, $role, $salary)) return $this->fail('Не удалось обновить данные сотрудника', 500);
        return $this->ok(['message' => 'Данные сотрудника обновлены']);
    }

    public function setEmployeeActive(int $employeeId, bool $isActive): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->users()->setActive($employeeId, $isActive)) return $this->fail('Не удалось изменить статус сотрудника', 500);
        return $this->ok(['message' => 'Статус сотрудника обновлён']);
    }

    public function setSalary(int $employeeId, float $salary): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        if (!$this->users()->setSalary($employeeId, $salary)) return $this->fail('Не удалось установить зарплату', 500);
        return $this->ok(['message' => 'Зарплата обновлена']);
    }

    public function getAllPurchaseRequests(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['requests' => $this->purchases()->getAll()]);
    }

    public function decidePurchaseRequest(int $requestId, int $adminId, bool $approve, ?string $comment = null): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $status = $approve ? PartPurchaseRequest::STATUS_APPROVED : PartPurchaseRequest::STATUS_REJECTED;
        if (!$this->purchases()->decide($requestId, $adminId, $status, $comment)) return $this->fail('Не удалось обработать запрос на закупку', 500);
        return $this->ok(['message' => 'Запрос на закупку обработан']);
    }

    public function getSalaryRecords(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['records' => $this->salary()->getAll()]);
    }

    public function getSalaryByPeriods(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok($this->salary()->getByPeriods());
    }

    public function getSalaryStats(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);
        return $this->ok(['stats' => $this->salary()->getStats()]);
    }

    public function createSalaryRecord(int $employeeId, float $amount, string $periodStart, string $periodEnd, int $adminId, ?string $comment = null): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $id = $this->salary()->create($employeeId, $amount, $periodStart, $periodEnd, $adminId, (string) ($comment ?? ''));
        if (!$id) return $this->fail('Не удалось создать запись по зарплате', 500);
        return $this->ok(['salary_record_id' => $id, 'message' => 'Запись по зарплате создана']);
    }

    public function setSalaryRecordStatus(int $salaryRecordId, string $newStatus): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if (!in_array($newStatus, SalaryRecord::getAvailableStatuses(), true)) return $this->fail('Недопустимый статус зарплатной записи');
        if (!$this->salary()->setStatus($salaryRecordId, $newStatus)) return $this->fail('Не удалось обновить статус зарплатной записи', 500);
        return $this->ok(['message' => 'Статус зарплатной записи обновлён']);
    }

    public function generatePayroll(string $periodStart, string $periodEnd, int $adminId): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        if ($periodStart > $periodEnd) return $this->fail('Дата начала периода не может быть позже даты окончания');

        $employees = $this->users()->getActiveEmployeesWithSalary();
        if (empty($employees)) return $this->fail('Нет активных сотрудников с установленным окладом');

        $existingIds = array_map('intval', $this->salary()->getExistingEmployeeIds($periodStart, $periodEnd));
        $created = 0;
        $skipped = 0;

        mysqli_begin_transaction($this->db);
        foreach ($employees as $emp) {
            $eid = (int) $emp['id'];
            if (in_array($eid, $existingIds, true)) { $skipped++; continue; }
            $comment = 'Оклад за период ' . $periodStart . ' — ' . $periodEnd;
            $id = $this->salary()->create($eid, (float) $emp['salary'], $periodStart, $periodEnd, $adminId, $comment);
            if (!$id) {
                mysqli_rollback($this->db);
                return $this->fail('Ошибка при создании записи для сотрудника ' . htmlspecialchars($emp['full_name']), 500);
            }
            $created++;
        }
        mysqli_commit($this->db);

        return $this->ok(['created' => $created, 'skipped' => $skipped, 'message' => "Ведомость сформирована: создано {$created}, пропущено {$skipped}."]);
    }

    public function bulkSetSalaryStatus(string $periodStart, string $periodEnd, string $fromStatus, string $toStatus): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $allowed = SalaryRecord::getAvailableStatuses();
        if (!in_array($fromStatus, $allowed, true) || !in_array($toStatus, $allowed, true)) return $this->fail('Некорректный статус');

        $affected = $this->salary()->bulkSetStatus($periodStart, $periodEnd, $fromStatus, $toStatus);
        return $this->ok(['affected' => $affected, 'message' => "Обновлено записей: {$affected}."]);
    }

    public function getDashboardStats(): array
    {
        $check = $this->requireRole([User::ROLE_ADMIN]);
        if (!$check[0]) return $this->fail($check[1]['message'], $check[1]['status']);

        $orderStats = $this->orders()->getStats();
        $stats = [
            'total_orders'      => (int) ($orderStats['total']    ?? 0),
            'new_orders'        => (int) ($orderStats['cnt_new']  ?? 0),
            'active_employees'  => 0,
            'total_clients'     => 0,
            'pending_purchases' => $this->purchases()->countPending(),
            'salary_drafts'     => $this->salary()->countDrafts(),
        ];

        $res = mysqli_query($this->db, "SELECT COUNT(*) AS c FROM users WHERE role = 'client'");
        $stats['total_clients'] = $res ? (int) $res->fetch_assoc()['c'] : 0;

        $res2 = mysqli_query($this->db, "SELECT COUNT(*) AS c FROM users WHERE role != 'client' AND is_active = 1");
        $stats['active_employees'] = $res2 ? (int) $res2->fetch_assoc()['c'] : 0;

        return $this->ok(['stats' => $stats]);
    }
}
