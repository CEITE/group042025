<?php
session_start();
include("conn.php");

// Check if user is logged in and is a vet
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vet') {
    header("Location: login.php");
    exit();
}

$vet_id = $_SESSION['user_id'];

// Handle forgot password redirect
if (isset($_GET['forgot_password'])) {
    $_SESSION['forgot_password_email'] = $vet['email'] ?? '';
    $_SESSION['forgot_password_redirect'] = 'vet_settings.php';
    header("Location: vet-forgot-password.php");
    exit();
}

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture, phone_number, clinic_name, license_number, specialization, bio FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Log password change activity
function logPasswordChange($vet_id, $conn) {
    $activity = "Password changed successfully";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    try {
        // Create activity logs table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS user_activity_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            activity VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        $log_stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, activity, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($log_stmt) {
            $log_stmt->bind_param("isss", $vet_id, $activity, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail - logging shouldn't break the main functionality
        error_log("Failed to log password change: " . $e->getMessage());
    }
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update basic profile information
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone_number = trim($_POST['phone_number']);
        $clinic_name = trim($_POST['clinic_name']);
        $license_number = trim($_POST['license_number']);
        $specialization = trim($_POST['specialization']);
        $bio = trim($_POST['bio']);
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            $_SESSION['error'] = "Name and email are required fields.";
        } else {
            // Check if email already exists (excluding current user)
            $check_email_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check_email_stmt->bind_param("si", $email, $vet_id);
            $check_email_stmt->execute();
            
            if ($check_email_stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Email already exists. Please use a different email address.";
            } else {
                // Update profile
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, clinic_name = ?, license_number = ?, specialization = ?, bio = ?, updated_at = NOW() WHERE user_id = ?");
                $update_stmt->bind_param("sssssssi", $name, $email, $phone_number, $clinic_name, $license_number, $specialization, $bio, $vet_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = "Profile updated successfully!";
                    $_SESSION['name'] = $name; // Update session name
                    header("Location: vet_settings.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error updating profile: " . $conn->error;
                }
                $update_stmt->close();
            }
            $check_email_stmt->close();
        }
    }
    
    if (isset($_POST['update_notifications'])) {
        // Handle notification preferences
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
        $new_appointment_alerts = isset($_POST['new_appointment_alerts']) ? 1 : 0;
        $emergency_alerts = isset($_POST['emergency_alerts']) ? 1 : 0;
        
        // Create notification preferences table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS vet_notification_preferences (
            vet_id INT PRIMARY KEY,
            email_notifications TINYINT DEFAULT 1,
            sms_notifications TINYINT DEFAULT 0,
            appointment_reminders TINYINT DEFAULT 1,
            new_appointment_alerts TINYINT DEFAULT 1,
            emergency_alerts TINYINT DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        // Update notification preferences
        $update_notifications_stmt = $conn->prepare("
            INSERT INTO vet_notification_preferences (vet_id, email_notifications, sms_notifications, appointment_reminders, new_appointment_alerts, emergency_alerts, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            email_notifications = ?, sms_notifications = ?, appointment_reminders = ?, new_appointment_alerts = ?, emergency_alerts = ?, updated_at = NOW()
        ");
        $update_notifications_stmt->bind_param("iiiiiiiiiii", 
            $vet_id, $email_notifications, $sms_notifications, $appointment_reminders, $new_appointment_alerts, $emergency_alerts,
            $email_notifications, $sms_notifications, $appointment_reminders, $new_appointment_alerts, $emergency_alerts
        );
        
        if ($update_notifications_stmt->execute()) {
            $_SESSION['success'] = "Notification preferences updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating notification preferences: " . $conn->error;
        }
        $update_notifications_stmt->close();
    }
    
    if (isset($_POST['update_working_hours'])) {
        // Handle working hours update
        $working_hours = json_encode([
            'monday' => [
                'enabled' => isset($_POST['monday_enabled']),
                'start' => $_POST['monday_start'],
                'end' => $_POST['monday_end']
            ],
            'tuesday' => [
                'enabled' => isset($_POST['tuesday_enabled']),
                'start' => $_POST['tuesday_start'],
                'end' => $_POST['tuesday_end']
            ],
            'wednesday' => [
                'enabled' => isset($_POST['wednesday_enabled']),
                'start' => $_POST['wednesday_start'],
                'end' => $_POST['wednesday_end']
            ],
            'thursday' => [
                'enabled' => isset($_POST['thursday_enabled']),
                'start' => $_POST['thursday_start'],
                'end' => $_POST['thursday_end']
            ],
            'friday' => [
                'enabled' => isset($_POST['friday_enabled']),
                'start' => $_POST['friday_start'],
                'end' => $_POST['friday_end']
            ],
            'saturday' => [
                'enabled' => isset($_POST['saturday_enabled']),
                'start' => $_POST['saturday_start'],
                'end' => $_POST['saturday_end']
            ],
            'sunday' => [
                'enabled' => isset($_POST['sunday_enabled']),
                'start' => $_POST['sunday_start'],
                'end' => $_POST['sunday_end']
            ]
        ]);
        
        // Create working hours table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS vet_working_hours (
            vet_id INT PRIMARY KEY,
            working_hours JSON,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        $update_hours_stmt = $conn->prepare("
            INSERT INTO vet_working_hours (vet_id, working_hours, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE working_hours = ?, updated_at = NOW()
        ");
        $update_hours_stmt->bind_param("iss", $vet_id, $working_hours, $working_hours);
        
        if ($update_hours_stmt->execute()) {
            $_SESSION['success'] = "Working hours updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating working hours: " . $conn->error;
        }
        $update_hours_stmt->close();
    }
}

// Fetch notification preferences
$notification_preferences = [
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'appointment_reminders' => 1,
    'new_appointment_alerts' => 1,
    'emergency_alerts' => 1
];

// Create notification preferences table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS vet_notification_preferences (
    vet_id INT PRIMARY KEY,
    email_notifications TINYINT DEFAULT 1,
    sms_notifications TINYINT DEFAULT 0,
    appointment_reminders TINYINT DEFAULT 1,
    new_appointment_alerts TINYINT DEFAULT 1,
    emergency_alerts TINYINT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

$preferences_stmt = $conn->prepare("SELECT * FROM vet_notification_preferences WHERE vet_id = ?");
if ($preferences_stmt) {
    $preferences_stmt->bind_param("i", $vet_id);
    $preferences_stmt->execute();
    $preferences_result = $preferences_stmt->get_result();
    if ($preferences_result->num_rows > 0) {
        $notification_preferences = $preferences_result->fetch_assoc();
    }
    $preferences_stmt->close();
}

// Fetch working hours
$default_hours = [
    'monday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
    'tuesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
    'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
    'thursday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
    'friday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
    'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '12:00'],
    'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '12:00']
];

// Create working hours table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS vet_working_hours (
    vet_id INT PRIMARY KEY,
    working_hours JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

$hours_stmt = $conn->prepare("SELECT working_hours FROM vet_working_hours WHERE vet_id = ?");
if ($hours_stmt) {
    $hours_stmt->bind_param("i", $vet_id);
    $hours_stmt->execute();
    $hours_result = $hours_stmt->get_result();
    if ($hours_result->num_rows > 0) {
        $hours_data = $hours_result->fetch_assoc();
        $working_hours = json_decode($hours_data['working_hours'], true);
        if ($working_hours) {
            $default_hours = $working_hours;
        }
    }
    $hours_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - VetCareQR</title>
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
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, var(--primary-light) 100%);
            margin: 0;
            color: var(--dark);
            min-height: 100vh;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--primary-light);
            padding: 2rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-dark);
        }
        
        .sidebar .profile {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid var(--primary);
            object-fit: cover;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            margin: .3rem 0;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: .2s;
        }
        
        .sidebar a .icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.6);
            margin-right: 10px;
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: var(--light);
            color: var(--primary-dark);
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--danger), #e74c3c);
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            border: none;
        }

        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .settings-card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: none;
        }
        
        .settings-section {
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-picture-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .working-hours-day {
            background: var(--light);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .day-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .time-inputs {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #0369a1);
            transform: translateY(-2px);
        }
        
        .alert-custom {
            border-radius: var(--radius);
            border: none;
        }
        
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .time-inputs {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-paw"></i> VetCareQR</div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Vet"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6>Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($vet['role']); ?></small>
        </div>

        <a href="vet_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_appointments.php">
            <div class="icon"><i class="fa-solid fa-calendar-check"></i></div> Appointments
        </a>
        <a href="vet_patients.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> Patients
        </a>
        <a href="vet_records.php">
            <div class="icon"><i class="fa-solid fa-file-medical"></i></div> Medical Records
        </a>
        <a href="vet_settings.php" class="active">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Settings</h5>
                <small class="text-muted">Manage your account and preferences</small>
            </div>
            <div class="text-end">
                <strong id="currentDate"></strong><br>
                <small id="currentTime"></small>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Profile Settings -->
                <div class="settings-card">
                    <h4 class="mb-4"><i class="fas fa-user-gear me-2"></i>Profile Settings</h4>
                    
                    <form method="POST" action="vet_settings.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($vet['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($vet['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($vet['phone_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" class="form-control" name="license_number" value="<?php echo htmlspecialchars($vet['license_number'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Clinic/Hospital Name</label>
                            <input type="text" class="form-control" name="clinic_name" value="<?php echo htmlspecialchars($vet['clinic_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <select class="form-select" name="specialization">
                                <option value="">Select Specialization</option>
                                <option value="General Practice" <?php echo ($vet['specialization'] ?? '') == 'General Practice' ? 'selected' : ''; ?>>General Practice</option>
                                <option value="Surgery" <?php echo ($vet['specialization'] ?? '') == 'Surgery' ? 'selected' : ''; ?>>Surgery</option>
                                <option value="Dermatology" <?php echo ($vet['specialization'] ?? '') == 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                <option value="Dentistry" <?php echo ($vet['specialization'] ?? '') == 'Dentistry' ? 'selected' : ''; ?>>Dentistry</option>
                                <option value="Ophthalmology" <?php echo ($vet['specialization'] ?? '') == 'Ophthalmology' ? 'selected' : ''; ?>>Ophthalmology</option>
                                <option value="Cardiology" <?php echo ($vet['specialization'] ?? '') == 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="Emergency Care" <?php echo ($vet['specialization'] ?? '') == 'Emergency Care' ? 'selected' : ''; ?>>Emergency Care</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Professional Bio</label>
                            <textarea class="form-control" name="bio" rows="4" placeholder="Tell pet owners about your experience and expertise..."><?php echo htmlspecialchars($vet['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>

                <!-- Working Hours -->
                <div class="settings-card">
                    <h4 class="mb-4"><i class="fas fa-clock me-2"></i>Working Hours</h4>
                    <p class="text-muted mb-4">Set your available working hours for appointments</p>
                    
                    <form method="POST" action="vet_settings.php">
                        <?php 
                        $days = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ];
                        
                        foreach ($days as $day_key => $day_name): 
                            $day_data = $default_hours[$day_key];
                        ?>
                        <div class="working-hours-day">
                            <div class="day-toggle">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           id="<?php echo $day_key; ?>_enabled" 
                                           name="<?php echo $day_key; ?>_enabled" 
                                           <?php echo $day_data['enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="<?php echo $day_key; ?>_enabled">
                                        <?php echo $day_name; ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="time-inputs">
                                <div class="flex-grow-1">
                                    <label class="form-label small">Start Time</label>
                                    <input type="time" class="form-control" 
                                           name="<?php echo $day_key; ?>_start" 
                                           value="<?php echo $day_data['start']; ?>"
                                           <?php echo !$day_data['enabled'] ? 'disabled' : ''; ?>>
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label small">End Time</label>
                                    <input type="time" class="form-control" 
                                           name="<?php echo $day_key; ?>_end" 
                                           value="<?php echo $day_data['end']; ?>"
                                           <?php echo !$day_data['enabled'] ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="update_working_hours" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Working Hours
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Password Reset -->
                <div class="settings-card">
                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Password Reset</h5>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Need to update your password?</strong>
                        <p class="mb-0 mt-1">Click the button below to reset your password using our secure system.</p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="vet_settings.php?forgot_password=1" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Reset My Password
                        </a>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Last updated: 
                            <?php
                            // Get last update date
                            $update_stmt = $conn->prepare("SELECT updated_at FROM users WHERE user_id = ?");
                            $update_stmt->bind_param("i", $vet_id);
                            $update_stmt->execute();
                            $update_result = $update_stmt->get_result();
                            if ($update_result->num_rows > 0) {
                                $user_data = $update_result->fetch_assoc();
                                echo date('M j, Y', strtotime($user_data['updated_at']));
                            } else {
                                echo 'Unknown';
                            }
                            $update_stmt->close();
                            ?>
                        </small>
                    </div>
                </div>

                <!-- Notification Preferences -->
                <div class="settings-card">
                    <h5 class="mb-3"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                    
                    <form method="POST" action="vet_settings.php">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="email_notifications" 
                                       id="email_notifications" 
                                       <?php echo $notification_preferences['email_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">
                                    Email Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive notifications via email</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="sms_notifications" 
                                       id="sms_notifications" 
                                       <?php echo $notification_preferences['sms_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_notifications">
                                    SMS Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive notifications via text message</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="appointment_reminders" 
                                       id="appointment_reminders" 
                                       <?php echo $notification_preferences['appointment_reminders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="appointment_reminders">
                                    Appointment Reminders
                                </label>
                            </div>
                            <small class="text-muted">Get reminded about upcoming appointments</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="new_appointment_alerts" 
                                       id="new_appointment_alerts" 
                                       <?php echo $notification_preferences['new_appointment_alerts'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="new_appointment_alerts">
                                    New Appointment Alerts
                                </label>
                            </div>
                            <small class="text-muted">Alert when new appointments are booked</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="emergency_alerts" 
                                       id="emergency_alerts" 
                                       <?php echo $notification_preferences['emergency_alerts'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emergency_alerts">
                                    Emergency Alerts
                                </label>
                            </div>
                            <small class="text-muted">Critical and emergency case notifications</small>
                        </div>
                        
                        <button type="submit" name="update_notifications" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Preferences
                        </button>
                    </form>
                </div>

                <!-- Account Actions -->
                <div class="settings-card">
                    <h5 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Account Security</h5>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#twoFAModal">
                            <i class="fas fa-mobile-alt me-2"></i>Two-Factor Authentication
                        </button>
                        
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash-alt me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Two-Factor Authentication Modal -->
<div class="modal fade" id="twoFAModal" tabindex="-1" aria-labelledby="twoFAModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="twoFAModalLabel">Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Enhance your account security by enabling two-factor authentication.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This feature will require you to enter a verification code from your mobile device when logging in.
                </div>
                <p class="text-muted small">Coming soon in the next update.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" disabled>Enable 2FA</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <p>Deleting your account will:</p>
                <ul>
                    <li>Permanently remove all your personal information</li>
                    <li>Delete all your appointments and medical records</li>
                    <li>Remove your access to the VetCareQR system</li>
                </ul>
                <p class="text-muted">If you're sure you want to proceed, please contact system administration.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" disabled>Contact Admin to Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update date and time
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // Enable/disable time inputs based on day toggle
    document.querySelectorAll('.form-check-input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.name.includes('_enabled')) {
            checkbox.addEventListener('change', function() {
                const dayKey = this.name.replace('_enabled', '');
                const startInput = document.querySelector(`input[name="${dayKey}_start"]`);
                const endInput = document.querySelector(`input[name="${dayKey}_end"]`);
                
                if (startInput && endInput) {
                    startInput.disabled = !this.checked;
                    endInput.disabled = !this.checked;
                }
            });
        }
    });

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>

