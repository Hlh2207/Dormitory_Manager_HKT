<?php
// ============================================================
//  EditProfileView.php — Chỉnh sửa hồ sơ Admin/Staff
//  Connects to: users table
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

// Security & Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginView.php");
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    session_destroy();
    header("Location: LoginView.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// Fetch current user data to populate the form
$userSql = "SELECT user_id, username, email, full_name, phone FROM users WHERE user_id = :user_id LIMIT 1";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute(['user_id' => $current_user_id]);
$userInfo = $userStmt->fetch();

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name) || empty($email)) {
        $errorMsg = "Full Name and Email are required fields.";
    } else {
        $updateSql = "UPDATE users SET full_name = :full_name, email = :email, phone = :phone WHERE user_id = :user_id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            'full_name' => $full_name,
            'email'     => $email,
            'phone'     => $phone,
            'user_id'   => $current_user_id
        ]);

        $_SESSION['full_name'] = $full_name;
        $successMsg = "Profile information updated successfully!";
        
        $userInfo['full_name'] = $full_name;
        $userInfo['email']     = $email;
        $userInfo['phone']     = $phone;
    }
}

$pageTitle = "Edit Profile";
include 'header.php'; // Includes your team's shared header
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    /* Centers the edit container perfectly on the screen */
    .edit-profile-container {
        max-width: 650px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card-edit-section {
        border: 1px solid rgba(216, 112, 147, 0.2) !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
        background: #ffffff !important;
    }
    .card-edit-title {
        color: #4a5568;
        font-weight: 600;
        font-size: 18px;
        border-bottom: 2px solid #f7fafc;
        padding-bottom: 12px;
        margin-bottom: 25px;
    }
    .form-label {
        font-weight: 600;
        color: #4a5568;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .form-control:focus {
        border-color: #D87093 !important;
        box-shadow: 0 0 0 0.25rem rgba(216, 112, 147, 0.15) !important;
    }
    .btn-pink-save {
        background-color: #D87093 !important;
        border: none !important;
        color: white !important;
        font-weight: 600 !important;
        padding: 10px !important;
        border-radius: 8px !important;
        transition: all 0.2s;
    }
    .btn-pink-save:hover {
        background-color: #C25A7A !important;
        box-shadow: 0 4px 8px rgba(216, 112, 147, 0.25);
    }
    .back-link {
        color: #D87093;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: color 0.2s;
    }
    .back-link:hover {
        color: #C25A7A;
    }
</style>

<div class="edit-profile-container">
    
    <div class="mb-3">
        <a href="ProfileView.php" class="back-link">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Profile
        </a>
    </div>

    <div class="card card-edit-section p-4">
        <h4 class="card-edit-title">
            <i class="fa-solid fa-user-pen me-2" style="color: #D87093;"></i> Edit Personal Profile
        </h4>

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

        <form action="EditProfileView.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label text-muted">Username (Read-only)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="text" class="form-control bg-light text-muted" value="<?= htmlspecialchars($userInfo['username']) ?>" readonly>
                </div>
            </div>

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?= htmlspecialchars($userInfo['full_name']) ?>" required placeholder="Enter your full name">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope text-muted"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($userInfo['email']) ?>" required placeholder="Enter your email address">
                </div>
            </div>

            <div class="mb-4">
                <label for="phone" class="form-label">Contact Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-phone text-muted"></i></span>
                    <input type="text" class="form-control" id="phone" name="phone" 
                           value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>" placeholder="Enter your phone number">
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" name="btn_update_profile" class="btn btn-pink-save py-2">
                    <i class="fa-solid fa-check me-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>