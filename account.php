<?php
/* ==========================================================================
   TINDOC ACOUSTIC GUITARS — CUSTOMER ACCOUNT PANEL
   Shows a logged-in customer's profile, order history, cart, wishlist, and messages.
   ========================================================================== */

require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Guard: bounce guests back to the homepage with the login modal ----
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php?login=1');
    exit;
}

$customer_id = $_SESSION['customer_id'];
$pdo = getConnection();
$dbError = null;

/* ==========================================================================
   1. PROFILE — Get real customer data
   ========================================================================== */
try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, status, created_at FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Customer not found, logout and redirect
        session_destroy();
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $dbError = "Could not load profile: " . $e->getMessage();
    $customer = [
        'id' => $customer_id,
        'full_name' => 'User',
        'email' => 'user@example.com',
        'phone' => '',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

/* ==========================================================================
   2. ORDERS — Get real orders with items
   ========================================================================== */
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, order_number, order_date, total, payment_method, status 
        FROM orders 
        WHERE customer_id = ? 
        ORDER BY order_date DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each order, get the items
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT oi.quantity, oi.unit_price, p.name, p.variant_label, p.image_path 
            FROM order_items oi 
            JOIN products p ON p.id = oi.product_id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order);
} catch (PDOException $e) {
    $dbError = $dbError ? $dbError . " | Could not load orders." : "Could not load orders.";
    $orders = [];
}

/* ==========================================================================
   3. CART — Get real cart items
   ========================================================================== */
$cart = [];
$cart_total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.quantity, p.id AS product_id, p.name, 
               p.variant_label, p.price, p.image_path, p.stock_status
        FROM cart_items ci 
        JOIN products p ON p.id = ci.product_id 
        WHERE ci.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cart_total = array_reduce($cart, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
} catch (PDOException $e) {
    $dbError = $dbError ? $dbError . " | Could not load cart." : "Could not load cart.";
    $cart = [];
}

/* ==========================================================================
   4. WISHLIST — Get real wishlist items
   ========================================================================== */
$wishlist = [];
$wishlist_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT wi.id, p.id AS product_id, p.name, p.variant_label, 
               p.price, p.image_path, p.stock_status
        FROM wishlist_items wi 
        JOIN products p ON p.id = wi.product_id 
        WHERE wi.customer_id = ?
        ORDER BY wi.added_at DESC
    ");
    $stmt->execute([$customer_id]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $wishlist_count = count($wishlist);
} catch (PDOException $e) {
    $dbError = $dbError ? $dbError . " | Could not load wishlist." : "Could not load wishlist.";
    $wishlist = [];
}

/* ==========================================================================
   5. MESSAGES — Get real messages from admin
   ========================================================================== */
$messages = [];
$unread_count = 0;
try {
    // Get all messages for this customer (both sent and received)
    $stmt = $pdo->prepare("
        SELECT id, sender_name, sender_type, message, is_read, created_at 
        FROM messages 
        WHERE customer_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread messages (admin messages that are not read)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages 
        WHERE customer_id = ? AND sender_type = 'admin' AND is_read = 0
    ");
    $stmt->execute([$customer_id]);
    $unread_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $dbError = $dbError ? $dbError . " | Could not load messages." : "Could not load messages.";
    $messages = [];
}

// ---- Display helpers ----
function pretty_date($datetime) { return date('M j, Y', strtotime($datetime)); }
function pretty_datetime($datetime) { return date('M j, Y g:i A', strtotime($datetime)); }
function status_label($status) { return ucwords(str_replace('-', ' ', $status)); }

// Handle form submissions
$flashMessage = null;
$formError = null;

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($full_name) || empty($email)) {
        $formError = "Name and email are required.";
    } else {
        try {
            if (!empty($password)) {
                // Update with new password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $hashed, $customer_id]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $customer_id]);
            }
            $flashMessage = "Profile updated successfully!";
            // Refresh customer data
            $stmt = $pdo->prepare("SELECT id, full_name, email, phone, status, created_at FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            // Update session name
            $_SESSION['customer_name'] = $customer['full_name'];
        } catch (PDOException $e) {
            $formError = str_contains($e->getMessage(), 'Duplicate entry') 
                ? "That email is already used by another account." 
                : "Could not update profile: " . $e->getMessage();
        }
    }
}

// Update cart quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cart_qty') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    
    if ($cart_id > 0) {
        try {
            // Check stock
            $stmt = $pdo->prepare("
                SELECT p.stock_quantity, p.stock_status 
                FROM cart_items ci 
                JOIN products p ON p.id = ci.product_id 
                WHERE ci.id = ? AND ci.customer_id = ?
            ");
            $stmt->execute([$cart_id, $customer_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product && $product['stock_status'] !== 'out-of-stock' && $product['stock_quantity'] >= $quantity) {
                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND customer_id = ?");
                $stmt->execute([$quantity, $cart_id, $customer_id]);
                $flashMessage = "Cart updated!";
            } else {
                $formError = "Not enough stock available.";
            }
        } catch (PDOException $e) {
            $formError = "Could not update cart.";
        }
    }
}

// Remove from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    if ($cart_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND customer_id = ?");
            $stmt->execute([$cart_id, $customer_id]);
            $flashMessage = "Item removed from cart.";
        } catch (PDOException $e) {
            $formError = "Could not remove item.";
        }
    }
}

// Remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_from_wishlist') {
    $wishlist_id = (int)($_POST['wishlist_id'] ?? 0);
    if ($wishlist_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ? AND customer_id = ?");
            $stmt->execute([$wishlist_id, $customer_id]);
            $flashMessage = "Item removed from wishlist.";
            // Refresh wishlist count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            $wishlist_count = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $formError = "Could not remove item.";
        }
    }
}

// Add to cart from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'wishlist_to_cart') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $wishlist_id = (int)($_POST['wishlist_id'] ?? 0);
    
    if ($product_id > 0) {
        try {
            // Check stock
            $stmt = $pdo->prepare("SELECT stock_quantity, stock_status, name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product && $product['stock_status'] !== 'out-of-stock' && $product['stock_quantity'] >= 1) {
                // Add to cart
                $stmt = $pdo->prepare("
                    INSERT INTO cart_items (customer_id, product_id, quantity) 
                    VALUES (?, ?, 1) 
                    ON DUPLICATE KEY UPDATE quantity = quantity + 1
                ");
                $stmt->execute([$customer_id, $product_id]);
                
                // Remove from wishlist
                if ($wishlist_id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE id = ? AND customer_id = ?");
                    $stmt->execute([$wishlist_id, $customer_id]);
                }
                
                $flashMessage = $product['name'] . " added to cart!";
                // Refresh wishlist count
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?");
                $stmt->execute([$customer_id]);
                $wishlist_count = (int)$stmt->fetchColumn();
            } else {
                $formError = "Item is out of stock.";
            }
        } catch (PDOException $e) {
            $formError = "Could not add to cart.";
        }
    }
}

// Mark messages as read when viewing messages tab
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 1) {
    try {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE customer_id = ? AND sender_type = 'admin'");
        $stmt->execute([$customer_id]);
        $unread_count = 0;
        // Refresh messages
        $stmt = $pdo->prepare("
            SELECT id, sender_name, sender_type, message, is_read, created_at 
            FROM messages 
            WHERE customer_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$customer_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Refresh data after actions
if ($flashMessage || $formError) {
    // Refresh cart and wishlist
    try {
        $stmt = $pdo->prepare("
            SELECT ci.id, ci.quantity, p.id AS product_id, p.name, 
                   p.variant_label, p.price, p.image_path, p.stock_status
            FROM cart_items ci 
            JOIN products p ON p.id = ci.product_id 
            WHERE ci.customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cart_total = array_reduce($cart, function($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']);
        }, 0);
    } catch (PDOException $e) {}
    
    try {
        $stmt = $pdo->prepare("
            SELECT wi.id, p.id AS product_id, p.name, p.variant_label, 
                   p.price, p.image_path, p.stock_status
            FROM wishlist_items wi 
            JOIN products p ON p.id = wi.product_id 
            WHERE wi.customer_id = ?
            ORDER BY wi.added_at DESC
        ");
        $stmt->execute([$customer_id]);
        $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $wishlist_count = count($wishlist);
    } catch (PDOException $e) {}
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account — TINDOC Acoustic Guitars</title>

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

    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/accountstyle.css" />
  </head>
  <body>
    <!-- ============ HEADER / NAV ============ -->
    <header class="site-header">
      <img src="images/Nav-Design.png" alt="" class="nav-design" aria-hidden="true" />
      <div class="nav-container">
        <a href="index.php" class="logo">
          <img src="images/Main-Logo.png" alt="TINDOC Acoustic Guitars" />
        </a>

        <nav class="main-nav" id="main-nav">
          <ul>
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="about.php">ABOUT</a></li>
            <li><a href="accessories.php">ACCESSORIES</a></li>
            <li><a href="contact.php">CONTACT</a></li>
          </ul>
        </nav>

        <div class="nav-icons">
          <button class="icon-btn active" aria-label="Account">
            <i class="fa-solid fa-user"></i>
          </button>
          <button class="icon-btn" aria-label="Wishlist" onclick="switchTab('wishlist')" style="position:relative;">
            <i class="fa-regular fa-heart"></i>
            <span class="wishlist-badge" id="wishlistBadge" style="<?= $wishlist_count > 0 ? 'display:inline;' : 'display:none;' ?>position:absolute;top:-8px;right:-8px;background:#a12c2c;color:white;font-size:0.6rem;font-weight:700;padding:0.1rem 0.4rem;border-radius:50%;min-width:18px;text-align:center;z-index:5;"><?= $wishlist_count ?></span>
          </button>
          <button class="icon-btn" aria-label="Cart" onclick="switchTab('cart')">
            <i class="fa-solid fa-cart-shopping"></i>
          </button>
          <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="main-nav">
            <span></span><span></span><span></span>
          </button>
        </div>
      </div>
    </header>

    <!-- ============ ACCOUNT HEADER ============ -->
    <section class="account-hero">
      <div class="account-hero-inner">
        <div class="account-avatar"><?= strtoupper(substr($customer['full_name'], 0, 1)) ?></div>
        <div>
          <p class="eyebrow">My Account</p>
          <h1><?= htmlspecialchars($customer['full_name']) ?></h1>
          <p class="account-hero-sub">Member since <?= pretty_date($customer['created_at']) ?></p>
          <?php if (isset($_SESSION['customer_id'])): ?>
            <p style="font-size:0.8rem;color:#226a44;margin-top:0.25rem;">
              <i class="fa-solid fa-circle-check"></i> Logged in
              <a href="logout.php" style="color:#a13a3a;margin-left:1rem;text-decoration:underline;">Logout</a>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- ============ ACCOUNT PANEL ============ -->
    <section class="account-section">
      <div class="account-layout">
        <!-- Tab nav -->
        <nav class="account-tabs" aria-label="Account sections">
          <button class="account-tab active" data-tab="profile" onclick="switchTab('profile')">
            <i class="fa-regular fa-user"></i> Profile
          </button>
          <button class="account-tab" data-tab="orders" onclick="switchTab('orders')">
            <i class="fa-solid fa-box"></i> Orders
            <span class="tab-count"><?= count($orders) ?></span>
          </button>
          <button class="account-tab" data-tab="cart" onclick="switchTab('cart')">
            <i class="fa-solid fa-cart-shopping"></i> Cart
            <span class="tab-count"><?= count($cart) ?></span>
          </button>
          <button class="account-tab" data-tab="wishlist" onclick="switchTab('wishlist')">
            <i class="fa-regular fa-heart"></i> Wishlist
            <span class="tab-count"><?= $wishlist_count ?></span>
          </button>
          <button class="account-tab" data-tab="messages" onclick="switchTab('messages')" style="position:relative;">
            <i class="fa-regular fa-envelope"></i> Messages
            <span class="tab-count"><?= count($messages) ?></span>
            <?php if ($unread_count > 0): ?>
              <span class="tab-count" style="background:#a12c2c;color:white;min-width:20px;"><?= $unread_count ?></span>
            <?php endif; ?>
          </button>
        </nav>

        <div class="account-panels">
          <?php if ($flashMessage): ?>
            <div class="admin-flash" style="background:#e4f3ea;color:#226a44;border:1px solid #c3e6d1;border-radius:4px;padding:0.85rem 1.1rem;margin-bottom:1rem;font-size:0.85rem;">
              <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashMessage) ?>
            </div>
          <?php endif; ?>
          <?php if ($formError): ?>
            <div class="admin-flash" style="background:#fbeaea;color:#a13a3a;border:1px solid #f0caca;border-radius:4px;padding:0.85rem 1.1rem;margin-bottom:1rem;font-size:0.85rem;">
              <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($formError) ?>
            </div>
          <?php endif; ?>
          <?php if ($dbError): ?>
            <div class="admin-flash" style="background:#fbeaea;color:#a13a3a;border:1px solid #f0caca;border-radius:4px;padding:0.85rem 1.1rem;margin-bottom:1rem;font-size:0.85rem;">
              <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($dbError) ?>
            </div>
          <?php endif; ?>

          <!-- ---------------- PROFILE ---------------- -->
          <div class="account-panel active" id="panel-profile">
            <h2>Personal Information</h2>
            <form class="profile-form" method="post">
              <input type="hidden" name="action" value="update_profile" />
              <div class="form-row">
                <label>Full Name
                  <input type="text" name="full_name" value="<?= htmlspecialchars($customer['full_name']) ?>" required />
                </label>
                <label>Email Address
                  <input type="email" name="email" value="<?= htmlspecialchars($customer['email']) ?>" required />
                </label>
              </div>
              <div class="form-row">
                <label>Phone Number
                  <input type="text" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" />
                </label>
                <label>Account Status
                  <input type="text" value="<?= status_label($customer['status']) ?>" disabled />
                </label>
              </div>
              <div class="form-row">
                <label>New Password
                  <input type="password" name="password" placeholder="Leave blank to keep current password" />
                </label>
              </div>
              <button type="submit" class="btn btn-dark">Save Changes</button>
            </form>
          </div>

          <!-- ---------------- ORDERS ---------------- -->
          <div class="account-panel" id="panel-orders">
            <h2>Order History</h2>

            <?php if (empty($orders)): ?>
              <p class="empty-state">You haven't placed any orders yet. <a href="shop.php">Start shopping →</a></p>
            <?php else: ?>
              <div class="order-list">
                <?php foreach ($orders as $order): ?>
                  <div class="order-card">
                    <div class="order-card-head">
                      <div>
                        <h3>#<?= htmlspecialchars($order['order_number']) ?></h3>
                        <p class="order-date"><?= pretty_date($order['order_date']) ?> · <?= htmlspecialchars($order['payment_method']) ?></p>
                      </div>
                      <span class="order-status status-<?= htmlspecialchars($order['status']) ?>">
                        <?= status_label($order['status']) ?>
                      </span>
                    </div>

                    <div class="order-items">
                      <?php foreach ($order['items'] as $item): ?>
                        <div class="order-item-row">
                          <img src="<?= htmlspecialchars($item['image_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($item['name']) ?>" />
                          <div class="order-item-info">
                            <p class="order-item-name"><?= htmlspecialchars($item['name']) ?></p>
                            <?php if ($item['variant_label']): ?>
                              <p class="order-item-variant"><?= htmlspecialchars($item['variant_label']) ?></p>
                            <?php endif; ?>
                          </div>
                          <p class="order-item-qty">x<?= (int)$item['quantity'] ?></p>
                          <p class="order-item-price"><?= formatPrice($item['unit_price']) ?></p>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <div class="order-card-foot">
                      <p class="order-total">Total: <strong><?= formatPrice($order['total']) ?></strong></p>
                      <button class="btn-link" onclick="openOrderDetails(<?= (int)$order['id'] ?>)">View Details</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- ---------------- CART ---------------- -->
          <div class="account-panel" id="panel-cart">
            <h2>My Cart</h2>

            <?php if (empty($cart)): ?>
              <p class="empty-state">Your cart is empty. <a href="shop.php">Browse guitars →</a></p>
            <?php else: ?>
              <div class="cart-list">
                <?php foreach ($cart as $item): ?>
                  <div class="cart-row" style="display:grid;grid-template-columns:64px 1fr auto auto auto;align-items:center;gap:1rem;padding:1rem;border:1px solid var(--color-border);border-radius:var(--radius-sm);margin-bottom:0.5rem;">
                    
                    <!-- Product Image -->
                    <img src="<?= htmlspecialchars($item['image_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:var(--radius-sm);" />
                    
                    <!-- Product Info -->
                    <div class="cart-row-info">
                      <p class="cart-row-name"><?= htmlspecialchars($item['name']) ?></p>
                      <?php if ($item['variant_label']): ?>
                        <p class="cart-row-variant"><?= htmlspecialchars($item['variant_label']) ?></p>
                      <?php endif; ?>
                      <p class="cart-row-price" style="font-size:0.8rem;color:#6b5d4f;margin:0;"><?= formatPrice($item['price']) ?> each</p>
                      <?php if ($item['stock_status'] === 'low-stock'): ?>
                        <p class="stock-flag stock-low">Low stock</p>
                      <?php elseif ($item['stock_status'] === 'out-of-stock'): ?>
                        <p class="stock-flag stock-out">Out of stock</p>
                      <?php endif; ?>
                    </div>

                    <!-- Quantity Controls -->
                    <form method="post" class="quantity-form" style="display:flex;align-items:center;gap:0.25rem;">
                      <input type="hidden" name="action" value="update_cart_qty" />
                      <input type="hidden" name="cart_id" value="<?= (int)$item['id'] ?>" />
                      
                      <button type="submit" class="qty-btn" name="quantity" value="<?= max(1, (int)$item['quantity'] - 1) ?>" aria-label="Decrease quantity" style="width:32px;height:32px;border:1px solid var(--color-border);background:var(--color-cream);border-radius:var(--radius-sm);cursor:pointer;">
                        <i class="fa-solid fa-minus" style="font-size:0.7rem;"></i>
                      </button>
                      
                      <input class="qty-input" type="number" value="<?= (int)$item['quantity'] ?>" min="1" max="99" readonly style="width:48px;height:32px;text-align:center;border:1px solid var(--color-border);border-radius:var(--radius-sm);background:var(--color-white);font-size:0.9rem;" />
                      
                      <button type="submit" class="qty-btn" name="quantity" value="<?= (int)$item['quantity'] + 1 ?>" aria-label="Increase quantity" style="width:32px;height:32px;border:1px solid var(--color-border);background:var(--color-cream);border-radius:var(--radius-sm);cursor:pointer;">
                        <i class="fa-solid fa-plus" style="font-size:0.7rem;"></i>
                      </button>
                    </form>

                    <!-- Total Price -->
                    <p class="cart-row-price" style="font-weight:700;font-size:0.95rem;"><?= formatPrice($item['price'] * $item['quantity']) ?></p>
                    
                    <!-- Remove Button -->
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="remove_from_cart" />
                      <input type="hidden" name="cart_id" value="<?= (int)$item['id'] ?>" />
                      <button type="submit" class="cart-remove" aria-label="Remove" style="background:none;border:none;color:#9e9393;font-size:0.95rem;padding:0.4rem;cursor:pointer;transition:color 0.2s;">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="cart-summary" style="display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--color-border);font-size:1rem;">
                <p style="font-weight:600;">Subtotal</p>
                <p class="cart-summary-total" style="font-size:1.2rem;font-weight:700;"><?= formatPrice($cart_total) ?></p>
              </div>
              
              <button class="btn btn-primary cart-checkout" onclick="openCheckoutModal()" style="display:block;width:100%;margin-top:1.25rem;text-align:center;padding:0.9rem;">
                Proceed to Checkout →
              </button>
            <?php endif; ?>
          </div>

          <!-- ---------------- WISHLIST ---------------- -->
          <div class="account-panel" id="panel-wishlist">
            <h2>My Wishlist</h2>

            <?php if (empty($wishlist)): ?>
              <p class="empty-state">You haven't saved any guitars yet. <a href="shop.php">Browse guitars →</a></p>
            <?php else: ?>
              <div class="wishlist-grid">
                <?php foreach ($wishlist as $item): ?>
                  <div class="wishlist-card">
                    <form method="post" style="position:absolute;top:0.6rem;right:0.6rem;z-index:1;">
                      <input type="hidden" name="action" value="remove_from_wishlist" />
                      <input type="hidden" name="wishlist_id" value="<?= (int)$item['id'] ?>" />
                      <button type="submit" class="wishlist-remove" aria-label="Remove from wishlist" style="width:2rem;height:2rem;border-radius:50%;background:var(--color-white);border:1px solid var(--color-border);color:var(--color-dark);font-size:0.85rem;display:flex;align-items:center;justify-content:center;cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </form>
                    <img src="<?= htmlspecialchars($item['image_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($item['name']) ?>" />
                    <div class="wishlist-card-info">
                      <h3><?= htmlspecialchars($item['name']) ?></h3>
                      <?php if ($item['variant_label']): ?>
                        <p class="wishlist-variant"><?= htmlspecialchars($item['variant_label']) ?></p>
                      <?php endif; ?>
                      <p class="wishlist-price"><?= formatPrice($item['price']) ?></p>
                      
                      <?php if ($item['stock_status'] === 'out-of-stock'): ?>
                        <button class="btn btn-dark wishlist-add-cart" disabled style="background:#d9d3cb;color:#8a7c6c;cursor:not-allowed;">Out of Stock</button>
                      <?php else: ?>
                        <form method="post">
                          <input type="hidden" name="action" value="wishlist_to_cart" />
                          <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>" />
                          <input type="hidden" name="wishlist_id" value="<?= (int)$item['id'] ?>" />
                          <button type="submit" class="btn btn-dark wishlist-add-cart">Add to Cart</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- ---------------- MESSAGES ---------------- -->
          <div class="account-panel" id="panel-messages">
            <h2>My Messages</h2>
            
            <?php if ($unread_count > 0): ?>
              <div style="background:#e4f3ea;color:#226a44;border:1px solid #c3e6d1;border-radius:4px;padding:0.85rem 1.1rem;margin-bottom:1rem;font-size:0.85rem;">
                <i class="fa-solid fa-circle-check"></i> You have <strong><?= $unread_count ?></strong> unread message<?= $unread_count > 1 ? 's' : '' ?> from our team.
                <a href="?mark_read=1" style="color:#226a44;font-weight:600;text-decoration:underline;margin-left:0.5rem;">Mark all as read</a>
              </div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
              <p class="empty-state">You don't have any messages yet. <a href="contact.php">Contact us →</a></p>
            <?php else: ?>
              <div class="message-list">
                <?php foreach ($messages as $msg): ?>
                  <div class="message-card <?= $msg['sender_type'] === 'admin' ? 'message-admin' : 'message-customer' ?>">
                    <div class="message-header">
                      <div class="message-sender">
                        <?php if ($msg['sender_type'] === 'admin'): ?>
                          <span class="message-avatar-small" style="background:var(--color-brown);color:white;display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;font-size:0.75rem;font-weight:700;margin-right:0.5rem;">
                            <i class="fa-solid fa-user-tie"></i>
                          </span>
                          <span class="message-sender-name" style="font-weight:700;color:var(--color-brown);">
                            TINDOC Support
                            <?php if ($msg['is_read'] == 0): ?>
                              <span style="background:#a12c2c;color:white;font-size:0.6rem;font-weight:700;padding:0.1rem 0.4rem;border-radius:3px;margin-left:0.5rem;">New</span>
                            <?php endif; ?>
                          </span>
                        <?php else: ?>
                          <span class="message-avatar-small" style="background:var(--color-darker);color:white;display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;font-size:0.75rem;font-weight:700;margin-right:0.5rem;">
                            <?= strtoupper(substr($customer['full_name'], 0, 1)) ?>
                          </span>
                          <span class="message-sender-name">You</span>
                        <?php endif; ?>
                      </div>
                      <span class="message-time"><?= pretty_datetime($msg['created_at']) ?></span>
                    </div>
                    <div class="message-body">
                      <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <div style="margin-top:1.5rem;text-align:center;padding-top:1.5rem;border-top:1px solid var(--color-border);">
                <a href="contact.php" class="btn btn-primary">Send New Message</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ CHECKOUT MODAL ============ -->
    <div class="checkout-modal-backdrop" id="checkoutModal">
        <div class="checkout-modal">
            <div class="checkout-modal-header">
                <h2>Order Summary</h2>
                <button type="button" class="checkout-modal-close" onclick="closeCheckoutModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="checkout-modal-body">
                <div id="checkoutItems">
                    <!-- Items will be loaded here via JavaScript -->
                </div>
                
                <div class="checkout-total">
                    <span>Total Amount</span>
                    <span id="checkoutTotal">₱0</span>
                </div>

                <div class="checkout-form">
                    <h3>Payment Details</h3>
                    
                    <div class="form-field">
                        <label>Payment Method</label>
                        <select id="paymentMethod" required>
                            <option value="">Select Payment Method</option>
                            <option value="GCash">GCash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Shipping Address</label>
                        <input type="text" id="shippingAddress" placeholder="Enter your shipping address" required />
                    </div>

                    <div class="form-field">
                        <label>Contact Number</label>
                        <input type="text" id="contactNumber" placeholder="Enter your contact number" required />
                    </div>

                    <button class="btn btn-primary" onclick="placeOrder()" style="width:100%;padding:0.9rem;font-size:1rem;">
                        <i class="fa-solid fa-check"></i> Place Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ORDER DETAILS MODAL ============ -->
    <div class="order-details-modal-backdrop" id="orderDetailsModal">
        <div class="order-details-modal">
            <div class="order-details-modal-header">
                <h2>Order Details</h2>
                <button type="button" class="order-details-modal-close" onclick="closeOrderDetailsModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="order-details-modal-body">
                <!-- Order Info -->
                <div class="order-details-info">
                    <div class="order-details-row">
                        <span class="order-details-label">Order #</span>
                        <span class="order-details-value" id="orderDetailsNumber">-</span>
                    </div>
                    <div class="order-details-row">
                        <span class="order-details-label">Date</span>
                        <span class="order-details-value" id="orderDetailsDate">-</span>
                    </div>
                    <div class="order-details-row">
                        <span class="order-details-label">Status</span>
                        <span class="order-details-value" id="orderDetailsStatus">-</span>
                    </div>
                    <div class="order-details-row">
                        <span class="order-details-label">Payment Method</span>
                        <span class="order-details-value" id="orderDetailsPayment">-</span>
                    </div>
                </div>

                <!-- Order Items -->
                <h4 class="order-details-subheading">Items</h4>
                <div class="order-details-items" id="orderDetailsItems">
                    <!-- Items will be loaded here -->
                </div>

                <!-- Order Total -->
                <div class="order-details-total">
                    <span>Total Amount</span>
                    <span id="orderDetailsTotal">₱0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ FOOTER ============ -->
    <footer class="site-footer" id="contact">
      <img src="Contact-Design.png" alt="" class="footer-decoration" aria-hidden="true" />

      <div class="footer-grid">
        <div class="footer-brand">
          <img src="images/White-Logo.png" alt="TINDOC Acoustic Guitars" class="footer-logo" />
          <p>Quality guitars for every musician. Crafted to inspire. Built to last.</p>
          <div class="social-links">
            <a href="#" aria-label="Facebook" onclick="alert('No Function Yet')"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="#" aria-label="Instagram" onclick="alert('No Function Yet')"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" aria-label="YouTube" onclick="alert('No Function Yet')"><i class="fa-brands fa-youtube"></i></a>
            <a href="#" aria-label="TikTok" onclick="alert('No Function Yet')"><i class="fa-brands fa-tiktok"></i></a>
          </div>
        </div>

        <div class="footer-links">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="shop.php">Shop</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="accessories.php">Accessories</a></li>
            <li><a href="contact.php">Contact</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Customer Service</h4>
          <ul>
            <li><a href="#" onclick="alert('No Function Yet')">FAQs</a></li>
            <li><a href="#" onclick="alert('No Function Yet')">Shipping &amp; Delivery</a></li>
            <li><a href="#" onclick="alert('No Function Yet')">Returns &amp; Exchanges</a></li>
            <li><a href="#" onclick="alert('No Function Yet')">Warranty</a></li>
            <li><a href="#" onclick="alert('No Function Yet')">Track Order</a></li>
          </ul>
        </div>

        <div class="footer-contact">
          <h4>Contact Us</h4>
          <ul>
            <li><i class="fa-solid fa-phone"></i> <span>+63 967 643 7439</span></li>
            <li><i class="fa-regular fa-envelope"></i> <span>tindocoustic@gmail.com</span></li>
            <li><i class="fa-solid fa-location-dot"></i> <span>Bacong, Philippines</span></li>
          </ul>
        </div>
      </div>

      <hr class="footer-divider" />
      <p class="copyright">© 2026 TINDOC Acoustic Guitars. All Rights Reserved.</p>
    </footer>

    <script>
      const navToggle = document.getElementById("nav-toggle");
      const mainNav = document.getElementById("main-nav");

      navToggle.addEventListener("click", () => {
        const isOpen = mainNav.classList.toggle("is-open");
        navToggle.classList.toggle("is-active", isOpen);
        navToggle.setAttribute("aria-expanded", isOpen);
      });

      mainNav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
          mainNav.classList.remove("is-open");
          navToggle.classList.remove("is-active");
          navToggle.setAttribute("aria-expanded", "false");
        });
      });

      // ---- Account tab switching ----
      function switchTab(tabName) {
        document.querySelectorAll(".account-tab").forEach((btn) => {
          btn.classList.toggle("active", btn.dataset.tab === tabName);
        });
        document.querySelectorAll(".account-panel").forEach((panel) => {
          panel.classList.toggle("active", panel.id === `panel-${tabName}`);
        });
        window.scrollTo({ top: document.querySelector(".account-section").offsetTop - 20, behavior: "smooth" });
        
        // If switching to messages tab, mark messages as read
        if (tabName === 'messages') {
          <?php if ($unread_count > 0): ?>
            fetch('account.php?mark_read=1')
              .then(() => {
                const messageTab = document.querySelector('.account-tab[data-tab="messages"]');
                if (messageTab) {
                  const badges = messageTab.querySelectorAll('.tab-count');
                  if (badges.length > 1) {
                    badges[1].style.display = 'none';
                  }
                }
              })
              .catch(() => {});
          <?php endif; ?>
        }
      }

      // Deep-link support: account.php#orders opens the Orders tab directly
      const initialTab = window.location.hash.replace("#", "");
      if (initialTab && document.getElementById(`panel-${initialTab}`)) {
        switchTab(initialTab);
      }

      // ============ CHECKOUT FUNCTIONS ============
      function openCheckoutModal() {
          // Get cart items via AJAX
          fetch('api/get-cart-items.php')
              .then(res => res.json())
              .then(data => {
                  if (data.success && data.items.length > 0) {
                      let html = '';
                      let total = 0;
                      
                      data.items.forEach(item => {
                          const itemTotal = item.price * item.quantity;
                          total += itemTotal;
                          html += `
                              <div class="checkout-item">
                                  <img src="${item.image_path || 'images/placeholder.png'}" alt="${item.name}" />
                                  <div class="checkout-item-info">
                                      <p class="checkout-item-name">${item.name}</p>
                                      ${item.variant_label ? `<p class="checkout-item-variant">${item.variant_label}</p>` : ''}
                                      <p class="checkout-item-qty">Qty: ${item.quantity}</p>
                                  </div>
                                  <p class="checkout-item-price">₱${itemTotal.toFixed(0)}</p>
                              </div>
                          `;
                      });
                      
                      document.getElementById('checkoutItems').innerHTML = html;
                      document.getElementById('checkoutTotal').textContent = '₱' + total.toFixed(0);
                      document.getElementById('checkoutModal').classList.add('open');
                      
                      // Reset form fields when opening
                      document.getElementById('paymentMethod').value = '';
                      document.getElementById('shippingAddress').value = '';
                      document.getElementById('contactNumber').value = '';
                      document.querySelector('.checkout-form').style.display = 'block';
                      document.querySelector('.checkout-total').style.display = 'flex';
                      
                      // Reset the Place Order button
                      const btn = document.querySelector('.checkout-form .btn-primary');
                      if (btn) {
                          btn.disabled = false;
                          btn.innerHTML = '<i class="fa-solid fa-check"></i> Place Order';
                      }
                  } else {
                      alert('Your cart is empty.');
                  }
              })
              .catch(() => alert('Could not load cart items.'));
      }

      function closeCheckoutModal() {
          document.getElementById('checkoutModal').classList.remove('open');
          // Reset form fields when closing
          document.getElementById('paymentMethod').value = '';
          document.getElementById('shippingAddress').value = '';
          document.getElementById('contactNumber').value = '';
          document.querySelector('.checkout-form').style.display = 'block';
          document.querySelector('.checkout-total').style.display = 'flex';
          
          // Reset the Place Order button
          const btn = document.querySelector('.checkout-form .btn-primary');
          if (btn) {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-check"></i> Place Order';
          }
      }

      function placeOrder() {
          const paymentMethod = document.getElementById('paymentMethod').value;
          const shippingAddress = document.getElementById('shippingAddress').value.trim();
          const contactNumber = document.getElementById('contactNumber').value.trim();
          
          if (!paymentMethod) {
              alert('Please select a payment method.');
              return;
          }
          if (!shippingAddress) {
              alert('Please enter your shipping address.');
              return;
          }
          if (!contactNumber) {
              alert('Please enter your contact number.');
              return;
          }
          
          // Disable the button to prevent double submission
          const btn = document.querySelector('.checkout-form .btn-primary');
          btn.disabled = true;
          btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
          
          fetch('api/place-order.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                  payment_method: paymentMethod,
                  shipping_address: shippingAddress,
                  contact_number: contactNumber
              })
          })
          .then(res => res.json())
          .then(result => {
              if (result.success) {
                  // Show success message
                  document.getElementById('checkoutItems').innerHTML = `
                      <div class="order-success">
                          <i class="fa-solid fa-circle-check" style="font-size:3rem;color:#226a44;"></i>
                          <h3>Order Placed Successfully!</h3>
                          <p>Order #: <strong>${result.order_number}</strong></p>
                          <p>Thank you for your purchase. You will receive a confirmation email shortly.</p>
                          <button class="btn btn-primary" onclick="closeCheckoutModal(); window.location.href='account.php#orders';" style="margin-top:1rem;">
                              View My Orders
                          </button>
                      </div>
                  `;
                  document.querySelector('.checkout-form').style.display = 'none';
                  document.querySelector('.checkout-total').style.display = 'none';
                  
                  // Refresh page after a delay to update cart and orders
                  setTimeout(() => {
                      window.location.reload();
                  }, 2000);
              } else {
                  alert(result.message || 'Failed to place order. Please try again.');
                  btn.disabled = false;
                  btn.innerHTML = '<i class="fa-solid fa-check"></i> Place Order';
              }
          })
          .catch(() => {
              alert('Connection error. Please try again.');
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-check"></i> Place Order';
          });
      }

      // Click outside modal to close
      document.getElementById('checkoutModal').addEventListener('click', function(e) {
          if (e.target === this) {
              closeCheckoutModal();
          }
      });

      // ============ ORDER DETAILS FUNCTIONS ============
      function openOrderDetails(orderId) {
          // Get order details via AJAX
          fetch('api/get-order-details.php?order_id=' + orderId)
              .then(res => res.json())
              .then(data => {
                  if (data.success) {
                      // Populate order info
                      document.getElementById('orderDetailsNumber').textContent = '#' + data.order.order_number;
                      document.getElementById('orderDetailsDate').textContent = data.order.order_date;
                      document.getElementById('orderDetailsStatus').textContent = data.order.status;
                      document.getElementById('orderDetailsPayment').textContent = data.order.payment_method;
                      
                      // Populate items
                      let itemsHtml = '';
                      data.order.items.forEach(item => {
                          const itemTotal = item.unit_price * item.quantity;
                          itemsHtml += `
                              <div class="order-details-item">
                                  <img src="${item.image_path || 'images/placeholder.png'}" alt="${item.name}" />
                                  <div class="order-details-item-info">
                                      <p class="order-details-item-name">${item.name}</p>
                                      ${item.variant_label ? `<p class="order-details-item-variant">${item.variant_label}</p>` : ''}
                                      <p class="order-details-item-qty">Qty: ${item.quantity} × ${formatPrice(item.unit_price)}</p>
                                  </div>
                                  <p class="order-details-item-price">${formatPrice(itemTotal)}</p>
                              </div>
                          `;
                      });
                      document.getElementById('orderDetailsItems').innerHTML = itemsHtml;
                      
                      // Populate total
                      document.getElementById('orderDetailsTotal').textContent = formatPrice(data.order.total);
                      
                      // Open modal
                      document.getElementById('orderDetailsModal').classList.add('open');
                  } else {
                      alert('Could not load order details.');
                  }
              })
              .catch(() => alert('Connection error. Please try again.'));
      }

      function closeOrderDetailsModal() {
          document.getElementById('orderDetailsModal').classList.remove('open');
      }

      // Click outside modal to close
      document.getElementById('orderDetailsModal').addEventListener('click', function(e) {
          if (e.target === this) {
              closeOrderDetailsModal();
          }
      });

      // Format price helper
      function formatPrice(amount) {
          return '₱' + Number(amount).toFixed(0);
      }
    </script>
  </body>
</html>