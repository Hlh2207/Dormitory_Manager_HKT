<?php
// ============================================================
//  StudentListView.php — Student List
//  Connects to: students, users
//  Task 6: All queries use PDO Prepared Statements
// 
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) { die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>'); }

require_once __DIR__ . '/StudentController.php';
$controller = new StudentController($pdo);
$deleteMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    if ($delId) {
        session_start();
        $_SESSION['delete_message'] = $controller->deleteStudent($delId);
    }
    header('Location: StudentListView.php'); exit;
}

session_start();
if (isset($_SESSION['delete_message'])) {
    $deleteMessage = $_SESSION['delete_message'];
    unset($_SESSION['delete_message']);
}

// Task 6: search input goes through StudentController, which uses
// $stmt->execute([':s1' => $like, ...]) — never concatenated into SQL.
$search = trim($_GET['search'] ?? '');
$students = $controller->getAllStudents($search);

$total  = count($students);
$male   = count(array_filter($students, fn($s) => $s['gender'] === 'male'));
$female = count(array_filter($students, fn($s) => $s['gender'] === 'female'));

function genderLabel(string $g): string {
    return match($g) { 'male' => 'Male', 'female' => 'Female', default => 'Other' };
}
function statusLabel(string $c): array {
    return match($c) {
        'active'    => ['Active',     'badge-green'],
        'graduated' => ['Graduated',  'badge-blue'],
        'suspended' => ['Suspended',  'badge-yellow'],
        'expelled'  => ['Expelled',   'badge-red'],
        default     => [$c,           'badge-gray'],
    };
}

$pageTitle = "Student Management";
include 'header.php';
?>

<main class="page">
    <h1 class="page-title">Student Management</h1>
    <p class="page-desc">List of all registered students — add, edit, or delete student profiles.</p>

    <?php if ($deleteMessage): ?>
    <div class="alert <?= $deleteMessage['success'] ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($deleteMessage['message']) ?>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-value"><?= $male ?></div>
            <div class="stat-label">Male</div>
        </div>
        <div class="stat-card pink">
            <div class="stat-value"><?= $female ?></div>
            <div class="stat-label">Female</div>
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" style="display:contents">
            <div class="search-box">
                <input type="text" name="search"
                       placeholder="Search by Name, Student ID, Email, Phone..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
            <a href="StudentListView.php" class="btn btn-ghost">Clear Filter</a>
            <?php endif; ?>
        </form>
        <a href="StudentFormView.php" class="btn btn-primary" style="margin-left:auto">Add Student</a>
    </div>

    <div class="table-wrap">
        <div class="table-header">
            <h2>Students <?= $search ? '(results: "' . htmlspecialchars($search) . '")' : '' ?> (<?= $total ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th class="hide-mobile">Gender</th>
                        <th class="hide-mobile">Faculty / Major</th>
                        <th>Email / Phone</th>
                        <th class="hide-mobile">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="7"><div class="empty">No students found.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($students as $sv):
                        [$statusText, $statusClass] = statusLabel($sv['status_code']);
                        $initials = mb_substr($sv['full_name'], 0, 1, 'UTF-8');
                        $colors   = ['#F3B0C3','#A8E6CF','#FF9AA2','#CBAACB','#FDFD96','#AEC6CF'];
                        $color    = $colors[$sv['student_id'] % count($colors)];
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:<?= $color ?>; color:#4A4A4A;"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($sv['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['class_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code style="font-size:13px;background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($sv['student_code']) ?></code></td>
                        <td class="hide-mobile">
                            <span class="badge <?= $sv['gender'] === 'male' ? 'badge-blue' : ($sv['gender'] === 'female' ? 'badge-pink' : 'badge-gray') ?>">
                                <?= genderLabel($sv['gender']) ?>
                            </span>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-size:13px"><?= htmlspecialchars($sv['faculty']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['major']) ?></div>
                        </td>
                        <td>
                            <div style="font-size:13px"><?= htmlspecialchars($sv['email']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($sv['phone'] ?? '—') ?></div>
                        </td>
                        <td class="hide-mobile"><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="StudentFormView.php?student_id=<?= $sv['student_id'] ?>" class="btn btn-edit btn-sm">Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete student <?= htmlspecialchars(addslashes($sv['full_name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="student_id" value="<?= $sv['student_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
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