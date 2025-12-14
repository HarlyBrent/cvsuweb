<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name); 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['cvsuid'])) {
    header('Location: account.php');
    exit();
}

$cvsuid = $_SESSION['cvsuid'];
$alert_message = '';

$stmt_fetch = $conn->prepare("SELECT fullname, email, profile_image, password FROM users WHERE cvsuid = ?");
$stmt_fetch->bind_param("s", $cvsuid);
$stmt_fetch->execute();
$user_data = $stmt_fetch->get_result()->fetch_assoc();
$stmt_fetch->close();
$current_profile_image = $user_data['profile_image'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile_submit'])) {
        $new_fullname = trim($_POST['fullname']);
        $new_email = trim($_POST['email']);
        $uploaded_file = $_FILES['profile_image'];
        $update_parts = [];
        $bind_types = '';
        $bind_values = [];

        if (empty($new_fullname) || empty($new_email)) {
            $alert_message = "❌ Full Name and Email are required.";
        }

        if (!$alert_message) {
            
            $stmt_check_email = $conn->prepare("SELECT cvsuid FROM users WHERE email = ? AND cvsuid != ?");
            $stmt_check_email->bind_param("ss", $new_email, $cvsuid);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();

            if ($stmt_check_email->num_rows > 0) {
                $alert_message = "❌ This email is already registered to another account.";
            }
            $stmt_check_email->close();
        }

        if (!$alert_message) {
            
            if ($uploaded_file['error'] === 0) {
                $file_tmp = $uploaded_file['tmp_name'];
                $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png'];

                if (!in_array($file_ext, $allowed)) {
                    $alert_message = "❌ Invalid profile picture file type. Must be JPG or PNG.";
                } else {
                    $new_filename = 'profile_' . $cvsuid . '_' . uniqid() . '.' . $file_ext;
                    $destination = 'uploads/profile_pictures/' . $new_filename;

                    if (!is_dir('uploads/profile_pictures')) {
                        mkdir('uploads/profile_pictures', 0777, true);
                    }
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        $update_parts[] = 'profile_image = ?';
                        $bind_types .= 's';
                        $bind_values[] = $destination;
                        
                        if (!empty($current_profile_image) && file_exists($current_profile_image)) {
                            unlink($current_profile_image);
                        }
                    } else {
                        $alert_message = "❌ Failed to save new profile picture.";
                    }
                }
            }

            if (!$alert_message) {
                if ($new_fullname !== $user_data['fullname']) {
                    $update_parts[] = 'fullname = ?';
                    $bind_types .= 's';
                    $bind_values[] = $new_fullname;
                }
                
                if ($new_email !== $user_data['email']) {
                    $update_parts[] = 'email = ?';
                    $bind_types .= 's';
                    $bind_values[] = $new_email;
                }

                if (!empty($update_parts)) {
                    $sql = "UPDATE users SET " . implode(', ', $update_parts) . " WHERE cvsuid = ?";
                    $bind_values[] = $cvsuid;
                    $bind_types .= 's';

                    $stmt_update = $conn->prepare($sql);
                    $stmt_update->bind_param($bind_types, ...$bind_values);

                    if ($stmt_update->execute()) {
                        $alert_message = "✅ Profile updated successfully!";
                    } else {
                        $alert_message = "❌ Database error: " . $conn->error;
                    }
                    $stmt_update->close();
                } else {
                    $alert_message = "ℹ️ No changes detected.";
                }
            }
        }
    }

    // --- 2. Handle Password Update ---
    if (isset($_POST['update_password_submit'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        
        if (empty($old_password) || empty($new_password) || empty($confirm_new_password)) {
            $alert_message = "❌ All password fields are required.";
        } elseif ($new_password !== $confirm_new_password) {
            $alert_message = "❌ New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $alert_message = "❌ New password must be at least 8 characters.";
        } else {
            
            if (password_verify($old_password, $user_data['password'])) {
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE cvsuid = ?");
                $stmt_update_pass->bind_param("ss", $new_hashed_password, $cvsuid);

                if ($stmt_update_pass->execute()) {
                    $alert_message = "✅ Password updated successfully!";
                } else {
                    $alert_message = "❌ Database error: " . $conn->error;
                }
                $stmt_update_pass->close();
            } else {
                $alert_message = "❌ Incorrect old password.";
            }
        }
    }
}

$conn->close();

header("Location: profile.php?custom_alert=" . urlencode($alert_message));
exit();