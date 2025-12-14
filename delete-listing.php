<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_SESSION['cvsuid'])) {
    header("Location: profile.php");
    exit();
}

$product_id = intval($_GET['id']);
$cvsuid = $_SESSION['cvsuid'];

$stmt_fetch = $conn->prepare("SELECT posted_by, image, image2, image3 FROM products WHERE product_id = ?");
$stmt_fetch->bind_param("i", $product_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$product = $result->fetch_assoc();
$stmt_fetch->close();

if (!$product) {
    header("Location: profile.php?message=Product not found.");
    exit();
}

if ((int)$product['posted_by'] !== (int)$cvsuid) {
    header("Location: profile.php?message=❌ Access denied. Not your listing.");
    exit();
}

$images = [$product['image'], $product['image2'], $product['image3']];

foreach ($images as $img) {
    if (!empty($img)) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . "/" . $img;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}

$stmt_delete = $conn->prepare("DELETE FROM products WHERE product_id = ? AND posted_by = ?");
$stmt_delete->bind_param("ii", $product_id, $cvsuid);

if ($stmt_delete->execute()) {
    $stmt_delete->close();
    $conn->close();
    header("Location: profile.php?message=✅ Listing deleted successfully.");
    exit();
} else {
    $stmt_delete->close();
    $conn->close();
    die("Error deleting listing: " . $conn->error);
}
?>
