<?php
// ============================================================
//  ViolationView.php — Violation Records Management List
//  Connects to: violation_records, students, rooms
// ============================================================

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
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Truy vấn toàn bộ danh sách vi phạm
$sql = "
    SELECT 
        v.violation_id, v.violation_type, v.description, v.violation_date, v.severity,
        v.penalty, v.penalty_fee, v.penalty_fee_paid, v.status_code,
        s.student_code, s.full_name AS student_name, r.room_number
    FROM violation_records v
    JOIN students s ON v.student_id = s.student_id
    LEFT JOIN rooms r ON v.room_id = r.room_id
    ORDER BY v.violation_date DESC, v.violation_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$violations = $stmt->fetchAll();

function severityClass(string $severity): string {
    return match($severity) {
        'minor'    => 'badge-blue',
        'moderate' => 'badge-purple',
        'serious'  => 'badge-pink',
        'critical' => 'badge-red',
        default    => 'badge-gray',
    };
}

function statusClass(string $status): string {
    return match($status) {
        'open'      => 'badge-red',
        'resolved'  => 'badge-green',
        'appealing' => 'badge-yellow',
        default     => 'badge-gray',
    };
}

$pageTitle = "Violation Management";
include 'header.php'; 
?>

<main class="page">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
        <div>
            <h1 class="page-title" style="margin-bottom:0;">Violation Records</h1>
            <p class="page-desc">Track and manage student disciplinary infractions and penalties.</p>
        </div>
        <a href="ViolationFormView.php" class="btn-detail" style="background-color:#D87093; color:white; border:none; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:600; font-size:14px; display:inline-flex; align-items:center; gap:8px;">
            ➕ Add New Violation
        </a>
    </div>

    <?php
    $totalViolations = count($violations);
    $totalOpen = 0; $totalResolved = 0; $totalPenaltyFee = 0;
    foreach ($violations as $v) {
        if ($v['status_code'] === 'open') $totalOpen++;
        elseif ($v['status_code'] === 'resolved') $totalResolved++;
        $totalPenaltyFee += $v['penalty_fee'];
    }
    ?>

    <div class="summary-row">
        <div class="stat-card">
            <div class="stat-value"><?= $totalViolations ?></div>
            <div class="stat-label">Total Incidents</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $totalOpen ?></div>
            <div class="stat-label">Unresolved (Open)</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $totalResolved ?></div>
            <div class="stat-label">Resolved Cases</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value" style="font-size:19px; padding-top:4px; font-weight:700;">
                <?= number_format($totalPenaltyFee) ?>đ
            </div>
            <div class="stat-label">Total Penalties</div>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-header">
            <h2>📋 All Violations (<?= $totalViolations ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student / Room</th>
                        <th>Infraction Type</th>
                        <th>Description & Date</th>
                        <th>Severity</th>
                        <th>Penalty & Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($violations)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">No violation records available.</td></tr>
                <?php else: ?>
                    <?php foreach ($violations as $v): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($v['student_name']) ?></div>
                            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                Code: <strong><?= htmlspecialchars($v['student_code']) ?></strong>
                                <?= !empty($v['room_number']) ? " · Rm " . htmlspecialchars($v['room_number']) : "" ?>
                            </div>
                        </td>
                        <td style="text-transform: capitalize; font-weight:500; color:#4a5568;">
                            <?= htmlspecialchars(str_replace('_', ' ', $v['violation_type'])) ?>
                        </td>
                        <td style="max-width:250px;">
                            <div style="font-size:13px; color:#2d3748; line-height:1.4;" class="text-truncate"><?= htmlspecialchars($v['description']) ?></div>
                            <div style="font-size:11px;color:var(--muted);margin-top:3px">
                                📅 <?= date('M d, Y', strtotime($v['violation_date'])) ?>
                            </div>
                        </td>
                        <td><span class="badge <?= severityClass($v['severity']) ?>"><?= ucfirst($v['severity']) ?></span></td>
                        <td>
                            <div style="font-size:13px; font-weight:500;"><?= htmlspecialchars($v['penalty'] ?? 'Warning') ?></div>
                            <?php if ($v['penalty_fee'] > 0): ?>
                                <div style="font-size:11px; margin-top:2px; font-weight:600; color:#B8506E;">
                                    <?= number_format($v['penalty_fee']) ?>đ 
                                    <?= $v['penalty_fee_paid'] ? '<span style="color:#2ec4b6">(Paid)</span>' : '<span style="color:#e71d36">(Unpaid)</span>' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= statusClass($v['status_code']) ?>"><?= ucfirst($v['status_code']) ?></span></td>
                        <td>
                            <a class="btn-detail" href="ViolationDetailView.php?id=<?= $v['violation_id'] ?>" style="text-decoration:none; display:inline-block; font-size:12px; padding:5px 10px;">
                                🔍 View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>