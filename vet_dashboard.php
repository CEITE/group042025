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

// Fetch pending appointment requests (NEW - only pending appointments)
$pending_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, u.name as owner_name, u.email as owner_email
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
");
$pending_appointments_stmt->execute();
$pending_appointments = $pending_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch scheduled appointments (approved by vet)
$scheduled_appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species, p.breed, u.name as owner_name, u.email as owner_email
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.status IN ('scheduled', 'confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$scheduled_appointments_stmt->execute();
$scheduled_appointments = $scheduled_appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unread notifications
$notifications_stmt = $conn->prepare("
    SELECT * FROM vet_notifications 
    WHERE is_read = 0 
    ORDER BY created_at DESC
");
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count today's appointments
$today = date('Y-m-d');
$today_appointments_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE appointment_date = ? AND status IN ('scheduled', 'confirmed')
");
$today_appointments_stmt->bind_param("s", $today);
$today_appointments_stmt->execute();
$today_count = $today_appointments_stmt->get_result()->fetch_assoc()['count'];

// Count pending appointments (NEW - count only pending)
$pending_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE status = 'pending'
");
$pending_stmt->execute();
$pending_count = $pending_stmt->get_result()->fetch_assoc()['count'];

// Handle appointment status update
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $vet_notes = $_POST['vet_notes'] ?? '';
    
    $update_stmt = $conn->prepare("
        UPDATE appointments 
        SET status = ?, vet_notes = ?, updated_at = NOW() 
        WHERE appointment_id = ?
    ");
    $update_stmt->bind_param("ssi", $new_status, $vet_notes, $appointment_id);
    
    if ($update_stmt->execute()) {
        // Create notification for user when appointment is approved/rejected
        if ($new_status == 'scheduled' || $new_status == 'cancelled') {
            $appointment_info_stmt = $conn->prepare("
                SELECT u.user_id, a.appointment_date, a.appointment_time 
                FROM appointments a 
                JOIN users u ON a.user_id = u.user_id 
                WHERE a.appointment_id = ?
            ");
            $appointment_info_stmt->bind_param("i", $appointment_id);
            $appointment_info_stmt->execute();
            $appointment_info = $appointment_info_stmt->get_result()->fetch_assoc();
            
            if ($appointment_info) {
                $message = $new_status == 'scheduled' 
                    ? "Your appointment on " . date('M j, Y', strtotime($appointment_info['appointment_date'])) . " at " . date('g:i A', strtotime($appointment_info['appointment_time'])) . " has been approved!"
                    : "Your appointment request has been cancelled by the vet.";
                
                $user_notification_stmt = $conn->prepare("
                    INSERT INTO user_notifications (user_id, message, appointment_id, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $user_notification_stmt->bind_param("isi", $appointment_info['user_id'], $message, $appointment_id);
                $user_notification_stmt->execute();
            }
        }
        
        $_SESSION['success'] = "Appointment status updated successfully!";
        
        // Mark related notifications as read
        $mark_read_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE appointment_id = ?");
        $mark_read_stmt->bind_param("i", $appointment_id);
        $mark_read_stmt->execute();
        
        header("Location: vet_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating appointment status.";
    }
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1");
    if ($mark_all_stmt->execute()) {
        $_SESSION['success'] = "All notifications marked as read!";
        header("Location: vet_dashboard.php");
        exit();
    }
}

// Handle mark single notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE vet_notifications SET is_read = 1 WHERE notification_id = ?");
    $mark_stmt->bind_param("i", $notification_id);
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
        /* Your existing CSS styles remain the same */
        :root {
            --primary-pink: #e91e63;
            --secondary-pink: #f8bbd9;
            --light-pink: #fce4ec;
            --dark-pink: #ad1457;
            --accent-pink: #f48fb1;
            --blue: #4a6cf7;
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        /* ... (keep all your existing CSS styles) ... */
        
        .status-pending {
            background-color: var(--orange-light);
            color: var(--orange);
        }
        
        .badge-new {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 10px;
        }
        
        .appointment-card.pending {
            border-left: 4px solid var(--orange);
            background: var(--orange-light);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, var(--green), #27ae60);
            color: white;
            border: none;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar (unchanged) -->
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
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar (unchanged) -->
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
                <div class="stats-card stats-today">
                    <div class="stats-number"><?php echo $today_count; ?></div>
                    <div class="stats-label">Today's Appointments</div>
                    <i class="fas fa-calendar-day fa-2x mt-2"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card stats-pending">
                    <div class="stats-number"><?php echo $pending_count; ?></div>
                    <div class="stats-label">Pending Requests</div>
                    <i class="fas fa-clock fa-2x mt-2"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card stats-notifications">
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
                        <span class="badge bg-warning"><?php echo count($pending_appointments); ?> New</span>
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
                                <div class="card appointment-card pending mb-3">
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
                                            <span class="status-badge status-pending">
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
                                                <small class="text-muted">Contact</small>
                                                <div class="fw-semibold">
                                                    <?php echo htmlspecialchars($appointment['owner_email']); ?>
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
                                                <input type="hidden" name="status" value="scheduled">
                                                <input type="hidden" name="vet_notes" value="Appointment approved by vet">
                                                <button type="submit" name="update_status" class="btn btn-approve">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="vet_dashboard.php" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <input type="hidden" name="vet_notes" value="Appointment rejected by vet">
                                                <button type="submit" name="update_status" class="btn btn-reject">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $appointment['appointment_id']; ?>">
                                                <i class="fas fa-edit me-1"></i> Custom Response
                                            </button>
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
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="scheduled">Approve (Scheduled)</option>
                                                                    <option value="cancelled">Reject (Cancelled)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Vet Notes</label>
                                                                <textarea class="form-control" name="vet_notes" rows="3" placeholder="Add notes for the owner..."></textarea>
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

                <!-- Scheduled Appointments -->
                <div class="card-custom mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-calendar-check me-2 text-success"></i>Scheduled Appointments</h4>
                        <span class="badge bg-success"><?php echo count($scheduled_appointments); ?> Total</span>
                    </div>
                    
                    <?php if (empty($scheduled_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Scheduled Appointments</h5>
                            <p class="text-muted">No upcoming appointments at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($scheduled_appointments as $appointment): ?>
                                <div class="card appointment-card mb-3">
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
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <small class="text-muted">Date & Time</small>
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
                                        
                                        <!-- Action Form -->
                                        <form method="POST" action="vet_dashboard.php" class="mt-3">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                            <div class="row align-items-end">
                                                <div class="col-md-5">
                                                    <label class="form-label">Update Status</label>
                                                    <select class="form-select" name="status" required>
                                                        <option value="scheduled" <?php echo $appointment['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                        <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                        <option value="completed">Completed</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Vet Notes</label>
                                                    <input type="text" class="form-control" name="vet_notes" placeholder="Add notes..." value="<?php echo htmlspecialchars($appointment['vet_notes'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" name="update_status" class="btn btn-primary w-100">Update</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions & Info -->
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
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Today's Schedule</h5>
                    <?php
                    $today_schedule_stmt = $conn->prepare("
                        SELECT a.*, p.name as pet_name, p.species, u.name as owner_name
                        FROM appointments a 
                        LEFT JOIN pets p ON a.pet_id = p.pet_id 
                        LEFT JOIN users u ON a.user_id = u.user_id
                        WHERE a.appointment_date = ? AND a.status IN ('scheduled', 'confirmed')
                        ORDER BY a.appointment_time ASC
                    ");
                    $today_schedule_stmt->bind_param("s", $today);
                    $today_schedule_stmt->execute();
                    $today_schedule = $today_schedule_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($today_schedule)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                            <p class="mb-0">No appointments today</p>
                        </div>
                    <?php else: ?>
                        <div class="schedule-list">
                            <?php foreach ($today_schedule as $schedule): ?>
                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                    <div class="pet-avatar me-3">
                                        <i class="fas fa-<?php echo strtolower($schedule['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong class="d-block"><?php echo htmlspecialchars($schedule['pet_name']); ?></strong>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($schedule['appointment_time'])); ?></small>
                                        <small class="d-block"><?php echo htmlspecialchars($schedule['owner_name']); ?></small>
                                    </div>
                                    <span class="status-badge status-<?php echo $schedule['status']; ?>">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        
        // Auto-refresh notifications every 30 seconds
        setInterval(refreshNotifications, 30000);
        
        console.log('Vet dashboard initialized successfully!');
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    function refreshNotifications() {
        // This would typically make an AJAX call to check for new notifications
        console.log('Checking for new notifications...');
        // In a real implementation, you would fetch new notifications and update the UI
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
