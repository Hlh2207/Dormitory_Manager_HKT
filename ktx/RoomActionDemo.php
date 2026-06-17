<?php
// ============================================================
//  RoomActionDemo.php — RoomController Testing Page
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
    die('DB Connection Failed: ' . htmlspecialchars($e->getMessage()));
}

$controller = new RoomController($pdo);
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if ($roomId) {
        $message = match ($action) {
            'assign'      => $controller->assignStudentToRoom($roomId),
            'remove'      => $controller->removeStudentFromRoom($roomId),
            'maintenance' => $controller->setRoomMaintenance($roomId, 'Room is under maintenance'),
            'reopen'      => $controller->reopenRoom($roomId),
            default       => ['success' => false, 'message' => 'Invalid action.'],
        };
    }
}

$rooms = $controller->getRoomsWithDetails();

function statusLabel(string $code): array {
    return match($code) {
        'available'   => ['Available',   'status-available'],
        'full'        => ['Full',        'status-full'],
        'maintenance' => ['Maintenance', 'status-maintenance'],
        default       => [$code,         'status-default'],
    };
}

$pageTitle = "Room Status Testing";
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="BuildingListView.php">Buildings</a>
        <span>&rsaquo;</span>
        <span>Testing Room Status Updates</span>
    </div>

    <h1 class="page-title">Testing Room Status Updates</h1>
    <p class="page-desc">Perform actions to test logic for student assignment, maintenance toggling, and room reopening.</p>

    <?php if ($message): ?>
    <div class="alert <?= $message['success'] ? 'ok' : 'no' ?>">
        <?= htmlspecialchars($message['message']) ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <div class="table-header">
            <h2>Room List (<?= count($rooms) ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Building</th>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Empty Beds</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                                <button class="btn btn-assign" name="action" value="assign">Assign</button>
                                <button class="btn btn-remove" name="action" value="remove">Remove</button>
                                <button class="btn btn-maint" name="action" value="maintenance">Maint.</button>
                                <button class="btn btn-reopen" name="action" value="reopen">Reopen</button>
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