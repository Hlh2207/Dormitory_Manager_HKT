<?php
// ============================================================
//  LogicTestingDemo.php — Test Task 3, 4, 5
// ============================================================
require_once __DIR__ . '/ContractController.php';
require_once __DIR__ . '/InvoiceController.php';

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die('DB Connection Failed.'); }

$contractController = new ContractController($pdo);
$invoiceController  = new InvoiceController($pdo);
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // --- TEST TASK 3: DUYỆT ĐƠN ---
        case 'approve_reg':
            $regId = (int)$_POST['reg_id'];
            $start = $_POST['start_date'];
            $end   = $_POST['end_date'];
            $dep   = (float)$_POST['deposit'];
            $message = $contractController->approveRegistration($regId, $start, $end, $dep);
            break;

        // --- TEST TASK 3: QUÉT HẾT HẠN ---
        case 'scan_expired':
            $message = $contractController->processExpiredContracts();
            break;

        // --- TEST TASK 4: TẠO HÓA ĐƠN TỪ CHỈ SỐ ---
        case 'gen_invoice':
            $readId = (int)$_POST['reading_id'];
            $due    = $_POST['due_date'];
            $message = $invoiceController->generateInvoiceFromReading($readId, 3500, 25000, 50000, $due);
            break;

        // --- TEST TASK 4: THANH TOÁN ---
        case 'pay_invoice':
            $invId = (int)$_POST['invoice_id'];
            $message = $invoiceController->markAsPaid($invId);
            break;
    }
}

$pageTitle = "Backend Logic Testing (Tasks 3, 4, 5)";
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="DashboardView.php">Dashboard</a> <span>›</span> <span>Logic Testing Area</span>
    </div>

    <h1 class="page-title">Test Backend Logic (Controllers)</h1>
    <p class="page-desc">Simulate backend processes for Contract Approvals, Expirations, and Utility Invoicing with SQL Transactions.</p>

    <?php if ($message): ?>
    <div class="alert <?= $message['success'] ? 'alert-success' : 'alert-error' ?>">
        <?= $message['success'] ? '✅' : '❌' ?> <?= htmlspecialchars($message['message']) ?>
    </div>
    <?php endif; ?>

    <div class="form-grid form-grid-2">
        
        <div class="card">
            <div class="card-header"><h2>Task 3: Approve Registration to Contract</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="approve_reg">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Registration ID (Pending)</label>
                        <input type="number" name="reg_id" class="form-control" required placeholder="e.g. 1">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Deposit Amount (VND)</label>
                        <input type="number" name="deposit" class="form-control" value="1000000">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">Run Approval Transaction</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Task 4: Generate Utility Invoice</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="gen_invoice">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Utility Reading ID (Uninvoiced)</label>
                        <input type="number" name="reading_id" class="form-control" required placeholder="e.g. 1">
                        <div class="form-hint">Make sure room has an active contract.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">Calculate & Generate Invoice</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Task 4: Mark Invoice as Paid</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="pay_invoice">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Invoice ID (Unpaid)</label>
                        <input type="number" name="invoice_id" class="form-control" required placeholder="e.g. 1">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">Confirm Payment</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Task 3: Cronjob - Expired Contracts</h2></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="scan_expired">
                    <p style="font-size:14px; color:var(--muted); margin-bottom:20px; line-height:1.6">
                        Simulates a nightly cronjob. It scans the <code>contracts</code> table. If any contract's <code>end_date</code> is in the past, it changes status to Expired and frees up the room bed.
                    </p>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; background:var(--yellow); color:#fff">Run Expiration Scanner</button>
                </form>
            </div>
        </div>

    </div>
</main>
</body>
</html>