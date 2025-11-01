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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_vet.php");
    exit();
}

// Check if user has the correct role
if ($_SESSION['role'] !== 'vet') {
    // Clear any existing session data
    session_unset();
    session_destroy();
    
    // Redirect to appropriate login based on role
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            header("Location: login_admin.php");
        } else {
            header("Location: login.php");
        }
    } else {
        header("Location: login_vet.php");
    }
    exit();
}

// Validate session and prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // session started more than 30 minutes ago
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Function to validate session security
function validateSession() {
    if (isset($_SESSION['IPaddress']) && isset($_SESSION['userAgent'])) {
        if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR']) {
            return false;
        }
        if ($_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT']) {
            return false;
        }
    } else {
        $_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    return true;
}

// Call the function
if (!validateSession()) {
    session_unset();
    session_destroy();
    header("Location: login_vet.php?error=session_invalid");
    exit();
}

$vet_id = $_SESSION['user_id'];

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = "uploads/profiles/";
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['profile_picture']['name']);
    $target_file = $upload_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($_FILES['profile_picture']['tmp_name']);
    if ($check === false) {
        $_SESSION['error'] = "File is not an image.";
    } 
    // Check file size (5MB max)
    elseif ($_FILES['profile_picture']['size'] > 5000000) {
        $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
    }
    // Allow certain file formats
    elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        $_SESSION['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }
    // Upload file
    elseif (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
        // Update database
        $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        if (!$update_stmt) {
            $_SESSION['error'] = "Database error: " . $conn->error;
        } else {
            $update_stmt->bind_param("si", $target_file, $vet_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['profile_picture'] = $target_file;
                $_SESSION['success'] = "Profile picture updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating profile picture in database: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
    
    header("Location: vet_dashboard.php");
    exit();
}

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $vet_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$vet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Fetch pending appointment requests
$pending_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, p.age, p.gender, 
           u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'pending' OR a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
if (!$pending_appointments_stmt) {
    die("Prepare failed: " . $conn->error);
}
$pending_appointments_stmt->execute();
$pending_appointments = $pending_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_appointments_stmt->close();

// Fetch confirmed appointments (today and upcoming)
$today = date('Y-m-d');
$confirmed_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'confirmed' AND a.appointment_date >= ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
if (!$confirmed_appointments_stmt) {
    die("Prepare failed: " . $conn->error);
}
$confirmed_appointments_stmt->bind_param("s", $today);
$confirmed_appointments_stmt->execute();
$confirmed_appointments = $confirmed_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$confirmed_appointments_stmt->close();

// Fetch today's appointments
$today_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, u.name as owner_name, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_date = ? AND (a.status = 'confirmed' OR a.status = 'scheduled')
    ORDER BY a.appointment_time ASC
");
if (!$today_appointments_stmt) {
    die("Prepare failed: " . $conn->error);
}
$today_appointments_stmt->bind_param("s", $today);
$today_appointments_stmt->execute();
$today_appointments = $today_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$today_appointments_stmt->close();

// Fetch unread notifications for vet
$notifications_stmt = $conn->prepare("
    SELECT * FROM vet_notifications 
    WHERE vet_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
if (!$notifications_stmt) {
    die("Prepare failed: " . $conn->error);
}
$notifications_stmt->bind_param("i", $vet_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notifications_stmt->close();

// Fetch emails sent to veterinarian
$emails_stmt = $conn->prepare("
    SELECT el.*, u.name as sender_name 
    FROM email_logs el 
    LEFT JOIN users u ON el.sent_by = u.user_id 
    WHERE el.vet_id = ? 
    ORDER BY el.sent_at DESC 
    LIMIT 10
");
if (!$emails_stmt) {
    die("Prepare failed: " . $conn->error);
}
$emails_stmt->bind_param("i", $vet_id);
$emails_stmt->execute();
$received_emails = $emails_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$emails_stmt->close();

// Count statistics
$pending_count = count($pending_appointments);
$today_count = count($today_appointments);
$confirmed_count = count($confirmed_appointments);
$notification_count = count($notifications);
$email_count = count($received_emails);

// Handle appointment status update
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $vet_notes = $_POST['vet_notes'] ?? '';
    
    // Get appointment details for notification
    $appointment_info_stmt = $conn->prepare("
        SELECT u.user_id, u.name as owner_name, p.name as pet_name, a.appointment_date, a.appointment_time 
        FROM appointments a 
        JOIN users u ON a.user_id = u.user_id 
        JOIN pets p ON a.pet_id = p.pet_id
        WHERE a.appointment_id = ?
    ");
    if ($appointment_info_stmt) {
        $appointment_info_stmt->bind_param("i", $appointment_id);
        $appointment_info_stmt->execute();
        $appointment_info = $appointment_info_stmt->get_result()->fetch_assoc();
        $appointment_info_stmt->close();
        
        if ($appointment_info) {
            // Update appointment status
            $update_stmt = $conn->prepare("
                UPDATE appointments 
                SET status = ?, vet_notes = ?, updated_at = NOW() 
                WHERE appointment_id = ?
            ");
            if ($update_stmt) {
                $update_stmt->bind_param("ssi", $new_status, $vet_notes, $appointment_id);
                
                if ($update_stmt->execute()) {
                    // Create notification for user
                    if ($new_status == 'confirmed') {
                        $message = "✅ Great news! Your appointment for " . $appointment_info['pet_name'] . " on " . 
                                  date('M j, Y', strtotime($appointment_info['appointment_date'])) . " at " . 
                                  date('g:i A', strtotime($appointment_info['appointment_time'])) . " has been confirmed!";
                    } elseif ($new_status == 'cancelled') {
                        $message = "❌ Your appointment for " . $appointment_info['pet_name'] . " has been cancelled. " . 
                                  (!empty($vet_notes) ? "Reason: " . $vet_notes : "Please contact the clinic for more information.");
                    } elseif ($new_status == 'completed') {
                        $message = "✅ Appointment completed for " . $appointment_info['pet_name'] . " on " . 
                                  date('M j, Y', strtotime($appointment_info['appointment_date'])) . ". Thank you for choosing our clinic!";
                    }
                    
                    // Insert user notification
                    if (isset($message)) {
                        $user_notification_stmt = $conn->prepare("
                            INSERT INTO user_notifications (user_id, message, appointment_id, created_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        if ($user_notification_stmt) {
                            $user_notification_stmt->bind_param("isi", $appointment_info['user_id'], $message, $appointment_id);
                            $user_notification_stmt->execute();
                            $user_notification_stmt->close();
                        }
                    }
                    
                    $_SESSION['success'] = "Appointment " . $new_status . " and owner notified!";
                    
                    // Mark related vet notifications as read
                    $mark_read_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE appointment_id = ? AND vet_id = ?");
                    if ($mark_read_stmt) {
                        $mark_read_stmt->bind_param("ii", $appointment_id, $vet_id);
                        $mark_read_stmt->execute();
                        $mark_read_stmt->close();
                    }
                    
                    header("Location: vet_dashboard.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Error updating appointment status: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
    }
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE vet_id = ?");
    if ($mark_all_stmt) {
        $mark_all_stmt->bind_param("i", $vet_id);
        if ($mark_all_stmt->execute()) {
            $_SESSION['success'] = "All notifications marked as read!";
        } else {
            $_SESSION['error'] = "Error marking notifications as read: " . $mark_all_stmt->error;
        }
        $mark_all_stmt->close();
    }
    header("Location: vet_dashboard.php");
    exit();
}

// Handle mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE notification_id = ? AND vet_id = ?");
    if ($mark_stmt) {
        $mark_stmt->bind_param("ii", $notification_id, $vet_id);
        $mark_stmt->execute();
        $mark_stmt->close();
    }
    header("Location: vet_dashboard.php");
    exit();
}

// Handle mark email as read
if (isset($_GET['mark_email_read'])) {
    $email_id = $_GET['mark_email_read'];
    $mark_email_stmt = $conn->prepare("UPDATE email_logs SET is_read = 1 WHERE id = ? AND vet_id = ?");
    if ($mark_email_stmt) {
        $mark_email_stmt->bind_param("ii", $email_id, $vet_id);
        $mark_email_stmt->execute();
        $mark_email_stmt->close();
    }
    header("Location: vet_dashboard.php");
    exit();
}

// Create notifications for any pending appointments without notifications (Temporary fix)
$check_pending_stmt = $conn->prepare("
    SELECT a.appointment_id, p.name as pet_name, a.appointment_date, a.appointment_time, u.name as owner_name
    FROM appointments a 
    JOIN pets p ON a.pet_id = p.pet_id 
    JOIN users u ON a.user_id = u.user_id
    WHERE (a.status = 'pending' OR a.status = 'scheduled') 
    AND a.appointment_id NOT IN (SELECT appointment_id FROM vet_notifications WHERE vet_id = ?)
    AND a.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
");
if ($check_pending_stmt) {
    $check_pending_stmt->bind_param("i", $vet_id);
    $check_pending_stmt->execute();
    $missing_notifications = $check_pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $check_pending_stmt->close();

    foreach ($missing_notifications as $appointment) {
        $message = "📅 New appointment request from " . $appointment['owner_name'] . " for " . $appointment['pet_name'] . " on " . 
                   date('M j, Y', strtotime($appointment['appointment_date'])) . " at " . 
                   date('g:i A', strtotime($appointment['appointment_time']));
        
        $insert_stmt = $conn->prepare("
            INSERT INTO vet_notifications (vet_id, message, appointment_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        if ($insert_stmt) {
            $insert_stmt->bind_param("isi", $vet_id, $message, $appointment['appointment_id']);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Dashboard - BrightView Veterinary Clinic</title>
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
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
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
            position: relative;
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid var(--primary);
            object-fit: cover;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .sidebar .profile img:hover {
            transform: scale(1.05);
            opacity: 0.8;
        }
        
        .profile-edit-overlay {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        
        .profile:hover .profile-edit-overlay {
            opacity: 1;
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
        
        .card-custom {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
            transition: transform 0.3s;
        }
        
        .card-custom:hover {
            transform: translateY(-2px);
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: var(--radius);
            height: 100%;
            color: white;
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        /* Status Badges */
        .badge-pending { background: linear-gradient(135deg, var(--warning), #e67e22); }
        .badge-confirmed { background: linear-gradient(135deg, var(--success), #27ae60); }
        .badge-completed { background: linear-gradient(135deg, var(--primary), #2980b9); }
        .badge-cancelled { background: linear-gradient(135deg, var(--danger), #c0392b); }
        
        /* Appointment Cards */
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .appointment-pending { 
            border-left-color: var(--warning); 
            background: linear-gradient(135deg, var(--warning-light), #fef5e7);
        }
        
        .appointment-confirmed { 
            border-left-color: var(--success); 
            background: linear-gradient(135deg, var(--success-light), #e8f8f5);
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
        }
        
        .pet-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .alert-custom {
            border-radius: var(--radius);
            border: none;
        }
        
        .notification-item {
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .notification-item.unread {
            background: var(--primary-light);
            border-left-color: var(--primary-dark);
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        .email-item {
            border-left: 4px solid var(--secondary);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .email-item.unread {
            background: var(--primary-light);
            border-left-color: var(--secondary);
        }
        
        .email-item:hover {
            transform: translateX(5px);
            background: #f8f9fa;
        }
        
        /* Action Buttons */
        .btn-confirm { 
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-confirm:hover {
            background: linear-gradient(135deg, #27ae60, #229954);
            transform: translateY(-2px);
        }
        
        .btn-cancel { 
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .client-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
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
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-paw"></i> BrightView<br>Veterinary Clinic
        </div>
        <div class="profile">
            <div class="profile-picture-container" data-bs-toggle="modal" data-bs-target="#profilePictureModal">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                     alt="Vet" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
                <div class="profile-edit-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <h6 id="vetNameSidebar">Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($vet['role']); ?></small>
        </div>

        <a href="vet_dashboard.php" class="active">
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
        <a href="vet_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout_vet.php" class="logout">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Veterinary Dashboard</h5>
                <small class="text-muted">Welcome to BrightView Veterinary Clinic Management System</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Email Inbox -->
                <div class="dropdown">
                    <a href="#" class="btn btn-outline-info position-relative" data-bs-toggle="dropdown">
                        <i class="fas fa-envelope"></i>
                        <?php if ($email_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $email_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 400px;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Messages</h6>
                            <small class="text-muted"><?php echo $email_count; ?> new</small>
                        </div>
                        <div class="email-list" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($received_emails)): ?>
                                <div class="p-3 text-center text-muted">
                                    <i class="fas fa-envelope-open fa-2x mb-2"></i>
                                    <p class="mb-0">No new messages</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($received_emails as $email): ?>
                                    <div class="email-item <?php echo $email['is_read'] == 0 ? 'unread' : ''; ?>" 
                                         onclick="viewEmail(<?php echo $email['id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small fw-bold"><?php echo htmlspecialchars($email['subject']); ?></h6>
                                                <p class="mb-1 small text-truncate"><?php echo htmlspecialchars(substr($email['message'], 0, 100)); ?>...</p>
                                                <small class="text-muted">
                                                    From: <?php echo htmlspecialchars($email['sender_name'] ?? 'Administration'); ?> | 
                                                    <?php echo date('M j, g:i A', strtotime($email['sent_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($email['is_read'] == 0): ?>
                                                <span class="badge bg-danger ms-2">New</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 border-top">
                            <a href="vet_messages.php" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-inbox me-2"></i>View All Messages
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Notification Bell -->
                <div class="dropdown">
                    <a href="#" class="btn btn-outline-primary position-relative" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $notification_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 350px;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            <?php if ($notification_count > 0): ?>
                                <a href="vet_dashboard.php?mark_all_read=1" class="btn btn-sm btn-outline-primary">Mark All Read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($notifications)): ?>
                                <div class="p-3 text-center text-muted">
                                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                    <p class="mb-0">No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item unread">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                            <a href="vet_dashboard.php?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <strong id="currentDate"></strong><br>
                    <small id="currentTime"></small>
                </div>
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

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <div class="stats-number"><?php echo $today_count; ?></div>
                    <div class="stats-label">Today's Appointments</div>
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning), #f5576c);">
                    <div class="stats-number"><?php echo $pending_count; ?></div>
                    <div class="stats-label">Pending Requests</div>
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success), #00f2fe);">
                    <div class="stats-number"><?php echo $confirmed_count; ?></div>
                    <div class="stats-label">Confirmed</div>
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary), #38f9d7);">
                    <div class="stats-number"><?php echo $email_count; ?></div>
                    <div class="stats-label">New Messages</div>
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Appointment Requests -->
            <div class="col-lg-8 mb-4">
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-clock me-2 text-warning"></i>Pending Appointment Requests</h4>
                        <span class="badge bg-warning"><?php echo $pending_count; ?> New</span>
                    </div>
                    
                    <?php if (empty($pending_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check text-success"></i>
                            <h5>No Pending Requests</h5>
                            <p class="text-muted">All appointment requests have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($pending_appointments as $appointment): ?>
                                <div class="card appointment-card appointment-pending mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="pet-avatar me-3">
                                                    <i class="fas fa-<?php echo strtolower($appointment['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($appointment['pet_name']); ?></h6>
                                                    <small class="text-muted">
                                                        Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?> | 
                                                        Service: <?php echo htmlspecialchars($appointment['service_type']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <span class="badge badge-pending">
                                                <i class="fas fa-clock me-1"></i>Pending Review
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <small class="text-muted">Requested Date & Time</small>
                                                <div class="fw-semibold">
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> 
                                                    at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Contact Information</small>
                                                <div class="fw-semibold">
                                                    📞 <?php echo htmlspecialchars($appointment['owner_phone']); ?><br>
                                                    📧 <?php echo htmlspecialchars($appointment['owner_email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($appointment['reason'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Reason for Visit</small>
                                                <div class="small"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($appointment['notes'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Owner Notes</small>
                                                <div class="small"><?php echo htmlspecialchars($appointment['notes']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Quick Action Buttons -->
                                        <div class="action-buttons">
                                            <form method="POST" action="vet_dashboard.php" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <input type="hidden" name="vet_notes" value="Appointment confirmed by veterinarian">
                                                <button type="submit" name="update_status" class="btn btn-confirm">
                                                    <i class="fas fa-check me-1"></i> Confirm Appointment
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-cancel" data-bs-toggle="modal" 
                                                    data-bs-target="#cancelModal<?php echo $appointment['appointment_id']; ?>">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal<?php echo $appointment['appointment_id']; ?>">
                                                <i class="fas fa-edit me-1"></i> Custom Response
                                            </button>
                                        </div>
                                        
                                        <!-- Cancel Modal -->
                                        <div class="modal fade" id="cancelModal<?php echo $appointment['appointment_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Cancel Appointment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="vet_dashboard.php">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Cancellation</label>
                                                                <textarea class="form-control" name="vet_notes" rows="3" 
                                                                          placeholder="Please provide a reason for cancellation..." required></textarea>
                                                            </div>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                This will notify the owner that their appointment has been cancelled.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_status" class="btn btn-danger">Cancel Appointment</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Custom Response Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $appointment['appointment_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Manage Appointment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="vet_dashboard.php">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Appointment Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="confirmed">Confirm Appointment</option>
                                                                    <option value="cancelled">Cancel Appointment</option>
                                                                    <option value="completed">Mark as Completed</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Veterinarian Notes</label>
                                                                <textarea class="form-control" name="vet_notes" rows="4" 
                                                                          placeholder="Add notes for the owner (this will be included in the notification)..."></textarea>
                                                                <div class="form-text">These notes will be visible to the pet owner.</div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Update Appointment</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Confirmed Appointments -->
                <div class="card-custom mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Confirmed Appointments</h4>
                        <span class="badge bg-success"><?php echo $confirmed_count; ?> Upcoming</span>
                    </div>
                    
                    <?php if (empty($confirmed_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Confirmed Appointments</h5>
                            <p class="text-muted">No upcoming confirmed appointments.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($confirmed_appointments as $appointment): ?>
                                <div class="card appointment-card appointment-confirmed mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="pet-avatar me-3">
                                                    <i class="fas fa-<?php echo strtolower($appointment['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($appointment['pet_name']); ?></h6>
                                                    <small class="text-muted">
                                                        Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?> | 
                                                        Service: <?php echo htmlspecialchars($appointment['service_type']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <span class="badge badge-confirmed">
                                                <i class="fas fa-check-circle me-1"></i>Confirmed
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <small class="text-muted">Scheduled Date & Time</small>
                                                <div class="fw-semibold">
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> 
                                                    at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Contact</small>
                                                <div class="fw-semibold">
                                                    📞 <?php echo htmlspecialchars($appointment['owner_phone']); ?><br>
                                                    📧 <?php echo htmlspecialchars($appointment['owner_email']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (!empty($appointment['vet_notes'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Vet Notes</small>
                                                <div class="small text-success"><?php echo htmlspecialchars($appointment['vet_notes']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Additional Actions for Confirmed Appointments -->
                                        <div class="action-buttons mt-3">
                                            <form method="POST" action="vet_dashboard.php" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <input type="hidden" name="vet_notes" value="<?php echo htmlspecialchars($appointment['vet_notes'] ?? ''); ?>">
                                                <button type="submit" name="update_status" class="btn btn-outline-success">
                                                    <i class="fas fa-flag-checkered me-1"></i> Mark Complete
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#rescheduleModal<?php echo $appointment['appointment_id']; ?>">
                                                <i class="fas fa-calendar-alt me-1"></i> Reschedule
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" 
                                                    data-bs-target="#notesModal<?php echo $appointment['appointment_id']; ?>">
                                                <i class="fas fa-notes-medical me-1"></i> Add Notes
                                            </button>
                                        </div>
                                        
                                        <!-- Reschedule Modal -->
                                        <div class="modal fade" id="rescheduleModal<?php echo $appointment['appointment_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reschedule Appointment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            To reschedule this appointment, please contact the owner directly or use the full appointments management page.
                                                        </div>
                                                        <div class="text-center">
                                                            <a href="vet_appointments.php" class="btn btn-primary">
                                                                <i class="fas fa-external-link-alt me-2"></i>Go to Appointments
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Notes Modal -->
                                        <div class="modal fade" id="notesModal<?php echo $appointment['appointment_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Add Medical Notes</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="vet_dashboard.php">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Medical Notes & Observations</label>
                                                                <textarea class="form-control" name="vet_notes" rows="6" 
                                                                          placeholder="Enter medical notes, observations, treatment details, or follow-up instructions..."><?php echo htmlspecialchars($appointment['vet_notes'] ?? ''); ?></textarea>
                                                                <div class="form-text">These notes will be saved with the appointment record.</div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Save Notes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions & Today's Schedule -->
            <div class="col-lg-4 mb-4">
                <!-- Quick Actions -->
                <div class="card-custom mb-4">
                    <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="vet_appointments.php" class="btn btn-outline-primary text-start">
                            <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                        </a>
                        <a href="vet_patients.php" class="btn btn-outline-success text-start">
                            <i class="fas fa-dog me-2"></i>Manage Patients
                        </a>
                        <a href="vet_records.php" class="btn btn-outline-info text-start">
                            <i class="fas fa-file-medical me-2"></i>Medical Records
                        </a>
                        <a href="vet_messages.php" class="btn btn-outline-warning text-start">
                            <i class="fas fa-envelope me-2"></i>View Messages
                        </a>
                        <a href="vet_settings.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-gear me-2"></i>Clinic Settings
                        </a>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-calendar-day me-2"></i>Today's Schedule</h5>
                    <?php if (empty($today_appointments)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                            <p class="mb-0">No appointments today</p>
                        </div>
                    <?php else: ?>
                        <div class="schedule-list">
                            <?php foreach ($today_appointments as $schedule): ?>
                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                    <div class="pet-avatar me-3">
                                        <i class="fas fa-<?php echo strtolower($schedule['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong class="d-block"><?php echo htmlspecialchars($schedule['pet_name']); ?></strong>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($schedule['appointment_time'])); ?></small>
                                        <small class="d-block"><?php echo htmlspecialchars($schedule['owner_name']); ?></small>
                                        <small class="text-muted"><?php echo htmlspecialchars($schedule['service_type']); ?></small>
                                    </div>
                                    <span class="badge badge-confirmed">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Stats -->
                <div class="card-custom mt-4">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h5>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-warning"><?php echo $pending_count; ?></h4>
                                <small>Pending</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-success"><?php echo $confirmed_count; ?></h4>
                                <small>Confirmed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-info"><?php echo $today_count; ?></h4>
                                <small>Today</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-primary"><?php echo $email_count; ?></h4>
                                <small>Messages</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Messages Preview -->
                <div class="card-custom mt-4">
                    <h5 class="mb-3"><i class="fas fa-envelope me-2"></i>Recent Messages</h5>
                    <?php if (empty($received_emails)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-envelope-open fa-2x mb-2"></i>
                            <p class="mb-0">No messages</p>
                        </div>
                    <?php else: ?>
                        <div class="messages-preview">
                            <?php foreach (array_slice($received_emails, 0, 3) as $email): ?>
                                <div class="d-flex align-items-start mb-3 p-2 border rounded">
                                    <div class="flex-grow-1">
                                        <strong class="d-block small"><?php echo htmlspecialchars($email['subject']); ?></strong>
                                        <small class="text-muted">From: <?php echo htmlspecialchars($email['sender_name'] ?? 'Admin'); ?></small>
                                        <small class="d-block text-truncate"><?php echo htmlspecialchars(substr($email['message'], 0, 50)); ?>...</small>
                                    </div>
                                    <?php if ($email['is_read'] == 0): ?>
                                        <span class="badge bg-danger ms-2">New</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-2">
                                <a href="vet_messages.php" class="btn btn-sm btn-outline-primary">View All Messages</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Picture Modal -->
<div class="modal fade" id="profilePictureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="vet_dashboard.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                             alt="Current Profile" 
                             id="currentProfilePicture"
                             class="rounded-circle mb-3"
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--primary);"
                             onerror="this.src='https://i.pravatar.cc/150?u=<?php echo urlencode($vet['name']); ?>'">
                        <div id="imagePreview" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--primary); display: none;"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Choose New Profile Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <div class="form-text">Supported formats: JPG, JPEG, PNG, GIF. Max size: 5MB</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your profile picture will be visible to pet owners when you confirm their appointments.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Profile Picture</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Email View Modal -->
<div class="modal fade" id="emailViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalTitle">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="emailModalContent">
                    <!-- Email content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="vet_messages.php" class="btn btn-primary">Go to Messages</a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date and time
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Auto-refresh dashboard every 2 minutes
        setInterval(refreshDashboard, 120000);
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Image preview for profile picture upload
        const profilePictureInput = document.getElementById('profile_picture');
        const imagePreview = document.getElementById('imagePreview');
        const currentProfilePicture = document.getElementById('currentProfilePicture');
        
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.style.backgroundImage = `url(${e.target.result})`;
                        imagePreview.style.display = 'block';
                        currentProfilePicture.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        console.log('Vet dashboard initialized successfully!');
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    function refreshDashboard() {
        console.log('Refreshing vet dashboard...');
        // In a real implementation, you might want to use AJAX to refresh data
        // For now, we'll just log to console
    }

    // View email function
    function viewEmail(emailId) {
        // Mark email as read
        window.location.href = 'vet_dashboard.php?mark_email_read=' + emailId;
        
        // In a real implementation, you would fetch the email content via AJAX
        // and display it in the modal
        console.log('Viewing email:', emailId);
        
        // For now, redirect to messages page
        // window.location.href = 'vet_messages.php?email_id=' + emailId;
    }

    // Quick action functions
    function quickConfirm(appointmentId) {
        if (confirm('Confirm this appointment?')) {
            // This would typically submit a form via AJAX
            console.log('Quick confirming appointment:', appointmentId);
        }
    }

    function quickCancel(appointmentId) {
        if (confirm('Cancel this appointment?')) {
            // This would typically submit a form via AJAX
            console.log('Quick cancelling appointment:', appointmentId);
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+C to focus on search (if implemented)
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
            e.preventDefault();
            const searchInput = document.querySelector('.input-group input');
            if (searchInput) searchInput.focus();
        }
    });
</script>
</body>
</html>

