<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cvsuid']) || !isset($_GET['partner_id'])) {
    echo json_encode(['error' => 'Unauthorized or missing partner ID.']);
    exit();
}

$cvsuid = $_SESSION['cvsuid'];
$partner_id = $_GET['partner_id'];

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

$sql = "
    SELECT id, sender_cvsuid, receiver_cvsuid, message, created_at
    FROM messages
    WHERE (sender_cvsuid = ? AND receiver_cvsuid = ?)
       OR (sender_cvsuid = ? AND receiver_cvsuid = ?)
    ORDER BY created_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $cvsuid, $partner_id, $partner_id, $cvsuid);
$stmt->execute();
$result = $stmt->get_result();

$messages = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

echo json_encode($messages);
?>