<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    $_SESSION['error_message'] = "Database connection failed.";
    header('Location: account.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_email'])) {
    $email = trim($_POST['forgot_email']);
    $success_message = "If your email is registered, a password reset link has been sent.";

    $stmt = $conn->prepare("SELECT cvsuid, fullname FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $cvsuid = $user['cvsuid'];
        $fullname = $user['fullname'];

        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600); 

        $conn->query("DELETE FROM password_resets WHERE cvsuid = '$cvsuid'");

        $stmt_insert = $conn->prepare("INSERT INTO password_resets (cvsuid, token, expires) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $cvsuid, $token, $expires);
        
        if ($stmt_insert->execute()) {
            
            $reset_link = "http://localhost/Marketing/reset_password_form.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); 
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'martplace67@gmail.com'; 
                $mail->Password   = 'rjgh mdxz erid cowq'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465; 

                $mail->setFrom('no-reply@cvsu-marketplace.com', 'CvSU Marketplace');
                $mail->addAddress($email, $fullname); 

                $mail->isHTML(true); 
                $mail->Subject = "CvSU Marketplace Password Reset";
                $mail->Body    = "
                    <html>
                    <body>
                    <p>Hello <b>{$fullname}</b>,</p>
                    <p>We received a request to reset the password for your CvSU Marketplace account.</p>
                    <p>Click the link below to set a new password. This link will expire in 1 hour:</p>
                    <p><a href='{$reset_link}'>Reset My Password</a></p>
                    <p>If you did not request a password reset, you can safely ignore this email.</p>
                    <p>Thank you,<br>CvSU Marketplace Team</p>
                    </body>
                    </html>
                ";
                $mail->AltBody = "Hello {$fullname},\n\nClick this link to reset your password (expires in 1 hour): {$reset_link}\n\nIf you did not request this, ignore this email.\n\nThank you,\nCvSU Marketplace Team";

                $mail->send();
                $_SESSION['success_message'] = $success_message;
            } catch (Exception $e) {
                $conn->query("DELETE FROM password_resets WHERE token = '$token'");
                $_SESSION['error_message'] = "Email could not be sent. Please try again later.";
            }
        } else {
            $_SESSION['error_message'] = "An unexpected error occurred during token generation.";
        }
        $stmt_insert->close();
    } else {
        $_SESSION['success_message'] = $success_message;
    }

    $stmt->close();
}

$conn->close();

header('Location: account.php');
exit();
?>