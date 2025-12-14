<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name); 
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$error_message = '';
$success_message = '';
$token = '';
$is_valid = false;
$cvsuid = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT cvsuid, expires FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset_record = $result->fetch_assoc();
    $stmt->close();

    if ($reset_record) {
        $expiry_time = strtotime($reset_record['expires']);
        
        if (time() < $expiry_time) {
            $is_valid = true;
            $cvsuid = $reset_record['cvsuid'];
        } else {
            $error_message = "Password reset link has expired. Please request a new one.";
            
            $conn->query("DELETE FROM password_resets WHERE token = '{$token}'");
        }
    } else {
        $error_message = "Invalid or previously used reset link.";
    }
} else {
    $error_message = "A valid reset token is required to reset your password.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_submit']) && $is_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters.';
    } else {
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE cvsuid = ?");
        $stmt_update->bind_param("ss", $hashed_password, $cvsuid);

        if ($stmt_update->execute()) {
            
            $conn->query("DELETE FROM password_resets WHERE token = '{$token}'");
            
            $success_message = "Your password has been successfully reset! You can now log in.";
            $is_valid = false;
        } else {
            $error_message = "Database error: Failed to update password.";
        }
        $stmt_update->close();
    }
}

if ($conn && $conn->ping()) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="marketplace_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
    <div class="logo">
        <h2>
            <a href="index.php">CvSU MarketPlace</a>
        </h2>
    </div>
</nav>

<section class="account-page">
    <div class="form-container">

        <?php 
        if ($error_message) {
            echo '<div class="alert alert-error">'. htmlspecialchars($error_message) .'</div>';
        } elseif ($success_message) {
            echo '<div class="alert alert-success">'. htmlspecialchars($success_message) .'</div>';
        }
        ?>

        <div class="form-reset-password active">
            <h2><i class="fa-solid fa-lock-open"></i> Set New Password</h2>
            
            <?php if ($is_valid && !$success_message): ?>
                <p>Enter and confirm your new password below.</p>
                <form action="reset_password_form.php?token=<?= htmlspecialchars($token) ?>" method="POST">
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="reset_submit" class="btn-primary">Reset Password</button>
                </form>
            <?php elseif (!$is_valid || $success_message): ?>
                <p class="switch-form-link">
                    <a href="account.php">Go to Login Page</a>
                </p>
            <?php endif; ?>

        </div>
    </div>
</section>

<footer>
    <p>© 2025 Cavite State University | CvSU MarketPlace</p>
</footer>

</body>
</html>