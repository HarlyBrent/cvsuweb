<?php
session_start();

if (!isset($_SESSION['cvsuid'])) {
    header("Location: account.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['cvsuid'];
$error = '';
$cart_items = [];

$stmt = $conn->prepare("
    SELECT 
        c.id AS cart_id, 
        c.quantity, 
        p.product_id, 
        p.name, 
        p.price, 
        p.variant_type,
        c.variant_id, 
        pv.size_variant, 
        p.image
    FROM cart_items c
    JOIN products p ON c.product_id = p.product_id
    LEFT JOIN product_variants pv ON c.variant_id = pv.variant_id
    WHERE c.user_id=?
");

$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$cart_items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($cart_items)) {
    $meetup_location = trim($_POST['meetup_location'] ?? '');
    $meetup_time = $_POST['meetup_time'] ?? '';

    if (empty($meetup_location) || empty($meetup_time)) {
        $error = "Please fill in the meetup location and time.";
    } else {
        $total_amount = array_sum(array_map(function($item){ return $item['price'] * $item['quantity']; }, $cart_items));
        $payment_method = "Cash";

        $conn->begin_transaction();
        try {
            $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, meetup_location, meetup_time, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_order->bind_param("sdsss", $user_id, $total_amount, $payment_method, $meetup_location, $meetup_time);
            $stmt_order->execute();
            $order_id = $stmt_order->insert_id;
            $stmt_order->close();

            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, variant, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt_update_stock = $conn->prepare("UPDATE product_variants SET stock_variant = stock_variant - ? WHERE variant_id = ?");

            foreach ($cart_items as $item) {
                $variant_text = $item['size_variant'] ?? 'N/A'; 
                
                $stmt_item->bind_param("iisid", $order_id, $item['product_id'], $variant_text, $item['quantity'], $item['price']);
                $stmt_item->execute();
                
                if ($item['variant_id'] > 0 && $stmt_update_stock) {
                    $stmt_update_stock->bind_param("ii", $item['quantity'], $item['variant_id']);
                    $stmt_update_stock->execute();
                }
            }
            $stmt_item->close();
            if ($stmt_update_stock) $stmt_update_stock->close();

            $stmt_clear = $conn->prepare("DELETE FROM cart_items WHERE user_id=?");
            $stmt_clear->bind_param("s", $user_id);
            $stmt_clear->execute();
            $stmt_clear->close();

            $conn->commit();
            
            $_SESSION['checkout_success'] = "✅ Your order has been placed! Awaiting meetup with the seller.";
            header("Location: checkout.php"); 
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "An error occurred during checkout. Please try again. (" . $e->getMessage() . ")";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Checkout</title>
<link rel="stylesheet" href="marketplace_style.css">
<link rel="stylesheet" href="checkout_style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<a href="cart.php" class="side-back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Cart</a>

<div class="container">
<h2>Checkout - Cash Payment</h2>

<?php if(isset($_SESSION['checkout_success'])): ?>
<p class="success"><?= htmlspecialchars($_SESSION['checkout_success']); ?></p>
<a href="profile.php" class="confirm-btn" style="text-align:center; display:block;">View Orders in Profile</a>
<?php unset($_SESSION['checkout_success']); ?>

<?php elseif(empty($cart_items)): ?>
<p class="empty-cart">Your cart is empty. Nothing to checkout.</p>
<a href="home.php" class="confirm-btn" style="text-align:center; display:block;">Back to Home</a>

<?php else: ?>
<?php if(!empty($error)) echo "<p class='error'>". htmlspecialchars($error) ."</p>"; ?>

<div class="checkout-items-list">
<?php 
$total = 0;
foreach($cart_items as $item): 
$item_subtotal = $item['price'] * $item['quantity'];
$total += $item_subtotal;
?>
<div class="cart-item">
    <img src="<?= htmlspecialchars($item['image'] ?: 'default-product.png') ?>" alt="Product Image">
    <div class="cart-item-details">
        <strong><?= htmlspecialchars($item['name']) ?></strong>
        <p>Variant: <?= htmlspecialchars($item['size_variant'] ?? 'N/A') ?></p>
        <p>Qty: <?= $item['quantity'] ?> @ ₱<?= number_format($item['price'], 2) ?></p>
        <p>Subtotal: ₱<?= number_format($item_subtotal, 2) ?></p>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="total">Total: ₱<?= number_format($total, 2) ?></div>

<form method="POST">
    <div class="form-group">
        <label for="meetup_location"><i class="fa-solid fa-location-dot"></i> Meetup Location (e.g., Main Gate, CvSU Campus)</label>
        <input type="text" name="meetup_location" id="meetup_location" placeholder="Enter location..." required value="<?= htmlspecialchars($_POST['meetup_location'] ?? '') ?>">
    </div>
    
    <div class="form-group">
        <label for="meetup_time"><i class="fa-solid fa-clock"></i> Preferred Meetup Date and Time</label>
        <input type="datetime-local" name="meetup_time" id="meetup_time" required>
    </div>

    <button type="submit" class="confirm-btn">Confirm Cash Payment & Place Order</button>
</form>

<?php endif; ?>
</div>

</body>
</html>