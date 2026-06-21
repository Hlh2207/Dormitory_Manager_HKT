<?php
// ============================================================
//  BuildingListView.php — Building List
//  Connects to: buildings, rooms
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>'); }

// Task 6: PDO Prepared Statement
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
        COUNT(r.room_id)                                                AS actual_rooms,
        SUM(CASE WHEN r.status_code = 'available'   THEN 1 ELSE 0 END) AS rooms_available,
        SUM(CASE WHEN r.status_code = 'full'         THEN 1 ELSE 0 END) AS rooms_full,
        SUM(CASE WHEN r.status_code = 'maintenance'  THEN 1 ELSE 0 END) AS rooms_maintenance
    FROM buildings b
    LEFT JOIN rooms r ON r.building_id = b.building_id
    WHERE b.is_active = 1
    GROUP BY b.building_id
    ORDER BY b.building_code
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$buildings = $stmt->fetchAll();

function genderLabel(string $type): string {
    return match($type) {
        'male'   => 'Male',
        'female' => 'Female',
        'mixed'  => 'Mixed',
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

$pageTitle = "Building Management";
include 'header.php';
?>

<main class="page">
    <h1 class="page-title">Building List</h1>
    <p class="page-desc">Overview of campus buildings — click "View Rooms" to see detailed allocations.</p>

    <?php
    $totalBuildings   = count($buildings);
    $totalRooms       = array_sum(array_column($buildings, 'actual_rooms'));
    $totalAvailable   = array_sum(array_column($buildings, 'rooms_available'));
    $totalFull        = array_sum(array_column($buildings, 'rooms_full'));
    $totalMaintenance = array_sum(array_column($buildings, 'rooms_maintenance'));
    ?>

    <div class="summary-row">
        <div class="stat-card">
            <div class="stat-value"><?= $totalBuildings ?></div>
            <div class="stat-label">Total Buildings</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-value"><?= $totalRooms ?></div>
            <div class="stat-label">Total Rooms</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $totalAvailable ?></div>
            <div class="stat-label">Available Rooms</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $totalFull ?></div>
            <div class="stat-label">Full Rooms</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $totalMaintenance ?></div>
            <div class="stat-label">Maintenance</div>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-header">
            <h2>All Buildings (<?= $totalBuildings ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code / Name</th>
                        <th>Gender Assigned</th>
                        <th class="hide-mobile">Floors</th>
                        <th>Room Status</th>
                        <th class="hide-mobile">Manager</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($buildings)): ?>
                    <tr><td colspan="6"><div class="empty">No building data available.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($buildings as $b):
                        $totalActual = $b['actual_rooms'] ?: 1;
                        $fullPct = round(($b['rooms_full'] / $totalActual) * 100);
                        $fillClass = $fullPct >= 80 ? 'high' : ($fullPct >= 50 ? 'mid' : '');
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:15px">
                                <?= htmlspecialchars($b['building_name']) ?>
                            </div>
                            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                Code: <strong><?= htmlspecialchars($b['building_code']) ?></strong>
                                · <?= $b['actual_rooms'] ?> actual rooms
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= genderClass($b['gender_type']) ?>">
                                <?= genderLabel($b['gender_type']) ?>
                            </span>
                        </td>
                        <td class="hide-mobile" style="color:var(--muted)"><?= $b['total_floors'] ?> floors</td>
                        <td>
                            <div class="room-bar">
                                <?php if ($b['rooms_available'] > 0): ?>
                                <span class="room-dot avail"><?= $b['rooms_available'] ?> available</span>
                                <?php endif; ?>
                                <?php if ($b['rooms_full'] > 0): ?>
                                <span class="room-dot full"><?= $b['rooms_full'] ?> full</span>
                                <?php endif; ?>
                                <?php if ($b['rooms_maintenance'] > 0): ?>
                                <span class="room-dot maint"><?= $b['rooms_maintenance'] ?> maint.</span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                                <div class="occ-bar">
                                    <div class="occ-fill <?= $fillClass ?>" style="width:<?= $fullPct ?>%"></div>
                                </div>
                                <span style="font-size:11px;color:var(--muted)"><?= $fullPct ?>% filled</span>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-size:13px"><?= htmlspecialchars($b['manager_name'] ?? '—') ?></div>
                            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($b['manager_phone'] ?? '') ?></div>
                        </td>
                        <td>
                            <a class="btn-detail" href="RoomDetailView.php?building_id=<?= $b['building_id'] ?>">
                                View Rooms
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