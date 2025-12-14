<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    $_SESSION['error_message'] = "Database connection failed.";
    header("Location: cart.php");
    exit();
}

$cvsuid = $_SESSION['cvsuid'] ?? null;

if (!$cvsuid || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$variant_id = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT) ?? 0;

if (!$product_id) {
    $_SESSION['error_message'] = "Invalid product ID provided.";
    header("Location: cart.php");
    exit();
}

$sql = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
$params = "si";
$bind_params = [$cvsuid, $product_id];

if ($variant_id > 0) {
    $sql .= " AND variant_id = ?";
    $params .= "i";
    $bind_params[] = $variant_id;
} else {
    $sql .= " AND (variant_id IS NULL OR variant_id = 0)";
}

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($params, ...$bind_params);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Item successfully removed from your cart.";
    } else {
        $_SESSION['error_message'] = "Error removing item: " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Database error preparing statement.";
}

$conn->close();

header("Location: cart.php");
exit();
?>