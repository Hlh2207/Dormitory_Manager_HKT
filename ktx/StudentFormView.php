<?php
// ============================================================
//  StudentFormView.php — Add / Edit Student Form
// ============================================================

// ---------- 1. BẢO MẬT & SESSION (Đã thêm) ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Chặn người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}

require_once __DIR__ . '/Validator.php';

// ---------- 2. KẾT NỐI DATABASE ----------
$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>'); }

// ---------- 3. XỬ LÝ LOGIC ----------
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$isEdit    = (bool)$studentId;

$old = [];
if ($isEdit) {
    $stmtGet = $pdo->prepare("SELECT s.*, u.email, u.phone, u.username FROM students s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = ?");
    $stmtGet->execute([$studentId]);
    $old = $stmtGet->fetch();
    if (!$old) { header('Location: StudentListView.php'); exit; }
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $fullName    = trim($_POST['full_name']    ?? '');
    $studentCode = trim($_POST['student_code'] ?? '');
    $gender      = trim($_POST['gender']       ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $email       = trim($_POST['email']        ?? '');
    $idCard      = trim($_POST['id_card']      ?? '');
    $dob         = trim($_POST['date_of_birth']?? '');
    $faculty     = trim($_POST['faculty']      ?? '');
    $major       = trim($_POST['major']        ?? '');
    $intakeYear  = trim($_POST['intake_year']  ?? '');
    $className   = trim($_POST['class_name']   ?? '');
    $hometown    = trim($_POST['hometown']     ?? '');
    $statusCode  = trim($_POST['status_code']  ?? 'active');

    $errors = Validator::validateStudentForm($_POST);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE user_id = (SELECT user_id FROM students WHERE student_id = ?)")->execute([$email, $phone, $studentId]);
                $pdo->prepare("UPDATE students SET full_name=?, student_code=?, gender=?, id_card=?, date_of_birth=?, faculty=?, major=?, intake_year=?, class_name=?, hometown=?, status_code=? WHERE student_id=?")
                    ->execute([$fullName, $studentCode, $gender, $idCard, $dob, $faculty, $major, $intakeYear, $className, $hometown, $statusCode, $studentId]);
                $pdo->commit();
                $success = 'Student information updated successfully!';
                $stmtGet->execute([$studentId]); $old = $stmtGet->fetch();
            } else {
                $username = 'sv_' . strtolower($studentCode);
                $pdo->prepare("INSERT INTO users (username, password, email, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'student')")
                    ->execute([$username, password_hash('KTX@' . $studentCode, PASSWORD_BCRYPT), $email, $fullName, $phone]);
                $newUserId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO students (user_id, student_code, full_name, date_of_birth, gender, id_card, faculty, major, intake_year, class_name, hometown, status_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$newUserId, $studentCode, $fullName, $dob, $gender, $idCard, $faculty, $major, $intakeYear, $className, $hometown, $statusCode]);

                $pdo->commit();
                header('Location: StudentListView.php'); exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'email')) $errors['email'] = 'This email is already in use.';
                elseif (str_contains($e->getMessage(), 'phone')) $errors['phone'] = 'This phone number is already in use.';
                elseif (str_contains($e->getMessage(), 'id_card')) $errors['id_card'] = 'This ID Card number already exists.';
                elseif (str_contains($e->getMessage(), 'student_code')) $errors['student_code'] = 'This Student ID already exists.';
                else $errors['general'] = 'Duplicate data: ' . $e->getMessage();
            } else {
                $errors['general'] = 'Database Error: ' . $e->getMessage();
            }
        }
    }
}

function val(string $key, array $old): string { return htmlspecialchars($_POST[$key] ?? $old[$key] ?? ''); }

// ---------- 4. GỌI HEADER CHUNG ----------
$pageTitle = $isEdit ? 'Edit Student Profile' : 'Add New Student';
include 'header.php';
?>

<main class="page" style="max-width:760px;">
    <div class="breadcrumb">
        <a href="StudentListView.php">👥 Students</a>
        <span>›</span>
        <span><?= $pageTitle ?></span>
    </div>

    <h1 class="page-title"><?= $pageTitle ?></h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (!empty($errors['general'])): ?><div class="alert alert-error">❌ <?= htmlspecialchars($errors['general']) ?></div><?php endif; ?>
    <?php if (!empty($errors) && !isset($errors['general'])): ?><div class="alert alert-error">❌ Please check the highlighted fields below.</div><?php endif; ?>

    <form method="POST">
        <?php if ($isEdit): ?><input type="hidden" name="student_id" value="<?= $studentId ?>"><?php endif; ?>
        <input type="hidden" name="save" value="1">

        <div class="card">
            <div class="card-header"><h2>📋 Personal Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?= val('full_name', $old) ?>" class="form-control" placeholder="Nguyen Van A">
                        <?php if (isset($errors['full_name'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['full_name'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Student ID <span class="required">*</span></label>
                        <input type="text" name="student_code" value="<?= val('student_code', $old) ?>" class="form-control" placeholder="2151012001" <?= $isEdit ? 'readonly' : '' ?>>
                        <?php if (isset($errors['student_code'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['student_code'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender <span class="required">*</span></label>
                        <select name="gender" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['gender'] ?? $old['gender'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['gender'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['gender'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" value="<?= val('date_of_birth', $old) ?>" class="form-control">
                        <?php if (isset($errors['date_of_birth'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['date_of_birth'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ID Card Number <span class="required">*</span></label>
                        <input type="text" name="id_card" value="<?= val('id_card', $old) ?>" class="form-control" placeholder="079203000101" maxlength="12">
                        <?php if (isset($errors['id_card'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['id_card'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hometown</label>
                        <input type="text" name="hometown" value="<?= val('hometown', $old) ?>" class="form-control" placeholder="Hanoi">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>📞 Contact Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" value="<?= val('email', $old) ?>" class="form-control" placeholder="example@gmail.com">
                        <?php if (isset($errors['email'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['email'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="text" name="phone" value="<?= val('phone', $old) ?>" class="form-control" placeholder="0912345678" maxlength="10">
                        <?php if (isset($errors['phone'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['phone'] ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>🎓 Academic Details</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Faculty <span class="required">*</span></label>
                        <input type="text" name="faculty" value="<?= val('faculty', $old) ?>" class="form-control">
                        <?php if (isset($errors['faculty'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['faculty'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Major <span class="required">*</span></label>
                        <input type="text" name="major" value="<?= val('major', $old) ?>" class="form-control">
                        <?php if (isset($errors['major'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['major'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Intake Year <span class="required">*</span></label>
                        <input type="number" name="intake_year" value="<?= val('intake_year', $old) ?>" class="form-control" min="2000" max="2099">
                        <?php if (isset($errors['intake_year'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['intake_year'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Class <span class="required">*</span></label>
                        <input type="text" name="class_name" value="<?= val('class_name', $old) ?>" class="form-control">
                        <?php if (isset($errors['class_name'])): ?><div style="color:var(--red);font-size:12px">⚠ <?= $errors['class_name'] ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status_code" class="form-control">
                            <?php foreach (['active'=>'Active','graduated'=>'Graduated','suspended'=>'Suspended','expelled'=>'Expelled'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['status_code'] ?? $old['status_code'] ?? 'active') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '💾 Save Changes' : '➕ Add Student' ?></button>
            <a href="StudentListView.php" class="btn btn-ghost">✕ Cancel</a>
            <?php if ($isEdit): ?>
            <span style="font-size:12px;color:var(--muted);margin-left:auto">
                Default Password: <code>KTX@<?= val('student_code', $old) ?></code>
            </span>
            <?php endif; ?>
        </div>
    </form>
</main>

<?php 
?>