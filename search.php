<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cvsu_marketplace'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name); 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$results = [];

$isLoggedIn = isset($_SESSION['cvsuid']); 
$cvsuid = $isLoggedIn ? $_SESSION['cvsuid'] : '';

if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    
    $stmt = $conn->prepare("
        SELECT product_id, name, price, description, image, `condition`, posted_by, seller_name, variant_type, category 
        FROM products 
        WHERE name LIKE ? OR description LIKE ?
    ");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?= htmlspecialchars($search_query) ?>"</title>
    <link rel="stylesheet" href="marketplace_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <input type="text" name="query" placeholder="Search items..." id="searchBar" value="<?= htmlspecialchars($search_query) ?>">
            <i class="fa-solid fa-magnifying-glass"></i>
        </form>
    </div>

    <ul class="nav-links">
        <?php if ($isLoggedIn): ?>
            <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Logout</a></li>
            <li><a href="dashboard.php"><i class="fa-solid fa-user-circle"></i> Profile</a></li>
        <?php else: ?>
            <li><a href="account.php" class="login-link"><i class="fa-solid fa-right-to-bracket"></i> Login/Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<section class="search-results-page">
    <div class="container">
        <h1>Search Results for "<?= htmlspecialchars($search_query) ?>"</h1>

        <?php if (!empty($search_query) && count($results) > 0): ?>
            <p><?= count($results) ?> items found.</p>
            <div class="product-grid">
                <?php foreach ($results as $product): ?>
                    <div class="product-card">
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="price">₱<?= number_format($product['price'], 2) ?></p>
                        <a href="product_details.php?id=<?= htmlspecialchars($product['product_id']) ?>" class="btn-primary">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($search_query)): ?>
            <div class="alert alert-info">
                No products found matching "<?= htmlspecialchars($search_query) ?>".
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Please enter a query in the search bar to find products.
            </div>
        <?php endif; ?>
    </div>
</section>

<footer>
    <p>© 2025 Cavite State University | CvSU MarketPlace</p>
</footer>

</body>
</html>