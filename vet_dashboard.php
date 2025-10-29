<?php
session_start();
include("conn.php");

// Check if user is logged in and is a vet
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vet') {
    header("Location: login.php");
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
        $update_stmt->bind_param("si", $target_file, $vet_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['profile_picture'] = $target_file;
            $_SESSION['success'] = "Profile picture updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile picture in database.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
    
    header("Location: vet_dashboard.php");
    exit();
}

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// ✅ FIXED: Fetch pending appointment requests
$pending_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, p.age, p.gender, 
           u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'pending' OR a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$pending_appointments_stmt->execute();
$pending_appointments = $pending_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ FIXED: Fetch confirmed appointments (today and upcoming)
$today = date('Y-m-d');
$confirmed_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'confirmed' AND a.appointment_date >= ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$confirmed_appointments_stmt->bind_param("s", $today);
$confirmed_appointments_stmt->execute();
$confirmed_appointments = $confirmed_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ FIXED: Fetch today's appointments
$today_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, u.name as owner_name, u.phone_number as owner_phone
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_date = ? AND (a.status = 'confirmed' OR a.status = 'scheduled')
    ORDER BY a.appointment_time ASC
");
$today_appointments_stmt->bind_param("s", $today);
$today_appointments_stmt->execute();
$today_appointments = $today_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ FIXED: Fetch unread notifications for vet
$notifications_stmt = $conn->prepare("
    SELECT * FROM vet_notifications 
    WHERE vet_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$notifications_stmt->bind_param("i", $vet_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count statistics
$pending_count = count($pending_appointments);
$today_count = count($today_appointments);
$confirmed_count = count($confirmed_appointments);
$notification_count = count($notifications);

// ✅ FIXED: Handle appointment status update
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
    $appointment_info_stmt->bind_param("i", $appointment_id);
    $appointment_info_stmt->execute();
    $appointment_info = $appointment_info_stmt->get_result()->fetch_assoc();
    
    if ($appointment_info) {
        // Update appointment status
        $update_stmt = $conn->prepare("
            UPDATE appointments 
            SET status = ?, vet_notes = ?, updated_at = NOW() 
            WHERE appointment_id = ?
        ");
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
                $user_notification_stmt->bind_param("isi", $appointment_info['user_id'], $message, $appointment_id);
                $user_notification_stmt->execute();
            }
            
            $_SESSION['success'] = "Appointment " . $new_status . " and owner notified!";
            
            // Mark related vet notifications as read
            $mark_read_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE appointment_id = ? AND vet_id = ?");
            $mark_read_stmt->bind_param("ii", $appointment_id, $vet_id);
            $mark_read_stmt->execute();
            
            header("Location: vet_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating appointment status: " . $conn->error;
        }
    }
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE vet_id = ?");
    $mark_all_stmt->bind_param("i", $vet_id);
    if ($mark_all_stmt->execute()) {
        $_SESSION['success'] = "All notifications marked as read!";
        header("Location: vet_dashboard.php");
        exit();
    }
}

// Handle mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE notification_id = ? AND vet_id = ?");
    $mark_stmt->bind_param("ii", $notification_id, $vet_id);
    $mark_stmt->execute();
    header("Location: vet_dashboard.php");
    exit();
}

// ✅ FIXED: Create notifications for any pending appointments without notifications (Temporary fix)
$check_pending_stmt = $conn->prepare("
    SELECT a.appointment_id, p.name as pet_name, a.appointment_date, a.appointment_time, u.name as owner_name
    FROM appointments a 
    JOIN pets p ON a.pet_id = p.pet_id 
    JOIN users u ON a.user_id = u.user_id
    WHERE (a.status = 'pending' OR a.status = 'scheduled') 
    AND a.appointment_id NOT IN (SELECT appointment_id FROM vet_notifications WHERE vet_id = ?)
    AND a.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$check_pending_stmt->bind_param("i", $vet_id);
$check_pending_stmt->execute();
$missing_notifications = $check_pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($missing_notifications as $appointment) {
    $message = "📅 New appointment request from " . $appointment['owner_name'] . " for " . $appointment['pet_name'] . " on " . 
               date('M j, Y', strtotime($appointment['appointment_date'])) . " at " . 
               date('g:i A', strtotime($appointment['appointment_time']));
    
    $insert_stmt = $conn->prepare("
        INSERT INTO vet_notifications (vet_id, message, appointment_id, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $insert_stmt->bind_param("isi", $vet_id, $message, $appointment['appointment_id']);
    $insert_stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Dashboard - VetCareQR</title>
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
        <div class="brand"><i class="fa-solid fa-paw"></i> VetCareQR</div>
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
        <a href="logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Veterinary Dashboard</h5>
                <small class="text-muted">Manage appointments and patient care</small>
            </div>
            <div class="d-flex align-items-center gap-3">
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
                    <div class="stats-number"><?php echo $notification_count; ?></div>
                    <div class="stats-label">Notifications</div>
                    <i class="fas fa-bell"></i>
                </div>
            </div>
        </div>

        <!-- Rest of the dashboard content remains the same -->
        <!-- ... (appointments sections, quick actions, etc.) ... -->

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
