<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if already logged in as vet
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'vet') {
    header("Location: vet_dashboard.php");
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
        // Check if user exists and is a vet
        $query = "SELECT user_id, name, email, password, role, profile_picture FROM users WHERE email = ? AND role = 'vet'";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Debug: Check what we're getting from database
                error_log("Vet login attempt: " . $email . ", Role: " . $user['role']);
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];
                    
                    $_SESSION['success'] = "Welcome back, Dr. " . $user['name'] . "!";
                    
                    // Update last login if the column exists
                    $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    if ($update_stmt) {
                        $update_stmt->bind_param("i", $user['user_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    header("Location: vet_dashboard.php");
                    exit();
                } else {
                    error_log("Password verification failed for vet: " . $email);
                    $errors[] = "Invalid email or password";
                }
            } else {
                error_log("No vet user found with email: " . $email);
                $errors[] = "Invalid email or password, or account is not a veterinarian account";
            }
            
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Login - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --light: #ecf0f1;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
        }
        
        .login-left {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 3rem;
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
            padding: 3rem;
            background: white;
        }
        
        .logo {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 2.5rem 0;
        }
        
        .feature-list li {
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
        }
        
        .feature-list i {
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 15px 20px;
            border: 2px solid #e8f0fe;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background: white;
            border: 2px solid #e8f0fe;
            border-right: none;
            border-radius: 12px 0 0 12px;
        }
        
        .form-control:not(:first-child) {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }
        
        .btn-outline-light {
            border: 2px solid white;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
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
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: var(--primary);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 2rem;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .logo {
                font-size: 1.8rem;
            }
            
            .feature-list li {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .login-left,
            .login-right {
                padding: 1.5rem;
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
                            <i class="fas fa-stethoscope"></i>
                            VetCareQR
                        </div>
                        <h2 class="mb-3">Welcome Back, Doctor</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.9;">
                            Access your veterinary dashboard to manage pet medical records and provide quality care.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure Medical Records Management</span>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>24/7 Access to Patient Data</span>
                            </li>
                            <li>
                                <i class="fas fa-chart-line"></i>
                                <span>Track Patient Health Progress</span>
                            </li>
                            <li>
                                <i class="fas fa-bell"></i>
                                <span>Automated Appointment Reminders</span>
                            </li>
                            <li>
                                <i class="fas fa-file-medical"></i>
                                <span>Digital Health Certificates</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.8;">New veterinary professional? </small>
                            <a href="register_vet.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-user-plus me-1"></i> Register Veterinary Account
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="col-lg-6">
                    <div class="login-right">
                        <div class="text-center mb-4">
                            <h3 class="mb-2">Veterinarian Login</h3>
                            <p class="text-muted">Sign in to your professional veterinary account</p>
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
                                        <i class="fas fa-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="your.email@clinic.com" required
                                           autocomplete="email">
                                </div>
                                <div class="form-text">Enter your registered veterinary email address</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your password" required
                                           autocomplete="current-password">
                                    <button type="button" class="input-group-text password-toggle" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Enter your account password</div>
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
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Veterinary Dashboard
                            </button>
                            
                            <div class="text-center mb-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div style="flex: 1; height: 1px; background: #e9ecef;"></div>
                                    <span class="px-3 text-muted small">Secure Login</span>
                                    <div style="flex: 1; height: 1px; background: #e9ecef;"></div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted d-block">
                                    <i class="fas fa-lock me-1"></i>Your login is secured with encryption
                                </small>
                            </div>
                        </form>
                        
                        <div class="text-center mt-5 pt-4 border-top">
                            <p class="mb-3">Don't have a veterinary account?</p>
                            <a href="register_vet.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-1"></i> Register as Veterinarian
                            </a>
                        </div>
                        
                        <div class="text-center mt-4">
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

        // Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                const form = document.querySelector('form');
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.click();
            }
        });

        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
            
            // Add loading state to form submission
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
                submitButton.disabled = true;
            });
            
            // Auto-dismiss alerts after 8 seconds
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            let isValid = true;

            // Reset previous error states
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });

            // Validate email
            if (!email || !isValidEmail(email)) {
                document.getElementById('email').classList.add('is-invalid');
                isValid = false;
            }

            // Validate password
            if (!password) {
                document.getElementById('password').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                // Show error message
                if (!document.querySelector('.alert-danger')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Please fill in all required fields correctly.
                    `;
                    document.querySelector('.login-right').insertBefore(errorDiv, document.querySelector('form'));
                    
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        errorDiv.remove();
                    }, 5000);
                }
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    </script>
</body>
</html>
