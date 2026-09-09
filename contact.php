<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$messageSent = false;
$messageError = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
    
    // Validate
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $messageError = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageError = "Please enter a valid email address.";
    } else {
        try {
            $pdo = getConnection();
            
            // Check if email exists in customers table
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $customer_id = $customer ? $customer['id'] : null;
            
            // If customer exists but not logged in, they're sending as guest
            // If logged in, use their customer_id
            if ($isLoggedIn && $customer) {
                $customer_id = $_SESSION['customer_id'];
            } elseif ($isLoggedIn && !$customer) {
                // Logged in but email doesn't match - use their actual ID
                $customer_id = $_SESSION['customer_id'];
            }
            
            // Insert message into database
            $stmt = $pdo->prepare("
                INSERT INTO messages (customer_id, sender_name, sender_type, message, is_read, created_at) 
                VALUES (?, ?, 'customer', ?, 0, NOW())
            ");
            $stmt->execute([$customer_id, $name, $message]);
            
            $messageSent = true;
            
        } catch (PDOException $e) {
            $messageError = "Could not send message. Please try again later.";
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contact — TINDOC Acoustic Guitars</title>

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

    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/contactstyle.css?v=3" />
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
            <li><a href="contact.php" class="active">CONTACT</a></li>
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

    <!-- ============ CONTACT HERO ============ -->
    <section class="hero">
      <img
        src="images/Contact Background V2.png"
        alt="Acoustic guitar and TINDOC leather strap on a wooden table"
        class="hero-static-image"
      />
      <div class="hero-content">
        <h1>Get in Touch<span>With Us.</span></h1>
        <p class="hero-text">
          We're here to help. Whether you have a question, need support, or
          just want to share your love for guitars — we'd love to hear from
          you.
        </p>
      </div>
    </section>

    <!-- ============ CONTACT INFO + FORM ============ -->
    <section class="contact-section" id="contact-section">
      <div class="section-header">
        <p class="eyebrow">Contact Us</p>
        <h2>We'd Love to Hear From You</h2>
      </div>

      <?php if ($messageSent): ?>
        <div class="contact-success" style="max-width:var(--container-width);margin:0 auto 2rem;background:#e4f3ea;color:#226a44;border:1px solid #c3e6d1;border-radius:4px;padding:1.25rem 1.5rem;text-align:center;font-size:1rem;">
          <i class="fa-solid fa-circle-check" style="font-size:1.2rem;margin-right:0.5rem;"></i>
          Your message has been sent successfully! We'll get back to you within 24 hours.
        </div>
      <?php endif; ?>

      <?php if ($messageError): ?>
        <div class="contact-error" style="max-width:var(--container-width);margin:0 auto 2rem;background:#fbeaea;color:#a13a3a;border:1px solid #f0caca;border-radius:4px;padding:1.25rem 1.5rem;text-align:center;font-size:1rem;">
          <i class="fa-solid fa-triangle-exclamation" style="font-size:1.2rem;margin-right:0.5rem;"></i>
          <?= htmlspecialchars($messageError) ?>
        </div>
      <?php endif; ?>

      <div class="contact-layout">
        <div class="contact-info">
          <div class="contact-item">
            <i class="fa-solid fa-location-dot"></i>
            <div>
              <h3>Our Location</h3>
              <p>Bacong, Negros Oriental<br />Philippines 6216</p>
            </div>
          </div>

          <div class="contact-item">
            <i class="fa-solid fa-phone"></i>
            <div>
              <h3>Phone</h3>
              <p>+63 967 634 7439<br />Mon - Fri, 9:00 AM - 6:00 PM</p>
            </div>
          </div>

          <div class="contact-item">
            <i class="fa-regular fa-envelope"></i>
            <div>
              <h3>Email</h3>
              <p>tindocoustic@gmail.com<br/>We'll get back to you within 24 hours.</p>
            </div>
          </div>

          <div class="contact-item">
            <i class="fa-regular fa-clock"></i>
            <div>
              <h3>Business Hours</h3>
              <p>Mon - Fri: 9:00 AM - 6:00 PM<br />Sat - Sun: Closed</p>
            </div>
          </div>
        </div>

        <div class="contact-divider" aria-hidden="true"></div>

        <div class="contact-form">
          <h3>Send Us a Message</h3>
          <p>Fill out the form below and we'll get back to you as soon as possible.</p>

          <form id="contactForm" method="post">
            <input type="hidden" name="action" value="send_message" />
            
            <input type="text" name="name" id="contactName" placeholder="Name *" required />
            <input type="email" name="email" id="contactEmail" placeholder="Email *" required />
            <select name="subject" id="contactSubject" required>
              <option value="" disabled selected>Subject *</option>
              <option value="General Inquiry">General Inquiry</option>
              <option value="Order Support">Order Support</option>
              <option value="Warranty & Repairs">Warranty &amp; Repairs</option>
              <option value="Custom Guitar">Custom Guitar</option>
              <option value="Other">Other</option>
            </select>
            <textarea name="message" id="contactMessage" placeholder="Message *" rows="5" required></textarea>
            <button type="submit" class="btn btn-dark" id="sendMessageBtn">Send Message →</button>
          </form>
        </div>
      </div>
    </section>

    <!-- ============ LOCATION BANNER ============ -->
    <section class="location-banner">
      <div class="location-content">
        <img src="images/MOckup.png" alt="Close-up of a guitar headstock and tuning pegs" />
        <div class="location-overlay">
          <h2>Visit Our<br />Location</h2>
          <p>
            We'd be happy to welcome you to our workshop and showroom in
            Dumaguete City.
          </p>
        </div>
      </div>
      <div class="location-map">
        <img src="images/Tindocoustic Map v2.png" alt="Map showing TINDOC Acoustic Guitars location in Dumaguete City" />
      </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section class="faq-section" id="faq-section">
      <div class="section-header">
        <p class="eyebrow">Frequently Asked Questions</p>
        <h2>Quick Answers</h2>
      </div>

      <div class="faq-grid">
        <button class="faq-item" data-answer="Domestic orders typically arrive within 3–5 business days. International orders can take 7–14 business days depending on the destination and customs processing.">
          <span>How long does shipping take?</span>
          <i class="fa-solid fa-plus"></i>
        </button>
        <button class="faq-item" data-answer="Yes. We accept returns and exchanges within 14 days of delivery, provided the guitar is unused and in its original packaging. Contact us first so we can arrange the return.">
          <span>Can I return or exchange my guitar?</span>
          <i class="fa-solid fa-plus"></i>
        </button>
        <button class="faq-item" data-answer="Yes, we ship internationally. Shipping costs and delivery times vary by country and are calculated at checkout.">
          <span>Do you offer international shipping?</span>
          <i class="fa-solid fa-plus"></i>
        </button>
        <button class="faq-item" data-answer="Yes! We build custom guitars to your specifications — wood, finish, and inlay options. Reach out through the contact form and we'll walk you through the process.">
          <span>Do you offer custom guitars?</span>
          <i class="fa-solid fa-plus"></i>
        </button>
      </div>
    </section>

    <!-- ============ FAQ ANSWER MODAL ============ -->
    <div class="faq-modal-backdrop" id="faq-modal-backdrop">
      <div class="faq-modal">
        <button class="faq-modal-close" id="faq-modal-close" aria-label="Close">
          <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 id="faq-modal-question"></h3>
        <p id="faq-modal-answer"></p>
      </div>
    </div>

    <!-- ============ CTA BANNER ============ -->
    <section class="cta-banner">
      <img
        src="images/Contact image.png"
        alt="Close-up of an acoustic guitar body and strings"
        class="cta-bg"
      />
      <div class="cta-content">
        <h2>Still Have Questions?</h2>
        <p class="hero-text">Feel free to reach out. We're happy to help.</p>
        <a href="#contact-section"><button class="btn btn-primary">
          Contact Us →
        </button></a>
      </div>
    </section>

    <!-- ============ FOOTER ============ -->
    <footer class="site-footer" id="contact">
      <img
        src="Contact-Design.png"
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
            <a href="#" aria-label="Facebook" onclick="alert('No Function Yet')"
              ><i class="fa-brands fa-facebook-f"></i
            ></a>
            <a
              href="#"
              aria-label="Instagram"
              onclick="alert('No Function Yet')"
              ><i class="fa-brands fa-instagram"></i
            ></a>
            <a href="#" aria-label="YouTube" onclick="alert('No Function Yet')"
              ><i class="fa-brands fa-youtube"></i
            ></a>
            <a href="#" aria-label="TikTok" onclick="alert('No Function Yet')"
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
            <li><a href="#" onclick="alert('No Function Yet')">FAQs</a></li>
            <li>
              <a href="#" onclick="alert('No Function Yet')"
                >Shipping &amp; Delivery</a
              >
            </li>
            <li>
              <a href="#" onclick="alert('No Function Yet')"
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

      // ============ CONTACT FORM - CHECK LOGIN ============
      document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        fetch('api/check-session.php')
            .then(res => res.json())
            .then(data => {
                if (data.logged_in) {
                    this.submit();
                } else {
                    openLoginModal('login', function() {
                        document.getElementById('contactForm').submit();
                    });
                }
            })
            .catch(() => {
                openLoginModal('login', function() {
                    document.getElementById('contactForm').submit();
                });
            });
      });

      // ============ FAQ MODAL ============
      const faqBackdrop = document.getElementById("faq-modal-backdrop");
      const faqQuestionEl = document.getElementById("faq-modal-question");
      const faqAnswerEl = document.getElementById("faq-modal-answer");
      const faqCloseBtn = document.getElementById("faq-modal-close");

      document.querySelectorAll(".faq-item").forEach((btn) => {
        btn.addEventListener("click", () => {
          faqQuestionEl.textContent = btn.querySelector("span").textContent;
          faqAnswerEl.textContent = btn.dataset.answer;
          faqBackdrop.classList.add("open");
        });
      });

      function closeFaqModal() {
        faqBackdrop.classList.remove("open");
      }

      faqCloseBtn.addEventListener("click", closeFaqModal);
      faqBackdrop.addEventListener("click", (e) => {
        if (e.target === faqBackdrop) closeFaqModal();
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") closeFaqModal();
      });

      // ============ RUN ON PAGE LOAD ============
      document.addEventListener('DOMContentLoaded', function() {
        updateAccountIcon();
        
        if (window.location.search.includes('login=1')) {
          openLoginModal();
        }
      });
    </script>
  </body>
</html>