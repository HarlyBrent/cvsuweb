<?php

if (session_status() == PHP_SESSION_NONE) session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name); 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$isLoggedIn = isset($_SESSION['cvsuid']);
$cvsuid = $isLoggedIn ? $_SESSION['cvsuid'] : '';
$is_admin = false; 

if ($isLoggedIn) { 
    header('Location: index.php'); 
    exit(); 
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$initial_form = 'login'; 
$uploaded_proof_path = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_submit'])) {
        $initial_form = 'login';
    } elseif (isset($_POST['register_submit'])) {
        $initial_form = 'register';
    }
} elseif (isset($_GET['form']) && $_GET['form'] === 'register') {
    $initial_form = 'register';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $cvsuid = trim($_POST['cvsuid']);
    $password = $_POST['password'];

    if (empty($cvsuid) || empty($password)) {
        $error_message = 'Please fill in both fields.';
    } else {
        
        $stmt = $conn->prepare("SELECT cvsuid, password, status, role FROM users WHERE cvsuid = ?");
        $stmt->bind_param("s", $cvsuid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) { 
                
                if ($user['status'] === 'Pending') {
                    $error_message = 'Your account is pending approval by the administration.';
                } elseif ($user['status'] === 'Reject') {
                    $error_message = 'Your account registration was rejected.';
                } else {
                    $_SESSION['cvsuid'] = $user['cvsuid'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: index.php'); 
                    exit();
                }
                
            } else {
                $error_message = 'Invalid CVSU ID or password.';
            }
        } else {
            $error_message = 'Invalid CVSU ID or password.';
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $cvsuid = trim($_POST['reg_cvsuid']);
    $email = trim($_POST['email']);
    $fullname = trim($_POST['fullname']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['confirm_password'];

    $role = 'user';
    $status = 'Pending';
    $initial_form = 'register';

    if (empty($cvsuid) || empty($email) || empty($fullname) || empty($password) || empty($confirm_password) || empty($_FILES['id_proof']['name'])) {
        $error_message = 'All fields including ID proof are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters.';
    } else {

        $file = $_FILES['id_proof'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($file_ext, $allowed)) {
            $error_message = 'Invalid file type.';
        } elseif ($file_error !== 0) {
            $error_message = 'Upload error. Try again.';
        } elseif ($file_size > 5000000) {
            $error_message = 'File must be less than 5MB.';
        } else {

            $new_filename = uniqid("proof_", true) . '.' . $file_ext;
            $destination = 'uploads/id_proofs/' . $new_filename;

            if (!move_uploaded_file($file_tmp, $destination)) {
                $error_message = 'Failed to save uploaded ID proof.';
            }
        }

        if (!$error_message) {

            $stmt_check = $conn->prepare("SELECT cvsuid FROM users WHERE cvsuid = ? OR email = ?");
            $stmt_check->bind_param("ss", $cvsuid, $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                if (file_exists($destination)) unlink($destination);
                $error_message = 'CVSU ID or Email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt_insert = $conn->prepare("
                    INSERT INTO users (cvsuid, fullname, email, id_image, password, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->bind_param("sssssss", $cvsuid, $fullname, $email, $destination, $hashed, $role, $status);

                if ($stmt_insert->execute()) {
                    $success_message = "Registration successful! Your account is now pending approval. Please log in once approved.";
                    $initial_form = 'login';
                } else {
                    if (file_exists($destination)) unlink($destination);
                    $error_message = "Database error: " . $conn->error;
                }
                $stmt_insert->close();
            }

            $stmt_check->close();
        }
    }
}

if ($conn && $conn->ping()) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account - Login & Register</title>
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

    <div class="search-bar">
        <form id="searchForm" action="search.php" method="GET">
            <input type="text" name="query" placeholder="Search items..." id="searchBar">
            <i class="fa-solid fa-magnifying-glass"></i>
        </form>
    </div>

    <ul class="nav-links">
        <?php if ($isLoggedIn): ?>
            
        <?php else: ?>
            <li><span class="login-link"><i class="fa-solid fa-right-to-bracket"></i> Login/Register</span></li>
        <?php endif; ?>
    </ul>
</nav>
<section class="account-page">
    
    <div class="back-home-container">
        <a href="index.php" class="btn-back-home">
            <i class="fa-solid fa-arrow-left"></i> Back to Homepage
        </a>
    </div>
    
    <div class="form-container">
        
        <?php 
        
        if ($error_message) {
            echo '<div class="alert alert-error">'. htmlspecialchars($error_message) .'</div>';
        } elseif ($success_message) {
            echo '<div class="alert alert-success">'. htmlspecialchars($success_message) .'</div>';
        }
        ?>

        
        <div id="loginForm" class="form-login <?= $initial_form === 'login' ? 'active' : '' ?>">
            <h2><i class="fa-solid fa-right-to-bracket"></i> Login</h2>
            <form action="account.php" method="POST">
                <div class="form-group">
                    <label for="cvsuid">CVSU ID</label>
                    <input type="text" id="cvsuid" name="cvsuid" required value="<?= isset($_POST['cvsuid']) && $initial_form === 'login' ? htmlspecialchars($_POST['cvsuid']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login_submit" class="btn-primary">Login</button>
                <p class="forgot-password-link">
                    <a href="#" onclick="switchForm('forgot_password'); return false;">Forgot Password?</a>
                </p>
            </form>
            <p class="switch-form-link">
                Don't have an account? <a href="#" onclick="switchForm('register'); return false;">Register Now</a>
            </p>
        </div>

        
        <div id="registerForm" class="form-register <?= $initial_form === 'register' ? 'active' : '' ?>">
            <h2><i class="fa-solid fa-user-plus"></i> Register</h2>
            <form action="account.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="reg_cvsuid">CVSU ID (e.g., 202210100)</label>
                    <input type="text" id="reg_cvsuid" name="reg_cvsuid" required value="<?= isset($_POST['reg_cvsuid']) && $initial_form === 'register' ? htmlspecialchars($_POST['reg_cvsuid']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= isset($_POST['email']) && $initial_form === 'register' ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" required value="<?= isset($_POST['fullname']) && $initial_form === 'register' ? htmlspecialchars($_POST['fullname']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="id_proof">Upload CVSU ID/Proof (Max 5MB, JPG, PNG, PDF)</label>
                    <input type="file" id="id_proof" name="id_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>

                <div class="form-group">
                    <label for="reg_password">Password (min 8 characters)</label>
                    <input type="password" id="reg_password" name="reg_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="register_submit" class="btn-primary">Submit for Approval</button>
            </form>
            <p class="switch-form-link">
                Already have an account? <a href="#" onclick="switchForm('login'); return false;">Login Here</a>
            </p>
        </div>

        
        <div id="forgotPasswordForm" class="form-forgot-password">
            <h2><i class="fa-solid fa-lock-open"></i> Reset Password</h2>
            <p>Enter your registered email address to receive a password reset link.</p>
            <form action="forgot_password_handler.php" method="POST">
                <div class="form-group">
                    <label for="forgot_email">Email Address</label>
                    <input type="email" id="forgot_email" name="forgot_email" required>
                </div>
                <button type="submit" name="forgot_submit" class="btn-primary">Send Reset Link</button>
            </form>
            <p class="switch-form-link">
                <a href="#" onclick="switchForm('login'); return false;">Back to Login</a>
            </p>
        </div>

    </div>
</section>


<script>
    function switchForm(target) {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');

        loginForm.classList.remove('active');
        registerForm.classList.remove('active');
        forgotPasswordForm.classList.remove('active');

        if (target === 'login') {
            loginForm.classList.add('active');
        } else if (target === 'register') {
            registerForm.classList.add('active');
        } else if (target === 'forgot_password') {
            forgotPasswordForm.classList.add('active');
        }
    }

    if ('<?= $initial_form ?>' === 'forgot_password') {
        window.onload = function() {
            switchForm('forgot_password');
        };
    }
</script>

<footer>
    <p>© 2025 Cavite State University | CvSU MarketPlace</p>
</footer>

</body>
</html>