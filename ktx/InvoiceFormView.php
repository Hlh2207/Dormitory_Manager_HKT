<?php
// ============================================================
//  InvoiceFormView.php — Tạo mới / Chỉnh sửa Hóa đơn
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

// Lấy danh sách hợp đồng (active) để lập hóa đơn
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isEdit ? 'Sửa hóa đơn' : 'Lập hóa đơn' ?> — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#0ea5a4;--primary-lt:#e0f2f1;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--red:#dc2626;--radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:860px;margin:0 auto;padding:28px 20px}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:20px}
.breadcrumb a{color:var(--primary);text-decoration:none}.breadcrumb a:hover{text-decoration:underline}

.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-header h2{font-size:15px;font-weight:700}.card-header .icon{font-size:18px}
.card-body{padding:24px}
.form-grid{display:grid;gap:20px}
.form-grid-2{grid-template-columns:1fr 1fr}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:13px;font-weight:600}
.form-control{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;color:var(--text);background:#fff;outline:none;transition:border-color .15s}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,164,.1)}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
.pfx-wrap{position:relative}.pfx-wrap .pfx{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;font-weight:600;pointer-events:none}
.pfx-wrap .form-control{padding-left:48px}

.total-banner{background:var(--primary-lt);border:1px solid #b2dfdb;border-radius:8px;padding:16px;text-align:right;font-size:16px;color:var(--text);margin-top:20px}
.total-banner strong{font-size:24px;color:var(--primary);margin-left:10px}

.form-actions{display:flex;gap:12px;align-items:center;padding-top:10px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:11px 28px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#0b8483}
.btn-ghost{background:#f1f5f9;color:var(--muted);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border)}
@media(max-width:640px){nav{display:none}.form-grid-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'BuildingListView.php' || basename($_SERVER['PHP_SELF']) == 'RoomDetailView.php' ? 'active' : '' ?>">Tòa nhà</a>
        <a href="StudentListView.php"  class="<?= basename($_SERVER['PHP_SELF']) == 'StudentListView.php' || basename($_SERVER['PHP_SELF']) == 'StudentFormView.php' ? 'active' : '' ?>">Sinh viên</a>
        <a href="ContractListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ContractListView.php' || basename($_SERVER['PHP_SELF']) == 'ContractFormView.php' ? 'active' : '' ?>">Hợp đồng</a>
        <a href="InvoiceView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'InvoiceView.php' ? 'active' : '' ?>">Hóa đơn</a>
        <a href="#">Vi phạm</a>
    </nav>
</header>

<main class="page">
    <div class="breadcrumb">
        <a href="InvoiceView.php">🧾 Hóa đơn</a>
        <span>›</span>
        <span><?= $isEdit ? 'Chỉnh sửa' : 'Lập mới' ?></span>
    </div>

    <h1 style="font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:6px;">
        <span style="display:block;width:4px;height:28px;background:var(--primary);border-radius:2px;"></span>
        <?= $isEdit ? '✏ Sửa hóa đơn' : '🧾 Lập hóa đơn' ?>
    </h1>
    <p style="color:var(--muted);font-size:14px;margin-bottom:24px;">Hóa đơn gộp tiền phòng, điện, nước và các dịch vụ hàng tháng.</p>

    <form method="POST">
        <div class="card">
            <div class="card-header"><span class="icon">📋</span><h2>Thông tin cơ bản</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Chọn Hợp đồng / Phòng</label>
                        <select name="contract_id" id="contract_id" class="form-control" required onchange="fillRoomFee()">
                            <option value="">— Chọn —</option>
                            <?php foreach($contracts as $c): ?>
                            <option value="<?= $c['contract_id'] ?>" data-fee="<?= $c['monthly_fee_snapshot'] ?>" <?= $fContractId == $c['contract_id'] ? 'selected' : '' ?>>
                                P.<?= $c['room_number'] ?> — <?= $c['full_name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mã hóa đơn</label>
                        <input type="text" name="invoice_code" class="form-control" value="<?= htmlspecialchars($fCode) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tháng xuất HĐ (YYYY-MM)</label>
                        <input type="month" name="billing_month" class="form-control" value="<?= $fMonth ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hạn thanh toán</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $fDueDate ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="icon">💵</span><h2>Chi tiết khoản thu</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Tiền phòng (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" id="room_fee" name="room_fee" class="form-control calc" value="<?= $fRoomFee ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dịch vụ khác / Vệ sinh (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="service_fee" class="form-control calc" value="<?= $fService ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số điện tiêu thụ (kWh)</label>
                        <input type="number" name="electricity_kwh" class="form-control calc" value="<?= $fElecKwh ?>" step="0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đơn giá điện (VND/kWh)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="electricity_rate" class="form-control calc" value="<?= $fElecRate ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số nước tiêu thụ (m³)</label>
                        <input type="number" name="water_m3" class="form-control calc" value="<?= $fWaterM3 ?>" step="0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đơn giá nước (VND/m³)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="water_rate" class="form-control calc" value="<?= $fWaterRate ?>"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="color:var(--red)">Phạt vi phạm (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="penalty_fee" class="form-control calc" value="<?= $fPenalty ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:var(--green)">Giảm trừ (VND)</label>
                        <div class="pfx-wrap"><span class="pfx">VND</span><input type="number" name="discount" class="form-control calc" value="<?= $fDiscount ?>"></div>
                    </div>
                </div>

                <div class="total-banner">
                    Dự kiến thu: <strong id="total_display">0 VND</strong>
                </div>

                <div class="form-group" style="margin-top:20px;max-width:300px">
                    <label class="form-label">Trạng thái thanh toán ban đầu</label>
                    <select name="status_code" class="form-control">
                        <option value="unpaid" <?= $fStatus=='unpaid'?'selected':'' ?>>⏳ Chưa thanh toán</option>
                        <option value="paid" <?= $fStatus=='paid'?'selected':'' ?>>✅ Đã thanh toán</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '💾 Lưu hóa đơn' : '✅ Phát hành hóa đơn' ?></button>
            <a href="InvoiceView.php" class="btn btn-ghost">✕ Hủy</a>
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