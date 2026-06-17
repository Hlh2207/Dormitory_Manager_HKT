<?php
// ============================================================
//  ContractListView.php — Contract List
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);
    if ($delId) {
        $pdo->prepare("DELETE FROM contracts WHERE contract_id = ?")->execute([$delId]);
    }
    header('Location: ContractListView.php');
    exit;
}

$justCreated = isset($_GET['created']) && $_GET['created'] === '1';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        c.contract_id,
        c.contract_code,
        c.start_date,
        c.end_date,
        c.monthly_fee_snapshot,
        c.deposit_amount,
        c.deposit_paid,
        c.status_code,
        c.signed_date,
        s.full_name,
        s.student_code,
        s.student_id,
        r.room_number,
        rt.type_name,
        b.building_name
    FROM contracts c
    JOIN students   s  ON s.student_id  = c.student_id
    JOIN rooms      r  ON r.room_id     = c.room_id
    JOIN room_types rt ON rt.type_id    = r.type_id
    JOIN buildings  b  ON b.building_id = r.building_id
";
$params = [];
if ($search !== '') {
    $sql .= " WHERE c.contract_code LIKE :s1
               OR s.full_name     LIKE :s2
               OR s.student_code  LIKE :s3
               OR r.room_number   LIKE :s4
               OR b.building_name LIKE :s5";
    $like = '%' . $search . '%';
    $params = [':s1'=>$like,':s2'=>$like,':s3'=>$like,':s4'=>$like,':s5'=>$like];
}
$sql .= " ORDER BY c.contract_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

$total      = count($contracts);
$active     = count(array_filter($contracts, fn($c) => $c['status_code'] === 'active'));
$draft      = count(array_filter($contracts, fn($c) => $c['status_code'] === 'draft'));
$expired    = count(array_filter($contracts, fn($c) => $c['status_code'] === 'expired'));
$terminated = count(array_filter($contracts, fn($c) => $c['status_code'] === 'terminated'));

function statusLabel(string $c): array {
    return match($c) {
        'active'     => ['Active',     'badge-green'],
        'draft'      => ['Draft',      'badge-yellow'],
        'expired'    => ['Expired',    'badge-blue'],
        'terminated' => ['Terminated', 'badge-red'],
        default      => [$c,           'badge-gray'],
    };
}
function fmtMoney(float $n): string {
    return number_format($n, 0, ',', '.') . ' VND';
}
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

$pageTitle = "Contract Management";
include 'header.php';
?>

<main class="page">
    <h1 class="page-title">Contract Management</h1>
    <p class="page-desc">List of dormitory contracts — add, edit, or delete student contracts.</p>

    <?php if ($justCreated): ?>
    <div class="alert alert-success">✔ Contract created successfully!</div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Contracts</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?= $active ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-value"><?= $draft ?></div>
            <div class="stat-label">Draft</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-value"><?= $expired ?></div>
            <div class="stat-label">Expired</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?= $terminated ?></div>
            <div class="stat-label">Terminated</div>
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" style="display:contents">
            <div class="search-box">
                <span>🔍</span>
                <input type="text" name="search"
                       placeholder="Search by Contract No, Name, ID, Room, Building..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
            <a href="ContractListView.php" class="btn btn-ghost">✕ Clear</a>
            <?php endif; ?>
        </form>
        <a href="ContractFormView.php" class="btn btn-primary" style="margin-left:auto">＋ New Contract</a>
    </div>

    <div class="table-wrap">
        <div class="table-header">
            <h2>📄 Contracts <?= $search ? '(results: "' . htmlspecialchars($search) . '")' : '' ?> (<?= $total ?>)</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Contract Code</th>
                        <th class="hide-mobile">Room / Building</th>
                        <th class="hide-mobile">Duration</th>
                        <th class="hide-mobile">Monthly Fee</th>
                        <th class="hide-mobile">Deposit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($contracts)): ?>
                    <tr><td colspan="8"><div class="empty"><div style="font-size:40px;margin-bottom:12px">📄</div><div>No contracts found.</div></div></td></tr>
                <?php else: ?>
                    <?php foreach ($contracts as $ct):
                        [$statusText, $statusClass] = statusLabel($ct['status_code']);
                        $initials = mb_substr($ct['full_name'], 0, 1, 'UTF-8');
                        $colors   = ['#F3B0C3','#A8E6CF','#FF9AA2','#CBAACB','#FDFD96','#AEC6CF'];
                        $color    = $colors[$ct['student_id'] % count($colors)];
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:<?= $color ?>; color:#4A4A4A;"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600"><?= htmlspecialchars($ct['full_name']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($ct['student_code']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">
                                <?= htmlspecialchars($ct['contract_code']) ?>
                            </code>
                            <?php if ($ct['signed_date']): ?>
                            <div style="font-size:11px;color:var(--muted);margin-top:3px">Signed: <?= fmtDate($ct['signed_date']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-weight:600">Room <?= htmlspecialchars($ct['room_number']) ?></div>
                            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($ct['building_name']) ?> · <?= htmlspecialchars($ct['type_name']) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-size:13px;white-space:nowrap"><?= fmtDate($ct['start_date']) ?></div>
                            <div style="font-size:11px;color:var(--muted)">→ <?= fmtDate($ct['end_date']) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-size:13px;font-weight:600;white-space:nowrap">
                                <?= fmtMoney((float)$ct['monthly_fee_snapshot']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--muted)">/month</div>
                        </td>
                        <td class="hide-mobile">
                            <div style="font-size:13px;white-space:nowrap"><?= fmtMoney((float)$ct['deposit_amount']) ?></div>
                            <div style="font-size:11px;margin-top:2px">
                                <?php if ($ct['deposit_paid']): ?>
                                <span class="dep-yes">✔ Collected</span>
                                <?php else: ?>
                                <span class="dep-no">— Pending</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a href="ContractFormView.php?contract_id=<?= $ct['contract_id'] ?>"
                                   class="btn btn-edit btn-sm">✏ Edit</a>
                                <form method="POST"
                                      onsubmit="return confirm('Delete contract <?= htmlspecialchars(addslashes($ct['contract_code'])) ?> of <?= htmlspecialchars(addslashes($ct['full_name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contract_id" value="<?= $ct['contract_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑 Del</button>
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