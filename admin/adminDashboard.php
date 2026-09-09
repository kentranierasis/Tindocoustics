<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Adjust this path/filename if your getConnection() function lives
// somewhere else (e.g. ../includes/db.php).
require_once __DIR__ . '/../config.php';

$pdo = getConnection();
$dbError = null;

/* ==========================================================================
   HELPERS - KEEP ONLY DASHBOARD-SPECIFIC FUNCTIONS
   peso(), timeAgo(), initialsFromName() are now in config.php
   ========================================================================== */

// Returns ['dir' => 'up'|'down'|'flat', 'text' => '12% from last week']
function pctChange($current, $previous) {
    if ($previous == 0) {
        if ($current > 0) return ['dir' => 'up', 'text' => 'New this week'];
        return ['dir' => 'flat', 'text' => 'No change from last week'];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $dir = $pct >= 0 ? 'up' : 'down';
    return ['dir' => $dir, 'text' => number_format(abs($pct), 0) . '% from last week'];
}

function changeIcon($dir) {
    if ($dir === 'up')   return 'fa-arrow-up';
    if ($dir === 'down') return 'fa-arrow-down';
    return 'fa-minus';
}

/* ==========================================================================
   DATA — everything below is wrapped so a DB hiccup shows a banner
   instead of a blank white page.
   ========================================================================== */
$totalOrders = $totalCustomers = $totalProducts = 0;
$totalSales = 0.0;
$ordersChange = $customersChange = $productsChange = $salesChange = ['dir' => 'flat', 'text' => 'No data yet'];
$chartLabels = $chartValues = [];
$polylinePoints = $polygonPoints = '';
$recentOrders = $topProducts = $recentMessages = [];
$adminName = 'Admin';

try {
    /* ---------- stat card totals ---------- */
    $totalOrders    = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $totalProducts  = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalSales     = (float)$pdo->query(
        "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'"
    )->fetchColumn();

    /* ---------- week-over-week comparisons ---------- */
    $weekCountSql = function (string $table, string $dateCol): array {
        global $pdo;
        $sql = "SELECT
                    SUM(CASE WHEN $dateCol >= NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS this_week,
                    SUM(CASE WHEN $dateCol >= NOW() - INTERVAL 14 DAY
                              AND $dateCol <  NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS last_week
                FROM $table";
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return [(int)($row['this_week'] ?? 0), (int)($row['last_week'] ?? 0)];
    };

    // FIXED: orders table uses 'order_date', customers and products use 'created_at'
    [$ordersThisWeek, $ordersLastWeek]       = $weekCountSql('orders', 'order_date');
    [$customersThisWeek, $customersLastWeek] = $weekCountSql('customers', 'created_at');
    [$productsThisWeek, $productsLastWeek]   = $weekCountSql('products', 'created_at');

    $salesRow = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN order_date >= NOW() - INTERVAL 7 DAY THEN total ELSE 0 END), 0) AS this_week,
            COALESCE(SUM(CASE WHEN order_date >= NOW() - INTERVAL 14 DAY
                          AND order_date <  NOW() - INTERVAL 7 DAY THEN total ELSE 0 END), 0) AS last_week
         FROM orders WHERE status != 'cancelled'"
    )->fetch(PDO::FETCH_ASSOC);
    $salesThisWeek = (float)$salesRow['this_week'];
    $salesLastWeek = (float)$salesRow['last_week'];

    $ordersChange    = pctChange($ordersThisWeek, $ordersLastWeek);
    $customersChange = pctChange($customersThisWeek, $customersLastWeek);
    $productsChange  = pctChange($productsThisWeek, $productsLastWeek);
    $salesChange     = pctChange($salesThisWeek, $salesLastWeek);

    /* ---------- sales overview: last 7 days ---------- */
    $dailySales = [];
    for ($i = 6; $i >= 0; $i--) {
        $dailySales[date('Y-m-d', strtotime("-$i day"))] = 0.0;
    }

    // FIXED: Using order_date instead of created_at
    $stmt = $pdo->query(
        "SELECT DATE(order_date) AS d, SUM(total) AS total
         FROM orders
         WHERE order_date >= CURDATE() - INTERVAL 6 DAY
           AND status != 'cancelled'
         GROUP BY DATE(order_date)"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($dailySales[$row['d']])) {
            $dailySales[$row['d']] = (float)$row['total'];
        }
    }

    foreach ($dailySales as $date => $total) {
        $chartLabels[] = date('M j', strtotime($date));
        $chartValues[] = $total;
    }

    // Map the 7 values onto the existing 40..700 / 20..220 SVG viewBox.
    $plotLeft = 40; $plotRight = 700; $plotTop = 20; $plotBottom = 220;
    $chartMax = max($chartValues);
    $chartMin = min($chartValues);
    $range    = ($chartMax - $chartMin) ?: 1;
    $count    = count($chartValues);

    $points = [];
    foreach ($chartValues as $i => $val) {
        $x = $plotLeft + ($i * (($plotRight - $plotLeft) / max($count - 1, 1)));
        $y = $chartMax == $chartMin
            ? ($plotBottom - $plotTop) / 2 + $plotTop  // flat line if every day is equal (incl. all zero)
            : $plotBottom - ((($val - $chartMin) / $range) * ($plotBottom - $plotTop));
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $polylinePoints = implode(' ', $points);
    $polygonPoints  = $polylinePoints . " {$plotRight},{$plotBottom} {$plotLeft},{$plotBottom}";

    /* ---------- recent orders ---------- */
    $recentOrders = $pdo->query(
        "SELECT o.order_number, c.full_name, o.total, o.status, o.order_date
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         ORDER BY o.order_date DESC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ---------- top selling products ---------- */
    $topProducts = $pdo->query(
        "SELECT p.name, p.image_path, SUM(oi.quantity) AS units_sold,
                SUM(oi.quantity * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         GROUP BY oi.product_id
         ORDER BY units_sold DESC
         LIMIT 4"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ---------- recent messages ---------- */
    $recentMessages = $pdo->query(
        "SELECT sender_name, message, created_at
         FROM messages
         WHERE sender_type = 'customer'
         ORDER BY created_at DESC
         LIMIT 4"
    )->fetchAll(PDO::FETCH_ASSOC);

    /* ---------- admin greeting ---------- */
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $adminName = $row['full_name'];
    }
} catch (PDOException $e) {
    $dbError = "Couldn't load live data: " . $e->getMessage();
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard — TINDOC Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
      integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />

    <!-- Shared color/font variables from the main site -->
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="admdashboardstyle.css?v=1" />
  </head>
  <body>
    <div class="admin-layout">
      <!-- ============ SIDEBAR ============ -->
      <aside class="admin-sidebar">
        <img
          src="../images/Admin Navbar Background.png"
          alt=""
          class="admin-sidebar-bg"
          aria-hidden="true"
        />

        <div class="admin-sidebar-content">
          <div class="admin-sidebar-logo">
            <img src="../images/White-Logo.png" alt="TINDOC Acoustic Guitars" />
          </div>

          <nav class="admin-nav">
            <a href="adminDashboard.php" class="admin-nav-item active">
              <i class="fa-solid fa-house"></i>
              <span>Dashboard</span>
            </a>
            <a href="admproducts.php" class="admin-nav-item">
              <i class="fa-solid fa-guitar"></i>
              <span>Products</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admorder.php" class="admin-nav-item">
              <i class="fa-solid fa-bag-shopping"></i>
              <span>Orders</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admcustomers.php" class="admin-nav-item">
              <i class="fa-solid fa-users"></i>
              <span>Customers</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
            <a href="admmessages.php" class="admin-nav-item">
              <i class="fa-regular fa-envelope"></i>
              <span>Messages</span>
              <i class="fa-solid fa-chevron-right admin-nav-chevron"></i>
            </a>
          </nav>

          <blockquote class="admin-sidebar-quote">
            "Better Guitars<br />for a Brighter<br />Tomorrow."
          </blockquote>
        </div>
      </aside>

      <!-- ============ MAIN CONTENT ============ -->
      <main class="admin-main">
        <!-- Top bar -->
        <div class="admin-topbar">
          <div></div>
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
        <div class="admin-db-error">
          <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($dbError) ?>
        </div>
        <?php endif; ?>

        <!-- Welcome banner -->
        <section class="admin-banner">
          <div class="admin-banner-text">
            <p class="eyebrow">Admin Dashboard</p>
            <h1><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($adminName) ?></h1>
            <p>Here's what's happening with your store today.</p>
          </div>
          <img src="../images/MOckup.png" alt="" class="admin-banner-bg" aria-hidden="true" />
          <div class="admin-banner-date">
            <span>Today</span>
            <strong><?= date('M j, Y') ?></strong>
          </div>
        </section>

        <!-- Stat cards -->
        <section class="admin-stats">
          <div class="admin-stat-card">
            <div class="admin-stat-icon"><i class="fa-solid fa-bag-shopping"></i></div>
            <div class="admin-stat-body">
              <p class="admin-stat-label">Total Orders</p>
              <p class="admin-stat-value"><?= number_format($totalOrders) ?></p>
              <p class="admin-stat-change <?= $ordersChange['dir'] ?>">
                <i class="fa-solid <?= changeIcon($ordersChange['dir']) ?>"></i> <?= htmlspecialchars($ordersChange['text']) ?>
              </p>
            </div>
          </div>

          <div class="admin-stat-card">
            <div class="admin-stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="admin-stat-body">
              <p class="admin-stat-label">Total Customers</p>
              <p class="admin-stat-value"><?= number_format($totalCustomers) ?></p>
              <p class="admin-stat-change <?= $customersChange['dir'] ?>">
                <i class="fa-solid <?= changeIcon($customersChange['dir']) ?>"></i> <?= htmlspecialchars($customersChange['text']) ?>
              </p>
            </div>
          </div>

          <div class="admin-stat-card">
            <div class="admin-stat-icon"><i class="fa-solid fa-guitar"></i></div>
            <div class="admin-stat-body">
              <p class="admin-stat-label">Total Products</p>
              <p class="admin-stat-value"><?= number_format($totalProducts) ?></p>
              <p class="admin-stat-change <?= $productsChange['dir'] ?>">
                <i class="fa-solid <?= changeIcon($productsChange['dir']) ?>"></i> <?= htmlspecialchars($productsChange['text']) ?>
              </p>
            </div>
          </div>

          <div class="admin-stat-card">
            <div class="admin-stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="admin-stat-body">
              <p class="admin-stat-label">Total Sales</p>
              <p class="admin-stat-value"><?= peso($totalSales) ?></p>
              <p class="admin-stat-change <?= $salesChange['dir'] ?>">
                <i class="fa-solid <?= changeIcon($salesChange['dir']) ?>"></i> <?= htmlspecialchars($salesChange['text']) ?>
              </p>
            </div>
          </div>
        </section>

        <!-- Sales overview + Recent orders -->
        <section class="admin-row">
          <div class="admin-panel admin-chart-panel">
            <div class="admin-panel-header">
              <h2>Sales Overview</h2>
            </div>

            <?php if (count($chartValues) > 0): ?>
            <svg viewBox="0 0 720 260" class="admin-chart" preserveAspectRatio="none">
              <line x1="40" y1="20" x2="40" y2="220" stroke="var(--color-border)" stroke-width="1" />
              <line x1="40" y1="220" x2="700" y2="220" stroke="var(--color-border)" stroke-width="1" />
              <line x1="40" y1="20" x2="700" y2="20" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="4 4" />
              <line x1="40" y1="70" x2="700" y2="70" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="4 4" />
              <line x1="40" y1="120" x2="700" y2="120" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="4 4" />
              <line x1="40" y1="170" x2="700" y2="170" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="4 4" />

              <polygon points="<?= htmlspecialchars($polygonPoints) ?>" fill="var(--color-accent-light)" opacity="0.35" />
              <polyline points="<?= htmlspecialchars($polylinePoints) ?>" fill="none" stroke="var(--color-brown)" stroke-width="2.5" />

              <?php foreach (explode(' ', $polylinePoints) as $pt): [$cx, $cy] = explode(',', $pt); ?>
                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="4" fill="var(--color-brown)" />
              <?php endforeach; ?>
            </svg>

            <div class="admin-chart-x-labels">
              <?php foreach ($chartLabels as $label): ?>
                <span><?= htmlspecialchars($label) ?></span>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
              <p class="admin-empty-state">No sales in the last 7 days yet.</p>
            <?php endif; ?>
          </div>

          <div class="admin-panel admin-orders-panel">
            <div class="admin-panel-header">
              <h2>Recent Orders</h2>
              <a href="admorder.php">View All</a>
            </div>

            <?php if (count($recentOrders) > 0): ?>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td>#<?= htmlspecialchars($order['order_number']) ?></td>
                  <td><?= htmlspecialchars($order['full_name']) ?></td>
                  <td><?= peso($order['total']) ?></td>
                  <td><span class="status-badge <?= htmlspecialchars($order['status']) ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span></td>
                  <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php else: ?>
              <p class="admin-empty-state">No orders yet.</p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Top products + Recent messages -->
        <section class="admin-row">
          <div class="admin-panel">
            <div class="admin-panel-header">
              <h2>Top Selling Products</h2>
              <a href="admproducts.php">View All</a>
            </div>

            <?php if (count($topProducts) > 0): ?>
            <ul class="admin-product-list">
              <?php foreach ($topProducts as $p): ?>
              <li>
                <!-- Avatar with initials instead of image -->
                <div class="product-avatar" style="width:48px;height:48px;border-radius:50%;background:var(--color-brown);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                  <?= htmlspecialchars(initialsFromName($p['name'])) ?>
                </div>
                <div class="admin-product-info">
                  <p class="admin-product-name"><?= htmlspecialchars($p['name']) ?></p>
                  <p class="admin-product-sold"><?= (int)$p['units_sold'] ?> sold</p>
                </div>
                <span class="admin-product-price"><?= peso($p['revenue']) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
              <p class="admin-empty-state">No sales recorded yet.</p>
            <?php endif; ?>
          </div>

          <div class="admin-panel">
            <div class="admin-panel-header">
              <h2>Recent Messages</h2>
              <a href="admmessages.php">View All</a>
            </div>

            <?php if (count($recentMessages) > 0): ?>
            <ul class="admin-message-list">
              <?php foreach ($recentMessages as $m): ?>
              <li>
                <span class="admin-avatar-sm"><?= htmlspecialchars(initialsFromName($m['sender_name'])) ?></span>
                <div class="admin-message-body">
                  <p class="admin-message-name"><?= htmlspecialchars($m['sender_name']) ?></p>
                  <p class="admin-message-preview"><?= htmlspecialchars($m['message']) ?></p>
                </div>
                <span class="admin-message-time"><?= htmlspecialchars(timeAgo($m['created_at'])) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php else: ?>
              <p class="admin-empty-state">No messages yet.</p>
            <?php endif; ?>
          </div>
        </section>
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
        if (menu && profile && !profile.contains(event.target)) {
          menu.classList.remove('open');
        }
      });
    </script>
  </body>
</html>