<?php
session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data with profile picture
$stmt = $conn->prepare("SELECT name, email, phone_number, address, role, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Set default profile picture if none exists
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/150?u=" . urlencode($user['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .settings-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .settings-section {
            margin-bottom: 2rem;
        }
        .settings-section:last-child {
            margin-bottom: 0;
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #4a6cf7;
            border-bottom: 2px solid #ffd6e7;
            padding-bottom: 0.5rem;
        }
        .info-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
        }
        .info-value {
            color: #6c757d;
            flex: 1;
        }
        .profile-picture-container {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffd6e7;
            transition: transform 0.3s;
        }
        .profile-picture:hover {
            transform: scale(1.05);
        }
        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }
        .profile-picture-overlay i {
            color: white;
            font-size: 1.5rem;
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
        .picture-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        .stat-label {
            font-weight: 500;
            color: #495057;
        }
        .stat-value {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container settings-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-cog me-2"></i>Settings</h3>
            <a href="user_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Profile Information Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title"><i class="fas fa-user me-2"></i>Profile Information</h5>
                
                <!-- Profile Picture Section -->
                <div class="text-center mb-4">
                    <div class="profile-picture-container">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile Picture" 
                                 class="profile-picture"
                                 id="currentProfilePicture"
                                 onerror="this.src='https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>'">
                        <?php else: ?>
                            <div class="profile-picture-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="profile-picture-overlay" onclick="location.href='update_profile.php'">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Click on the picture to change</small>
                    </div>
                    
                    <!-- Picture Stats -->
                    <div class="picture-stats">
                        <div class="stat-item">
                            <span class="stat-label">Current Picture:</span>
                            <span class="stat-value">
                                <?php echo !empty($user['profile_picture']) ? 'Custom' : 'Default'; ?>
                            </span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Last Updated:</span>
                            <span class="stat-value">
                                <?php 
                                if (!empty($user['profile_picture']) && strpos($user['profile_picture'], 'uploads/') !== false) {
                                    $file_path = $user['profile_picture'];
                                    if (file_exists($file_path)) {
                                        echo date('M j, Y', filemtime($file_path));
                                    } else {
                                        echo 'Unknown';
                                    }
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone_number'] ?? 'Not set'); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'Not set'); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="info-value badge bg-primary"><?php echo htmlspecialchars($user['role']); ?></span>
                </div>
                
                <div class="mt-3">
                    <a href="update_profile.php" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Profile
                    </a>
                    <?php if (!empty($user['profile_picture'])): ?>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removePictureModal">
                            <i class="fas fa-trash me-1"></i> Remove Picture
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Settings Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title"><i class="fas fa-shield-alt me-2"></i>Account Security</h5>
                <p class="text-muted mb-3">Manage your account security settings</p>
                
                <div class="d-grid gap-2">
                    <a href="change_password.php" class="btn btn-outline-warning text-start">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a>
                    <a href="privacy_settings.php" class="btn btn-outline-info text-start">
                        <i class="fas fa-user-shield me-2"></i> Privacy Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <a href="user_pet_profile.php" class="btn btn-outline-success w-100 text-start">
                            <i class="fas fa-paw me-2"></i> Manage Pets
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="qr_code.php" class="btn btn-outline-primary w-100 text-start">
                            <i class="fas fa-qrcode me-2"></i> QR Codes
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="register_pet.php" class="btn btn-outline-info w-100 text-start">
                            <i class="fas fa-plus-circle me-2"></i> Add New Pet
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a href="logout.php" class="btn btn-outline-danger w-100 text-start">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Picture Modal -->
    <div class="modal fade" id="removePictureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Remove Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove your profile picture? This action cannot be undone.</p>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Current Profile Picture" 
                             class="rounded-circle"
                             style="width: 80px; height: 80px; object-fit: cover;"
                             onerror="this.style.display='none'">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="remove_profile_picture.php" style="display: inline;">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Remove Picture
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile picture preview and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add click event to profile picture container
            const profileContainer = document.querySelector('.profile-picture-container');
            if (profileContainer) {
                profileContainer.addEventListener('click', function() {
                    window.location.href = 'update_profile.php';
                });
            }

            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

            // Check if there are success/error messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showMessage(urlParams.get('success'), 'success');
            }
            if (urlParams.has('error')) {
                showMessage(urlParams.get('error'), 'error');
            }
        });

        function showMessage(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            document.querySelector('.settings-container').insertBefore(alertDiv, document.querySelector('.settings-container').firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Profile picture error handling
        const profilePicture = document.getElementById('currentProfilePicture');
        if (profilePicture) {
            profilePicture.addEventListener('error', function() {
                this.src = 'https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>';
            });
        }
    </script>
</body>
</html>
