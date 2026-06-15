<?php
// ============================================================
//  ContractFormView.php — Lập / chỉnh sửa hợp đồng thuê phòng
//  Kết nối bảng: contracts, students, users, rooms, room_types, buildings
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

// ---------- CHẾ ĐỘ SỬA ----------
$contractId = filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT);
$contract   = null;
$isEdit     = false;

if ($contractId) {
    $stmt = $pdo->prepare("
        SELECT c.*, s.full_name, s.student_code, s.faculty,
               r.room_number, b.building_name,
               rt.type_name, rt.price_per_month
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

// ---------- XỬ LÝ LƯU ----------
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId       = filter_input(INPUT_POST, 'student_id',          FILTER_VALIDATE_INT);
    $roomId          = filter_input(INPUT_POST, 'room_id',             FILTER_VALIDATE_INT);
    $startDate       = trim($_POST['start_date']                       ?? '');
    $endDate         = trim($_POST['end_date']                         ?? '');
    $deposit         = filter_input(INPUT_POST, 'deposit_amount',      FILTER_VALIDATE_FLOAT);
    $monthlyFee      = filter_input(INPUT_POST, 'monthly_fee_snapshot',FILTER_VALIDATE_FLOAT);
    $depositPaid     = isset($_POST['deposit_paid']) ? 1 : 0;
    $depositPaidDate = trim($_POST['deposit_paid_date']                ?? '');
    $signedDate      = trim($_POST['signed_date']                      ?? '');
    $terms           = trim($_POST['terms']                            ?? '');
    $statusCode      = trim($_POST['status_code']                      ?? 'draft');
    $contractCode    = trim($_POST['contract_code']                    ?? '');

    if (!$studentId)                    $errors[] = 'Vui lòng chọn sinh viên.';
    if (!$roomId)                       $errors[] = 'Vui lòng chọn phòng.';
    if (!$contractCode)                 $errors[] = 'Mã hợp đồng không được để trống.';
    if (!$startDate)                    $errors[] = 'Ngày bắt đầu không được để trống.';
    if (!$endDate)                      $errors[] = 'Ngày kết thúc không được để trống.';
    if ($startDate && $endDate && $startDate >= $endDate)
                                        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    if ($deposit === false || $deposit < 0)
                                        $errors[] = 'Số tiền đặt cọc không hợp lệ.';
    if ($monthlyFee === false || $monthlyFee <= 0)
                                        $errors[] = 'Giá thuê hàng tháng không hợp lệ.';

    if (empty($errors)) {
        $dpDate = ($depositPaid && $depositPaidDate) ? $depositPaidDate : null;
        $sDate  = $signedDate ?: null;

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
                $deposit, $depositPaid, $dpDate,
                $monthlyFee, $terms, $sDate, $statusCode,
                $contractId
            ]);
            $success = 'Cập nhật hợp đồng thành công.';
        } else {
            $pdo->prepare("
                INSERT INTO contracts
                    (student_id, room_id, contract_code,
                     start_date, end_date,
                     deposit_amount, deposit_paid, deposit_paid_date,
                     monthly_fee_snapshot, terms, signed_date, status_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $studentId, $roomId, $contractCode,
                $startDate, $endDate,
                $deposit, $depositPaid, $dpDate,
                $monthlyFee, $terms, $sDate, $statusCode
            ]);
            header('Location: ContractListView.php');
            exit;
        }
    }
}

// ---------- DỮ LIỆU DROPDOWN: SINH VIÊN ----------
$students = $pdo->query("
    SELECT s.student_id, s.student_code, s.full_name, s.faculty
    FROM students s
    WHERE s.status_code = 'active'
    ORDER BY s.full_name
")->fetchAll();

// ---------- DỮ LIỆU DROPDOWN: PHÒNG (JOIN room_types, buildings) ----------
$rooms = $pdo->query("
    SELECT r.room_id, r.room_number, r.floor,
           rt.type_name, rt.price_per_month,
           b.building_name, b.building_id
    FROM rooms      r
    JOIN room_types rt ON rt.type_id    = r.type_id
    JOIN buildings  b  ON b.building_id = r.building_id
    WHERE r.status_code = 'available'
      AND rt.is_active  = 1
    ORDER BY b.building_name, r.floor, r.room_number
")->fetchAll();

// Giá trị form (POST khi lỗi → dữ liệu hợp đồng → mặc định)
$fStudentId    = $_POST['student_id']           ?? $contract['student_id']           ?? '';
$fRoomId       = $_POST['room_id']              ?? $contract['room_id']              ?? '';
$fContractCode = $_POST['contract_code']        ?? $contract['contract_code']        ?? ('HD-' . date('Ymd') . '-');
$fStart        = $_POST['start_date']           ?? $contract['start_date']           ?? '';
$fEnd          = $_POST['end_date']             ?? $contract['end_date']             ?? '';
$fDeposit      = $_POST['deposit_amount']       ?? $contract['deposit_amount']       ?? '';
$fMonthlyFee   = $_POST['monthly_fee_snapshot'] ?? $contract['monthly_fee_snapshot'] ?? '';
$fDepositPaid  = $_POST['deposit_paid']         ?? $contract['deposit_paid']         ?? 0;
$fDepositDate  = $_POST['deposit_paid_date']    ?? $contract['deposit_paid_date']    ?? '';
$fSignedDate   = $_POST['signed_date']          ?? $contract['signed_date']          ?? date('Y-m-d');
$fTerms        = $_POST['terms']                ?? $contract['terms']                ?? '';
$fStatus       = $_POST['status_code']          ?? $contract['status_code']          ?? 'draft';

$statusOptions = [
    'draft'      => 'Nháp',
    'active'     => 'Đang hiệu lực',
    'expired'    => 'Hết hạn',
    'terminated' => 'Đã chấm dứt',
];
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
    --bg:#f0f2f5;--card:#fff;--primary:#1d4ed8;--primary-lt:#eff6ff;
    --text:#1e293b;--muted:#64748b;--border:#e2e8f0;
    --green:#16a34a;--green-lt:#dcfce7;
    --red:#dc2626;--red-lt:#fee2e2;
    --yellow:#ca8a04;--yellow-lt:#fef9c3;
    --blue:#2563eb;--blue-lt:#dbeafe;
    --radius:12px;--shadow:0 1px 3px rgba(0,0,0,.08);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* HEADER */
.site-header{background:var(--primary);color:#fff;padding:0 24px;display:flex;align-items:center;gap:16px;height:60px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.logo{font-size:20px;font-weight:700}
.subtitle{font-size:13px;opacity:.75}
nav{margin-left:auto;display:flex;gap:4px}
nav a{color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-size:13px;opacity:.8;transition:background .15s}
nav a:hover,nav a.active{background:rgba(255,255,255,.15);opacity:1}

/* PAGE */
.page{max-width:860px;margin:0 auto;padding:28px 20px}
.page-title{font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:6px}
.page-title::before{content:'';display:block;width:4px;height:28px;background:var(--primary);border-radius:2px}
.page-desc{color:var(--muted);font-size:14px;margin-bottom:24px}

/* BREADCRUMB */
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);margin-bottom:20px}
.breadcrumb a{color:var(--primary);text-decoration:none}.breadcrumb a:hover{text-decoration:underline}

/* ALERT */
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:20px}
.alert-error{background:var(--red-lt);color:var(--red);border:1px solid #fca5a5}
.alert-success{background:var(--green-lt);color:var(--green);border:1px solid #86efac}
.alert ul{padding-left:16px;margin-top:4px}
.alert li{margin-top:2px}

/* CARD */
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.card-header h2{font-size:15px;font-weight:700;color:var(--text)}
.card-header .card-icon{font-size:18px}
.card-body{padding:24px}

/* FORM GRID */
.form-grid{display:grid;gap:20px}
.form-grid-2{grid-template-columns:1fr 1fr}
.form-grid-3{grid-template-columns:1fr 1fr 1fr}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:13px;font-weight:600;color:var(--text)}
.form-label .required{color:var(--red);margin-left:2px}
.form-hint{font-size:11px;color:var(--muted);margin-top:2px}

/* INPUTS */
.form-control{
    width:100%;padding:10px 14px;border:1px solid var(--border);
    border-radius:8px;font-size:14px;color:var(--text);
    background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;
    font-family:inherit;
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(29,78,216,.1)}
.form-control::placeholder{color:#cbd5e1}
.form-control:disabled{background:#f8fafc;color:var(--muted);cursor:not-allowed}
select.form-control{cursor:pointer;appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
textarea.form-control{resize:vertical;min-height:90px}
.form-control.is-error{border-color:var(--red)}
input[type="date"].form-control{color:var(--text)}

/* PREFIX INPUT */
.input-prefix-wrap{position:relative}
.input-prefix-wrap .prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;font-weight:600;pointer-events:none}
.input-prefix-wrap .form-control{padding-left:52px}

/* CHECKBOX ROW */
.check-row{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:background .15s}
.check-row:hover{background:#f8fafc}
.check-row input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
.check-row span{font-size:14px;font-weight:500}

/* PREVIEW BOX */
.preview-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 16px;font-size:13px;display:none}
.preview-box.visible{display:block}
.preview-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0}
.preview-row:not(:last-child){border-bottom:1px solid var(--border)}
.preview-key{color:var(--muted);font-size:12px}
.preview-val{font-weight:600;font-size:13px}

/* EDIT BANNER */
.edit-banner{background:var(--primary-lt);border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;font-size:13px;color:var(--primary);display:flex;align-items:center;gap:10px;margin-bottom:20px}

/* STATUS RADIO */
.status-group{display:flex;flex-wrap:wrap;gap:8px}
.status-option{display:none}
.status-label{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid var(--border);transition:all .15s;background:#fff}
.status-option:checked + .status-label{border-color:var(--primary);background:var(--primary-lt);color:var(--primary)}

/* BADGE */
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-green{background:var(--green-lt);color:var(--green)}
.badge-blue{background:var(--blue-lt);color:var(--blue)}
.badge-yellow{background:var(--yellow-lt);color:var(--yellow)}
.badge-red{background:var(--red-lt);color:var(--red)}
.badge-gray{background:#f1f5f9;color:var(--muted)}

/* BUTTONS */
.form-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding-top:4px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:background .15s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:#1e40af}
.btn-ghost{background:#f1f5f9;color:var(--muted);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border)}
.btn-lg{padding:11px 28px;font-size:15px}

/* RESPONSIVE */
@media(max-width:640px){
    nav{display:none}
    .form-grid-2,.form-grid-3{grid-template-columns:1fr}
    .form-actions{flex-direction:column;align-items:stretch}
    .btn-lg{width:100%;justify-content:center}
}
</style>
</head>
<body>

<header class="site-header">
    <div><div class="logo">🏢 KTX Campus</div><div class="subtitle">Hệ thống quản lý ký túc xá</div></div>
    <nav>
        <a href="BuildingListView.php">Tòa nhà</a>
        <a href="StudentListView.php">Sinh viên</a>
        <a href="ContractListView.php" class="active">Hợp đồng</a>
        <a href="#">Hóa đơn</a>
    </nav>
</header>

<main class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="ContractListView.php">📄 Hợp đồng</a>
        <span>›</span>
        <span><?= $isEdit ? 'Chỉnh sửa #' . $contractId : 'Lập hợp đồng mới' ?></span>
    </div>

    <h1 class="page-title"><?= $isEdit ? '✏ Chỉnh sửa hợp đồng' : '📋 Lập hợp đồng mới' ?></h1>
    <p class="page-desc"><?= $isEdit
        ? 'Cập nhật thông tin hợp đồng thuê phòng ký túc xá.'
        : 'Điền đầy đủ thông tin để tạo hợp đồng thuê phòng cho sinh viên.' ?></p>

    <?php if ($isEdit): ?>
    <div class="edit-banner">
        📝 &nbsp;Đang chỉnh sửa hợp đồng <strong><?= htmlspecialchars($contract['contract_code']) ?></strong>
        — <strong><?= htmlspecialchars($contract['full_name']) ?></strong>
        · Phòng <strong><?= htmlspecialchars($contract['room_number']) ?></strong>
        · <strong><?= htmlspecialchars($contract['building_name']) ?></strong>
    </div>
    <?php endif; ?>

    <!-- LỖI -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <span>⚠</span>
        <div><strong>Vui lòng kiểm tra lại:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="contractForm" novalidate>
        <?php if ($isEdit): ?>
        <input type="hidden" name="contract_id" value="<?= $contractId ?>">
        <?php endif; ?>

        <!-- ===== 1. THÔNG TIN CHUNG ===== -->
        <div class="card">
            <div class="card-header"><span class="card-icon">📄</span><h2>Thông tin chung</h2></div>
            <div class="card-body">
                <div class="form-grid form-grid-2">
                    <!-- Mã hợp đồng -->
                    <div class="form-group">
                        <label class="form-label" for="contract_code">Mã hợp đồng <span class="required">*</span></label>
                        <input type="text" name="contract_code" id="contract_code"
                               class="form-control <?= in_array('Mã hợp đồng không được để trống.', $errors) ? 'is-error' : '' ?>"
                               placeholder="VD: HD-20250601-001"
                               value="<?= htmlspecialchars($fContractCode) ?>" required>
                        <div class="form-hint">Mã phải duy nhất trong hệ thống.</div>
                    </div>
                    <!-- Ngày ký -->
                    <div class="form-group">
                        <label class="form-label" for="signed_date">Ngày ký hợp đồng</label>
                        <input type="date" name="signed_date" id="signed_date"
                               class="form-control"
                               value="<?= htmlspecialchars($fSignedDate) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== 2. SINH VIÊN ===== -->
        <div class="card">
            <div class="card-header"><span class="card-icon">👤</span><h2>Sinh viên</h2></div>
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
                        <div class="form-hint">Chỉ hiển thị sinh viên có trạng thái "Đang học".</div>
                    </div>
                    <div class="preview-box" id="studentPreview">
                        <div class="preview-row"><span class="preview-key">MSSV</span><span class="preview-val" id="previewCode">—</span></div>
                        <div class="preview-row"><span class="preview-key">Khoa</span><span class="preview-val" id="previewFaculty">—</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== 3. PHÒNG ===== -->
        <div class="card">
            <div class="card-header"><span class="card-icon">🛏</span><h2>Phòng thuê</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="room_id">Chọn phòng <span class="required">*</span></label>
                        <select name="room_id" id="room_id"
                                class="form-control <?= in_array('Vui lòng chọn phòng.', $errors) ? 'is-error' : '' ?>"
                                onchange="previewRoom(this)" required>
                            <option value="">— Chọn phòng —</option>
                            <?php
                            $curBuilding = '';
                            foreach ($rooms as $r):
                                if ($r['building_name'] !== $curBuilding):
                                    if ($curBuilding !== '') echo '</optgroup>';
                                    echo '<optgroup label="🏢 ' . htmlspecialchars($r['building_name']) . '">';
                                    $curBuilding = $r['building_name'];
                                endif;
                            ?>
                            <option value="<?= $r['room_id'] ?>"
                                    data-type="<?= htmlspecialchars($r['type_name']) ?>"
                                    data-price="<?= number_format((float)$r['price_per_month'], 0, ',', '.') ?>"
                                    data-price-raw="<?= $r['price_per_month'] ?>"
                                    data-building="<?= htmlspecialchars($r['building_name']) ?>"
                                    data-floor="Tầng <?= $r['floor'] ?>"
                                    <?= (string)$fRoomId === (string)$r['room_id'] ? 'selected' : '' ?>>
                                Phòng <?= htmlspecialchars($r['room_number']) ?> · <?= htmlspecialchars($r['type_name']) ?>
                            </option>
                            <?php endforeach; if ($curBuilding !== '') echo '</optgroup>'; ?>
                        </select>
                        <div class="form-hint">Chỉ hiển thị phòng trạng thái "available".</div>
                    </div>
                    <div class="preview-box" id="roomPreview">
                        <div class="preview-row"><span class="preview-key">Tòa nhà</span><span class="preview-val" id="previewBuilding">—</span></div>
                        <div class="preview-row"><span class="preview-key">Tầng</span><span class="preview-val" id="previewFloor">—</span></div>
                        <div class="preview-row"><span class="preview-key">Loại phòng</span><span class="preview-val" id="previewType">—</span></div>
                        <div class="preview-row"><span class="preview-key">Giá niêm yết / tháng</span><span class="preview-val" id="previewPrice">—</span></div>
                    </div>

                    <!-- Giá thuê snapshot -->
                    <div class="form-group">
                        <label class="form-label" for="monthly_fee_snapshot">
                            Giá thuê chốt trong hợp đồng (VNĐ) <span class="required">*</span>
                        </label>
                        <div class="input-prefix-wrap">
                            <span class="prefix">₫</span>
                            <input type="number" name="monthly_fee_snapshot" id="monthly_fee_snapshot"
                                   class="form-control <?= in_array('Giá thuê hàng tháng không hợp lệ.', $errors) ? 'is-error' : '' ?>"
                                   placeholder="0" min="0" step="50000"
                                   value="<?= htmlspecialchars($fMonthlyFee) ?>" required>
                        </div>
                        <div class="form-hint">Tự động điền theo loại phòng. Có thể điều chỉnh nếu có thỏa thuận khác.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== 4. THỜI HẠN & ĐẶT CỌC ===== -->
        <div class="card">
            <div class="card-header"><span class="card-icon">📅</span><h2>Thời hạn & Đặt cọc</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- Ngày bắt đầu / kết thúc -->
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="start_date">Ngày bắt đầu <span class="required">*</span></label>
                            <input type="date" name="start_date" id="start_date"
                                   class="form-control <?= in_array('Ngày bắt đầu không được để trống.', $errors) ? 'is-error' : '' ?>"
                                   value="<?= htmlspecialchars($fStart) ?>"
                                   onchange="calcDuration()" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="end_date">Ngày kết thúc <span class="required">*</span></label>
                            <input type="date" name="end_date" id="end_date"
                                   class="form-control <?= in_array('Ngày kết thúc không được để trống.', $errors) ? 'is-error' : '' ?>"
                                   value="<?= htmlspecialchars($fEnd) ?>"
                                   onchange="calcDuration()" required>
                            <div class="form-hint" id="durationHint"></div>
                        </div>
                    </div>

                    <!-- Đặt cọc -->
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="deposit_amount">Số tiền đặt cọc (VNĐ) <span class="required">*</span></label>
                            <div class="input-prefix-wrap">
                                <span class="prefix">₫</span>
                                <input type="number" name="deposit_amount" id="deposit_amount"
                                       class="form-control <?= in_array('Số tiền đặt cọc không hợp lệ.', $errors) ? 'is-error' : '' ?>"
                                       placeholder="0" min="0" step="50000"
                                       value="<?= htmlspecialchars($fDeposit) ?>" required>
                            </div>
                            <div class="form-hint">Thường bằng 1 tháng tiền phòng.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Trạng thái đặt cọc</label>
                            <label class="check-row">
                                <input type="checkbox" name="deposit_paid" id="deposit_paid" value="1"
                                       onchange="toggleDepositDate(this)"
                                       <?= $fDepositPaid ? 'checked' : '' ?>>
                                <span>Đã nhận tiền đặt cọc</span>
                            </label>
                        </div>
                    </div>

                    <!-- Ngày thu cọc (hiện khi tick) -->
                    <div class="form-group" id="depositDateWrap" style="<?= $fDepositPaid ? '' : 'display:none' ?>">
                        <label class="form-label" for="deposit_paid_date">Ngày thu cọc</label>
                        <input type="date" name="deposit_paid_date" id="deposit_paid_date"
                               class="form-control"
                               value="<?= htmlspecialchars($fDepositDate) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== 5. ĐIỀU KHOẢN & TRẠNG THÁI ===== -->
        <div class="card">
            <div class="card-header"><span class="card-icon">📝</span><h2>Điều khoản & Trạng thái</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="terms">Điều khoản hợp đồng</label>
                        <textarea name="terms" id="terms" class="form-control"
                                  placeholder="Nội dung điều khoản, quy định, cam kết thêm..."><?= htmlspecialchars($fTerms) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Trạng thái hợp đồng</label>
                        <div class="status-group">
                            <?php foreach ($statusOptions as $val => $label): ?>
                            <input type="radio" name="status_code" id="status_<?= $val ?>"
                                   class="status-option" value="<?= $val ?>"
                                   <?= $fStatus === $val ? 'checked' : '' ?>>
                            <label class="status-label" for="status_<?= $val ?>"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ACTIONS ===== -->
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
    const box = document.getElementById('studentPreview');
    document.getElementById('previewCode').textContent    = opt.dataset.code    || '—';
    document.getElementById('previewFaculty').textContent = opt.dataset.faculty || '—';
    box.classList.toggle('visible', !!sel.value);
}

function previewRoom(sel) {
    const opt = sel.options[sel.selectedIndex];
    const box = document.getElementById('roomPreview');
    document.getElementById('previewBuilding').textContent = opt.dataset.building || '—';
    document.getElementById('previewFloor').textContent    = opt.dataset.floor    || '—';
    document.getElementById('previewType').textContent     = opt.dataset.type     || '—';
    document.getElementById('previewPrice').textContent    = opt.dataset.price ? opt.dataset.price + ' ₫' : '—';
    box.classList.toggle('visible', !!sel.value);

    // Tự điền giá snapshot nếu chưa có
    const feeInput = document.getElementById('monthly_fee_snapshot');
    if (sel.value && !feeInput.value && opt.dataset.priceRaw) {
        feeInput.value = opt.dataset.priceRaw;
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
        const months = Math.round(diff / 30);
        hint.style.color = 'var(--muted)';
        hint.textContent = `Thời hạn: ${diff} ngày (~${months} tháng)`;
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
PHPEOF
echo "Done"