<?php
session_start();
include("conn.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $dbPassword = $user['password'];

        // ✅ Accept multiple password formats
        if (
            password_verify($password, $dbPassword) ||  // new hashed passwords
            md5($password) === $dbPassword ||           // legacy md5
            $password === $dbPassword                   // plain text
        ) {
            // Normalize role
            $role = strtolower(trim($user['role']));

            // ✅ CLEAR EXISTING SESSION COMPLETELY
            $_SESSION = array();
            session_regenerate_id(true);

            // ✅ SET COMPLETE SESSION DATA
            $_SESSION['user_id'] = $user['user_id']; 
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $role;
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? '';
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();

            // ✅ Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();

            // ✅ Redirect by role with proper headers
            if ($role === "admin") {
                header("Location: admin_dashboard.php");
                exit();
            } elseif ($role === "vet") {
                header("Location: vet_dashboard.php");
                exit();
            } else {$role ==="owner") { 
                header("Location: user_dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with that email!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Login</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    :root {
  --primary: #0ea5e9;
  --primary-dark: #0284c7;
  --primary-light: #e0f2fe;
  --secondary: #8b5cf6;
  --light: #f0f9ff;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif;
  color: #2a2e34;
  background: #fff;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  overflow-x: hidden;
}

.login-section {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(
    1200px 600px at 15% 20%,
    #e0f2fe 0%,
    #bae6fd 40%,
    var(--primary-light) 100%
  );
  padding: 40px 20px;
  position: relative;
}

.login-card {
  background: #fff;
  border: 1px solid #bfdbfe;
  border-radius: 24px;
  box-shadow: 0 15px 40px rgba(14, 165, 233, 0.12);
  padding: 40px 35px;
  max-width: 450px;
  width: 100%;
  transition: all 0.3s ease;
  position: relative;
  backdrop-filter: blur(10px);
}

.login-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(14, 165, 233, 0.15);
}

.logo-container {
  text-align: center;
  margin-bottom: 25px;
}

.logo-icon {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  font-size: 28px;
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}

.welcome-title {
  font-weight: 800;
  margin-bottom: 5px;
  color: var(--primary-dark);
  font-size: 28px;
}

.welcome-subtitle {
  color: #6c757d;
  margin-bottom: 30px;
  font-size: 15px;
  line-height: 1.5;
}

.form-label {
  font-weight: 600;
  margin-bottom: 8px;
  color: #2a2e34;
  font-size: 14px;
}

.input-group {
  position: relative;
}

.form-control {
  border-radius: 12px;
  padding: 0.85rem 1rem 0.85rem 3rem;
  transition: all 0.2s ease;
  border: 2px solid #e9ecef;
  font-size: 15px;
}

.form-control:focus {
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  border-color: var(--primary);
  transform: translateY(-1px);
}

.input-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  z-index: 5;
  font-size: 16px;
}

.password-toggle {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #6c757d;
  cursor: pointer;
  z-index: 5;
  transition: color 0.2s ease;
  padding: 5px;
  border-radius: 5px;
}

.password-toggle:hover {
  color: var(--primary);
  background: rgba(14, 165, 233, 0.1);
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 0.9rem 1.2rem;
  font-weight: 700;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.25);
  font-size: 16px;
  position: relative;
  overflow: hidden;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(14, 165, 233, 0.35);
}

.btn-primary:active {
  transform: translateY(0);
}

.btn-primary::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s;
}

.btn-primary:hover::before {
  left: 100%;
}

.btn-outline-primary {
  background: transparent;
  color: var(--primary);
  border: 2px solid var(--primary);
  border-radius: 12px;
  padding: 0.9rem 1.2rem;
  font-weight: 700;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-outline-primary:hover {
  background: var(--primary);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
}

.forgot-link {
  color: var(--primary);
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.forgot-link:hover {
  color: var(--primary-dark);
  text-decoration: underline;
  transform: translateX(2px);
}

.divider {
  display: flex;
  align-items: center;
  margin: 25px 0;
  color: #6c757d;
  font-size: 14px;
}

.divider::before,
.divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, #dee2e6, transparent);
}

.divider::before {
  margin-right: 15px;
}

.divider::after {
  margin-left: 15px;
}

.alert {
  border-radius: 12px;
  padding: 0.85rem 1rem;
  margin-bottom: 20px;
  border: none;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

footer {
  background: #f0f9ff;
  border-top: 1px solid #e0f2fe;
  padding: 1.5rem 0;
  margin-top: auto;
  text-align: center;
}

/* Back to Home Button Styles */
.back-home-btn-sm {
  background: rgba(255, 255, 255, 0.9);
  border: 2px solid var(--primary);
  color: var(--primary);
  border-radius: 50%;
  width: 45px;
  height: 45px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.back-home-btn-sm:hover {
  background: var(--primary);
  color: white;
  transform: translateX(-3px) scale(1.05);
  box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
}

/* Floating decorative elements */
.floating-element {
  position: absolute;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
  backdrop-filter: blur(10px);
  animation: float 6s ease-in-out infinite;
}

.floating-1 {
  width: 80px;
  height: 80px;
  top: 10%;
  left: 10%;
  animation-delay: 0s;
}

.floating-2 {
  width: 60px;
  height: 60px;
  top: 70%;
  right: 15%;
  animation-delay: 2s;
}

.floating-3 {
  width: 40px;
  height: 40px;
  bottom: 20%;
  left: 20%;
  animation-delay: 4s;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  50% {
    transform: translateY(-20px) rotate(180deg);
  }
}
  </style>
</head>
<body>

  <section class="login-section">
    <!-- Floating decorative elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <div class="login-card">
      <!-- Back to Home Button - Small (Top Right of Card) -->
      <a href="front_page.php" class="back-home-btn-sm" title="Back to Home" style="position: absolute; top: 20px; right: 20px;">
        <i class="fas fa-home"></i>
      </a>

      <div class="logo-container">
        <div class="logo-icon">
          <i class="fas fa-paw"></i>
        </div>
        <h1 class="welcome-title">Welcome Back</h1>
        <p class="welcome-subtitle">Sign in to access your VetCareQR account and manage your pet's health records</p>
      </div>

      <!-- Show error if login fails -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="" id="loginForm">
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-envelope"></i>
            </span>
            <input type="email" name="email" class="form-control" placeholder="Enter your email address" required 
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
          </div>
        </div>
        
        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required />
            <button type="button" class="password-toggle" id="passwordToggle" title="Toggle password visibility">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="rememberMe">
              <label class="form-check-label small" for="rememberMe">Remember me</label>
            </div>
            <a href="forgot-password.php" class="forgot-link">
              <i class="fas fa-key me-1"></i>Forgot Password?
            </a>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginButton">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In to Your Account
        </button>
      </form>

      <div class="divider">New to VetCareQR?</div>
      
      <div class="text-center mb-4">
        <p class="mb-3">Don't have an account yet?</p>
        <a href="register.php" class="btn btn-outline-primary w-100">
          <i class="fas fa-user-plus me-2"></i>Create New Account
        </a>
      </div>
    </div>
  </section>

  <footer class="py-4 text-center">
    <div class="container">
      <small>© 2025 VetCareQR — Santa Rosa Municipal Veterinary Services</small>
      <div class="mt-2">
        <small class="text-muted">Secure Pet Health Management System</small>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Password visibility toggle
    document.getElementById('passwordToggle').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        this.setAttribute('title', 'Hide password');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        this.setAttribute('title', 'Show password');
      }
      
      // Add focus back to password field
      passwordInput.focus();
    });

    // Form submission handling
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const loginButton = document.getElementById('loginButton');
      const originalText = loginButton.innerHTML;
      
      // Show loading state
      loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
      loginButton.disabled = true;
    });

    // Remember me functionality
    document.addEventListener('DOMContentLoaded', function() {
      const rememberMe = localStorage.getItem('rememberMe');
      const savedEmail = localStorage.getItem('savedEmail');
      
      if (rememberMe === 'true' && savedEmail) {
        document.querySelector('input[name="email"]').value = savedEmail;
        document.getElementById('rememberMe').checked = true;
      }
    });

    document.getElementById('rememberMe').addEventListener('change', function() {
      if (this.checked) {
        const email = document.querySelector('input[name="email"]').value;
        localStorage.setItem('savedEmail', email);
        localStorage.setItem('rememberMe', 'true');
      } else {
        localStorage.removeItem('savedEmail');
        localStorage.removeItem('rememberMe');
      }
    });

    // Add console welcome message
    console.log(`%c🐾 Welcome to VetCareQR Login! %c\nSecure Pet Health Management System`, 
                'color: #0ea5e9; font-size: 16px; font-weight: bold;', 
                'color: #666; font-size: 12px;');
  </script>
</body>
</html>

<?php
session_start();
include("conn.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $dbPassword = $user['password'];

        // ✅ Accept multiple password formats
        if (
            password_verify($password, $dbPassword) ||  // new hashed passwords
            md5($password) === $dbPassword ||           // legacy md5
            $password === $dbPassword                   // plain text
        ) {
            // Normalize role
            $role = strtolower(trim($user['role']));

            // ✅ CLEAR EXISTING SESSION COMPLETELY
            $_SESSION = array();
            session_regenerate_id(true);

            // ✅ SET COMPLETE SESSION DATA
            $_SESSION['user_id'] = $user['user_id']; 
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $role;
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? '';
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();

            // ✅ Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();

            // ✅ Redirect by role with proper headers
            if ($role === "admin") {
                header("Location: admin_dashboard.php");
                exit();
            } elseif ($role === "vet") {
                header("Location: vet_dashboard.php");
                exit();
            } else {
                header("Location: user_dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with that email!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Login</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    :root {
  --primary: #0ea5e9;
  --primary-dark: #0284c7;
  --primary-light: #e0f2fe;
  --secondary: #8b5cf6;
  --light: #f0f9ff;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif;
  color: #2a2e34;
  background: #fff;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  overflow-x: hidden;
}

.login-section {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(
    1200px 600px at 15% 20%,
    #e0f2fe 0%,
    #bae6fd 40%,
    var(--primary-light) 100%
  );
  padding: 40px 20px;
  position: relative;
}

.login-card {
  background: #fff;
  border: 1px solid #bfdbfe;
  border-radius: 24px;
  box-shadow: 0 15px 40px rgba(14, 165, 233, 0.12);
  padding: 40px 35px;
  max-width: 450px;
  width: 100%;
  transition: all 0.3s ease;
  position: relative;
  backdrop-filter: blur(10px);
}

.login-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 50px rgba(14, 165, 233, 0.15);
}

.logo-container {
  text-align: center;
  margin-bottom: 25px;
}

.logo-icon {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  font-size: 28px;
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}

.welcome-title {
  font-weight: 800;
  margin-bottom: 5px;
  color: var(--primary-dark);
  font-size: 28px;
}

.welcome-subtitle {
  color: #6c757d;
  margin-bottom: 30px;
  font-size: 15px;
  line-height: 1.5;
}

.form-label {
  font-weight: 600;
  margin-bottom: 8px;
  color: #2a2e34;
  font-size: 14px;
}

.input-group {
  position: relative;
}

.form-control {
  border-radius: 12px;
  padding: 0.85rem 1rem 0.85rem 3rem;
  transition: all 0.2s ease;
  border: 2px solid #e9ecef;
  font-size: 15px;
}

.form-control:focus {
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  border-color: var(--primary);
  transform: translateY(-1px);
}

.input-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  z-index: 5;
  font-size: 16px;
}

.password-toggle {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #6c757d;
  cursor: pointer;
  z-index: 5;
  transition: color 0.2s ease;
  padding: 5px;
  border-radius: 5px;
}

.password-toggle:hover {
  color: var(--primary);
  background: rgba(14, 165, 233, 0.1);
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 0.9rem 1.2rem;
  font-weight: 700;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.25);
  font-size: 16px;
  position: relative;
  overflow: hidden;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(14, 165, 233, 0.35);
}

.btn-primary:active {
  transform: translateY(0);
}

.btn-primary::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s;
}

.btn-primary:hover::before {
  left: 100%;
}

.btn-outline-primary {
  background: transparent;
  color: var(--primary);
  border: 2px solid var(--primary);
  border-radius: 12px;
  padding: 0.9rem 1.2rem;
  font-weight: 700;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-outline-primary:hover {
  background: var(--primary);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
}

.forgot-link {
  color: var(--primary);
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.forgot-link:hover {
  color: var(--primary-dark);
  text-decoration: underline;
  transform: translateX(2px);
}

.divider {
  display: flex;
  align-items: center;
  margin: 25px 0;
  color: #6c757d;
  font-size: 14px;
}

.divider::before,
.divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, #dee2e6, transparent);
}

.divider::before {
  margin-right: 15px;
}

.divider::after {
  margin-left: 15px;
}

.alert {
  border-radius: 12px;
  padding: 0.85rem 1rem;
  margin-bottom: 20px;
  border: none;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

footer {
  background: #f0f9ff;
  border-top: 1px solid #e0f2fe;
  padding: 1.5rem 0;
  margin-top: auto;
  text-align: center;
}

/* Back to Home Button Styles */
.back-home-btn-sm {
  background: rgba(255, 255, 255, 0.9);
  border: 2px solid var(--primary);
  color: var(--primary);
  border-radius: 50%;
  width: 45px;
  height: 45px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.back-home-btn-sm:hover {
  background: var(--primary);
  color: white;
  transform: translateX(-3px) scale(1.05);
  box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
}

/* Floating decorative elements */
.floating-element {
  position: absolute;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
  backdrop-filter: blur(10px);
  animation: float 6s ease-in-out infinite;
}

.floating-1 {
  width: 80px;
  height: 80px;
  top: 10%;
  left: 10%;
  animation-delay: 0s;
}

.floating-2 {
  width: 60px;
  height: 60px;
  top: 70%;
  right: 15%;
  animation-delay: 2s;
}

.floating-3 {
  width: 40px;
  height: 40px;
  bottom: 20%;
  left: 20%;
  animation-delay: 4s;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  50% {
    transform: translateY(-20px) rotate(180deg);
  }
}
  </style>
</head>
<body>

  <section class="login-section">
    <!-- Floating decorative elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <div class="login-card">
      <!-- Back to Home Button - Small (Top Right of Card) -->
      <a href="front_page.php" class="back-home-btn-sm" title="Back to Home" style="position: absolute; top: 20px; right: 20px;">
        <i class="fas fa-home"></i>
      </a>

      <div class="logo-container">
        <div class="logo-icon">
          <i class="fas fa-paw"></i>
        </div>
        <h1 class="welcome-title">Welcome Back</h1>
        <p class="welcome-subtitle">Sign in to access your VetCareQR account and manage your pet's health records</p>
      </div>

      <!-- Show error if login fails -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="" id="loginForm">
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-envelope"></i>
            </span>
            <input type="email" name="email" class="form-control" placeholder="Enter your email address" required 
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
          </div>
        </div>
        
        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required />
            <button type="button" class="password-toggle" id="passwordToggle" title="Toggle password visibility">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="rememberMe">
              <label class="form-check-label small" for="rememberMe">Remember me</label>
            </div>
            <a href="forgot-password.php" class="forgot-link">
              <i class="fas fa-key me-1"></i>Forgot Password?
            </a>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginButton">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In to Your Account
        </button>
      </form>

      <div class="divider">New to VetCareQR?</div>
      
      <div class="text-center mb-4">
        <p class="mb-3">Don't have an account yet?</p>
        <a href="register.php" class="btn btn-outline-primary w-100">
          <i class="fas fa-user-plus me-2"></i>Create New Account
        </a>
      </div>
    </div>
  </section>

  <footer class="py-4 text-center">
    <div class="container">
      <small>© 2025 VetCareQR — Santa Rosa Municipal Veterinary Services</small>
      <div class="mt-2">
        <small class="text-muted">Secure Pet Health Management System</small>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Password visibility toggle
    document.getElementById('passwordToggle').addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        this.setAttribute('title', 'Hide password');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        this.setAttribute('title', 'Show password');
      }
      
      // Add focus back to password field
      passwordInput.focus();
    });

    // Form submission handling
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const loginButton = document.getElementById('loginButton');
      const originalText = loginButton.innerHTML;
      
      // Show loading state
      loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
      loginButton.disabled = true;
    });

    // Remember me functionality
    document.addEventListener('DOMContentLoaded', function() {
      const rememberMe = localStorage.getItem('rememberMe');
      const savedEmail = localStorage.getItem('savedEmail');
      
      if (rememberMe === 'true' && savedEmail) {
        document.querySelector('input[name="email"]').value = savedEmail;
        document.getElementById('rememberMe').checked = true;
      }
    });

    document.getElementById('rememberMe').addEventListener('change', function() {
      if (this.checked) {
        const email = document.querySelector('input[name="email"]').value;
        localStorage.setItem('savedEmail', email);
        localStorage.setItem('rememberMe', 'true');
      } else {
        localStorage.removeItem('savedEmail');
        localStorage.removeItem('rememberMe');
      }
    });

    // Add console welcome message
    console.log(`%c🐾 Welcome to VetCareQR Login! %c\nSecure Pet Health Management System`, 
                'color: #0ea5e9; font-size: 16px; font-weight: bold;', 
                'color: #666; font-size: 12px;');
  </script>
</body>
</html>

