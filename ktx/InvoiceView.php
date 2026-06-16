<?php
// ============================================================
//  InvoiceView.php — Danh sách hóa đơn tiền phòng / điện / nước
//  Kết nối bảng: invoices, contracts, students, rooms, room_types, buildings
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red">Kết nối DB thất bại: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ---------- XỬ LÝ XÓA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    if ($delId) $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ?")->execute([$delId]);
    header('Location: InvoiceView.php'); exit;
}

// ---------- THÁNG CÓ DỮ LIỆU (dropdown filter) ----------
$months = $pdo->query("SELECT DISTINCT billing_month FROM invoices ORDER BY billing_month DESC")->fetchAll(PDO::FETCH_COLUMN);

// ---------- BỘ LỌC ----------
$search       = trim($_GET['search']        ?? '');
$filterMonth  = trim($_GET['billing_month'] ?? '');
$filterStatus = trim($_GET['status']        ?? '');

// ---------- QUERY ----------
$sql = "
    SELECT
        i.invoice_id, i.invoice_code, i.billing_month,
        i.room_fee,
        i.electricity_fee, i.electricity_kwh, i.electricity_rate,
        i.water_fee,       i.water_m3,        i.water_rate,
        i.service_fee, i.penalty_fee, i.discount,
        i.total_amount, i.paid_amount,
        i.due_date, i.paid_date, i.payment_method, i.status_code,
        s.full_name, s.student_code, s.student_id,
        r.room_number, b.building_name, rt.type_name
    FROM invoices i
    JOIN students   s  ON s.student_id  = i.student_id
    JOIN contracts  c  ON c.contract_id = i.contract_id
    JOIN rooms      r  ON r.room_id     = c.room_id
    JOIN buildings  b  ON b.building_id = r.building_id
    JOIN room_types rt ON rt.type_id    = r.type_id
";

$where  = []; $params = [];
if ($search !== '') {
    $where[]            = "(i.invoice_code LIKE :s1 OR s.full_name LIKE :s2 OR s.student_code LIKE :s3 OR r.room_number LIKE :s4)";
    $like               = '%' . $search . '%';
    $params[':s1']      = $like; $params[':s2'] = $like; $params[':s3'] = $like; $params[':s4'] = $like;
}
if ($filterMonth  !== '') { $where[] = 'i.billing_month = :bm'; $params[':bm'] = $filterMonth; }
if ($filterStatus !== '') { $where[] = 'i.status_code   = :st'; $params[':st'] = $filterStatus; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.billing_month DESC, i.invoice_id DESC';

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$invoices = $stmt->fetchAll();

// ---------- THỐNG KÊ ----------
$total    = count($invoices);
$paid     = count(array_filter($invoices, fn($i) => $i['status_code'] === 'paid'));
$unpaid   = count(array_filter($invoices, fn($i) => $i['status_code'] === 'unpaid'));
$overdue  = count(array_filter($invoices, fn($i) => $i['status_code'] === 'overdue'));
$partial  = count(array_filter($invoices, fn($i) => $i['status_code'] === 'partial'));
$totalAmt = array_sum(array_column($invoices, 'total_amount'));
$paidAmt  = array_sum(array_column($invoices, 'paid_amount'));
$debtAmt  = $totalAmt - $paidAmt;

// ---------- HELPER FUNCTIONS ----------
function ivStatusLabel(string $c): array {
    return match($c) {
        'paid'    => ['Đã thanh toán', 'badge-green'],
        'unpaid'  => ['Chưa thanh toán','badge-yellow'],
        'overdue' => ['Quá hạn',        'badge-red'],
        'partial' => ['Thanh toán một phần','badge-blue'],
        default   => [$c,               'badge-gray'],
    };
}
function payMethod(string $m): string {
    return match($m) {
        'cash'          => '💵 Tiền mặt',
        'bank_transfer' => '🏦 Chuyển khoản',
        'momo'          => '📱 MoMo',
        'vnpay'         => '💳 VNPay',
        'other'         => 'Khác',
        default         => $m,
    };
}
function fmtMoney(float $n): string { return number_format($n, 0, ',', '.') . ' ₫'; }
function fmtDate(?string $d): string {
    if (!$d) return '—'; $t = strtotime($d); return $t ? date('d/m/Y', $t) : $d;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý hóa đơn — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#1d4ed8;--primary-lt:#eff6ff;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;
    --yellow:#ca8a04;--yellow-lt:#fef9c3;--blue:#2563eb;--blue-lt:#dbeafe;
    --orange:#ea580c;--orange-lt:#ffedd5;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:1320px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:6px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}
.page-desc{color:var(--muted);font-size:14px;margin-bottom:24px}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary)}
.stat-card.green{border-color:var(--green)}.stat-card.yellow{border-color:var(--yellow)}
.stat-card.red{border-color:var(--red)}.stat-card.blue{border-color:var(--blue)}.stat-card.orange{border-color:var(--orange)}
.stat-value{font-size:22px;font-weight:700}.stat-sub{font-size:11px;color:var(--muted);margin-top:1px;font-weight:400}
.stat-label{font-size:12px;color:var(--muted);margin-top:4px}

/* TOOLBAR */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;flex:1;min-width:180px;max-width:340px;box-shadow:var(--shadow)}
.search-box input{border:none;outline:none;font-size:14px;width:100%;background:transparent;color:var(--text)}
.search-box span{color:var(--muted)}
.filter-select{padding:8px 32px 8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;-webkit-appearance:none;appearance:none;cursor:pointer;box-shadow:var(--shadow)}
.filter-select:focus{outline:none;border-color:var(--primary)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#1e40af}
.btn-danger{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}.btn-danger:hover{background:#fee2e2}
.btn-edit{background:var(--primary-lt);color:var(--primary);border:1px solid #bfdbfe}.btn-edit:hover{background:#dbeafe}
.btn-sm{padding:5px 11px;font-size:12px;border-radius:6px}
.btn-clear{background:#f1f5f9;color:var(--muted);border:1px solid var(--border)}

/* TABLE */
.table-wrap{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.table-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.table-header h2{font-size:15px;font-weight:600}
.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);white-space:nowrap}
thead th.right{text-align:right}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f8fafc}
td{padding:11px 12px;vertical-align:middle}.td-right{text-align:right}

/* BADGE */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap}
.badge-green{background:var(--green-lt);color:var(--green)}.badge-blue{background:var(--blue-lt);color:var(--blue)}
.badge-yellow{background:var(--yellow-lt);color:var(--yellow)}.badge-red{background:var(--red-lt);color:var(--red)}
.badge-gray{background:#f1f5f9;color:var(--muted)}

/* AVATAR */
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}

/* UTILITY PILLS */
.util-row{display:flex;flex-direction:column;gap:2px}
.util-pill{font-size:10px;color:var(--muted);white-space:nowrap}
.util-amount{font-size:12px;font-weight:600;color:var(--text)}

/* PROGRESS BAR */
.pay-wrap{min-width:100px}
.pay-bar{height:5px;background:#e2e8f0;border-radius:3px;margin-top:4px;overflow:hidden}
.pay-fill{height:100%;border-radius:3px;background:var(--green);transition:width .3s}
.pay-fill.partial{background:var(--blue)}.pay-fill.overdue{background:var(--red)}
.pay-text{font-size:11px;font-weight:600}

/* MONTH BADGE */
.month-pill{display:inline-flex;align-items:center;padding:4px 10px;background:var(--primary-lt);color:var(--primary);border-radius:6px;font-size:12px;font-weight:700;letter-spacing:.3px}

/* EMPTY */
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-icon{font-size:40px;margin-bottom:12px}

/* SUMMARY ROW */
.summary-row{background:#f8fafc!important}
.summary-row td{font-weight:700;font-size:13px;border-top:2px solid var(--border)!important}

@media(max-width:768px){nav{display:none}.hide-mobile{display:none}.stats-row{grid-template-columns:1fr 1fr 1fr}}
@media(max-width:480px){.stats-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php">Tòa nhà</a>
        <a href="StudentListView.php">Sinh viên</a>
        <a href="ContractListView.php">Hợp đồng</a>
        <a href="InvoiceView.php" class="active">Hóa đơn</a>
        <a href="ViolationView.php">Vi phạm</a>
    </nav>
</header>

<main class="page">
    <h1 class="page-title">Quản lý hóa đơn</h1>
    <p class="page-desc">Hóa đơn tiền phòng, điện, nước từng phòng theo tháng — theo dõi thanh toán và công nợ.</p>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Tổng hóa đơn</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $paid ?></div>
            <div class="stat-label">Đã thanh toán</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $unpaid ?></div>
            <div class="stat-label">Chưa thanh toán</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $overdue ?></div>
            <div class="stat-label">Quá hạn</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-value"><?= $partial ?></div>
            <div class="stat-label">Thanh toán một phần</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-value" style="font-size:16px"><?= fmtMoney($debtAmt) ?></div>
            <div class="stat-label">Tổng công nợ</div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET">
        <div class="toolbar">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search"
                       placeholder="Tìm mã HĐ, tên SV, MSSV, số phòng..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <select name="billing_month" class="filter-select" onchange="this.form.submit()">
                <option value="">📅 Tất cả tháng</option>
                <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>
                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">📋 Tất cả trạng thái</option>
                <option value="paid"    <?= $filterStatus==='paid'    ? 'selected':'' ?>>✅ Đã thanh toán</option>
                <option value="unpaid"  <?= $filterStatus==='unpaid'  ? 'selected':'' ?>>⏳ Chưa thanh toán</option>
                <option value="overdue" <?= $filterStatus==='overdue' ? 'selected':'' ?>>🔴 Quá hạn</option>
                <option value="partial" <?= $filterStatus==='partial' ? 'selected':'' ?>>🔵 Một phần</option>
            </select>

            <button type="submit" class="btn btn-primary">Lọc</button>

            <?php if ($search || $filterMonth || $filterStatus): ?>
            <a href="InvoiceView.php" class="btn btn-clear">✕ Xóa lọc</a>
            <?php endif; ?>

            <a href="InvoiceFormView.php" class="btn btn-primary" style="margin-left:auto">＋ Thêm hóa đơn</a>
        </div>
    </form>

    <!-- TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <h2>🧾 Hóa đơn
                <?php if ($filterMonth): ?> · Tháng <?= date('m/Y', strtotime($filterMonth . '-01')) ?><?php endif; ?>
                <?php if ($filterStatus): ?> · <?= ivStatusLabel($filterStatus)[0] ?><?php endif; ?>
                (<?= $total ?>)
            </h2>
            <?php if ($totalAmt > 0): ?>
            <div style="font-size:13px;color:var(--muted)">
                Tổng: <strong style="color:var(--text)"><?= fmtMoney($totalAmt) ?></strong>
                &nbsp;·&nbsp; Đã thu: <strong style="color:var(--green)"><?= fmtMoney($paidAmt) ?></strong>
                &nbsp;·&nbsp; Còn nợ: <strong style="color:var(--red)"><?= fmtMoney($debtAmt) ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Sinh viên</th>
                        <th>Mã hóa đơn</th>
                        <th class="hide-mobile">Tháng</th>
                        <th class="right hide-mobile">Tiền phòng</th>
                        <th class="right hide-mobile">Điện</th>
                        <th class="right hide-mobile">Nước</th>
                        <th class="right hide-mobile">Phí khác</th>
                        <th class="right">Tổng cộng</th>
                        <th class="hide-mobile">Thanh toán</th>
                        <th class="hide-mobile">Hạn TT</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="12">
                        <div class="empty">
                            <div class="empty-icon">🧾</div>
                            <div><?= ($search || $filterMonth || $filterStatus)
                                ? 'Không tìm thấy hóa đơn nào phù hợp với bộ lọc.'
                                : 'Chưa có hóa đơn nào.' ?>
                            </div>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv):
                        [$statusText, $statusClass] = ivStatusLabel($inv['status_code']);
                        $colors   = ['#1d4ed8','#16a34a','#dc2626','#7c3aed','#ca8a04','#0891b2'];
                        $color    = $colors[$inv['student_id'] % count($colors)];
                        $initials = mb_substr($inv['full_name'], 0, 1, 'UTF-8');
                        $pct      = $inv['total_amount'] > 0 ? min(100, round($inv['paid_amount'] / $inv['total_amount'] * 100)) : 0;
                        $remaining= $inv['total_amount'] - $inv['paid_amount'];
                        $isOverdue= $inv['status_code'] === 'overdue';
                        $otherFee = $inv['service_fee'] + $inv['penalty_fee'] - $inv['discount'];
                    ?>
                    <tr>
                        <!-- Sinh viên -->
                        <td>
                            <div style="display:flex;align-items:center;gap:9px">
                                <div class="avatar" style="background:<?= $color ?>"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($inv['full_name']) ?></div>
                                    <div style="font-size:10px;color:var(--muted)"><?= htmlspecialchars($inv['student_code']) ?></div>
                                    <div style="font-size:10px;color:var(--muted)">P.<?= htmlspecialchars($inv['room_number']) ?> · <?= htmlspecialchars($inv['building_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <!-- Mã HĐ -->
                        <td>
                            <code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px;white-space:nowrap">
                                <?= htmlspecialchars($inv['invoice_code']) ?>
                            </code>
                        </td>
                        <!-- Tháng -->
                        <td class="hide-mobile">
                            <span class="month-pill"><?= date('m/Y', strtotime($inv['billing_month'] . '-01')) ?></span>
                        </td>
                        <!-- Tiền phòng -->
                        <td class="td-right hide-mobile">
                            <div class="util-amount"><?= fmtMoney((float)$inv['room_fee']) ?></div>
                        </td>
                        <!-- Điện -->
                        <td class="td-right hide-mobile">
                            <div class="util-row">
                                <div class="util-amount">🔌 <?= fmtMoney((float)$inv['electricity_fee']) ?></div>
                                <div class="util-pill"><?= number_format((float)$inv['electricity_kwh'],1,',','.') ?> kWh × <?= fmtMoney((float)$inv['electricity_rate']) ?></div>
                            </div>
                        </td>
                        <!-- Nước -->
                        <td class="td-right hide-mobile">
                            <div class="util-row">
                                <div class="util-amount">💧 <?= fmtMoney((float)$inv['water_fee']) ?></div>
                                <div class="util-pill"><?= number_format((float)$inv['water_m3'],1,',','.') ?> m³ × <?= fmtMoney((float)$inv['water_rate']) ?></div>
                            </div>
                        </td>
                        <!-- Phí khác (service + penalty - discount) -->
                        <td class="td-right hide-mobile">
                            <div class="util-row">
                                <?php if ($inv['service_fee'] > 0): ?>
                                <div class="util-pill">DV: <?= fmtMoney((float)$inv['service_fee']) ?></div>
                                <?php endif; ?>
                                <?php if ($inv['penalty_fee'] > 0): ?>
                                <div class="util-pill" style="color:var(--red)">Phạt: <?= fmtMoney((float)$inv['penalty_fee']) ?></div>
                                <?php endif; ?>
                                <?php if ($inv['discount'] > 0): ?>
                                <div class="util-pill" style="color:var(--green)">Giảm: -<?= fmtMoney((float)$inv['discount']) ?></div>
                                <?php endif; ?>
                                <?php if ($otherFee == 0): ?>
                                <div class="util-pill">—</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!-- Tổng -->
                        <td class="td-right">
                            <div style="font-weight:700;font-size:14px;white-space:nowrap"><?= fmtMoney((float)$inv['total_amount']) ?></div>
                            <?php if ($remaining > 0): ?>
                            <div style="font-size:10px;color:var(--red);white-space:nowrap">Còn: <?= fmtMoney($remaining) ?></div>
                            <?php endif; ?>
                        </td>
                        <!-- Thanh toán -->
                        <td class="hide-mobile">
                            <div class="pay-wrap">
                                <div class="pay-text" style="color:<?= $pct==100 ? 'var(--green)' : ($isOverdue ? 'var(--red)' : 'var(--muted)') ?>">
                                    <?= $pct ?>%
                                    <?php if ($inv['paid_date']): ?>
                                    <span style="font-weight:400;font-size:10px"> · <?= fmtDate($inv['paid_date']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="pay-bar">
                                    <div class="pay-fill <?= $isOverdue ? 'overdue' : ($pct < 100 ? 'partial' : '') ?>"
                                         style="width:<?= $pct ?>%"></div>
                                </div>
                                <?php if ($inv['payment_method']): ?>
                                <div style="font-size:10px;color:var(--muted);margin-top:3px"><?= payMethod($inv['payment_method']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!-- Hạn TT -->
                        <td class="hide-mobile">
                            <div style="font-size:12px;white-space:nowrap;<?= $isOverdue ? 'color:var(--red);font-weight:600' : '' ?>">
                                <?= $isOverdue ? '⚠ ' : '' ?><?= fmtDate($inv['due_date']) ?>
                            </div>
                        </td>
                        <!-- Trạng thái -->
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <!-- Thao tác -->
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="InvoiceFormView.php?invoice_id=<?= $inv['invoice_id'] ?>"
                                   class="btn btn-edit btn-sm">✏ Sửa</a>
                                <form method="POST"
                                      onsubmit="return confirm('Xóa hóa đơn <?= htmlspecialchars(addslashes($inv['invoice_code'])) ?>?')">
                                    <input type="hidden" name="action"     value="delete">
                                    <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑 Xóa</button>
                                </form>
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
</body>
</html>
PHPEOF
echo "InvoiceView done"