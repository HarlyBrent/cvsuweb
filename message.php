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

$stmt = $conn->prepare("SELECT role, fullname FROM users WHERE cvsuid = ?");
$stmt->bind_param("s", $cvsuid);
$stmt->execute();
$user_res = $stmt->get_result()->fetch_assoc();
$is_admin = ($user_res && $user_res['role'] === 'admin');
$stmt->close();

$conversations = [];
$users_in_chat = [];

$sql_users = "
    SELECT DISTINCT 
        CASE 
            WHEN sender_cvsuid = ? THEN receiver_cvsuid 
            ELSE sender_cvsuid 
        END AS partner_cvsuid
    FROM messages
    WHERE sender_cvsuid = ? OR receiver_cvsuid = ?
";
$stmt = $conn->prepare($sql_users);
$stmt->bind_param("sss", $cvsuid, $cvsuid, $cvsuid);
$stmt->execute();
$result_users = $stmt->get_result();

while ($row = $result_users->fetch_assoc()) {
    $users_in_chat[] = $row['partner_cvsuid'];
}
$stmt->close();

if (!empty($users_in_chat)) {
    $in_clause = "'" . implode("','", $users_in_chat) . "'";

    $sql_conv = "
        SELECT u.cvsuid, u.fullname, u.profile_image, 
               (SELECT message FROM messages 
                WHERE (sender_cvsuid = u.cvsuid AND receiver_cvsuid = ?) OR 
                      (sender_cvsuid = ? AND receiver_cvsuid = u.cvsuid)
                ORDER BY created_at DESC LIMIT 1) AS last_message,
               (SELECT created_at FROM messages 
                WHERE (sender_cvsuid = u.cvsuid AND receiver_cvsuid = ?) OR 
                      (sender_cvsuid = ? AND receiver_cvsuid = u.cvsuid)
                ORDER BY created_at DESC LIMIT 1) AS created_at,
               (SELECT COUNT(id) FROM messages 
                WHERE sender_cvsuid = u.cvsuid AND receiver_cvsuid = ? AND is_read = 0) AS unread_count
        FROM users u
        WHERE u.cvsuid IN ($in_clause)
        ORDER BY created_at DESC
    ";
    
    $stmt = $conn->prepare($sql_conv);
    $stmt->bind_param("sssss", $cvsuid, $cvsuid, $cvsuid, $cvsuid, $cvsuid);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$current_chat_id = $_GET['chat_id'] ?? null;
$current_chat_name = 'Select a Chat';
$current_partner_data = null;
$current_partner_image = '';

if ($current_chat_id) {
    $stmt = $conn->prepare("SELECT fullname, profile_image FROM users WHERE cvsuid = ?");
    $stmt->bind_param("s", $current_chat_id);
    $stmt->execute();
    $current_partner_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($current_partner_data) {
        $current_chat_name = htmlspecialchars($current_partner_data['fullname']);
        $current_partner_image = htmlspecialchars($current_partner_data['profile_image']);
    }

    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_cvsuid = ? AND receiver_cvsuid = ? AND is_read = 0");
    $stmt->bind_param("ss", $current_chat_id, $cvsuid);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

function get_profile_image_src($image_path, $fullname) {
    if ($image_path) {
        return htmlspecialchars($image_path);
    }
    $initial = strtoupper(substr($fullname, 0, 1));
    return "https://via.placeholder.com/50/DDD/000000?text={$initial}";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - CvSU MarketPlace</title>
<link rel="stylesheet" href="marketplace_style.css"> 
<link rel="stylesheet" href="message_style.css"> 
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
                <a href="message.php" class="message-link active" title="Messages">
                    <i class="fa-solid fa-message"></i>
                    <span id="unreadMessageBadge" style="display:none;"></span>
                </a>
            </li>
        <?php else: ?>
            <li><a href="account.php" class="login-link"><i class="fa-solid fa-right-to-bracket"></i> Login/Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="messenger-container">
    
    <div class="conversation-sidebar">
        <h3>Chats</h3>
        
        <div class="conversation-list">
            <?php if (!empty($conversations)): ?>
                <?php foreach ($conversations as $conv): ?>
                    <a href="message.php?chat_id=<?= htmlspecialchars($conv['cvsuid']) ?>" 
                       class="conversation-item <?= $conv['cvsuid'] === $current_chat_id ? 'active' : '' ?>">
                        <img 
                            src="<?= get_profile_image_src($conv['profile_image'], $conv['fullname']) ?>" 
                            alt="<?= htmlspecialchars($conv['fullname']) ?>" 
                            class="chat-profile-pic"
                        >
                        <div class="conversation-info">
                            <strong><?= htmlspecialchars($conv['fullname']) ?></strong>
                            <p><?= htmlspecialchars($conv['last_message'] ?: 'Start conversation...') ?></p>
                        </div>
                        <span class="chat-time"><?= $conv['created_at'] ? date('H:i', strtotime($conv['created_at'])) : '' ?></span>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="unread-count"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #999; margin-top: 20px;">No active conversations.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="chat-window">
        <?php if ($current_chat_id): ?>
            
            <div class="chat-header">
                <img 
                    src="<?= get_profile_image_src($current_partner_image, $current_chat_name) ?>" 
                    alt="<?= $current_chat_name ?>" 
                    class="chat-profile-pic"
                >
                <h4><?= $current_chat_name ?></h4>
            </div>
            
            <div class="messages-area" id="messagesArea">
            </div>
            
            <div class="message-input-area">
                <form id="sendMessageForm">
                    <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($current_chat_id) ?>">
                    <input type="text" name="message" id="messageInput" placeholder="Type a message..." required>
                    <button type="submit" class="send-btn"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        
        <?php else: ?>
            <div class="chat-placeholder">
                <i class="fa-solid fa-message" style="font-size: 50px; color: #ccc;"></i>
                <h2>Select a chat to start messaging</h2>
                <p>Messages are a great way to coordinate meetups and sales.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="navbar.js"></script>

<script>
    const currentUserId = '<?= htmlspecialchars($cvsuid) ?>';
    const currentChatPartnerId = '<?= htmlspecialchars($current_chat_id) ?>';
    const messagesArea = document.getElementById('messagesArea');
    const messageForm = document.getElementById('sendMessageForm');

    function scrollToBottom() {
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    function fetchMessages() {
        if (!currentChatPartnerId) return;

        fetch(`fetch_messages.php?partner_id=${currentChatPartnerId}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                data.forEach(msg => {
                    const isSelf = msg.sender_cvsuid === currentUserId;
                    const timestamp = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    
                    html += `
                        <div class="message-bubble-container ${isSelf ? 'self-message' : 'other-message'}">
                            <div class="message-bubble">
                                <p>${msg.message}</p>
                                <span class="message-time">${timestamp}</span>
                            </div>
                        </div>
                    `;
                });
                
                const shouldScroll = messagesArea.scrollTop + messagesArea.clientHeight >= messagesArea.scrollHeight - 20;

                messagesArea.innerHTML = html;

                if (shouldScroll) {
                    scrollToBottom();
                }
            })
            .catch(error => console.error('Error fetching messages:', error));
    }

    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('messageInput');
            const messageText = messageInput.value.trim();

            if (!messageText) return;

            const formData = new FormData(this);
            formData.append('message', messageText);

            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = ''; 
                    fetchMessages(); 
                } else {
                    alert('Failed to send message: ' + data.message);
                }
            })
            .catch(error => alert('Network error.'));
        });
    }

    if (currentChatPartnerId) {
        fetchMessages();
        setInterval(fetchMessages, 3000); 
    }

    document.addEventListener('DOMContentLoaded', scrollToBottom);
</script>

</body>
</html>