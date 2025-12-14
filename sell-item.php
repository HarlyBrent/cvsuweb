<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    die("DB Error");
}

if (!isset($_SESSION['cvsuid'])) {
    header("Location: account.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $condition = $_POST['condition'];
    $category = $_POST['category'];
    $variant_type= trim($_POST['variant_type']); 
    $seller_id = $_SESSION['cvsuid'];
    
    $variants = $_POST['variant'] ?? []; 
    $variant_stocks = $_POST['variant_stock'] ?? []; 

    if (empty($name) || $price <= 0 || empty($description) || empty($variants)) {
        $error = "Please fill out all required fields and add at least one variant/stock combination.";
    }

    if (empty($error)) {
        
        $uploadDir = "uploads/list/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadedImages = [];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxFiles = 3; 

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if (count($uploadedImages) >= $maxFiles) {
                    break;
                }
                
                $fileType = $_FILES['images']['type'][$key];

                if (!empty($tmpName) && in_array($fileType, $allowedTypes)) {
                    $originalName = basename($_FILES['images']['name'][$key]);
                    $safeName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);
                    
                    $filename = time() . "_" . uniqid() . "_" . $safeName;
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedImages[] = $targetPath;
                    }
                }
            }
        }

        $image1 = $uploadedImages[0] ?? "";
        $image2 = $uploadedImages[1] ?? "";
        $image3 = $uploadedImages[2] ?? "";
        
        $conn->begin_transaction();
        $success = true;

        $sql = "INSERT INTO products (name, price, description, `condition`, category, variant_type, image, image2, image3, posted_by, seller_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT fullname FROM users WHERE cvsuid=?))";

        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("sdsssssssss", 
            $name, 
            $price, 
            $description, 
            $condition, 
            $category, 
            $variant_type, 
            $image1, 
            $image2, 
            $image3, 
            $seller_id,
            $seller_id 
        );

        if (!$stmt->execute()) {
            $success = false;
            $error = "Error posting main item: " . $stmt->error;
        } else {
            $product_id = $conn->insert_id; 
            $stmt->close();

            $stmt_variant = $conn->prepare("INSERT INTO product_variants (product_id, size_variant, stock_variant) VALUES (?, ?, ?)");
            
            foreach ($variants as $index => $variant_name) {
                $variant_stock = intval($variant_stocks[$index]);
                $variant_name = trim($variant_name);

                if (empty($variant_name) || $variant_stock <= 0) {
                    $success = false;
                    $error = "Invalid variant name or stock value provided.";
                    break;
                }

                if (!$stmt_variant->bind_param("isi", $product_id, $variant_name, $variant_stock) || !$stmt_variant->execute()) {
                    $success = false;
                    $error = "Error posting variant: " . $stmt_variant->error;
                    break;
                }
            }
            $stmt_variant->close();
        }

        if ($success) {
            $conn->commit();
            $conn->close();
            header("Location: index.php?message=Item posted successfully with multiple variants!");
            exit();
        } else {
            $conn->rollback();
            foreach ($uploadedImages as $img) {
                if (file_exists($img)) {
                    unlink($img);
                }
            }
            $conn->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Sell Item - CvSU MarketPlace</title>
<link rel="stylesheet" href="marketplace_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="form-container">
    
    <a href="index.php" class="back-home-btn"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
    
    <h2><i class="fa-solid fa-store"></i> Post a New Item</h2>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="sell-form" method="POST" enctype="multipart/form-data">
        
        <label for="name">Product Name</label>
        <input type="text" id="name" name="name" placeholder="E.g., CvSU PE Uniform" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        
        <label for="price">Price (₱)</label>
        <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
        
        <label for="description">Detailed Description</label>
        <textarea id="description" name="description" placeholder="Describe the item, its flaws, and why you're selling it." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

        <label for="condition">Item Condition</label>
        <select id="condition" name="condition" required>
            <option value="Pre-Loved" <?= (($_POST['condition'] ?? '') == 'Pre-Loved') ? 'selected' : '' ?>>Pre-Loved (Used)</option>
            <option value="Brandnew" <?= (($_POST['condition'] ?? '') == 'Brandnew') ? 'selected' : '' ?>>Brandnew</option>
        </select>

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <option value="Uniform" <?= (($_POST['category'] ?? '') == 'Uniform') ? 'selected' : '' ?>>Uniform</option>
            <option value="Books" <?= (($_POST['category'] ?? '') == 'Books') ? 'selected' : '' ?>>Books</option>
            <option value="Gadget" <?= (($_POST['category'] ?? '') == 'Gadget') ? 'selected' : '' ?>>Gadget</option>
            <option value="Others" <?= (($_POST['category'] ?? '') == 'Others') ? 'selected' : '' ?>>Others</option>
        </select>

        <label for="variant_type">Variant Type Label</label>
        <input type="text" id="variant_type" name="variant_type" placeholder="e.g., Size, Color, Storage" value="<?= htmlspecialchars($_POST['variant_type'] ?? '') ?>" required>
        
        <h3><i class="fa-solid fa-layer-group"></i> Define Stock for Each Variant</h3>
        
        <div class="variant-group">
            <p class="variant-info">Add each specific size/model and its corresponding stock count.</p>
            <div id="variantContainer">
            </div>
            <button type="button" class="add-variant-btn" onclick="addVariantRow()">+ Add Variant/Stock</button>
        </div>

        <label for="images">Product Images (Upload up to 3)</label>
        <input type="file" id="images" name="images[]" multiple accept=".jpg,.jpeg,.png" required>

        <button type="submit"><i class="fa-solid fa-circle-up"></i> Post Item</button>

    </form>
</div>
<script>
    let variantCount = 0;

    function addVariantRow(sizeValue = '', stockValue = 1) {
        variantCount++;
        const container = document.getElementById('variantContainer');
        
        const div = document.createElement('div');
        div.className = 'variant-row';
        div.id = 'variant-' + variantCount;
        
        div.innerHTML = `
            <input type="text" name="variant[]" placeholder="Variant Name (e.g., Small, Blue, 64GB)" value="${sizeValue}" required>
            <input type="number" name="variant_stock[]" placeholder="Stock" min="1" value="${stockValue}" required>
            <button type="button" class="remove-variant-btn" onclick="removeVariantRow(${variantCount})"><i class="fa-solid fa-xmark"></i></button>
        `;
        
        container.appendChild(div);
    }

    function removeVariantRow(id) {
        const row = document.getElementById('variant-' + id);
        if (row) {
            row.remove();
        }
    }

    window.onload = function() {
        <?php if (!empty($_POST['variant'])): ?>
            <?php foreach ($_POST['variant'] as $i => $v): 
                $stock = $_POST['variant_stock'][$i] ?? 1; ?>
                addVariantRow("<?= htmlspecialchars($v) ?>", "<?= htmlspecialchars($stock) ?>");
            <?php endforeach; ?>
        <?php else: ?>
            addVariantRow();
        <?php endif; ?>
    };
</script>


</body>
</html>