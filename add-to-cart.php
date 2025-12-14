<?php
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['cvsuid'])) {
    echo json_encode(["success" => false, "message" => "You must be logged in to add items to your cart."]);
    exit();
}

$cvsuid = $_SESSION['cvsuid'];


if (!isset($_POST['product_id']) || !isset($_POST['variant_id'])) {
    echo json_encode(["success" => false, "message" => "Missing product or variant information."]);
    exit();
}

$product_id = intval($_POST['product_id']);
$variant_id = intval($_POST['variant_id']);
$quantity = 1; 

$conn = new mysqli("localhost", "root", "", "cvsu_marketplace");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed. Please try again later."]);
    exit();
}

if ($variant_id > 0) {
    $stmt = $conn->prepare("SELECT stock_variant FROM product_variants WHERE variant_id=? AND product_id=?");
    $stmt->bind_param("ii", $variant_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $variant = $result->fetch_assoc();
    $stmt->close();

    if (!$variant) {
        echo json_encode(["success" => false, "message" => "Invalid product or variant selection."]);
        exit();
    }

    if ($variant['stock_variant'] <= 0) {
        echo json_encode(["success" => false, "message" => "This variant is currently out of stock."]);
        exit();
    }
}

$stmt = $conn->prepare("
    SELECT id, quantity 
    FROM cart_items 
    WHERE user_id=? AND product_id=? AND variant_id=?
");
$stmt->bind_param("sii", $cvsuid, $product_id, $variant_id);
$stmt->execute();
$cartResult = $stmt->get_result();
$existing = $cartResult->fetch_assoc();
$stmt->close();


if ($existing) {
    $newQty = $existing['quantity'] + $quantity; 


    if ($variant_id > 0 && $newQty > $variant['stock_variant']) {
        echo json_encode(["success" => false, "message" => "Cannot add, exceeds available stock of {$variant['stock_variant']}."]);
        $conn->close();
        exit();
    }
    
   
    $stmt = $conn->prepare("UPDATE cart_items SET quantity=? WHERE id=?");
    $stmt->bind_param("ii", $newQty, $existing['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Quantity updated to {$newQty} in cart."]);
} else {
    
    $stmt = $conn->prepare("
        INSERT INTO cart_items (user_id, product_id, variant_id, quantity)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("siii", $cvsuid, $product_id, $variant_id, $quantity);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Item added to cart successfully!"]);
}

$conn->close();
?>