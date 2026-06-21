<?php
// ============================================================
//  StudentFormView.php — Add / Edit Student
//  Add: INSERT into users + students (+ optional contract/room assignment)
//  Edit: UPDATE users + students
//  Validation: Validator.php (Task 5 — Regex)
// ============================================================

require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/RoomController.php';

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>'); }

$roomController = new RoomController($pdo);

// ---------- MODE: add or edit ----------
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT)
          ?: filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$isEdit    = (bool)$studentId;

// ---------- LOAD EXISTING DATA IF EDIT ----------
$old = [];
$currentRoomId = null;
if ($isEdit) {
    $stmtGet = $pdo->prepare("
        SELECT s.*, u.email, u.phone, u.username
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.student_id = ?
    ");
    $stmtGet->execute([$studentId]);
    $old = $stmtGet->fetch();
    if (!$old) { header('Location: StudentListView.php'); exit; }

    // Tìm phòng hiện tại (nếu có hợp đồng active) để hiện sẵn trong dropdown khi sửa
    $stmtRoom = $pdo->prepare("SELECT room_id FROM contracts WHERE student_id = :sid AND status_code = 'active' LIMIT 1");
    $stmtRoom->execute([':sid' => $studentId]);
    $currentRoomId = $stmtRoom->fetchColumn() ?: null;
}

// ---------- HANDLE SUBMIT ----------
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
    $roomId      = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT) ?: null; // optional room assignment

    // ===== VALIDATION (Task 5 — Regex via Validator class) =====
    $errors = Validator::validateStudentForm($_POST);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // --- UPDATE ---
                $pdo->prepare("
                    UPDATE users SET email = ?, phone = ? WHERE user_id = (
                        SELECT user_id FROM students WHERE student_id = ?
                    )
                ")->execute([$email, $phone, $studentId]);

                $pdo->prepare("
                    UPDATE students SET
                        full_name = ?, student_code = ?, gender = ?, id_card = ?,
                        date_of_birth = ?, faculty = ?, major = ?, intake_year = ?,
                        class_name = ?, hometown = ?, status_code = ?
                    WHERE student_id = ?
                ")->execute([
                    $fullName, $studentCode, $gender, $idCard,
                    $dob, $faculty, $major, $intakeYear,
                    $className, $hometown, $statusCode, $studentId
                ]);

                $pdo->commit();
                $success = 'Student information updated successfully!';
                $stmtGet->execute([$studentId]);
                $old = $stmtGet->fetch();

            } else {
                // --- INSERT ---
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
                $newStudentId = $pdo->lastInsertId();

                // ---- OPTIONAL ROOM ASSIGNMENT ----
                if ($roomId) {
                    // Lấy giá phòng để snapshot vào hợp đồng
                    $stmtRoomInfo = $pdo->prepare("
                        SELECT rt.price_per_month
                        FROM rooms r JOIN room_types rt ON rt.type_id = r.type_id
                        WHERE r.room_id = :rid
                    ");
                    $stmtRoomInfo->execute([':rid' => $roomId]);
                    $roomInfo = $stmtRoomInfo->fetch();

                    if ($roomInfo) {
                        // Trừ giường trống / cập nhật trạng thái phòng (Task 3 logic)
                        $assignResult = $roomController->assignStudentToRoom($roomId);

                        if ($assignResult['success']) {
                            $contractCode = 'HD-' . date('Y') . '-S' . $newStudentId . '-R' . $roomId;
                            $startDate = date('Y-m-d');
                            $endDate   = date('Y-m-d', strtotime('+6 months'));

                            $pdo->prepare("
                                INSERT INTO contracts
                                    (contract_code, student_id, room_id, start_date, end_date,
                                     deposit_amount, deposit_paid, monthly_fee_snapshot, status_code, signed_date)
                                VALUES
                                    (:code, :sid, :rid, :start, :end, 0, 0, :fee, 'active', CURDATE())
                            ")->execute([
                                ':code'  => $contractCode,
                                ':sid'   => $newStudentId,
                                ':rid'   => $roomId,
                                ':start' => $startDate,
                                ':end'   => $endDate,
                                ':fee'   => $roomInfo['price_per_month'],
                            ]);
                        }
                        // Nếu assign thất bại (vd phòng đang maintenance), vẫn giữ SV được tạo,
                        // chỉ là chưa có phòng — admin có thể xếp phòng sau.
                    }
                }

                $pdo->commit();
                header('Location: StudentListView.php');
                exit;
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'email'))        $errors['email'] = 'This email is already in use.';
                elseif (str_contains($e->getMessage(), 'phone'))    $errors['phone'] = 'This phone number is already in use.';
                elseif (str_contains($e->getMessage(), 'id_card'))  $errors['id_card'] = 'This ID card number already exists.';
                elseif (str_contains($e->getMessage(), 'student_code')) $errors['student_code'] = 'This student ID already exists.';
                else $errors['general'] = 'Duplicate data: ' . $e->getMessage();
            } else {
                $errors['general'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// ---------- LOAD ROOM LIST FOR DROPDOWN ----------
// Show every room (including full/maintenance), grouped by building/floor,
// with a warning flag if not available — filtering is left to the admin.
$rooms = $pdo->query("
    SELECT
        r.room_id, r.room_number, r.floor, r.status_code,
        rt.type_name, rt.capacity, rt.price_per_month,
        b.building_name, b.building_code,
        (rt.capacity - r.current_occupancy) AS empty_beds
    FROM rooms r
    JOIN room_types rt ON rt.type_id = r.type_id
    JOIN buildings  b  ON b.building_id = r.building_id
    ORDER BY b.building_code, r.floor, r.room_number
")->fetchAll();

function val(string $key, array $old): string {
    return htmlspecialchars($_POST[$key] ?? $old[$key] ?? '');
}

$pageTitle = $isEdit ? 'Edit Student' : 'Add New Student';
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="StudentListView.php">Students</a>
        <span>›</span>
        <span><?= $isEdit ? 'Edit' : 'Add New' ?></span>
    </div>

    <h1 class="page-title"><?= $pageTitle ?></h1>

    <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors) && !isset($errors['general'])): ?>
    <div class="alert alert-error">Please check the highlighted fields below.</div>
    <?php endif; ?>

    <form method="POST">
        <?php if ($isEdit): ?>
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <?php endif; ?>
        <input type="hidden" name="save" value="1">

        <div class="card">
            <div class="card-header"><h2>Personal Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Full Name<span style="color:#dc2626">*</span></label>
                        <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                               value="<?= val('full_name', $old) ?>" placeholder="John Smith">
                        <?php if (isset($errors['full_name'])): ?><div class="form-error"><?= $errors['full_name'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Student ID<span style="color:#dc2626">*</span></label>
                        <input type="text" name="student_code" class="form-control <?= isset($errors['student_code']) ? 'is-invalid' : '' ?>"
                               value="<?= val('student_code', $old) ?>" placeholder="2151012001"
                               <?= $isEdit ? 'readonly style="background:#f8fafc"' : '' ?>>
                        <?php if (isset($errors['student_code'])): ?><div class="form-error"><?= $errors['student_code'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gender<span style="color:#dc2626">*</span></label>
                        <select name="gender" class="form-control <?= isset($errors['gender']) ? 'is-invalid' : '' ?>">
                            <option value="">— Select —</option>
                            <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['gender'] ?? $old['gender'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['gender'])): ?><div class="form-error"><?= $errors['gender'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Birth<span style="color:#dc2626">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>"
                               value="<?= val('date_of_birth', $old) ?>">
                        <?php if (isset($errors['date_of_birth'])): ?><div class="form-error"><?= $errors['date_of_birth'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ID Card Number<span style="color:#dc2626">*</span></label>
                        <input type="text" name="id_card" class="form-control <?= isset($errors['id_card']) ? 'is-invalid' : '' ?>"
                               value="<?= val('id_card', $old) ?>" placeholder="079203000101" maxlength="12">
                        <div class="form-hint">Exactly 12 digits</div>
                        <?php if (isset($errors['id_card'])): ?><div class="form-error"><?= $errors['id_card'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hometown</label>
                        <input type="text" name="hometown" class="form-control" value="<?= val('hometown', $old) ?>" placeholder="Hanoi">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Contact Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Email<span style="color:#dc2626">*</span></label>
                        <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= val('email', $old) ?>" placeholder="example@gmail.com">
                        <div class="form-hint">Must end with @gmail.com or @school.edu.vn</div>
                        <?php if (isset($errors['email'])): ?><div class="form-error"><?= $errors['email'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number<span style="color:#dc2626">*</span></label>
                        <input type="text" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               value="<?= val('phone', $old) ?>" placeholder="0912345678" maxlength="10">
                        <div class="form-hint">10 digits, starting with 0</div>
                        <?php if (isset($errors['phone'])): ?><div class="form-error"><?= $errors['phone'] ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Academic Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Faculty<span style="color:#dc2626">*</span></label>
                        <input type="text" name="faculty" class="form-control <?= isset($errors['faculty']) ? 'is-invalid' : '' ?>"
                               value="<?= val('faculty', $old) ?>" placeholder="Information Technology">
                        <?php if (isset($errors['faculty'])): ?><div class="form-error"><?= $errors['faculty'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Major<span style="color:#dc2626">*</span></label>
                        <input type="text" name="major" class="form-control <?= isset($errors['major']) ? 'is-invalid' : '' ?>"
                               value="<?= val('major', $old) ?>" placeholder="Software Engineering">
                        <?php if (isset($errors['major'])): ?><div class="form-error"><?= $errors['major'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Intake Year<span style="color:#dc2626">*</span></label>
                        <input type="number" name="intake_year" class="form-control <?= isset($errors['intake_year']) ? 'is-invalid' : '' ?>"
                               value="<?= val('intake_year', $old) ?>" placeholder="2023" min="2000" max="2099">
                        <?php if (isset($errors['intake_year'])): ?><div class="form-error"><?= $errors['intake_year'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Class<span style="color:#dc2626">*</span></label>
                        <input type="text" name="class_name" class="form-control <?= isset($errors['class_name']) ? 'is-invalid' : '' ?>"
                               value="<?= val('class_name', $old) ?>" placeholder="23SE01">
                        <?php if (isset($errors['class_name'])): ?><div class="form-error"><?= $errors['class_name'] ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status_code" class="form-control">
                            <?php foreach ([
                                'active'    => 'Active',
                                'graduated' => 'Graduated',
                                'suspended' => 'Suspended',
                                'expelled'  => 'Expelled',
                            ] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= (($_POST['status_code'] ?? $old['status_code'] ?? 'active') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$isEdit): ?>
        <!-- ROOM ASSIGNMENT — only shown when adding a new student -->
        <div class="card">
            <div class="card-header"><h2>Room Assignment (Optional)</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Assign to Room</label>
                    <select name="room_id" id="room_id" class="form-control" onchange="showRoomWarning()">
                        <option value="">— Leave unassigned, assign later —</option>
                        <?php foreach ($rooms as $r):
                            $isFull  = $r['status_code'] === 'full';
                            $isMaint = $r['status_code'] === 'maintenance';
                            $label = sprintf(
                                '%s — Room %s (Floor %d, %s) — %d beds empty [%s]',
                                $r['building_name'], $r['room_number'], $r['floor'], $r['type_name'],
                                $r['empty_beds'], strtoupper($r['status_code'])
                            );
                        ?>
                        <option value="<?= $r['room_id'] ?>"
                                data-status="<?= $r['status_code'] ?>"
                                <?= (($_POST['room_id'] ?? '') == $r['room_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">All rooms are listed, including full or under-maintenance ones — a warning will be shown if selected.</div>
                    <div id="room-warning" class="alert alert-error" style="display:none;margin-top:10px"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Add Student' ?></button>
            <a href="StudentListView.php" class="btn btn-ghost">Cancel</a>
            <?php if ($isEdit): ?>
            <span style="font-size:12px;color:var(--muted);margin-left:auto">
                Default password: <code>KTX@<?= val('student_code', $old) ?></code>
            </span>
            <?php endif; ?>
        </div>
    </form>
</main>

<script>
function showRoomWarning() {
    const sel = document.getElementById('room_id');
    const opt = sel.options[sel.selectedIndex];
    const warningBox = document.getElementById('room-warning');
    const status = opt ? opt.dataset.status : '';

    if (status === 'full') {
        warningBox.style.display = 'block';
        warningBox.textContent = 'Warning: this room is currently FULL. The student can still be assigned, but please double check bed availability.';
    } else if (status === 'maintenance') {
        warningBox.style.display = 'block';
        warningBox.textContent = 'Warning: this room is currently UNDER MAINTENANCE and may not be ready for occupancy.';
    } else {
        warningBox.style.display = 'none';
    }
}
</script>
</body>
</html>