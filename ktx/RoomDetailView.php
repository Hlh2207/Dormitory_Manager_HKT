<?php
// ============================================================
//  RoomDetailView.php — Chi tiết phòng của 1 tòa nhà (Grid)
//  Kết nối bảng: buildings, rooms, room_types
//  Phòng available = xanh | full = đỏ | maintenance = vàng
// ============================================================

// ---------- 1. KẾT NỐI DATABASE ----------
$host    = 'localhost';
$db      = 'campus_dormitory';
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

// ---------- 2. VALIDATE THAM SỐ ----------
$buildingId = filter_input(INPUT_GET, 'building_id', FILTER_VALIDATE_INT);
if (!$buildingId || $buildingId <= 0) {
    header('Location: BuildingListView.php');
    exit;
}

// ---------- 3. LẤY THÔNG TIN TÒA NHÀ ----------
$stmtB = $pdo->prepare("
    SELECT building_id, building_code, building_name,
           gender_type, total_floors, total_rooms, manager_name, manager_phone
    FROM buildings
    WHERE building_id = :id AND is_active = 1
");
$stmtB->execute([':id' => $buildingId]);
$building = $stmtB->fetch();

if (!$building) {
    die('<p style="color:red">Không tìm thấy tòa nhà.</p>');
}

// ---------- 4. LẤY DANH SÁCH PHÒNG (JOIN 3 BẢNG) ----------
$stmtR = $pdo->prepare("
    SELECT
        r.room_id,
        r.room_number,
        r.floor,
        r.current_occupancy,
        r.status_code,
        r.notes,
        rt.type_id,
        rt.type_name,
        rt.capacity,
        rt.price_per_month,
        rt.area_m2,
        -- Số giường trống
        (rt.capacity - r.current_occupancy) AS empty_beds
    FROM rooms r
    JOIN room_types rt ON rt.type_id = r.type_id
    WHERE r.building_id = :bid
    ORDER BY r.floor ASC, r.room_number ASC
");
$stmtR->execute([':bid' => $buildingId]);
$rooms = $stmtR->fetchAll();

// ---------- 5. NHÓM PHÒNG THEO TẦNG ----------
$byFloor = [];
foreach ($rooms as $room) {
    $byFloor[$room['floor']][] = $room;
}
ksort($byFloor);

// ---------- 6. THỐNG KÊ NHANH ----------
$total     = count($rooms);
$available = count(array_filter($rooms, fn($r) => $r['status_code'] === 'available'));
$full      = count(array_filter($rooms, fn($r) => $r['status_code'] === 'full'));
$maint     = count(array_filter($rooms, fn($r) => $r['status_code'] === 'maintenance'));

// ---------- 7. HÀM TIỆN ÍCH ----------
function statusInfo(string $code): array {
    return match($code) {
        'available'   => ['class' => 'room-available', 'label' => 'Còn chỗ',    'icon' => '✓'],
        'full'        => ['class' => 'room-full',       'label' => 'Hết chỗ',   'icon' => '✗'],
        'maintenance' => ['class' => 'room-maint',      'label' => 'Bảo trì',   'icon' => '⚙'],
        'closed'      => ['class' => 'room-closed',     'label' => 'Đóng cửa',  'icon' => '🔒'],
        default       => ['class' => 'room-unknown',    'label' => $code,        'icon' => '?'],
    };
}

function formatPrice(float $price): string {
    return number_format($price, 0, ',', '.') . ' đ';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($building['building_name']) ?> — Sơ đồ phòng</title>
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

    /* Màu trạng thái phòng */
    --c-avail:   #16a34a;
    --c-avail-lt:#dcfce7;
    --c-avail-bd:#bbf7d0;

    --c-full:    #dc2626;
    --c-full-lt: #fee2e2;
    --c-full-bd: #fca5a5;

    --c-maint:   #ca8a04;
    --c-maint-lt:#fef9c3;
    --c-maint-bd:#fde68a;

    --c-closed:  #6b7280;
    --c-closed-lt:#f3f4f6;
    --c-closed-bd:#d1d5db;

    --radius: 12px;
    --shadow: 0 1px 3px rgba(0,0,0,.08);
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* ===== HEADER ===== */
.site-header {
    background: var(--primary); color: #fff;
    padding: 0 24px; display: flex; align-items: center;
    gap: 16px; height: 60px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.site-header .logo { font-size: 20px; font-weight: 700; }
.site-header .subtitle { font-size: 13px; opacity: .75; }
.site-header nav { margin-left: auto; display: flex; gap: 4px; }
.site-header nav a {
    color:#fff; text-decoration:none; padding:6px 14px;
    border-radius:6px; font-size:13px; opacity:.8;
    transition: background .15s;
}
.site-header nav a:hover { background: rgba(255,255,255,.15); opacity:1; }

/* ===== PAGE ===== */
.page { max-width: 1300px; margin: 0 auto; padding: 28px 20px; }

/* Breadcrumb */
.breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--muted); margin-bottom: 16px;
}
.breadcrumb a { color: var(--primary); text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb span { opacity: .5; }

/* Page title */
.page-title {
    font-size: 22px; font-weight: 700;
    display: flex; align-items: center; gap: 10px; margin-bottom: 4px;
}
.page-title::before {
    content:''; display:block; width:4px; height:28px;
    background:var(--primary); border-radius:2px;
}
.page-meta { color: var(--muted); font-size: 14px; margin-bottom: 20px; }

/* ===== STATS ROW ===== */
.stats-row {
    display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px;
}
.stat {
    background: var(--card); border-radius: 10px;
    padding: 14px 20px; box-shadow: var(--shadow);
    min-width: 110px; border-top: 3px solid transparent;
    flex: 1 1 100px;
}
.stat.blue   { border-color: var(--primary); }
.stat.green  { border-color: var(--c-avail); }
.stat.red    { border-color: var(--c-full); }
.stat.yellow { border-color: var(--c-maint); }
.stat-n { font-size: 24px; font-weight: 700; }
.stat-l { font-size: 12px; color: var(--muted); margin-top: 2px; }

/* ===== LEGEND ===== */
.legend {
    display: flex; gap: 16px; flex-wrap: wrap;
    margin-bottom: 22px; align-items: center;
}
.legend-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; color: var(--muted);
}
.legend-box {
    width: 20px; height: 20px; border-radius: 5px;
    border: 2px solid;
}
.lb-avail { background:var(--c-avail-lt); border-color:var(--c-avail); }
.lb-full  { background:var(--c-full-lt);  border-color:var(--c-full); }
.lb-maint { background:var(--c-maint-lt); border-color:var(--c-maint); }

/* ===== FLOOR SECTION ===== */
.floor-section { margin-bottom: 28px; }
.floor-label {
    font-size: 13px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .6px;
    padding: 6px 12px; background: var(--card);
    border-radius: 8px; display: inline-block;
    margin-bottom: 12px; box-shadow: var(--shadow);
}

/* ===== ROOM GRID ===== */
.room-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}

/* ===== ROOM CARD ===== */
.room-card {
    border-radius: 10px;
    border: 2px solid transparent;
    padding: 14px 12px;
    cursor: pointer;
    position: relative;
    transition: transform .15s, box-shadow .15s;
}
.room-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
}

/* Màu theo trạng thái */
.room-available {
    background: var(--c-avail-lt);
    border-color: var(--c-avail-bd);
}
.room-full {
    background: var(--c-full-lt);
    border-color: var(--c-full-bd);
}
.room-maint {
    background: var(--c-maint-lt);
    border-color: var(--c-maint-bd);
}
.room-closed, .room-unknown {
    background: var(--c-closed-lt);
    border-color: var(--c-closed-bd);
}

.room-number {
    font-size: 20px; font-weight: 800; line-height: 1;
    margin-bottom: 6px;
}
.room-available .room-number { color: var(--c-avail); }
.room-full      .room-number { color: var(--c-full); }
.room-maint     .room-number { color: var(--c-maint); }
.room-closed    .room-number { color: var(--c-closed); }

.room-type { font-size: 11px; color: var(--muted); margin-bottom: 8px; }

.room-beds {
    display: flex; gap: 3px; flex-wrap: wrap; margin-bottom: 8px;
}
.bed {
    width: 16px; height: 16px; border-radius: 3px;
    border: 1.5px solid currentColor; font-size: 9px;
    display: flex; align-items: center; justify-content: center;
}
.bed.occupied { background: currentColor; }

.room-status-badge {
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; gap: 3px;
}
.room-available .room-status-badge { color: var(--c-avail); }
.room-full      .room-status-badge { color: var(--c-full); }
.room-maint     .room-status-badge { color: var(--c-maint); }
.room-closed    .room-status-badge { color: var(--c-closed); }

.room-price {
    font-size: 11px; color: var(--muted); margin-top: 5px;
}

/* ===== MODAL OVERLAY ===== */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 100;
    align-items: center; justify-content: center; padding: 20px;
}
.modal-overlay.open { display: flex; }

.modal {
    background: var(--card); border-radius: 16px;
    padding: 28px; max-width: 380px; width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    animation: modal-in .18s ease;
}
@keyframes modal-in {
    from { transform: scale(.95); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}
.modal-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 20px;
}
.modal-title { font-size: 18px; font-weight: 700; }
.modal-close {
    background: none; border: none; font-size: 20px;
    cursor: pointer; color: var(--muted); line-height: 1;
    padding: 2px 6px; border-radius: 4px;
}
.modal-close:hover { background: var(--bg); }

.info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.info-row:last-child { border-bottom: none; }
.info-key { color: var(--muted); }
.info-val { font-weight: 600; }

/* ===== RESPONSIVE ===== */
@media (max-width: 600px) {
    .site-header nav { display: none; }
    .room-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
    .room-card { padding: 10px 8px; }
    .room-number { font-size: 16px; }
    .stats-row { gap: 8px; }
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
        <a href="BuildingListView.php">Tòa nhà</a>
        <a href="#">Sinh viên</a>
        <a href="#">Hóa đơn</a>
    </nav>
</header>

<main class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="BuildingListView.php">🏠 Tòa nhà</a>
        <span>›</span>
        <span><?= htmlspecialchars($building['building_name']) ?></span>
    </div>

    <!-- TIÊU ĐỀ -->
    <h1 class="page-title"><?= htmlspecialchars($building['building_name']) ?></h1>
    <p class="page-meta">
        Quản lý: <strong><?= htmlspecialchars($building['manager_name'] ?? '—') ?></strong>
        · <?= htmlspecialchars($building['manager_phone'] ?? '') ?>
        · <?= $building['total_floors'] ?> tầng
    </p>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat blue">
            <div class="stat-n"><?= $total ?></div>
            <div class="stat-l">Tổng phòng</div>
        </div>
        <div class="stat green">
            <div class="stat-n"><?= $available ?></div>
            <div class="stat-l">Còn chỗ</div>
        </div>
        <div class="stat red">
            <div class="stat-n"><?= $full ?></div>
            <div class="stat-l">Hết chỗ</div>
        </div>
        <div class="stat yellow">
            <div class="stat-n"><?= $maint ?></div>
            <div class="stat-l">Bảo trì</div>
        </div>
    </div>

    <!-- CHÚ THÍCH -->
    <div class="legend">
        <strong style="font-size:13px">Chú thích:</strong>
        <div class="legend-item"><div class="legend-box lb-avail"></div> Còn chỗ</div>
        <div class="legend-item"><div class="legend-box lb-full"></div>  Hết chỗ (đầy)</div>
        <div class="legend-item"><div class="legend-box lb-maint"></div> Đang bảo trì</div>
        <span style="font-size:12px;color:var(--muted);margin-left:auto">Nhấn vào phòng để xem chi tiết</span>
    </div>

    <?php if (empty($rooms)): ?>
        <div style="text-align:center;color:var(--muted);padding:60px 0;font-size:15px">
            Tòa nhà này chưa có phòng nào.
        </div>
    <?php else: ?>

        <!-- GRID PHÒNG THEO TẦNG -->
        <?php foreach ($byFloor as $floor => $floorRooms): ?>
        <div class="floor-section">
            <div class="floor-label">Tầng <?= $floor ?> — <?= count($floorRooms) ?> phòng</div>
            <div class="room-grid">
                <?php foreach ($floorRooms as $room):
                    $info      = statusInfo($room['status_code']);
                    $capacity  = (int)$room['capacity'];
                    $occupied  = (int)$room['current_occupancy'];
                    $emptyBeds = (int)$room['empty_beds'];
                ?>
                <div class="room-card <?= $info['class'] ?>"
                     onclick="showModal(<?= htmlspecialchars(json_encode([
                         'room_number'       => $room['room_number'],
                         'floor'             => $room['floor'],
                         'type_name'         => $room['type_name'],
                         'capacity'          => $capacity,
                         'current_occupancy' => $occupied,
                         'empty_beds'        => $emptyBeds,
                         'price_per_month'   => $room['price_per_month'],
                         'area_m2'           => $room['area_m2'],
                         'status_code'       => $room['status_code'],
                         'status_label'      => $info['label'],
                         'notes'             => $room['notes'],
                     ]), ENT_QUOTES) ?>)"
                     title="Phòng <?= htmlspecialchars($room['room_number']) ?> — <?= $info['label'] ?>">

                    <!-- Số phòng -->
                    <div class="room-number"><?= htmlspecialchars($room['room_number']) ?></div>

                    <!-- Loại phòng -->
                    <div class="room-type"><?= htmlspecialchars($room['type_name']) ?></div>

                    <!-- Giường (icon trực quan) -->
                    <div class="room-beds">
                        <?php for ($i = 0; $i < $capacity; $i++): ?>
                        <div class="bed <?= ($i < $occupied) ? 'occupied' : '' ?>"
                             title="<?= ($i < $occupied) ? 'Đã có người' : 'Trống' ?>">
                            <?= ($i < $occupied) ? '●' : '' ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Trạng thái -->
                    <div class="room-status-badge">
                        <?= $info['icon'] ?> <?= $info['label'] ?>
                    </div>

                    <!-- Số giường trống -->
                    <?php if ($room['status_code'] === 'available'): ?>
                    <div class="room-price">Còn <?= $emptyBeds ?>/<?= $capacity ?> giường</div>
                    <?php elseif ($room['status_code'] === 'full'): ?>
                    <div class="room-price">Đầy <?= $capacity ?>/<?= $capacity ?> giường</div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<!-- MODAL CHI TIẾT PHÒNG -->
<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
    <div class="modal" id="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">Chi tiết phòng</div>
            <button class="modal-close" onclick="closeModalDirect()">✕</button>
        </div>
        <div id="modal-body"></div>
    </div>
</div>

<script>
function showModal(data) {
    document.getElementById('modal-title').textContent = 'Phòng ' + data.room_number;
    const statusColors = {
        'available':   '#16a34a',
        'full':        '#dc2626',
        'maintenance': '#ca8a04',
        'closed':      '#6b7280',
    };
    const color = statusColors[data.status_code] || '#64748b';

    const rows = [
        ['Số phòng',      data.room_number],
        ['Tầng',          'Tầng ' + data.floor],
        ['Loại phòng',    data.type_name],
        ['Sức chứa',      data.capacity + ' người'],
        ['Đang ở',        data.current_occupancy + ' người'],
        ['Giường trống',  data.empty_beds + ' giường'],
        ['Diện tích',     data.area_m2 ? data.area_m2 + ' m²' : '—'],
        ['Giá/tháng',     parseInt(data.price_per_month).toLocaleString('vi-VN') + ' đ'],
        ['Trạng thái',    `<span style="color:${color};font-weight:700">${data.status_label}</span>`],
        ['Ghi chú',       data.notes || '—'],
    ];

    document.getElementById('modal-body').innerHTML = rows.map(([k, v]) =>
        `<div class="info-row">
            <span class="info-key">${k}</span>
            <span class="info-val">${v}</span>
        </div>`
    ).join('');

    document.getElementById('modal-overlay').classList.add('open');
}

function closeModal(e) {
    if (e.target.id === 'modal-overlay') closeModalDirect();
}
function closeModalDirect() {
    document.getElementById('modal-overlay').classList.remove('open');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModalDirect();
});
</script>

</body>
</html>
