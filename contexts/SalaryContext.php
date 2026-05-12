<?php
require_once __DIR__ . '/AppContext.php';

class SalaryContext extends AppContext
{
    public function findById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM salary_records WHERE id = ? LIMIT 1", 'i', [$id]);
    }

    public function create(int $employeeId, float $amount, string $periodStart, string $periodEnd, int $adminId, string $comment = ''): int
    {
        return $this->insert(
            "INSERT INTO salary_records (employee_id, amount, period_start, period_end, status, comment, created_by_admin_id) VALUES (?, ?, ?, ?, 'draft', ?, ?)",
            'idssi', [$employeeId, $amount, $periodStart, $periodEnd, $comment, $adminId]
        );
    }

    public function setStatus(int $id, string $status): bool
    {
        $approvedAt = $status === 'approved' ? 'NOW()' : 'NULL';
        $paidAt     = $status === 'paid'     ? 'NOW()' : 'NULL';
        return $this->execute(
            "UPDATE salary_records SET status = ?, approved_at = {$approvedAt}, paid_at = {$paidAt} WHERE id = ?",
            'si', [$status, $id]
        );
    }

    public function bulkSetStatus(string $periodStart, string $periodEnd, string $fromStatus, string $toStatus): int
    {
        $approvedAt = $toStatus === 'approved' ? 'NOW()' : 'NULL';
        $paidAt     = $toStatus === 'paid'     ? 'NOW()' : 'NULL';
        return $this->affectedExecute(
            "UPDATE salary_records SET status = ?, approved_at = {$approvedAt}, paid_at = {$paidAt} WHERE period_start = ? AND period_end = ? AND status = ?",
            'ssss', [$toStatus, $periodStart, $periodEnd, $fromStatus]
        );
    }

    public function getExistingEmployeeIds(string $periodStart, string $periodEnd): array
    {
        $rows = $this->fetchAll(
            "SELECT employee_id FROM salary_records WHERE period_start = ? AND period_end = ?",
            'ss', [$periodStart, $periodEnd]
        );
        return array_column($rows, 'employee_id');
    }

    public function getAll(): array
    {
        return $this->fetchAll(
            "SELECT sr.id, sr.employee_id, sr.amount, sr.period_start, sr.period_end,
                    sr.status, sr.comment, sr.approved_at, sr.paid_at,
                    u_e.full_name AS employee_name, u_e.role AS employee_role,
                    u_a.full_name AS created_by_admin_name
             FROM salary_records sr
             JOIN users u_e ON u_e.id = sr.employee_id
             JOIN users u_a ON u_a.id = sr.created_by_admin_id
             ORDER BY sr.period_start DESC, sr.id DESC"
        );
    }

    public function getByPeriods(): array
    {
        $periods = $this->fetchAll(
            "SELECT period_start, period_end,
                    COUNT(*)                                     AS total_records,
                    SUM(amount)                                  AS total_amount,
                    SUM(status='draft')                          AS cnt_draft,
                    SUM(status='approved')                       AS cnt_approved,
                    SUM(status='paid')                           AS cnt_paid,
                    SUM(status='rejected')                       AS cnt_rejected,
                    SUM(CASE WHEN status='paid'     THEN amount ELSE 0 END) AS paid_amount,
                    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) AS approved_amount
             FROM salary_records GROUP BY period_start, period_end ORDER BY period_start DESC"
        );

        $records = $this->fetchAll(
            "SELECT sr.id, sr.employee_id, sr.amount, sr.period_start, sr.period_end,
                    sr.status, sr.comment, sr.approved_at, sr.paid_at,
                    u.full_name AS employee_name, u.role AS employee_role
             FROM salary_records sr
             JOIN users u ON u.id = sr.employee_id
             ORDER BY sr.period_start DESC, u.full_name"
        );

        $details = [];
        foreach ($records as $rec) {
            $key = $rec['period_start'] . '_' . $rec['period_end'];
            $details[$key][] = $rec;
        }

        return ['periods' => $periods, 'details' => $details];
    }

    public function getStats(): array
    {
        $result = mysqli_query($this->db,
            "SELECT COUNT(*) AS total,
                    SUM(status='draft')                          AS draft_cnt,
                    SUM(status='approved')                       AS approved_cnt,
                    SUM(status='paid')                           AS paid_cnt,
                    SUM(CASE WHEN status='paid'     THEN amount ELSE 0 END) AS total_paid_amount,
                    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) AS total_approved_amount,
                    SUM(CASE WHEN status='draft'    THEN amount ELSE 0 END) AS total_draft_amount
             FROM salary_records"
        );
        return $result ? ($result->fetch_assoc() ?? []) : [];
    }

    public function countDrafts(): int
    {
        $result = mysqli_query($this->db, "SELECT COUNT(*) AS cnt FROM salary_records WHERE status = 'draft'");
        return $result ? (int) $result->fetch_assoc()['cnt'] : 0;
    }
}
