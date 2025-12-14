<?php
session_start();

if (!isset($_SESSION['cvsuid'])) {
    header('Location: account.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'cvsu_marketplace');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$cvsuid = $_SESSION['cvsuid'];
$isLoggedIn = true;
$is_admin = false;

$stmt = $conn->prepare("SELECT cvsuid, fullname, email, profile_image, role, is_approved, created_at FROM users WHERE cvsuid = ?");
$stmt->bind_param("s", $cvsuid);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_assoc();
$stmt->close();

if (!$users) {
    session_destroy();
    header("Location: account.php?message=User not found or session expired.");
    exit();
}

if ($users['role'] === 'user' && $users['is_approved'] == 0) {
    session_destroy();
    header("Location: account.php?message=❌ Your account is not approved yet. Please wait for admin confirmation.");
    exit();
}

$is_admin = ($users['role'] === 'admin');

$stmt2 = $conn->prepare("SELECT product_id, name, price, image FROM products WHERE posted_by = ? ORDER BY created_at DESC");
$stmt2->bind_param("s", $cvsuid);
$stmt2->execute();
$userProducts = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$pendingUsers = [];
if ($is_admin) {
    $stmt3 = $conn->prepare("SELECT cvsuid, fullname, email, id_image FROM users WHERE is_approved = 0 AND role = 'user'");
    $stmt3->execute();
    $pendingUsers = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt3->close();
}

$orders = [];
$sql_orders = "
    SELECT 
        o.order_id, o.user_id, o.total_amount, o.status, o.created_at, o.proof_image,
        oi.product_id, oi.quantity, oi.price AS item_price, oi.variant,
        p.name AS product_name, p.image AS product_image, p.posted_by,
        u_seller.fullname AS seller_name,
        u_buyer.fullname AS buyer_name
    FROM 
        orders o
    JOIN 
        order_items oi ON o.order_id = oi.order_id 
    JOIN 
        products p ON oi.product_id = p.product_id
    JOIN 
        users u_seller ON p.posted_by = u_seller.cvsuid
    JOIN 
        users u_buyer ON o.user_id = u_buyer.cvsuid
    WHERE 
        o.user_id = ? OR p.posted_by = ? 
    ORDER BY 
        o.created_at DESC";

$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("ss", $cvsuid, $cvsuid);
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_orders->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - CvSU MarketPlace</title>
<link rel="stylesheet" href="marketplace_style.css"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>

<nav class="navbar">
    <div class="logo"><h2><a href="index.php">CvSU MarketPlace</a></h2></div>
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
    <div id="cartItems" style="flex:1;"><p style="text-align:center;">Loading cart items...</p></div>
    <button onclick="window.location='cart.php'" class="cart-view-full-btn">View Full Cart</button>
</div>
<?php endif; ?>
<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <h3>Hello, <?= htmlspecialchars($users['fullname']) ?> (<?= htmlspecialchars($users['role']) ?>)</h3>
        <a onclick="showSection('profile')" class="active"><i class="fa-solid fa-user-circle"></i> Profile</a>
        <a onclick="showSection('my-products')"><i class="fa-solid fa-store"></i> My Listings</a>
        <a onclick="showSection('order-status')"><i class="fa-solid fa-box-open"></i> Order Status</a>
        <a onclick="showSection('account-settings')"><i class="fa-solid fa-gear"></i> Account Settings</a>
        <?php if($is_admin) echo '<a onclick="showSection(\'account-approval\')"><i class="fa-solid fa-users-viewfinder"></i> Account Approval</a>'; ?>
        <a onclick="showSection('about')"><i class="fa-solid fa-circle-info"></i> About Website</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="dashboard-main">
        <section id="profile" class="dashboard-section active profile-card">
            <img src="<?= $users['profile_image'] ?: 'https://via.placeholder.com/100/007bff/FFFFFF?text=P' ?>" alt="Profile Picture">
            <div>
                <h2><?= htmlspecialchars($users['fullname']) ?></h2>
                <p>ID: <strong><?= htmlspecialchars($users['cvsuid']) ?></strong></p>
                <p>Email: <?= htmlspecialchars($users['email']) ?: 'Not provided' ?></p>
                <p>Role: <span class="role-badge"><?= htmlspecialchars($users['role']) ?></span></p>
                <p>Joined: <?= date("F d, Y", strtotime($users['created_at'])) ?></p>
            </div>
        </section>

        <section id="my-products" class="dashboard-section">
            <h2>📦 My Listings</h2><hr>
            <div class="product-grid">
                <?php if (!empty($userProducts)): ?>
                    <?php foreach ($userProducts as $prod): ?>
                        <div class="product-card" onclick="window.location='product-details.php?id=<?= $prod['product_id'] ?>'">
                            <img src="<?= $prod['image'] ?: 'https://via.placeholder.com/200x150?text=No+Image' ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                            <h4><?= htmlspecialchars($prod['name']) ?></h4>
                            <p class="price">₱<?= number_format($prod['price'],2) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No listings yet. Start selling!</p>
                <?php endif; ?>
            </div>
        </section>

        <section id="order-status" class="dashboard-section">
            <h2>🚚 Order Status</h2><hr>
            <?php if (empty($orders)): ?>
                <p>You have no active orders or sales.</p>
            <?php else: ?>
                <?php 
                $grouped_orders = [];
                foreach ($orders as $item) {
                    $order_id = $item['order_id'];
                    if (!isset($grouped_orders[$order_id])) {
                        $is_seller = ($item['posted_by'] === $cvsuid);
                        $grouped_orders[$order_id] = [
                            'order_id' => $order_id,
                            'status' => $item['status'],
                            'total_amount' => $item['total_amount'],
                            'is_seller' => $is_seller,
                            'partner_name' => $is_seller ? $item['buyer_name'] : $item['seller_name'],
                            'proof_image' => $item['proof_image'],
                            'items' => [],
                        ];
                    }
                    $grouped_orders[$order_id]['items'][] = $item;
                }
                ?>

                <?php foreach ($grouped_orders as $order): 
                    $status_class = 'status-' . str_replace(' ', '', $order['status']);
                    $is_seller = $order['is_seller'];
                ?>
                <div class="order-card">
                    <h4>Order #<?= $order['order_id'] ?> (<?= $is_seller ? 'Sale' : 'Purchase' ?>)</h4>
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="order-card-details">
                        <img src="<?= $item['product_image'] ?: 'placeholder.png' ?>" alt="Product Image">
                        <div>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                            <small>Variant: <?= htmlspecialchars($item['variant'] ?: 'N/A') ?></small><br>
                            <small>Qty: <?= $item['quantity'] ?> @ ₱<?= number_format($item['item_price'], 2) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <p style="margin-top: 10px;">Contact: <span class="partner-name"><?= htmlspecialchars($order['partner_name']) ?></span> (<?= $is_seller ? 'Buyer' : 'Seller' ?>)</p>
                    <p class="total-amount">Total: ₱<?= number_format($order['total_amount'], 2) ?></p>
                    <span class="<?= $status_class ?> order-status-badge"><?= $order['status'] ?></span>

                    <?php if (!empty($order['proof_image'])): ?>
                        <p class="proof-label">Proof of Receipt/Meetup:</p>
                        <img src="<?= htmlspecialchars($order['proof_image']) ?>" class="order-proof-img" onclick="window.open(this.src)">
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if ($is_seller): ?>
                            <?php if ($order['status'] == 'Pending'): ?>
                                <button onclick="updateOrderStatus(<?= $order['order_id'] ?>, 'Ready For Meetup')" class="btn btn-confirm">Mark Ready for Meetup</button>
                            <?php elseif ($order['status'] == 'Ready For Meetup'): ?>
                                <button onclick="updateOrderStatus(<?= $order['order_id'] ?>, 'In Meetup')" class="btn btn-confirm">Start Meetup</button>
                            <?php endif; ?>
                            <?php if ($order['status'] != 'Completed' && $order['status'] != 'Cancelled'): ?>
                                <button onclick="updateOrderStatus(<?= $order['order_id'] ?>, 'Cancelled')" class="btn btn-cancel">Cancel Order</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($order['status'] == 'In Meetup'): ?>
                                <form onsubmit="handleProofUpload(event, this)" method="POST" enctype="multipart/form-data" class="proof-upload-form">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <label for="proof_<?= $order['order_id'] ?>" class="proof-label-file">Upload Proof</label>
                                    <input type="file" name="proof_image" id="proof_<?= $order['order_id'] ?>" accept=".jpg,.jpeg,.png" required class="input-file">
                                    <button type="submit" class="btn btn-confirm">Upload Proof & Complete</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($order['status'] == 'Pending'): ?>
                                <button onclick="updateOrderStatus(<?= $order['order_id'] ?>, 'Cancelled')" class="btn btn-cancel">Cancel Order</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

       <section id="account-settings" class="dashboard-section">
    <h2>⚙️ Account Settings</h2><hr>
    <form class="account-form" method="POST" enctype="multipart/form-data" action="update-account.php">
        <input type="hidden" name="cvsuid" value="<?= htmlspecialchars($users['cvsuid']) ?>">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($users['fullname']) ?>" required>
        <label for="email">Email (optional)</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($users['email']) ?>" placeholder="Add your email">
        <label for="profile_image">Update Profile Picture</label>
        <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png">
        
        <button type="submit" name="update_profile_submit" class="btn btn-primary">💾 Save Changes</button>
        
    </form>
</section>

        <section id="about" class="dashboard-section">
            <h2>ℹ️ About CvSU MarketPlace</h2><hr>
            <p>This is a community marketplace platform for students and staff of Cavite State University to buy and sell items safely.</p>
            <p>Please use the platform responsibly and report any suspicious activity to the administration.</p>
        </section>

        <?php if($is_admin): ?>
        <section id="account-approval" class="dashboard-section">
            <h2>✅ Pending Account Approvals</h2><hr>
            <?php if(!empty($pendingUsers)): ?>
            <div class="table-responsive">
            <table>
                <tr>
                    <th>ID</th><th>Full Name</th><th>Email</th><th>ID Proof</th><th>Action</th>
                </tr>
                <?php foreach($pendingUsers as $pu): ?>
                <tr>
                    <td><?= htmlspecialchars($pu['cvsuid']) ?></td>
                    <td><?= htmlspecialchars($pu['fullname']) ?></td>
                    <td><?= htmlspecialchars($pu['email']) ?></td>
                    <td>
                        <?php if(!empty($pu['id_image'])): ?>
                        <button class="btn btn-secondary viewIDBtn" 
                            data-file="<?= htmlspecialchars($pu['id_image']) ?>" 
                            data-fullname="<?= htmlspecialchars($pu['fullname']) ?>">
                            View ID
                        </button>
                        <?php else: ?>
                            No ID Provided
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="approve-user.php?cvsuid=<?= $pu['cvsuid'] ?>" onclick="return confirm('Approve this user?');" class="action-link-approve">Approve</a> |
                        <a href="#" onclick="rejectUser('<?= $pu['cvsuid'] ?>')" class="action-link-reject">Reject</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
            <?php else: ?>
            <p>No pending accounts.</p>
            <?php endif; ?>

            <div id="idModal" class="modal">
                <div class="modal-content">
                    <span id="closeModal" class="close-btn">&times;</span>
                    <h3 id="modalfullname"></h3>
                    <img id="modalIDImage" src="" class="modal-img">
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<script src="navbar.js"></script>

<script>
function showSection(sectionId){
    document.querySelectorAll('.dashboard-section').forEach(s=>s.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    document.querySelectorAll('.dashboard-sidebar a').forEach(link=>{
        link.classList.remove('active');
        if(link.getAttribute('onclick')?.includes(`'${sectionId}'`)) link.classList.add('active');
    });
}
document.addEventListener('DOMContentLoaded', ()=>showSection('profile'));

function updateOrderStatus(orderId,newStatus){
    if(!confirm(`Change status of Order #${orderId} to "${newStatus}"?`)) return;
    fetch('update-status.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`order_id=${orderId}&status=${newStatus}`
    }).then(r=>r.json()).then(d=>{
        alert(d.message);
        if(d.success) window.location.reload();
    }).catch(e=>alert('Network error.'));
}

function handleProofUpload(e,form){
    e.preventDefault();
    const formData=new FormData(form);
    const btn=form.querySelector('button[type="submit"]');
    btn.disabled=true; btn.textContent='Uploading...';
    fetch('order-proof.php',{method:'POST',body:formData})
    .then(r=>r.json()).then(d=>{
        alert(d.message);
        if(d.success) window.location.reload();
        else {btn.disabled=false; btn.textContent='Upload Proof & Complete';}
    }).catch(err=>{
        alert('Network error: '+err.message);
        btn.disabled=false; btn.textContent='Upload Proof & Complete';
    });
}

const modal = document.getElementById('idModal');
const modalImg = document.getElementById('modalIDImage');
const modalName = document.getElementById('modalfullname');
const closeModal = document.getElementById('closeModal');

document.querySelectorAll('.viewIDBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        modalImg.src = btn.dataset.file;
        modalName.textContent = `ID Photo for ${btn.dataset.fullname}`;
        modal.style.display = 'flex';
    });
});

closeModal.addEventListener('click', ()=>{
    modal.style.display = 'none';
});

window.addEventListener('click', e => {
    if(e.target === modal) modal.style.display = 'none';
});

function rejectUser(cvsuid){
    let reason = prompt("Enter reason for rejection:");
    if(reason) window.location=`reject-user.php?cvsuid=${cvsuid}&reason=${encodeURIComponent(reason)}`;
}

function checkCustomAlert() {
    const urlParams = new URLSearchParams(window.location.search);
    const alertMessage = urlParams.get('custom_alert');

    if (alertMessage) {
        alert(decodeURIComponent(alertMessage));
        
        const newUrl = window.location.href.split('?')[0] + (window.location.search.replace(/([&?])custom_alert=[^&]*/g, '$1').replace(/[?&]$/, ''));
        history.replaceState({}, document.title, newUrl);
    }
}

document.addEventListener('DOMContentLoaded', checkCustomAlert);

function checkCustomAlert() {
    const urlParams = new URLSearchParams(window.location.search);
    const alertMessage = urlParams.get('custom_alert');

    if (alertMessage) {
        alert(decodeURIComponent(alertMessage));
        
      
        const newUrl = window.location.href.split('?')[0] + (window.location.search.replace(/([&?])custom_alert=[^&]*/g, '$1').replace(/[?&]$/, ''));
        history.replaceState({}, document.title, newUrl);
    }
}

document.addEventListener('DOMContentLoaded', checkCustomAlert);
</script>

</body>
</html>
