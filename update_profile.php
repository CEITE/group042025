<?php
session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT name, email, phone_number, address, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "This email is already registered by another user.";
        } else {
            // Handle profile picture upload
            $profile_picture = $user['profile_picture']; // Keep existing picture by default
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/profile_pictures/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file = $_FILES['profile_picture'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                // Validate file type
                if (in_array($file_extension, $allowed_extensions)) {
                    // Validate file size (max 2MB)
                    if ($file['size'] <= 2 * 1024 * 1024) {
                        // Generate unique filename
                        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Delete old profile picture if it exists and isn't the default
                            if (!empty($user['profile_picture']) && file_exists($user['profile_picture']) && 
                                !str_contains($user['profile_picture'], 'pravatar.cc')) {
                                unlink($user['profile_picture']);
                            }
                            $profile_picture = $upload_path;
                        } else {
                            $error = "Error uploading profile picture.";
                        }
                    } else {
                        $error = "Profile picture must be less than 2MB.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                }
            }
            
            if (empty($error)) {
                // Update user profile
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, address = ?, profile_picture = ? WHERE user_id = ?");
                $update_stmt->bind_param("sssssi", $name, $email, $phone_number, $address, $profile_picture, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = "Profile updated successfully!";
                    // Update session name if changed
                    $_SESSION['user_name'] = $name;
                    // Refresh user data
                    $user = ['name' => $name, 'email' => $email, 'phone_number' => $phone_number, 'address' => $address, 'profile_picture' => $profile_picture];
                } else {
                    $error = "Error updating profile: " . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .profile-picture-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffd6e7;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .profile-picture:hover {
            transform: scale(1.05);
        }
        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
            border: 4px solid #ffd6e7;
            margin: 0 auto;
        }
        .upload-btn {
            margin-top: 1rem;
        }
        .picture-preview {
            display: none;
            max-width: 150px;
            margin: 1rem auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="profile-header text-center mb-4">
                <h3>Edit Profile</h3>
                <p class="text-muted">Update your personal information and profile picture</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Profile Picture Section -->
                <div class="profile-picture-container">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture"
                             id="currentProfilePicture">
                    <?php else: ?>
                        <div class="profile-picture-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-btn">
                        <input type="file" 
                               class="form-control d-none" 
                               id="profile_picture" 
                               name="profile_picture" 
                               accept="image/*">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('profile_picture').click()">
                            <i class="fas fa-camera me-1"></i> Change Picture
                        </button>
                        <?php if (!empty($user['profile_picture'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeProfilePicture()">
                                <i class="fas fa-trash me-1"></i> Remove
                            </button>
                        <?php endif; ?>
                    </div>
                    <img id="picturePreview" class="picture-preview rounded-circle">
                    <div class="form-text">Max file size: 2MB. Allowed: JPG, JPEG, PNG, GIF</div>
                </div>

                <!-- Personal Information -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="user_dashboard.php" class="btn btn-secondary me-md-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('picturePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Hide current profile picture
                    const currentPic = document.getElementById('currentProfilePicture');
                    if (currentPic) {
                        currentPic.style.display = 'none';
                    }
                }
                reader.readAsDataURL(file);
            }
        });

        function removeProfilePicture() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                // Create a hidden field to indicate picture removal
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'remove_picture';
                hiddenInput.value = '1';
                document.querySelector('form').appendChild(hiddenInput);
                
                // Submit the form
                document.querySelector('form').submit();
            }
        }

        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
