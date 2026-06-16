<?php
// ============================================================
//  ContractFormView.php — Lập / chỉnh sửa hợp đồng thuê phòng
//  Kết nối bảng: contracts, students, rooms, room_types, buildings
// ============================================================

$host = 'localhost'; $db = 'campus_final'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red">Kết nối DB thất bại: ' . htmlspecialchars($e->getMessage()) . '</p>');
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

    if (!$studentId)   $errors[] = 'Vui lòng chọn sinh viên.';
    if (!$roomId)      $errors[] = 'Vui lòng chọn phòng.';
    if (!$contractCode) $errors[] = 'Mã hợp đồng không được để trống.';
    if (!$startDate)   $errors[] = 'Ngày bắt đầu không được để trống.';
    if (!$endDate)     $errors[] = 'Ngày kết thúc không được để trống.';
    if ($startDate && $endDate && $startDate >= $endDate)
                       $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    if ($deposit === false || $deposit < 0)
                       $errors[] = 'Số tiền đặt cọc không hợp lệ (nhập 0 nếu không có cọc).';

    if ($monthlyFee === false || $monthlyFee <= 0) {
        if ($roomId) {
            $feeStmt = $pdo->prepare("
                SELECT rt.price_per_month FROM rooms r
                JOIN room_types rt ON rt.type_id = r.type_id WHERE r.room_id = ?
            ");
            $feeStmt->execute([$roomId]);
            $monthlyFee = (float)($feeStmt->fetchColumn() ?: 0);
        }
        if (!$monthlyFee) $errors[] = 'Giá thuê tháng không hợp lệ.';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $pdo->prepare("
                    UPDATE contracts
                    SET student_id=?, room_id=?, contract_code=?,
                        start_date=?, end_date=?,
                        deposit_amount=?, deposit_paid=?, deposit_paid_date=?,
                        monthly_fee_snapshot=?, terms=?, signed_date=?, status_code=?
                    WHERE contract_id=?
                ")->execute([
                    $studentId, $roomId, $contractCode,
                    $startDate, $endDate,
                    $deposit, $depositPaid, $depositPaidDate,
                    $monthlyFee, $terms, $signedDate, $statusCode,
                    $contractId
                ]);
                $success = 'Cập nhật hợp đồng thành công!';
            } else {
                $pdo->prepare("
                    INSERT INTO contracts
                        (contract_code, student_id, room_id,
                         start_date, end_date,
                         deposit_amount, deposit_paid, deposit_paid_date,
                         monthly_fee_snapshot, terms, signed_date, status_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $contractCode, $studentId, $roomId,
                    $startDate, $endDate,
                    $deposit, $depositPaid, $depositPaidDate,
                    $monthlyFee, $terms, $signedDate, $statusCode
                ]);
                header('Location: ContractListView.php?created=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $dbError = 'Mã hợp đồng "' . htmlspecialchars($contractCode) . '" đã tồn tại. Vui lòng dùng mã khác.';
            } else {
                $dbError = 'Lỗi DB: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$students = $pdo->query("
    SELECT s.student_id, s.student_code, s.full_name, s.faculty
    FROM students s WHERE s.status_code = 'active' ORDER BY s.full_name
")->fetchAll();

$rooms = $pdo->query("
    SELECT r.room_id, r.room_number, r.floor, r.status_code AS room_status,
           rt.type_name, rt.price_per_month,
           b.building_name
    FROM rooms      r
    JOIN room_types rt ON rt.type_id    = r.type_id
    JOIN buildings  b  ON b.building_id = r.building_id
    ORDER BY b.building_name, r.floor, r.room_number
")->fetchAll();

$fStudentId    = $_POST['student_id']           ?? $contract['student_id']           ?? '';
$fRoomId       = $_POST['room_id']              ?? $contract['room_id']              ?? '';
$fContractCode = $_POST['contract_code']        ?? $contract['contract_code']        ?? 'HD-' . date('Y') . '-';
$fStart        = $_POST['start_date']           ?? $contract['start_date']           ?? '';
$fEnd          = $_POST['end_date']             ?? $contract['end_date']             ?? '';
$fDeposit      = $_POST['deposit_amount']       ?? $contract['deposit_amount']       ?? '0';
$fMonthlyFee   = $_POST['monthly_fee_snapshot'] ?? $contract['monthly_fee_snapshot'] ?? '';
$fDepositPaid  = $_POST['deposit_paid']         ?? $contract['deposit_paid']         ?? 0;
$fDepositDate  = $_POST['deposit_paid_date']    ?? $contract['deposit_paid_date']    ?? '';
$fSignedDate   = $_POST['signed_date']          ?? $contract['signed_date']          ?? date('Y-m-d');
$fTerms        = $_POST['terms']                ?? $contract['terms']                ?? '';
$fStatus       = $_POST['status_code']          ?? $contract['status_code']          ?? 'draft';

$statusOptions = ['draft'=>'Nháp','active'=>'Đang hiệu lực','expired'=>'Hết hạn','terminated'=>'Đã chấm dứt'];
$roomStatusLabel = ['available'=>'Còn trống','full'=>'Đã đầy','maintenance'=>'Bảo trì','closed'=>'Đóng cửa'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isEdit ? 'Sửa hợp đồng' : 'Lập hợp đồng mới' ?> — KTX Campus</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f0f2f5;--card:#fff;--primary:#0ea5a4;--primary-lt:#e0f2f1;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;
    --red:#dc2626;--red-lt:#fee2e2;
    --yellow:#ca8a04;--yellow-lt:#fef9c3;
    --blue:#2563eb;--blue-lt:#dbeafe;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}
.page{max-width:860px;margin:0 auto;padding:28px 20px}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:20px}
.breadcrumb a{color:var(--primary);text-decoration:none}.breadcrumb a:hover{text-decoration:underline}
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:20px;line-height:1.6}
.alert-error{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}
.alert-success{background:var(--green-lt);color:var(--green);border:1px solid #86efac}
.alert-db{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.alert ul{padding-left:16px;margin-top:4px}.alert li{margin-top:2px}
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-header h2{font-size:15px;font-weight:700}.card-header .icon{font-size:18px}
.card-body{padding:24px}
.form-grid{display:grid;gap:20px}
.form-grid-2{grid-template-columns:1fr 1fr}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:13px;font-weight:600}.required{color:var(--red);margin-left:2px}
.form-hint{font-size:11px;color:var(--muted)}
.form-control{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;color:var(--text);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,164,.1)}
.form-control::placeholder{color:#cbd5e1}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
textarea.form-control{resize:vertical;min-height:90px}
.form-control.is-error{border-color:var(--red)}
.pfx-wrap{position:relative}.pfx-wrap .pfx{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;font-weight:600;pointer-events:none}
.pfx-wrap .form-control{padding-left:48px}
.check-row{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:background .15s}
.check-row:hover{background:#f8fafc}
.check-row input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);cursor:pointer;flex-shrink:0}
.preview-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 16px;font-size:13px;display:none}
.preview-box.visible{display:block}
.preview-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0}
.preview-row:not(:last-child){border-bottom:1px solid var(--border)}
.pkey{color:var(--muted);font-size:12px}.pval{font-weight:600;font-size:13px}
.edit-banner{background:var(--primary-lt);border:1px solid #b2dfdb;border-radius:8px;padding:12px 16px;font-size:13px;color:var(--primary);display:flex;align-items:center;gap:10px;margin-bottom:20px}
.status-group{display:flex;flex-wrap:wrap;gap:8px}
.s-radio{display:none}
.s-label{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid var(--border);transition:all .15s;background:#fff}
.s-radio:checked + .s-label{border-color:var(--primary);background:var(--primary-lt);color:var(--primary)}
.form-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding-top:4px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#0b8483}
.btn-ghost{background:#f1f5f9;color:var(--muted);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border)}
.btn-lg{padding:11px 28px;font-size:15px}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700}
@media(max-width:640px){nav{display:none}.form-grid-2{grid-template-columns:1fr}.form-actions{flex-direction:column;align-items:stretch}.btn-lg{width:100%;justify-content:center}}
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
        <a href="ContractListView.php">📄 Hợp đồng</a>
        <span>›</span>
        <span><?= $isEdit ? 'Chỉnh sửa #' . $contractId : 'Lập mới' ?></span>
    </div>

    <h1 class="page-title"><?= $isEdit ? '✏ Chỉnh sửa hợp đồng' : '📋 Lập hợp đồng mới' ?></h1>
    <p class="page-desc"><?= $isEdit ? 'Cập nhật thông tin hợp đồng thuê phòng.' : 'Điền đầy đủ thông tin để tạo hợp đồng thuê phòng cho sinh viên.' ?></p>

    <?php if ($isEdit && $contract): ?>
    <div class="edit-banner">
        📝 &nbsp;Hợp đồng <strong><?= htmlspecialchars($contract['contract_code']) ?></strong>
        — <strong><?= htmlspecialchars($contract['full_name']) ?></strong>
        · Phòng <strong><?= htmlspecialchars($contract['room_number']) ?></strong>
        · <strong><?= htmlspecialchars($contract['building_name']) ?></strong>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <span>⚠</span>
        <div><strong>Vui lòng kiểm tra lại:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    </div>
    <?php endif; ?>

    <?php if ($dbError): ?>
    <div class="alert alert-db">🛑 <?= $dbError ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="contractForm" novalidate>
        <?php if ($isEdit): ?><input type="hidden" name="contract_id" value="<?= $contractId ?>"><?php endif; ?>

        <div class="card">
            <div class="card-header"><span class="icon">📄</span><h2>Thông tin chung</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="contract_code">Mã hợp đồng <span class="required">*</span></label>
                        <input type="text" name="contract_code" id="contract_code"
                               class="form-control <?= in_array('Mã hợp đồng không được để trống.', $errors) ? 'is-error' : '' ?>"
                               placeholder="VD: HD-2025-00006"
                               value="<?= htmlspecialchars($fContractCode) ?>" required>
                        <div class="form-hint">Mã phải duy nhất. Gợi ý: HD-<?= date('Y') ?>-XXXXX</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="signed_date">Ngày ký</label>
                        <input type="date" name="signed_date" id="signed_date"
                               class="form-control" value="<?= htmlspecialchars($fSignedDate) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="icon">👤</span><h2>Sinh viên</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Chọn sinh viên <span class="required">*</span></label>
                        <select name="student_id" id="student_id"
                                class="form-control <?= in_array('Vui lòng chọn sinh viên.', $errors) ? 'is-error' : '' ?>"
                                onchange="previewStudent(this)" required>
                            <option value="">— Chọn sinh viên —</option>
                            <?php foreach ($students as $sv): ?>
                            <option value="<?= $sv['student_id'] ?>"
                                    data-code="<?= htmlspecialchars($sv['student_code']) ?>"
                                    data-faculty="<?= htmlspecialchars($sv['faculty']) ?>"
                                    <?= (string)$fStudentId === (string)$sv['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sv['full_name']) ?> — <?= htmlspecialchars($sv['student_code']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Chỉ hiển thị sinh viên đang học (active).</div>
                    </div>
                    <div class="preview-box" id="studentPreview">
                        <div class="preview-row"><span class="pkey">MSSV</span><span class="pval" id="pvCode">—</span></div>
                        <div class="preview-row"><span class="pkey">Khoa</span><span class="pval" id="pvFaculty">—</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="icon">🛏</span><h2>Phòng thuê</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="room_id">Chọn phòng <span class="required">*</span></label>
                        <select name="room_id" id="room_id"
                                class="form-control <?= in_array('Vui lòng chọn phòng.', $errors) ? 'is-error' : '' ?>"
                                onchange="previewRoom(this)" required>
                            <option value="">— Chọn phòng —</option>
                            <?php
                            $curB = '';
                            foreach ($rooms as $r):
                                if ($r['building_name'] !== $curB):
                                    if ($curB !== '') echo '</optgroup>';
                                    echo '<optgroup label="🏢 ' . htmlspecialchars($r['building_name']) . '">';
                                    $curB = $r['building_name'];
                                endif;
                                $statusTag = match($r['room_status']) {
                                    'available'   => '[Còn trống]',
                                    'full'        => '[Đầy]',
                                    'maintenance' => '[Bảo trì]',
                                    default       => '[' . $r['room_status'] . ']'
                                };
                            ?>
                            <option value="<?= $r['room_id'] ?>"
                                    data-type="<?= htmlspecialchars($r['type_name']) ?>"
                                    data-price="<?= number_format((float)$r['price_per_month'], 0, ',', '.') . ' VND' ?>"
                                    data-price-raw="<?= (float)$r['price_per_month'] ?>"
                                    data-building="<?= htmlspecialchars($r['building_name']) ?>"
                                    data-floor="Tầng <?= $r['floor'] ?>"
                                    data-status="<?= $r['room_status'] ?>"
                                    <?= (string)$fRoomId === (string)$r['room_id'] ? 'selected' : '' ?>>
                                Phòng <?= htmlspecialchars($r['room_number']) ?> <?= $statusTag ?> · <?= htmlspecialchars($r['type_name']) ?>
                            </option>
                            <?php endforeach; if ($curB !== '') echo '</optgroup>'; ?>
                        </select>
                        <div class="form-hint">Hiển thị tất cả phòng. Nên chọn phòng "Còn trống".</div>
                    </div>
                    <div class="preview-box" id="roomPreview">
                        <div class="preview-row"><span class="pkey">Tòa nhà</span><span class="pval" id="pvBuilding">—</span></div>
                        <div class="preview-row"><span class="pkey">Tầng</span><span class="pval" id="pvFloor">—</span></div>
                        <div class="preview-row"><span class="pkey">Loại phòng</span><span class="pval" id="pvType">—</span></div>
                        <div class="preview-row"><span class="pkey">Trạng thái</span><span class="pval" id="pvStatus">—</span></div>
                        <div class="preview-row"><span class="pkey">Giá niêm yết / tháng</span><span class="pval" id="pvPrice">—</span></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="monthly_fee_snapshot">
                            Giá thuê chốt trong HĐ (VND) <span class="required">*</span>
                        </label>
                        <div class="pfx-wrap">
                            <span class="pfx">VND</span>
                            <input type="number" name="monthly_fee_snapshot" id="monthly_fee_snapshot"
                                   class="form-control <?= in_array('Giá thuê tháng không hợp lệ.', $errors) ? 'is-error' : '' ?>"
                                   placeholder="0" min="0" step="50000"
                                   value="<?= htmlspecialchars($fMonthlyFee) ?>" required>
                        </div>
                        <div class="form-hint">Tự động điền khi chọn phòng. Có thể chỉnh sửa nếu có thỏa thuận riêng.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="icon">📅</span><h2>Thời hạn & Đặt cọc</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="start_date">Ngày bắt đầu <span class="required">*</span></label>
                            <input type="date" name="start_date" id="start_date"
                                   class="form-control <?= in_array('Ngày bắt đầu không được để trống.', $errors) ? 'is-error' : '' ?>"
                                   value="<?= htmlspecialchars($fStart) ?>" onchange="calcDuration()" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="end_date">Ngày kết thúc <span class="required">*</span></label>
                            <input type="date" name="end_date" id="end_date"
                                   class="form-control <?= in_array('Ngày kết thúc không được để trống.', $errors) ? 'is-error' : '' ?>"
                                   value="<?= htmlspecialchars($fEnd) ?>" onchange="calcDuration()" required>
                            <div class="form-hint" id="durationHint"></div>
                        </div>
                    </div>
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="deposit_amount">Tiền đặt cọc (VND) <span class="required">*</span></label>
                            <div class="pfx-wrap">
                                <span class="pfx">VND</span>
                                <input type="number" name="deposit_amount" id="deposit_amount"
                                       class="form-control <?= in_array('Số tiền đặt cọc không hợp lệ (nhập 0 nếu không có cọc).', $errors) ? 'is-error' : '' ?>"
                                       placeholder="0" min="0" step="50000"
                                       value="<?= htmlspecialchars($fDeposit) ?>" required>
                            </div>
                            <div class="form-hint">Nhập 0 nếu không yêu cầu đặt cọc.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Trạng thái tiền cọc</label>
                            <label class="check-row">
                                <input type="checkbox" name="deposit_paid" id="deposit_paid" value="1"
                                       onchange="toggleDepositDate(this)"
                                       <?= $fDepositPaid ? 'checked' : '' ?>>
                                <span>Đã thu tiền đặt cọc</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="depositDateWrap" style="<?= $fDepositPaid ? '' : 'display:none' ?>">
                        <label class="form-label" for="deposit_paid_date">Ngày thu cọc</label>
                        <input type="date" name="deposit_paid_date" id="deposit_paid_date"
                               class="form-control" value="<?= htmlspecialchars($fDepositDate) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="icon">📝</span><h2>Điều khoản & Trạng thái</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="terms">Điều khoản hợp đồng</label>
                        <textarea name="terms" id="terms" class="form-control"
                                  placeholder="Nội dung điều khoản, quy định bổ sung, cam kết..."><?= htmlspecialchars($fTerms) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trạng thái hợp đồng</label>
                        <div class="status-group">
                            <?php foreach ($statusOptions as $val => $label): ?>
                            <input type="radio" name="status_code" id="s_<?= $val ?>" class="s-radio" value="<?= $val ?>"
                                   <?= $fStatus === $val ? 'checked' : '' ?>>
                            <label class="s-label" for="s_<?= $val ?>"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <?= $isEdit ? '💾 Lưu thay đổi' : '✅ Tạo hợp đồng' ?>
            </button>
            <a href="ContractListView.php" class="btn btn-ghost btn-lg">✕ Hủy</a>
            <?php if ($isEdit): ?>
            <span style="margin-left:auto;font-size:12px;color:var(--muted)">
                ID: <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px">#<?= $contractId ?></code>
            </span>
            <?php endif; ?>
        </div>
    </form>
</main>

<script>
function previewStudent(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('pvCode').textContent    = opt.dataset.code    || '—';
    document.getElementById('pvFaculty').textContent = opt.dataset.faculty || '—';
    document.getElementById('studentPreview').classList.toggle('visible', !!sel.value);
}
function previewRoom(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('pvBuilding').textContent = opt.dataset.building || '—';
    document.getElementById('pvFloor').textContent    = opt.dataset.floor    || '—';
    document.getElementById('pvType').textContent     = opt.dataset.type     || '—';
    document.getElementById('pvPrice').textContent    = opt.dataset.price ? opt.dataset.price : '—';
    const st = opt.dataset.status || '';
    const stMap = {available:'✅ Còn trống', full:'🔴 Đã đầy', maintenance:'🔧 Bảo trì'};
    document.getElementById('pvStatus').textContent = stMap[st] || st || '—';
    document.getElementById('roomPreview').classList.toggle('visible', !!sel.value);
    const fee = document.getElementById('monthly_fee_snapshot');
    if (sel.value && opt.dataset.priceRaw && (!fee.value || fee.value === '0')) {
        fee.value = opt.dataset.priceRaw;
    }
}
function calcDuration() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    const hint = document.getElementById('durationHint');
    if (!s || !e) { hint.textContent = ''; return; }
    const diff = Math.round((new Date(e) - new Date(s)) / 86400000);
    if (diff <= 0) {
        hint.style.color = 'var(--red)';
        hint.textContent = '⚠ Ngày kết thúc phải sau ngày bắt đầu.';
    } else {
        hint.style.color = 'var(--muted)';
        hint.textContent = `Thời hạn: ${diff} ngày (~${Math.round(diff/30)} tháng)`;
    }
}
function toggleDepositDate(cb) {
    document.getElementById('depositDateWrap').style.display = cb.checked ? '' : 'none';
}
window.addEventListener('DOMContentLoaded', () => {
    const sv = document.getElementById('student_id');
    const rm = document.getElementById('room_id');
    if (sv.value) previewStudent(sv);
    if (rm.value) previewRoom(rm);
    calcDuration();
});
</script>
</body>
</html>