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
try {
    $stmt = $conn->prepare("SELECT name, email, phone_number, address, role, profile_picture, created_at, last_login FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User not found");
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Set default profile picture if none exists
    $profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/150?u=" . urlencode($user['name']);
    
    // Format dates
    $created_at = date('M j, Y', strtotime($user['created_at']));
    $last_login = !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never';
    
    // Check if profile picture exists locally
    $profile_picture_exists = false;
    $profile_picture_last_updated = 'Never';
    
    if (!empty($user['profile_picture']) && strpos($user['profile_picture'], 'uploads/') !== false) {
        $file_path = $user['profile_picture'];
        if (file_exists($file_path)) {
            $profile_picture_exists = true;
            $profile_picture_last_updated = date('M j, Y', filemtime($file_path));
        }
    }
    
} catch (Exception $e) {
    error_log("Settings page error: " . $e->getMessage());
    $_SESSION['error'] = "Unable to load user data. Please try again.";
    header("Location: user_dashboard.php");
    exit();
}

// Handle success/error messages from previous actions
$success_message = '';
$error_message = '';

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
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
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --radius: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        body {
            background: linear-gradient(135deg, var(--light) 0%, #e0f2fe 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .settings-container {
            max-width: 900px;
            margin: 2rem auto;
            animation: fadeIn 0.5s ease-in;
        }
        
        .settings-card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        }
        
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(14, 165, 233, 0.15);
        }
        
        .settings-section {
            margin-bottom: 2rem;
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-dark);
            padding-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 2px solid var(--primary-light);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border-radius: var(--radius);
            background: var(--primary-light);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .info-item:hover {
            background: #bae6fd;
            transform: translateX(5px);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .info-value {
            color: var(--dark);
            text-align: right;
            word-break: break-word;
            font-weight: 500;
        }
        
        .profile-picture-container {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .profile-picture {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-light);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        
        .profile-picture:hover {
            transform: scale(1.08);
            border-color: var(--primary);
            box-shadow: 0 12px 25px rgba(14, 165, 233, 0.3);
        }
        
        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.7), rgba(2, 132, 199, 0.7));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }
        
        .profile-picture-overlay i {
            color: white;
            font-size: 2rem;
        }
        
        .profile-picture-placeholder {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), #bae6fd);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: var(--primary);
            border: 5px solid var(--primary-light);
            margin: 0 auto;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        
        .profile-picture-placeholder:hover {
            border-color: var(--primary);
            transform: scale(1.08);
            box-shadow: 0 12px 25px rgba(14, 165, 233, 0.3);
        }
        
        .picture-stats {
            background: linear-gradient(135deg, var(--primary-light), #f0f9ff);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-top: 1.5rem;
            border-left: 5px solid var(--primary);
            box-shadow: var(--shadow);
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stat-value {
            color: var(--dark);
            font-weight: 500;
        }
        
        .btn-custom {
            border-radius: var(--radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
            box-shadow: var(--shadow);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #0369a1);
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(14, 165, 233, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(14, 165, 233, 0.3);
        }
        
        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            transform: translateY(-3px);
        }
        
        .btn-outline-info {
            border: 2px solid #17a2b8;
            color: #17a2b8;
        }
        
        .btn-outline-info:hover {
            background: #17a2b8;
            transform: translateY(-3px);
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }
        
        .action-btn {
            padding: 1.25rem;
            border-radius: var(--radius);
            text-align: left;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-light);
        }
        
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(14, 165, 233, 0.15);
            color: inherit;
            text-decoration: none;
        }
        
        .action-btn i {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .action-btn.border-warning {
            border-left: 4px solid var(--warning);
        }
        
        .action-btn.border-info {
            border-left: 4px solid #17a2b8;
        }
        
        .action-btn.border-success {
            border-left: 4px solid var(--success);
        }
        
        .action-btn.border-primary {
            border-left: 4px solid var(--primary);
        }
        
        .action-btn.border-secondary {
            border-left: 4px solid var(--gray);
        }
        
        .action-btn.border-danger {
            border-left: 4px solid var(--danger);
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
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .role-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: capitalize;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .alert {
            border-radius: var(--radius);
            border: none;
            box-shadow: var(--shadow);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 5px solid var(--success);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fecaca, #fca5a5);
            color: #7f1d1d;
            border-left: 5px solid var(--danger);
        }
        
        h3 {
            color: var(--primary-dark);
            font-weight: 700;
        }
        
        .text-muted {
            color: var(--gray) !important;
        }
        
        @media (max-width: 768px) {
            .settings-container {
                margin: 1rem;
            }
            
            .settings-card {
                padding: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-picture, .profile-picture-placeholder {
                width: 130px;
                height: 130px;
            }
        }
        
        /* Custom blue modal */
        .modal-content {
            border-radius: var(--radius);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-light), var(--light));
            border-bottom: 1px solid var(--primary-light);
        }
        
        .modal-title {
            color: var(--primary-dark);
            font-weight: 700;
        }
        
        .modal-footer {
            border-top: 1px solid var(--primary-light);
        }
    </style>
</head>
<body>
    <div class="container settings-container">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">
                <i class="fas fa-cog me-2"></i>Account Settings
            </h3>
            <a href="user_dashboard.php" class="btn btn-outline-primary btn-custom">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Profile Information Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title">
                    <i class="fas fa-user"></i>Profile Information
                </h5>
                
                <!-- Profile Picture Section -->
                <div class="text-center mb-4">
                    <div class="profile-picture-container" onclick="location.href='update_profile.php'">
                        <?php if (!empty($user['profile_picture']) && $profile_picture_exists): ?>
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
                    <div class="mt-2">
                        <small class="text-muted">Click on the picture to update your profile</small>
                    </div>
                    
                    <!-- Picture Stats -->
                    <div class="picture-stats">
                        <div class="stat-item">
                            <span class="stat-label">
                                <i class="fas fa-image"></i>Current Picture:
                            </span>
                            <span class="stat-value">
                                <?php echo (!empty($user['profile_picture']) && $profile_picture_exists) ? 'Custom' : 'Default'; ?>
                            </span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">
                                <i class="fas fa-calendar"></i>Last Updated:
                            </span>
                            <span class="stat-value">
                                <?php echo $profile_picture_last_updated; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-user-tag"></i>Full Name:
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-envelope"></i>Email:
                        </span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-phone"></i>Phone:
                        </span>
                        <span class="info-value">
                            <?php echo !empty($user['phone_number']) ? htmlspecialchars($user['phone_number']) : '<span class="text-muted">Not set</span>'; ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-map-marker-alt"></i>Address:
                        </span>
                        <span class="info-value">
                            <?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : '<span class="text-muted">Not set</span>'; ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-user-shield"></i>Role:
                        </span>
                        <span class="info-value">
                            <span class="role-badge">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-calendar-plus"></i>Member Since:
                        </span>
                        <span class="info-value"><?php echo $created_at; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-sign-in-alt"></i>Last Login:
                        </span>
                        <span class="info-value"><?php echo $last_login; ?></span>
                    </div>
                </div>
                
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <a href="update_profile.php" class="btn btn-primary btn-custom">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <?php if (!empty($user['profile_picture']) && $profile_picture_exists): ?>
                        <button type="button" class="btn btn-outline-danger btn-custom" data-bs-toggle="modal" data-bs-target="#removePictureModal">
                            <i class="fas fa-trash"></i> Remove Picture
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-info btn-custom" onclick="refreshProfile()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Account Settings Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title">
                    <i class="fas fa-shield-alt"></i>Account Security
                </h5>
                <p class="text-muted mb-3">Manage your account security and privacy settings</p>
                
                <div class="action-grid">
                    <a href="forgot-password.php" class="action-btn border-warning">
                        <i class="fas fa-key"></i>
                        <div>
                            <strong>Change Password</strong>
                            <div class="small text-muted">Update your account password</div>
                        </div>
                    </a>
                    
                    <a href="privacy_settings.php" class="action-btn border-info">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong>Privacy Settings</strong>
                            <div class="small text-muted">Control your privacy preferences</div>
                        </div>
                    </a>
                    
                    <a href="two_factor.php" class="action-btn border-success">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <strong>Two-Factor Auth</strong>
                            <div class="small text-muted">Add extra security to your account</div>
                        </div>
                    </a>
                    
                    <a href="login_sessions.php" class="action-btn border-primary">
                        <i class="fas fa-desktop"></i>
                        <div>
                            <strong>Login Sessions</strong>
                            <div class="small text-muted">Manage active sessions</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="settings-card">
            <div class="settings-section">
                <h5 class="section-title">
                    <i class="fas fa-bolt"></i>Quick Actions
                </h5>
                
                <div class="action-grid">
                    <a href="user_pet_profile.php" class="action-btn border-success">
                        <i class="fas fa-paw"></i>
                        <div>
                            <strong>Manage Pets</strong>
                            <div class="small text-muted">View and edit your pets</div>
                        </div>
                    </a>
                    
                    <a href="qr_code.php" class="action-btn border-primary">
                        <i class="fas fa-qrcode"></i>
                        <div>
                            <strong>QR Codes</strong>
                            <div class="small text-muted">Generate and manage QR codes</div>
                        </div>
                    </a>
                    
                    <a href="register_pet.php" class="action-btn border-info">
                        <i class="fas fa-plus-circle"></i>
                        <div>
                            <strong>Add New Pet</strong>
                            <div class="small text-muted">Register a new pet</div>
                        </div>
                    </a>
                    
                    <a href="pet_medical_records.php" class="action-btn border-warning">
                        <i class="fas fa-file-medical"></i>
                        <div>
                            <strong>Medical Records</strong>
                            <div class="small text-muted">View medical history</div>
                        </div>
                    </a>
                    
                    <a href="backup_data.php" class="action-btn border-secondary">
                        <i class="fas fa-download"></i>
                        <div>
                            <strong>Backup Data</strong>
                            <div class="small text-muted">Download your data</div>
                        </div>
                    </a>
                    
                    <a href="logout.php" class="action-btn border-danger">
                        <i class="fas fa-sign-out-alt"></i>
                        <div>
                            <strong>Logout</strong>
                            <div class="small text-muted">Sign out of your account</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Picture Modal -->
    <div class="modal fade" id="removePictureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Remove Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove your profile picture? This action cannot be undone.</p>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Current Profile Picture" 
                             class="rounded-circle shadow"
                             style="width: 100px; height: 100px; object-fit: cover;"
                             onerror="this.style.display='none'">
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Your profile will revert to the default avatar after removal.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="remove_profile_picture.php" id="removePictureForm">
                        <button type="submit" class="btn btn-danger" id="removePictureBtn">
                            <i class="fas fa-trash me-1"></i> Remove Picture
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-close alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });

            // Add loading state to forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<div class="loading-spinner"></div> Processing...';
                    }
                });
            });

            // Enhanced profile picture error handling
            const profilePictures = document.querySelectorAll('img[onerror]');
            profilePictures.forEach(img => {
                img.addEventListener('error', handleImageError);
            });
        });

        function handleImageError(img) {
            img.src = 'https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>';
            img.onerror = null; // Prevent infinite loop
        }

        function refreshProfile() {
            const refreshBtn = event.target.closest('button');
            const originalHtml = refreshBtn.innerHTML;
            
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<div class="loading-spinner"></div> Refreshing...';
            
            // Simulate refresh (in real implementation, this would be an AJAX call)
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Remove picture form handling
        const removePictureForm = document.getElementById('removePictureForm');
        if (removePictureForm) {
            removePictureForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('removePictureBtn');
                btn.disabled = true;
                btn.innerHTML = '<div class="loading-spinner"></div> Removing...';
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshProfile();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });
            }
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>

