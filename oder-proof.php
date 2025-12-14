<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cvsuid']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$cvsuid = $_SESSION['cvsuid'];
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$proof_file = $_FILES['proof_image'] ?? null;

if (!$order_id || !$proof_file || $proof_file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or file upload failed.']);
    $conn->close();
    exit();
}

// --- File Handling Logic ---
$target_dir = "uploads/proofs/";
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

$file_ext = strtolower(pathinfo($proof_file['name'], PATHINFO_EXTENSION));
$allowed_ext = ['jpg', 'jpeg', 'png'];

if (!in_array($file_ext, $allowed_ext)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG files are allowed.']);
    $conn->close();
    exit();
}

$filename = "proof_" . $order_id . "_" . time() . "." . $file_ext;
$target_file = $target_dir . $filename;

if (!move_uploaded_file($proof_file['tmp_name'], $target_file)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    $conn->close();
    exit();
}
// --- End File Handling ---

$conn->begin_transaction();
try {
    // 1. Update order status and attach proof image path
    $stmt_update = $conn->prepare("
        UPDATE orders 
        SET status = 'Completed', proof_image = ? 
        WHERE order_id = ? AND user_id = ? AND status = 'In Meetup'
    ");
    $stmt_update->bind_param("sis", $target_file, $order_id, $cvsuid);
    $stmt_update->execute();

    if ($conn->affected_rows === 0) {
        throw new Exception("Order status not updated. Check if the order status is 'In Meetup'.");
    }
    $stmt_update->close();
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order completed! Proof uploaded successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    // Delete the file if the database transaction fails
    if (file_exists($target_file)) unlink($target_file); 
    echo json_encode(['success' => false, 'message' => 'Failed to complete order: ' . $e->getMessage()]);
}

$conn->close();
?>