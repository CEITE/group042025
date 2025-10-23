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

// Handle registration
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $license_number = trim($_POST['license_number']);
    $position = trim($_POST['position']);
    $lgu_name = trim($_POST['lgu_name']);
    $region = trim($_POST['region']);
    $province = trim($_POST['province']);
    $city = trim($_POST['city']);
    $barangay = trim($_POST['barangay'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($license_number)) {
        $errors[] = "LGU identification number is required";
    }
    
    if (empty($position)) {
        $errors[] = "Position is required";
    }
    
    if (empty($lgu_name)) {
        $errors[] = "LGU name is required";
    }
    
    if (empty($region)) {
        $errors[] = "Region is required";
    }
    
    if (empty($province)) {
        $errors[] = "Province is required";
    }
    
    if (empty($city)) {
        $errors[] = "City/Municipality is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_result = $check_email->get_result();
        
        if ($email_result->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        
        // Check if license number already exists
        $check_license = $conn->prepare("SELECT user_id FROM users WHERE license_number = ?");
        $check_license->bind_param("s", $license_number);
        $check_license->execute();
        $license_result = $check_license->get_result();
        
        if ($license_result->num_rows > 0) {
            $errors[] = "LGU identification number already registered";
        }
        
        if (empty($errors)) {
            // Insert new LGU user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'lgu';
            
            $query = "INSERT INTO users (name, email, phone_number, license_number, specialization, address, region, province, city, barangay, password, role) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            
            if ($stmt) {
                // Use LGU name as the main name field, position as specialization
                $stmt->bind_param("ssssssssssss", $lgu_name, $email, $phone_number, $license_number, $position, $address, $region, $province, $city, $barangay, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $success = "LGU account created successfully! You can now login.";
                    // Clear form
                    $_POST = array();
                } else {
                    $errors[] = "Registration failed: " . $conn->error;
                }
                
                $stmt->close();
            } else {
                $errors[] = "System error: Unable to process registration";
            }
        }
        
        $check_email->close();
        $check_license->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Registration - VetCareQR</title>
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
        
        .register-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            max-width: 1200px;
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
            padding: 3rem;
            background: white;
            position: relative;
            max-height: 100vh;
            overflow-y: auto;
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
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #f3f4f6;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f0f9ff;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }
        
        .input-group-text {
            background: #f0f9ff;
            border: 2px solid #f3f4f6;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--primary);
        }
        
        .form-control:not(:first-child) {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
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
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 10px;
            padding: 10px 24px;
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
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
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
        
        .form-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .form-section h6 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 3rem 2rem;
            }
            
            .register-right {
                padding: 2rem;
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
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="register-container">
            <div class="row g-0">
                <!-- Left Side - Information -->
                <div class="col-lg-5">
                    <div class="register-left">
                        <div class="logo">
                            <i class="fas fa-landmark"></i>
                            VetCareQR LGU
                        </div>
                        <h2 class="mb-3 fw-bold">Register LGU Account</h2>
                        <p class="mb-4" style="font-size: 1.1rem; opacity: 0.95;">
                            Create your Local Government Unit account to access veterinary compliance monitoring and public health management tools.
                        </p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-chart-bar"></i>
                                <span>Compliance Analytics Dashboard</span>
                            </li>
                            <li>
                                <i class="fas fa-file-contract"></i>
                                <span>License & Permit Management</span>
                            </li>
                            <li>
                                <i class="fas fa-map-marked-alt"></i>
                                <span>Regional Oversight Tools</span>
                            </li>
                            <li>
                                <i class="fas fa-bullhorn"></i>
                                <span>Public Health Communications</span>
                            </li>
                            <li>
                                <i class="fas fa-database"></i>
                                <span>Centralized Data Management</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small style="opacity: 0.9;">Already have an LGU account? </small>
                            <a href="login_lgu.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-sign-in-alt me-1"></i> Login Here
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="col-lg-7">
                    <div class="register-right">
                        <div class="text-center mb-4">
                            <h3 class="mb-2 fw-bold" style="color: var(--primary);">LGU Official Registration</h3>
                            <p class="text-muted">Create your government administration account</p>
                        </div>
                        
                        <!-- Success Messages -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
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
                            <!-- LGU Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-building me-2"></i>LGU Information</h6>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="lgu_name" class="form-label fw-semibold">LGU Name *</label>
                                        <input type="text" class="form-control" id="lgu_name" name="lgu_name" 
                                               value="<?php echo isset($_POST['lgu_name']) ? htmlspecialchars($_POST['lgu_name']) : ''; ?>" 
                                               placeholder="Municipality of Sample" required>
                                        <div class="form-text">Official name of your Local Government Unit</div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="license_number" class="form-label fw-semibold">LGU Identification Number *</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number" 
                                               value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>" 
                                               placeholder="LGU-2024-001" required>
                                        <div class="form-text">Official LGU identification or authorization number</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label fw-semibold">Your Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                               placeholder="Juan Dela Cruz" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="position" class="form-label fw-semibold">Your Position *</label>
                                        <input type="text" class="form-control" id="position" name="position" 
                                               value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>" 
                                               placeholder="Municipal Agriculturist" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-address-card me-2"></i>Contact Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label fw-semibold">Official Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                               placeholder="lgu.official@municipality.gov.ph" required>
                                        <div class="form-text">Your official government email address</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone_number" class="form-label fw-semibold">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" 
                                               placeholder="+63 912 345 6789" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Location Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-map-marker-alt me-2"></i>Location Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="region" class="form-label fw-semibold">Region *</label>
                                        <select class="form-control" id="region" name="region" required>
                                            <option value="">Select Region</option>
                                            <option value="NCR" <?php echo (isset($_POST['region']) && $_POST['region'] === 'NCR') ? 'selected' : ''; ?>>National Capital Region (NCR)</option>
                                            <option value="Region I" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region I') ? 'selected' : ''; ?>>Region I (Ilocos Region)</option>
                                            <option value="Region II" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region II') ? 'selected' : ''; ?>>Region II (Cagayan Valley)</option>
                                            <option value="Region III" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region III') ? 'selected' : ''; ?>>Region III (Central Luzon)</option>
                                            <option value="Region IV-A" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region IV-A') ? 'selected' : ''; ?>>Region IV-A (CALABARZON)</option>
                                            <option value="Region IV-B" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region IV-B') ? 'selected' : ''; ?>>Region IV-B (MIMAROPA)</option>
                                            <option value="Region V" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region V') ? 'selected' : ''; ?>>Region V (Bicol Region)</option>
                                            <option value="Region VI" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region VI') ? 'selected' : ''; ?>>Region VI (Western Visayas)</option>
                                            <option value="Region VII" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region VII') ? 'selected' : ''; ?>>Region VII (Central Visayas)</option>
                                            <option value="Region VIII" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region VIII') ? 'selected' : ''; ?>>Region VIII (Eastern Visayas)</option>
                                            <option value="Region IX" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region IX') ? 'selected' : ''; ?>>Region IX (Zamboanga Peninsula)</option>
                                            <option value="Region X" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region X') ? 'selected' : ''; ?>>Region X (Northern Mindanao)</option>
                                            <option value="Region XI" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region XI') ? 'selected' : ''; ?>>Region XI (Davao Region)</option>
                                            <option value="Region XII" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region XII') ? 'selected' : ''; ?>>Region XII (SOCCSKSARGEN)</option>
                                            <option value="Region XIII" <?php echo (isset($_POST['region']) && $_POST['region'] === 'Region XIII') ? 'selected' : ''; ?>>Region XIII (Caraga)</option>
                                            <option value="CAR" <?php echo (isset($_POST['region']) && $_POST['region'] === 'CAR') ? 'selected' : ''; ?>>Cordillera Administrative Region (CAR)</option>
                                            <option value="BARMM" <?php echo (isset($_POST['region']) && $_POST['region'] === 'BARMM') ? 'selected' : ''; ?>>Bangsamoro (BARMM)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="province" class="form-label fw-semibold">Province *</label>
                                        <input type="text" class="form-control" id="province" name="province" 
                                               value="<?php echo isset($_POST['province']) ? htmlspecialchars($_POST['province']) : ''; ?>" 
                                               placeholder="Laguna" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label fw-semibold">City/Municipality *</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" 
                                               placeholder="Santa Cruz" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="barangay" class="form-label fw-semibold">Barangay</label>
                                        <input type="text" class="form-control" id="barangay" name="barangay" 
                                               value="<?php echo isset($_POST['barangay']) ? htmlspecialchars($_POST['barangay']) : ''; ?>" 
                                               placeholder="Poblacion">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="address" class="form-label fw-semibold">Full Address</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" 
                                               placeholder="Municipal Hall, Main Street">
                                    </div>
                                </div>
                            </div>

                            <!-- Account Security -->
                            <div class="form-section">
                                <h6><i class="fas fa-lock me-2"></i>Account Security</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label fw-semibold">Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter password" required>
                                            <button type="button" class="input-group-text password-toggle" data-target="password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Minimum 8 characters</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label fw-semibold">Confirm Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                   placeholder="Confirm password" required>
                                            <button type="button" class="input-group-text password-toggle" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" class="login-link">Terms of Service</a> and <a href="#" class="login-link">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4 fw-bold">
                                <i class="fas fa-user-plus me-2"></i> Register LGU Account
                            </button>
                        </form>
                        
                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="mb-3">Already have an LGU account?</p>
                            <a href="login_lgu.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to LGU Dashboard
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
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            if (password.value && confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--danger)';
                } else {
                    confirmPassword.style.borderColor = '';
                }
            }
        }

        password.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('input', validatePasswordMatch);

        // Add loading state to form submission
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Registering...';
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