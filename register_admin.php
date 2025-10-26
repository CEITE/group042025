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

// Handle registration
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Full name is required and must be at least 2 characters";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password is required and must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $check_query = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        
        if ($check_stmt) {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = "Email address is already registered";
            }
            
            $check_stmt->close();
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new admin user
        $insert_query = "INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        
        if ($insert_stmt) {
            $insert_stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                $success = true;
                $_SESSION['success'] = "Administrator account created successfully! You can now login.";
                header("Location: admin_login.php");
                exit();
            } else {
                $errors[] = "System error: Unable to create account. Please try again.";
            }
            
            $insert_stmt->close();
        } else {
            $errors[] = "System error: Unable to process registration";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Registration - VetCareQR</title>
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
        
        .register-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .register-left {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        
        .register-right {
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
        
        .login-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
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
        
        .password-strength {
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .password-weak {
            background: var(--danger);
            width: 25%;
        }
        
        .password-medium {
            background: var(--warning);
            width: 50%;
        }
        
        .password-strong {
            background: var(--success);
            width: 75%;
        }
        
        .password-very-strong {
            background: var(--success);
            width: 100%;
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
            .register-left {
                padding: 3rem 2rem;
            }
            
            .register-right {
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
            
            .register-left,
            .register-right {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="register-container">
            <div class="row g-0">
                <!-- Left Side - Information -->
                <div class="col-lg-6">
                    <div class="register-left">
                        <div class="logo">
                            <i class="fas fa-user-shield"></i>
                            VetCareQR
                        </div>
                        <h2 class="mb-3 fw-bold">Administrator Registration</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.95;">
                            Create a new administrator account to manage the VetCareQR system and its users.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-user-cog"></i>
                                <span>Full System Administration</span>
                            </li>
                            <li>
                                <i class="fas fa-users"></i>
                                <span>User Management & Permissions</span>
                            </li>
                            <li>
                                <i class="fas fa-chart-pie"></i>
                                <span>System Analytics & Reports</span>
                            </li>
                            <li>
                                <i class="fas fa-cogs"></i>
                                <span>Configuration & Settings</span>
                            </li>
                            <li>
                                <i class="fas fa-database"></i>
                                <span>Database Management Tools</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.9;">Already have an account? </small>
                            <a href="admin_login.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Admin Panel
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="col-lg-6">
                    <div class="register-right">
                        <div class="text-center mb-5">
                            <h3 class="mb-2 fw-bold" style="color: var(--primary);">Create Admin Account</h3>
                            <p class="text-muted">Register a new system administrator account</p>
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
                                <label for="name" class="form-label fw-semibold">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           placeholder="Enter your full name" required
                                           autocomplete="name">
                                </div>
                                <div class="form-text">Enter your complete name as it should appear in the system</div>
                            </div>
                            
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
                                <div class="form-text">This will be your administrator login email</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Create a strong password" required
                                           autocomplete="new-password">
                                    <button type="button" class="input-group-text password-toggle" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Password must be at least 8 characters long</div>
                                <div id="password-strength" class="password-strength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm your password" required
                                           autocomplete="new-password">
                                    <button type="button" class="input-group-text password-toggle" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Re-enter your password for verification</div>
                                <div id="password-match" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="login-link">Terms of Service</a> and <a href="#" class="login-link">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4 fw-bold">
                                <i class="fas fa-user-plus me-2"></i> Create Administrator Account
                            </button>
                            
                            <div class="divider">
                                <span class="divider-text">Secure Registration</span>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted d-block">
                                    <i class="fas fa-shield-alt me-1"></i>Your information is protected with encryption
                                </small>
                            </div>
                        </form>
                        
                        <div class="text-center mt-5 pt-4 border-top">
                            <p class="mb-3">Already have an administrator account?</p>
                            <a href="admin_login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Admin Panel
                            </a>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Are you a veterinarian? 
                                <a href="register_vet.php" class="login-link">Register vet account here</a>
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

        // Toggle confirm password visibility
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('title', 'Hide password');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('title', 'Show password');
            }
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;
            
            strengthBar.className = 'password-strength';
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.style.background = 'transparent';
            } else if (strength === 1) {
                strengthBar.className += ' password-weak';
            } else if (strength === 2) {
                strengthBar.className += ' password-medium';
            } else if (strength === 3) {
                strengthBar.className += ' password-strong';
            } else if (strength === 4) {
                strengthBar.className += ' password-very-strong';
            }
        });

        // Password match indicator
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchIndicator = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchIndicator.innerHTML = '';
            } else if (password === confirmPassword) {
                matchIndicator.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</small>';
            } else {
                matchIndicator.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</small>';
            }
        });

        // Auto-focus on name field
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            if (nameField && !nameField.value) {
                nameField.focus();
            }
        });

        // Add loading state to form submission
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
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