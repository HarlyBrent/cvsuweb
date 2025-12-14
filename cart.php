<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$isLoggedIn = isset($_SESSION['cvsuid']);
$cvsuid = $_SESSION['cvsuid'] ?? null;

if (!$isLoggedIn) {
    header("Location: account.php");
    exit();
}

$is_admin = false;
if ($isLoggedIn) {
    $stmt_user = $conn->prepare("SELECT role FROM users WHERE cvsuid=?");
    $stmt_user->bind_param("s", $cvsuid);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    $user = $res_user->fetch_assoc();
    $stmt_user->close();
    $is_admin = ($user['role'] === 'admin');
    
    if ($is_admin) {
       
    }
}

$cart_items = [];
$subtotal = 0;

$sql = "
    SELECT 
        ci.product_id,
        ci.variant_id,
        ci.quantity,
        p.name AS product_name,
        p.price AS base_price,
        p.image AS product_image,
        p.variant_type,
        pv.size_variant,
        pv.stock_variant
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    LEFT JOIN product_variants pv ON ci.variant_id = pv.variant_id
    WHERE ci.user_id = ?
    ORDER BY ci.created_at DESC
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $cvsuid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $item_price = $row['base_price'];
        $item_total = $item_price * $row['quantity'];
        $subtotal += $item_total;
        $cart_items[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart - CvSU MarketPlace</title>
<link rel="stylesheet" href="marketplace_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<nav class="navbar">
    <div class="logo">
        <h2><a href="index.php">CvSU MarketPlace</a></h2>
    </div>

    <div class="search-bar">
        <form id="searchForm" action="search.php" method="GET">
            <input type="text" name="query" placeholder="Search items..." id="searchBar">
            <i class="fa-solid fa-magnifying-glass"></i>
        </form>
    </div>

    <ul class="nav-links">
        <?php if ($isLoggedIn): ?>
            <li><a href="profile.php" title="Profile"><i class="fa-solid fa-user-circle"></i></a></li>

            <?php if (!$is_admin): ?>
            <li class="icon-badge-container">
                <button id="navbarCartButton" type="button" title="Shopping Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="cartCountBadge" style="display:none;">0</span>
                </button>
            </li>
            <?php endif; ?>

            <li><a href="sell-item.php" title="Sell an Item"><i class="fa-solid fa-plus-circle"></i></a></li>
            
           <li class="icon-badge-container">
    <a href="message.php" id="navbarMessagesLink" class="message-link" title="Messages">
        <i class="fa-solid fa-message"></i>
        <span id="unreadMessageBadge" style="display:none;"></span>
    </a>
</li>
        <?php else: ?>
            <li><a href="account.php" class="login-link"><i class="fa-solid fa-right-to-bracket"></i> Login/Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<?php if($isLoggedIn && !$is_admin): ?>
<div id="cartPanel" class="side-panel">
    <h3><i class="fa-solid fa-shopping-cart"></i> Your Cart</h3>
    <div id="cartItems" style="flex:1;">
        <p style="text-align:center;">Loading cart items...</p>
    </div>
    <button onclick="window.location='cart.php'" class="cart-view-full-btn">View Full Cart</button>
</div>
<section class="cart-container">
    <h2><i class="fa-solid fa-shopping-cart"></i> Your Shopping Cart</h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;"><?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <p style="color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;"><?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
    <?php endif; ?>

    <div id="cart-items-list">
        
        <?php if (!empty($cart_items)): ?>
            <?php foreach ($cart_items as $item): 
                $item_total = $item['base_price'] * $item['quantity'];
                
                $item_name = htmlspecialchars($item['product_name']);
                if ($item['variant_id'] && $item['size_variant']) {
                    $variant_type = htmlspecialchars($item['variant_type'] ?? 'Variant');
                    $item_name .= " ({$variant_type}: " . htmlspecialchars($item['size_variant']) . ")";
                }
            ?>
            <div class="cart-item" data-product-id="<?= $item['product_id'] ?>" data-variant-id="<?= $item['variant_id'] ?? 0 ?>" data-base-price="<?= $item['base_price'] ?>">
                <div class="cart-item-details">
                    <img src="<?= htmlspecialchars($item['product_image'] ?: 'https://via.placeholder.com/80x80') ?>" alt="<?= $item_name ?>">
                    <div>
                        <strong><?= $item_name ?></strong>
                        <p class="cart-item-price">₱<?= number_format($item['base_price'], 2) ?></p>
                        <?php if ($item['variant_id']): ?>
                            <p class="cart-item-stock">Stock: <?= htmlspecialchars($item['stock_variant']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cart-controls">
                    <input 
                        type="number" 
                        value="<?= htmlspecialchars($item['quantity']) ?>" 
                        min="1" 
                        max="<?= $item['stock_variant'] ?? 99 ?>" 
                        class="quantity-input"
                    >
                    
                    <span class="item-total-display" data-total-price="<?= $item_total ?>">₱<?= number_format($item_total, 2) ?></span>
                    
                    <form method="POST" action="remove_from_cart.php" style="margin: 0;">
                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                        <input type="hidden" name="variant_id" value="<?= $item['variant_id'] ?? 0 ?>">
                        <button type="submit" class="btn-remove" title="Remove Item">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        
    </div>

    <div class="cart-total">
        Subtotal: <span id="cart-subtotal">₱<?= number_format($subtotal, 2) ?></span>
    </div>
    <button class="checkout-btn" onclick="window.location.href='checkout.php'">Proceed to Checkout</button>

    <?php else: ?>
        <p class="cart-empty-message">Your cart is currently empty. Start browsing our products!</p>
    <?php endif;  ?>

</section>

<footer>
    <p>© 2025 Cavite State University | CvSU MarketPlace</p>
</footer>

<script>
    const currentUserId = '<?= htmlspecialchars($cvsuid ?? '') ?>';
    const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
    const is_admin = <?= json_encode($is_admin) ?>;
</script>
<script src="navbar.js"></script>
<script src="cart_interactions.js"></script>

<?php endif;  ?>

<?php $conn->close(); ?>
</body>
</html>