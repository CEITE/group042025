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

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT name, email, profile_picture FROM users WHERE user_id = ?";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();
$admin_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $specialization = trim($_POST['specialization']);
    $status = $_POST['status'];
    $bio = trim($_POST['bio']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate required fields
    if (empty($name) || empty($email) || empty($specialization) || empty($password)) {
        $_SESSION['error'] = "Please fill in all required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address!";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
    } else {
        // Check if email already exists
        $email_check = "SELECT user_id FROM users WHERE email = ?";
        $stmt_check = $conn->prepare($email_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $email_result = $stmt_check->get_result();
        
        if ($email_result->num_rows > 0) {
            $_SESSION['error'] = "Email already exists! Please use a different email address.";
        } else {
            // Handle profile picture upload
            $profile_picture = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "uploads/profiles/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = "vet_" . time() . "." . $file_extension;
                $target_file = $upload_dir . $filename;
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($file_extension), $allowed_types)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        $profile_picture = $target_file;
                    }
                }
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert veterinarian
            $insert_query = "INSERT INTO users (name, email, phone_number, specialization, status, bio, profile_picture, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'vet', NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssssss", $name, $email, $phone_number, $specialization, $status, $bio, $profile_picture, $hashed_password);
            
            if ($stmt->execute()) {
                $new_vet_id = $stmt->insert_id;
                $_SESSION['success'] = "Veterinarian added successfully!";
                
                // Send welcome email (simulated)
                $email_sent = true; // In real application, implement email sending
                
                if ($email_sent) {
                    $_SESSION['success'] .= " Welcome email sent to " . htmlspecialchars($email);
                }
                
                header("Location: vet_profile.php?id=" . $new_vet_id);
                exit();
            } else {
                $_SESSION['error'] = "Error adding veterinarian: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

// Get specializations for dropdown
$specializations_query = "SELECT DISTINCT specialization FROM users WHERE role = 'vet' AND specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
$specializations_result = $conn->query($specializations_query);
$specializations = [];
while ($row = $specializations_result->fetch_assoc()) {
    $specializations[] = $row['specialization'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Veterinarian - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f0f8ff;
            --card: #ffffff;
            --ink: #1e3a8a;
            --muted: #64748b;
            --brand: #3b82f6;
            --brand-2: #2563eb;
            --warning: #f59e0b;
            --danger: #dc2626;
            --lav: #1d4ed8;
            --success: #059669;
            --info: #0ea5e9;
            --shadow: 0 10px 30px rgba(59, 130, 246, 0.1);
            --radius: 1.25rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(180deg, #f0f9ff 0%, #f0f8ff 40%, #f0f8ff 100%);
            color: var(--ink);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        .app-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
            gap: 24px;
            padding: 24px;
            max-width: 1920px;
            margin: 0 auto;
        }

        @media (max-width: 992px) {
            .app-shell {
                grid-template-columns: 1fr;
                padding: 16px;
                gap: 16px;
            }
        }

        .sidebar {
            background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
            color: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link {
            color: #e0f2fe;
            border-radius: 12px;
            padding: 14px 16px;
            font-weight: 600;
            transition: var(--transition);
            margin-bottom: 4px;
            text-decoration: none;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
            transform: translateX(8px);
        }

        .card-soft {
            background: var(--card);
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.8);
            overflow: hidden;
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            border: none;
            color: white;
            font-weight: 800;
            border-radius: 14px;
            padding: 12px 20px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-brand:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .profile-picture-upload {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .profile-picture-upload:hover {
            border-color: var(--brand);
            background: #f8fafc;
        }

        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand);
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #dc2626; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #3b82f6; width: 75%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="me-3"><i class="fa-solid fa-user-shield fa-lg"></i></div>
                <div class="h4 mb-0 fw-bold">VetCareQR</div>
            </div>
            
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center" href="admin_dashboard.php">
                    <i class="fa-solid fa-gauge-high me-3"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
                    <i class="fa-solid fa-user-doctor me-3"></i>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pet_owners.php">
                    <i class="fa-solid fa-users me-3"></i>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pets.php">
                    <i class="fa-solid fa-paw me-3"></i>
                    <span>Pets</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="appointments.php">
                    <i class="fa-solid fa-calendar-check me-3"></i>
                    <span>Appointments</span>
                </a>
            </nav>

            <div class="mt-auto pt-4">
                <div class="admin-profile d-flex align-items-center p-3 rounded-3 bg-white bg-opacity-10">
                    <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
                         class="rounded-circle me-3" width="50" height="50" alt="Admin" />
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($admin['name']); ?></div>
                        <small class="text-white-50">Administrator</small>
                    </div>
                    <a href="logout.php" class="text-white-50"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="d-flex flex-column gap-4">
            <!-- Back Button -->
            <div class="d-flex align-items-center mb-3">
                <a href="veterinarian.php" class="btn btn-outline-primary btn-sm me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Veterinarians
                </a>
                <h1 class="h4 mb-0 fw-bold">Add New Veterinarian</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card-soft p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Left Column - Profile Picture & Status -->
                        <div class="col-lg-4">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Profile Picture</label>
                                <div class="profile-picture-upload" onclick="document.getElementById('profile_picture').click()">
                                    <div class="mb-3">
                                        <img id="profile-preview" 
                                             src="https://ui-avatars.com/api/?name=New+Veterinarian&background=3b82f6&color=fff&size=150" 
                                             class="profile-picture-preview" 
                                             alt="Profile Preview">
                                    </div>
                                    <div class="text-muted">
                                        <i class="fas fa-camera me-2"></i>
                                        Click to upload profile photo
                                    </div>
                                    <input type="file" id="profile_picture" name="profile_picture" 
                                           accept="image/*" class="d-none" onchange="previewImage(this)">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
                                </select>
                                <div class="form-text">
                                    Active veterinarians can log in immediately.
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Form Fields -->
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           required placeholder="Enter full name">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required placeholder="Enter email address">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone_number" 
                                           value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" 
                                           placeholder="Enter phone number">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Specialization <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="specialization" 
                                           list="specializations" 
                                           value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>" 
                                           required placeholder="Enter specialization">
                                    <datalist id="specializations">
                                        <?php foreach ($specializations as $spec): ?>
                                            <option value="<?php echo htmlspecialchars($spec); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" 
                                           id="password" required 
                                           placeholder="Enter password" onkeyup="checkPasswordStrength()">
                                    <div id="password-strength" class="password-strength"></div>
                                    <div class="form-text">
                                        Password must be at least 6 characters long.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           id="confirm_password" required 
                                           placeholder="Confirm password" onkeyup="checkPasswordMatch()">
                                    <div id="password-match" class="form-text"></div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Bio/Description</label>
                                    <textarea class="form-control" name="bio" rows="4" 
                                              placeholder="Enter veterinarian's bio or description..."><?php echo isset($_POST['bio']) ? htmlspecialchars($_POST['bio']) : ''; ?></textarea>
                                    <div class="form-text">
                                        Optional: Add a brief description about the veterinarian's experience and expertise.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 pt-3 border-top">
                                <button type="submit" class="btn btn-brand">
                                    <i class="fas fa-user-plus me-2"></i>Add Veterinarian
                                </button>
                                <a href="veterinarian.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('profile-preview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthBar.className = 'password-strength ';
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
            } else if (strength <= 1) {
                strengthBar.className += 'strength-weak';
            } else if (strength === 2) {
                strengthBar.className += 'strength-fair';
            } else if (strength === 3) {
                strengthBar.className += 'strength-good';
            } else {
                strengthBar.className += 'strength-strong';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchText.innerHTML = '';
            } else if (password === confirmPassword) {
                matchText.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
            } else {
                matchText.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = document.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
        });
    </script>
</body>
</html>