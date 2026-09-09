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

/* ==========================================================================
   ACTION — add new customer (POST only)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $status   = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $dbError = "Name, email, and password are required.";
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO customers (full_name, email, password_hash, phone, status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$fullName, $email, $passwordHash, $phone, $status]);
            $flashMessage = 'Customer added.';
        } catch (PDOException $e) {
            $dbError = str_contains($e->getMessage(), 'Duplicate entry')
                ? "That email is already used by another customer."
                : "Couldn't add customer: " . $e->getMessage();
        }
    }

    if (!$dbError) {
        $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: admcustomers.php' . $qs);
        exit;
    }
}

/* ==========================================================================
   ACTIONS — edit / delete (POST only)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['customer_id'])) {
    $customerId = (int)$_POST['customer_id'];

    if ($_POST['action'] === 'edit') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $status   = $_POST['status'] ?? 'active';

        if ($fullName === '' || $email === '') {
            $dbError = "Name and email are required.";
        } else {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE customers SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?"
                );
                $stmt->execute([$fullName, $email, $phone, $status, $customerId]);
                $flashMessage = 'Customer updated.';
            } catch (PDOException $e) {
                $dbError = str_contains($e->getMessage(), 'Duplicate entry')
                    ? "That email is already used by another customer."
                    : "Couldn't update customer: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $flashMessage = 'Customer deleted.';
        } catch (PDOException $e) {
            $dbError = str_contains($e->getMessage(), 'foreign key constraint')
                ? "Can't delete this customer — they still have orders on file."
                : "Couldn't delete customer: " . $e->getMessage();
        }
    }

    if (!$dbError) {
        $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: admcustomers.php' . $qs);
        exit;
    }
}

// ----- BULK DELETE CUSTOMERS -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_customers') {
    $customerIds = $_POST['customer_ids'] ?? [];
    if (!empty($customerIds)) {
        $deleted = 0;
        $failed = 0;
        foreach ($customerIds as $id) {
            try {
                // Check if customer has orders
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
                $stmt->execute([$id]);
                $hasOrders = (int)$stmt->fetchColumn();
                if ($hasOrders > 0) {
                    $failed++;
                    continue;
                }
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$id]);
                $deleted++;
            } catch (PDOException $e) {
                $failed++;
            }
        }
        $flashMessage = "$deleted customer(s) deleted successfully." . ($failed > 0 ? " $failed could not be deleted (have orders)." : "");
    } else {
        $dbError = "No customers selected.";
    }
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: admcustomers.php' . $qs);
    exit;
}

$adminName = 'Admin';
$customers = [];
$totalCustomers = 0;
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$customerOrdersById = [];

try {
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $adminName = $row['full_name'];

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($status !== '' && $status !== 'All Customers') {
        $where[] = "c.status = ?";
        $params[] = strtolower($status);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $orderSql = match ($sort) {
        'oldest'      => 'c.created_at ASC',
        'most_orders' => 'total_orders DESC',
        'spent'       => 'lifetime_spent DESC',
        default       => 'c.created_at DESC',
    };

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
    $countStmt->execute($params);
    $totalCustomers = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        "SELECT c.*,
                COUNT(o.id) AS total_orders,
                COALESCE(SUM(o.total), 0) AS lifetime_spent
         FROM customers c
         LEFT JOIN orders o ON o.customer_id = c.id
         $whereSql
         GROUP BY c.id
         ORDER BY $orderSql
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($customers) > 0) {
        $ids = array_column($customers, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT customer_id, order_number, total, status, order_date
             FROM orders
             WHERE customer_id IN ($in)
             ORDER BY order_date DESC"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $customerOrdersById[$o['customer_id']][] = $o;
        }
    }
} catch (PDOException $e) {
    $dbError = "Couldn't load customers: " . $e->getMessage();
}

$totalPages = max(1, (int)ceil($totalCustomers / $perPage));
$showingStart = $totalCustomers === 0 ? 0 : (($page - 1) * $perPage) + 1;
$showingEnd = min($page * $perPage, $totalCustomers);

function qs($overrides = []) {
    return '?' . http_build_query(array_merge($_GET, $overrides));
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customers — TINDOC Admin</title>

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
    <link rel="stylesheet" href="admcustomerstyle.css?v=3" />
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
            <a href="admcustomers.php" class="admin-nav-item active">
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
            <h1>Customers</h1>
            <p class="admin-page-subtitle">View and manage your customer accounts.</p>
          </div>
          <button type="button" class="btn admin-add-btn" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Add New Customer
          </button>
        </div>

        <!-- ADD CUSTOMER MODAL -->
        <div class="cust-modal-backdrop" id="addModal">
          <div class="cust-modal">
            <div class="cust-modal-header">
              <h3>Add New Customer</h3>
              <button type="button" aria-label="Close" onclick="closeModal('addModal')">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>

            <form method="post" class="cust-edit-form">
              <input type="hidden" name="action" value="add" />

              <div class="form-field">
                <label>Full Name</label>
                <input type="text" name="full_name" required />
              </div>

              <div class="form-field">
                <label>Email Address</label>
                <input type="email" name="email" required />
              </div>

              <div class="form-field">
                <label>Phone Number</label>
                <input type="text" name="phone" />
              </div>

              <div class="form-field">
                <label>Password</label>
                <input type="password" name="password" required minlength="6" />
              </div>

              <div class="form-field">
                <label>Status</label>
                <select name="status">
                  <option value="active" selected>Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>

              <div class="cust-modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn admin-add-btn">Add Customer</button>
              </div>
            </form>
          </div>
        </div>

        <form class="admin-filters" method="get">
          <div class="admin-search admin-table-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, or phone..." />
          </div>

          <select name="status" onchange="this.form.submit()">
            <option <?= $status === '' ? 'selected' : '' ?>>All Customers</option>
            <option <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
            <option <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>

          <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort by: Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Sort by: Oldest First</option>
            <option value="most_orders" <?= $sort === 'most_orders' ? 'selected' : '' ?>>Sort by: Most Orders</option>
            <option value="spent" <?= $sort === 'spent' ? 'selected' : '' ?>>Sort by: Lifetime Spent</option>
          </select>
        </form>

        <!-- Bulk Actions -->
        <form method="post" id="bulkActionForm" onsubmit="return confirmBulkDelete()">
          <input type="hidden" name="action" value="bulk_delete_customers" />
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;padding:0.75rem 1rem;background:var(--color-tan-2);border-radius:var(--radius-sm);">
            <span style="font-size:0.85rem;font-weight:600;color:var(--color-brown);">
              <span id="selectedCount">0</span> items selected
            </span>
            <button type="submit" class="btn" style="background:#a13a3a;color:white;padding:0.5rem 1.2rem;font-size:0.8rem;" id="deleteSelectedBtn" disabled>
              <i class="fa-solid fa-trash"></i> Delete Selected
            </button>
          </div>
        </form>

        <div class="admin-panel admin-customers-table-panel">
          <?php if (count($customers) > 0): ?>
          <table class="admin-table admin-customers-table">
            <thead>
              <tr>
                <th class="col-checkbox">
                  <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes()" />
                </th>
                <th>Customer</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Total Orders</th>
                <th>Lifetime Spent</th>
                <th>Joined</th>
                <th>Status</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
              <?php $orders = $customerOrdersById[$c['id']] ?? []; ?>
              <tr>
                <td class="col-checkbox">
                  <input type="checkbox" class="row-checkbox" name="customer_ids[]" value="<?= (int)$c['id'] ?>" onchange="updateSelectedCount()" />
                </td>
                <td class="col-customer">
                  <span class="customer-avatar"><?= htmlspecialchars(initialsFromName($c['full_name'])) ?></span>
                  <span class="customer-name"><?= htmlspecialchars($c['full_name']) ?></span>
                </td>
                <td class="col-email"><?= htmlspecialchars($c['email']) ?></td>
                <td class="col-phone"><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td class="col-orders"><?= (int)$c['total_orders'] ?></td>
                <td class="col-spent"><?= peso($c['lifetime_spent']) ?></td>
                <td class="col-joined"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td><span class="status-badge <?= htmlspecialchars($c['status']) ?>"><?= ucfirst(htmlspecialchars($c['status'])) ?></span></td>
                <td class="col-actions">
                  <button type="button" aria-label="View customer" onclick="openViewModal(<?= (int)$c['id'] ?>)">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                  <button type="button" aria-label="Edit customer" onclick="openEditModal(<?= (int)$c['id'] ?>)">
                    <i class="fa-solid fa-pen"></i>
                  </button>
                  <form method="post" class="inline-action-form"
                        onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['full_name'])) ?>? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>" />
                    <button type="submit" aria-label="Delete customer">
                      <i class="fa-regular fa-trash-can"></i>
                    </button>
                  </form>
                </td>
              </tr>

              <!-- VIEW MODAL -->
              <div class="cust-modal-backdrop" id="viewModal<?= (int)$c['id'] ?>">
                <div class="cust-modal">
                  <div class="cust-modal-header">
                    <h3><?= htmlspecialchars($c['full_name']) ?></h3>
                    <button type="button" aria-label="Close" onclick="closeModal('viewModal<?= (int)$c['id'] ?>')">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>

                  <div class="cust-modal-meta">
                    <div>
                      <span class="cust-modal-label">Email</span>
                      <span><?= htmlspecialchars($c['email']) ?></span>
                    </div>
                    <div>
                      <span class="cust-modal-label">Phone</span>
                      <span><?= htmlspecialchars($c['phone'] ?: '—') ?></span>
                    </div>
                    <div>
                      <span class="cust-modal-label">Joined</span>
                      <span><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div>
                      <span class="cust-modal-label">Status</span>
                      <span class="status-badge <?= htmlspecialchars($c['status']) ?>"><?= ucfirst(htmlspecialchars($c['status'])) ?></span>
                    </div>
                  </div>

                  <h4 class="cust-modal-subheading">Order History</h4>
                  <div class="cust-modal-orders">
                    <?php if (count($orders) > 0): ?>
                      <?php foreach ($orders as $o): ?>
                      <div class="cust-modal-order-row">
                        <div>
                          <p class="cust-modal-order-number">#<?= htmlspecialchars($o['order_number']) ?></p>
                          <p class="cust-modal-order-date"><?= date('M j, Y', strtotime($o['order_date'])) ?></p>
                        </div>
                        <span class="status-badge <?= htmlspecialchars($o['status']) ?>"><?= ucfirst(htmlspecialchars($o['status'])) ?></span>
                        <span class="cust-modal-order-total"><?= peso($o['total']) ?></span>
                      </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="admin-empty-state">No orders yet.</p>
                    <?php endif; ?>
                  </div>

                  <div class="cust-modal-total">
                    <span>Lifetime Spent</span>
                    <span><?= peso($c['lifetime_spent']) ?></span>
                  </div>
                </div>
              </div>

              <!-- EDIT MODAL -->
              <div class="cust-modal-backdrop" id="editModal<?= (int)$c['id'] ?>">
                <div class="cust-modal">
                  <div class="cust-modal-header">
                    <h3>Edit Customer</h3>
                    <button type="button" aria-label="Close" onclick="closeModal('editModal<?= (int)$c['id'] ?>')">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>

                  <form method="post" class="cust-edit-form">
                    <input type="hidden" name="action" value="edit" />
                    <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>" />

                    <div class="form-field">
                      <label>Full Name</label>
                      <input type="text" name="full_name" value="<?= htmlspecialchars($c['full_name']) ?>" required />
                    </div>

                    <div class="form-field">
                      <label>Email Address</label>
                      <input type="email" name="email" value="<?= htmlspecialchars($c['email']) ?>" required />
                    </div>

                    <div class="form-field">
                      <label>Phone Number</label>
                      <input type="text" name="phone" value="<?= htmlspecialchars($c['phone'] ?? '') ?>" />
                    </div>

                    <div class="form-field">
                      <label>Status</label>
                      <select name="status">
                        <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                      </select>
                    </div>

                    <div class="cust-modal-actions">
                      <button type="button" class="btn-cancel" onclick="closeModal('editModal<?= (int)$c['id'] ?>')">Cancel</button>
                      <button type="submit" class="btn admin-add-btn">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="admin-pagination">
            <span>Showing <?= $showingStart ?>&ndash;<?= $showingEnd ?> of <?= $totalCustomers ?> customers</span>
            <div class="admin-pagination-controls">
              <a href="<?= qs(['page' => max(1, $page - 1)]) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= qs(['page' => $i]) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a href="<?= qs(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
          </div>
          <?php else: ?>
            <p class="admin-empty-state">No customers found.</p>
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
      });

      function openViewModal(id) {
        document.getElementById('viewModal' + id).classList.add('open');
      }
      function openEditModal(id) {
        document.getElementById('editModal' + id).classList.add('open');
      }
      function openAddModal() {
        document.getElementById('addModal').classList.add('open');
      }
      function closeModal(id) {
        document.getElementById(id).classList.remove('open');
      }
      document.querySelectorAll('.cust-modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
          if (e.target === backdrop) backdrop.classList.remove('open');
        });
      });

      // ============ CHECKBOX FUNCTIONS ============
      function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateSelectedCount();
      }

      function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').textContent = count;
        const deleteBtn = document.getElementById('deleteSelectedBtn');
        if (deleteBtn) {
          deleteBtn.disabled = count === 0;
        }
      }

      function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) {
          alert('Please select at least one item to delete.');
          return false;
        }
        return confirm('Delete ' + checkboxes.length + ' selected customer(s)? This cannot be undone.');
      }

      document.addEventListener('DOMContentLoaded', function() {
        updateSelectedCount();
      });
    </script>
  </body>
</html>