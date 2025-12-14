<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid product ID.");
}

if (!isset($_SESSION['cvsuid'])) {
    header("Location: account.php");
    exit();
}

$product_id = intval($_GET['id']);
$cvsuid = $_SESSION['cvsuid'];

$stmt = $conn->prepare("SELECT product_id, name, price, description, `condition`, category, variant_type, image, image2, image3, posted_by, seller_name FROM products WHERE product_id=?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) die("Product not found.");

$is_seller = ($cvsuid === $product['posted_by']);

$stmt_user = $conn->prepare("SELECT fullname, profile_image FROM users WHERE cvsuid=?");
$stmt_user->bind_param("s", $product['posted_by']);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$seller = $res_user->fetch_assoc();
$stmt_user->close();

$seller_id = $product['posted_by'];
$seller_name = htmlspecialchars($seller['fullname']); 

$stmt_loggedin = $conn->prepare("SELECT fullname, role FROM users WHERE cvsuid=?");
$stmt_loggedin->bind_param("s", $cvsuid);
$stmt_loggedin->execute();
$res_loggedin = $stmt_loggedin->get_result();
$loggedin_user = $res_loggedin->fetch_assoc();
$stmt_loggedin->close();

$is_admin = ($loggedin_user['role'] === 'admin');

$existing_variants = [];
$stmt_v = $conn->prepare("SELECT variant_id, size_variant, stock_variant FROM product_variants WHERE product_id=? ORDER BY variant_id ASC");
$stmt_v->bind_param("i", $product_id);
$stmt_v->execute();
$v_result = $stmt_v->get_result();
while ($row = $v_result->fetch_assoc()) $existing_variants[] = $row;
$stmt_v->close();

$product_images = array_filter([$product['image'],$product['image2'],$product['image3']]);
?>

<!DOCTYPE html>
<html>
<head>
<title>Product - <?= htmlspecialchars($product['name']) ?></title>
<link rel="stylesheet" href="marketplace_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<button class="side-back-btn" onclick="history.back()"><i class="fa-solid fa-arrow-left"></i> Back</button>

<div class="product-container">
    <h2 class="product-title"><?= htmlspecialchars($product['name']) ?></h2>

    <div class="product-grid">
        <div class="product-gallery">
            <?php if(count($product_images)): ?>
            <img id="mainImage" src="<?= $product_images[0] ?>" class="main-image">
            <div class="thumbnails">
            <?php foreach($product_images as $i => $img): ?>
            <img src="<?= $img ?>" class="<?= $i===0 ? 'active' : '' ?>" onclick="changeMainImage(this)">
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <div class="seller-info">
                <img src="<?= $seller['profile_image'] ?: 'default-profile.png' ?>" class="seller-profile-img">
                <div>
                    <strong class="seller-name-label"><?= htmlspecialchars($seller['fullname']) ?></strong><br>
                    <span class="seller-username"><?= htmlspecialchars($product['seller_name']) ?></span>
                </div>
            </div>

            <h3 class="product-price">Price: ₱<?= number_format($product['price'], 2) ?></h3>

            <div class="product-specs">
                <p><strong>Description:</strong> <?= htmlspecialchars($product['description']) ?></p>
                <p><strong>Condition:</strong> <span class="spec-tag"><?= htmlspecialchars($product['condition']) ?></span></p> 
                <p><strong>Category:</strong> <span class="spec-tag"><?= htmlspecialchars($product['category']) ?></span></p>
                <?php if ($product['variant_type']): ?>
                    <p><strong>Variant Type:</strong> <?= htmlspecialchars($product['variant_type']) ?></p>
                <?php endif; ?>
            </div>

            <?php if(!$is_admin && count($existing_variants)): ?>
                <form id="cartForm" class="purchase-form">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    <input type="hidden" name="variant_id" id="selectedVariantId" value="0">

                    <h4>Select <?= htmlspecialchars($product['variant_type']) ?: 'Variant' ?>:</h4>
                    <div class="variant-buttons">
                    <?php foreach($existing_variants as $v): ?>
                        <button type="button" class="variant-btn" data-variant-id="<?= $v['variant_id'] ?>" <?= $v['stock_variant']==0?'disabled':'' ?>>
                            <?= htmlspecialchars($v['size_variant']) ?> (<?= $v['stock_variant'] ?>)
                        </button>
                    <?php endforeach; ?>
                    </div>

                    <div class="buttons-wrapper">
                        <button type="button" class="action-btn add-cart-btn" id="addToCartBtn" disabled>
                            <i class="fa-solid fa-cart-plus"></i> Add to Cart
                        </button>
                        <button type="button" class="action-btn buy-now-btn" id="buyNowBtn" disabled>
                            <i class="fa-solid fa-bolt"></i> Buy Now
                        </button>
                    </div>

                    <button type="button" class="contact-seller-btn" onclick="loadConversation('<?= $seller_id ?>', '<?= $seller_name ?>')">
                        <i class="fa-solid fa-comment-dots"></i> Contact Seller
                    </button>
                </form>
            <?php elseif($is_admin): ?>
                <p class="admin-notice">Admins cannot purchase products.</p>
            <?php endif; ?>

            <?php if ($is_seller): ?>
                <div class="seller-actions">
                    <button type="button" class="action-btn edit-btn" onclick="showEditModal()">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Product
                    </button>
                    <button type="button" class="action-btn delete-btn" onclick="deleteProduct(<?= $product['product_id'] ?>)">
                        <i class="fa-solid fa-trash"></i> Delete Product
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changeMainImage(img){
    document.getElementById("mainImage").src = img.src;
    document.querySelectorAll(".thumbnails img").forEach(x=>x.classList.remove("active"));
    img.classList.add("active");
}

function selectVariant(btn, id){
    document.querySelectorAll(".variant-buttons .variant-btn").forEach(b=>b.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById("selectedVariantId").value = id;
    document.getElementById("addToCartBtn").disabled = false;
    document.getElementById("buyNowBtn").disabled = false;
}

document.querySelectorAll(".variant-buttons .variant-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        const variantId = this.getAttribute('data-variant-id');
        selectVariant(this, variantId);
    });
});

function handleCartAction(redirectUrl) {
    if (document.getElementById("selectedVariantId").value == "0") {
        alert("Please select a product variant first.");
        return;
    }
    
    let formData = new FormData(document.getElementById("cartForm"));
    
    fetch("add-to-cart.php", {method:"POST", body:formData})
    .then(res => res.json())
    .then(data => {
        if(data.success){
            alert(data.message);
            window.location.href = redirectUrl;
        } else {
            alert("Error adding to cart: " + data.message);
        }
    })
    .catch(err => alert("Error communicating with server."));
}

document.getElementById("addToCartBtn")?.addEventListener("click", function(){
    handleCartAction("cart.php");
});

document.getElementById("buyNowBtn")?.addEventListener("click", function(){
    handleCartAction("checkout.php"); 
});

function showEditModal() {
    window.location.href = "edit-product.php?id=<?= $product_id ?>";
}

function deleteProduct(productId) {
    if (confirm("Are you sure you want to delete this product? This action cannot be undone.")) {
        window.location.href = "delete-listing.php?id=" + productId; 
    }
}

function loadConversation(sellerId, sellerName) {
    window.location.href = `message.php?recipient=${sellerId}`; 
}
</script>

<?php $conn->close(); ?>
</body>
</html>