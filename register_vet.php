<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simple connection test
echo "<!-- Starting registration process -->";

try {
    include("conn.php");
    echo "<!-- Database connection successful -->";
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: vet_dashboard.php");
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- Form submitted -->";
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    echo "<!-- Name: $name, Email: $email -->";
    
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
    if (empty($errors)) {
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($check_email) {
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $check_email->store_result();
            
            if ($check_email->num_rows > 0) {
                $errors[] = "Email already registered";
            }
        } else {
            $errors[] = "Database query preparation failed";
        }
    }
    
    echo "<!-- Errors: " . count($errors) . " -->";
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into database
        $insert_query = "INSERT INTO users (name, email, phone, license_number, specialization, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'vet', NOW())";
        
        $stmt = $conn->prepare($insert_query);
        
        if ($stmt) {
            $stmt->bind_param("ssssss", $name, $email, $phone, $license_number, $specialization, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful! Please login to continue.";
                header("Location: login_vet.php");
                exit();
            } else {
                $errors[] = "Registration failed: " . $conn->error;
            }
        } else {
            $errors[] = "Failed to prepare statement: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">Veterinarian Registration</h2>
            
            <!-- Debug Info -->
            <div class="alert alert-info">
                <strong>Debug Mode:</strong> This page should show errors if any occur.
            </div>
            
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
                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">License Number *</label>
                    <input type="text" class="form-control" name="license_number" required 
                           value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            
            <div class="text-center mt-3">
                <a href="login_vet.php">Already have an account? Login here</a>
            </div>
        </div>
    </div>
</body>
</html>
