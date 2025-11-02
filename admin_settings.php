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
    header("Location: login_admin.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT user_id, name, email, profile_picture, phone, address, created_at FROM users WHERE user_id = ?";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();
$admin_stmt->close();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Handle profile picture upload
    $profile_picture = $admin['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/profiles/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $file_name = "admin_" . $admin_id . "_" . time() . "." . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // Check file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                // Delete old profile picture if it exists and is not the default
                if ($admin['profile_picture'] && !str_contains($admin['profile_picture'], 'ui-avatars.com')) {
                    @unlink($admin['profile_picture']);
                }
                $profile_picture = $file_path;
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Please upload JPG, JPEG, PNG, or GIF files only.";
        }
    }
    
    $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssssi", $name, $email, $phone, $address, $profile_picture, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        // Update session data
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        // Refresh admin data
        $admin['name'] = $name;
        $admin['email'] = $email;
        $admin['phone'] = $phone;
        $admin['address'] = $address;
        $admin['profile_picture'] = $profile_picture;
    } else {
        $_SESSION['error'] = "Error updating profile: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $verify_query = "SELECT password FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Password changed successfully!";
                } else {
                    $_SESSION['error'] = "Error changing password: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "New password must be at least 6 characters long.";
            }
        } else {
            $_SESSION['error'] = "New passwords do not match.";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect.";
    }
}

// Handle Clinic Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_clinic_settings'])) {
    $clinic_name = trim($_POST['clinic_name']);
    $clinic_address = trim($_POST['clinic_address']);
    $clinic_phone = trim($_POST['clinic_phone']);
    $clinic_email = trim($_POST['clinic_email']);
    $business_hours = trim($_POST['business_hours']);
    
    // In a real application, you'd store these in a settings table
    // For now, we'll use session to demonstrate
    $_SESSION['clinic_settings'] = [
        'name' => $clinic_name,
        'address' => $clinic_address,
        'phone' => $clinic_phone,
        'email' => $clinic_email,
        'business_hours' => $business_hours
    ];
    
    $_SESSION['success'] = "Clinic settings updated successfully!";
}

// Get clinic settings from session or set defaults
$clinic_settings = isset($_SESSION['clinic_settings']) ? $_SESSION['clinic_settings'] : [
    'name' => 'BrightView Veterinary Clinic',
    'address' => '123 Pet Care Avenue, Veterinary City',
    'phone' => '(555) 123-4567',
    'email' => 'info@brightviewvet.com',
    'business_hours' => 'Mon-Fri: 8:00 AM - 6:00 PM, Sat: 9:00 AM - 4:00 PM, Sun: Closed'
];

// Get system statistics for dashboard
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'vet') as total_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'pet_owner') as total_owners,
        (SELECT COUNT(*) FROM pets) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as today_appointments,
        (SELECT COUNT(*) FROM pet_medical_records) as total_records
";

$stats_result = $conn->query($stats_query);
$system_stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_vets' => 0,
    'total_owners' => 0,
    'total_pets' => 0,
    'today_appointments' => 0,
    'total_records' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - VetCareQR</title>
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
        }

        /* Shell layout */
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

        /* Enhanced Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
            color: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .brand {
            font-weight: 800;
            color: #fff;
            font-size: 1.5rem;
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .sidebar .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.2);
            margin-right: 12px;
            transition: var(--transition);
        }

        .sidebar .nav-link.active .icon,
        .sidebar .nav-link:hover .icon {
            background: rgba(255,255,255,.3);
            transform: scale(1.1);
        }

        /* Stats in sidebar */
        .sidebar-stats {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Enhanced Topbar */
        .topbar {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.8);
        }

        .topbar .search {
            flex: 1;
            max-width: 480px;
        }

        .form-control.search-input {
            border: none;
            background: #f8fafc;
            border-radius: 14px;
            padding: 12px 16px;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .form-control.search-input:focus {
            background: white;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        /* Enhanced Cards */
        .card-soft {
            background: var(--card);
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.8);
            overflow: hidden;
        }

        .card-soft:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.15);
        }

        /* Enhanced KPI Cards */
        .kpi {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            position: relative;
        }

        .kpi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand), var(--lav));
            opacity: 0;
            transition: var(--transition);
        }

        .kpi:hover::before {
            opacity: 1;
        }

        .kpi .bubble {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 24px;
            transition: var(--transition);
        }

        .kpi:hover .bubble {
            transform: scale(1.1) rotate(5deg);
        }

        .kpi small {
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-size: 0.75rem;
        }

        .kpi .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1;
            margin: 8px 0 4px;
            background: linear-gradient(135deg, var(--ink), var(--lav));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: var(--muted);
            font-size: 0.75rem;
        }

        .badge-dot::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Enhanced Section Titles */
        .section-title {
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--ink), var(--lav));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtle {
            color: var(--muted);
            font-weight: 600;
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
            background: linear-gradient(135deg, var(--lav), var(--brand));
        }

        /* Settings Specific Styles */
        .settings-nav {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }

        .nav-pills .nav-link {
            color: var(--muted);
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: var(--transition);
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            color: white;
            transform: translateX(8px);
        }

        .nav-pills .nav-link:hover:not(.active) {
            background: rgba(59, 130, 246, 0.1);
            color: var(--brand);
        }

        .settings-section {
            background: var(--card);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }

        .profile-picture-container {
            text-align: center;
            padding: 2rem;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--brand);
            margin-bottom: 1rem;
        }

        .file-upload {
            position: relative;
            display: inline-block;
        }

        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 2rem;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
        }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .app-shell {
                gap: 16px;
                padding: 12px;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .topbar .search {
                max-width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* Danger zone styles */
        .danger-zone {
            border-left: 4px solid var(--danger);
            background: rgba(220, 38, 38, 0.05);
        }

        .clinic-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .clinic-info-card h3 {
            color: white;
            margin-bottom: 1rem;
        }

        .clinic-info-card p {
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="icon me-3"><i class="fa-solid fa-user-shield"></i></div>
                <div class="brand h4 mb-0">VetCareQR</div>
            </div>
            
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center" href="admin_dashboard.php">
                    <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
                    <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pet_owner.php">
                    <span class="icon"><i class="fa-solid fa-users"></i></span>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pets.php">
                    <span class="icon"><i class="fa-solid fa-paw"></i></span>
                    <span>Pets</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="appointments.php">
                    <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <span>Appointments</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="medical_records.php">
                    <span class="icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span>Medical Records</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="analytics.php">
                    <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span>Analytics</span>
                </a>
                <a class="nav-link d-flex align-items-center active" href="settings.php">
                    <span class="icon"><i class="fa-solid fa-gear"></i></span>
                    <span>Settings</span>
                </a>
            </nav>

            <!-- System Statistics in Sidebar -->
            <div class="sidebar-stats">
                <h6 class="text-white mb-3">System Overview</h6>
                <div class="stat-item">
                    <span>Veterinarians</span>
                    <span class="stat-value"><?php echo $system_stats['total_vets']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Pet Owners</span>
                    <span class="stat-value"><?php echo $system_stats['total_owners']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Pets</span>
                    <span class="stat-value"><?php echo $system_stats['total_pets']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Today's Appointments</span>
                    <span class="stat-value"><?php echo $system_stats['today_appointments']; ?></span>
                </div>
            </div>

            <div class="mt-auto pt-4">
                <div class="admin-profile d-flex align-items-center p-3 rounded-3" style="background: rgba(255,255,255,0.1);">
                    <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
                         class="rounded-circle me-3" width="50" height="50" alt="Admin" />
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($admin['name']); ?></div>
                        <small class="text-white-50">Administrator</small>
                    </div>
                    <a href="logout_admin.php" class="text-white-50"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="d-flex flex-column gap-4">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex align-items-center">
                    <h1 class="h4 mb-0 fw-bold">Admin Settings</h1>
                    <span class="badge bg-light text-dark ms-3">System Configuration</span>
                </div>

                <div class="search ms-auto">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input class="form-control search-input" placeholder="Search settings..." />
                    </div>
                </div>

                <div class="position-relative">
                    <button class="btn btn-light rounded-circle position-relative">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                </div>

                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
                             class="rounded-circle" width="40" height="40" alt="Admin" />
                        <span class="fw-bold d-none d-md-inline"><?php echo htmlspecialchars($admin['name']); ?></span>
                        <i class="fa-solid fa-chevron-down text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#profile-settings"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#security-settings"><i class="fa-solid fa-lock me-2"></i>Security</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <div class="settings-nav fade-in">
                <ul class="nav nav-pills justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="#profile-settings">
                            <i class="fa-solid fa-user me-2"></i>Profile Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#security-settings">
                            <i class="fa-solid fa-lock me-2"></i>Security
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#clinic-settings">
                            <i class="fa-solid fa-hospital me-2"></i>Clinic Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#system-settings">
                            <i class="fa-solid fa-cog me-2"></i>System
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Profile Settings -->
            <div id="profile-settings" class="settings-section fade-in">
                <h3 class="section-title mb-4">
                    <i class="fa-solid fa-user me-2"></i>Profile Settings
                </h3>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="profile-picture-container">
                            <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff&size=150'; ?>" 
                                 class="profile-picture" alt="Profile Picture">
                            <div class="file-upload">
                                <button class="btn btn-outline-brand">
                                    <i class="fa-solid fa-camera me-2"></i>Change Photo
                                </button>
                                <input type="file" class="file-upload-input" accept="image/*" form="profile-form" name="profile_picture">
                            </div>
                            <small class="text-muted d-block mt-2">JPG, PNG or GIF, Max 5MB</small>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <form id="profile-form" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Account Created</label>
                                <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($admin['created_at'])); ?>" readonly>
                            </div>
                            
                            <button type="submit" class="btn btn-brand">
                                <i class="fa-solid fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="security-settings" class="settings-section fade-in" style="display: none;">
                <h3 class="section-title mb-4">
                    <i class="fa-solid fa-lock me-2"></i>Security Settings
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">New Password *</label>
                                <input type="password" class="form-control" name="new_password" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-brand">
                        <i class="fa-solid fa-key me-2"></i>Change Password
                    </button>
                </form>
                
                <hr class="my-4">
                
                <div class="danger-zone p-4 rounded">
                    <h5 class="text-danger mb-3">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>Danger Zone
                    </h5>
                    <p class="text-muted mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        <i class="fa-solid fa-trash me-2"></i>Delete Account
                    </button>
                </div>
            </div>

            <!-- Clinic Settings -->
            <div id="clinic-settings" class="settings-section fade-in" style="display: none;">
                <h3 class="section-title mb-4">
                    <i class="fa-solid fa-hospital me-2"></i>Clinic Settings
                </h3>
                
                <div class="clinic-info-card">
                    <h3><?php echo htmlspecialchars($clinic_settings['name']); ?></h3>
                    <p><i class="fa-solid fa-location-dot me-2"></i><?php echo htmlspecialchars($clinic_settings['address']); ?></p>
                    <p><i class="fa-solid fa-phone me-2"></i><?php echo htmlspecialchars($clinic_settings['phone']); ?></p>
                    <p><i class="fa-solid fa-envelope me-2"></i><?php echo htmlspecialchars($clinic_settings['email']); ?></p>
                    <p><i class="fa-solid fa-clock me-2"></i><?php echo htmlspecialchars($clinic_settings['business_hours']); ?></p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="update_clinic_settings" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Clinic Name *</label>
                                <input type="text" class="form-control" name="clinic_name" value="<?php echo htmlspecialchars($clinic_settings['name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Clinic Phone *</label>
                                <input type="tel" class="form-control" name="clinic_phone" value="<?php echo htmlspecialchars($clinic_settings['phone']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Clinic Address *</label>
                        <textarea class="form-control" name="clinic_address" rows="3" required><?php echo htmlspecialchars($clinic_settings['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Clinic Email *</label>
                                <input type="email" class="form-control" name="clinic_email" value="<?php echo htmlspecialchars($clinic_settings['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Business Hours *</label>
                                <textarea class="form-control" name="business_hours" rows="3" required><?php echo htmlspecialchars($clinic_settings['business_hours']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-brand">
                        <i class="fa-solid fa-save me-2"></i>Update Clinic Settings
                    </button>
                </form>
            </div>

            <!-- System Settings -->
            <div id="system-settings" class="settings-section fade-in" style="display: none;">
                <h3 class="section-title mb-4">
                    <i class="fa-solid fa-cog me-2"></i>System Settings
                </h3>
                
                <div class="stats-grid">
                    <div class="card-soft">
                        <div class="kpi">
                            <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
                                <i class="fa-solid fa-user-doctor"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small>Veterinarians</small>
                                <div class="stat-value"><?php echo $system_stats['total_vets']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-soft">
                        <div class="kpi">
                            <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small>Pet Owners</small>
                                <div class="stat-value"><?php echo $system_stats['total_owners']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-soft">
                        <div class="kpi">
                            <div class="bubble" style="background:#fff0f5;color:#c2417a">
                                <i class="fa-solid fa-paw"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small>Pets</small>
                                <div class="stat-value"><?php echo $system_stats['total_pets']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-soft">
                        <div class="kpi">
                            <div class="bubble" style="background:#fff7e6;color:#b45309">
                                <i class="fa-solid fa-file-medical"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small>Medical Records</small>
                                <div class="stat-value"><?php echo $system_stats['total_records']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-soft p-4">
                            <h5 class="section-title">Database Maintenance</h5>
                            <p class="text-muted mb-3">Optimize database performance and clean up temporary data.</p>
                            <button class="btn btn-outline-brand">
                                <i class="fa-solid fa-database me-2"></i>Optimize Database
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-soft p-4">
                            <h5 class="section-title">Backup & Restore</h5>
                            <p class="text-muted mb-3">Create backups of your system data for safety.</p>
                            <button class="btn btn-outline-brand">
                                <i class="fa-solid fa-download me-2"></i>Create Backup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
                    </div>
                    <p>Are you sure you want to delete your account? This will remove:</p>
                    <ul>
                        <li>Your profile information</li>
                        <li>All associated data</li>
                        <li>Access to the system</li>
                    </ul>
                    <div class="form-group">
                        <label class="form-label">Type "DELETE" to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirm" placeholder="Type DELETE here">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" disabled>
                        <i class="fa-solid fa-trash me-2"></i>Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Settings Navigation
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-pills .nav-link');
            const sections = document.querySelectorAll('.settings-section');
            
            // Show profile section by default
            document.getElementById('profile-settings').style.display = 'block';
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(nav => nav.classList.remove('active'));
                    // Hide all sections
                    sections.forEach(section => section.style.display = 'none');
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Show corresponding section
                    const targetId = this.getAttribute('href').substring(1);
                    document.getElementById(targetId).style.display = 'block';
                });
            });

            // File upload preview
            const fileUploadInput = document.querySelector('.file-upload-input');
            const profilePicture = document.querySelector('.profile-picture');
            
            if (fileUploadInput) {
                fileUploadInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profilePicture.src = e.target.result;
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Delete account confirmation
            const deleteConfirm = document.getElementById('deleteConfirm');
            const confirmDelete = document.getElementById('confirmDelete');
            
            if (deleteConfirm && confirmDelete) {
                deleteConfirm.addEventListener('input', function() {
                    confirmDelete.disabled = this.value !== 'DELETE';
                });
            }

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });

            // Add loading animation to cards
            document.querySelectorAll('.card-soft').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>