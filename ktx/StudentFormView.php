<?php
// ============================================================
//  StudentFormView.php — Form thêm / sửa sinh viên
//  Thêm mới: INSERT vào users + students
//  Sửa:      UPDATE users + students
// ============================================================

$host    = 'localhost';
$db      = 'campus_final';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<p style="color:red">Kết nối DB thất bại: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT)
          ?: filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$isEdit    = (bool)$studentId;
$pageTitle = $isEdit ? 'Sửa thông tin sinh viên' : 'Thêm sinh viên mới';

$old = [];
if ($isEdit) {
    $stmtGet = $pdo->prepare("
        SELECT s.*, u.email, u.phone, u.username
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.student_id = ?
    ");
    $stmtGet->execute([$studentId]);
    $old = $stmtGet->fetch();
    if (!$old) {
        header('Location: StudentListView.php');
        exit;
    }
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

    if ($fullName === '') $errors['full_name'] = 'Họ tên không được để trống.';
    if ($studentCode === '') $errors['student_code'] = 'Mã sinh viên không được để trống.';
    if (!in_array($gender, ['male','female','other'])) $errors['gender'] = 'Vui lòng chọn giới tính.';
    if (!preg_match('/^[\w.+\-]+@(gmail\.com|[\w\-]+\.edu\.vn)$/i', $email)) $errors['email'] = 'Email phải có đuôi @gmail.com hoặc @trường.edu.vn';
    if (!preg_match('/^0[0-9]{9}$/', $phone)) $errors['phone'] = 'Số điện thoại phải đủ 10 số và bắt đầu bằng 0.';
    if (!preg_match('/^0[0-9]{11}$/', $idCard)) $errors['id_card'] = 'Số CCCD phải đúng 12 chữ số và bắt đầu bằng 0.';
    if ($dob === '') $errors['date_of_birth'] = 'Vui lòng nhập ngày sinh.';
    if ($faculty === '') $errors['faculty'] = 'Vui lòng nhập khoa.';
    if ($major === '') $errors['major'] = 'Vui lòng nhập ngành.';
    if (!preg_match('/^[0-9]{4}$/', $intakeYear)) $errors['intake_year'] = 'Năm nhập học phải là 4 chữ số.';
    if ($className === '') $errors['class_name'] = 'Vui lòng nhập lớp.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $pdo->prepare("
                    UPDATE users SET email = ?, phone = ? WHERE user_id = (
                        SELECT user_id FROM students WHERE student_id = ?
                    )
                ")->execute([$email, $phone, $studentId]);

                $pdo->prepare("
                    UPDATE students SET
                        full_name      = ?,
                        student_code   = ?,
                        gender         = ?,
                        id_card        = ?,
                        date_of_birth  = ?,
                        faculty        = ?,
                        major          = ?,
                        intake_year    = ?,
                        class_name     = ?,
                        hometown       = ?,
                        status_code    = ?
                    WHERE student_id = ?
                ")->execute([
                    $fullName, $studentCode, $gender, $idCard,
                    $dob, $faculty, $major, $intakeYear,
                    $className, $hometown, $statusCode, $studentId
                ]);

                $pdo->commit();
                $success = 'Cập nhật thông tin sinh viên thành công!';
                $stmtGet->execute([$studentId]);
                $old = $stmtGet->fetch();
            } else {
                $username = 'sv_' . strtolower($studentCode);
                $pdo->prepare("
                    INSERT INTO users (username, password, email, full_name, phone, role)
                    VALUES (?, ?, ?, ?, ?, 'student')
                ")->execute([
                    $username,
                    password_hash('KTX@' . $studentCode, PASSWORD_BCRYPT),
                    $email, $fullName, $phone
                ]);
                $newUserId = $pdo->lastInsertId();

                $pdo->prepare("
                    INSERT INTO students
                        (user_id, student_code, full_name, date_of_birth, gender,
                         id_card, faculty, major, intake_year, class_name, hometown, status_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $newUserId, $studentCode, $fullName, $dob, $gender,
                    $idCard, $faculty, $major, $intakeYear, $className, $hometown, $statusCode
                ]);

                $pdo->commit();
                header('Location: StudentListView.php');
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'email')) $errors['email'] = 'Email này đã được sử dụng.';
                elseif (str_contains($e->getMessage(), 'phone')) $errors['phone'] = 'Số điện thoại này đã được sử dụng.';
                elseif (str_contains($e->getMessage(), 'id_card')) $errors['id_card'] = 'Số CCCD này đã tồn tại.';
                elseif (str_contains($e->getMessage(), 'student_code')) $errors['student_code'] = 'Mã sinh viên này đã tồn tại.';
                else $errors['general'] = 'Dữ liệu bị trùng lặp: ' . $e->getMessage();
            } else { $errors['general'] = 'Lỗi database: ' . $e->getMessage(); }
        }
    }
}
function val(string $key, array $old): string { return htmlspecialchars($_POST[$key] ?? $old[$key] ?? ''); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?> — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#0ea5a4;--primary-lt:#e0f2f1;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;
    --red:#dc2626;--red-lt:#fee2e2;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:760px;margin:0 auto;padding:28px 20px}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:16px}
.breadcrumb a{color:var(--primary);text-decoration:none}.breadcrumb a:hover{text-decoration:underline}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:24px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}
.form-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.form-section{padding:20px 24px;border-bottom:1px solid var(--border)}
.form-section:last-child{border-bottom:none}
.section-title{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:13px;font-weight:600;color:var(--text)}
.field label .req{color:var(--red);margin-left:2px}
.field input,.field select{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:14px;color:var(--text);background:#fff;outline:none;transition:border-color .15s;font-family:inherit;}
.field input:focus,.field select:focus{border-color:var(--primary)}
.field input.err,.field select.err{border-color:var(--red);background:var(--red-lt)}
.field-hint{font-size:11px;color:var(--muted)}
.field-error{font-size:12px;color:var(--red);font-weight:500}
.alert{padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.alert-success{background:var(--green-lt);color:var(--green);border:1px solid #bbf7d0}
.alert-error{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}
.form-actions{display:flex;gap:12px;align-items:center;padding:20px 24px;border-top:1px solid var(--border)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#0b8483}
.btn-secondary{background:#f1f5f9;color:var(--muted)}.btn-secondary:hover{background:var(--border)}
@media(max-width:600px){nav{display:none};.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'BuildingListView.php' || basename($_SERVER['PHP_SELF']) == 'RoomDetailView.php' ? 'active' : '' ?>">Tòa nhà</a>
        <a href="StudentListView.php"  class="<?= basename($_SERVER['PHP_SELF']) == 'StudentListView.php' || basename($_SERVER['PHP_SELF']) == 'StudentFormView.php' ? 'active' : '' ?>">Sinh viên</a>
        <a href="ContractListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ContractListView.php' || basename($_SERVER['PHP_SELF']) == 'ContractFormView.php' ? 'active' : '' ?>">Hợp đồng</a>
        <a href="InvoiceView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'InvoiceView.php' ? 'active' : '' ?>">Hóa đơn</a>
        <a href="#">Vi phạm</a>
    </nav>
</header>

<main class="page">
    <div class="breadcrumb">
        <a href="StudentListView.php">👥 Sinh viên</a>
        <span>›</span>
        <span><?= $pageTitle ?></span>
    </div>

    <h1 class="page-title"><?= $pageTitle ?></h1>

    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if ($isEdit): ?><input type="hidden" name="student_id" value="<?= $studentId ?>"><?php endif; ?>
        <input type="hidden" name="save" value="1">

        <div class="form-card">
            <div class="form-section">
                <div class="section-title">📋 Thông tin cá nhân</div>
                <div class="form-grid">
                    <div class="field" style="grid-column:1/-1">
                        <label>Họ và tên<span class="req">*</span></label>
                        <input type="text" name="full_name" value="<?= val('full_name', $old) ?>" class="<?= isset($errors['full_name']) ? 'err' : '' ?>" placeholder="Nguyễn Văn A">
                        <?php if (isset($errors['full_name'])): ?><div class="field-error">⚠ <?= $errors['full_name'] ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Mã sinh viên (MSSV)<span class="req">*</span></label>
                        <input type="text" name="student_code" value="<?= val('student_code', $old) ?>" class="<?= isset($errors['student_code']) ? 'err' : '' ?>" placeholder="2151012001" <?= $isEdit ? 'readonly style="background:#f8fafc"' : '' ?>>
                        <?php if (isset($errors['student_code'])): ?><div class="field-error">⚠ <?= $errors['student_code'] ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Giới tính<span class="req">*</span></label>
                        <select name="gender" class="<?= isset($errors['gender']) ? 'err' : '' ?>">
                            <option value="">-- Chọn --</option>
                            <?php foreach (['male'=>'Nam','female'=>'Nữ','other'=>'Khác'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['gender'] ?? $old['gender'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Ngày sinh<span class="req">*</span></label>
                        <input type="date" name="date_of_birth" value="<?= val('date_of_birth', $old) ?>" class="<?= isset($errors['date_of_birth']) ? 'err' : '' ?>">
                    </div>
                    <div class="field">
                        <label>Số CCCD<span class="req">*</span></label>
                        <input type="text" name="id_card" value="<?= val('id_card', $old) ?>" class="<?= isset($errors['id_card']) ? 'err' : '' ?>" placeholder="079203000101" maxlength="12">
                        <div class="field-hint">Đúng 12 chữ số</div>
                    </div>
                    <div class="field">
                        <label>Quê quán</label>
                        <input type="text" name="hometown" value="<?= val('hometown', $old) ?>" placeholder="Hà Nội">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">📞 Thông tin liên hệ</div>
                <div class="form-grid">
                    <div class="field">
                        <label>Email<span class="req">*</span></label>
                        <input type="email" name="email" value="<?= val('email', $old) ?>" class="<?= isset($errors['email']) ? 'err' : '' ?>" placeholder="example@gmail.com">
                    </div>
                    <div class="field">
                        <label>Số điện thoại<span class="req">*</span></label>
                        <input type="text" name="phone" value="<?= val('phone', $old) ?>" class="<?= isset($errors['phone']) ? 'err' : '' ?>" placeholder="0912345678" maxlength="10">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">🎓 Thông tin học tập</div>
                <div class="form-grid">
                    <div class="field">
                        <label>Khoa<span class="req">*</span></label>
                        <input type="text" name="faculty" value="<?= val('faculty', $old) ?>" class="<?= isset($errors['faculty']) ? 'err' : '' ?>">
                    </div>
                    <div class="field">
                        <label>Ngành<span class="req">*</span></label>
                        <input type="text" name="major" value="<?= val('major', $old) ?>" class="<?= isset($errors['major']) ? 'err' : '' ?>">
                    </div>
                    <div class="field">
                        <label>Năm nhập học<span class="req">*</span></label>
                        <input type="number" name="intake_year" value="<?= val('intake_year', $old) ?>" class="<?= isset($errors['intake_year']) ? 'err' : '' ?>">
                    </div>
                    <div class="field">
                        <label>Lớp<span class="req">*</span></label>
                        <input type="text" name="class_name" value="<?= val('class_name', $old) ?>" class="<?= isset($errors['class_name']) ? 'err' : '' ?>">
                    </div>
                    <div class="field">
                        <label>Trạng thái</label>
                        <select name="status_code">
                            <?php foreach (['active'=>'Đang học','graduated'=>'Đã tốt nghiệp','suspended'=>'Bảo lưu','expelled'=>'Bị đuổi học'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['status_code'] ?? $old['status_code'] ?? 'active') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? '💾 Lưu thay đổi' : '➕ Thêm sinh viên' ?>
                </button>
                <a href="StudentListView.php" class="btn btn-secondary">✕ Hủy</a>
            </div>
        </div>
    </form>
</main>
</body>
</html>