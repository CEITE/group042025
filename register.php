<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conn.php");

$error = "";
$success = "";
$show_otp_form = false;

// Check if users table has the required columns
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
if ($checkColumns->num_rows == 0) {
    // Add missing columns
    $conn->query("ALTER TABLE users 
        ADD COLUMN is_verified TINYINT(1) DEFAULT 1,
        ADD COLUMN otp_code VARCHAR(6),
        ADD COLUMN otp_expiry DATETIME");
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['verify_otp'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $role = isset($_POST['role']) ? $_POST['role'] : 'user';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email already exists!";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user (auto-verified for now)
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone_number, address, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
                if ($stmt) {
                    $stmt->bind_param("ssssss", $name, $email, $hashed_password, $role, $phone_number, $address);
                    
                    if ($stmt->execute()) {
                        $success = "Registration successful! You can now login.";
                        // Clear form
                        $name = $email = $phone_number = $address = "";
                    } else {
                        $error = "Error creating account: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Register</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    :root {
      --pink: #ffd6e7;
      --pink-2: #f7c5e0;
      --pink-dark: #bf3b78;
      --pink-darker: #9c2c60;
      --ink: #2a2e34;
      --gray-light: #f8f9fa;
    }

    body {
      font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif;
      color: var(--ink);
      background: #fff;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .register-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(
        1200px 600px at 15% 20%,
        #ffe7f2 0%,
        #ffd8ec 40%,
        var(--pink) 100%
      );
      padding: 40px 20px;
    }

    .register-card {
      background: #fff;
      border: 1px solid #f0ddea;
      border-radius: 24px;
      box-shadow: 0 15px 40px rgba(184, 71, 129, 0.12);
      padding: 40px 35px;
      max-width: 500px;
      width: 100%;
    }

    .logo-container {
      text-align: center;
      margin-bottom: 25px;
    }

    .logo-icon {
      background: linear-gradient(135deg, var(--pink-dark), var(--pink-darker));
      color: white;
      width: 70px;
      height: 70px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      font-size: 28px;
    }

    .welcome-title {
      font-weight: 800;
      margin-bottom: 5px;
      color: var(--pink-darker);
      font-size: 28px;
    }

    .welcome-subtitle {
      color: #6c757d;
      margin-bottom: 30px;
      font-size: 15px;
    }

    .form-label {
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--ink);
      font-size: 14px;
    }

    .input-group {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      z-index: 5;
    }

    .form-control, .form-select {
      border-radius: 12px;
      padding: 0.85rem 1rem 0.85rem 3rem;
      transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
      box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.15);
      border-color: #ced4da;
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
    }

    .btn-pink {
      background: var(--pink-dark);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      font-weight: 700;
      transition: all 0.2s ease;
    }

    .btn-pink:hover {
      background: var(--pink-darker);
      transform: translateY(-2px);
    }

    .login-link {
      color: var(--pink-dark);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
    }

    .login-link:hover {
      color: var(--pink-darker);
      text-decoration: underline;
    }

    .alert {
      border-radius: 12px;
      padding: 0.85rem 1rem;
      margin-bottom: 20px;
    }

    footer {
      background: #fff8fc;
      border-top: 1px solid #f1e6f0;
      padding: 1.5rem 0;
      margin-top: auto;
    }
  </style>
</head>
<body>

  <section class="register-section">
    <div class="register-card">
      <div class="logo-container">
        <div class="logo-icon">
          <i class="fas fa-paw"></i>
        </div>
        <h1 class="welcome-title">Create Account</h1>
        <p class="welcome-subtitle">Join VetCareQR to manage your pet's health</p>
      </div>

      <!-- Show error/success messages -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="fas fa-check-circle me-2"></i>
          <div><?php echo htmlspecialchars($success); ?></div>
        </div>
      <?php endif; ?>

      <!-- Registration Form -->
      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-user"></i>
            </span>
            <input type="text" name="name" class="form-control" placeholder="Enter your full name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required />
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-envelope"></i>
            </span>
            <input type="email" name="email" class="form-control" placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required />
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Account Type</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-user-tag"></i>
            </span>
            <select name="role" class="form-select" required>
              <option value="user" selected>Pet Owner</option>
              <option value="vet">Veterinarian</option>
            </select>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-phone"></i>
            </span>
            <input type="tel" name="phone_number" class="form-control" placeholder="Enter your phone number" value="<?php echo isset($phone_number) ? htmlspecialchars($phone_number) : ''; ?>" />
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Address (Optional)</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-home"></i>
            </span>
            <input type="text" name="address" class="form-control" placeholder="Enter your address" value="<?php echo isset($address) ? htmlspecialchars($address) : ''; ?>" />
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="password" id="password" class="form-control" placeholder="Create a password" required />
            <button type="button" class="password-toggle" id="passwordToggle">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        
        <div class="mb-4">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
            <span class="input-icon">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm your password" required />
            <button type="button" class="password-toggle" id="confirmPasswordToggle">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div id="passwordMatch" class="mt-2 small"></div>
        </div>
        
        <button type="submit" class="btn btn-pink w-100 mb-3">
          <i class="fas fa-user-plus me-2"></i>Create Account
        </button>
      </form>

      <div class="text-center">
        <p class="mb-0">Already have an account?
          <a href="login.php" class="login-link">Sign In</a>
        </p>
      </div>
    </div>
  </section>

  <footer class="py-4 text-center">
    <small>© 2025 VetCareQR — Santa Rosa Municipal Veterinary Services</small>
  </footer>

  <script>
    // Password visibility toggle
    document.getElementById('passwordToggle')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Confirm password visibility toggle
    document.getElementById('confirmPasswordToggle')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('confirmPassword');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Password match checking
    document.getElementById('confirmPassword')?.addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirmPassword = this.value;
      const matchElement = document.getElementById('passwordMatch');
      
      if (confirmPassword === '') {
        matchElement.textContent = '';
        matchElement.style.color = '';
      } else if (password === confirmPassword) {
        matchElement.textContent = 'Passwords match';
        matchElement.style.color = '#28a745';
      } else {
        matchElement.textContent = 'Passwords do not match';
        matchElement.style.color = '#dc3545';
      }
    });
  </script>
</body>
</html>
