<?php
// ============================================================
//  InvoiceView.php — Danh sách hóa đơn (Thu gọn + Modal)
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý hóa đơn — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#0ea5a4;--primary-lt:#e0f2f1;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;
    --red:#dc2626;--red-lt:#fee2e2;
    --yellow:#ca8a04;--yellow-lt:#fef9c3;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:1200px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:24px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary)}
.stat-card.green{border-color:var(--green)}.stat-card.yellow{border-color:var(--yellow)}
.stat-value{font-size:22px;font-weight:700}.stat-label{font-size:12px;color:var(--muted);margin-top:4px}

/* Toolbar */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;flex:1;min-width:180px;max-width:340px;box-shadow:var(--shadow)}
.search-box input{border:none;outline:none;font-size:14px;width:100%;background:transparent;color:var(--text)}
.filter-select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;outline:none;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#0b8483}
.btn-edit{background:var(--primary-lt);color:var(--primary);border:1px solid #b2dfdb}.btn-edit:hover{background:#b2dfdb}
.btn-view{background:#dbeafe;color:#2563eb;border:1px solid #bfdbfe}.btn-view:hover{background:#bfdbfe}
.btn-sm{padding:5px 11px;font-size:12px;border-radius:6px}

/* Table */
.table-wrap{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.table-responsive{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:14px}
thead th{background:#f8fafc;padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border)}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
tbody tr:hover{background:#f8fafc}
td{padding:12px 16px;vertical-align:middle}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-green{background:var(--green-lt);color:var(--green)}
.badge-yellow{background:var(--yellow-lt);color:var(--yellow)}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--card);border-radius:16px;padding:28px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:modal-in .18s ease;}
@keyframes modal-in{from{transform:scale(.95);opacity:0;}to{transform:scale(1);opacity:1;}}
.modal-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;}
.modal-title{font-size:18px;font-weight:700;color:var(--primary)}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;padding:2px 6px;border-radius:4px;}
.modal-close:hover{background:var(--bg);}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px dashed var(--border);font-size:14px;}
.info-row:last-child{border-bottom:none;}
.info-key{color:var(--muted);} .info-val{font-weight:600;}
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
    <h1 class="page-title">Quản lý hóa đơn</h1>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div><div class="stat-label">Tổng hóa đơn</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $paid ?></div><div class="stat-label">Đã thanh toán</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $unpaid ?></div><div class="stat-label">Chưa thanh toán</div>
        </div>
        <div class="stat-card" style="border-color:var(--primary)">
            <div class="stat-value" style="font-size:18px"><?= fmtMoney($totalAmt) ?></div><div class="stat-label">Tổng tiền (kết quả lọc)</div>
        </div>
    </div>

    <form method="GET">
        <div class="toolbar">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search" placeholder="Tìm mã HĐ, tên SV, số phòng..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="billing_month" class="filter-select" onchange="this.form.submit()">
                <option value="">📅 Tất cả tháng</option>
                <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>Tháng <?= date('m/Y', strtotime($m . '-01')) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">📋 Tất cả trạng thái</option>
                <option value="paid"   <?= $filterStatus==='paid'?'selected':'' ?>>Đã thanh toán</option>
                <option value="unpaid" <?= $filterStatus==='unpaid'?'selected':'' ?>>Chưa thanh toán</option>
            </select>
            <button type="submit" class="btn btn-primary">Lọc</button>
            <a href="InvoiceFormView.php" class="btn btn-primary" style="margin-left:auto">＋ Lập hóa đơn</a>
        </div>
    </form>

    <div class="table-wrap">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>SV / Phòng</th>
                        <th>Mã Hóa Đơn</th>
                        <th>Kỳ (Tháng)</th>
                        <th>Tổng cộng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">Chưa có dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        $statusClass = $inv['status_code'] === 'paid' ? 'badge-green' : 'badge-yellow';
                        $statusText  = $inv['status_code'] === 'paid' ? 'Đã thu' : 'Chưa thu';
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($inv['full_name']) ?></div>
                            <div style="font-size:12px;color:var(--muted)">P.<?= htmlspecialchars($inv['room_number']) ?> · <?= htmlspecialchars($inv['building_name']) ?></div>
                        </td>
                        <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($inv['invoice_code']) ?></code></td>
                        <td><?= date('m/Y', strtotime($inv['billing_month'] . '-01')) ?></td>
                        <td><strong style="color:var(--text);font-size:15px"><?= fmtMoney($inv['total_amount']) ?></strong></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-view btn-sm" onclick="showModal(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES) ?>)">👁 Xem</button>
                                <a href="InvoiceFormView.php?invoice_id=<?= $inv['invoice_id'] ?>" class="btn btn-edit btn-sm">✏ Sửa</a>
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
            <div class="modal-title" id="m-title">Chi tiết hóa đơn</div>
            <button class="modal-close" onclick="closeModalDirect()">✕</button>
        </div>
        <div id="m-body"></div>
    </div>
</div>

<script>
function showModal(data) {
    document.getElementById('m-title').textContent = 'Hóa đơn: ' + data.invoice_code;
    const fm = (val) => Number(val).toLocaleString('vi-VN') + ' VND';
    const html = `
        <div class="info-row"><span class="info-key">Tiền phòng:</span> <span class="info-val">${fm(data.room_fee)}</span></div>
        <div class="info-row"><span class="info-key">Tiền điện (${data.electricity_kwh} kWh):</span> <span class="info-val">${fm(data.electricity_fee)}</span></div>
        <div class="info-row"><span class="info-key">Tiền nước (${data.water_m3} m³):</span> <span class="info-val">${fm(data.water_fee)}</span></div>
        <div class="info-row"><span class="info-key">Dịch vụ khác:</span> <span class="info-val">${fm(data.service_fee)}</span></div>
        <div class="info-row"><span class="info-key">Phạt vi phạm:</span> <span class="info-val" style="color:var(--red)">${fm(data.penalty_fee)}</span></div>
        <div class="info-row"><span class="info-key">Giảm trừ:</span> <span class="info-val" style="color:var(--green)">-${fm(data.discount)}</span></div>
        <div class="info-row" style="margin-top:10px;border:none;font-size:18px;color:var(--primary)">
            <span class="info-key" style="color:var(--primary)">Tổng cộng:</span> 
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