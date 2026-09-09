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

$adminName = 'Admin';
$products = [];
$totalProducts = 0;
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$stockStatus = $_GET['stock'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// ==========================================================================
// HANDLE BULK DELETE
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $productIds = $_POST['product_ids'] ?? [];
    if (!empty($productIds)) {
        $deleted = 0;
        $failed = 0;
        foreach ($productIds as $id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $deleted++;
            } catch (PDOException $e) {
                $failed++;
            }
        }
        $flashMessage = "$deleted product(s) deleted successfully." . ($failed > 0 ? " $failed could not be deleted (may have orders)." : "");
    } else {
        $dbError = "No products selected.";
    }
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: admproducts.php' . $qs);
    exit;
}

// ----- ADD PRODUCT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $variant_label = trim($_POST['variant_label'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? 'Acoustic Guitar');
    $price = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $stock_status = $_POST['stock_status'] ?? 'in-stock';
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name) || empty($sku) || $price <= 0) {
        $dbError = "Product name, SKU, and price are required.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            if ($stmt->fetch()) {
                $dbError = "SKU already exists. Please use a unique SKU.";
            } else {
                $image_path = null;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../images/';
                    $fileName = time() . '_' . basename($_FILES['product_image']['name']);
                    $targetFile = $uploadDir . $fileName;
                    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($imageFileType, $allowedTypes)) {
                        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetFile)) {
                            $image_path = 'images/' . $fileName;
                        } else {
                            $dbError = "Failed to upload image.";
                        }
                    } else {
                        $dbError = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                    }
                }
                
                if (!$dbError) {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, variant_label, sku, category, price, stock_quantity, stock_status, image_path, description) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $variant_label, $sku, $category, $price, $stock_quantity, $stock_status, $image_path, $description]);
                    $flashMessage = "Product added successfully!";
                }
            }
        } catch (PDOException $e) {
            $dbError = "Could not add product: " . $e->getMessage();
        }
    }
    
    if (!$dbError) {
        $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: admproducts.php' . $qs);
        exit;
    }
}

// ----- EDIT PRODUCT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $variant_label = trim($_POST['variant_label'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? 'Acoustic Guitar');
    $price = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $stock_status = $_POST['stock_status'] ?? 'in-stock';
    $description = trim($_POST['description'] ?? '');
    $current_image = $_POST['current_image'] ?? '';
    
    if ($product_id <= 0 || empty($name) || empty($sku) || $price <= 0) {
        $dbError = "Product name, SKU, and price are required.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $stmt->execute([$sku, $product_id]);
            if ($stmt->fetch()) {
                $dbError = "SKU already exists. Please use a unique SKU.";
            } else {
                $image_path = $current_image;
                
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../images/';
                    $fileName = time() . '_' . basename($_FILES['product_image']['name']);
                    $targetFile = $uploadDir . $fileName;
                    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($imageFileType, $allowedTypes)) {
                        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetFile)) {
                            if ($current_image && file_exists(__DIR__ . '/../' . $current_image)) {
                                unlink(__DIR__ . '/../' . $current_image);
                            }
                            $image_path = 'images/' . $fileName;
                        } else {
                            $dbError = "Failed to upload image.";
                        }
                    } else {
                        $dbError = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                    }
                }
                
                if (!$dbError) {
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET name = ?, variant_label = ?, sku = ?, category = ?, price = ?, 
                            stock_quantity = ?, stock_status = ?, image_path = ?, description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $variant_label, $sku, $category, $price, $stock_quantity, $stock_status, $image_path, $description, $product_id]);
                    $flashMessage = "Product updated successfully!";
                }
            }
        } catch (PDOException $e) {
            $dbError = "Could not update product: " . $e->getMessage();
        }
    }
    
    if (!$dbError) {
        $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: admproducts.php' . $qs);
        exit;
    }
}

// ----- DELETE SINGLE PRODUCT -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            
            if ($product && $product['image_path'] && file_exists(__DIR__ . '/../' . $product['image_path'])) {
                unlink(__DIR__ . '/../' . $product['image_path']);
            }
            
            $flashMessage = "Product deleted successfully!";
        } catch (PDOException $e) {
            $dbError = str_contains($e->getMessage(), 'foreign key constraint') 
                ? "Cannot delete this product because it has orders associated with it." 
                : "Could not delete product: " . $e->getMessage();
        }
    }
    
    if (!$dbError) {
        $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: admproducts.php' . $qs);
        exit;
    }
}

// ==========================================================================
// GET PRODUCTS
// ==========================================================================
try {
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $adminName = $row['full_name'];
    }

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(name LIKE ? OR sku LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($category !== '' && $category !== 'All Categories') {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if ($stockStatus !== '' && $stockStatus !== 'All Stock Status') {
        $map = ['In Stock' => 'in-stock', 'Low Stock' => 'low-stock', 'Out of Stock' => 'out-of-stock'];
        $where[] = "stock_status = ?";
        $params[] = $map[$stockStatus] ?? 'in-stock';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $orderSql = match ($sort) {
        'price_low'  => 'price ASC',
        'price_high' => 'price DESC',
        'stock'      => 'stock_quantity DESC',
        default      => 'created_at DESC',
    };

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereSql");
    $countStmt->execute($params);
    $totalProducts = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT * FROM products $whereSql ORDER BY $orderSql LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = "Couldn't load products: " . $e->getMessage();
}

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$showingStart = $totalProducts === 0 ? 0 : (($page - 1) * $perPage) + 1;
$showingEnd = min($page * $perPage, $totalProducts);

function qs($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Products — TINDOC Admin</title>

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
    <link rel="stylesheet" href="admproductstyle.css?v=2" />
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
            <a href="admproducts.php" class="admin-nav-item active">
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
            <h1>Products</h1>
            <p class="admin-page-subtitle">Manage your product listings, inventory, and details.</p>
          </div>
          <div style="display:flex;gap:0.75rem;">
            <button class="btn admin-add-btn" onclick="openAddModal()">
              <i class="fa-solid fa-plus"></i> Add New Product
            </button>
          </div>
        </div>

        <!-- ADD PRODUCT MODAL -->
        <div class="product-modal-backdrop" id="addModal">
          <div class="product-modal-content">
            <div class="product-modal-header">
              <h3>Add New Product</h3>
              <button type="button" aria-label="Close" onclick="closeModal('addModal')">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>

            <form method="post" enctype="multipart/form-data" class="product-edit-form">
              <input type="hidden" name="action" value="add" />
              
              <div class="form-row">
                <div class="form-field">
                  <label>Product Name *</label>
                  <input type="text" name="name" required />
                </div>
                <div class="form-field">
                  <label>Variant Label</label>
                  <input type="text" name="variant_label" placeholder="e.g. Natural Spruce" />
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label>SKU *</label>
                  <input type="text" name="sku" required placeholder="e.g. TIND-A1-001" />
                </div>
                <div class="form-field">
                  <label>Category</label>
                  <select name="category">
                    <option value="Acoustic Guitar">Acoustic Guitar</option>
                    <option value="Accessories">Accessories</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label>Price (₱) *</label>
                  <input type="number" name="price" step="0.01" min="0" required />
                </div>
                <div class="form-field">
                  <label>Stock Quantity</label>
                  <input type="number" name="stock_quantity" min="0" value="0" />
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label>Stock Status</label>
                  <select name="stock_status">
                    <option value="in-stock">In Stock</option>
                    <option value="low-stock">Low Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                  </select>
                </div>
                <div class="form-field">
                  <label>Product Image</label>
                  <input type="file" name="product_image" accept="image/*" />
                </div>
              </div>

              <div class="form-field">
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Product description..."></textarea>
              </div>

              <div class="product-modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn admin-add-btn">Add Product</button>
              </div>
            </form>
          </div>
        </div>

        <form class="admin-filters" method="get">
          <div class="admin-search admin-table-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products by name or SKU..." />
          </div>

          <select name="category" onchange="this.form.submit()">
            <option <?= $category === '' ? 'selected' : '' ?>>All Categories</option>
            <option <?= $category === 'Acoustic Guitar' ? 'selected' : '' ?>>Acoustic Guitar</option>
            <option <?= $category === 'Accessories' ? 'selected' : '' ?>>Accessories</option>
          </select>

          <select name="stock" onchange="this.form.submit()">
            <option <?= $stockStatus === '' ? 'selected' : '' ?>>All Stock Status</option>
            <option <?= $stockStatus === 'In Stock' ? 'selected' : '' ?>>In Stock</option>
            <option <?= $stockStatus === 'Low Stock' ? 'selected' : '' ?>>Low Stock</option>
            <option <?= $stockStatus === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
          </select>

          <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort by: Newest</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Sort by: Price (Low to High)</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Sort by: Price (High to Low)</option>
            <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>Sort by: Stock</option>
          </select>
        </form>

        <!-- Bulk Actions -->
        <form method="post" id="bulkActionForm" onsubmit="return confirmBulkDelete()">
          <input type="hidden" name="action" value="bulk_delete" />
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;padding:0.75rem 1rem;background:var(--color-tan-2);border-radius:var(--radius-sm);">
            <span style="font-size:0.85rem;font-weight:600;color:var(--color-brown);">
              <span id="selectedCount">0</span> items selected
            </span>
            <button type="submit" class="btn" style="background:#a13a3a;color:white;padding:0.5rem 1.2rem;font-size:0.8rem;" id="deleteSelectedBtn" disabled>
              <i class="fa-solid fa-trash"></i> Delete Selected
            </button>
          </div>
        </form>

        <div class="admin-panel admin-products-panel">
          <?php if (count($products) > 0): ?>
          <table class="admin-table admin-products-table">
            <thead>
              <tr>
                <th class="col-checkbox">
                  <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes()" />
                </th>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
              <tr>
                <td class="col-checkbox">
                  <input type="checkbox" class="row-checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" onchange="updateSelectedCount()" />
                </td>
                <td class="col-product" style="display:flex;align-items:center;gap:0.9rem;">
                  <div class="product-avatar" style="width:48px;height:48px;border-radius:50%;background:var(--color-brown);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                    <?= htmlspecialchars(initialsFromName($p['name'])) ?>
                  </div>
                  <div>
                    <p class="admin-product-name"><?= htmlspecialchars($p['name']) ?></p>
                    <p class="admin-product-sold"><?= htmlspecialchars($p['variant_label'] ?? '') ?></p>
                  </div>
                </td>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['category']) ?></td>
                <td><?= peso($p['price']) ?></td>
                <td><?= (int)$p['stock_quantity'] ?></td>
                <td><span class="status-badge <?= getStockClass($p['stock_status']) ?>"><?= getStockLabel($p['stock_status']) ?></span></td>
                <td class="col-actions">
                  <button aria-label="View" onclick="openViewModal(<?= (int)$p['id'] ?>)"><i class="fa-regular fa-eye"></i></button>
                  <button aria-label="Edit" onclick="openEditModal(<?= (int)$p['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                  <form method="post" class="inline-action-form" 
                        onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?>? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>" />
                    <button type="submit" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
                  </form>
                </td>
              </tr>

              <!-- VIEW MODAL -->
              <div class="product-modal-backdrop" id="viewModal<?= (int)$p['id'] ?>">
                <div class="product-modal-content">
                  <div class="product-modal-header">
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <button type="button" aria-label="Close" onclick="closeModal('viewModal<?= (int)$p['id'] ?>')">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>
                  <div class="product-view-body">
                    <div class="product-avatar-large" style="width:120px;height:120px;border-radius:50%;background:var(--color-brown);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:2.5rem;margin:0 auto;">
                      <?= htmlspecialchars(initialsFromName($p['name'])) ?>
                    </div>
                    <div class="product-view-info">
                      <p><strong>SKU:</strong> <?= htmlspecialchars($p['sku']) ?></p>
                      <p><strong>Variant:</strong> <?= htmlspecialchars($p['variant_label'] ?? '—') ?></p>
                      <p><strong>Category:</strong> <?= htmlspecialchars($p['category']) ?></p>
                      <p><strong>Price:</strong> <?= peso($p['price']) ?></p>
                      <p><strong>Stock:</strong> <?= (int)$p['stock_quantity'] ?></p>
                      <p><strong>Status:</strong> <span class="status-badge <?= getStockClass($p['stock_status']) ?>"><?= getStockLabel($p['stock_status']) ?></span></p>
                      <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($p['description'] ?? 'No description')) ?></p>
                      <p><strong>Added:</strong> <?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- EDIT MODAL -->
              <div class="product-modal-backdrop" id="editModal<?= (int)$p['id'] ?>">
                <div class="product-modal-content">
                  <div class="product-modal-header">
                    <h3>Edit Product</h3>
                    <button type="button" aria-label="Close" onclick="closeModal('editModal<?= (int)$p['id'] ?>')">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>

                  <form method="post" enctype="multipart/form-data" class="product-edit-form">
                    <input type="hidden" name="action" value="edit" />
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>" />
                    <input type="hidden" name="current_image" value="<?= htmlspecialchars($p['image_path'] ?? '') ?>" />
                    
                    <div class="form-row">
                      <div class="form-field">
                        <label>Product Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required />
                      </div>
                      <div class="form-field">
                        <label>Variant Label</label>
                        <input type="text" name="variant_label" value="<?= htmlspecialchars($p['variant_label'] ?? '') ?>" placeholder="e.g. Natural Spruce" />
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-field">
                        <label>SKU *</label>
                        <input type="text" name="sku" value="<?= htmlspecialchars($p['sku']) ?>" required />
                      </div>
                      <div class="form-field">
                        <label>Category</label>
                        <select name="category">
                          <option value="Acoustic Guitar" <?= $p['category'] === 'Acoustic Guitar' ? 'selected' : '' ?>>Acoustic Guitar</option>
                          <option value="Accessories" <?= $p['category'] === 'Accessories' ? 'selected' : '' ?>>Accessories</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-field">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?= $p['price'] ?>" required />
                      </div>
                      <div class="form-field">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock_quantity" min="0" value="<?= (int)$p['stock_quantity'] ?>" />
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-field">
                        <label>Stock Status</label>
                        <select name="stock_status">
                          <option value="in-stock" <?= $p['stock_status'] === 'in-stock' ? 'selected' : '' ?>>In Stock</option>
                          <option value="low-stock" <?= $p['stock_status'] === 'low-stock' ? 'selected' : '' ?>>Low Stock</option>
                          <option value="out-of-stock" <?= $p['stock_status'] === 'out-of-stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                      </div>
                      <div class="form-field">
                        <label>Product Image</label>
                        <?php if ($p['image_path'] && file_exists(__DIR__ . '/../' . $p['image_path'])): ?>
                          <div style="margin-bottom:0.5rem;">
                            <img src="../<?= htmlspecialchars($p['image_path']) ?>" style="max-width:80px;max-height:80px;border-radius:4px;" />
                          </div>
                        <?php endif; ?>
                        <input type="file" name="product_image" accept="image/*" />
                        <small style="color:#8a7c6c;font-size:0.75rem;">Leave empty to keep current image</small>
                      </div>
                    </div>

                    <div class="form-field">
                      <label>Description</label>
                      <textarea name="description" rows="4" placeholder="Product description..."><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                    </div>

                    <div class="product-modal-actions">
                      <button type="button" class="btn-cancel" onclick="closeModal('editModal<?= (int)$p['id'] ?>')">Cancel</button>
                      <button type="submit" class="btn admin-add-btn">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="admin-pagination">
            <span>Showing <?= $showingStart ?>&ndash;<?= $showingEnd ?> of <?= $totalProducts ?> products</span>
            <div class="admin-pagination-controls">
              <a href="<?= qs(['page' => max(1, $page - 1)]) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= qs(['page' => $i]) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a href="<?= qs(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
          </div>
          <?php else: ?>
            <p class="admin-empty-state">No products found.</p>
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

      function openAddModal() {
        document.getElementById('addModal').classList.add('open');
      }

      function openViewModal(id) {
        document.getElementById('viewModal' + id).classList.add('open');
      }

      function openEditModal(id) {
        document.getElementById('editModal' + id).classList.add('open');
      }

      function closeModal(id) {
        document.getElementById(id).classList.remove('open');
      }

      document.querySelectorAll('.product-modal-backdrop').forEach(function (backdrop) {
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
        return confirm('Delete ' + checkboxes.length + ' selected product(s)? This cannot be undone.');
      }

      // Initialize count on page load
      document.addEventListener('DOMContentLoaded', function() {
        updateSelectedCount();
      });
    </script>
  </body>
</html>