<?php
// ============================================================
//  InvoiceController.php — Xử lý logic Hóa đơn & Điện nước
//  Task 4: Tính tiền điện nước từ chỉ số, cập nhật trạng thái thanh toán
//  Task 5: Áp dụng SQL Transaction an toàn
// ============================================================

class InvoiceController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ============================================================
    // TASK 4 & 5: TẠO HÓA ĐƠN TỪ CHỈ SỐ ĐIỆN NƯỚC (TRANSACTION)
    // ============================================================
    public function generateInvoiceFromReading(
        int $readingId, 
        float $serviceFee = 50000, 
        string $dueDate
    ): array {
        try {
            // TASK 5: BẮT ĐẦU TRANSACTION ĐỂ BẢO VỆ DỮ LIỆU
            $this->pdo->beginTransaction();

            // 1. Lấy chỉ số ghi điện nước
            $stmt = $this->pdo->prepare("
                SELECT room_id, reading_month, 
                       electricity_prev, electricity_curr, electricity_rate,
                       water_prev, water_curr, water_rate, is_invoiced
                FROM utility_readings 
                WHERE reading_id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $readingId]);
            $reading = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reading) {
                throw new Exception('Không tìm thấy bản ghi chỉ số điện nước.');
            }
            if ($reading['is_invoiced']) {
                throw new Exception('Chỉ số tháng này đã được xuất hóa đơn, không thể xuất đúp!');
            }

            $roomId = (int)$reading['room_id'];
            $month  = $reading['reading_month'];

            // 2. Tính toán lượng tiêu thụ
            $elecUsed = max(0, $reading['electricity_curr'] - $reading['electricity_prev']);
            $waterUsed = max(0, $reading['water_curr'] - $reading['water_prev']);
            
            $elecRate = (float)$reading['electricity_rate'];
            $waterRate = (float)$reading['water_rate'];

            $elecFee = $elecUsed * $elecRate;
            $waterFee = $waterUsed * $waterRate;

            // 3. Tìm hợp đồng đang active của phòng này
            $stmtContract = $this->pdo->prepare("
                SELECT contract_id, student_id, monthly_fee_snapshot 
                FROM contracts 
                WHERE room_id = :rid AND status_code = 'active'
                ORDER BY start_date ASC LIMIT 1
            ");
            $stmtContract->execute([':rid' => $roomId]);
            $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);

            if (!$contract) {
                throw new Exception('Phòng này hiện không có hợp đồng thuê nào đang hoạt động.');
            }

            $roomFee = (float)$contract['monthly_fee_snapshot'];
            $totalAmount = $roomFee + $elecFee + $waterFee + $serviceFee;
            $invoiceCode = 'INV-' . date('ym') . '-R' . $roomId . '-' . rand(100, 999);

            // 4. Insert dòng hóa đơn mới
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO invoices (
                    contract_id, student_id, invoice_code, billing_month, 
                    room_fee, electricity_kwh, electricity_rate, electricity_fee,
                    water_m3, water_rate, water_fee, service_fee, 
                    total_amount, due_date, status_code
                ) VALUES (
                    :cid, :sid, :code, :month,
                    :room_fee, :e_kwh, :e_rate, :e_fee,
                    :w_m3, :w_rate, :w_fee, :s_fee,
                    :total, :due, 'unpaid'
                )
            ");
            $stmtInsert->execute([
                ':cid'      => $contract['contract_id'],
                ':sid'      => $contract['student_id'], 
                ':code'     => $invoiceCode,
                ':month'    => $month,
                ':room_fee' => $roomFee,
                ':e_kwh'    => $elecUsed,
                ':e_rate'   => $elecRate,
                ':e_fee'    => $elecFee,
                ':w_m3'     => $waterUsed,
                ':w_rate'   => $waterRate,
                ':w_fee'    => $waterFee,
                ':s_fee'    => $serviceFee,
                ':total'    => $totalAmount,
                ':due'      => $dueDate
            ]);

            // 5. Đánh dấu bản ghi điện nước đã lên hóa đơn
            $this->pdo->prepare("UPDATE utility_readings SET is_invoiced = 1 WHERE reading_id = :id")
                      ->execute([':id' => $readingId]);

            // TASK 5: NẾU MỌI THỨ OK -> CHỐT GIAO DỊCH
            $this->pdo->commit();

            return [
                'success' => true, 
                'message' => 'Lập hóa đơn thành công! Tổng tiền: ' . number_format($totalAmount, 0, ',', '.') . ' VND'
            ];

        } catch (Exception $e) {
            // TASK 5: NẾU LỖI -> HỦY GIAO DỊCH, KHÔNG LƯU DỮ LIỆU RÁC VÀO DB
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ============================================================
    // TASK 4: XÁC NHẬN THANH TOÁN (Unpaid -> Paid)
    // ============================================================
    public function markAsPaid(int $invoiceId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE invoices 
                SET status_code = 'paid', paid_date = CURDATE()
                WHERE invoice_id = :id AND status_code = 'unpaid'
            ");
            $stmt->execute([':id' => $invoiceId]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Đã xác nhận thanh toán hóa đơn.'];
            } else {
                return ['success' => false, 'message' => 'Hóa đơn này không tồn tại hoặc đã được thanh toán trước đó.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()];
        }
    }
}
?>