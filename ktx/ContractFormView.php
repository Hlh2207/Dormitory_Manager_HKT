<?php
// ============================================================
//  ContractFormView.php — Create / Edit Contract
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

$contractId = filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT);
$contract   = null;
$isEdit     = false;

if ($contractId) {
    $stmt = $pdo->prepare("
        SELECT c.*, s.full_name, s.student_code, s.faculty,
               r.room_number, b.building_name, rt.type_name, rt.price_per_month
        FROM contracts c
        JOIN students   s  ON s.student_id  = c.student_id
        JOIN rooms      r  ON r.room_id     = c.room_id
        JOIN buildings  b  ON b.building_id = r.building_id
        JOIN room_types rt ON rt.type_id    = r.type_id
        WHERE c.contract_id = ?
    ");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();
    $isEdit   = (bool)$contract;
}

$errors  = [];
$success = '';
$dbError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId       = filter_input(INPUT_POST, 'student_id',           FILTER_VALIDATE_INT);
    $roomId          = filter_input(INPUT_POST, 'room_id',              FILTER_VALIDATE_INT);
    $contractCode    = trim($_POST['contract_code']                     ?? '');
    $startDate       = trim($_POST['start_date']                        ?? '');
    $endDate         = trim($_POST['end_date']                          ?? '');
    $depositRaw      = trim($_POST['deposit_amount']                    ?? '0');
    $deposit         = is_numeric($depositRaw) ? (float)$depositRaw : false;
    $monthlyFeeRaw   = trim($_POST['monthly_fee_snapshot']              ?? '');
    $monthlyFee      = is_numeric($monthlyFeeRaw) ? (float)$monthlyFeeRaw : false;
    $depositPaid     = isset($_POST['deposit_paid']) ? 1 : 0;
    $depositPaidDate = trim($_POST['deposit_paid_date']                 ?? '') ?: null;
    $signedDate      = trim($_POST['signed_date']                       ?? '') ?: null;
    $terms           = trim($_POST['terms']                             ?? '');
    $statusCode      = trim($_POST['status_code']                       ?? 'draft');

    if (!$studentId)   $errors[] = 'Please select a student.';
    if (!$roomId)      $errors[] = 'Please select a room.';
    if (!$contractCode) $errors[] = 'Contract Code cannot be empty.';
    if (!$startDate)   $errors[] = 'Start Date cannot be empty.';
    if (!$endDate)     $errors[] = 'End Date cannot be empty.';
    if ($startDate && $endDate && $startDate >= $endDate)
                       $errors[] = 'End Date must be after Start Date.';
    if ($deposit === false || $deposit < 0)
                       $errors[] = 'Invalid deposit amount (enter 0 if no deposit).';

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $pdo->prepare("
                    UPDATE contracts SET student_id=?, room_id=?, contract_code=?, start_date=?, end_date=?,
                    deposit_amount=?, deposit_paid=?, deposit_paid_date=?, monthly_fee_snapshot=?, terms=?, signed_date=?, status_code=?
                    WHERE contract_id=?
                ")->execute([
                    $studentId, $roomId, $contractCode, $startDate, $endDate, $deposit, $depositPaid,
                    $depositPaidDate, $monthlyFee, $terms, $signedDate, $statusCode, $contractId
                ]);
                $success = 'Contract updated successfully!';
            } else {
                $pdo->prepare("
                    INSERT INTO contracts (contract_code, student_id, room_id, start_date, end_date, deposit_amount, deposit_paid, deposit_paid_date, monthly_fee_snapshot, terms, signed_date, status_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $contractCode, $studentId, $roomId, $startDate, $endDate, $deposit, $depositPaid,
                    $depositPaidDate, $monthlyFee, $terms, $signedDate, $statusCode
                ]);
                header('Location: ContractListView.php?created=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $dbError = 'Contract Code "' . htmlspecialchars($contractCode) . '" already exists.';
            else $dbError = 'DB Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$students = $pdo->query("SELECT student_id, student_code, full_name, faculty, gender FROM students WHERE status_code = 'active' ORDER BY full_name")->fetchAll();

$roomsQuery = "
    SELECT r.room_id, r.room_number, r.floor, r.status_code AS room_status,
           rt.type_name, rt.price_per_month, b.building_name, b.gender_type
    FROM rooms r JOIN room_types rt ON rt.type_id = r.type_id JOIN buildings b ON b.building_id = r.building_id
    WHERE r.status_code != 'full' ";
if ($isEdit) $roomsQuery .= " OR r.room_id = " . (int)$contract['room_id'];
$roomsQuery .= " ORDER BY b.building_name, r.floor, r.room_number";
$rooms = $pdo->query($roomsQuery)->fetchAll();

$fStudentId    = $_POST['student_id']           ?? $contract['student_id']           ?? '';
$fRoomId       = $_POST['room_id']              ?? $contract['room_id']              ?? '';
$fContractCode = $_POST['contract_code']        ?? $contract['contract_code']        ?? '';
$fStart        = $_POST['start_date']           ?? $contract['start_date']           ?? '';
$fEnd          = $_POST['end_date']             ?? $contract['end_date']             ?? '';
$fDeposit      = $_POST['deposit_amount']       ?? $contract['deposit_amount']       ?? '0';
$fMonthlyFee   = $_POST['monthly_fee_snapshot'] ?? $contract['monthly_fee_snapshot'] ?? '';
$fDepositPaid  = $_POST['deposit_paid']         ?? $contract['deposit_paid']         ?? 0;
$fDepositDate  = $_POST['deposit_paid_date']    ?? $contract['deposit_paid_date']    ?? '';
$fSignedDate   = $_POST['signed_date']          ?? $contract['signed_date']          ?? date('Y-m-d');
$fTerms        = $_POST['terms']                ?? $contract['terms']                ?? '';
$fStatus       = $_POST['status_code']          ?? $contract['status_code']          ?? 'draft';

$statusOptions = ['draft'=>'Draft','active'=>'Active'];
if ($isEdit) {
    $statusOptions['expired'] = 'Expired';
    $statusOptions['terminated'] = 'Terminated';
}

$pageTitle = $isEdit ? 'Edit Contract' : 'New Contract';
include 'header.php';
?>

<main class="page">
    <div class="breadcrumb">
        <a href="ContractListView.php">📄 Contracts</a>
        <span>›</span>
        <span><?= $isEdit ? 'Edit #' . $contractId : 'New Contract' ?></span>
    </div>

    <h1 class="page-title">
        <?= $isEdit ? '✏ Edit Contract' : '📋 Create New Contract' ?>
    </h1>
    <p class="page-desc"><?= $isEdit ? 'Update contract details.' : 'Fill in the information to create a new accommodation contract.' ?></p>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><span>⚠</span><div><strong>Please check the following:</strong><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
    <?php if ($dbError): ?><div class="alert alert-db">🛑 <?= $dbError ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" id="contractForm" novalidate>
        <?php if ($isEdit): ?><input type="hidden" name="contract_id" value="<?= $contractId ?>"><?php endif; ?>

        <div class="card">
            <div class="card-header"><h2>👤 Student & Room</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Select Student <span class="required">*</span></label>
                        <select name="student_id" id="student_id" class="form-control" onchange="handleStudentChange()" required>
                            <option value="">— Select student —</option>
                            <?php foreach ($students as $sv): ?>
                            <option value="<?= $sv['student_id'] ?>" data-code="<?= $sv['student_code'] ?>" data-gender="<?= $sv['gender'] ?>" <?= (string)$fStudentId === (string)$sv['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sv['full_name']) ?> — <?= $sv['student_code'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Selecting a student filters the room list automatically.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="room_id">Select Room <span class="required">*</span></label>
                        <select name="room_id" id="room_id" class="form-control" onchange="autoGenCode()" required>
                            <option value="">— Select room —</option>
                            <?php
                            $curB = '';
                            foreach ($rooms as $r):
                                if ($r['building_name'] !== $curB):
                                    if ($curB !== '') echo '</optgroup>';
                                    echo '<optgroup label="🏢 ' . htmlspecialchars($r['building_name']) . '" data-bgender="' . $r['gender_type'] . '">';
                                    $curB = $r['building_name'];
                                endif;
                            ?>
                            <option value="<?= $r['room_id'] ?>" data-rnum="<?= $r['room_number'] ?>" data-price="<?= (float)$r['price_per_month'] ?>" <?= (string)$fRoomId === (string)$r['room_id'] ? 'selected' : '' ?>>
                                Room <?= htmlspecialchars($r['room_number']) ?> · <?= htmlspecialchars($r['type_name']) ?>
                            </option>
                            <?php endforeach; if ($curB !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>📄 Contract Information</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Contract Code <span class="required">*</span></label>
                        <input type="text" name="contract_code" id="contract_code" class="form-control" value="<?= htmlspecialchars($fContractCode) ?>" required <?= $isEdit ? 'readonly' : '' ?>>
                        <div class="form-hint">Auto-generated when creating.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Signed</label>
                        <input type="date" name="signed_date" class="form-control" value="<?= htmlspecialchars($fSignedDate) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($fStart) ?>" onchange="calcDuration()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date <span class="required">*</span></label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($fEnd) ?>" onchange="calcDuration()" required>
                        <div class="form-hint" id="durationHint"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>💵 Financials & Status</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Monthly Fee in Contract (VND) <span class="required">*</span></label>
                        <div class="pfx-wrap">
                            <span class="pfx">VND</span>
                            <input type="number" name="monthly_fee_snapshot" id="monthly_fee_snapshot" class="form-control" value="<?= htmlspecialchars($fMonthlyFee) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deposit Amount (VND) <span class="required">*</span></label>
                        <div class="pfx-wrap">
                            <span class="pfx">VND</span>
                            <input type="number" name="deposit_amount" class="form-control" value="<?= htmlspecialchars($fDeposit) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deposit Status</label>
                        <label class="check-row">
                            <input type="checkbox" name="deposit_paid" value="1" <?= $fDepositPaid ? 'checked' : '' ?>>
                            <span>Deposit Collected</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contract Status</label>
                        <div class="status-group">
                            <?php foreach ($statusOptions as $val => $label): ?>
                            <input type="radio" name="status_code" id="s_<?= $val ?>" class="s-radio" value="<?= $val ?>" <?= $fStatus === $val ? 'checked' : '' ?>>
                            <label class="s-label" for="s_<?= $val ?>"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top:20px">
                    <label class="form-label">Additional Terms</label>
                    <textarea name="terms" class="form-control" placeholder="Notes..."><?= htmlspecialchars($fTerms) ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg"><?= $isEdit ? '💾 Save Changes' : '✅ Create Contract' ?></button>
            <a href="ContractListView.php" class="btn btn-ghost btn-lg">✕ Cancel</a>
        </div>
    </form>
</main>

<script>
const isEdit = <?= $isEdit ? 'true' : 'false' ?>;

function handleStudentChange() { filterRooms(); autoGenCode(); }

function filterRooms() {
    const svSelect = document.getElementById('student_id');
    const opt = svSelect.options[svSelect.selectedIndex];
    const svGender = opt ? opt.dataset.gender : ''; 

    const optgroups = document.querySelectorAll('#room_id optgroup');
    optgroups.forEach(group => {
        const bGender = group.dataset.bgender; 
        if (svGender === '' || bGender === 'mixed' || bGender === svGender) {
            group.style.display = '';
        } else {
            group.style.display = 'none';
        }
    });
}

function autoGenCode() {
    if (isEdit) return; 
    const svOpt = document.getElementById('student_id').options[document.getElementById('student_id').selectedIndex];
    const rmOpt = document.getElementById('room_id').options[document.getElementById('room_id').selectedIndex];
    const codeInput = document.getElementById('contract_code');
    const feeInput = document.getElementById('monthly_fee_snapshot');

    if (svOpt && svOpt.value && rmOpt && rmOpt.value) {
        const year = new Date().getFullYear();
        codeInput.value = `CT-${year}-${svOpt.dataset.code}-R${rmOpt.dataset.rnum}`;
        if (!feeInput.value || feeInput.value === '0') {
            feeInput.value = rmOpt.dataset.price;
        }
    } else {
        codeInput.value = '';
    }
}

function calcDuration() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    const hint = document.getElementById('durationHint');
    if (!s || !e) { hint.textContent = ''; return; }
    const diff = Math.round((new Date(e) - new Date(s)) / 86400000);
    if (diff <= 0) {
        hint.style.color = '#A31D1D';
        hint.textContent = '⚠ End Date must be after Start Date.';
    } else {
        hint.style.color = 'var(--muted)';
        hint.textContent = `Duration: ${diff} days (~${Math.round(diff/30)} months)`;
    }
}

window.addEventListener('DOMContentLoaded', () => { filterRooms(); calcDuration(); });
</script>
</body>
</html>