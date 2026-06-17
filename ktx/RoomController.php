<?php
// ============================================================
//  RoomController.php — Xử lý logic phòng
//  Kết nối bảng: buildings, rooms, room_types
//
//  CHỨC NĂNG CHÍNH:
//   1. getRoomsWithDetails()  — lấy danh sách phòng (JOIN 3 bảng)
//   2. assignStudentToRoom()  — xếp SV vào phòng (trừ giường trống)
//   3. removeStudentFromRoom()— SV rời phòng (cộng lại giường trống)
//   4. setRoomMaintenance()   — chuyển phòng sang trạng thái bảo trì
//   5. updateRoomStatus()     — hàm dùng chung, tự suy ra status đúng
// ============================================================

class RoomController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ============================================================
    // 1. LẤY DANH SÁCH PHÒNG — JOIN 3 BẢNG buildings + rooms + room_types
    // ============================================================
    public function getRoomsWithDetails(?int $buildingId = null): array
    {
        $sql = "
            SELECT
                b.building_id,
                b.building_code,
                b.building_name,
                r.room_id,
                r.room_number,
                r.floor,
                r.current_occupancy,
                r.status_code,
                rt.type_id,
                rt.type_name,
                rt.capacity,
                rt.price_per_month,
                (rt.capacity - r.current_occupancy) AS empty_beds
            FROM rooms r
            JOIN buildings   b  ON b.building_id = r.building_id
            JOIN room_types  rt ON rt.type_id    = r.type_id
        ";

        $params = [];
        if ($buildingId !== null) {
            $sql .= " WHERE r.building_id = :bid";
            $params[':bid'] = $buildingId;
        }
        $sql .= " ORDER BY b.building_code, r.floor, r.room_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 2. LẤY CHI TIẾT 1 PHÒNG (dùng nội bộ trước khi cập nhật)
    // ============================================================
    private function getRoomById(int $roomId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.room_id, r.room_number, r.building_id, r.floor,
                r.current_occupancy, r.status_code,
                rt.type_id, rt.capacity
            FROM rooms r
            JOIN room_types rt ON rt.type_id = r.type_id
            WHERE r.room_id = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        return $room ?: null;
    }

    // ============================================================
    // 3. SUY RA TRẠNG THÁI PHÒNG TỪ SỐ GIƯỜNG (logic dùng chung)
    // ============================================================
    private function resolveStatus(int $occupancy, int $capacity, string $currentStatus): string
    {
        // Nếu phòng đang bảo trì hoặc đóng cửa thì giữ nguyên,
        // không tự động chuyển về available/full khi chỉ thay đổi occupancy.
        if (in_array($currentStatus, ['maintenance', 'closed'])) {
            return $currentStatus;
        }

        if ($occupancy >= $capacity) {
            return 'full';        // Hết giường trống → Đầy
        }
        return 'available';       // Còn giường trống → Còn chỗ
    }

    // ============================================================
    // 4. XẾP SINH VIÊN VÀO PHÒNG
    //    - Trừ 1 giường trống (tăng current_occupancy)
    //    - Nếu hết giường trống (occupancy == capacity) → status = 'full'
    // ============================================================
    public function assignStudentToRoom(int $roomId): array
    {
        try {
            $this->pdo->beginTransaction();

            $room = $this->getRoomById($roomId);
            if (!$room) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Không tìm thấy phòng.'];
            }

            $capacity = (int)$room['capacity'];
            $current  = (int)$room['current_occupancy'];

            // Kiểm tra điều kiện trước khi xếp
            if ($room['status_code'] === 'maintenance') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Phòng đang bảo trì, không thể xếp sinh viên.'];
            }
            if ($current >= $capacity) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Phòng đã đầy, không còn giường trống.'];
            }

            // Trừ 1 giường trống = tăng current_occupancy lên 1
            $newOccupancy = $current + 1;
            $newStatus    = $this->resolveStatus($newOccupancy, $capacity, $room['status_code']);

            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET current_occupancy = :occ,
                    status_code       = :status
                WHERE room_id = :id
            ");
            $stmt->execute([
                ':occ'    => $newOccupancy,
                ':status' => $newStatus,
                ':id'     => $roomId,
            ]);

            $this->pdo->commit();

            return [
                'success'      => true,
                'message'      => "Xếp sinh viên thành công. Giường trống còn: " . ($capacity - $newOccupancy),
                'empty_beds'   => $capacity - $newOccupancy,
                'status_code'  => $newStatus,
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }

    // ============================================================
    // 5. SINH VIÊN RỜI PHÒNG (chuyển phòng / kết thúc hợp đồng)
    //    - Cộng lại 1 giường trống (giảm current_occupancy)
    //    - Nếu trước đó là 'full' mà giờ còn trống → tự chuyển 'available'
    // ============================================================
    public function removeStudentFromRoom(int $roomId): array
    {
        try {
            $this->pdo->beginTransaction();

            $room = $this->getRoomById($roomId);
            if (!$room) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Không tìm thấy phòng.'];
            }

            $capacity = (int)$room['capacity'];
            $current  = (int)$room['current_occupancy'];

            // Không cho occupancy âm
            $newOccupancy = max(0, $current - 1);
            $newStatus    = $this->resolveStatus($newOccupancy, $capacity, $room['status_code']);

            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET current_occupancy = :occ,
                    status_code       = :status
                WHERE room_id = :id
            ");
            $stmt->execute([
                ':occ'    => $newOccupancy,
                ':status' => $newStatus,
                ':id'     => $roomId,
            ]);

            $this->pdo->commit();

            return [
                'success'     => true,
                'message'     => "Đã cập nhật, giường trống hiện tại: " . ($capacity - $newOccupancy),
                'empty_beds'  => $capacity - $newOccupancy,
                'status_code' => $newStatus,
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }

    // ============================================================
    // 6. CHUYỂN PHÒNG SANG TRẠNG THÁI BẢO TRÌ (phòng hỏng)
    // ============================================================
    public function setRoomMaintenance(int $roomId, string $note = ''): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status_code = 'maintenance',
                    notes       = :note
                WHERE room_id = :id
            ");
            $stmt->execute([
                ':note' => $note !== '' ? $note : 'Phòng đang được bảo trì',
                ':id'   => $roomId,
            ]);

            return ['success' => true, 'message' => 'Đã chuyển phòng sang trạng thái bảo trì.'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }

    // ============================================================
    // 7. MỞ LẠI PHÒNG SAU KHI SỬA XONG (bảo trì → available/full)
    // ============================================================
    public function reopenRoom(int $roomId): array
    {
        try {
            $room = $this->getRoomById($roomId);
            if (!$room) {
                return ['success' => false, 'message' => 'Không tìm thấy phòng.'];
            }

            $capacity = (int)$room['capacity'];
            $current  = (int)$room['current_occupancy'];
            // Suy ra trạng thái thật dựa trên occupancy hiện tại
            $newStatus = ($current >= $capacity) ? 'full' : 'available';

            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status_code = :status
                WHERE room_id = :id
            ");
            $stmt->execute([':status' => $newStatus, ':id' => $roomId]);

            return ['success' => true, 'message' => "Đã mở lại phòng, trạng thái: $newStatus"];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }
}


// ============================================================
//  XỬ LÝ REQUEST (entry point khi gọi trực tiếp file này từ AJAX/form)
//  Bỏ phần này nếu bạn chỉ include class để dùng ở nơi khác.
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {

    header('Content-Type: application/json; charset=utf-8');

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
        echo json_encode(['success' => false, 'message' => 'Kết nối DB thất bại: ' . $e->getMessage()]);
        exit;
    }

    $controller = new RoomController($pdo);
    $action     = $_POST['action'] ?? $_GET['action'] ?? '';
    $roomId     = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT)
               ?: filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);

    switch ($action) {
        case 'assign':
            echo json_encode($controller->assignStudentToRoom($roomId));
            break;

        case 'remove':
            echo json_encode($controller->removeStudentFromRoom($roomId));
            break;

        case 'maintenance':
            $note = trim($_POST['note'] ?? 'Phòng hỏng, đang sửa chữa');
            echo json_encode($controller->setRoomMaintenance($roomId, $note));
            break;

        case 'reopen':
            echo json_encode($controller->reopenRoom($roomId));
            break;

        case 'list':
            $buildingId = filter_input(INPUT_GET, 'building_id', FILTER_VALIDATE_INT) ?: null;
            echo json_encode([
                'success' => true,
                'data'    => $controller->getRoomsWithDetails($buildingId),
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
    }
}
