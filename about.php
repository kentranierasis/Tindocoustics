<?php
require_once 'config.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About — TINDOC Acoustic Guitars</title>

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
    <link rel="stylesheet" href="css/aboutstyle.css?v=3" />
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
            <li><a href="about.php" class="active">ABOUT</a></li>
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

    <!-- ============ ABOUT HERO ============ -->
    <section class="hero about-hero">
      <img
        src="images/Home.png"
        alt="TINDOC acoustic guitar leaning against a wooden wall"
        class="hero-static-image"
      />

      <div class="hero-content">
        <h1>About<span>TINDOC Acoustic</span></h1>
        <p class="hero-text">
          We believe that a guitar is more than just an instrument—it's a
          companion in every moment, a voice to your story, and a legacy
          that lasts for generations.
        </p>
      </div>
    </section>

    <!-- ============ OUR STORY ============ -->
    <section class="story-section">
      <div class="story-inner">
        <div class="story-image">
          <img src="images/guitar w hand v2.png" alt="Luthier hand-finishing a TINDOC guitar top" />
        </div>

        <div class="story-content">
          <p class="eyebrow">Our Story</p>
          <h2>Crafted with Passion.<br />Played with Heart.</h2>

          <p>
            TINDOC Acoustic Guitars was born from a simple love for music and
            craftsmanship. What started as a personal journey of building and
            perfecting guitars has grown into a brand dedicated to creating
            instruments that inspire.
          </p>
          <p>
            Every TINDOC guitar is carefully crafted using quality tonewoods,
            precise workmanship, and a deep respect for tradition—blended with
            modern innovation for today's players.
          </p>
        </div>
      </div>
    </section>

    <!-- ============ STORY FEATURES ============ -->
    <section class="story-features">
      <div class="story-features-inner">
        <div class="story-feature">
          <i class="fa-solid fa-tree"></i>
          <h3>Premium Tonewoods</h3>
          <p>We select only the finest woods to ensure rich tone, durability, and timeless beauty.</p>
        </div>
        <div class="story-feature">
          <i class="fa-solid fa-guitar"></i>
          <h3>Expert Craftsmanship</h3>
          <p>Each guitar is handcrafted with precision and care by skilled artisans.</p>
        </div>
        <div class="story-feature">
          <i class="fa-solid fa-wave-square"></i>
          <h3>Balanced Sound</h3>
          <p>Designed to deliver warm, clear, and well-balanced tone for every playing style.</p>
        </div>
        <div class="story-feature">
          <i class="fa-solid fa-shield-halved"></i>
          <h3>Built to Last</h3>
          <p>Made with attention to detail and strict quality standards so your guitar can last a lifetime.</p>
        </div>
      </div>
    </section>

    <!-- ============ OUR MISSION ============ -->
    <section class="mission-section">
      <div class="mission-content">
        <p class="eyebrow">Our Mission</p>
        <h2>
          To build guitars that empower musicians at every stage of their
          journey.
        </h2>
      </div>

      <div class="mission-gallery">
        <img src="images/MOckup.png" alt="Close-up of a guitar headstock and tuning pegs" />
        <img src="images/Aboutguitar 1 v2.png" alt="Side profile of a guitar body" />
        <img src="images/Aboutguitar2 v2.png" alt="Close-up of a guitar soundhole and rosette" />
      </div>
    </section>

    <!-- ============ TESTIMONIAL ============ -->
    <section class="testimonial">
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
        <p class="testimonial-author" id="testimonialAuthor">— Michael D.</p>
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
            Built to perform every session. Crafted to be with you for life.
          </p>
          <div class="social-links">
            <a href="https://www.facebook.com/kennhaku" aria-label="Facebook" 
              ><i class="fa-brands fa-facebook-f"></i
            ></a>
            <a href="https://www.instagram.com/kensimpleton/"
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
            <li><a href="index.php">Home</a></li>
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
            <li><a href="contact.php#faq-section" >FAQs</a></li>
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

      // ============ TESTIMONIAL SLIDER ============
      const testimonials = [
        {
          text: "TINDOC guitars exceeded my expectations. Great sound, smooth playability, and beautifully made!",
          author: "— Michael D."
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