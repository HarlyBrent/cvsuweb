<?php
session_start();
header('Content-Type: application/json');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$cvsuid = $_SESSION['cvsuid'] ?? null;
if (!$cvsuid) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$product_id = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT);
$variant_id = filter_var($data['variant_id'] ?? 0, FILTER_VALIDATE_INT);
$new_quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);

if (!$product_id || !$new_quantity || $new_quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided for update.']);
    exit();
}

$max_stock = 99999; 
if ($variant_id > 0) {
    $stmt_stock = $conn->prepare("SELECT stock_variant FROM product_variants WHERE variant_id = ?");
    $stmt_stock->bind_param("i", $variant_id);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $stock_row = $result_stock->fetch_assoc();
    $stmt_stock->close();

    if ($stock_row) {
        $max_stock = $stock_row['stock_variant'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Variant not found.']);
        exit();
    }
}

if ($new_quantity > $max_stock) {
    echo json_encode(['success' => false, 'message' => 'Quantity exceeds available stock of ' . $max_stock, 'max_stock' => $max_stock]);
    exit();
}

$sql = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
$params = "isi";
$bind_params = [$new_quantity, $cvsuid, $product_id];

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
        echo json_encode(['success' => true, 'new_quantity' => $new_quantity]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error during update: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement.']);
}

$conn->close();
?>