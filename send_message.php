<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cvsuid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$sender_id = $_SESSION['cvsuid'];
$receiver_id = $_POST['receiver_id'] ?? null;
$message_text = $_POST['message'] ?? null;

if (empty($receiver_id) || empty($message_text)) {
    echo json_encode(['success' => false, 'message' => 'Receiver ID or message is empty.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$sql = "
    INSERT INTO messages (sender_cvsuid, receiver_cvsuid, message, is_read)
    VALUES (?, ?, ?, 0)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $sender_id, $receiver_id, $message_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database insert error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>