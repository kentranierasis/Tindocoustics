<?php
/**
 * login.php
 * ------------------------------------------------------------------
 * Unified Customer & Admin Login / Create Account modal for TINDOC
 * Acoustic Guitars.
 *
 * This file contains ONLY the modal component (overlay + card).
 * It has no page background, header, or hero — it's meant to be
 * included on any page (index.php, shop.php, etc.) right before
 * the closing </body> tag, then triggered by JS.
 *
 *   <?php include 'login.php'; ?>
 *
 * Requires (already loaded site-wide):
 *   - css/style.css        (color variables, fonts)
 *   - css/login-modal.css  (this component's styles)
 *   - Font Awesome (icons)
 * ------------------------------------------------------------------
 */
?>

<link rel="stylesheet" href="css/login-modal.css?v=3" />

<!-- ============ UNIFIED LOGIN / CREATE ACCOUNT MODAL ============ -->
<div class="login-modal-overlay" id="loginModalOverlay">
  <div
    class="login-modal-card"
    role="dialog"
    aria-modal="true"
    aria-label="Welcome to TINDOC — log in or create an account"
  >
    <button
      class="login-modal-close"
      id="loginModalClose"
      aria-label="Close login modal"
    >
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="login-modal-header">
      <img
        src="images/Main-Logo.png"
        alt="TINDOC Acoustic Guitars"
        class="login-modal-logo"
      />
      <p class="login-modal-subtext">
        Log in to your account or create a new one<br />
        to continue your journey with us.
      </p>
      <span class="login-modal-rule" aria-hidden="true"></span>
    </div>

    <!-- Tabs -->
    <div class="login-modal-tabs" role="tablist">
      <button
        class="login-modal-tab active"
        id="tabLogin"
        role="tab"
        aria-selected="true"
        aria-controls="panelLogin"
        onclick="switchLoginTab('login')"
      >
        Login
      </button>
      <button
        class="login-modal-tab"
        id="tabCreate"
        role="tab"
        aria-selected="false"
        aria-controls="panelCreate"
        onclick="switchLoginTab('create')"
      >
        Create Account
      </button>
    </div>

    <!-- ===== Login panel ===== -->
    <div class="login-modal-panel active" id="panelLogin" role="tabpanel">
      <form
        class="login-modal-form"
        id="loginForm"
        onsubmit="return handleLogin(event)"
      >
        <div class="input-field">
          <i class="fa-regular fa-envelope"></i>
          <input
            type="email"
            name="email"
            id="loginEmail"
            placeholder="Email Address"
            required
          />
        </div>

        <div class="input-field">
          <i class="fa-solid fa-lock"></i>
          <input
            type="password"
            name="password"
            id="loginPassword"
            placeholder="Password"
            required
          />
        </div>

        <!-- REMOVED: Forgot password link - only Remember me remains -->
        <div class="login-modal-row">
          <label class="remember-me">
            <input type="checkbox" name="remember" />
            <span>Remember me</span>
          </label>
        </div>

        <div id="loginError" class="login-error" style="display:none; color: #a12c2c; font-size:0.85rem; margin:0.5rem 0;"></div>

        <button type="submit" class="btn btn-dark login-modal-submit">
          Log In <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <div class="login-modal-divider">
        <span>OR</span>
      </div>

      <button
        type="button"
        class="btn login-modal-alt-btn"
        onclick="switchLoginTab('create')"
      >
        <i class="fa-solid fa-user-plus"></i> Create an Account
      </button>

      <p class="login-modal-footnote">
        New here? Create an account to start shopping.
      </p>
    </div>

    <!-- ===== Create Account panel ===== -->
    <div class="login-modal-panel" id="panelCreate" role="tabpanel">
      <form
        class="login-modal-form"
        id="registerForm"
        onsubmit="return handleRegister(event)"
      >
        <div class="input-field">
          <i class="fa-regular fa-user"></i>
          <input type="text" name="fullname" id="registerName" placeholder="Full Name" required />
        </div>

        <div class="input-field">
          <i class="fa-regular fa-envelope"></i>
          <input
            type="email"
            name="email"
            id="registerEmail"
            placeholder="Email Address"
            required
          />
        </div>

        <div class="input-field">
          <i class="fa-solid fa-lock"></i>
          <input
            type="password"
            name="password"
            id="registerPassword"
            placeholder="Password (min 6 characters)"
            required
            minlength="6"
          />
        </div>

        <div class="input-field">
          <i class="fa-solid fa-lock"></i>
          <input
            type="password"
            name="confirm_password"
            id="registerConfirm"
            placeholder="Confirm Password"
            required
          />
        </div>

        <div id="registerError" class="login-error" style="display:none; color: #a12c2c; font-size:0.85rem; margin:0.5rem 0;"></div>

        <button type="submit" class="btn btn-dark login-modal-submit">
          Create Account <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <p class="login-modal-footnote">
        Already have an account?
        <a href="#" onclick="switchLoginTab('login'); return false;"
          >Log in here</a
        >.
      </p>
    </div>
  </div>
</div>

<script>
(function () {
  const overlay = document.getElementById("loginModalOverlay");
  const closeBtn = document.getElementById("loginModalClose");
  let scrollY = 0;
  let pendingAction = null;

  // ============ OPEN MODAL ============
  window.openLoginModal = function (tab, callback) {
    if (callback) {
      pendingAction = callback;
    }

    scrollY = window.scrollY;
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.classList.add("login-modal-lock-scroll");
    document.body.classList.add("login-modal-lock-scroll");
    document.body.style.top = `-${scrollY}px`;
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = `${scrollbarWidth}px`;
    }
    overlay.classList.add("is-open");
    if (tab) switchLoginTab(tab);

    document.getElementById('loginError').style.display = 'none';
    document.getElementById('registerError').style.display = 'none';
  };

  // ============ CLOSE MODAL ============
  window.closeLoginModal = function () {
    overlay.classList.remove("is-open");
    document.documentElement.classList.remove("login-modal-lock-scroll");
    document.body.classList.remove("login-modal-lock-scroll");
    document.body.style.top = "";
    document.body.style.paddingRight = "";
    window.scrollTo(0, scrollY);
    pendingAction = null;
  };

  // ============ SWITCH TABS ============
  window.switchLoginTab = function (tab) {
    const isLogin = tab === "login";

    document.getElementById("tabLogin").classList.toggle("active", isLogin);
    document.getElementById("tabLogin").setAttribute("aria-selected", isLogin);
    document.getElementById("tabCreate").classList.toggle("active", !isLogin);
    document.getElementById("tabCreate").setAttribute("aria-selected", !isLogin);

    document.getElementById("panelLogin").classList.toggle("active", isLogin);
    document.getElementById("panelCreate").classList.toggle("active", !isLogin);

    document.getElementById('loginError').style.display = 'none';
    document.getElementById('registerError').style.display = 'none';
  };

  // ============ LOGIN HANDLER ============
  window.handleLogin = async function (event) {
    event.preventDefault();

    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const errorEl = document.getElementById('loginError');

    errorEl.style.display = 'none';

    console.log('Attempting login for:', email);

    try {
      const response = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const result = await response.json();
      console.log('Login result:', result);

      if (result.success) {
        console.log('✅ Login successful!');
        console.log('User type:', result.user_type);
        console.log('Redirect to:', result.redirect);
        
        closeLoginModal();
        
        if (pendingAction) {
          const action = pendingAction;
          pendingAction = null;
          setTimeout(() => {
            if (typeof action === 'function') {
              action();
            }
          }, 300);
        } else {
          setTimeout(() => {
            if (result.redirect) {
              window.location.href = result.redirect;
            } else {
              window.location.reload();
            }
          }, 300);
        }
        
      } else {
        errorEl.textContent = result.message || 'Invalid email or password.';
        errorEl.style.display = 'block';
      }
    } catch (err) {
      console.error('Login error:', err);
      errorEl.textContent = 'Connection error. Please try again.';
      errorEl.style.display = 'block';
    }

    return false;
  };

  // ============ REGISTER HANDLER ============
  window.handleRegister = async function (event) {
    event.preventDefault();

    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const confirm = document.getElementById('registerConfirm').value;
    const errorEl = document.getElementById('registerError');

    errorEl.style.display = 'none';

    if (password !== confirm) {
      errorEl.textContent = 'Passwords do not match.';
      errorEl.style.display = 'block';
      return false;
    }

    console.log('Attempting registration for:', email);

    try {
      const response = await fetch('api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ full_name: name, email, password })
      });

      const result = await response.json();
      console.log('Registration result:', result);

      if (result.success) {
        console.log('✅ Registration successful!');
        closeLoginModal();
        setTimeout(() => {
          window.location.href = 'index.php';
        }, 300);
      } else {
        errorEl.textContent = result.message || 'Registration failed. Please try again.';
        errorEl.style.display = 'block';
      }
    } catch (err) {
      console.error('Registration error:', err);
      errorEl.textContent = 'Connection error. Please try again.';
      errorEl.style.display = 'block';
    }

    return false;
  };

  // ============ EVENT LISTENERS ============
  closeBtn.addEventListener("click", closeLoginModal);

  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeLoginModal();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && overlay.classList.contains("is-open")) {
      closeLoginModal();
    }
  });

  window.setPendingAction = function(callback) {
    pendingAction = callback;
  };

  fetch('api/check-session.php')
    .then(res => res.json())
    .then(data => {
      console.log('Session check on load:', data);
      if (window.updateAccountIcon) {
        window.updateAccountIcon();
      }
    })
    .catch(err => console.error('Session check error:', err));

})();
</script>