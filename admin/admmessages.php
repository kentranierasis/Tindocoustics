<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

require_once __DIR__ . '/../config.php';
$pdo = getConnection();
$dbError = null;

// REMOVED: initialsFromName() - now in config.php
// REMOVED: timeAgo() - now in config.php

$adminName = 'Admin';
$conversations = [];
$activeCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
$activeThread = [];
$activeCustomerInfo = null;
$filter = (isset($_GET['filter']) && $_GET['filter'] === 'unread') ? 'unread' : 'all';

try {
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $adminName = $row['full_name'];

    // Reply submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'], $_POST['reply_customer_id'])) {
        $replyText = trim($_POST['reply_message']);
        $replyCustomerId = (int)$_POST['reply_customer_id'];
        $replyIsGuest = isset($_POST['reply_is_guest']) && $_POST['reply_is_guest'] == 1;
        
        if ($replyText !== '' && $replyCustomerId > 0) {
            // Get customer name for the reply
            if ($replyIsGuest) {
                // For guest messages, get the sender name from the original message
                $stmt = $pdo->prepare("SELECT sender_name FROM messages WHERE customer_id = ? AND sender_type = 'customer' ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$replyCustomerId]);
                $guest = $stmt->fetch(PDO::FETCH_ASSOC);
                $senderName = $guest ? $guest['sender_name'] : 'Guest';
            } else {
                // For registered customers, get their full name
                $stmt = $pdo->prepare("SELECT full_name FROM customers WHERE id = ?");
                $stmt->execute([$replyCustomerId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                $senderName = $customer ? $customer['full_name'] : 'Customer';
            }
            
            $stmt = $pdo->prepare(
                "INSERT INTO messages (customer_id, sender_name, sender_type, message, is_read)
                 VALUES (?, ?, 'admin', ?, 1)"
            );
            $stmt->execute([$replyCustomerId, $adminName, $replyText]);
        }
        header('Location: admmessages.php?customer_id=' . $replyCustomerId);
        exit;
    }

    // Delete message
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message_id'])) {
        $deleteId = (int)$_POST['delete_message_id'];
        $redirectCustomerId = (int)($_POST['delete_customer_id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([$deleteId]);
        }
        $redirectUrl = 'admmessages.php';
        $params = [];
        if ($redirectCustomerId > 0) $params['customer_id'] = $redirectCustomerId;
        if (isset($_POST['delete_filter']) && $_POST['delete_filter'] === 'unread') $params['filter'] = 'unread';
        if ($params) $redirectUrl .= '?' . http_build_query($params);
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Delete entire conversation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation_id'])) {
        $deleteConvCustomerId = (int)$_POST['delete_conversation_id'];
        if ($deleteConvCustomerId > 0) {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE customer_id = ?");
            $stmt->execute([$deleteConvCustomerId]);
        }
        $redirectUrl = 'admmessages.php';
        if (isset($_POST['delete_filter']) && $_POST['delete_filter'] === 'unread') {
            $redirectUrl .= '?filter=unread';
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    // ==========================================================================
    // GET ALL CONVERSATIONS - Including guest messages (customer_id = NULL)
    // ==========================================================================
    
    // Get conversations for registered customers
    $customerConversations = $pdo->query("
        SELECT c.id AS customer_id, c.full_name,
                m.message AS last_message, m.created_at AS last_time,
                (SELECT COUNT(*) FROM messages m2
                  WHERE m2.customer_id = c.id AND m2.sender_type = 'customer' AND m2.is_read = 0) AS unread_count,
                0 AS is_guest
        FROM customers c
        JOIN messages m ON m.id = (
            SELECT id FROM messages WHERE customer_id = c.id ORDER BY created_at DESC LIMIT 1
        )
        GROUP BY c.id
        ORDER BY m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get conversations for guest messages (customer_id = NULL)
    $guestConversations = $pdo->query("
        SELECT 
            m.customer_id,
            m.sender_name AS full_name,
            m.message AS last_message,
            m.created_at AS last_time,
            (SELECT COUNT(*) FROM messages m2 
              WHERE m2.customer_id IS NULL 
                AND m2.sender_name = m.sender_name 
                AND m2.sender_type = 'customer' 
                AND m2.is_read = 0) AS unread_count,
            1 AS is_guest
        FROM messages m
        WHERE m.customer_id IS NULL 
          AND m.sender_type = 'customer'
          AND m.id = (
              SELECT MAX(id) FROM messages m3 
              WHERE m3.customer_id IS NULL 
                AND m3.sender_name = m.sender_name 
                AND m3.sender_type = 'customer'
          )
        GROUP BY m.sender_name
        ORDER BY m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge both conversation lists
    $allConversations = array_merge($customerConversations, $guestConversations);
    
    // Sort by last_time (newest first)
    usort($allConversations, function($a, $b) {
        return strtotime($b['last_time']) - strtotime($a['last_time']);
    });
    
    $allCount = count($allConversations);
    $unreadConversations = array_values(array_filter($allConversations, fn($c) => (int)$c['unread_count'] > 0));
    $unreadCount = count($unreadConversations);

    $conversations = $filter === 'unread' ? $unreadConversations : $allConversations;

    // If no active customer selected, select the first one
    if ($activeCustomerId === null && count($allConversations) > 0) {
        $activeCustomerId = (int)$allConversations[0]['customer_id'];
    }

    // Load active thread
    if ($activeCustomerId) {
        // Check if this is a registered customer or guest
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM customers WHERE id = ?");
        $stmt->execute([$activeCustomerId]);
        $activeCustomerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activeCustomerInfo) {
            // Registered customer - get their messages
            $stmt = $pdo->prepare(
                "SELECT * FROM messages WHERE customer_id = ? ORDER BY created_at ASC"
            );
            $stmt->execute([$activeCustomerId]);
            $activeThread = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark customer messages as read
            $stmt = $pdo->prepare(
                "UPDATE messages SET is_read = 1 WHERE customer_id = ? AND sender_type = 'customer'"
            );
            $stmt->execute([$activeCustomerId]);
        } else {
            // Guest - get messages with customer_id = NULL
            $stmt = $pdo->prepare("
                SELECT sender_name, MIN(created_at) as first_message 
                FROM messages 
                WHERE customer_id IS NULL AND sender_type = 'customer'
                GROUP BY sender_name
                ORDER BY first_message ASC
                LIMIT 1
            ");
            $stmt->execute();
            $guestInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($guestInfo) {
                $activeCustomerInfo = [
                    'id' => null,
                    'full_name' => $guestInfo['sender_name'],
                    'email' => 'Guest (no account)'
                ];
                
                // Get all messages from this guest (by sender_name)
                $stmt = $pdo->prepare(
                    "SELECT * FROM messages 
                     WHERE customer_id IS NULL 
                       AND sender_name = ? 
                       AND sender_type = 'customer'
                     ORDER BY created_at ASC"
                );
                $stmt->execute([$guestInfo['sender_name']]);
                $customerMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get admin replies for this guest
                $stmt = $pdo->prepare(
                    "SELECT * FROM messages 
                     WHERE customer_id IS NULL 
                       AND sender_type = 'admin'
                     ORDER BY created_at ASC"
                );
                $stmt->execute();
                $adminReplies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Combine and sort by created_at
                $activeThread = array_merge($customerMessages, $adminReplies);
                usort($activeThread, function($a, $b) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                });
                
                // Mark guest messages as read
                $stmt = $pdo->prepare(
                    "UPDATE messages SET is_read = 1 
                     WHERE customer_id IS NULL 
                       AND sender_name = ? 
                       AND sender_type = 'customer'"
                );
                $stmt->execute([$guestInfo['sender_name']]);
            } else {
                $activeCustomerInfo = [
                    'id' => null,
                    'full_name' => 'Guest',
                    'email' => 'No messages found'
                ];
                $activeThread = [];
            }
        }
    }
} catch (PDOException $e) {
    $dbError = "Couldn't load messages: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Messages — TINDOC Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
      integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />

    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="admmessagestyle.css?v=3" />
  </head>
  <body>
    <div class="admin-layout">
      <aside class="admin-sidebar">
        <img src="../images/Admin Navbar Background.png" alt="" class="admin-sidebar-bg" aria-hidden="true" />
        <div class="admin-sidebar-content">
          <div class="admin-sidebar-logo">
            <img src="../images/White-Logo.png" alt="TINDOC Acoustic Guitars" />
          </div>

          <nav class="admin-nav">
            <a href="adminDashboard.php" class="admin-nav-item">
              <i class="fa-solid fa-house"></i><span>Dashboard</span>
            </a>
            <a href="admproducts.php" class="admin-nav-item">
              <i class="fa-solid fa-guitar"></i><span>Products</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admorder.php" class="admin-nav-item">
              <i class="fa-solid fa-bag-shopping"></i><span>Orders</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admcustomers.php" class="admin-nav-item">
              <i class="fa-solid fa-users"></i><span>Customers</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admmessages.php" class="admin-nav-item active">
              <i class="fa-regular fa-envelope"></i><span>Messages</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
          </nav>

          <blockquote class="admin-sidebar-quote">
            "Quality Guitars<br />for a Brighter<br />Tomorrow."
          </blockquote>
        </div>
      </aside>

      <main class="admin-main">
        <div class="admin-topbar">
          <div class="admin-profile" onclick="toggleAdminMenu(event)">
            <span class="admin-avatar"><?= htmlspecialchars(mb_substr($adminName, 0, 1)) ?></span>
            <span><?= htmlspecialchars($adminName) ?></span>
            <i class="fa-solid fa-chevron-down"></i>
            <div class="admin-profile-menu" id="adminProfileMenu">
              <a href="logout.php" class="admin-profile-menu-item admin-profile-menu-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
              </a>
            </div>
          </div>
        </div>

        <?php if ($dbError): ?>
        <div class="admin-db-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($dbError) ?></div>
        <?php endif; ?>

        <div class="admin-page-header">
          <div>
            <p class="eyebrow">Admin Panel</p>
            <h1>Messages</h1>
            <p class="admin-page-subtitle">View and reply to customer inquiries and support messages.</p>
          </div>
        </div>

        <div class="admin-panel admin-messages-panel">
          <div class="messages-layout">
            <div class="conversations-panel">
              <div class="conversation-tabs">
                <a href="?filter=all<?= $activeCustomerId ? '&customer_id=' . $activeCustomerId : '' ?>"
                   class="conversation-tab <?= $filter === 'all' ? 'active' : '' ?>">
                  All Messages <span class="tab-count"><?= $allCount ?></span>
                </a>
                <a href="?filter=unread<?= $activeCustomerId ? '&customer_id=' . $activeCustomerId : '' ?>"
                   class="conversation-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                  Unread <span class="tab-count"><?= $unreadCount ?></span>
                </a>
              </div>

              <ul class="conversation-list">
                <?php if (count($conversations) === 0): ?>
                  <li class="admin-empty-state" style="padding:2rem 1.25rem;">
                    <?= $filter === 'unread' ? 'No unread conversations.' : 'No conversations yet.' ?>
                  </li>
                <?php endif; ?>
                <?php foreach ($conversations as $conv): ?>
                <li>
                  <a href="?customer_id=<?= (int)$conv['customer_id'] ?><?= $filter === 'unread' ? '&filter=unread' : '' ?>"
                     class="conversation-item <?= (int)$conv['customer_id'] === $activeCustomerId ? 'active' : '' ?>">
                    <span class="conversation-avatar"><?= htmlspecialchars(initialsFromName($conv['full_name'])) ?></span>
                    <div class="conversation-info">
                      <div class="conversation-top">
                        <p class="conversation-name">
                          <?= htmlspecialchars($conv['full_name']) ?>
                          <?php if (isset($conv['is_guest']) && $conv['is_guest'] == 1): ?>
                            <span style="font-size:0.65rem;background:#e2ddd6;color:#6b5d4f;padding:0.1rem 0.4rem;border-radius:3px;margin-left:0.3rem;">Guest</span>
                          <?php endif; ?>
                        </p>
                        <span class="conversation-time"><?= htmlspecialchars(timeAgo($conv['last_time'])) ?></span>
                      </div>
                      <div class="conversation-bottom">
                        <p class="conversation-preview"><?= htmlspecialchars($conv['last_message']) ?></p>
                        <?php if ((int)$conv['unread_count'] > 0): ?>
                          <span class="unread-badge"><?= (int)$conv['unread_count'] ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </a>
                </li>
                <?php endforeach; ?>
              </ul>

              <p class="conversation-list-footer">Showing <?= count($conversations) ?> conversation<?= count($conversations) === 1 ? '' : 's' ?></p>
            </div>

            <div class="chat-panel">
              <?php if ($activeCustomerInfo): ?>
              <div class="chat-header">
                <span class="chat-header-avatar"><?= htmlspecialchars(initialsFromName($activeCustomerInfo['full_name'])) ?></span>
                <div class="chat-header-info">
                  <p class="chat-header-name"><?= htmlspecialchars($activeCustomerInfo['full_name']) ?></p>
                  <p class="chat-header-email"><?= htmlspecialchars($activeCustomerInfo['email']) ?></p>
                  <?php if ($activeCustomerInfo['id'] === null): ?>
                    <p class="chat-header-email" style="color:#d4a017;font-size:0.75rem;">
                      <i class="fa-solid fa-user"></i> Guest user (no account)
                    </p>
                  <?php endif; ?>
                </div>
                <div class="chat-header-actions">
                  <form method="post"
                        onsubmit="return confirm('Delete this entire conversation? This cannot be undone.');">
                    <input type="hidden" name="delete_conversation_id" value="<?= (int)$activeCustomerInfo['id'] ?>" />
                    <input type="hidden" name="delete_filter" value="<?= htmlspecialchars($filter) ?>" />
                    <button type="submit" class="btn-delete-conversation" aria-label="Delete conversation" title="Delete conversation">
                      <i class="fa-solid fa-trash-can"></i> <span>Delete conversation</span>
                    </button>
                  </form>
                </div>
              </div>

              <div class="chat-messages">
                <?php if (count($activeThread) > 0): ?>
                  <?php foreach ($activeThread as $m): ?>
                    <?php $isSent = $m['sender_type'] === 'admin'; ?>
                    <div class="message-row <?= $isSent ? 'sent' : 'received' ?>">
                      <?php if (!$isSent): ?>
                        <span class="message-avatar"><?= htmlspecialchars(initialsFromName($m['sender_name'])) ?></span>
                      <?php endif; ?>
                      <div class="message-bubble">
                        <form method="post" class="message-delete-form"
                              onsubmit="return confirm('Delete this message?');">
                          <input type="hidden" name="delete_message_id" value="<?= (int)$m['id'] ?>" />
                          <input type="hidden" name="delete_customer_id" value="<?= (int)$activeCustomerInfo['id'] ?>" />
                          <input type="hidden" name="delete_filter" value="<?= htmlspecialchars($filter) ?>" />
                          <button type="submit" class="btn-delete-message" aria-label="Delete message" title="Delete message">
                            <i class="fa-solid fa-xmark"></i>
                          </button>
                        </form>
                        <p><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                        <span class="message-time"><?= date('g:i A', strtotime($m['created_at'])) ?></span>
                      </div>
                      <?php if ($isSent): ?>
                        <span class="message-avatar message-avatar-admin"><?= htmlspecialchars(mb_substr($adminName, 0, 1)) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="admin-empty-state">No messages in this conversation.</p>
                <?php endif; ?>
              </div>

              <form class="chat-footer" method="post">
                <input type="hidden" name="reply_customer_id" value="<?= (int)$activeCustomerInfo['id'] ?>" />
                <?php if ($activeCustomerInfo['id'] === null): ?>
                  <input type="hidden" name="reply_is_guest" value="1" />
                <?php endif; ?>
                <button type="button" class="btn-attach" aria-label="Attach file" onclick="alert('No Function Yet')">
                  <i class="fa-solid fa-paperclip"></i>
                </button>
                <input type="text" name="reply_message" class="chat-input" placeholder="Type a message..." required />
                <button type="submit" class="btn-send" aria-label="Send message">
                  <i class="fa-solid fa-paper-plane"></i>
                </button>
              </form>
              <?php else: ?>
                <p class="admin-empty-state">Select a conversation to view messages.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </main>
    </div>
    <script>
      function toggleAdminMenu(event) {
        event.stopPropagation();
        document.getElementById('adminProfileMenu').classList.toggle('open');
      }
      document.addEventListener('click', function (event) {
        var menu = document.getElementById('adminProfileMenu');
        var profile = document.querySelector('.admin-profile');
        if (menu && profile && !profile.contains(event.target)) menu.classList.remove('open');
      });
    </script>
  </body>
</html>