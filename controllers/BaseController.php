<?php
require_once __DIR__ . '/../contexts/AppContext.php';
require_once __DIR__ . '/../contexts/UserContext.php';
require_once __DIR__ . '/../contexts/OrderContext.php';
require_once __DIR__ . '/../contexts/CarContext.php';
require_once __DIR__ . '/../contexts/ServiceContext.php';
require_once __DIR__ . '/../contexts/PartContext.php';
require_once __DIR__ . '/../contexts/PurchaseRequestContext.php';
require_once __DIR__ . '/../contexts/SalaryContext.php';
require_once __DIR__ . '/../contexts/MechanicAssignmentContext.php';

class BaseController
{
    protected $db;

    private ?UserContext               $_users       = null;
    private ?OrderContext              $_orders      = null;
    private ?CarContext                $_cars        = null;
    private ?ServiceContext            $_services    = null;
    private ?PartContext               $_parts       = null;
    private ?PurchaseRequestContext    $_purchases   = null;
    private ?SalaryContext             $_salary      = null;
    private ?MechanicAssignmentContext $_assignments = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function users(): UserContext               { return $this->_users       ??= new UserContext($this->db); }
    protected function orders(): OrderContext             { return $this->_orders      ??= new OrderContext($this->db); }
    protected function cars(): CarContext                 { return $this->_cars        ??= new CarContext($this->db); }
    protected function services(): ServiceContext         { return $this->_services    ??= new ServiceContext($this->db); }
    protected function parts(): PartContext               { return $this->_parts       ??= new PartContext($this->db); }
    protected function purchases(): PurchaseRequestContext{ return $this->_purchases   ??= new PurchaseRequestContext($this->db); }
    protected function salary(): SalaryContext            { return $this->_salary      ??= new SalaryContext($this->db); }
    protected function assignments(): MechanicAssignmentContext { return $this->_assignments ??= new MechanicAssignmentContext($this->db); }

    protected function requireRole(array $allowedRoles): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if (empty($_SESSION['user'])) {
            return [false, ['status' => 401, 'message' => 'Необходима авторизация']];
        }
        $role = $_SESSION['user']['role'] ?? null;
        if (!in_array($role, $allowedRoles, true)) {
            return [false, ['status' => 403, 'message' => 'Недостаточно прав']];
        }
        return [true, null];
    }

    protected function currentUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    protected function ok(array $data = []): array  { return ['success' => true,  'data' => $data]; }
    protected function fail(string $message, int $status = 400): array { return ['success' => false, 'status' => $status, 'message' => $message]; }
}
