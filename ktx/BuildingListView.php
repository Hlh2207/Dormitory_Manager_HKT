<?php
// ============================================================
//  BuildingListView.php — Danh sách tòa nhà ký túc xá
//  Kết nối bảng: buildings, rooms (đếm phòng theo trạng thái)
// ============================================================

// ---------- 1. KẾT NỐI DATABASE ----------
$host   = 'localhost';
$db     = 'campus_final';   // đổi tên DB nếu khác
$user   = 'root';
$pass   = '';
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

// ---------- 2. QUERY DỮ LIỆU ----------
// Lấy danh sách tòa nhà + thống kê phòng theo trạng thái
$sql = "
    SELECT
        b.building_id,
        b.building_code,
        b.building_name,
        b.gender_type,
        b.total_floors,
        b.total_rooms,
        b.manager_name,
        b.manager_phone,
        b.description,
        -- Đếm phòng theo từng trạng thái
        COUNT(r.room_id)                                                  AS actual_rooms,
        SUM(CASE WHEN r.status_code = 'available'   THEN 1 ELSE 0 END)   AS rooms_available,
        SUM(CASE WHEN r.status_code = 'full'         THEN 1 ELSE 0 END)   AS rooms_full,
        SUM(CASE WHEN r.status_code = 'maintenance'  THEN 1 ELSE 0 END)   AS rooms_maintenance
    FROM buildings b
    LEFT JOIN rooms r ON r.building_id = b.building_id
    WHERE b.is_active = 1
    GROUP BY b.building_id
    ORDER BY b.building_code
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$buildings = $stmt->fetchAll();

// ---------- 3. HÀM TIỆN ÍCH ----------
function genderLabel(string $type): string {
    return match($type) {
        'male'   => '♂ Nam sinh',
        'female' => '♀ Nữ sinh',
        'mixed'  => '⊕ Hỗn hợp',
        default  => $type,
    };
}

function genderClass(string $type): string {
    return match($type) {
        'male'   => 'badge-blue',
        'female' => 'badge-pink',
        'mixed'  => 'badge-purple',
        default  => 'badge-gray',
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý tòa nhà — KTX Campus</title>
<style>
/* ===== RESET & BASE ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #f0f2f5;
    --card:      #ffffff;
    --primary:   #1d4ed8;
    --primary-lt:#eff6ff;
    --text:      #1e293b;
    --muted:     #64748b;
    --border:    #e2e8f0;
    --green:     #16a34a;
    --green-lt:  #dcfce7;
    --red:       #dc2626;
    --red-lt:    #fee2e2;
    --yellow:    #ca8a04;
    --yellow-lt: #fef9c3;
    --blue:      #2563eb;
    --blue-lt:   #dbeafe;
    --pink:      #db2777;
    --pink-lt:   #fce7f3;
    --purple:    #7c3aed;
    --purple-lt: #ede9fe;
    --radius:    12px;
    --shadow:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* ===== HEADER ===== */
.site-header {
    background: var(--primary);
    color: #fff;
    padding: 0 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    height: 60px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.site-header .logo { font-size: 20px; font-weight: 700; letter-spacing: -.3px; }
.site-header .subtitle { font-size: 13px; opacity: .75; }
.site-header nav { margin-left: auto; display: flex; gap: 4px; }
.site-header nav a {
    color: #fff; text-decoration: none; padding: 6px 14px;
    border-radius: 6px; font-size: 13px; opacity: .8;
    transition: background .15s, opacity .15s;
}
.site-header nav a:hover, .site-header nav a.active {
    background: rgba(255,255,255,.15); opacity: 1;
}

/* ===== PAGE LAYOUT ===== */
.page { max-width: 1200px; margin: 0 auto; padding: 28px 20px; }

.page-title {
    font-size: 22px; font-weight: 700; color: var(--text);
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 6px;
}
.page-title::before {
    content: ''; display: block;
    width: 4px; height: 28px;
    background: var(--primary); border-radius: 2px;
}
.page-desc { color: var(--muted); font-size: 14px; margin-bottom: 24px; }

/* ===== SUMMARY CARDS ===== */
.summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
}
.stat-card.green  { border-color: var(--green); }
.stat-card.red    { border-color: var(--red); }
.stat-card.yellow { border-color: var(--yellow); }

.stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }

/* ===== TABLE ===== */
.table-wrap {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.table-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.table-header h2 { font-size: 15px; font-weight: 600; }

.table-responsive { overflow-x: auto; }

table {
    width: 100%; border-collapse: collapse; font-size: 14px;
}
thead th {
    background: #f8fafc; padding: 11px 16px;
    text-align: left; font-size: 12px; font-weight: 600;
    color: var(--muted); text-transform: uppercase;
    letter-spacing: .5px; border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .12s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
td { padding: 13px 16px; vertical-align: middle; }

/* ===== BADGES ===== */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-blue   { background: var(--blue-lt);   color: var(--blue); }
.badge-pink   { background: var(--pink-lt);   color: var(--pink); }
.badge-purple { background: var(--purple-lt); color: var(--purple); }
.badge-gray   { background: #f1f5f9;          color: var(--muted); }

/* ===== ROOM MINI BARS ===== */
.room-bar { display: flex; gap: 4px; align-items: center; }
.room-dot {
    display: inline-flex; align-items: center;
    gap: 4px; font-size: 12px; font-weight: 600;
    padding: 2px 8px; border-radius: 10px;
}
.room-dot.avail   { background: var(--green-lt);  color: var(--green); }
.room-dot.full    { background: var(--red-lt);    color: var(--red); }
.room-dot.maint   { background: var(--yellow-lt); color: var(--yellow); }

/* ===== PROGRESS BAR ===== */
.occ-bar {
    width: 80px; height: 6px; background: var(--border);
    border-radius: 3px; overflow: hidden; display: inline-block;
}
.occ-fill { height: 100%; border-radius: 3px; background: var(--green); }
.occ-fill.mid  { background: var(--yellow); }
.occ-fill.high { background: var(--red); }

/* ===== ACTION LINK ===== */
.btn-detail {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--primary-lt); color: var(--primary);
    padding: 5px 12px; border-radius: 6px; font-size: 13px;
    font-weight: 600; text-decoration: none;
    transition: background .15s;
}
.btn-detail:hover { background: #dbeafe; }

/* ===== RESPONSIVE ===== */
@media (max-width: 640px) {
    .site-header nav { display: none; }
    thead th:nth-child(3),
    thead th:nth-child(5),
    td:nth-child(3),
    td:nth-child(5) { display: none; }
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <div>
        <div class="logo">🏢 KTX Campus</div>
        <div class="subtitle">Hệ thống quản lý ký túc xá</div>
    </div>
    <nav>
        <a href="BuildingListView.php" class="active">Tòa nhà</a>
        <a href="#">Sinh viên</a>
        <a href="#">Hóa đơn</a>
        <a href="#">Vi phạm</a>
    </nav>
</header>

<main class="page">
    <h1 class="page-title">Danh sách tòa nhà</h1>
    <p class="page-desc">Tổng quan các tòa nhà trong khuôn viên ký túc xá — nhấn "Xem phòng" để xem chi tiết phòng.</p>

    <?php
    // Tính tổng thống kê
    $totalBuildings   = count($buildings);
    $totalRooms       = array_sum(array_column($buildings, 'actual_rooms'));
    $totalAvailable   = array_sum(array_column($buildings, 'rooms_available'));
    $totalFull        = array_sum(array_column($buildings, 'rooms_full'));
    $totalMaintenance = array_sum(array_column($buildings, 'rooms_maintenance'));
    ?>

    <!-- SUMMARY CARDS -->
    <div class="summary-row">
        <div class="stat-card">
            <div class="stat-value"><?= $totalBuildings ?></div>
            <div class="stat-label">Tổng số tòa</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalRooms ?></div>
            <div class="stat-label">Tổng số phòng</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $totalAvailable ?></div>
            <div class="stat-label">Phòng còn chỗ</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $totalFull ?></div>
            <div class="stat-label">Phòng đầy</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $totalMaintenance ?></div>
            <div class="stat-label">Đang bảo trì</div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <h2>📋 Tất cả tòa nhà (<?= $totalBuildings ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Mã / Tên tòa</th>
                        <th>Đối tượng</th>
                        <th>Số tầng</th>
                        <th>Trạng thái phòng</th>
                        <th>Quản lý</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($buildings)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">Không có dữ liệu tòa nhà.</td></tr>
                <?php else: ?>
                    <?php foreach ($buildings as $b):
                        $total   = $b['actual_rooms'] ?: 1;
                        $fullPct = round(($b['rooms_full'] / $total) * 100);
                        $fillClass = $fullPct >= 80 ? 'high' : ($fullPct >= 50 ? 'mid' : '');
                    ?>
                    <tr>
                        <!-- Mã + Tên -->
                        <td>
                            <div style="font-weight:600;font-size:15px">
                                <?= htmlspecialchars($b['building_name']) ?>
                            </div>
                            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                Mã: <strong><?= htmlspecialchars($b['building_code']) ?></strong>
                                · <?= $b['actual_rooms'] ?> phòng thực tế
                            </div>
                        </td>

                        <!-- Giới tính -->
                        <td>
                            <span class="badge <?= genderClass($b['gender_type']) ?>">
                                <?= genderLabel($b['gender_type']) ?>
                            </span>
                        </td>

                        <!-- Số tầng -->
                        <td style="color:var(--muted)"><?= $b['total_floors'] ?> tầng</td>

                        <!-- Phòng theo trạng thái + thanh tiến độ -->
                        <td>
                            <div class="room-bar">
                                <?php if ($b['rooms_available'] > 0): ?>
                                <span class="room-dot avail">✓ <?= $b['rooms_available'] ?> còn</span>
                                <?php endif; ?>
                                <?php if ($b['rooms_full'] > 0): ?>
                                <span class="room-dot full">✗ <?= $b['rooms_full'] ?> đầy</span>
                                <?php endif; ?>
                                <?php if ($b['rooms_maintenance'] > 0): ?>
                                <span class="room-dot maint">⚙ <?= $b['rooms_maintenance'] ?> bảo trì</span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                                <div class="occ-bar">
                                    <div class="occ-fill <?= $fillClass ?>" style="width:<?= $fullPct ?>%"></div>
                                </div>
                                <span style="font-size:11px;color:var(--muted)"><?= $fullPct ?>% đầy</span>
                            </div>
                        </td>

                        <!-- Quản lý -->
                        <td>
                            <div style="font-size:13px"><?= htmlspecialchars($b['manager_name'] ?? '—') ?></div>
                            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($b['manager_phone'] ?? '') ?></div>
                        </td>

                        <!-- Nút xem chi tiết -->
                        <td>
                            <a class="btn-detail"
                               href="RoomDetailView.php?building_id=<?= $b['building_id'] ?>">
                                🔍 Xem phòng
                            </a>
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
