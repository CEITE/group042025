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
            
            // Check if user wants to remove profile picture
            if (isset($_POST['remove_picture']) && $_POST['remove_picture'] == '1') {
                // Delete old profile picture if it exists and isn't the default
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture']) && 
                    !str_contains($user['profile_picture'], 'pravatar.cc')) {
                    unlink($user['profile_picture']);
                }
                $profile_picture = null;
            }
            // Handle new profile picture upload
            elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
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
        :root {
            --primary-pink: #e91e63;
            --secondary-pink: #f8bbd9;
            --light-pink: #fce4ec;
            --dark-pink: #ad1457;
            --accent-pink: #f48fb1;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --dark-color: #37474f;
            --light-color: #fafafa;
        }
        
        body {
            background: linear-gradient(135deg, var(--light-pink) 0%, #f3e5f5 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 1rem;
        }
        
        .profile-container {
            max-width: 700px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(233, 30, 99, 0.1);
            overflow: hidden;
            position: relative;
            animation: fadeIn 0.5s ease-in;
        }
        
        .profile-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-pink), var(--accent-pink));
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--light-pink), #f3e5f5);
            padding: 2rem 2rem 1rem;
            text-align: center;
            border-bottom: 1px solid var(--secondary-pink);
        }
        
        .profile-content {
            padding: 2rem;
        }
        
        .profile-picture-container {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .profile-picture-wrapper {
            display: inline-block;
            position: relative;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        
        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--secondary-pink);
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.15);
        }
        
        .profile-picture:hover {
            transform: scale(1.05);
            border-color: var(--primary-pink);
            box-shadow: 0 12px 30px rgba(233, 30, 99, 0.25);
        }
        
        .profile-picture-placeholder {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-pink), var(--secondary-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--primary-pink);
            border: 5px solid var(--secondary-pink);
            margin: 0 auto;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.15);
        }
        
        .profile-picture-placeholder:hover {
            border-color: var(--primary-pink);
            transform: scale(1.05);
            box-shadow: 0 12px 30px rgba(233, 30, 99, 0.25);
        }
        
        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.7), rgba(244, 143, 177, 0.7));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-picture-wrapper:hover .profile-picture-overlay {
            opacity: 1;
        }
        
        .profile-picture-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        .upload-btn {
            margin-top: 1rem;
        }
        
        .picture-preview {
            display: none;
            max-width: 150px;
            margin: 1rem auto;
            border-radius: 50%;
            border: 3px solid var(--accent-pink);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-custom {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--accent-pink));
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-pink), var(--primary-pink));
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(233, 30, 99, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-pink);
            color: var(--primary-pink);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-pink);
            border-color: var(--primary-pink);
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(233, 30, 99, 0.3);
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .btn-outline-danger:hover {
            background: var(--danger-color);
            transform: translateY(-3px);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-3px);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 0.25rem rgba(233, 30, 99, 0.15);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-pink);
            margin-bottom: 0.5rem;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 1rem 1.5rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
            border-left: 5px solid var(--success-color);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            border-left: 5px solid var(--danger-color);
        }
        
        h3 {
            color: var(--dark-pink);
            font-weight: 700;
        }
        
        .text-muted {
            color: #78909c !important;
        }
        
        .file-info {
            background: var(--light-pink);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            text-align: center;
            border-left: 4px solid var(--accent-pink);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-pink);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .profile-container {
                margin: 1rem auto;
            }
            
            .profile-content {
                padding: 1.5rem;
            }
            
            .profile-picture, .profile-picture-placeholder {
                width: 140px;
                height: 140px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <h3><i class="fas fa-user-edit me-2"></i>Edit Profile</h3>
                <p class="text-muted">Update your personal information and profile picture</p>
            </div>

            <div class="profile-content">
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

                <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                    <!-- Profile Picture Section -->
                    <div class="profile-picture-container">
                        <div class="profile-picture-wrapper" onclick="document.getElementById('profile_picture').click()">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                     alt="Profile Picture" 
                                     class="profile-picture"
                                     id="currentProfilePicture"
                                     onerror="handleImageError(this)">
                            <?php else: ?>
                                <div class="profile-picture-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="profile-picture-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        
                        <input type="file" 
                               class="form-control d-none" 
                               id="profile_picture" 
                               name="profile_picture" 
                               accept="image/*">
                        
                        <div class="upload-btn">
                            <button type="button" class="btn btn-outline-primary btn-custom" onclick="document.getElementById('profile_picture').click()">
                                <i class="fas fa-camera me-1"></i> Change Picture
                            </button>
                            <?php if (!empty($user['profile_picture'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-custom" onclick="removeProfilePicture()">
                                    <i class="fas fa-trash me-1"></i> Remove
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <img id="picturePreview" class="picture-preview">
                        
                        <div class="file-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Max file size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF
                            </small>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-1"></i>Full Name *
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>Email Address *
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">
                            <i class="fas fa-phone me-1"></i>Phone Number
                        </label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                               value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="address" class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>Address
                        </label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="user_dashboard.php" class="btn btn-secondary me-md-2 btn-custom">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary btn-custom" id="submitBtn">
                            <i class="fas fa-save me-1"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
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
                    
                    // Hide placeholder if shown
                    const placeholder = document.querySelector('.profile-picture-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
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
        
        function handleImageError(img) {
            img.src = 'https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>';
            img.onerror = null; // Prevent infinite loop
        }

        // Form submission loading state
        document.getElementById('profileForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner"></div> Updating...';
        });

        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
        
        // Add character counter for textarea
        const addressTextarea = document.getElementById('address');
        if (addressTextarea) {
            // Create character counter element
            const counter = document.createElement('div');
            counter.className = 'form-text text-end';
            counter.id = 'addressCounter';
            addressTextarea.parentNode.appendChild(counter);
            
            function updateCounter() {
                const length = addressTextarea.value.length;
                counter.textContent = `${length} characters`;
                
                if (length > 200) {
                    counter.classList.add('text-danger');
                } else {
                    counter.classList.remove('text-danger');
                }
            }
            
            addressTextarea.addEventListener('input', updateCounter);
            updateCounter(); // Initialize counter
        }
        
        // Add input validation styling
        const inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '' && !this.required) {
                    this.classList.remove('is-valid');
                    this.classList.remove('is-invalid');
                } else if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
    </script>
</body>
</html>
