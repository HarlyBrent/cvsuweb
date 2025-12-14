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

if (!isset($_GET['cvsuid']) || !isset($_GET['reason'])) {
    header('Location: profile.php?section=account-approval&error=Invalid+rejection+request.');
    exit();
}

$cvsuid = $_GET['cvsuid'];
$reason = urldecode($_GET['reason']);

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    header('Location: profile.php?section=account-approval&error=Database+Connection+Failed.');
    exit();
}

$stmt = $conn->prepare("SELECT fullname, email, id_image FROM users WHERE cvsuid = ?");
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
$id_proof_path = $user['id_image'];

if (!empty($id_proof_path) && file_exists($id_proof_path)) {
    unlink($id_proof_path);
}

$stmt2 = $conn->prepare("DELETE FROM users WHERE cvsuid = ?");
$stmt2->bind_param("s", $cvsuid);
$delete_success = $stmt2->execute();
$stmt2->close();
$conn->close();

if (!$delete_success) {
    header('Location: profile.php?section=account-approval&error=Failed+to+delete+user+from+database.');
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
    $mail->Subject = "CvSU Marketplace Account Rejected";
    $mail->Body    = "
        <html>
        <body>
        <p>Hello <b>{$fullname}</b>,</p>
        <p>We regret to inform you that your CvSU Marketplace account registration was <b>REJECTED</b> and your registration data has been removed.</p>
        <p><b>Reason for Rejection:</b><br>{$reason}</p>
        <p>You may reapply after correcting the issue.</p>
        <p>Thank you,<br>CvSU Marketplace Team</p>
        </body>
        </html>
    ";
    $mail->AltBody = "Hello {$fullname},\n\nWe regret to inform you that your CvSU Marketplace account was REJECTED and your registration data has been removed.\n\nReason:\n{$reason}\n\nYou may reapply after correcting the issue.\n\nThank you,\nCvSU Marketplace Team";

    $mail->send();

} catch (Exception $e) {
    $mail_status = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

$final_message = "User {$cvsuid} rejected and DELETED. Status: {$mail_status}";

header("Location: profile.php?section=account-approval&custom_alert=" . urlencode($final_message));
exit();