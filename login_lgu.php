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

// Redirect if already logged in as LGU
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'lgu') {
    header("Location: lgu_dashboard.php");
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
        // Check if user exists and is an LGU
        $query = "SELECT user_id, name, email, password, role, license_number, region, province, city FROM users WHERE email = ? AND role = 'lgu'";
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
                    $_SESSION['license_number'] = $user['license_number'];
                    $_SESSION['region'] = $user['region'];
                    $_SESSION['province'] = $user['province'];
                    $_SESSION['city'] = $user['city'];
                    
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
                    
                    header("Location: lgu_dashboard.php");
                    exit();
                } else {
                    $errors[] = "Invalid email or password";
                }
            } else {
                $errors[] = "Invalid email or password, or account is not an LGU account";
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
    <title>LGU Login - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #dbeafe;
            --secondary: #1e40af;
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
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
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
                            <i class="fas fa-landmark"></i>
                            VetCareQR LGU
                        </div>
                        <h2 class="mb-3 fw-bold">Welcome Back, LGU Official</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.95;">
                            Access your Local Government Unit dashboard to manage veterinary compliance and public health monitoring.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-chart-bar"></i>
                                <span>Compliance Monitoring & Analytics</span>
                            </li>
                            <li>
                                <i class="fas fa-file-contract"></i>
                                <span>License & Permit Management</span>
                            </li>
                            <li>
                                <i class="fas fa-map-marked-alt"></i>
                                <span>Regional Veterinary Oversight</span>
                            </li>
                            <li>
                                <i class="fas fa-bullhorn"></i>
                                <span>Public Health Announcements</span>
                            </li>
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <span>Disease Outbreak Monitoring</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.9;">New LGU official? </small>
                            <a href="register-lgu.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-user-plus me-1"></i> Register LGU Account
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="col-lg-6">
                    <div class="login-right">
                        <div class="text-center mb-5">
                            <h3 class="mb-2 fw-bold" style="color: var(--primary);">LGU Official Login</h3>
                            <p class="text-muted">Sign in to your government administration account</p>
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
                                <label for="email" class="form-label fw-semibold">Official Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="lgu.official@municipality.gov.ph" required
                                           autocomplete="email">
                                </div>
                                <div class="form-text">Enter your registered LGU official email address</div>
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
                                <div class="form-text">Enter your LGU account password</div>
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
                                <i class="fas fa-sign-in-alt me-2"></i> Login to LGU Dashboard
                            </button>
                            
                            <div class="divider">
                                <span class="divider-text">Government Secure Login</span>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted d-block">
                                    <i class="fas fa-shield-alt me-1"></i>Your government login is secured with encryption
                                </small>
                            </div>
                        </form>
                        
                        <div class="text-center mt-5 pt-4 border-top">
                            <p class="mb-3">Don't have an LGU account?</p>
                            <a href="register_lgu.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-1"></i> Register LGU Account
                            </a>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Are you a veterinarian? 
                                <a href="login_vet.php" class="register-link">Login to veterinary portal here</a>
                            </small>
                        </div>
                        
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Are you a pet owner? 
                                <a href="../login.php" class="register-link">Login to pet portal here</a>
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

</html>
