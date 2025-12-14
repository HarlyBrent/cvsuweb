<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cvsuid']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$cvsuid = $_SESSION['cvsuid'];
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

if (!$order_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
    $conn->close();
    exit();
}

$allowed_statuses = ['Ready For Meetup', 'In Meetup', 'Cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status update attempt.']);
    $conn->close();
    exit();
}

$conn->begin_transaction();
try {
    $message = "Order status updated to '{$new_status}'.";

    if ($new_status === 'Cancelled') {
        $stmt_items = $conn->prepare("
            SELECT oi.quantity, oi.variant, p.product_id 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items_to_restore = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();

        $stmt_update = $conn->prepare("
            UPDATE orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            SET o.status = ?
            WHERE o.order_id = ? 
            AND (o.user_id = ? OR p.posted_by = ?)
            AND o.status IN ('Pending', 'Ready For Meetup', 'In Meetup')
            LIMIT 1
        ");
        $stmt_update->bind_param("siss", $new_status, $order_id, $cvsuid, $cvsuid);
        $stmt_update->execute();

        if ($conn->affected_rows > 0) {
            // --- 3. Restore stock ---
            $stmt_update_stock = $conn->prepare("UPDATE product_variants pv JOIN order_items oi ON pv.size_variant = oi.variant SET pv.stock_variant = pv.stock_variant + oi.quantity WHERE oi.order_id = ? AND oi.variant <> 'N/A'");
            $stmt_update_stock->bind_param("i", $order_id);
            $stmt_update_stock->execute();
            $stmt_update_stock->close();
            
            $message = "Order #{$order_id} has been **CANCELLED** and product stock has been restored.";
        } else {
            throw new Exception("Order not found, unauthorized, or status cannot be changed to Cancelled.");
        }

    } else {
        
        $stmt_is_seller = $conn->prepare("
            SELECT 1 FROM order_items oi JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = ? AND p.posted_by = ? LIMIT 1
        ");
        $stmt_is_seller->bind_param("is", $order_id, $cvsuid);
        $stmt_is_seller->execute();
        $is_seller = $stmt_is_seller->get_result()->num_rows > 0;
        $stmt_is_seller->close();

        if (!$is_seller) {
            throw new Exception("You are not authorized to update this order's status.");
        }
        
        $stmt_update = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND status <> 'Completed' AND status <> 'Cancelled'");
        $stmt_update->bind_param("si", $new_status, $order_id);
        $stmt_update->execute();

        if ($conn->affected_rows === 0) {
            throw new Exception("Status not updated. Order is already completed or cancelled.");
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
}

$conn->close();
?>