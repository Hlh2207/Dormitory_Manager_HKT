<?php
// ============================================================
//  ContractController.php — Xử lý logic Hợp đồng & Đăng ký
//  Task 3 & Task 5: Duyệt đăng ký, quét hợp đồng hết hạn, SQL Transaction
// ============================================================

require_once __DIR__ . '/RoomController.php';

class ContractController
{
    private PDO $pdo;
    private RoomController $roomController;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->roomController = new RoomController($pdo);
    }

    public function approveRegistration(int $registrationId, string $startDate, string $endDate, float $depositAmount): array
    {
        try {
            $this->pdo->beginTransaction();

            // Lấy thông tin đơn
            $stmt = $this->pdo->prepare("
                SELECT r.student_id, r.room_id, r.status_code, rm.type_id, rt.price_per_month
                FROM room_registrations r
                JOIN rooms rm ON rm.room_id = r.room_id
                JOIN room_types rt ON rt.type_id = rm.type_id
                WHERE r.registration_id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $registrationId]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg) throw new Exception('Không tìm thấy đơn đăng ký.');
            if ($reg['status_code'] !== 'pending') throw new Exception('Đơn đăng ký này đã được xử lý.');

            $studentId  = (int)$reg['student_id'];
            $roomId     = (int)$reg['room_id'];
            $monthlyFee = (float)$reg['price_per_month']; 

            // Trừ giường
            $assignResult = $this->roomController->assignStudentToRoom($roomId);
            if (!$assignResult['success']) {
                throw new Exception('Không thể xếp phòng: ' . $assignResult['message']);
            }

            $contractCode = 'HD-' . date('Y') . '-S' . $studentId . '-R' . $roomId;

            // Chèn hợp đồng (Đã bổ sung registration_id để thỏa mãn Trigger của DB)
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO contracts (
                    contract_code, student_id, room_id, registration_id, start_date, end_date, 
                    deposit_amount, deposit_paid, monthly_fee_snapshot, status_code, signed_date
                ) VALUES (
                    :code, :sid, :rid, :reg_id, :start, :end, 
                    :deposit, 0, :fee, 'active', CURDATE()
                )
            ");
            $stmtInsert->execute([
                ':code'    => $contractCode,
                ':sid'     => $studentId,
                ':rid'     => $roomId,
                ':reg_id'  => $registrationId,
                ':start'   => $startDate,
                ':end'     => $endDate,
                ':deposit' => $depositAmount,
                ':fee'     => $monthlyFee
            ]);

            // Cập nhật trạng thái
            $this->pdo->prepare("UPDATE room_registrations SET status_code = 'approved' WHERE registration_id = :id")
                      ->execute([':id' => $registrationId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Đã duyệt đơn và tạo hợp đồng thành công!'];

        } catch (Exception $e) {
            // Kiểm tra an toàn trước khi rollback để tránh lỗi Fatal PDO
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function processExpiredContracts(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT contract_id, room_id FROM contracts WHERE status_code = 'active' AND end_date < CURDATE()");
            $stmt->execute();
            $expiredContracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processedCount = 0;

            foreach ($expiredContracts as $contract) {
                $this->pdo->beginTransaction();
                try {
                    $this->pdo->prepare("UPDATE contracts SET status_code = 'expired' WHERE contract_id = ?")->execute([$contract['contract_id']]);
                    $removeResult = $this->roomController->removeStudentFromRoom((int)$contract['room_id']);
                    
                    if (!$removeResult['success']) throw new Exception($removeResult['message']);
                    
                    $this->pdo->commit();
                    $processedCount++;
                } catch (Exception $ex) {
                    if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                }
            }
            return ['success' => true, 'message' => "Đã xử lý và giải phóng $processedCount hợp đồng hết hạn."];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }
}
?>