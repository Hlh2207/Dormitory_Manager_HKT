<?php
// ============================================================
//  InvoiceFormView.php — Create / Edit Invoice
// ============================================================
$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try { $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } catch (PDOException $e) { die('DB Error'); }

$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);
$invoice   = null;
$isEdit    = false;

if ($invoiceId) {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    $isEdit  = (bool)$invoice;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contractId   = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);
    $invoiceCode  = trim($_POST['invoice_code']);
    $month        = trim($_POST['billing_month']);
    $roomFee      = (float)$_POST['room_fee'];
    $elecKwh      = (float)$_POST['electricity_kwh'];
    $elecRate     = (float)$_POST['electricity_rate'];
    $waterM3      = (float)$_POST['water_m3'];
    $waterRate    = (float)$_POST['water_rate'];
    $serviceFee   = (float)$_POST['service_fee'];
    $penaltyFee   = (float)$_POST['penalty_fee'];
    $discount     = (float)$_POST['discount'];
    
    // Auto calc
    $elecFee      = $elecKwh * $elecRate;
    $waterFee     = $waterM3 * $waterRate;
    $totalAmount  = $roomFee + $elecFee + $waterFee + $serviceFee + $penaltyFee - $discount;

    $dueDate      = trim($_POST['due_date']);
    $statusCode   = trim($_POST['status_code']);

    if ($isEdit) {
        $sql = "UPDATE invoices SET contract_id=?, invoice_code=?, billing_month=?, room_fee=?, electricity_kwh=?, electricity_rate=?, electricity_fee=?, water_m3=?, water_rate=?, water_fee=?, service_fee=?, penalty_fee=?, discount=?, total_amount=?, due_date=?, status_code=? WHERE invoice_id=?";
        $pdo->prepare($sql)->execute([$contractId, $invoiceCode, $month, $roomFee, $elecKwh, $elecRate, $elecFee, $waterM3, $waterRate, $waterFee, $serviceFee, $penaltyFee, $discount, $totalAmount, $dueDate, $statusCode, $invoiceId]);
    } else {
        $sql = "INSERT INTO invoices (student_id, contract_id, invoice_code, billing_month, room_fee, electricity_kwh, electricity_rate, electricity_fee, water_m3, water_rate, water_fee, service_fee, penalty_fee, discount, total_amount, due_date, status_code) VALUES ((SELECT student_id FROM contracts WHERE contract_id=?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$contractId, $contractId, $invoiceCode, $month, $roomFee, $elecKwh, $elecRate, $elecFee, $waterM3, $waterRate, $waterFee, $serviceFee, $penaltyFee, $discount, $totalAmount, $dueDate, $statusCode]);
    }
    header('Location: InvoiceView.php'); exit;
}

$contracts = $pdo->query("SELECT c.contract_id, c.monthly_fee_snapshot, r.room_number, s.full_name 
                          FROM contracts c JOIN rooms r ON r.room_id = c.room_id JOIN students s ON s.student_id = c.student_id 
                          WHERE c.status_code = 'active' ORDER BY r.room_number")->fetchAll();

$fContractId = $invoice['contract_id'] ?? '';
$fCode       = $invoice['invoice_code'] ?? 'INV-' . date('Ym') . '-';
$fMonth      = $invoice['billing_month'] ?? date('Y-m');
$fRoomFee    = $invoice['room_fee'] ?? 0;
$fElecKwh    = $invoice['electricity_kwh'] ?? 0;
$fElecRate   = $invoice['electricity_rate'] ?? 3500;
$fWaterM3    = $invoice['water_m3'] ?? 0;
$fWaterRate  = $invoice['water_rate'] ?? 25000;
$fService    = $invoice['service_fee'] ?? 0;
$fPenalty    = $invoice['penalty_fee'] ?? 0;
$fDiscount   = $invoice['discount'] ?? 0;
$fDueDate    = $invoice['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
$fStatus     = $invoice['status_code'] ?? 'unpaid';

$pageTitle = $isEdit ? 'Edit Invoice' : 'Create Invoice';
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="InvoiceView.php">🧾 Invoices</a>
        <span>›</span>
        <span><?= $isEdit ? 'Edit' : 'New' ?></span>
    </div>

    <h1 class="page-title">
        <?= $isEdit ? '✏ Edit Invoice' : '🧾 Create Invoice' ?>
    </h1>
    <p class="page-desc">Monthly invoice combining room, electricity, water, and services.</p>

    <form method="POST">
        <div class="card">
            <div class="card-header"><h2>📋 Basic Info</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Select Contract / Room</label>
                        <select name="contract_id" id="contract_id" class="form-control" required onchange="fillRoomFee()">
                            <option value="">— Select —</option>
                            <?php foreach($contracts as $c): ?>
                            <option value="<?= $c['contract_id'] ?>" data-fee="<?= $c['monthly_fee_snapshot'] ?>" <?= $fContractId == $c['contract_id'] ? 'selected' : '' ?>>
                                Room <?= $c['room_number'] ?> — <?= $c['full_name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Code</label>
                        <input type="text" name="invoice_code" class="form-control" value="<?= htmlspecialchars($fCode) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Billing Month (YYYY-MM)</label>
                        <input type="month" name="billing_month" class="form-control" value="<?= $fMonth ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $fDueDate ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>💵 Charges Breakdown</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Room Fee (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" id="room_fee" name="room_fee" class="form-control calc" value="<?= $fRoomFee ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Services / Cleaning (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="service_fee" class="form-control calc" value="<?= $fService ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Electricity Usage (kWh)</label>
                        <input type="number" name="electricity_kwh" class="form-control calc" value="<?= $fElecKwh ?>" step="0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Electricity Rate (VND/kWh)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="electricity_rate" class="form-control calc" value="<?= $fElecRate ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Water Usage (m³)</label>
                        <input type="number" name="water_m3" class="form-control calc" value="<?= $fWaterM3 ?>" step="0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Water Rate (VND/m³)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="water_rate" class="form-control calc" value="<?= $fWaterRate ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="color:#A31D1D">Penalty Fee (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="penalty_fee" class="form-control calc" value="<?= $fPenalty ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#2E5A2E">Discount (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="discount" class="form-control calc" value="<?= $fDiscount ?>"></div>
                    </div>
                </div>

                <div class="total-banner">
                    Total Expected: <strong id="total_display">0 VND</strong>
                </div>

                <div class="form-group" style="margin-top:20px;max-width:300px">
                    <label class="form-label">Initial Payment Status</label>
                    <select name="status_code" class="form-control">
                        <option value="unpaid" <?= $fStatus=='unpaid'?'selected':'' ?>>⏳ Unpaid</option>
                        <option value="paid" <?= $fStatus=='paid'?'selected':'' ?>>✅ Paid</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '💾 Save Invoice' : '✅ Issue Invoice' ?></button>
            <a href="InvoiceView.php" class="btn btn-ghost">✕ Cancel</a>
        </div>
    </form>
</main>

<script>
function fillRoomFee() {
    const sel = document.getElementById('contract_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.fee) {
        document.getElementById('room_fee').value = opt.dataset.fee;
        calculateTotal();
    }
}
function calculateTotal() {
    const getVal = (name) => Number(document.querySelector(`input[name="${name}"]`).value) || 0;
    const room    = getVal('room_fee');
    const elec    = getVal('electricity_kwh') * getVal('electricity_rate');
    const water   = getVal('water_m3') * getVal('water_rate');
    const service = getVal('service_fee');
    const penalty = getVal('penalty_fee');
    const discount= getVal('discount');

    const total = room + elec + water + service + penalty - discount;
    document.getElementById('total_display').textContent = total.toLocaleString('vi-VN') + ' VND';
}
document.querySelectorAll('.calc').forEach(inp => inp.addEventListener('input', calculateTotal));
calculateTotal();
</script>
</body>
</html>