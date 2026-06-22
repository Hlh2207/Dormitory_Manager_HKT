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

$violation_id = $_GET['id'] ?? 0;
$msg = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update_status'])) {
    $new_status    = $_POST['status_code'] ?? 'open';
    $resolved_note = trim($_POST['resolved_note'] ?? '');
    $fee_paid      = isset($_POST['penalty_fee_paid']) ? 1 : 0;
    
    $upSql = "UPDATE violation_records 
              SET status_code = :status, resolved_note = :note, penalty_fee_paid = :paid, resolved_at = NOW()
              WHERE violation_id = :vid";
    $upStmt = $pdo->prepare($upSql);
    $upStmt->execute([
        'status' => $new_status,
        'note'   => $resolved_note,
        'paid'   => $fee_paid,
        'vid'    => $violation_id
    ]);
    $msg = "Incident resolution updated successfully!";
}


$sql = "
    SELECT 
        v.violation_id, v.violation_type, v.description, v.violation_date, v.severity,
        v.penalty, v.penalty_fee, v.penalty_fee_paid, v.status_code, v.resolved_note, v.resolved_at, v.evidence_url,
        s.student_code, s.full_name AS student_name,
        r.room_number, b.building_name
    FROM violation_records v
    JOIN students s ON v.student_id = s.student_id
    LEFT JOIN rooms r ON v.room_id = r.room_id
    LEFT JOIN buildings b ON r.building_id = b.building_id
    WHERE v.violation_id = :vid LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['vid' => $violation_id]);
$v = $stmt->fetch();

if (!$v) {
    die('<p style="padding:15px; color:red; font-weight:bold;">Error: Violation record not found.</p>');
}

$pageTitle = "Case #" . $v['violation_id'] . " Review";
include 'header.php';
?>

<main class="page" style="max-width: 900px; margin: 0 auto;">
    <div class="sub-header" style="padding: 10px 0; margin-bottom: 15px; font-size: 13px; color: #718096;">
        <a href="ViolationView.php" style="text-decoration: none; color: #718096; font-weight: 500; display: inline-flex; align-items: center; gap: 5px;">
            Violations
        </a>
        <span style="color: #cbd5e0; margin: 0 8px;">/</span>
        <span style="color: #4a5568;">View Violation #<?= htmlspecialchars($v['violation_id']) ?></span>
    </div>

    <?php if(!empty($msg)): ?>
        <div style="background:#f0fff4; color:#22543d; padding:12px; border-radius:6px; margin-bottom:20px; font-size:14px;">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:25px;">
        
        <div class="table-wrap" style="padding:25px; background:#fff; border-radius:12px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
            <div style="border-bottom: 2px solid #edf2f7; padding-bottom:10px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0; font-size:18px; color:#4a5568;">🔍 Case Incident #<?= $v['violation_id'] ?></h2>
                <span style="font-size:12px; color:var(--muted)">Incident Date: <?= date('M d, Y', strtotime($v['violation_date'])) ?></span>
            </div>

            <div style="margin-bottom:18px;">
                <small style="color:var(--muted); font-weight:600; display:block; font-size:11px; text-uppercase:true; letter-spacing: 0.5px;">Resident Under Infraction</small>
                <div style="font-size:16px; font-weight:700; color:#2d3748; margin-top:2px;">
                    <?= htmlspecialchars($v['student_name']) ?> (<?= htmlspecialchars($v['student_code']) ?>)
                </div>
                <div style="font-size:13px; color:#4a5568; margin-top:2px;">
                    🏢 Location: <?= htmlspecialchars($v['building_name'] ?? 'N/A') ?> — Rm <?= htmlspecialchars($v['room_number'] ?? 'N/A') ?>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <small style="color:var(--muted); font-weight:600; display:block; font-size:11px; text-uppercase:true; letter-spacing: 0.5px;">Violation Rule Type & Severity</small>
                <div style="margin-top:4px; display:flex; gap:10px; align-items:center;">
                    <strong style="text-transform:uppercase; color:#4a5568; font-size:14px; letter-spacing: 0.5px;"><?= htmlspecialchars(str_replace('_', ' ', $v['violation_type'])) ?></strong>
                    <span style="padding:3px 10px; font-size:11px; border-radius:50px; font-weight:bold; background:#edf2f7; color:#4a5568;"><?= strtoupper($v['severity']) ?></span>
                </div>
            </div>

            <div style="margin-bottom:18px; background:#f7fafc; padding:15px; border-radius:8px; border-left:4px solid #cbd5e0;">
                <small style="color:var(--muted); font-weight:600; display:block; font-size:11px; text-uppercase:true; letter-spacing: 0.5px;">Fact Description</small>
                <p style="margin:6px 0 0 0; font-size:14px; color:#2d3748; line-height:1.5; white-space:pre-line;"><?= htmlspecialchars($v['description']) ?></p>
            </div>

            <?php if(!empty($v['evidence_url'])): ?>
                <div style="margin-bottom:10px;">
                    <small style="color:var(--muted); font-weight:600; display:block; font-size:11px; text-uppercase:true; margin-bottom:6px;">Attached Photo Evidence</small>
                    <img src="<?= htmlspecialchars($v['evidence_url']) ?>" alt="Evidence Photo" style="max-width:100%; max-height:250px; border-radius:8px; border:1px solid #e2e8f0; object-fit:contain;">
                </div>
            <?php endif; ?>
        </div>

        <div class="table-wrap" style="padding:25px; background:#fff; border-radius:12px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); height:fit-content;">
            <h3 style="margin:0 0 18px 0; font-size:16px; color:#4a5568; border-bottom:2px solid #edf2f7; padding-bottom:8px;">⚙️ Case Resolution</h3>
            
            <form method="POST" action="ViolationDetailView.php?id=<?= $v['violation_id'] ?>" style="display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; font-size:12px; color:#4a5568; margin-bottom:5px;">Update Status</label>
                    <select name="status_code" style="width:100%; padding:9px; border:1px solid #cbd5e0; border-radius:6px; font-size:14px; background:#fff; cursor:pointer;">
                        <option value="open" <?= $v['status_code'] === 'open' ? 'selected' : '' ?>>🔴 Open (Unresolved)</option>
                        <option value="appealing" <?= $v['status_code'] === 'appealing' ? 'selected' : '' ?>>🟡 Appealing (In Progress)</option>
                        <option value="resolved" <?= $v['status_code'] === 'resolved' ? 'selected' : '' ?>>🟢 Resolved (Case Closed)</option>
                    </select>
                </div>

                <div>
                    <label style="display:block; font-weight:600; font-size:12px; color:#4a5568; margin-bottom:5px;">Fine Fee Status</label>
                    <div style="background:#f7fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div style="font-weight:700; color:#B8506E; font-size:14px; margin-bottom:6px;">
                            Fee Amount: <?= number_format($v['penalty_fee']) ?> VND
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; margin:0; user-select:none;">
                            <input type="checkbox" name="penalty_fee_paid" value="1" <?= $v['penalty_fee_paid'] ? 'checked' : '' ?> style="width:15px; height:15px;"> Mark as Settled / Paid
                        </label>
                    </div>
                </div>

                <div>
                    <label style="display:block; font-weight:600; font-size:12px; color:#4a5568; margin-bottom:5px;">Resolution Notes (Action Taken)</label>
                    <textarea name="resolved_note" rows="4" placeholder="Enter disciplinary actions or resolution summary..." style="width:100%; padding:9px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px; font-family:inherit; line-height: 1.4;"><?= htmlspecialchars($v['resolved_note'] ?? '') ?></textarea>
                </div>

                <?php if(!empty($v['resolved_at'])): ?>
                    <div style="font-size:11px; color:var(--muted); background: #fff9fa; padding: 6px 10px; border-radius: 4px;">
                        <i class="fa-solid fa-clock me-1"></i> Processed on: <?= date('M d, Y H:i', strtotime($v['resolved_at'])) ?>
                    </div>
                <?php endif; ?>

                <button type="submit" name="btn_update_status" style="width:100%; padding:11px; border:none; background:#D87093; color:white; font-weight:600; font-size:14px; border-radius:6px; cursor:pointer; margin-top:4px; transition: background 0.2s;">
                    🔄 Update Resolution Status
                </button>
            </form>
        </div>
    </div>
</main>
</body>
</html>