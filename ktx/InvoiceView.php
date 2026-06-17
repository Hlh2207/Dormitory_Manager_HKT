<?php
// ============================================================
//  InvoiceView.php — Invoice List
// ============================================================
$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die('DB Error'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    if ($delId) $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ?")->execute([$delId]);
    header('Location: InvoiceView.php'); exit;
}

$months = $pdo->query("SELECT DISTINCT billing_month FROM invoices ORDER BY billing_month DESC")->fetchAll(PDO::FETCH_COLUMN);

$search       = trim($_GET['search'] ?? '');
$filterMonth  = trim($_GET['billing_month'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "SELECT i.*, s.full_name, s.student_code, s.student_id, r.room_number, b.building_name 
        FROM invoices i JOIN students s ON s.student_id = i.student_id 
        JOIN contracts c ON c.contract_id = i.contract_id 
        JOIN rooms r ON r.room_id = c.room_id JOIN buildings b ON b.building_id = r.building_id";
$where = []; $params = [];
if ($search) { $where[] = "(i.invoice_code LIKE :s OR s.full_name LIKE :s OR r.room_number LIKE :s)"; $params[':s'] = "%$search%"; }
if ($filterMonth) { $where[] = "i.billing_month = :m"; $params[':m'] = $filterMonth; }
if ($filterStatus) { $where[] = "i.status_code = :st"; $params[':st'] = $filterStatus; }
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY i.billing_month DESC, i.invoice_id DESC";

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$invoices = $stmt->fetchAll();

$total    = count($invoices);
$paid     = count(array_filter($invoices, fn($i) => $i['status_code'] === 'paid'));
$unpaid   = count(array_filter($invoices, fn($i) => $i['status_code'] === 'unpaid'));
$totalAmt = array_sum(array_column($invoices, 'total_amount'));

function fmtMoney($n) { return number_format((float)$n, 0, ',', '.') . ' VND'; }

$pageTitle = "Invoice Management";
include 'header.php';
?>

<main class="page">
    <h1 class="page-title">Invoice Management</h1>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div><div class="stat-label">Total Invoices</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $paid ?></div><div class="stat-label">Paid</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $unpaid ?></div><div class="stat-label">Unpaid</div>
        </div>
        <div class="stat-card" style="border-color:var(--primary-dk)">
            <div class="stat-value" style="font-size:18px"><?= fmtMoney($totalAmt) ?></div><div class="stat-label">Total Filtered Amount</div>
        </div>
    </div>

    <form method="GET">
        <div class="toolbar">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search" placeholder="Search Invoice No, Name, Room..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="billing_month" class="filter-select" onchange="this.form.submit()">
                <option value="">📅 All Months</option>
                <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>Month <?= date('m/Y', strtotime($m . '-01')) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">📋 All Status</option>
                <option value="paid"   <?= $filterStatus==='paid'?'selected':'' ?>>Paid</option>
                <option value="unpaid" <?= $filterStatus==='unpaid'?'selected':'' ?>>Unpaid</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="InvoiceFormView.php" class="btn btn-primary" style="margin-left:auto">＋ New Invoice</a>
        </div>
    </form>

    <div class="table-wrap">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student / Room</th>
                        <th>Invoice Code</th>
                        <th>Billing Cycle</th>
                        <th>Total Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No data available.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        $statusClass = $inv['status_code'] === 'paid' ? 'badge-green' : 'badge-yellow';
                        $statusText  = $inv['status_code'] === 'paid' ? 'Paid' : 'Unpaid';
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($inv['full_name']) ?></div>
                            <div style="font-size:12px;color:var(--muted)">Room <?= htmlspecialchars($inv['room_number']) ?> · <?= htmlspecialchars($inv['building_name']) ?></div>
                        </td>
                        <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($inv['invoice_code']) ?></code></td>
                        <td><?= date('m/Y', strtotime($inv['billing_month'] . '-01')) ?></td>
                        <td><strong style="color:var(--text);font-size:15px"><?= fmtMoney($inv['total_amount']) ?></strong></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-view btn-sm" onclick="showModal(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES) ?>)">👁 View</button>
                                <a href="InvoiceFormView.php?invoice_id=<?= $inv['invoice_id'] ?>" class="btn btn-edit btn-sm">✏ Edit</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="m-title">Invoice Details</div>
            <button class="modal-close" onclick="closeModalDirect()">✕</button>
        </div>
        <div id="m-body"></div>
    </div>
</div>

<script>
function showModal(data) {
    document.getElementById('m-title').textContent = 'Invoice: ' + data.invoice_code;
    const fm = (val) => Number(val).toLocaleString('vi-VN') + ' VND';
    const html = `
        <div class="info-row"><span class="info-key">Room Fee:</span> <span class="info-val">${fm(data.room_fee)}</span></div>
        <div class="info-row"><span class="info-key">Electricity (${data.electricity_kwh} kWh):</span> <span class="info-val">${fm(data.electricity_fee)}</span></div>
        <div class="info-row"><span class="info-key">Water (${data.water_m3} m³):</span> <span class="info-val">${fm(data.water_fee)}</span></div>
        <div class="info-row"><span class="info-key">Services:</span> <span class="info-val">${fm(data.service_fee)}</span></div>
        <div class="info-row"><span class="info-key">Penalty:</span> <span class="info-val" style="color:#A31D1D">${fm(data.penalty_fee)}</span></div>
        <div class="info-row"><span class="info-key">Discount:</span> <span class="info-val" style="color:#2E5A2E">-${fm(data.discount)}</span></div>
        <div class="info-row" style="margin-top:10px;border:none;font-size:18px;color:var(--primary-dk)">
            <span class="info-key" style="color:var(--primary-dk)">Total Due:</span> 
            <strong class="info-val">${fm(data.total_amount)}</strong>
        </div>
    `;
    document.getElementById('m-body').innerHTML = html;
    document.getElementById('modal-overlay').classList.add('open');
}
function closeModal(e) { if (e.target.id === 'modal-overlay') closeModalDirect(); }
function closeModalDirect() { document.getElementById('modal-overlay').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModalDirect(); });
</script>
</body>
</html>