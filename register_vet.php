<?php
session_start();
include("conn.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: vet_dashboard.php");
    exit();
}

// Handle registration
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $license_number = trim($_POST['license_number']);
    $specialization = trim($_POST['specialization'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($license_number)) {
        $errors[] = "Veterinary license number is required";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($check_email) {
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $check_email->store_result();
            
            if ($check_email->num_rows > 0) {
                $errors[] = "Email already registered";
            }
            $check_email->close();
        } else {
            $errors[] = "System error: Unable to verify email";
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into database
        try {
            $insert_query = "
            INSERT INTO users (name, email, phone, license_number, specialization, password, role, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'vet', NOW())
            ";
            
            $stmt = $conn->prepare($insert_query);
            if ($stmt) {
                $stmt->bind_param("ssssss", $name, $email, $phone, $license_number, $specialization, $hashed_password);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Registration successful! Please login to continue.";
                    header("Location: login_vet.php");
                    exit();
                } else {
                    $errors[] = "Registration failed. Please try again.";
                }
                $stmt->close();
            } else {
                $errors[] = "System error: Unable to create account";
            }
        } catch (Exception $e) {
            $errors[] = "Registration error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Registration - VetCareQR</title>
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
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
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
            border-radius: var(--radius);
            box-shadow: var(--shadow);
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
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--gray-light);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            transform: translateY(-2px);
            background: white;
        }
        
        .form-select {
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--gray-light);
            background: var(--light);
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 16px 32px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
            color: white;
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
            border-radius: var(--radius);
            border: none;
            padding: 1.2rem 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .alert-danger {
            background: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
            color: var(--gray);
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
        
        .strength-weak { background: var(--danger); width: 25%; }
        .strength-fair { background: var(--warning); width: 50%; }
        .strength-good { background: #f59e0b; width: 75%; }
        .strength-strong { background: var(--success); width: 100%; }
        
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
            background: var(--gray-light);
        }
        
        .divider-text {
            padding: 0 1rem;
            color: var(--gray);
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
                            <i class="fas fa-stethoscope"></i>
                            VetCareQR
                        </div>
                        <h2 class="mb-3 fw-bold">Join Our Veterinary Network</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.95;">
                            Register to access professional veterinary tools and manage pet medical records efficiently.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-paw"></i>
                                <span>Comprehensive Pet Medical Records</span>
                            </li>
                            <li>
                                <i class="fas fa-qrcode"></i>
                                <span>QR Code Medical History Access</span>
                            </li>
                            <li>
                                <i class="fas fa-calendar-check"></i>
                                <span>Appointment Scheduling System</span>
                            </li>
                            <li>
                                <i class="fas fa-file-medical"></i>
                                <span>Digital Health Certificates</span>
                            </li>
                            <li>
                                <i class="fas fa-bell"></i>
                                <span>Automated Follow-up Reminders</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.9;">Already a member? </small>
                            <a href="login_vet.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Your Account
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="col-lg-6">
                    <div class="register-right">
                        <div class="text-center mb-5">
                            <h3 class="mb-2 fw-bold" style="color: var(--primary);">Veterinarian Registration</h3>
                            <p class="text-muted">Create your professional veterinary account</p>
                        </div>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Registration Issues:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" novalidate>
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label for="name" class="form-label fw-semibold">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           placeholder="Dr. John Smith" required
                                           autocomplete="name">
                                    <div class="form-text">Enter your full professional name</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="email" class="form-label fw-semibold">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           placeholder="dr.smith@clinic.com" required
                                           autocomplete="email">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                           placeholder="+1 (555) 123-4567"
                                           autocomplete="tel">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="license_number" class="form-label fw-semibold">License Number *</label>
                                    <input type="text" class="form-control" id="license_number" name="license_number" 
                                           value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>"
                                           placeholder="VET-123456" required>
                                    <div class="form-text">Your veterinary license number</div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="specialization" class="form-label fw-semibold">Specialization</label>
                                    <select class="form-select" id="specialization" name="specialization">
                                        <option value="">Select Specialization</option>
                                        <option value="General Practice" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'General Practice') ? 'selected' : ''; ?>>General Practice</option>
                                        <option value="Surgery" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                        <option value="Dentistry" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Dentistry') ? 'selected' : ''; ?>>Dentistry</option>
                                        <option value="Dermatology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                        <option value="Emergency Care" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Emergency Care') ? 'selected' : ''; ?>>Emergency Care</option>
                                        <option value="Internal Medicine" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Internal Medicine') ? 'selected' : ''; ?>>Internal Medicine</option>
                                        <option value="Other" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="form-text">Your area of veterinary specialization</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="password" class="form-label fw-semibold">Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Minimum 6 characters" required
                                               autocomplete="new-password">
                                        <button type="button" class="input-group-text password-toggle" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="password-strength" class="password-strength"></div>
                                    <div id="password-feedback" class="form-text"></div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Re-enter your password" required
                                               autocomplete="new-password">
                                        <button type="button" class="input-group-text password-toggle" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="confirm-feedback" class="form-text"></div>
                                </div>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="login-link">Terms of Service</a> and <a href="#" class="login-link">Privacy Policy</a> *
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4 fw-bold">
                                <i class="fas fa-user-plus me-2"></i> Create Veterinary Account
                            </button>
                            
                            <div class="divider">
                                <span class="divider-text">Secure Registration</span>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="mb-3">Already have a veterinary account?</p>
                            <a href="login_vet.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Your Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function setupPasswordToggle(inputId, buttonId) {
            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    this.setAttribute('title', 'Hide password');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    this.setAttribute('title', 'Show password');
                }
            });
        }

        // Setup password toggles
        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('confirm_password', 'toggleConfirmPassword');

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            const feedback = document.getElementById('password-feedback');
            
            let strength = 0;
            let message = '';
            let strengthClass = '';
            
            // Length check
            if (password.length >= 8) strength++;
            else if (password.length >= 6) strength += 0.5;
            
            // Character variety checks
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            // Determine strength level
            if (password.length === 0) {
                message = 'Enter a password';
                strengthClass = '';
            } else if (password.length < 6) {
                message = 'Too short (min 6 characters)';
                strengthClass = 'strength-weak';
            } else if (strength < 3) {
                message = 'Weak password';
                strengthClass = 'strength-weak';
            } else if (strength < 4) {
                message = 'Fair password';
                strengthClass = 'strength-fair';
            } else if (strength < 5) {
                message = 'Good password';
                strengthClass = 'strength-good';
            } else {
                message = 'Strong password';
                strengthClass = 'strength-strong';
            }
            
            strengthBar.className = 'password-strength ' + strengthClass;
            feedback.textContent = message;
            feedback.className = 'form-text ' + 
                (strengthClass === 'strength-weak' ? 'text-danger' : 
                 strengthClass === 'strength-fair' ? 'text-warning' :
                 strengthClass === 'strength-good' ? 'text-info' :
                 strengthClass === 'strength-strong' ? 'text-success' : 'text-muted');
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const feedback = document.getElementById('confirm-feedback');
            
            if (confirmPassword.length === 0) {
                feedback.textContent = 'Please confirm your password';
                feedback.className = 'form-text text-muted';
            } else if (password !== confirmPassword) {
                feedback.textContent = 'Passwords do not match';
                feedback.className = 'form-text text-danger';
            } else {
                feedback.textContent = 'Passwords match';
                feedback.className = 'form-text text-success';
            }
        });

        // Form validation and loading state
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            let isValid = true;

            // Reset previous error states
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });

            // Validate required fields
            const requiredFields = this.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });

            // Validate email format
            const email = document.getElementById('email');
            if (email.value && !isValidEmail(email.value)) {
                email.classList.add('is-invalid');
                isValid = false;
            }

            // Validate password match
            if (password !== confirmPassword) {
                document.getElementById('confirm_password').classList.add('is-invalid');
                isValid = false;
            }

            // Validate terms
            if (!terms) {
                document.getElementById('terms').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = this.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            } else {
                // Add loading state
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
                submitButton.disabled = true;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Auto-focus on first field
        document.addEventListener('DOMContentLoaded', function() {
            const firstField = document.getElementById('name');
            if (firstField) {
                firstField.focus();
            }
        });
    </script>
</body>
</html>
