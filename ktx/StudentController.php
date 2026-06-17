<?php
// ============================================================
//  StudentController.php — Student CRUD Handling
//  Connects to: students, users, contracts, rooms
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

    public function insertStudent(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $username = 'sv_' . strtolower($data['student_code']);
            $defaultPassword = password_hash('KTX@' . $data['student_code'], PASSWORD_BCRYPT);

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

            return ['success' => true, 'message' => 'Student added successfully.', 'student_id' => $newStudentId];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    public function updateStudent(int $studentId, array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE users SET email = :email, phone = :phone
                WHERE user_id = (SELECT user_id FROM students WHERE student_id = :sid)
            ");
            $stmt->execute([
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':sid'   => $studentId,
            ]);

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
            return ['success' => true, 'message' => 'Student information updated successfully.'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    public function deleteStudent(int $studentId): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT c.contract_id, c.room_id
                FROM contracts c
                WHERE c.student_id = :sid AND c.status_code = 'active' LIMIT 1
            ");
            $stmt->execute([':sid' => $studentId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($contract && $contract['room_id']) {
                $roomResult = $this->roomController->removeStudentFromRoom((int)$contract['room_id']);
                if (!$roomResult['success']) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Cannot update room: ' . $roomResult['message']];
                }
                $this->pdo->prepare("UPDATE contracts SET status_code = 'terminated', end_date = CURDATE() WHERE contract_id = :cid")
                          ->execute([':cid' => $contract['contract_id']]);
            }

            $stmt = $this->pdo->prepare("SELECT user_id FROM students WHERE student_id = :id");
            $stmt->execute([':id' => $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Student not found.'];
            }

            $this->pdo->prepare("DELETE FROM students WHERE student_id = :id")->execute([':id' => $studentId]);
            $this->pdo->prepare("DELETE FROM users WHERE user_id = :uid")->execute([':uid' => $student['user_id']]);

            $this->pdo->commit();

            $msg = 'Student deleted successfully.';
            if ($contract) $msg .= ' Room availability updated.';

            return ['success' => true, 'message' => $msg];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->translateDbError($e)];
        }
    }

    private function translateDbError(PDOException $e): string
    {
        if ($e->getCode() === '23000') {
            $msg = $e->getMessage();
            if (str_contains($msg, 'email'))        return 'This email is already in use.';
            if (str_contains($msg, 'phone'))        return 'This phone number is already in use.';
            if (str_contains($msg, 'id_card'))      return 'This ID Card number already exists.';
            if (str_contains($msg, 'student_code')) return 'This Student ID already exists.';
            if (str_contains($msg, 'username'))     return 'Username already exists.';
            return 'Duplicate data, please check again.';
        }
        return 'Database Error: ' . $e->getMessage();
    }
}

// XỬ LÝ REQUEST
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    $host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB Connection Failed: ' . $e->getMessage()]); exit;
    }
    $controller = new StudentController($pdo);
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    switch ($action) {
        case 'list':   echo json_encode(['success' => true, 'data' => $controller->getAllStudents(trim($_GET['search'] ?? ''))]); break;
        case 'get':    echo json_encode(['success' => true, 'data' => $controller->getStudentById(filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT))]); break;
        case 'insert': echo json_encode($controller->insertStudent($_POST)); break;
        case 'update': echo json_encode($controller->updateStudent(filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT), $_POST)); break;
        case 'delete': echo json_encode($controller->deleteStudent(filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT))); break;
        default:       echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
}