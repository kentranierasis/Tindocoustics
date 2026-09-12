<?php
require_once 'config.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TINDOC Acoustic Guitars</title>

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
  </head>
  <body>
    <!-- ============ HEADER / NAV ============ -->
    <header class="site-header" id="home">
      <img src="images/Nav-Design.png" alt="" class="nav-design" aria-hidden="true" />
      <div class="nav-container">
        <a href="#home" class="logo">
          <img src="images/Main-Logo.png" alt="TINDOC Acoustic Guitars" />
        </a>

        <nav class="main-nav" id="main-nav">
          <ul>
            <li>
              <a href="#home" class="active">HOME</a>
            </li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="about.php">ABOUT</a></li>
            <li>
              <a href="accessories.php">ACCESSORIES</a>
            </li>
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

    <!-- ============ HERO ============ -->
    <section class="hero">
      <div class="hero-slider">
        <div class="slider-track">
          <img
            src="images/Home.png"
            alt="Acoustic guitar resting against a wooden wall"
            id="slide-1"
          />
          <img src="images/pic2.png" alt="" id="slide-2" />
          <img src="images/HD-Acoustic-Guitar-Wallpaper.jpg" alt="" id="slide-3" />
        </div>
        <div class="slider-nav">
          <a href="#slide-1" aria-label="Show slide 1"></a>
          <a href="#slide-2" aria-label="Show slide 2"></a>
          <a href="#slide-3" aria-label="Show slide 3"></a>
        </div>
      </div>

      <img
        src="images/Home-Design.png"
        alt=""
        class="hero-decoration"
        aria-hidden="true"
      />

      <div class="hero-content">
        <h1>Made to Play,<span>Build to Last.</span></h1>
        <p class="hero-text">
          Quality acoustic guitars crafted for rich sound, comfort, and
          performance.
          <br />
          For every strummer, for every stage.
        </p>
        <a href="shop.php#shop-display" class="btn btn-primary">Shop Now →</a>
      </div>
    </section>

    <!-- ============ FEATURED GUITARS ============ -->
    <section class="collection-section" id="guitars-display">
      <div class="section-header">
        <p class="eyebrow">Our Collection</p>
        <h2>Featured Guitars</h2>
      </div>

      <?php
      $pdo = getConnection();
      $stmt = $pdo->query("SELECT * FROM products WHERE category = 'Acoustic Guitar' ORDER BY created_at DESC LIMIT 4");
      $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="product-grid">
          <?php foreach ($featuredProducts as $product): ?>
          <article class="product-card">
              <img src="<?= htmlspecialchars($product['image_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($product['name']) ?>" />
              <div class="product-info">
                  <h3><?= htmlspecialchars($product['name']) ?></h3>
                  <p class="product-desc"><?= htmlspecialchars($product['variant_label'] ?? '') ?></p>
                  <p class="product-price"><?= formatPrice($product['price']) ?></p>
                  
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
              </div>
          </article>
          <?php endforeach; ?>
      </div>

      <img
        src="images/Item-Design.png"
        alt=""
        class="collection-decoration collection-decoration-1"
        aria-hidden="true"
      />
      <img
        src="images/Item-Design-2.png"
        alt=""
        class="collection-decoration collection-decoration-2"
        aria-hidden="true"
      />
    </section>

    <!-- ============ ABOUT ============ -->
    <section class="about-section">
      <div class="about-intro">
        <div class="about-image">
          <img
            src="images/MOckup.png"
            alt="Close-up of a guitar headstock and tuning pegs"
          />
        </div>

        <div class="about-text">
          <p class="eyebrow">Why Choose Tindoc?</p>
          <h2>Crafted with Passion<br />Played with Heart</h2>

          <div class="feature-list">
            <div class="feature">
              <img src="images/About-Design1.png" alt="" aria-hidden="true" />
              <h3>Quality Materials</h3>
              <p>
                Carefully selected tonewoods for excellent sound and durability
              </p>
            </div>

            <div class="feature">
              <img src="images/About-Design2.png" alt="" aria-hidden="true" />
              <h3>Expert Craftmanship</h3>
              <p>
                Built by skilled hands with attention to detail in every piece
              </p>
            </div>

            <div class="feature">
              <img src="images/About-Design3.png" alt="" aria-hidden="true" />
              <h3>Rich, Balanced Sound</h3>
              <p>
                Designed to deliver warm, clear and resonant tones for every
                playing style
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="testimonial">
        <p class="eyebrow">What Our Customers Say</p>

        <button
          class="testimonial-arrow left"
          onclick="changeTestimonial(-1)"
          aria-label="Previous testimonial"
        >
          <img src="images/Arrow.png" alt="" />
        </button>

        <div class="testimonial-content">
          <img
            src="images/Quotation.png"
            alt=""
            class="quote-icon"
            aria-hidden="true"
          />
          <p id="testimonialText">
            TINDOC guitars exceeded my expectations. Great sound, smooth
            playability, and beautifully made!
          </p>
          <img src="images/Stars.png" alt="5 out of 5 stars" class="stars-img" />
          <p class="testimonial-author" id="testimonialAuthor">— Trigg M.</p>
        </div>

        <button
          class="testimonial-arrow right"
          onclick="changeTestimonial(1)"
          aria-label="Next testimonial"
        >
          <img src="images/Arrow R.png" alt="" />
        </button>

        <div class="testimonial-dots" id="testimonialDots">
          <button class="dot active" onclick="goToTestimonial(0)" aria-label="Show testimonial 1"></button>
          <button class="dot" onclick="goToTestimonial(1)" aria-label="Show testimonial 2"></button>
          <button class="dot" onclick="goToTestimonial(2)" aria-label="Show testimonial 3"></button>
        </div>

        <img
          src="images/About-Design4.png"
          alt=""
          class="testimonial-decoration"
          aria-hidden="true"
        />
      </div>
    </section>

    <!-- ============ CTA BANNER ============ -->
    <section class="cta-banner">
      <img
        src="images/Contact image.png"
        alt="Close-up of an acoustic guitar body and strings"
        class="cta-bg"
      />
      <div class="cta-content">
        <h2>Find the guitar that speaks to you.</h2>
        <a href="shop.php#shop-display"
          ><button class="btn btn-primary">Browse All Guitars →</button></a
        >
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
            ></a><a href="https://www.instagram.com/kensimpleton/"
              aria-label="Instagram"
              >
            <i class="fa-brands fa-instagram"></i
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
            <li><a href="#home">Home</a></li>
            <li><a href="shop.php">Shop</a></li>
            <li><a href="about.php">About</a></li>
            <li>
              <a href="accessories.php">Accessories</a>
            </li>
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
              <i class="fa-solid fa-phone"></i> <span>+63 967 634 7439</span>
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

      // ============ TESTIMONIAL SLIDER ============
      const testimonials = [
        {
          text: "TINDOC guitars exceeded my expectations. Great sound, smooth playability, and beautifully made!",
          author: "— Trigg M."
        },
        {
          text: "I've owned three acoustics over the years, and my TINDOC A2C is by far the easiest to play. The cutaway makes soloing effortless.",
          author: "— Marisol Vega."
        },
        {
          text: "The mahogany tone on my M1 is warm and full without being muddy. Perfect for late-night songwriting sessions.",
          author: "— Devon Achterberg."
        }
      ];

      let currentTestimonial = 0;
      const testimonialTextEl = document.getElementById("testimonialText");
      const testimonialAuthorEl = document.getElementById("testimonialAuthor");
      const testimonialDots = document.querySelectorAll("#testimonialDots .dot");

      function showTestimonial(index) {
        const content = document.querySelector(".testimonial-content");
        content.classList.add("fade-out");

        setTimeout(() => {
          testimonialTextEl.textContent = testimonials[index].text;
          testimonialAuthorEl.textContent = testimonials[index].author;
          content.classList.remove("fade-out");
        }, 150);

        testimonialDots.forEach((dot, i) => {
          dot.classList.toggle("active", i === index);
        });

        currentTestimonial = index;
      }

      function changeTestimonial(direction) {
        const total = testimonials.length;
        const nextIndex = (currentTestimonial + direction + total) % total;
        showTestimonial(nextIndex);
      }

      function goToTestimonial(index) {
        showTestimonial(index);
      }

      // ============ RUN ON PAGE LOAD ============
      document.addEventListener('DOMContentLoaded', function() {
        updateAccountIcon();
      });
    </script>
  </body>
</html>