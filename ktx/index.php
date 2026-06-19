<?php
// ============================================================
//  index.php — Dashboard Overview
// ============================================================

// ---------- 1. SECURITY & SESSION ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Block if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}

// ---------- 2. DATABASE CONNECTION ----------
$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red">Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ---------- 3. DASHBOARD STATISTICS ----------

// Total active students
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status_code = 'active'")->fetchColumn();

// Room stats (Total and Available)
$totalRooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$availRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status_code = 'available'")->fetchColumn();

// Total active contracts
$activeContracts = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status_code = 'active'")->fetchColumn();

// Finances: Total debt (Unpaid / Partial / Overdue invoices)
$totalDebt = $pdo->query("
    SELECT SUM(total_amount - paid_amount) 
    FROM invoices 
    WHERE status_code IN ('unpaid', 'partial', 'overdue')
")->fetchColumn();

// ---------- 4. DASHBOARD WIDGETS ----------

// 5 Recently created contracts
$recentContracts = $pdo->query("
    SELECT c.contract_code, s.full_name, r.room_number, c.start_date, b.building_name
    FROM contracts c
    JOIN students s ON s.student_id = c.student_id
    JOIN rooms r ON r.room_id = c.room_id
    JOIN buildings b ON b.building_id = r.building_id
    ORDER BY c.contract_id DESC LIMIT 5
")->fetchAll();

// 5 Pending / Overdue invoices
$pendingInvoices = $pdo->query("
    SELECT i.invoice_code, s.full_name, r.room_number, (i.total_amount - i.paid_amount) AS debt_amount, i.due_date, i.status_code
    FROM invoices i
    JOIN students s ON s.student_id = i.student_id
    JOIN contracts c ON c.contract_id = i.contract_id
    JOIN rooms r ON r.room_id = c.room_id
    WHERE i.status_code IN ('unpaid', 'partial', 'overdue')
    ORDER BY i.due_date ASC LIMIT 5
")->fetchAll();

// Format helpers (English Standard)
function fmtMoney($n) { return number_format((float)$n, 0, '.', ',') . ' VND'; }
function fmtDate($d)  { return date('M d, Y', strtotime($d)); }

// ---------- 5. INCLUDE SHARED HEADER ----------
$pageTitle = "Dashboard Overview";
include 'header.php'; 
?>

<style>
    /* Inherits .page from your style.css, layout for Dashboard Grid only */
    .dashboard-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 24px; 
        margin-top: 10px;
    }
    .widget { 
        background: #fff; 
        border-radius: 12px; 
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); 
        border: 1px solid rgba(216, 112, 147, 0.2); 
        overflow: hidden; 
    }
    .widget-header { 
        padding: 16px 20px; 
        border-bottom: 2px solid #f7fafc; 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        background: #ffffff; 
    }
    .widget-header h3 { 
        font-size: 16px; 
        font-weight: 600; 
        color: #4a5568; 
        margin: 0; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }
    .widget-header h3 i { color: #D87093; } /* VNU Pink for icons */
    .widget-body { padding: 0; }
    
    .mini-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .mini-table th { 
        background: #FFF0F5; /* Very pale pink background */
        padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; 
        color: #B8506E; border-bottom: 1px solid rgba(216,112,147,0.15); 
    }
    .mini-table td { 
        padding: 12px 16px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; 
    }
    .mini-table tr:last-child td { border-bottom: none; }
    .mini-table tr:hover { background: #fdfbfb; }

    .widget-link { font-size: 13px; text-decoration: none; color: #D87093; font-weight: 600; }
    .widget-link:hover { color: #C25A7A; text-decoration: underline; }

    /* Add FontAwesome if your header.php doesn't include it */
    @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

    @media (max-width: 768px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<main class="page">
    <div style="margin-bottom: 24px;">
        <h1 class="page-title" style="margin-bottom: 4px;">VNU Campus Dashboard</h1>
        <p class="page-desc text-muted" style="margin: 0; font-size: 14.5px;">Quick overview of operations, residency, and finances.</p>
    </div>

    <div class="stats-row">
        <div class="stat-card" style="border-left: 4px solid #3b82f6;">
            <div class="stat-value"><?= number_format($totalStudents) ?></div>
            <div class="stat-label">👥 Active Students</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-value"><?= number_format($availRooms) ?> <span style="font-size:16px;color:var(--muted);font-weight:500">/ <?= number_format($totalRooms) ?></span></div>
            <div class="stat-label">🛏 Available Rooms</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #D87093;">
            <div class="stat-value"><?= number_format($activeContracts) ?></div>
            <div class="stat-label">📄 Active Contracts</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
            <div class="stat-value" style="font-size:24px; color: #d97706;"><?= fmtMoney((float)$totalDebt) ?></div>
            <div class="stat-label">⚠ Total Debt</div>
        </div>
    </div>

    <div class="dashboard-grid">
        
        <div class="widget">
            <div class="widget-header">
                <h3><i class="fa-solid fa-file-signature"></i> Recent Contracts</h3>
                <a href="ContractListView.php" class="widget-link">View All</a>
            </div>
            <div class="widget-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Room / Bldg</th>
                            <th>Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentContracts)): ?>
                            <tr><td colspan="3" style="text-align:center; color:#6c757d; padding: 20px;">No recent contracts.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentContracts as $ct): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color: #2d3748;"><?= htmlspecialchars($ct['full_name']) ?></div>
                                    <div style="font-size:11px; color:#718096;"><?= htmlspecialchars($ct['contract_code']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color: #2d3748;">Rm <?= htmlspecialchars($ct['room_number']) ?></div>
                                    <div style="font-size:11px; color:#718096;"><?= htmlspecialchars($ct['building_name']) ?></div>
                                </td>
                                <td style="color: #4a5568; font-size: 13px;"><?= fmtDate($ct['start_date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="widget">
            <div class="widget-header">
                <h3><i class="fa-solid fa-file-invoice-dollar"></i> Pending Payments</h3>
                <a href="InvoiceView.php" class="widget-link">Resolve Now</a>
            </div>
            <div class="widget-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Amount Due</th>
                            <th>Due Date / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingInvoices)): ?>
                            <tr><td colspan="3" style="text-align:center; color:#10b981; font-weight:600; padding: 20px;">🎉 Great! No outstanding debts.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingInvoices as $inv): 
                                $isOverdue = $inv['status_code'] === 'overdue' || strtotime($inv['due_date']) < time();
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color: #2d3748;"><?= htmlspecialchars($inv['invoice_code']) ?></div>
                                    <div style="font-size:11px; color:#718096;"><?= htmlspecialchars($inv['full_name']) ?> (Rm <?= htmlspecialchars($inv['room_number']) ?>)</div>
                                </td>
                                <td>
                                    <strong style="color:<?= $isOverdue ? '#e53e3e' : '#4a5568' ?>; font-size: 14px;">
                                        <?= fmtMoney((float)$inv['debt_amount']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-size:12px; margin-bottom:4px; color: #4a5568;"><?= fmtDate($inv['due_date']) ?></div>
                                    <?php if ($isOverdue): ?>
                                        <span style="background:#fee2e2; color:#dc2626; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:bold;">OVERDUE</span>
                                    <?php else: ?>
                                        <span style="background:#fef9c3; color:#ca8a04; padding:3px 8px; border-radius:12px; font-size:10px; font-weight:bold;">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

</body>
</html>