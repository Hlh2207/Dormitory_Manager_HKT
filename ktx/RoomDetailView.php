<?php
// ============================================================
//  RoomDetailView.php — Room Layout for a Building (Grid)
//  Connects to: buildings, rooms, room_types, contracts, students
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>'); }

$buildingId = filter_input(INPUT_GET, 'building_id', FILTER_VALIDATE_INT);
if (!$buildingId || $buildingId <= 0) { header('Location: BuildingListView.php'); exit; }

$stmtB = $pdo->prepare("SELECT * FROM buildings WHERE building_id = :id AND is_active = 1");
$stmtB->execute([':id' => $buildingId]);
$building = $stmtB->fetch();

if (!$building) die('<p style="color:red">Building not found.</p>');

$stmtR = $pdo->prepare("
    SELECT r.room_id, r.room_number, r.floor, r.current_occupancy, r.status_code, r.notes,
           rt.type_id, rt.type_name, rt.capacity, rt.price_per_month, rt.area_m2,
           (rt.capacity - r.current_occupancy) AS empty_beds
    FROM rooms r JOIN room_types rt ON rt.type_id = r.type_id
    WHERE r.building_id = :bid ORDER BY r.floor ASC, r.room_number ASC
");
$stmtR->execute([':bid' => $buildingId]);
$rooms = $stmtR->fetchAll();

$byFloor = []; foreach ($rooms as $room) $byFloor[$room['floor']][] = $room; ksort($byFloor);

$total     = count($rooms);
$available = count(array_filter($rooms, fn($r) => $r['status_code'] === 'available'));
$full      = count(array_filter($rooms, fn($r) => $r['status_code'] === 'full'));
$maint     = count(array_filter($rooms, fn($r) => $r['status_code'] === 'maintenance'));

function statusInfo(string $code): array {
    return match($code) {
        'available'   => ['class' => 'room-available', 'label' => 'Available'],
        'full'        => ['class' => 'room-full',      'label' => 'Full'],
        'maintenance' => ['class' => 'room-maint',     'label' => 'Maintenance'],
        'closed'      => ['class' => 'room-closed',    'label' => 'Closed'],
        default       => ['class' => 'room-unknown',   'label' => $code],
    };
}

$pageTitle = htmlspecialchars($building['building_name']) . " — Room Layout";
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="BuildingListView.php">🏠 Buildings</a>
        <span>›</span>
        <span><?= htmlspecialchars($building['building_name']) ?></span>
    </div>

    <h1 class="page-title"><?= htmlspecialchars($building['building_name']) ?></h1>
    <p class="page-desc">
        Manager: <strong><?= htmlspecialchars($building['manager_name'] ?? '—') ?></strong>
        · <?= htmlspecialchars($building['manager_phone'] ?? '') ?>
        · <?= $building['total_floors'] ?> Floors
    </p>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div><div class="stat-label">Total Rooms</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $available ?></div><div class="stat-label">Available</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $full ?></div><div class="stat-label">Full</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $maint ?></div><div class="stat-label">Maintenance</div>
        </div>
    </div>

    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:22px; align-items:center;">
        <strong style="font-size:13px">Legend:</strong>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);"><div style="width:20px;height:20px;border-radius:5px;background:var(--green-lt);border:2px solid var(--green);"></div> Available</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);"><div style="width:20px;height:20px;border-radius:5px;background:var(--red-lt);border:2px solid var(--red);"></div> Full</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);"><div style="width:20px;height:20px;border-radius:5px;background:var(--yellow-lt);border:2px solid var(--yellow);"></div> Maintenance</div>
        <span style="font-size:12px;color:var(--muted);margin-left:auto">Click on a room for details</span>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="empty">No rooms available in this building.</div>
    <?php else: ?>

        <?php foreach ($byFloor as $floor => $floorRooms): ?>
        <div style="margin-bottom: 28px;">
            <div style="font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;padding:6px 12px;background:var(--card);border-radius:8px;display:inline-block;margin-bottom:12px;box-shadow:var(--shadow);">
                Floor <?= $floor ?> — <?= count($floorRooms) ?> rooms
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:12px;">
                <?php foreach ($floorRooms as $room):
                    $info      = statusInfo($room['status_code']);
                    $capacity  = (int)$room['capacity'];
                    $occupied  = (int)$room['current_occupancy'];
                    $emptyBeds = (int)$room['empty_beds'];

                    $bgColor = match($room['status_code']) { 'available' => 'var(--green-lt)', 'full' => 'var(--red-lt)', 'maintenance' => 'var(--yellow-lt)', default => '#f3f4f6' };
                    $bdColor = match($room['status_code']) { 'available' => 'var(--green)', 'full' => 'var(--red)', 'maintenance' => 'var(--yellow)', default => '#d1d5db' };
                    $txColor = match($room['status_code']) { 'available' => 'var(--green)', 'full' => 'var(--red)', 'maintenance' => 'var(--yellow)', default => 'var(--muted)' };
                ?>
                <div style="border-radius:10px; border:2px solid <?= $bdColor ?>; background:<?= $bgColor ?>; padding:14px 12px; cursor:pointer; transition:transform .15s, box-shadow .15s;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='none';"
                     onclick="showModal(<?= htmlspecialchars(json_encode([
                         'room_id'           => $room['room_id'],
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
                     title="Room <?= htmlspecialchars($room['room_number']) ?> — <?= $info['label'] ?>">

                    <div style="font-size:20px;font-weight:800;line-height:1;margin-bottom:6px;color:<?= $txColor ?>;">
                        <?= htmlspecialchars($room['room_number']) ?>
                    </div>
                    <div style="font-size:11px;color:var(--muted);margin-bottom:8px;">
                        <?= htmlspecialchars($room['type_name']) ?>
                    </div>

                    <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:8px;color:<?= $txColor ?>;">
                        <?php for ($i = 0; $i < $capacity; $i++): ?>
                        <div style="width:16px;height:16px;border-radius:3px;border:1.5px solid currentColor;font-size:9px;display:flex;align-items:center;justify-content:center; <?= ($i < $occupied) ? 'background:currentColor;' : '' ?>"
                             title="<?= ($i < $occupied) ? 'Occupied' : 'Empty' ?>">
                            <?= ($i < $occupied) ? '●' : '' ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div style="font-size:11px;font-weight:700;color:<?= $txColor ?>;">
                        <?= $info['label'] ?>
                    </div>

                    <?php if ($room['status_code'] === 'available'): ?>
                    <div style="font-size:11px;color:var(--muted);margin-top:5px;">Available <?= $emptyBeds ?>/<?= $capacity ?> beds</div>
                    <?php elseif ($room['status_code'] === 'full'): ?>
                    <div style="font-size:11px;color:var(--muted);margin-top:5px;">Full <?= $capacity ?>/<?= $capacity ?> beds</div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
    <div class="modal" id="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">Room Details</div>
            <button class="modal-close" onclick="closeModalDirect()">✕</button>
        </div>
        <div id="modal-body"></div>
    </div>
</div>

<script>
function showModal(data) {
    document.getElementById('modal-title').textContent = 'Room ' + data.room_number;
    const statusColors = { 'available': 'var(--green)', 'full': 'var(--red)', 'maintenance': 'var(--yellow)', 'closed': 'var(--muted)' };
    const color = statusColors[data.status_code] || 'var(--muted)';

    const rows = [
        ['Room Number',       data.room_number],
        ['Floor',             'Floor ' + data.floor],
        ['Room Type',         data.type_name],
        ['Capacity',          data.capacity + ' people'],
        ['Current Occupants', data.current_occupancy + ' people'],
        ['Empty Beds',        data.empty_beds + ' beds'],
        ['Area',              data.area_m2 ? data.area_m2 + ' m²' : '—'],
        ['Price / Month',     parseInt(data.price_per_month).toLocaleString('en-US') + ' VND'],
        ['Status',            `<span style="color:${color};font-weight:700">${data.status_label}</span>`],
        ['Notes',             data.notes || '—'],
    ];

    let html = rows.map(([k, v]) =>
        `<div class="info-row"><span class="info-key">${k}</span><span class="info-val">${v}</span></div>`
    ).join('');

    html += `<div id="modal-students-section" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
        <div style="font-size:13px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">👥 Students in this Room</div>
        <div id="modal-students-list" style="font-size:13px;color:var(--muted)">Loading...</div>
    </div>`;

    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('modal-overlay').classList.add('open');

    fetchRoomStudents(data.room_id);
}

function fetchRoomStudents(roomId) {
    fetch('get_room_students.php?room_id=' + encodeURIComponent(roomId))
        .then(res => res.json())
        .then(result => {
            const listEl = document.getElementById('modal-students-list');
            if (!listEl) return;

            if (!result.success) {
                listEl.innerHTML = '<span style="color:var(--red)">⚠ Could not load student list.</span>';
                return;
            }
            if (result.count === 0) {
                listEl.innerHTML = '<span>No students currently assigned to this room.</span>';
                return;
            }

            listEl.innerHTML = result.students.map(s => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                    <div>
                        <div style="font-weight:600;color:var(--text)">${escapeHtml(s.full_name)}</div>
                        <div style="font-size:11px;color:var(--muted)">${escapeHtml(s.student_code)} · ${escapeHtml(s.phone || '—')}</div>
                    </div>
                    <div style="font-size:11px;color:var(--muted);text-align:right">From ${formatDate(s.start_date)}</div>
                </div>
            `).join('');
        })
        .catch(() => {
            const listEl = document.getElementById('modal-students-list');
            if (listEl) listEl.innerHTML = '<span style="color:var(--red)">⚠ Network error while loading students.</span>';
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-GB');
}

function closeModal(e) { if (e.target.id === 'modal-overlay') closeModalDirect(); }
function closeModalDirect() { document.getElementById('modal-overlay').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModalDirect(); });
</script>

</body>
</html>