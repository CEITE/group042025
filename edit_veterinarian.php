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

// Get veterinarian ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: veterinarian.php");
    exit();
}

$vet_id = intval($_GET['id']);

// Get veterinarian details
$vet_query = "SELECT * FROM users WHERE user_id = ? AND role = 'vet'";
$vet_stmt = $conn->prepare($vet_query);
$vet_stmt->bind_param("i", $vet_id);
$vet_stmt->execute();
$vet_result = $vet_stmt->get_result();

if ($vet_result->num_rows === 0) {
    $_SESSION['error'] = "Veterinarian not found!";
    header("Location: veterinarian.php");
    exit();
}

$vet = $vet_result->fetch_assoc();
$vet_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $specialization = trim($_POST['specialization']);
    $status = $_POST['status'];
    $bio = trim($_POST['bio']);

    // Validate required fields
    if (empty($name) || empty($email) || empty($specialization)) {
        $_SESSION['error'] = "Please fill in all required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address!";
    } else {
        // Check if email already exists (excluding current vet)
        $email_check = "SELECT user_id FROM users WHERE email = ? AND user_id != ? AND role = 'vet'";
        $stmt_check = $conn->prepare($email_check);
        $stmt_check->bind_param("si", $email, $vet_id);
        $stmt_check->execute();
        $email_result = $stmt_check->get_result();
        
        if ($email_result->num_rows > 0) {
            $_SESSION['error'] = "Email already exists for another veterinarian!";
        } else {
            // Handle profile picture upload
            $profile_picture = $vet['profile_picture'];
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "uploads/profiles/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = "vet_" . $vet_id . "_" . time() . "." . $file_extension;
                $target_file = $upload_dir . $filename;
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($file_extension), $allowed_types)) {
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        // Delete old profile picture if it exists and is not the default
                        if ($profile_picture && !str_contains($profile_picture, 'ui-avatars.com')) {
                            unlink($profile_picture);
                        }
                        $profile_picture = $target_file;
                    }
                }
            }

            // Update veterinarian
            $update_query = "UPDATE users SET name = ?, email = ?, phone_number = ?, specialization = ?, status = ?, bio = ?, profile_picture = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssssssi", $name, $email, $phone_number, $specialization, $status, $bio, $profile_picture, $vet_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Veterinarian profile updated successfully!";
                header("Location: vet_profile.php?id=" . $vet_id);
                exit();
            } else {
                $_SESSION['error'] = "Error updating veterinarian: " . $stmt->error;
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
    <title>Edit Veterinarian - VetCareQR</title>
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
                <a href="vet_profile.php?id=<?php echo $vet_id; ?>" class="btn btn-outline-primary btn-sm me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Profile
                </a>
                <h1 class="h4 mb-0 fw-bold">Edit Veterinarian Profile</h1>
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
                        <!-- Left Column - Profile Picture -->
                        <div class="col-lg-4">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Profile Picture</label>
                                <div class="profile-picture-upload" onclick="document.getElementById('profile_picture').click()">
                                    <div class="mb-3">
                                        <img id="profile-preview" 
                                             src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=3b82f6&color=fff&size=150'; ?>" 
                                             class="profile-picture-preview" 
                                             alt="Profile Preview">
                                    </div>
                                    <div class="text-muted">
                                        <i class="fas fa-camera me-2"></i>
                                        Click to upload new photo
                                    </div>
                                    <input type="file" id="profile_picture" name="profile_picture" 
                                           accept="image/*" class="d-none" onchange="previewImage(this)">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $vet['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $vet['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo $vet['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                        </div>

                        <!-- Right Column - Form Fields -->
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($vet['name']); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($vet['email']); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone_number" 
                                           value="<?php echo htmlspecialchars($vet['phone_number'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Specialization <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="specialization" 
                                           list="specializations" 
                                           value="<?php echo htmlspecialchars($vet['specialization'] ?? ''); ?>" 
                                           required>
                                    <datalist id="specializations">
                                        <?php foreach ($specializations as $spec): ?>
                                            <option value="<?php echo htmlspecialchars($spec); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Bio/Description</label>
                                    <textarea class="form-control" name="bio" rows="4" 
                                              placeholder="Enter veterinarian's bio or description..."><?php echo htmlspecialchars($vet['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 pt-3 border-top">
                                <button type="submit" class="btn btn-brand">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                                <a href="vet_profile.php?id=<?php echo $vet_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <a href="veterinarian.php" class="btn btn-outline-primary ms-auto">
                                    <i class="fas fa-list me-2"></i>Back to List
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</body>
</html>