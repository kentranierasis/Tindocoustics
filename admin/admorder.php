<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

require_once __DIR__ . '/../config.php';
$pdo = getConnection();
$dbError = null;
$flashMessage = null;

// REMOVED: peso() - now in config.php

/* ==========================================================================
   ACTIONS — ship / delete (POST only)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    try {
        if ($_POST['action'] === 'ship') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'shipped' WHERE id = ? AND status IN ('processing', 'pending')");
            $stmt->execute([$orderId]);
            $flashMessage = 'Order marked as shipped.';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $flashMessage = 'Order deleted.';
        }
    } catch (PDOException $e) {
        $dbError = "Action failed: " . $e->getMessage();
    }
    // Redirect to avoid resubmission on refresh, keep existing filters/page.
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: admorder.php' . $qs);
    exit;
}

$adminName = 'Admin';
$orders = [];
$totalOrders = 0;
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$orderItemsByOrder = [];

try {
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $adminName = $row['full_name'];

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(o.order_number LIKE ? OR c.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status !== '' && $status !== 'All Statuses') {
        $where[] = "o.status = ?";
        $params[] = strtolower($status);
    }
    if ($payment !== '' && $payment !== 'All Payment Methods') {
        $where[] = "o.payment_method = ?";
        $params[] = $payment;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $orderSql = match ($sort) {
        'oldest'     => 'o.order_date ASC',
        'total_high' => 'o.total DESC',
        'total_low'  => 'o.total ASC',
        default      => 'o.order_date DESC',
    };

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN customers c ON c.id = o.customer_id $whereSql");
    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        "SELECT o.*, c.full_name, c.email
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         $whereSql
         ORDER BY $orderSql
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pull line items for every order shown on this page (small N, cheap query).
    if (count($orders) > 0) {
        $orderIds = array_column($orders, 'id');
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT oi.order_id, p.name, p.image_path, oi.quantity, oi.unit_price
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($in)"
        );
        $stmt->execute($orderIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderItemsByOrder[$item['order_id']][] = $item;
        }
    }
} catch (PDOException $e) {
    $dbError = "Couldn't load orders: " . $e->getMessage();
}

$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$showingStart = $totalOrders === 0 ? 0 : (($page - 1) * $perPage) + 1;
$showingEnd = min($page * $perPage, $totalOrders);

function qs($overrides = []) {
    return '?' . http_build_query(array_merge($_GET, $overrides));
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Orders — TINDOC Admin</title>

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
    <link rel="stylesheet" href="admorderstyle.css?v=4" />
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
            <a href="admorder.php" class="admin-nav-item active">
              <i class="fa-solid fa-bag-shopping"></i><span>Orders</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admcustomers.php" class="admin-nav-item">
              <i class="fa-solid fa-users"></i><span>Customers</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admmessages.php" class="admin-nav-item">
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

        <?php if ($flashMessage): ?>
        <div class="admin-flash"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashMessage) ?></div>
        <?php endif; ?>

        <div class="admin-page-header">
          <div>
            <p class="eyebrow">Admin Panel</p>
            <h1>Orders</h1>
            <p class="admin-page-subtitle">Manage and track all customer orders.</p>
          </div>
        </div>

        <form class="admin-filters" method="get">
          <div class="admin-search admin-table-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by order # or customer name..." />
          </div>

          <select name="status" onchange="this.form.submit()">
            <option <?= $status === '' ? 'selected' : '' ?>>All Statuses</option>
            <option <?= $status === 'Processing' ? 'selected' : '' ?>>Processing</option>
            <option <?= $status === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
            <option <?= $status === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
            <option <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
          </select>

          <select name="payment" onchange="this.form.submit()">
            <option <?= $payment === '' ? 'selected' : '' ?>>All Payment Methods</option>
            <option <?= $payment === 'GCash' ? 'selected' : '' ?>>GCash</option>
            <option <?= $payment === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
            <option <?= $payment === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
          </select>

          <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort by: Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Sort by: Oldest First</option>
            <option value="total_high" <?= $sort === 'total_high' ? 'selected' : '' ?>>Sort by: Total (High to Low)</option>
            <option value="total_low" <?= $sort === 'total_low' ? 'selected' : '' ?>>Sort by: Total (Low to High)</option>
          </select>
        </form>

        <div class="admin-panel admin-orders-table-panel">
          <?php if (count($orders) > 0): ?>
          <table class="admin-table admin-orders-table">
            <thead>
              <tr>
                <th class="col-checkbox"><input type="checkbox" /></th>
                <th>Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
              <?php
                $items = $orderItemsByOrder[$o['id']] ?? [];
                $canShip = in_array($o['status'], ['processing', 'pending'], true);
              ?>
              <tr>
                <td class="col-checkbox"><input type="checkbox" /></td>
                <td class="order-number">#<?= htmlspecialchars($o['order_number']) ?></td>
                <td class="col-customer">
                  <p class="customer-name"><?= htmlspecialchars($o['full_name']) ?></p>
                  <p class="customer-email"><?= htmlspecialchars($o['email']) ?></p>
                </td>

                <td class="col-date"><?= date('M j, Y', strtotime($o['order_date'])) ?> &bull; <?= date('g:i A', strtotime($o['order_date'])) ?></td>

                <td class="col-total"><?= peso($o['total']) ?></td>
                <td class="col-payment"><?= htmlspecialchars($o['payment_method']) ?></td>
                <td><span class="status-badge <?= htmlspecialchars($o['status']) ?>"><?= ucfirst(htmlspecialchars($o['status'])) ?></span></td>
                <td class="col-actions">
                  <button type="button" aria-label="View order details" onclick="openOrderModal(<?= (int)$o['id'] ?>)">
                    <i class="fa-regular fa-eye"></i>
                  </button>

                  <form method="post" class="inline-action-form">
                    <input type="hidden" name="action" value="ship" />
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>" />
                    <button
                      type="submit"
                      aria-label="Mark as shipped"
                      <?= $canShip ? '' : 'disabled title="Already shipped or delivered"' ?>
                      onclick="return confirm('Mark order #<?= htmlspecialchars($o['order_number']) ?> as shipped?')"
                    >
                      <i class="fa-solid fa-truck"></i>
                    </button>
                  </form>

                  <div class="row-menu">
                    <button type="button" aria-label="More actions" onclick="toggleRowMenu(event, <?= (int)$o['id'] ?>)">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="row-menu-dropdown" id="rowMenu<?= (int)$o['id'] ?>">
                      <form method="post" onsubmit="return confirm('Delete order #<?= htmlspecialchars($o['order_number']) ?>? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>" />
                        <button type="submit" class="row-menu-item row-menu-delete">
                          <i class="fa-regular fa-trash-can"></i> Delete
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Order details modal (hidden until opened) -->
              <div class="order-modal-backdrop" id="orderModal<?= (int)$o['id'] ?>">
                <div class="order-modal">
                  <div class="order-modal-header">
                    <h3>Order #<?= htmlspecialchars($o['order_number']) ?></h3>
                    <button type="button" aria-label="Close" onclick="closeOrderModal(<?= (int)$o['id'] ?>)">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>

                  <div class="order-modal-meta">
                    <div>
                      <span class="order-modal-label">Customer</span>
                      <span><?= htmlspecialchars($o['full_name']) ?> (<?= htmlspecialchars($o['email']) ?>)</span>
                    </div>
                    <div>
                      <span class="order-modal-label">Date</span>
                      <span><?= date('M j, Y g:i A', strtotime($o['order_date'])) ?></span>
                    </div>
                    <div>
                      <span class="order-modal-label">Payment</span>
                      <span><?= htmlspecialchars($o['payment_method']) ?></span>
                    </div>
                    <div>
                      <span class="order-modal-label">Status</span>
                      <span class="status-badge <?= htmlspecialchars($o['status']) ?>"><?= ucfirst(htmlspecialchars($o['status'])) ?></span>
                    </div>
                  </div>

                  <div class="order-modal-items">
                    <?php if (count($items) > 0): ?>
                      <?php foreach ($items as $item): ?>
                      <div class="order-modal-item">
                        <img src="<?= htmlspecialchars($item['image_path'] ?: '../images/placeholder.png') ?>" alt="<?= htmlspecialchars($item['name']) ?>" />
                        <div class="order-modal-item-info">
                          <p class="order-modal-item-name"><?= htmlspecialchars($item['name']) ?></p>
                          <p class="order-modal-item-qty">Qty: <?= (int)$item['quantity'] ?> &times; <?= peso($item['unit_price']) ?></p>
                        </div>
                        <span class="order-modal-item-total"><?= peso($item['quantity'] * $item['unit_price']) ?></span>
                      </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="admin-empty-state">No items recorded for this order.</p>
                    <?php endif; ?>
                  </div>

                  <div class="order-modal-total">
                    <span>Order Total</span>
                    <span><?= peso($o['total']) ?></span>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="admin-pagination">
            <span>Showing <?= $showingStart ?>&ndash;<?= $showingEnd ?> of <?= $totalOrders ?> orders</span>
            <div class="admin-pagination-controls">
              <a href="<?= qs(['page' => max(1, $page - 1)]) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= qs(['page' => $i]) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a href="<?= qs(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
          </div>
          <?php else: ?>
            <p class="admin-empty-state">No orders found.</p>
          <?php endif; ?>
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

        // Close any open row menu when clicking outside it
        document.querySelectorAll('.row-menu-dropdown.open').forEach(function (dd) {
          if (!dd.parentElement.contains(event.target)) dd.classList.remove('open');
        });
      });

      function toggleRowMenu(event, orderId) {
        event.stopPropagation();
        var dd = document.getElementById('rowMenu' + orderId);
        document.querySelectorAll('.row-menu-dropdown.open').forEach(function (other) {
          if (other !== dd) other.classList.remove('open');
        });
        dd.classList.toggle('open');
      }

      function openOrderModal(orderId) {
        document.getElementById('orderModal' + orderId).classList.add('open');
      }
      function closeOrderModal(orderId) {
        document.getElementById('orderModal' + orderId).classList.remove('open');
      }
      // Close modal when clicking the dark backdrop itself
      document.querySelectorAll('.order-modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
          if (e.target === backdrop) backdrop.classList.remove('open');
        });
      });
    </script>
  </body>
</html>