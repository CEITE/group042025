<?php
session_start();
include("conn.php");

// Check if user is logged in and is a vet
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vet') {
    header("Location: login.php");
    exit();
}

$vet_id = $_SESSION['user_id'];

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Fetch pending appointment requests
$pending_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, p.age, p.gender, 
           u.name as owner_name, u.email as owner_email, u.user_id as owner_id
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
");
$pending_appointments_stmt->execute();
$pending_appointments = $pending_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch confirmed appointments (today and upcoming)
$today = date('Y-m-d');
$confirmed_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, u.name as owner_name, u.email as owner_email
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'confirmed' AND a.appointment_date >= ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$confirmed_appointments_stmt->bind_param("s", $today);
$confirmed_appointments_stmt->execute();
$confirmed_appointments = $confirmed_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch today's appointments
$today_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, u.name as owner_name
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_date = ? AND a.status = 'confirmed'
    ORDER BY a.appointment_time ASC
");
$today_appointments_stmt->bind_param("s", $today);
$today_appointments_stmt->execute();
$today_appointments = $today_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unread notifications for vet
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
                $message = "Great news! Your appointment for " . $appointment_info['pet_name'] . " on " . 
                          date('M j, Y', strtotime($appointment_info['appointment_date'])) . " at " . 
                          date('g:i A', strtotime($appointment_info['appointment_time'])) . " has been confirmed by the veterinarian!";
            } elseif ($new_status == 'cancelled') {
                $message = "Your appointment for " . $appointment_info['pet_name'] . " has been cancelled. " . 
                          (!empty($vet_notes) ? "Reason: " . $vet_notes : "Please contact the clinic for more information.");
            }
            
            // Insert user notification
            $user_notification_stmt = $conn->prepare("
                INSERT INTO user_notifications (user_id, message, appointment_id, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $user_notification_stmt->bind_param("isi", $appointment_info['user_id'], $message, $appointment_id);
            $user_notification_stmt->execute();
            
            $_SESSION['success'] = "Appointment " . $new_status . " and user notified!";
            
            header("Location: vet_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating appointment status.";
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
            --primary-pink: #e91e63;
            --secondary-pink: #f8bbd9;
            --light-pink: #fce4ec;
            --dark-pink: #ad1457;
            --accent-pink: #f48fb1;
            --blue: #4a6cf7;
            --green: #2ecc71;
            --orange: #f39c12;
            --red: #e74c3c;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light-pink) 0%, #f3e5f5 100%);
            margin: 0;
            color: #333;
            min-height: 100vh;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--secondary-pink);
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
            color: var(--dark-pink);
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
            border: 3px solid var(--accent-pink);
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .sidebar .profile img:hover {
            transform: scale(1.05);
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            margin: .3rem 0;
            text-decoration: none;
            color: #333;
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
            background: var(--light-pink);
            color: var(--dark-pink);
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #dc3545, #e74c3c);
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
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
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
            border-radius: 16px;
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
        .badge-pending { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .badge-confirmed { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .badge-completed { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge-cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        /* Appointment Cards */
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .appointment-pending { 
            border-left-color: #f39c12; 
            background: linear-gradient(135deg, #fef9e7, #fef5e7);
        }
        
        .appointment-confirmed { 
            border-left-color: #2ecc71; 
            background: linear-gradient(135deg, #eafaf1, #e8f8f5);
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
            background: var(--light-pink);
            color: var(--dark-pink);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
        }
        
        .notification-item {
            border-left: 4px solid var(--primary-pink);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .notification-item.unread {
            background: var(--light-pink);
            border-left-color: var(--dark-pink);
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        /* Action Buttons */
        .btn-confirm { 
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-confirm:hover {
            background: linear-gradient(135deg, #27ae60, #229954);
            transform: translateY(-2px);
        }
        
        .btn-cancel { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                     alt="Vet" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
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
                        <?php if (count($notifications) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo count($notifications); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 350px;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            <?php if (count($notifications) > 0): ?>
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
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="stats-number"><?php echo $today_count; ?></div>
                    <div class="stats-label">Today's Appointments</div>
                    <i class="fas fa-calendar-day fa-2x mt-2"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <div class="stats-number"><?php echo $pending_count; ?></div>
                    <div class="stats-label">Pending Requests</div>
                    <i class="fas fa-clock fa-2x mt-2"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <div class="stats-number"><?php echo count($notifications); ?></div>
                    <div class="stats-label">New Notifications</div>
                    <i class="fas fa-bell fa-2x mt-2"></i>
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
                                                <small class="text-muted">Pet Information</small>
                                                <div class="fw-semibold">
                                                    <?php echo htmlspecialchars($appointment['species']); ?> • 
                                                    <?php echo htmlspecialchars($appointment['breed']); ?> • 
                                                    <?php echo htmlspecialchars($appointment['age']); ?> years
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
                                                    <?php echo htmlspecialchars($appointment['owner_email']); ?>
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
                                        </div>
                                        
                                        <!-- Reschedule Modal (Placeholder) -->
                                        <div class="modal fade" id="rescheduleModal<?php echo $appointment['appointment_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reschedule Appointment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Rescheduling functionality would be implemented here.</p>
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            This feature is coming soon!
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
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
                                <h4 class="mb-0 text-primary"><?php echo $pending_count; ?></h4>
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
                                <h4 class="mb-0 text-warning"><?php echo count($notifications); ?></h4>
                                <small>Alerts</small>
                            </div>
                        </div>
                    </div>
                </div>
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


