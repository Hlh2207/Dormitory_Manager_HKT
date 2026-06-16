<?php
// ============================================================
//  ContractListView.php — Danh sách hợp đồng thuê phòng
//  Kết nối bảng: contracts, students, rooms, room_types, buildings
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);
    if ($delId) {
        $pdo->prepare("DELETE FROM contracts WHERE contract_id = ?")->execute([$delId]);
    }
    header('Location: ContractListView.php');
    exit;
}

// ---------- THÔNG BÁO TẠO MỚI THÀNH CÔNG ----------
$justCreated = isset($_GET['created']) && $_GET['created'] === '1';

// ---------- TÌM KIẾM ----------
$search = trim($_GET['search'] ?? '');

// ---------- QUERY DANH SÁCH ----------
$sql = "
    SELECT
        c.contract_id,
        c.contract_code,
        c.start_date,
        c.end_date,
        c.monthly_fee_snapshot,
        c.deposit_amount,
        c.deposit_paid,
        c.status_code,
        c.signed_date,
        s.full_name,
        s.student_code,
        s.student_id,
        r.room_number,
        rt.type_name,
        b.building_name
    FROM contracts c
    JOIN students   s  ON s.student_id  = c.student_id
    JOIN rooms      r  ON r.room_id     = c.room_id
    JOIN room_types rt ON rt.type_id    = r.type_id
    JOIN buildings  b  ON b.building_id = r.building_id
";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.contract_code LIKE :s1
               OR s.full_name     LIKE :s2
               OR s.student_code  LIKE :s3
               OR r.room_number   LIKE :s4
               OR b.building_name LIKE :s5";
    $like = '%' . $search . '%';
    $params = [':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like,':s5'=>$like];
}
$sql .= " ORDER BY c.contract_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// ---------- THỐNG KÊ ----------
$total      = count($contracts);
$active     = count(array_filter($contracts, fn($c) => $c['status_code'] === 'active'));
$draft      = count(array_filter($contracts, fn($c) => $c['status_code'] === 'draft'));
$expired    = count(array_filter($contracts, fn($c) => $c['status_code'] === 'expired'));
$terminated = count(array_filter($contracts, fn($c) => $c['status_code'] === 'terminated'));

function statusLabel(string $c): array {
    return match($c) {
        'active'     => ['Đang hiệu lực', 'badge-green'],
        'draft'      => ['Nháp',           'badge-yellow'],
        'expired'    => ['Hết hạn',        'badge-blue'],
        'terminated' => ['Đã chấm dứt',   'badge-red'],
        default      => [$c,              'badge-gray'],
    };
}
function fmtMoney(float $n): string {
    return number_format($n, 0, ',', '.') . ' ₫';
}
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý hợp đồng — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#1d4ed8;--primary-lt:#eff6ff;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;
    --red:#dc2626;--red-lt:#fee2e2;
    --yellow:#ca8a04;--yellow-lt:#fef9c3;
    --blue:#2563eb;--blue-lt:#dbeafe;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:1280px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:6px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}
.page-desc{color:var(--muted);font-size:14px;margin-bottom:24px}
/* ALERT */
.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:20px}
.alert-success{background:var(--green-lt);color:var(--green);border:1px solid #86efac}
/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary)}
.stat-card.green{border-color:var(--green)}.stat-card.yellow{border-color:var(--yellow)}
.stat-card.blue{border-color:var(--blue)}.stat-card.red{border-color:var(--red)}
.stat-value{font-size:26px;font-weight:700}.stat-label{font-size:12px;color:var(--muted);margin-top:3px}
/* TOOLBAR */
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;flex:1;min-width:200px;max-width:400px;box-shadow:var(--shadow)}
.search-box input{border:none;outline:none;font-size:14px;width:100%;background:transparent;color:var(--text)}
.search-box span{color:var(--muted)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#1e40af}
.btn-danger{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}.btn-danger:hover{background:#fee2e2}
.btn-edit{background:var(--primary-lt);color:var(--primary);border:1px solid #bfdbfe}.btn-edit:hover{background:#dbeafe}
.btn-sm{padding:5px 11px;font-size:12px;border-radius:6px}
/* TABLE */
.table-wrap{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.table-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.table-header h2{font-size:15px;font-weight:600}
.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:14px}
thead th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f8fafc}
td{padding:12px 14px;vertical-align:middle}
/* BADGE */
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-green{background:var(--green-lt);color:var(--green)}
.badge-blue{background:var(--blue-lt);color:var(--blue)}
.badge-yellow{background:var(--yellow-lt);color:var(--yellow)}
.badge-red{background:var(--red-lt);color:var(--red)}
.badge-gray{background:#f1f5f9;color:var(--muted)}
/* AVATAR */
.avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}
/* EMPTY */
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-icon{font-size:40px;margin-bottom:12px}
/* DEPOSIT CHECK */
.dep-yes{color:var(--green);font-weight:700}
.dep-no{color:var(--muted)}
/* RESPONSIVE */
@media(max-width:768px){nav{display:none}.hide-mobile{display:none}}
@media(max-width:480px){.stats-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php">Tòa nhà</a>
        <a href="StudentListView.php">Sinh viên</a>
        <a href="ContractListView.php" class="active">Hợp đồng</a>
        <a href="#">Hóa đơn</a>
    </nav>
</header>

<main class="page">
    <h1 class="page-title">Quản lý hợp đồng</h1>
    <p class="page-desc">Danh sách hợp đồng thuê phòng ký túc xá — thêm, sửa, xóa hợp đồng.</p>

    <?php if ($justCreated): ?>
    <div class="alert alert-success">✔ Tạo hợp đồng mới thành công!</div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Tổng hợp đồng</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $active ?></div>
            <div class="stat-label">Đang hiệu lực</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $draft ?></div>
            <div class="stat-label">Nháp</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-value"><?= $expired ?></div>
            <div class="stat-label">Hết hạn</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $terminated ?></div>
            <div class="stat-label">Đã chấm dứt</div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <form method="GET" style="display:contents">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search"
                       placeholder="Tìm theo mã HĐ, tên SV, MSSV, phòng, tòa nhà..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Tìm</button>
            <?php if ($search): ?>
            <a href="ContractListView.php" class="btn" style="background:#f1f5f9;color:var(--muted)">✕ Xóa lọc</a>
            <?php endif; ?>
        </form>
        <a href="ContractFormView.php" class="btn btn-primary" style="margin-left:auto">＋ Thêm hợp đồng</a>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <h2>📄 Hợp đồng
                <?= $search ? '(kết quả: "' . htmlspecialchars($search) . '")' : '' ?>
                (<?= $total ?>)
            </h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Sinh viên</th>
                        <th>Mã hợp đồng</th>
                        <th class="hide-mobile">Phòng / Tòa nhà</th>
                        <th class="hide-mobile">Thời hạn</th>
                        <th class="hide-mobile">Tiền thuê</th>
                        <th class="hide-mobile">Đặt cọc</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($contracts)): ?>
                    <tr><td colspan="8">
                        <div class="empty">
                            <div class="empty-icon">📄</div>
                            <div><?= $search
                                ? 'Không tìm thấy hợp đồng nào khớp với "' . htmlspecialchars($search) . '"'
                                : 'Chưa có hợp đồng nào.' ?></div>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($contracts as $ct):
                        [$statusText, $statusClass] = statusLabel($ct['status_code']);
                        $initials = mb_substr($ct['full_name'], 0, 1, 'UTF-8');
                        $colors   = ['#1d4ed8','#16a34a','#dc2626','#7c3aed','#ca8a04','#0891b2'];
                        $color    = $colors[$ct['student_id'] % count($colors)];
                    ?>
                    <tr>
                        <!-- Sinh viên -->
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:<?= $color ?>"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($ct['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($ct['student_code']) ?></div>
                                </div>
                            </div>
                        </td>
                        <!-- Mã HĐ -->
                        <td>
                            <code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;white-space:nowrap">
                                <?= htmlspecialchars($ct['contract_code']) ?>
                            </code>
                            <?php if ($ct['signed_date']): ?>
                            <div style="font-size:11px;color:var(--muted);margin-top:3px">Ký: <?= fmtDate($ct['signed_date']) ?></div>
                            <?php endif; ?>
                        </td>
                        <!-- Phòng -->
                        <td class="hide-mobile">
                            <div style="font-weight:600">Phòng <?= htmlspecialchars($ct['room_number']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($ct['building_name']) ?> · <?= htmlspecialchars($ct['type_name']) ?></div>
                        </td>
                        <!-- Thời hạn -->
                        <td class="hide-mobile">
                            <div style="font-size:13px;white-space:nowrap"><?= fmtDate($ct['start_date']) ?></div>
                            <div style="font-size:11px;color:var(--muted)">→ <?= fmtDate($ct['end_date']) ?></div>
                        </td>
                        <!-- Tiền thuê -->
                        <td class="hide-mobile">
                            <div style="font-size:13px;font-weight:600;white-space:nowrap">
                                <?= fmtMoney((float)$ct['monthly_fee_snapshot']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--muted)">/tháng</div>
                        </td>
                        <!-- Đặt cọc -->
                        <td class="hide-mobile">
                            <div style="font-size:13px;white-space:nowrap"><?= fmtMoney((float)$ct['deposit_amount']) ?></div>
                            <div style="font-size:11px;margin-top:2px">
                                <?php if ($ct['deposit_paid']): ?>
                                <span class="dep-yes">✔ Đã thu</span>
                                <?php else: ?>
                                <span class="dep-no">— Chưa thu</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!-- Trạng thái -->
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <!-- Thao tác -->
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="ContractFormView.php?contract_id=<?= $ct['contract_id'] ?>"
                                   class="btn btn-edit btn-sm">✏ Sửa</a>
                                <form method="POST"
                                      onsubmit="return confirm('Xóa hợp đồng <?= htmlspecialchars(addslashes($ct['contract_code'])) ?> của <?= htmlspecialchars(addslashes($ct['full_name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contract_id" value="<?= $ct['contract_id'] ?>">
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
