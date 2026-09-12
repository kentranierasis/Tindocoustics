<?php
require_once 'config.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Accessories — TINDOC Acoustic Guitars</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons -->
    <link rel="stylesheet" href="css/fontawesome/all.min.css" />

    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/accessoriesstyle.css?v=3" />
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
            <li><a href="accessories.php" class="active">ACCESSORIES</a></li>
            <li>
              <a href="contact.php">CONTACT</a>
            </li>
          </ul>
        </nav>

        <div class="nav-icons">
          <button
            class="icon-btn"
            aria-label="Account"
            id="accountBtn"
          >
            <i class="fa-regular fa-user"></i>
          </button>
          <button
            class="icon-btn"
            aria-label="Cart"
            onclick="handleCartClick()"
          >
            <i class="fa-solid fa-cart-shopping"></i>
          </button>
          <button
            class="nav-toggle"
            id="nav-toggle"
            aria-label="Toggle menu"
            aria-expanded="false"
            aria-controls="main-nav"
          >
            <span></span><span></span><span></span>
          </button>
        </div>
      </div>
    </header>

    <!-- ============ ACCESSORIES HERO ============ -->
    <section class="hero">
      <img
        src="images/Accessories BG.png"
        alt="Guitar strap, clip-on tuner, capo, picks, and cleaning cloth laid out on a wooden table"
        class="hero-static-image"
      />
      <div class="hero-content">
        <h1>Accessories<span>Elevate Every Performance.</span></h1>
        <p class="hero-text">
          Premium accessories designed to protect, enhance, and complement
          your guitar journey.
        </p>
      </div>
    </section>

    <!-- ============ ACCESSORIES GRID ============ -->
    <section class="accessories-section" id="accessories-display">
      <div class="section-header">
        <p class="eyebrow">Our Collection</p>
        <h2>All Accessories</h2>
      </div>

      <div class="accessories-layout">
        <aside class="category-sidebar">
          <h4>Categories</h4>
          <ul>
            <li><a href="#" class="active" data-filter="all">All Accessories</a></li>
            <li><a href="#" data-filter="straps">Straps</a></li>
            <li><a href="#" data-filter="tuners">Tuners</a></li>
            <li><a href="#" data-filter="capos">Capos</a></li>
            <li><a href="#" data-filter="strings">Strings</a></li>
            <li><a href="#" data-filter="picks">Picks</a></li>
            <li><a href="#" data-filter="care">Care &amp; Maintenance</a></li>
            <li><a href="#" data-filter="cases">Cases &amp; Gig Bags</a></li>
          </ul>
        </aside>

        <div class="accessories-main">
          <?php
          $pdo = getConnection();
          $stmt = $pdo->query("SELECT * FROM products WHERE category = 'Accessories' ORDER BY created_at DESC");
          $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);

          $categoryMap = [
              'Premium Leather' => 'straps',
              'Accurate & Easy-to-Use' => 'tuners',
              'Zinc Alloy Build' => 'capos',
              '80/20 Bronze' => 'strings',
              'Celluloid, Medium' => 'picks',
              'Ultra-Soft Microfiber' => 'care',
              'Cleans & Protects' => 'care',
              'Padded Protection' => 'cases',
          ];
          ?>

          <div class="product-grid accessories-grid">
            <?php foreach ($accessories as $product): 
                $catValue = $categoryMap[$product['variant_label']] ?? 'other';
            ?>
            <article class="product-card" data-category="<?= $catValue ?>">
              <div class="product-media">
                <?php if ($product['stock_status'] === 'low-stock'): ?>
                    <span class="badge-new" style="background:#d4a017;">Low Stock</span>
                <?php elseif ($product['stock_status'] === 'out-of-stock'): ?>
                    <span class="badge-new" style="background:#a13a3a;">Out of Stock</span>
                <?php else: ?>
                    <span class="badge-new" style="background:#226a44;">In Stock</span>
                <?php endif; ?>
                <img src="<?= htmlspecialchars($product['image_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($product['name']) ?>" />
              </div>
              <div class="product-info">
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p class="product-desc"><?= htmlspecialchars($product['variant_label'] ?? '') ?></p>
                <p class="product-price"><?= formatPrice($product['price']) ?></p>
                <p class="product-stock" style="font-size:0.8rem;color:#6b5d4f;margin:0 0 0.75rem;">
                    <?php if ($product['stock_status'] === 'out-of-stock'): ?>
                        <span style="color:#a13a3a;font-weight:600;">Out of Stock</span>
                    <?php else: ?>
                        Stock: <?= (int)$product['stock_quantity'] ?> left
                    <?php endif; ?>
                </p>

                <div class="product-actions">
                  <?php if ($product['stock_status'] === 'out-of-stock'): ?>
                    <button class="btn btn-dark" style="background:#999;cursor:not-allowed;" disabled>Out of Stock</button>
                  <?php else: ?>
                    <button class="btn btn-dark" onclick="openProductModal({
                        id: <?= (int)$product['id'] ?>,
                        name: '<?= addslashes($product['name']) ?>',
                        desc: '<?= addslashes($product['variant_label'] ?? '') ?>',
                        price: '<?= formatPrice($product['price']) ?>',
                        image: '<?= htmlspecialchars($product['image_path'] ?? 'images/placeholder.png') ?>',
                        details: '<?= addslashes($product['description'] ?? '') ?>'
                    })">View Details</button>
                  <?php endif; ?>
                  <button class="wishlist-btn" aria-label="Add to wishlist" onclick="toggleWishlist(<?= (int)$product['id'] ?>, this)">
                    <i class="fa-regular fa-heart"></i>
                  </button>
                </div>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ DETAILS BANNER ============ -->
    <section class="details-banner">
      <div class="details-image">
        <img src="images/MOckup.png" alt="Close-up of a guitar headstock and tuning pegs" />
      </div>
      <div class="details-content">
        <h2>Small Details.<br />Lasting Difference.</h2>
        <p>Quality accessories make every performance feel just right.</p>
        <a href="#accessories-display" class="btn btn-primary shop-accessories-btn">
          Shop All Accessories → </a>
      </div>
    </section>

    <!-- ============ FEATURES BAR ============ -->
    <section class="accessories-features">
      <div class="accessories-feature">
        <i class="fa-solid fa-shield-halved"></i>
        <h3>Quality You Can Trust</h3>
        <p>Carefully selected accessories built for durability and reliability.</p>
      </div>
      <div class="accessories-feature">
        <i class="fa-solid fa-guitar"></i>
        <h3>Perfect Compatibility</h3>
        <p>Designed to work seamlessly with your acoustic guitar.</p>
      </div>
      <div class="accessories-feature">
        <i class="fa-solid fa-award"></i>
        <h3>Trusted by Musicians</h3>
        <p>Used and recommended by players at every stage of their journey.</p>
      </div>
      <div class="accessories-feature">
        <i class="fa-solid fa-box"></i>
        <h3>Complete Your Setup</h3>
        <p>Everything you need to protect, maintain, and enhance your sound.</p>
      </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer class="site-footer" id="contact">
      <img
        src="images/Contact-Design.png"
        alt=""
        class="footer-decoration"
        aria-hidden="true"
      />

      <div class="footer-grid">
        <div class="footer-brand">
          <img
            src="images/White-Logo.png"
            alt="TINDOC Acoustic Guitars"
            class="footer-logo"
          />
          <p>
            Quality guitars for every musician. Crafted to inspire. Built to
            last.
          </p>
          <div class="social-links">
            <a href="https://www.facebook.com/kennhaku" aria-label="Facebook" 
              ><i class="fa-brands fa-facebook-f"></i
            ></a>
            <a
              href="https://www.instagram.com/kensimpleton/"
              aria-label="Instagram"
              ><i class="fa-brands fa-instagram"></i
            ></a>
            <a href="https://www.youtube.com/@TindocKentRanier" aria-label="YouTube" 
              ><i class="fa-brands fa-youtube"></i
            ></a>
            <a href="https://www.tiktok.com/@kentranier_" aria-label="TikTok" 
              ><i class="fa-brands fa-tiktok"></i
            ></a>
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
            <li><a href="contact.php#faq-section">FAQs</a></li>
            <li>
              <a href="contact.php#faq-section"
                >Shipping &amp; Delivery</a
              >
            </li>
            <li>
              <a href="contact.php#faq-section"
                >Returns &amp; Exchanges</a
              >
            </li>
          </ul>
        </div>

        <div class="footer-contact">
          <h4>Contact Us</h4>
          <ul>
            <li>
              <i class="fa-solid fa-phone"></i> <span>+63 967 643 7439</span>
            </li>
            <li>
              <i class="fa-regular fa-envelope"></i>
              <span>tindocoustic@gmail.com</span>
            </li>
            <li>
              <i class="fa-solid fa-location-dot"></i>
              <span>Bacong, Philippines</span>
            </li>
          </ul>
        </div>
      </div>

      <hr class="footer-divider" />
      <p class="copyright">
        © 2026 TINDOC Acoustic Guitars. All Rights Reserved.
      </p>
    </footer>

    <!-- ============ PRODUCT DETAILS MODAL ============ -->
    <div class="product-modal-backdrop" id="productModal">
      <div class="product-modal">
        <button type="button" class="product-modal-close" aria-label="Close" onclick="closeProductModal()">
          <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="product-modal-image">
          <img id="productModalImage" src="" alt="" />
        </div>

        <div class="product-modal-body">
          <h3 id="productModalName"></h3>
          <p class="product-modal-desc" id="productModalDesc"></p>
          <p class="product-modal-price" id="productModalPrice"></p>
          <p class="product-modal-details" id="productModalDetails"></p>

          <div class="quantity-picker">
            <span class="quantity-label">Quantity</span>
            <div class="quantity-controls">
              <button type="button" class="qty-btn" aria-label="Decrease quantity" onclick="changeQty(-1)">
                <i class="fa-solid fa-minus"></i>
              </button>
              <input
                type="number"
                id="productModalQty"
                class="qty-input"
                value="1"
                min="1"
                max="99"
                inputmode="numeric"
                aria-label="Quantity"
                onchange="clampQty()"
              />
              <button type="button" class="qty-btn" aria-label="Increase quantity" onclick="changeQty(1)">
                <i class="fa-solid fa-plus"></i>
              </button>
            </div>
          </div>

          <div class="product-modal-actions">
            <button type="button" class="btn btn-dark" onclick="handleAddToCart()">Add to Cart</button>
            <button type="button" class="btn btn-primary" onclick="handleBuyNow()">Buy Now</button>
          </div>
        </div>
      </div>
    </div>

    <?php include 'login.php'; ?>

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

      // ============ USER ICON BEHAVIOR ============
      function updateAccountIcon() {
          fetch('api/check-session.php')
              .then(res => res.json())
              .then(data => {
                  const accountBtn = document.getElementById('accountBtn');
                  if (accountBtn) {
                      if (data.logged_in) {
                          accountBtn.innerHTML = '<i class="fa-solid fa-user"></i>';
                          accountBtn.style.color = '#226a44';
                          
                          if (data.user_type === 'admin') {
                              accountBtn.onclick = function() {
                                  window.location.href = 'admin/adminDashboard.php';
                              };
                              accountBtn.title = 'Admin Dashboard';
                          } else {
                              accountBtn.onclick = function() {
                                  window.location.href = 'account.php';
                              };
                              accountBtn.title = 'My Account';
                          }
                      } else {
                          accountBtn.innerHTML = '<i class="fa-regular fa-user"></i>';
                          accountBtn.style.color = '';
                          accountBtn.onclick = function() {
                              openLoginModal();
                          };
                          accountBtn.title = 'Login / Register';
                      }
                  }
              })
              .catch(() => {
                  const accountBtn = document.getElementById('accountBtn');
                  if (accountBtn) {
                      accountBtn.onclick = function() {
                          openLoginModal();
                      };
                  }
              });
      }

      // ============ CART BUTTON BEHAVIOR ============
      function handleCartClick() {
          fetch('api/check-session.php')
              .then(res => res.json())
              .then(data => {
                  if (data.logged_in && data.user_type === 'customer') {
                      window.location.href = 'account.php#cart';
                  } else if (data.logged_in && data.user_type === 'admin') {
                      window.location.href = 'admin/admorder.php';
                  } else {
                      openLoginModal('login', function() {
                          window.location.href = 'account.php#cart';
                      });
                  }
              })
              .catch(() => {
                  openLoginModal('login', function() {
                      window.location.href = 'account.php#cart';
                  });
              });
      }

      // ============ WISHLIST FUNCTIONS ============
      function toggleWishlist(productId, button) {
        fetch('api/check-session.php')
            .then(res => res.json())
            .then(data => {
                if (!data.logged_in) {
                    openLoginModal('login', function() {
                        toggleWishlist(productId, button);
                    });
                    return;
                }
                
                fetch('api/add-to-wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        if (result.action === 'added') {
                            button.innerHTML = '<i class="fa-solid fa-heart"></i>';
                            button.style.color = '#a12c2c';
                            button.title = 'Remove from wishlist';
                        } else {
                            button.innerHTML = '<i class="fa-regular fa-heart"></i>';
                            button.style.color = '';
                            button.title = 'Add to wishlist';
                        }
                    } else {
                        alert(result.message || 'Failed to update wishlist.');
                    }
                })
                .catch(() => alert('Connection error. Please try again.'));
            })
            .catch(() => {
                openLoginModal();
            });
      }

      function loadWishlistState(productId, button) {
        fetch('api/check-wishlist.php?product_id=' + productId)
            .then(res => res.json())
            .then(data => {
                if (data.in_wishlist) {
                    button.innerHTML = '<i class="fa-solid fa-heart"></i>';
                    button.style.color = '#a12c2c';
                    button.title = 'Remove from wishlist';
                } else {
                    button.innerHTML = '<i class="fa-regular fa-heart"></i>';
                    button.style.color = '';
                    button.title = 'Add to wishlist';
                }
            })
            .catch(() => {});
      }

      // ============ PRODUCT DETAILS MODAL ============
      function openProductModal(product) {
        document.getElementById("productModalImage").src = product.image;
        document.getElementById("productModalImage").alt = product.name;
        document.getElementById("productModalName").textContent = product.name;
        document.getElementById("productModalDesc").textContent = product.desc;
        document.getElementById("productModalPrice").textContent = product.price;
        document.getElementById("productModalDetails").textContent = product.details;
        document.getElementById("productModalQty").value = 1;
        
        let idField = document.getElementById('productModalId');
        if (!idField) {
          idField = document.createElement('input');
          idField.type = 'hidden';
          idField.id = 'productModalId';
          document.getElementById('productModal').appendChild(idField);
        }
        idField.value = product.id || 1;
        
        document.getElementById("productModal").classList.add("open");
      }

      function closeProductModal() {
        document.getElementById("productModal").classList.remove("open");
      }

      document.getElementById("productModal").addEventListener("click", (e) => {
        if (e.target.id === "productModal") closeProductModal();
      });

      // ============ QUANTITY PICKER ============
      function changeQty(delta) {
        const qtyInput = document.getElementById("productModalQty");
        let value = parseInt(qtyInput.value, 10) || 1;
        value = Math.min(99, Math.max(1, value + delta));
        qtyInput.value = value;
      }

      function clampQty() {
        const qtyInput = document.getElementById("productModalQty");
        let value = parseInt(qtyInput.value, 10) || 1;
        value = Math.min(99, Math.max(1, value));
        qtyInput.value = value;
      }

      // ============ CART FUNCTIONS WITH LOGIN CHECK ============
      function requireLogin(callback) {
        return fetch('api/check-session.php')
          .then(res => res.json())
          .then(data => {
            if (data.logged_in) {
              if (typeof callback === 'function') {
                callback();
              }
              return true;
            } else {
              openLoginModal('login', callback);
              return false;
            }
          })
          .catch(() => {
            openLoginModal('login', callback);
            return false;
          });
      }

      function handleAddToCart() {
        const qty = document.getElementById('productModalQty').value;
        const productName = document.getElementById('productModalName').textContent;
        
        function doAddToCart() {
          const productId = document.getElementById('productModalId')?.value || 1;
          const qty = document.getElementById('productModalQty').value;
          
          fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: qty })
          })
          .then(res => res.json())
          .then(result => {
            if (result.success) {
              alert(result.message);
              closeProductModal();
            } else {
              alert(result.message || 'Failed to add to cart.');
            }
          })
          .catch(() => alert('Connection error. Please try again.'));
        }
        
        requireLogin(doAddToCart);
      }

      function handleBuyNow() {
        const qty = document.getElementById('productModalQty').value;
        const productName = document.getElementById('productModalName').textContent;
        
        function doBuyNow() {
          const productId = document.getElementById('productModalId')?.value || 1;
          const qty = document.getElementById('productModalQty').value;
          
          fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: qty })
          })
          .then(res => res.json())
          .then(result => {
            if (result.success) {
              closeProductModal();
              window.location.href = 'account.php#cart';
            } else {
              alert(result.message || 'Failed to proceed to checkout.');
            }
          })
          .catch(() => alert('Connection error. Please try again.'));
        }
        
        requireLogin(doBuyNow);
      }

      // ============ CATEGORY FILTER ============
      const categoryLinks = document.querySelectorAll(".category-sidebar a[data-filter]");
      const productCards = document.querySelectorAll(".accessories-grid .product-card");

      categoryLinks.forEach((link) => {
        link.addEventListener("click", (e) => {
          e.preventDefault();
          categoryLinks.forEach((l) => l.classList.remove("active"));
          link.classList.add("active");
          const filter = link.dataset.filter;
          productCards.forEach((card) => {
            const matches = filter === "all" || card.dataset.category === filter;
            card.style.display = matches ? "" : "none";
          });
        });
      });

      // ============ RUN ON PAGE LOAD ============
      document.addEventListener('DOMContentLoaded', function() {
        updateAccountIcon();
        
        document.querySelectorAll('.wishlist-btn').forEach(function(btn) {
            const onclickAttr = btn.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/toggleWishlist\((\d+)/);
                if (match) {
                    const productId = match[1];
                    loadWishlistState(productId, btn);
                }
            }
        });
      });
    </script>
  </body>
</html>