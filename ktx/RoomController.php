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

    public function getRoomsWithDetails(?int $buildingId = null): array
    {
        $sql = "
            SELECT
                b.building_id, b.building_code, b.building_name,
                r.room_id, r.room_number, r.floor,
                r.current_occupancy, r.status_code,
                rt.type_id, rt.type_name, rt.capacity, rt.price_per_month,
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

    private function getRoomById(int $roomId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.room_id, r.room_number, r.building_id, r.floor, r.current_occupancy, r.status_code, rt.type_id, rt.capacity
            FROM rooms r JOIN room_types rt ON rt.type_id = r.type_id
            WHERE r.room_id = :id FOR UPDATE
        ");
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        return $room ?: null;
    }

    private function resolveStatus(int $occupancy, int $capacity, string $currentStatus): string
    {
        if (in_array($currentStatus, ['maintenance', 'closed'])) return $currentStatus;
        if ($occupancy >= $capacity) return 'full';
        return 'available';       
    }

    // XẾP SINH VIÊN (Xử lý Transaction an toàn)
    public function assignStudentToRoom(int $roomId): array
    {
        $isMyTransaction = false;
        try {
            // Nếu chưa có Transaction nào mở, thì tự mở cái mới
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isMyTransaction = true;
            }

            $room = $this->getRoomById($roomId);
            if (!$room) {
                if ($isMyTransaction) $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Không tìm thấy phòng.'];
            }

            $capacity = (int)$room['capacity'];
            $current  = (int)$room['current_occupancy'];

            if ($room['status_code'] === 'maintenance') {
                if ($isMyTransaction) $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Phòng đang bảo trì.'];
            }
            if ($current >= $capacity) {
                if ($isMyTransaction) $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Phòng đã đầy, không còn giường trống.'];
            }

            $newOccupancy = $current + 1;
            $newStatus    = $this->resolveStatus($newOccupancy, $capacity, $room['status_code']);

            $this->pdo->prepare("UPDATE rooms SET current_occupancy = :occ, status_code = :status WHERE room_id = :id")
                      ->execute([':occ' => $newOccupancy, ':status' => $newStatus, ':id' => $roomId]);

            // Chỉ commit nếu chính hàm này mở Transaction
            if ($isMyTransaction) $this->pdo->commit();

            return ['success' => true, 'message' => "Xếp thành công.", 'empty_beds' => $capacity - $newOccupancy, 'status_code' => $newStatus];

        } catch (PDOException $e) {
            if ($isMyTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }

    // SV RỜI PHÒNG (Xử lý Transaction an toàn)
    public function removeStudentFromRoom(int $roomId): array
    {
        $isMyTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isMyTransaction = true;
            }

            $room = $this->getRoomById($roomId);
            if (!$room) {
                if ($isMyTransaction) $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Không tìm thấy phòng.'];
            }

            $capacity = (int)$room['capacity'];
            $current  = (int)$room['current_occupancy'];

            $newOccupancy = max(0, $current - 1);
            $newStatus    = $this->resolveStatus($newOccupancy, $capacity, $room['status_code']);

            $this->pdo->prepare("UPDATE rooms SET current_occupancy = :occ, status_code = :status WHERE room_id = :id")
                      ->execute([':occ' => $newOccupancy, ':status' => $newStatus, ':id' => $roomId]);

            if ($isMyTransaction) $this->pdo->commit();

            return ['success' => true, 'message' => "Cập nhật thành công.", 'empty_beds' => $capacity - $newOccupancy, 'status_code' => $newStatus];

        } catch (PDOException $e) {
            if ($isMyTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }

    public function setRoomMaintenance(int $roomId, string $note = ''): array
    {
        try {
            $this->pdo->prepare("UPDATE rooms SET status_code = 'maintenance', notes = :note WHERE room_id = :id")
                      ->execute([':note' => $note !== '' ? $note : 'Đang bảo trì', ':id' => $roomId]);
            return ['success' => true, 'message' => 'Đã chuyển sang bảo trì.'];
        } catch (PDOException $e) { return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]; }
    }

    public function reopenRoom(int $roomId): array
    {
        try {
            $room = $this->getRoomById($roomId);
            if (!$room) return ['success' => false, 'message' => 'Không tìm thấy phòng.'];

            $newStatus = ((int)$room['current_occupancy'] >= (int)$room['capacity']) ? 'full' : 'available';
            $this->pdo->prepare("UPDATE rooms SET status_code = :status WHERE room_id = :id")->execute([':status' => $newStatus, ':id' => $roomId]);
            return ['success' => true, 'message' => "Đã mở lại phòng."];
        } catch (PDOException $e) { return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]; }
    }
}
?>