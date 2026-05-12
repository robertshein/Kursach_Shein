<?php
require_once __DIR__ . '/_init.php';

$nav_admin_section = 'dashboard';

$stats = [];
$r = $admin_controller->getDashboardStats();
if ($r['success'] ?? false) {
    $stats = $r['data']['stats'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора — АвтоПлюс</title>
    <?php include __DIR__ . '/../../includes/layout_styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/site_nav.php'; ?>

    <div class="page">
        <h1 class="sans">Панель администратора</h1>
        <p class="lead">Здравствуйте, <?php echo htmlspecialchars($admin_user['full_name'] ?? ''); ?>. Управляйте сотрудниками, закупками и зарплатами.</p>

        <div class="stats sans">
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['total_orders'] ?? 0); ?></div>
                <div class="lbl">Всего заявок</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['new_orders'] ?? 0); ?></div>
                <div class="lbl">Новых заявок</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['pending_purchases'] ?? 0); ?></div>
                <div class="lbl">Закупки (ожидают)</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['active_employees'] ?? 0); ?></div>
                <div class="lbl">Активных сотрудников</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['salary_drafts'] ?? 0); ?></div>
                <div class="lbl">Зарплат на согласовании</div>
            </div>
            <div class="stat">
                <div class="num"><?php echo (int) ($stats['total_clients'] ?? 0); ?></div>
                <div class="lbl">Клиентов</div>
            </div>
        </div>

        <section class="card">
            <h2>Быстрые действия</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px;">
                <a class="btn-submit" href="employees.php" style="display:inline-block; text-decoration:none;">
                    Сотрудники
                </a>
                <a class="btn-submit" href="purchase_requests.php" style="display:inline-block; text-decoration:none; background:#fff; color:var(--focus);">
                    Запросы на закупку
                    <?php if (!empty($stats['pending_purchases'])): ?>
                        <span style="background:var(--focus);color:#fff;border-radius:10px;padding:1px 7px;font-size:0.75rem;margin-left:4px;">
                            <?php echo (int) $stats['pending_purchases']; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="btn-submit" href="salary.php" style="display:inline-block; text-decoration:none; background:#fff; color:var(--focus);">
                    Зарплаты
                    <?php if (!empty($stats['salary_drafts'])): ?>
                        <span style="background:var(--focus);color:#fff;border-radius:10px;padding:1px 7px;font-size:0.75rem;margin-left:4px;">
                            <?php echo (int) $stats['salary_drafts']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </section>

        <section class="card">
            <h2>Отчётность</h2>
            <p class="hint" style="margin-top:0; margin-bottom:14px;">
                Системный отчёт содержит 5 листов: сводная статистика, все заявки,
                список сотрудников, история закупок и зарплатные записи.
            </p>
            <a href="report_download.php"
               class="btn-submit"
               style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Скачать отчёт Excel (.xlsx)
            </a>
            <p class="hint" style="margin-top:10px;">
                Данные актуальны на момент скачивания.
                Формат совместим с Microsoft Excel, LibreOffice Calc и Google Sheets.
            </p>
        </section>
    </div>
</body>
</html>
