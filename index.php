<?php
if (session_status() == PHP_SESSION_NONE) 
    session_start();

$isLoggedIn = isset($_SESSION['cvsuid']);
$cvsuid = $isLoggedIn ? $_SESSION['cvsuid'] : '';  
$is_admin = false;

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace'); 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($isLoggedIn) { 
    $stmt = $conn->prepare("SELECT role FROM users WHERE cvsuid = ?");
    $stmt->bind_param("s", $cvsuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $res = $result->fetch_assoc();
    $stmt->close();

    $is_admin = ($res && $res['role'] === 'admin');
}

$condition = null;
$condition_filter = '';
$allowed_conditions = ['Brandnew', 'Pre-Loved'];

if (isset($_GET['condition']) && in_array($_GET['condition'], $allowed_conditions)) {
    $condition = $_GET['condition'];
    $condition_filter = "WHERE `condition` = ?"; 
}

$sql = "SELECT
            product_id,
            name,
            price,
            image,
            `condition`, 
            seller_name,
            variant_type
            FROM products
            $condition_filter
            ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($condition) {
    $stmt->bind_param("s", $condition);
}  
$stmt->execute();
$result = $stmt->get_result();

$products = [];
if ($result->num_rows > 0) {
    $products = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CvSU MarketPlace - Home</title>

<link rel="stylesheet" href="marketplace_style.css"> 

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" type="image/x-icon" href="favicon.ico">

</head>
<body>

<nav class="navbar">
    <div class="logo">
        <h2>
            <a href="index.php">CvSU MarketPlace</a>
        </h2>
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
              <a href="cart.php" id="navbarCartButton" class="cart-link" title="Shopping Cart">
    <i class="fa-solid fa-cart-shopping"></i>
    <span id="cartCountBadge" style="display:none;">0</span>
</a>
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
<?php endif; ?>
<section class="hero">
    <div class="hero-text">
        <h1>Welcome to CvSU MarketPlace!</h1>
        <p>Find <?= $condition ? htmlspecialchars($condition) : 'Pre-loved and Brand New' ?> items from the community.</p>
        
        <div style="margin-top: 20px;">
            <a href="index.php" class="btn-filter default <?= $condition === null ? 'active' : '' ?>">All Items</a>
            <a href="index.php?condition=Pre-Loved" class="btn-filter default <?= $condition === 'Pre-Loved' ? 'active' : '' ?>">Pre-Loved</a>
            <a href="index.php?condition=Brandnew" class="btn-filter default <?= $condition === 'Brandnew' ? 'active' : '' ?>">Brand New</a>
        </div>
    </div>
</section>

<section class="products">
    <h2>Available Products</h2>
    <div class="product-grid">

        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="window.location='product-details.php?id=<?= $product['product_id'] ?>'">
                    <img 
                        src="<?= htmlspecialchars($product['image']) ?: 'https://via.placeholder.com/240x160?text=No+Image' ?>" 
                        alt="<?= htmlspecialchars($product['name']) ?>"
                    >
                    <div class="product-info">
                        <div>
                            <h3><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="price">₱<?= number_format($product['price'], 2) ?></p>
                        </div>
                        <div class="product-meta">
                            <span><?= htmlspecialchars($product['condition']) ?> | <?= htmlspecialchars($product['variant_type']) ?></span>
                            <span>Seller: <?= htmlspecialchars($product['seller_name']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; margin-top: 20px;">
                No products available yet<?php echo $condition ? " for the condition: **" . htmlspecialchars($condition) . "**" : ""; ?>.
            </p>
        <?php endif; ?>

    </div>
</section>

<footer>
    <p>© 2025 Cavite State University | CvSU MarketPlace</p>
</footer>

<script>
    const currentUserId = '<?= htmlspecialchars($cvsuid) ?>';
    const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
    const is_admin = <?= json_encode($is_admin) ?>;
</script>
<script src="navbar.js"></script>

<?php
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

</body>
</html>