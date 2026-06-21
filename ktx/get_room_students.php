<?php
// ============================================================
//  get_room_students.php — Returns the list of students
//  currently living in a given room (active contract).
//  Called via AJAX from RoomDetailView.php when a room is clicked.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Connection Failed.']);
    exit;
}

$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);

if (!$roomId) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID.']);
    exit;
}

// Lấy danh sách sinh viên đang có hợp đồng active tại phòng này
// (PDO Prepared Statement — Task 6)
$stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.student_code,
        s.full_name,
        s.gender,
        u.phone,
        u.email,
        c.start_date,
        c.end_date
    FROM contracts c
    JOIN students s ON s.student_id = c.student_id
    JOIN users    u ON u.user_id    = s.user_id
    WHERE c.room_id = :rid
      AND c.status_code = 'active'
    ORDER BY c.start_date ASC
");
$stmt->execute([':rid' => $roomId]);
$students = $stmt->fetchAll();

echo json_encode([
    'success'  => true,
    'count'    => count($students),
    'students' => $students,
]);
