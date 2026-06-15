<?php
// ============================================================
//  StudentListView.php — Danh sách sinh viên
//  Kết nối bảng: students, users
// ============================================================

$host    = 'localhost';
$db      = 'campus_final';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<p style="color:red">Kết nối DB thất bại: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ---------- XỬ LÝ XÓA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    if ($delId) {
        $pdo->prepare("DELETE FROM students WHERE student_id = ?")->execute([$delId]);
    }
    header('Location: StudentListView.php');
    exit;
}

// ---------- TÌM KIẾM ----------
$search = trim($_GET['search'] ?? '');

// ---------- QUERY DANH SÁCH ----------
$sql = "
    SELECT
        s.student_id,
        s.student_code,
        s.full_name,
        s.gender,
        s.id_card,
        s.faculty,
        s.major,
        s.intake_year,
        s.class_name,
        s.status_code,
        u.email,
        u.phone
    FROM students s
    JOIN users u ON u.user_id = s.user_id
";

$params = [];
if ($search !== '') {
    $sql .= " WHERE s.full_name LIKE :s1
               OR s.student_code LIKE :s2
               OR u.email LIKE :s3
               OR u.phone LIKE :s4";
    $like = '%' . $search . '%';
    $params = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
}
$sql .= " ORDER BY s.student_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// ---------- THỐNG KÊ ----------
$total  = count($students);
$male   = count(array_filter($students, fn($s) => $s['gender'] === 'male'));
$female = count(array_filter($students, fn($s) => $s['gender'] === 'female'));

function genderLabel(string $g): string {
    return match($g) { 'male' => 'Nam', 'female' => 'Nữ', default => 'Khác' };
}
function statusLabel(string $c): array {
    return match($c) {
        'active'    => ['Đang học',      'badge-green'],
        'graduated' => ['Đã tốt nghiệp', 'badge-blue'],
        'suspended' => ['Bảo lưu',       'badge-yellow'],
        'expelled'  => ['Bị đuổi',       'badge-red'],
        default     => [$c,              'badge-gray'],
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý sinh viên — KTX Campus</title>
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

/* HEADER */
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}
.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}

/* PAGE */
.page{max-width:1200px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:6px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}
.page-desc{color:var(--muted);font-size:14px;margin-bottom:24px}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary)}
.stat-card.green{border-color:var(--green)}.stat-card.red{border-color:var(--red)}.stat-card.yellow{border-color:var(--yellow)}
.stat-value{font-size:26px;font-weight:700}.stat-label{font-size:12px;color:var(--muted);margin-top:3px}

/* TOOLBAR */
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:8px 14px;flex:1;min-width:200px;max-width:360px;box-shadow:var(--shadow)}
.search-box input{border:none;outline:none;font-size:14px;width:100%;background:transparent;color:var(--text)}
.search-box span{color:var(--muted);font-size:16px}
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

/* BADGES */
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-green{background:var(--green-lt);color:var(--green)}
.badge-blue{background:var(--blue-lt);color:var(--blue)}
.badge-yellow{background:var(--yellow-lt);color:var(--yellow)}
.badge-red{background:var(--red-lt);color:var(--red)}
.badge-gray{background:#f1f5f9;color:var(--muted)}
.badge-male{background:#dbeafe;color:#1d4ed8}
.badge-female{background:#fce7f3;color:#db2777}

/* AVATAR */
.avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}

/* EMPTY */
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-icon{font-size:40px;margin-bottom:12px}

/* RESPONSIVE */
@media(max-width:768px){
    nav{display:none}
    .hide-mobile{display:none}
    .table-responsive{font-size:13px}
    td,thead th{padding:10px 10px}
}
@media(max-width:480px){
    .stats-row{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php">Tòa nhà</a>
        <a href="StudentListView.php" class="active">Sinh viên</a>
        <a href="#">Hóa đơn</a>
    </nav>
</header>

<main class="page">
    <h1 class="page-title">Quản lý sinh viên</h1>
    <p class="page-desc">Danh sách sinh viên đang ở ký túc xá — thêm, sửa, xóa hồ sơ sinh viên.</p>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $total ?></div><div class="stat-label">Tổng sinh viên</div></div>
        <div class="stat-card green"><div class="stat-value"><?= $male ?></div><div class="stat-label">Nam</div></div>
        <div class="stat-card red"><div class="stat-value"><?= $female ?></div><div class="stat-label">Nữ</div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <form method="GET" style="display:contents">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search"
                       placeholder="Tìm theo tên, MSSV, email, SĐT..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Tìm</button>
            <?php if ($search): ?>
            <a href="StudentListView.php" class="btn" style="background:#f1f5f9;color:var(--muted)">✕ Xóa lọc</a>
            <?php endif; ?>
        </form>
        <a href="StudentFormView.php" class="btn btn-primary" style="margin-left:auto">＋ Thêm sinh viên</a>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <h2>👥 Sinh viên <?= $search ? '(kết quả tìm: ' . htmlspecialchars($search) . ')' : '' ?> (<?= $total ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Sinh viên</th>
                        <th>MSSV</th>
                        <th class="hide-mobile">Giới tính</th>
                        <th class="hide-mobile">Khoa / Ngành</th>
                        <th>Email / SĐT</th>
                        <th class="hide-mobile">Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="7">
                        <div class="empty">
                            <div class="empty-icon">🔍</div>
                            <div><?= $search ? 'Không tìm thấy sinh viên nào khớp với "' . htmlspecialchars($search) . '"' : 'Chưa có sinh viên nào.' ?></div>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($students as $sv):
                        [$statusText, $statusClass] = statusLabel($sv['status_code']);
                        $initials = mb_substr($sv['full_name'], 0, 1, 'UTF-8');
                        $colors   = ['#1d4ed8','#16a34a','#dc2626','#7c3aed','#ca8a04','#0891b2'];
                        $color    = $colors[$sv['student_id'] % count($colors)];
                    ?>
                    <tr>
                        <!-- Tên + Avatar -->
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:<?= $color ?>"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($sv['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['class_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <!-- MSSV -->
                        <td><code style="font-size:13px;background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($sv['student_code']) ?></code></td>
                        <!-- Giới tính -->
                        <td class="hide-mobile">
                            <span class="badge <?= $sv['gender'] === 'male' ? 'badge-male' : 'badge-female' ?>">
                                <?= genderLabel($sv['gender']) ?>
                            </span>
                        </td>
                        <!-- Khoa / Ngành -->
                        <td class="hide-mobile">
                            <div style="font-size:13px"><?= htmlspecialchars($sv['faculty']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['major']) ?></div>
                        </td>
                        <!-- Email / SĐT -->
                        <td>
                            <div style="font-size:13px"><?= htmlspecialchars($sv['email']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['phone'] ?? '—') ?></div>
                        </td>
                        <!-- Trạng thái -->
                        <td class="hide-mobile">
                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                        </td>
                        <!-- Thao tác -->
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="StudentFormView.php?student_id=<?= $sv['student_id'] ?>"
                                   class="btn btn-edit btn-sm">✏ Sửa</a>
                                <form method="POST" onsubmit="return confirm('Xóa sinh viên <?= htmlspecialchars(addslashes($sv['full_name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?= $sv['student_id'] ?>">
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
