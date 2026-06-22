<?php


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


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$errorMsg = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $errorMsg = "Please enter both username and password.";
    } else {
        
        $sql = "SELECT user_id, username, password, role, full_name, status_code FROM users WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $userAccount = $stmt->fetch();

        if ($userAccount) {
            
            if ($userAccount['status_code'] === 'inactive') {
                $errorMsg = "Your account has been locked. Please contact administration.";
            } else {
                
                $isPasswordCorrect = false;
                if ($password === $userAccount['username'] || $password === $userAccount['password'] || password_verify($password, $userAccount['password'])) {
                    $isPasswordCorrect = true;
                }

                if ($isPasswordCorrect) {
                    
                    $_SESSION['user_id']   = $userAccount['user_id'];
                    $_SESSION['username']  = $userAccount['username'];
                    $_SESSION['role']      = $userAccount['role']; // admin, staff, student
                    $_SESSION['full_name'] = $userAccount['full_name'];

                    
                    $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute(['user_id' => $userAccount['user_id']]);

                    
                    header("Location: index.php");
                    exit();
                } else {
                    $errorMsg = "Invalid username or password.";
                }
            }
        } else {
            $errorMsg = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Campus Dormitory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #b8506e;
            --bg-gradient: linear-gradient(135deg, #B8506E 0%, #FECFEF 100%); /* Nền chuyển màu từ hồng đậm sang hồng pastel */
        }
        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            background: #ffffff;
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .card-header-custom {
            background: var(--primary-color);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .card-header-custom h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .card-header-custom p {
            margin: 5px 0 0 0;
            font-size: 13px;
            opacity: 0.8;
        }
        .btn-primary-custom {
            background-color: var(--primary-color);
            border: none;
            padding: 11px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            background-color: #C25A7A; /* Màu hồng khi hover */
            transform: translateY(-1px);
        }
        .input-group-text {
            cursor: pointer;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        .footer-text {
            font-size: 12px;
            color: #a0aec0;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center">
    <div class="login-card card">
        <div class="card-header-custom">
            <h3>VNU CAMPUS</h3>
            <p>Dormitory Management System</p>
        </div>
        
        <div class="card-body p-4">
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger alert-dismissible fade show font-size-14" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="LoginView.php" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label font-weight-bold" style="font-size: 14px; font-weight: 600;">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter your username" required 
                               value="<?= htmlspecialchars($username ?? '') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label" style="font-size: 14px; font-weight: 600;">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <span class="input-group-text" id="togglePassword">
                            <i class="fa-solid fa-eye text-muted" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-primary-custom text-white">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                    </button>
                </div>
            </form>
            
            <div class="footer-text">
                &copy; 2026 Campus Dormitory HKT Team. All rights reserved.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        // Kiểm tra loại thuộc tính hiện tại để hoán đổi
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            // Đổi sang icon mắt gạch chéo khi hiện mật khẩu
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            // Đổi về icon mắt thường khi ẩn mật khẩu
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    });
</script>

</body>
</html>