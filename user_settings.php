<?php
session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT name, email, phone, address, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
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
                    <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>