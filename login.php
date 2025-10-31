<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle login
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Check if user exists and is an admin
        $query = "SELECT user_id, name, email, password, role, profile_picture FROM users WHERE email = ? AND role = 'admin'";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];
                    
                    $_SESSION['success'] = "Welcome back, " . $user['name'] . "!";
                    
                    // Update last login if the column exists
                    try {
                        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
                        if ($check_column && $check_column->num_rows > 0) {
                            $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            if ($update_stmt) {
                                $update_stmt->bind_param("i", $user['user_id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    } catch (Exception $e) {
                        // Silently continue if column doesn't exist
                    }
                    
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $errors[] = "Invalid email or password";
                }
            } else {
                $errors[] = "Invalid email or password, or account is not an administrator account";
            }
            
            $stmt->close();
        } else {
            $errors[] = "System error: Unable to process login";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Login - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .login-left {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        
        .login-right {
            padding: 4rem 3rem;
            background: white;
            position: relative;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 3rem 0;
        }
        
        .feature-list li {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .feature-list i {
            background: rgba(255,255,255,0.2);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.2rem;
            font-size: 1.3rem;
            transition: all 0.3s ease;
        }
        
        .feature-list li:hover i {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .form-control {
            border-radius: 16px;
            padding: 16px 20px;
            border: 2px solid #f3f4f6;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f0f9ff;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            transform: translateY(-2px);
            background: white;
        }
        
        .input-group-text {
            background: #f0f9ff;
            border: 2px solid #f3f4f6;
            border-right: none;
            border-radius: 16px 0 0 16px;
            color: var(--primary);
        }
        
        .form-control:not(:first-child) {
            border-left: none;
            border-radius: 0 16px 16px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 16px 32px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
        }
        
        .btn-outline-light {
            border: 2px solid white;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .register-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .register-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .forgot-password {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--primary);
        }
        
        .alert {
            border-radius: 16px;
            border: none;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
            color: #6b7280;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        
        .divider-text {
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 3rem 2rem;
            }
            
            .login-right {
                padding: 3rem 2rem;
            }
            
            .logo {
                font-size: 2rem;
            }
            
            .feature-list li {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 15px;
            }
            
            .login-left,
            .login-right {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="login-container">
            <div class="row g-0">
                <!-- Left Side - Information -->
                <div class="col-lg-6">
                    <div class="login-left">
                        <div class="logo">
                            <i class="fas fa-user-shield"></i>
                            VetCareQR
                        </div>
                        <h2 class="mb-3 fw-bold">Administrator Access</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.95;">
                            Access the system administration panel to manage users, settings, and system-wide configurations.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-users-cog"></i>
                                <span>Complete User Management</span>
                            </li>
                            <li>
                                <i class="fas fa-cog"></i>
                                <span>System Configuration</span>
                            </li>
                            <li>
                                <i class="fas fa-chart-bar"></i>
                                <span>Analytics & Reporting</span>
                            </li>
                            <li>
                                <i class="fas fa-database"></i>
                                <span>Database Administration</span>
                            </li>
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <span>Security & Permissions</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.9;">Need administrator access? </small>
                            <a href="register_admin.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-user-plus me-1"></i> Register Admin Account
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="col-lg-6">
                    <div class="login-right">
                        <div class="text-center mb-5">
                            <h3 class="mb-2 fw-bold" style="color: var(--primary);">Administrator Login</h3>
                            <p class="text-muted">Sign in to your system administrator account</p>
                        </div>
                        
                        <!-- Success Messages -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $_SESSION['success']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="admin@vetcareqr.com" required
                                           autocomplete="email">
                                </div>
                                <div class="form-text">Enter your administrator email address</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your password" required
                                           autocomplete="current-password">
                                    <button type="button" class="input-group-text password-toggle" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Enter your administrator password</div>
                            </div>
                            
                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me for 30 days</label>
                                </div>
                                <a href="forgot_password.php" class="forgot-password">
                                    <i class="fas fa-key me-1"></i>Forgot password?
                                </a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4 fw-bold">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Admin Dashboard
                            </button>
                            
                            <div class="divider">
                                <span class="divider-text">Secure Login</span>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted d-block">
                                    <i class="fas fa-lock me-1"></i>Your login is secured with encryption
                                </small>
                            </div>
                        </form>
                        
                        <div class="text-center mt-5 pt-4 border-top">
                            <p class="mb-3">Don't have an administrator account?</p>
                            <a href="register_admin.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-1"></i> Register as Administrator
                            </a>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Are you a veterinarian? 
                                <a href="vet_login.php" class="register-link">Login to vet portal here</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('title', 'Hide password');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('title', 'Show password');
            }
        });

        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });

        // Add loading state to form submission
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
            submitButton.disabled = true;
        });

        // Auto-dismiss alerts after 8 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 8000);
            });
        });
    </script>
</body>

</html> can you copy the color them in this code , <?php
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
            $role = strtolower($user['role']);

            $_SESSION['user_id'] = $user['user_id']; 
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $role;

            // Redirect by role
            if ($role === "admin") {
                header("Location: admin_dashboard.php");
            } elseif ($role === "vet") {
                header("Location: vet_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with that email!";
    }
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
      --pink: #ffd6e7;
      --pink-2: #f7c5e0;
      --pink-dark: #bf3b78;
      --pink-darker: #9c2c60;
      --ink: #2a2e34;
      --gray-light: #f8f9fa;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif;
      color: var(--ink);
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
        #ffe7f2 0%,
        #ffd8ec 40%,
        var(--pink) 100%
      );
      padding: 40px 20px;
      position: relative;
    }

    .login-card {
      background: #fff;
      border: 1px solid #f0ddea;
      border-radius: 24px;
      box-shadow: 0 15px 40px rgba(184, 71, 129, 0.12);
      padding: 40px 35px;
      max-width: 450px;
      width: 100%;
      transition: all 0.3s ease;
      position: relative;
      backdrop-filter: blur(10px);
    }

    .login-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 50px rgba(184, 71, 129, 0.15);
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
      box-shadow: 0 4px 15px rgba(191, 59, 120, 0.3);
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
      line-height: 1.5;
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

    .form-control {
      border-radius: 12px;
      padding: 0.85rem 1rem 0.85rem 3rem;
      transition: all 0.2s ease;
      border: 2px solid #e9ecef;
      font-size: 15px;
    }

    .form-control:focus {
      box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.15);
      border-color: var(--pink-dark);
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
      color: var(--pink-dark);
      background: rgba(191, 59, 120, 0.1);
    }

    .btn-pink {
      background: linear-gradient(135deg, var(--pink-dark), var(--pink-darker));
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      font-weight: 700;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(191, 59, 120, 0.25);
      font-size: 16px;
      position: relative;
      overflow: hidden;
    }

    .btn-pink:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(191, 59, 120, 0.35);
    }

    .btn-pink:active {
      transform: translateY(0);
    }

    .btn-pink::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
    }

    .btn-pink:hover::before {
      left: 100%;
    }

    .btn-outline-pink {
      background: transparent;
      color: var(--pink-dark);
      border: 2px solid var(--pink-dark);
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

    .btn-outline-pink:hover {
      background: var(--pink-dark);
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(191, 59, 120, 0.2);
    }

    .forgot-link {
      color: var(--pink-dark);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .forgot-link:hover {
      color: var(--pink-darker);
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
      background: #fff8fc;
      border-top: 1px solid #f1e6f0;
      padding: 1.5rem 0;
      margin-top: auto;
      text-align: center;
    }

    /* Back to Home Button Styles */
    .back-home-btn-sm {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid var(--pink-dark);
      color: var(--pink-dark);
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
      background: var(--pink-dark);
      color: white;
      transform: translateX(-3px) scale(1.05);
      box-shadow: 0 6px 20px rgba(191, 59, 120, 0.3);
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

    /* Responsive Design */
    @media (max-width: 768px) {
      .login-section {
        padding: 20px 15px;
      }
      
      .login-card {
        padding: 30px 25px;
        margin: 20px 0;
      }
      
      .floating-element {
        display: none;
      }
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 25px 20px;
      }
      
      .welcome-title {
        font-size: 24px;
      }
      
      .btn-pink, .btn-outline-pink {
        padding: 0.8rem 1rem;
        font-size: 15px;
      }
    }

    /* Loading animation for button */
    .btn-loading {
      position: relative;
      color: transparent !important;
    }

    .btn-loading::after {
      content: '';
      position: absolute;
      width: 20px;
      height: 20px;
      top: 50%;
      left: 50%;
      margin: -10px 0 0 -10px;
      border: 2px solid #ffffff;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Success animation */
    @keyframes success {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    .success-animation {
      animation: success 0.5s ease-in-out;
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
            <a href="forgot_password.php" class="forgot-link">
              <i class="fas fa-key me-1"></i>Forgot Password?
            </a>
          </div>
        </div>
        
        <button type="submit" class="btn btn-pink w-100 mb-3" id="loginButton">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In to Your Account
        </button>
      </form>

      <div class="divider">New to VetCareQR?</div>
      
      <div class="text-center mb-4">
        <p class="mb-3">Don't have an account yet?</p>
        <a href="register.php" class="btn btn-outline-pink w-100">
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
      loginButton.classList.add('btn-loading');
      loginButton.disabled = true;
      
      // Simulate loading for demo (remove in production)
      setTimeout(() => {
        loginButton.classList.remove('btn-loading');
        loginButton.innerHTML = originalText;
        loginButton.disabled = false;
      }, 1500);
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

    // Auto-save email when typing
    let emailTimeout;
    document.querySelector('input[name="email"]').addEventListener('input', function() {
      clearTimeout(emailTimeout);
      emailTimeout = setTimeout(() => {
        if (document.getElementById('rememberMe').checked) {
          localStorage.setItem('savedEmail', this.value);
        }
      }, 1000);
    });

    // Add some interactive effects
    document.querySelectorAll('.form-control').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.querySelector('.input-icon').style.color = 'var(--pink-dark)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.querySelector('.input-icon').style.color = '#6c757d';
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl + H for home
      if (e.ctrlKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = 'front_page.php';
      }
      
      // Escape key to go back
      if (e.key === 'Escape') {
        window.location.href = 'front_page.php';
      }
    });

    // Add console welcome message
    console.log(`%c🐾 Welcome to VetCareQR Login! %c\nSecure Pet Health Management System`, 
                'color: #bf3b78; font-size: 16px; font-weight: bold;', 
                'color: #666; font-size: 12px;');
  </script>
</body>

</html> 
