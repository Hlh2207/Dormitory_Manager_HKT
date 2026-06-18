<?php
// ============================================================
//  ProfileView.php — Admin & Staff Profile & Change Password
//  Connects to: users table (Strict Access Control)
// ============================================================

// ---------- 1. DATABASE CONNECTION ----------
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
    die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Khởi tạo session để kiểm tra danh tính đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// KHÓA QUYỀN TRUY CẬP NGHIÊM NGẶT
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}

// Nếu là sinh viên, hủy session và đẩy ngược ra trang Login ngay lập tức
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    session_destroy();
    header("Location: LoginView.php?error=unauthorized");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// ---------- 2. TRUY VẤN THÔNG TIN TÀI KHOẢN QUẢN TRỊ ----------
$userSql = "SELECT user_id, username, password, email, full_name, phone, role, avatar_url FROM users WHERE user_id = :user_id LIMIT 1";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute(['user_id' => $current_user_id]);
$userInfo = $userStmt->fetch();

// ---------- 3. XỬ LÝ ĐỔI MẬT KHẨU ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_change_password'])) {
    $old_pass = trim($_POST['old_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
        $errorMsg = "Please fill in all password fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $errorMsg = "New password and confirmation do not match.";
    } elseif (strlen($new_pass) < 6) {
        $errorMsg = "New password must be at least 6 characters long.";
    } else {
        // Kiểm tra mật khẩu cũ (hỗ trợ pass thô, mật khẩu mẫu hoặc bcrypt hash)
        $isOldPassValid = false;
        if ($old_pass === $userInfo['password'] || password_verify($old_pass, $userInfo['password']) || $old_pass === $userInfo['username']) {
            $isOldPassValid = true;
        }

        if ($isOldPassValid) {
            // Hash mật khẩu mới bằng Bcrypt
            $hashed_new_pass = password_hash($new_pass, PASSWORD_BCRYPT);
            $updatePassSql = "UPDATE users SET password = :new_pass WHERE user_id = :user_id";
            $updatePassStmt = $pdo->prepare($updatePassSql);
            $updatePassStmt->execute([
                'new_pass' => $hashed_new_pass,
                'user_id'  => $current_user_id
            ]);

            $successMsg = "Password updated successfully!";
            $userInfo['password'] = $hashed_new_pass;
        } else {
            $errorMsg = "Incorrect current password.";
        }
    }
}

$pageTitle = "Management Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - VNU Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #D87093; /* Hồng vỏ đỗ chủ đạo */
            --primary-hover: #C25A7A; 
            --bg-light: #fff5f7;
        }
        body {
            background-color: #fcf8f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 2px solid var(--primary-color);
            padding: 10px 20px;
        }
        .navbar-brand-custom {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 22px;
            text-decoration: none;
        }
        .profile-header-card {
            background: linear-gradient(135deg, #B8506E 0%, #D87093 100%);
            color: white;
            border-radius: 12px;
            border: none;
        }
        .avatar-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: var(--primary-color);
            border: 3px solid rgba(255, 255, 255, 0.4);
            overflow: hidden;
        }
        .avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(216, 112, 147, 0.08);
            background: #ffffff;
        }
        .card-custom-title {
            color: #4a5568;
            font-weight: 600;
            font-size: 16px;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: 600;
            color: #718096;
            font-size: 13px;
            margin-bottom: 2px;
        }
        .info-value {
            color: #2d3748;
            font-size: 15px;
            margin-bottom: 20px;
        }
        .btn-pink {
            background-color: var(--primary-color);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-pink:hover {
            background-color: var(--primary-hover);
            color: white;
        }
        .input-group-text {
            cursor: pointer;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom" href="BuildingListView.php">
            <i class="fa-solid fa-hotel me-2"></i>VNU Campus
        </a>
        <div class="ms-auto">
            <span class="text-muted me-3">Active Session: <strong><?= htmlspecialchars($userInfo['full_name']) ?></strong></span>
            <a href="LoginView.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="card profile-header-card p-4 mb-4">
        <div class="d-flex align-items-center gap-4 w-100">
            <div class="avatar-wrapper">
                <?php if (!empty($userInfo['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($userInfo['avatar_url']) ?>" alt="Avatar">
                <?php else: ?>
                    <i class="fa-solid fa-user-shield"></i>
                <?php endif; ?>
            </div>
            <div>
                <h2 class="mb-1 fw-bold"><?= htmlspecialchars($userInfo['full_name']) ?></h2>
                <p class="mb-0 text-white-50 text-uppercase" style="font-size: 11px; letter-spacing: 1px;">
                    System Privileges: <span class="badge bg-white text-dark ms-1"><?= htmlspecialchars($userInfo['role']) ?></span>
                </p>
            </div>
            
            <div class="ms-auto">
                <a href="EditProfileView.php" class="btn btn-light btn-sm fw-bold text-dark px-3 py-2" style="border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <i class="fa-solid fa-user-pen me-2" style="color: #D87093;"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card card-custom p-4 h-100">
                <h4 class="card-custom-title"><i class="fa-solid fa-user-gear me-2" style="color:var(--primary-color)"></i>Manager Information</h4>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="info-label">System Username</div>
                        <div class="info-value"><strong><?= htmlspecialchars($userInfo['username']) ?></strong></div>
                    </div>
                    <div class="col-md-12">
                        <div class="info-label">Official Email</div>
                        <div class="info-value"><?= htmlspecialchars($userInfo['email']) ?></div>
                    </div>
                    <div class="col-md-12">
                        <div class="info-label">Contact Hotline</div>
                        <div class="info-value mb-0"><?= htmlspecialchars($userInfo['phone'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-custom p-4 h-100">
                <h4 class="card-custom-title"><i class="fa-solid fa-key me-2" style="color:var(--primary-color)"></i>Security & Access Credentials</h4>
                
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 14px;" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success py-2" style="font-size: 14px;" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
                    </div>
                <?php endif; ?>

                <form action="ProfileView.php" method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-shield text-muted"></i></span>
                            <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Enter current password" required>
                            <span class="input-group-text toggle-pwd" data-target="old_password"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min 6 characters" required>
                            <span class="input-group-text toggle-pwd" data-target="new_password"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-check-double text-muted"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                            <span class="input-group-text toggle-pwd" data-target="confirm_password"><i class="fa-solid fa-eye text-muted"></i></span>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="btn_change_password" class="btn btn-pink py-2">
                            <i class="fa-solid fa-floppy-disk me-2"></i> Update Security Key
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // JS xử lý hiệu ứng click con mắt ẩn/hiện mật khẩu đồng thời cho cả 3 ô
    document.querySelectorAll('.toggle-pwd').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const inputField = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (inputField.type === 'password') {
                inputField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
</script>
</body>
</html>