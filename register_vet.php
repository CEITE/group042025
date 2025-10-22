<?php
session_start();
include("conn.php");

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: vet_dashboard.php");
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number']);
    $specialization = trim($_POST['specialization']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
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
    $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    
    if ($check_email->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // FIXED: Remove created_at from the query since it doesn't exist in your table
        $insert_query = "
        INSERT INTO users (name, email, phone_number, license_number, specialization, password, role) 
        VALUES (?, ?, ?, ?, ?, ?, 'vet')
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssssss", $name, $email, $phone_number, $license_number, $specialization, $hashed_password);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Registration successful! Please login to continue.";
            header("Location: login_vet.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - VetCareQR Veterinary System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --light: #ecf0f1;
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .register-left {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .register-right {
            padding: 3rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }
        
        .feature-list li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .feature-list i {
            background: rgba(255,255,255,0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e8f0fe;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .login-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 2rem;
            }
            
            .register-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="row g-0">
                <!-- Left Side - Information -->
                <div class="col-lg-6">
                    <div class="register-left">
                        <div class="logo">
                            <i class="fas fa-stethoscope me-2"></i>VetCareQR
                        </div>
                        <h2>Join Our Veterinary Network</h2>
                        <p class="mb-4">Register to access professional veterinary tools and manage pet medical records efficiently.</p>
                        
                        <ul class="feature-list">
                            <li>
                                <i class="fas fa-paw"></i>
                                <span>Manage Pet Medical Records</span>
                            </li>
                            <li>
                                <i class="fas fa-qrcode"></i>
                                <span>Generate Medical QR Codes</span>
                            </li>
                            <li>
                                <i class="fas fa-calendar-check"></i>
                                <span>Schedule Appointments</span>
                            </li>
                            <li>
                                <i class="fas fa-file-medical"></i>
                                <span>Track Medical History</span>
                            </li>
                            <li>
                                <i class="fas fa-bell"></i>
                                <span>Set Reminders & Follow-ups</span>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <small>Already have an account? </small>
                            <a href="login_vet.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-sign-in-alt me-1"></i> Login Here
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="col-lg-6">
                    <div class="register-right">
                        <h3 class="text-center mb-4">Veterinarian Registration</h3>
                        <p class="text-center text-muted mb-4">Create your professional account</p>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="license_number" class="form-label">License Number *</label>
                                    <input type="text" class="form-control" id="license_number" name="license_number" 
                                           value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>" 
                                           required>
                                    <small class="text-muted">Your veterinary license number</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
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
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="login-link">Terms of Service</a> and <a href="#" class="login-link">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-user-plus me-2"></i> Register as Veterinarian
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="login_vet.php" class="login-link">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (!strengthIndicator) {
                const indicator = document.createElement('div');
                indicator.id = 'password-strength';
                indicator.className = 'mt-1';
                this.parentNode.appendChild(indicator);
            }
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    feedback = '<small class="text-danger">Weak password</small>';
                    break;
                case 2:
                    feedback = '<small class="text-warning">Moderate password</small>';
                    break;
                case 3:
                    feedback = '<small class="text-info">Good password</small>';
                    break;
                case 4:
                    feedback = '<small class="text-success">Strong password</small>';
                    break;
            }
            
            document.getElementById('password-strength').innerHTML = feedback;
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const confirmFeedback = document.getElementById('confirm-feedback');
            
            if (!confirmFeedback) {
                const feedback = document.createElement('div');
                feedback.id = 'confirm-feedback';
                feedback.className = 'mt-1';
                this.parentNode.appendChild(feedback);
            }
            
            if (confirmPassword && password !== confirmPassword) {
                document.getElementById('confirm-feedback').innerHTML = '<small class="text-danger">Passwords do not match</small>';
            } else if (confirmPassword && password === confirmPassword) {
                document.getElementById('confirm-feedback').innerHTML = '<small class="text-success">Passwords match</small>';
            } else {
                document.getElementById('confirm-feedback').innerHTML = '';
            }
        });
    </script>
</body>
</html>
