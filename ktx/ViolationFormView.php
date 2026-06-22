<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}

$host   = 'localhost';
$db     = 'campus_final';   
$user   = 'root';
$pass   = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Connection Failed: ' . $e->getMessage());
}

$errorMsg = "";
$successMsg = "";


$studentSql = "SELECT student_id, student_code, full_name FROM students WHERE status_code = 'active' ORDER BY student_code";
$students = $pdo->query($studentSql)->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_save_violation'])) {
    $student_id     = $_POST['student_id'] ?? '';
    $violation_type = $_POST['violation_type'] ?? '';
    $severity       = $_POST['severity'] ?? 'minor';
    $violation_date = $_POST['violation_date'] ?? date('Y-m-to');
    $description    = trim($_POST['description'] ?? '');
    $penalty        = trim($_POST['penalty'] ?? '');
    $penalty_fee    = floatval($_POST['penalty_fee'] ?? 0);
    $fee_paid       = isset($_POST['penalty_fee_paid']) ? 1 : 0;
    $current_user   = $_SESSION['user_id'];

    if (empty($student_id) || empty($violation_type) || empty($description)) {
        $errorMsg = "Please select a student, infraction type, and enter description.";
    } else {
        
        $roomSql = "SELECT room_id FROM contracts WHERE student_id = :sid AND status_code = 'active' LIMIT 1";
        $roomStmt = $pdo->prepare($roomSql);
        $roomStmt->execute(['sid' => $student_id]);
        $roomRow = $roomStmt->fetch();
        $room_id = $roomRow ? $roomRow['room_id'] : null;

        try {
            $insSql = "INSERT INTO violation_records 
                (student_id, room_id, violation_type, description, violation_date, severity, penalty, penalty_fee, penalty_fee_paid, status_code)
                VALUES (:student_id, :room_id, :violation_type, :description, :violation_date, :severity, :penalty, :penalty_fee, :penalty_fee_paid, 'open')";

            $insStmt = $pdo->prepare($insSql);
            $insStmt->execute([
                'student_id'     => $student_id,
                'room_id'        => $room_id,
                'violation_type' => $violation_type,
                'description'    => $description,
                'violation_date' => $violation_date,
                'severity'       => $severity,
                'penalty'        => $penalty,
                'penalty_fee'    => $penalty_fee,
                'penalty_fee_paid'=> $fee_paid
            ]);
            
            $successMsg = "Disciplinary violation logged successfully!";
        } catch (PDOException $ex) {
            $errorMsg = "Database Error: " . $ex->getMessage();
        }
    }
}

$pageTitle = "Log Incident Form";
include 'header.php';
?>

<main class="page" style="max-width: 700px; margin: 0 auto;">
    <div class="sub-header" style="padding: 10px 0; margin-bottom: 15px; font-size: 13px; color: #718096;">
        <a href="ViolationView.php" style="text-decoration: none; color: #718096; font-weight: 500; display: inline-flex; align-items: center; gap: 5px;">
            Violations
        </a>
        <span style="color: #cbd5e0; margin: 0 8px;">/</span>
        <span style="color: #4a5568;">Add New Violation</span>
    </div>

    <div class="table-wrap" style="padding: 30px; background:#fff; border-radius:12px;">
        <div style="border-bottom: 2px solid #edf2f7; padding-bottom:12px; margin-bottom:25px;">
            <h2 style="margin:0; color:#4a5568; font-size:20px;">📋 Create Violation Incident</h2>
            <p style="margin:4px 0 0 0; color:var(--muted); font-size:13px;">File an official regulatory non-compliance record against a resident.</p>
        </div>

        <?php if(!empty($errorMsg)): ?>
            <div style="background:#fff5f5; color:#c53030; padding:12px; border-radius:6px; margin-bottom:20px; font-size:14px;">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>
        <?php if(!empty($successMsg)): ?>
            <div style="background:#f0fff4; color:#22543d; padding:12px; border-radius:6px; margin-bottom:20px; font-size:14px;">✅ <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <form method="POST" action="ViolationFormView.php" style="display:flex; flex-direction:column; gap:18px;">
            
            <div>
                <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Select Student *</label>
                <select name="student_id" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; background:#fff;">
                    <option value="">-- Choose Resident --</option>
                    <?php foreach($students as $st): ?>
                        <option value="<?= $st['student_id'] ?>"><?= htmlspecialchars($st['student_code']) ?> - <?= htmlspecialchars($st['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Violation Category *</label>
                    <select name="violation_type" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; background:#fff;">
                        <option value="curfew">Curfew Breach (Quá giờ giới nghiêm)</option>
                        <option value="noise">Noise Disturbance (Gây mất trật tự)</option>
                        <option value="cooking">Illegal Cooking (Nấu ăn sai quy định)</option>
                        <option value="property_damage">Property Damage (Phá hoại tài sản)</option>
                        <option value="unauthorized_guest">Unauthorized Guest (Chứa người lạ)</option>
                        <option value="other">Other Infraction (Vi phạm khác)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Severity Level *</label>
                    <select name="severity" style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; background:#fff;">
                        <option value="minor">Minor (Nhẹ - Nhắc nhở)</option>
                        <option value="moderate">Moderate (Vừa phải)</option>
                        <option value="serious">Serious (Nghiêm trọng)</option>
                        <option value="critical">Critical (Rất nguy hiểm)</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Incident Occurrence Date *</label>
                <input type="date" name="violation_date" value="<?= date('Y-m-d') ?>" required style="width:100%; padding:9px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
            </div>

            <div>
                <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Fact Description & Details *</label>
                <textarea name="description" rows="4" placeholder="Describe what rules were broken and the context..." required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; font-family:inherit;"></textarea>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px; align-items:end;">
                <div>
                    <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Disciplinary Penalty / Measure</label>
                    <input type="text" name="penalty" placeholder="e.g. Warning letter / Room Transfer" style="width:100%; padding:9px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; font-size:13px; color:#4a5568; margin-bottom:6px;">Fine Amount (VND)</label>
                    <input type="number" name="penalty_fee" value="0" min="0" style="width:100%; padding:9px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px;">
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:8px; margin-top:5px;">
                <input type="checkbox" id="paid" name="penalty_fee_paid" value="1" style="width:16px; height:16px;">
                <label for="paid" style="font-size:14px; color:#2d3748; cursor:pointer; font-weight:500;">Fine fee has been settled and paid up front.</label>
            </div>

            <div style="margin-top: 10px;">
                <button type="submit" name="btn_save_violation" style="width:100%; padding:12px; border:none; background:#D87093; color:white; font-weight:600; font-size:15px; border-radius:6px; cursor:pointer;">
                    💾 Save Incident Record
                </button>
            </div>
        </form>
    </div>
</main>
</body>
</html>