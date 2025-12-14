<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

session_start();

if (!isset($_SESSION['cvsuid']) || $_SESSION['role'] !== 'admin') {
    header('Location: account.php');
    exit();
}

if (!isset($_GET['cvsuid'])) {
    header('Location: profile.php?section=account-approval&error=Invalid+request.');
    exit();
}

$cvsuid = $_GET['cvsuid'];

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    header('Location: profile.php?section=account-approval&error=Database+Connection+Failed.');
    exit();
}

$stmt = $conn->prepare("SELECT fullname, email FROM users WHERE cvsuid = ?");
$stmt->bind_param("s", $cvsuid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    header('Location: profile.php?section=account-approval&error=User+record+not+found.');
    exit();
}

$email = $user['email'];
$fullname = $user['fullname'];

$new_status = 'approved';
$stmt2 = $conn->prepare("UPDATE users SET status = ? WHERE cvsuid = ? AND status = 'pending'");
$stmt2->bind_param("ss", $new_status, $cvsuid);
$update_success = $stmt2->execute();
$stmt2->close();
$conn->close();

if (!$update_success) {
    header('Location: profile.php?section=account-approval&error=Failed+to+approve+user+in+database.');
    exit();
}

$mail_status = 'Email successfully sent.';
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
    $mail->Subject = "Your CvSU Marketplace Account Has Been Approved";
    $mail->Body    = "
        <html>
        <body>
        <p>Hello <b>{$fullname}</b>,</p>
        <p>Your CvSU Marketplace account has been successfully <b>APPROVED</b>. </p>
        <p>You may now log in and start using the platform.</p>
        <p>Thank you,<br>CvSU Marketplace Team</p>
        </body>
        </html>
    ";
    $mail->AltBody = "Hello {$fullname},\n\nYour CvSU Marketplace account has been successfully APPROVED. \nYou may now log in and start using the platform.\n\nThank you,\nCvSU Marketplace Team";

    $mail->send();

} catch (Exception $e) {
    $mail_status = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

$final_message = "User {$cvsuid} approved. Status: {$mail_status}";

header("Location: profile.php?section=account-approval&custom_alert=" . urlencode($final_message));
exit();