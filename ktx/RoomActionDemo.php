<?php
// ============================================================
//  RoomActionDemo.php — Trang kiểm thử RoomController.php
//  Dùng để kiểm tra trực quan logic xếp sinh viên / bảo trì phòng
// ============================================================
require_once __DIR__ . '/RoomController.php';

$host = 'localhost';
$db   = 'campus_final';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Kết nối DB thất bại: ' . htmlspecialchars($e->getMessage()));
}

$controller = new RoomController($pdo);
$message = null;

// Xử lý hành động khi submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if ($roomId) {
        $message = match ($action) {
            'assign'      => $controller->assignStudentToRoom($roomId),
            'remove'      => $controller->removeStudentFromRoom($roomId),
            'maintenance' => $controller->setRoomMaintenance($roomId, 'Phòng đang được bảo trì'),
            'reopen'      => $controller->reopenRoom($roomId),
            default       => ['success' => false, 'message' => 'Hành động không hợp lệ.'],
        };
    }
}

// Lấy danh sách phòng để hiển thị bảng kiểm thử
$rooms = $controller->getRoomsWithDetails();

function statusLabel(string $code): array {
    return match($code) {
        'available'   => ['Còn chỗ', 'status-available'],
        'full'        => ['Đầy',     'status-full'],
        'maintenance' => ['Bảo trì', 'status-maintenance'],
        default       => [$code,     'status-default'],
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kiểm thử cập nhật trạng thái phòng — KTX Campus</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #f0f2f5;
    --card:      #ffffff;
    --primary:   #0d9488;
    --primary-dk:#0f766e;
    --primary-lt:#e6fffa;
    --text:      #1e293b;
    --muted:     #64748b;
    --border:    #e2e8f0;
    --green:     #16a34a;
    --green-lt:  #dcfce7;
    --red:       #dc2626;
    --red-lt:    #fee2e2;
    --yellow:    #ca8a04;
    --yellow-lt: #fef9c3;
    --radius:    12px;
    --shadow:    0 1px 3px rgba(0,0,0,.08);
}

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 12px;
    min-height: 100vh;
}

/* HEADER */
.site-header {
    background: var(--primary);
    color: #fff;
    padding: 0 28px;
    display: flex;
    align-items: center;
    gap: 16px;
    height: 68px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.site-header .logo { font-size: 22px; font-weight: 700; }
.site-header .subtitle { font-size: 14px; opacity: .8; }
.site-header nav { margin-left: auto; display: flex; gap: 6px; }
.site-header nav a {
    color: #fff; text-decoration: none; padding: 9px 18px;
    border-radius: 8px; font-size: 15px; opacity: .85;
    transition: background .15s, opacity .15s;
}
.site-header nav a:hover, .site-header nav a.active {
    background: rgba(255,255,255,.18); opacity: 1;
}

/* PAGE */
.page { max-width: 1300px; margin: 0 auto; padding: 32px 24px; }

.breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; color: var(--muted); margin-bottom: 18px;
}
.breadcrumb a { color: var(--primary); text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }

.page-title {
    font-size: 26px; font-weight: 700;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 8px;
}
.page-title::before {
    content: ''; display: block;
    width: 5px; height: 32px;
    background: var(--primary); border-radius: 3px;
}
.page-desc { color: var(--muted); font-size: 14px; margin-bottom: 28px; }

/* ALERT */
.alert {
    padding: 14px 20px; border-radius: 10px;
    margin-bottom: 24px; font-size: 16px; font-weight: 500;
}
.alert.ok { background: var(--green-lt); color: var(--green); }
.alert.no { background: var(--red-lt);   color: var(--red); }

/* TABLE */
.table-wrap {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.table-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}
.table-header h2 { font-size: 18px; font-weight: 600; }

.table-responsive { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
thead th {
    background: #f8fafc; padding: 14px 18px;
    text-align: left; font-size: 13px; font-weight: 700;
    color: var(--muted); text-transform: uppercase;
    letter-spacing: .5px; border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
td { padding: 14px 18px; vertical-align: middle; }

/* STATUS BADGE */
.status-badge {
    display: inline-flex; align-items: center;
    padding: 5px 14px; border-radius: 20px;
    font-size: 14px; font-weight: 700;
}
.status-available   { background: var(--green-lt);  color: var(--green); }
.status-full        { background: var(--red-lt);    color: var(--red); }
.status-maintenance { background: var(--yellow-lt); color: var(--yellow); }
.status-default     { background: #f1f5f9;          color: var(--muted); }

/* ACTION BUTTONS */
.action-row { display: flex; gap: 8px; flex-wrap: wrap; }
.btn {
    padding: 8px 14px; font-size: 14px; font-weight: 600;
    border: none; border-radius: 8px; cursor: pointer;
    transition: background .15s;
}
.btn-assign { background: var(--primary-lt); color: var(--primary-dk); }
.btn-assign:hover { background: #ccfbf1; }
.btn-remove { background: #f1f5f9; color: var(--muted); }
.btn-remove:hover { background: var(--border); }
.btn-maint { background: var(--yellow-lt); color: var(--yellow); }
.btn-maint:hover { background: #fef08a; }
.btn-reopen { background: var(--green-lt); color: var(--green); }
.btn-reopen:hover { background: #bbf7d0; }

/* RESPONSIVE */
@media (max-width: 768px) {
    .site-header nav { display: none; }
    table { font-size: 14px; }
    td, thead th { padding: 10px 12px; }
}
</style>
</head>
<body>

<header class="site-header">
    <div>
        <div class="logo">KTX Campus</div>
        <div class="subtitle">Hệ thống quản lý ký túc xá</div>
    </div>
    <nav>
     <a href="BuildingListView.php">Tòa nhà</a>
    <a href="StudentListView.php">Sinh viên</a>
    <a href="ContractListView.php">Hợp đồng</a>
    <a href="InvoiceView.php">Hóa đơn</a>
    <a href="#">Vi phạm</a>
    </nav>
</header>

<main class="page">

    <div class="breadcrumb">
        <a href="BuildingListView.php">Tòa nhà</a>
        <span>&rsaquo;</span>
        <span>Kiểm thử cập nhật trạng thái phòng</span>
    </div>

    <h1 class="page-title">Kiểm thử cập nhật trạng thái phòng</h1>
    <p class="page-desc">Thực hiện thao tác để kiểm tra logic xếp sinh viên vào phòng, chuyển trạng thái bảo trì và mở lại phòng.</p>

    <?php if ($message): ?>
    <div class="alert <?= $message['success'] ? 'ok' : 'no' ?>">
        <?= htmlspecialchars($message['message']) ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <div class="table-header">
            <h2>Danh sách phòng (<?= count($rooms) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Tòa</th>
                        <th>Phòng</th>
                        <th>Loại phòng</th>
                        <th>Sức chứa</th>
                        <th>Đang ở</th>
                        <th>Giường trống</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $r):
                    [$label, $class] = statusLabel($r['status_code']);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($r['building_code']) ?></td>
                        <td><strong><?= htmlspecialchars($r['room_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['type_name']) ?></td>
                        <td><?= $r['capacity'] ?></td>
                        <td><?= $r['current_occupancy'] ?></td>
                        <td><?= $r['empty_beds'] ?></td>
                        <td><span class="status-badge <?= $class ?>"><?= $label ?></span></td>
                        <td>
                            <form method="POST" class="action-row">
                                <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                                <button class="btn btn-assign" name="action" value="assign">Xếp sinh viên</button>
                                <button class="btn btn-remove" name="action" value="remove">Sinh viên rời</button>
                                <button class="btn btn-maint" name="action" value="maintenance">Bảo trì</button>
                                <button class="btn btn-reopen" name="action" value="reopen">Mở lại</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>