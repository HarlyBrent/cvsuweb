<?php
session_start();

if (!isset($_SESSION['cvsuid']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: account.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['cvsuid'];
$order_id = $_POST['order_id'] ?? null;

if (!is_numeric($order_id)) {
    $_SESSION['error_message'] = "Invalid order ID.";
    header("Location: profile.php");
    exit();
}

$conn->begin_transaction();
try {
    
    $stmt_items = $conn->prepare("SELECT product_id, variant, quantity FROM order_items WHERE order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $items_to_restore = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    $stmt_cancel = $conn->prepare("
        UPDATE orders 
        SET status = 'Canceled' 
        WHERE order_id = ? AND user_id = ? AND status = 'Pending'
    ");
    $stmt_cancel->bind_param("is", $order_id, $user_id);
    $stmt_cancel->execute();
    
    if ($conn->affected_rows === 0) {
        throw new Exception("Order not found, not yours, or cannot be canceled (Status: 'Processed').");
    }
    $stmt_cancel->close();

    
    $stmt_variant_lookup = $conn->prepare("SELECT variant_id FROM product_variants WHERE size_variant = ? LIMIT 1");
    $stmt_restore_stock = $conn->prepare("UPDATE product_variants SET stock_variant = stock_variant + ? WHERE variant_id = ?");

    foreach ($items_to_restore as $item) {
        $variant_text = $item['variant'];
        $quantity = $item['quantity'];

        
        $stmt_variant_lookup->bind_param("s", $variant_text);
        $stmt_variant_lookup->execute();
        $variant_res = $stmt_variant_lookup->get_result();
        
        if ($variant_row = $variant_res->fetch_assoc()) {
            $variant_id = $variant_row['variant_id'];
            
            $stmt_restore_stock->bind_param("ii", $quantity, $variant_id);
            $stmt_restore_stock->execute();
        }
    }
    $stmt_variant_lookup->close();
    $stmt_restore_stock->close();
    
    $conn->commit();
    $_SESSION['success_message'] = "Order #{$order_id} has been successfully canceled and stock restored.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Cancellation failed: " . $e->getMessage();
}

$conn->close();
header("Location: profile.php");
exit();
?>