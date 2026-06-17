<?php
// ============================================================
//  StudentController.php — Xử lý CRUD sinh viên
//  Kết nối bảng: students, users, contracts, rooms
//
//  CHỨC NĂNG CHÍNH:
//   1. getAllStudents()   — lấy danh sách (kèm tìm kiếm)
//   2. getStudentById()   — lấy 1 sinh viên
//   3. insertStudent()    — thêm mới (INSERT users + students)
//   4. updateStudent()    — sửa (UPDATE users + students)
//   5. deleteStudent()    — xóa + cập nhật lại giường trống của phòng
// ============================================================

require_once __DIR__ . '/RoomController.php';

class StudentController
{
    private PDO $pdo;
    private RoomController $roomController;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->roomController = new RoomController($pdo);
    }

    // ============================================================
    // 1. LẤY DANH SÁCH SINH VIÊN (có tìm kiếm — dùng PDO Prepared Statement)
    // ============================================================
    public function getAllStudents(string $search = ''): array
    {
        $sql = "
            SELECT
                s.student_id, s.student_code, s.full_name, s.gender,
                s.id_card, s.faculty, s.major, s.intake_year,
                s.class_name, s.status_code,
                u.email, u.phone
            FROM students s
            JOIN users u ON u.user_id = s.user_id
        ";

        $params = [];
        if ($search !== '') {
            $sql .= " WHERE s.full_name LIKE :s1
                       OR s.student_code LIKE :s2
                       OR u.email LIKE :s3
                       OR u.phone LIKE :s4";
            $like = '%' . $search . '%';
            $params = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
        }
        $sql .= " ORDER BY s.student_id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // 2. LẤY 1 SINH VIÊN THEO ID
    // ============================================================
    public function getStudentById(int $studentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.email, u.phone, u.username
            FROM students s
            JOIN users u ON u.user_id = s.user_id
            WHERE s.student_id = :id
        ");
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ============================================================
    // 3. THÊM SINH VIÊN MỚI
    //    - INSERT vào users trước (lấy user_id)
    //    - INSERT vào students với user_id vừa tạo
    // ============================================================
    public function insertStudent(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Sinh username + mật khẩu mặc định từ mã SV
            $username = 'sv_' . strtolower($data['student_code']);
            $defaultPassword = password_hash('KTX@' . $data['student_code'], PASSWORD_BCRYPT);

            // 3.1 INSERT users
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, email, full_name, phone, role)
                VALUES (:username, :password, :email, :full_name, :phone, 'student')
            ");
            $stmt->execute([
                ':username'  => $username,
                ':password'  => $defaultPassword,
                ':email'     => $data['email'],
                ':full_name' => $data['full_name'],
                ':phone'     => $data['phone'],
            ]);
            $userId = $this->pdo->lastInsertId();

            // 3.2 INSERT students
            $stmt = $this->pdo->prepare("
                INSERT INTO students
                    (user_id, student_code, full_name, date_of_birth, gender,
                     id_card, faculty, major, intake_year, class_name, hometown, status_code)
                VALUES
                    (:user_id, :student_code, :full_name, :dob, :gender,
                     :id_card, :faculty, :major, :intake_year, :class_name, :hometown, :status_code)
            ");
            $stmt->execute([
                ':user_id'      => $userId,
                ':student_code' => $data['student_code'],
                ':full_name'    => $data['full_name'],
                ':dob'          => $data['date_of_birth'],
                ':gender'       => $data['gender'],
                ':id_card'      => $data['id_card'],
                ':faculty'      => $data['faculty'],
                ':major'        => $data['major'],
                ':intake_year'  => $data['intake_year'],
                ':class_name'   => $data['class_name'],
                ':hometown'     => $data['hometown'] ?? null,
                ':status_code'  => $data['status_code'] ?? 'active',
            ]);

            $newStudentId = $this->pdo->lastInsertId();
            $this->pdo->commit();

            return ['success' => true, 'message' => 'Thêm sinh viên thành công.', 'student_id' => $newStudentId];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    // ============================================================
    // 4. CẬP NHẬT SINH VIÊN
    //    - UPDATE users (email, phone)
    //    - UPDATE students (các trường còn lại)
    // ============================================================
    public function updateStudent(int $studentId, array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // 4.1 UPDATE users — lấy user_id qua student_id
            $stmt = $this->pdo->prepare("
                UPDATE users SET email = :email, phone = :phone
                WHERE user_id = (SELECT user_id FROM students WHERE student_id = :sid)
            ");
            $stmt->execute([
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':sid'   => $studentId,
            ]);

            // 4.2 UPDATE students
            $stmt = $this->pdo->prepare("
                UPDATE students SET
                    full_name     = :full_name,
                    date_of_birth = :dob,
                    gender        = :gender,
                    id_card       = :id_card,
                    faculty       = :faculty,
                    major         = :major,
                    intake_year   = :intake_year,
                    class_name    = :class_name,
                    hometown      = :hometown,
                    status_code   = :status_code
                WHERE student_id = :id
            ");
            $stmt->execute([
                ':full_name'    => $data['full_name'],
                ':dob'          => $data['date_of_birth'],
                ':gender'       => $data['gender'],
                ':id_card'      => $data['id_card'],
                ':faculty'      => $data['faculty'],
                ':major'        => $data['major'],
                ':intake_year'  => $data['intake_year'],
                ':class_name'   => $data['class_name'],
                ':hometown'     => $data['hometown'] ?? null,
                ':status_code'  => $data['status_code'] ?? 'active',
                ':id'           => $studentId,
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Cập nhật thông tin sinh viên thành công.'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    // ============================================================
    // 5. XÓA SINH VIÊN
    //    BƯỚC QUAN TRỌNG:
    //    a) Tìm phòng sinh viên đang ở (qua contracts đang active)
    //    b) Gọi RoomController để CỘNG LẠI giường trống của phòng đó
    //    c) Mới xóa sinh viên khỏi bảng students
    //       (users / contracts liên quan sẽ tự xóa theo do ON DELETE CASCADE
    //        — nếu DB không cấu hình cascade, ta xóa thủ công ở dưới)
    // ============================================================
    public function deleteStudent(int $studentId): array
    {
        try {
            $this->pdo->beginTransaction();

            // 5.1 Tìm phòng hiện tại của sinh viên (hợp đồng đang active)
            $stmt = $this->pdo->prepare("
                SELECT c.contract_id, c.room_id
                FROM contracts c
                WHERE c.student_id = :sid
                  AND c.status_code = 'active'
                LIMIT 1
            ");
            $stmt->execute([':sid' => $studentId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            // 5.2 Nếu sinh viên đang ở phòng nào đó → cộng lại giường trống
            if ($contract && $contract['room_id']) {
                $roomResult = $this->roomController->removeStudentFromRoom((int)$contract['room_id']);
                if (!$roomResult['success']) {
                    // Nếu cập nhật phòng lỗi, hủy toàn bộ giao dịch
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Không thể cập nhật phòng: ' . $roomResult['message']];
                }

                // Đóng hợp đồng (đánh dấu kết thúc thay vì xóa hẳn, để giữ lịch sử)
                $this->pdo->prepare("
                    UPDATE contracts SET status_code = 'terminated', end_date = CURDATE()
                    WHERE contract_id = :cid
                ")->execute([':cid' => $contract['contract_id']]);
            }

            // 5.3 Lấy user_id để xóa kèm theo (nếu DB không cascade)
            $stmt = $this->pdo->prepare("SELECT user_id FROM students WHERE student_id = :id");
            $stmt->execute([':id' => $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Không tìm thấy sinh viên.'];
            }

            // 5.4 Xóa sinh viên
            $this->pdo->prepare("DELETE FROM students WHERE student_id = :id")
                       ->execute([':id' => $studentId]);

            // 5.5 Xóa user liên kết (nếu bảng users không tự cascade)
            $this->pdo->prepare("DELETE FROM users WHERE user_id = :uid")
                       ->execute([':uid' => $student['user_id']]);

            $this->pdo->commit();

            $msg = 'Xóa sinh viên thành công.';
            if ($contract) {
                $msg .= ' Đã cập nhật lại số giường trống của phòng.';
            }

            return ['success' => true, 'message' => $msg];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    // ============================================================
    // HÀM PHỤ: Dịch lỗi DB (trùng UNIQUE...) sang tiếng Việt dễ hiểu
    // ============================================================
    private function translateDbError(PDOException $e): string
    {
        if ($e->getCode() === '23000') {
            $msg = $e->getMessage();
            if (str_contains($msg, 'email'))        return 'Email này đã được sử dụng.';
            if (str_contains($msg, 'phone'))         return 'Số điện thoại này đã được sử dụng.';
            if (str_contains($msg, 'id_card'))       return 'Số CCCD này đã tồn tại.';
            if (str_contains($msg, 'student_code'))  return 'Mã sinh viên này đã tồn tại.';
            if (str_contains($msg, 'username'))      return 'Tên đăng nhập đã tồn tại.';
            return 'Dữ liệu bị trùng lặp, vui lòng kiểm tra lại.';
        }
        return 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
    }
}


// ============================================================
//  XỬ LÝ REQUEST — gọi trực tiếp file này từ form / AJAX
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

    $controller = new StudentController($pdo);
    $action     = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {

        case 'list':
            $search = trim($_GET['search'] ?? '');
            echo json_encode(['success' => true, 'data' => $controller->getAllStudents($search)]);
            break;

        case 'get':
            $id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
            echo json_encode(['success' => true, 'data' => $controller->getStudentById($id)]);
            break;

        case 'insert':
            echo json_encode($controller->insertStudent($_POST));
            break;

        case 'update':
            $id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            echo json_encode($controller->updateStudent($id, $_POST));
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            echo json_encode($controller->deleteStudent($id));
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
    }
}
