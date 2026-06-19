<?php
// ============================================================
//  ProfileView.php — Admin & Staff Profile & Change Password
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
    die('<p style="color:red">DB Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    session_destroy();
    header("Location: LoginView.php?error=unauthorized");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// ---------- FETCH USER INFO ----------
$userSql = "SELECT user_id, username, password, email, full_name, phone, role, avatar_url FROM users WHERE user_id = :user_id LIMIT 1";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute(['user_id' => $current_user_id]);
$userInfo = $userStmt->fetch();

// ---------- HANDLE PASSWORD CHANGE ----------
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
        $isOldPassValid = false;
        if ($old_pass === $userInfo['password'] || password_verify($old_pass, $userInfo['password']) || $old_pass === $userInfo['username']) {
            $isOldPassValid = true;
        }

        if ($isOldPassValid) {
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

$pageTitle = "My Profile";
include 'header.php'; 
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<main class="page">
    
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 class="page-title">My Profile</h1>
            <p class="page-desc" style="margin-bottom: 0;">Manage your account information and security keys.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 16px; background: var(--card); padding: 8px 16px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
            <span style="color: var(--muted); font-size: 14px; font-weight: 500;">
                Active Session: <strong style="color: var(--primary-dk);"><?= htmlspecialchars($userInfo['full_name']) ?></strong>
            </span>
            <div style="width: 1px; height: 20px; background: var(--border);"></div>
            <a href="LoginView.php" class="btn btn-danger btn-sm" style="text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="card" style="background: linear-gradient(135deg, var(--primary-dk) 0%, var(--primary) 100%); color: white; border: none; margin-bottom: 24px;">
        <div class="card-body" style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap; padding: 32px 24px;">
            <div class="avatar" style="width: 90px; height: 90px; font-size: 36px; background: white; color: var(--primary); border: 3px solid rgba(255,255,255,0.4); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?php if (!empty($userInfo['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($userInfo['avatar_url']) ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user-shield"></i>
                <?php endif; ?>
            </div>
            <div>
                <h2 style="font-size: 28px; font-weight: 800; margin: 0 0 6px 0; color: white; letter-spacing: -0.5px;">
                    <?= htmlspecialchars($userInfo['full_name']) ?>
                </h2>
                <div style="font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,0.9); display: flex; align-items: center; gap: 8px;">
                    System Privileges: 
                    <span style="background: white; color: var(--primary-dk); padding: 4px 10px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <?= htmlspecialchars($userInfo['role']) ?>
                    </span>
                </div>
            </div>
            <div style="margin-left: auto;">
                <a href="EditProfileView.php" class="btn" style="background: white; color: var(--primary-dk); box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-decoration:none;">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    <div class="form-grid form-grid-2">
        
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h2><i class="fa-solid fa-user-gear" style="margin-right: 8px;"></i> Manager Information</h2>
            </div>
            <div class="card-body">
                <div class="form-group" style="margin-bottom: 24px;">
                    <div class="info-key" style="margin-bottom: 4px; font-size:13px; font-weight:600; color:var(--muted);">System Username</div>
                    <div class="info-val" style="font-size: 16px; font-weight:700; color:var(--text);"><?= htmlspecialchars($userInfo['username']) ?></div>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <div class="info-key" style="margin-bottom: 4px; font-size:13px; font-weight:600; color:var(--muted);">Official Email</div>
                    <div class="info-val" style="font-size: 16px; font-weight:700; color:var(--text);"><?= htmlspecialchars($userInfo['email']) ?></div>
                </div>
                <div class="form-group">
                    <div class="info-key" style="margin-bottom: 4px; font-size:13px; font-weight:600; color:var(--muted);">Contact Hotline</div>
                    <div class="info-val" style="font-size: 16px; font-weight:700; color:var(--text);"><?= htmlspecialchars($userInfo['phone'] ?? '—') ?></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h2><i class="fa-solid fa-shield-halved" style="margin-right: 8px;"></i> Security & Access Credentials</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-error">⚠ <?= htmlspecialchars($errorMsg) ?></div>
                <?php endif; ?>
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success">✔ <?= htmlspecialchars($successMsg) ?></div>
                <?php endif; ?>

                <form action="ProfileView.php" method="POST" autocomplete="off">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">Current Password</label>
                        <div class="pfx-wrap">
                            <span class="pfx"><i class="fa-solid fa-shield"></i></span>
                            <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Enter current password" required style="padding-right: 40px;">
                            <i class="fa-solid fa-eye toggle-pwd" data-target="old_password" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--muted);"></i>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">New Password</label>
                        <div class="pfx-wrap">
                            <span class="pfx"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min 6 characters" required style="padding-right: 40px;">
                            <i class="fa-solid fa-eye toggle-pwd" data-target="new_password" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--muted);"></i>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="form-label">Confirm New Password</label>
                        <div class="pfx-wrap">
                            <span class="pfx"><i class="fa-solid fa-check-double"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required style="padding-right: 40px;">
                            <i class="fa-solid fa-eye toggle-pwd" data-target="confirm_password" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--muted);"></i>
                        </div>
                    </div>

                    <button type="submit" name="btn_change_password" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;">
                        <i class="fa-solid fa-floppy-disk"></i> Update Security Key
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
    document.querySelectorAll('.toggle-pwd').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const inputField = document.getElementById(targetId);
            
            if (inputField.type === 'password') {
                inputField.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    });
</script>
</body>
</html>