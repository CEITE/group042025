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

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// ✅ Fetch pending appointment requests
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

// ✅ Fetch confirmed appointments (today and upcoming)
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

// ✅ Fetch today's appointments
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

// ✅ Fetch unread notifications for vet
$notifications_stmt = $conn->prepare("
    SELECT * FROM vet_notifications 
    WHERE vet_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$notifications_stmt->bind_param("i", $vet_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ NEW: Fetch recent patients with medical records
$recent_patients_stmt = $conn->prepare("
    SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone,
           COUNT(a.appointment_id) as total_visits,
           MAX(a.appointment_date) as last_visit
    FROM pets p 
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN appointments a ON p.pet_id = a.pet_id
    GROUP BY p.pet_id
    ORDER BY p.last_vet_visit DESC, p.created_at DESC
    LIMIT 10
");
$recent_patients_stmt->execute();
$recent_patients = $recent_patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ NEW: Fetch pets needing vaccination soon (next 30 days)
$vaccination_reminders_stmt = $conn->prepare("
    SELECT p.pet_id, p.name as pet_name, p.species, p.breed, 
           u.name as owner_name, u.phone_number as owner_phone,
           p.rabies_vaccine_date, p.dhpp_vaccine_date,
           p.next_vet_visit
    FROM pets p 
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE (p.rabies_vaccine_date IS NULL OR p.rabies_vaccine_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        OR (p.dhpp_vaccine_date IS NULL OR p.dhpp_vaccine_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        OR (p.next_vet_visit IS NULL OR p.next_vet_visit <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    ORDER BY GREATEST(COALESCE(p.rabies_vaccine_date, '2000-01-01'), 
                     COALESCE(p.dhpp_vaccine_date, '2000-01-01'),
                     COALESCE(p.next_vet_visit, '2000-01-01')) ASC
    LIMIT 5
");
$vaccination_reminders_stmt->execute();
$vaccination_reminders = $vaccination_reminders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count statistics
$pending_count = count($pending_appointments);
$today_count = count($today_appointments);
$confirmed_count = count($confirmed_appointments);
$notification_count = count($notifications);
$patient_count = count($recent_patients);
$reminder_count = count($vaccination_reminders);

// ✅ Handle appointment status update
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
            // Update pet's last vet visit if appointment completed
            if ($new_status == 'completed') {
                $update_pet_stmt = $conn->prepare("
                    UPDATE pets SET last_vet_visit = CURDATE() 
                    WHERE pet_id = (SELECT pet_id FROM appointments WHERE appointment_id = ?)
                ");
                $update_pet_stmt->bind_param("i", $appointment_id);
                $update_pet_stmt->execute();
            }
            
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

// ✅ NEW: Handle medical record updates
if (isset($_POST['update_medical_record'])) {
    $pet_id = $_POST['pet_id'];
    $medical_notes = $_POST['medical_notes'] ?? '';
    $previous_conditions = $_POST['previous_conditions'] ?? '';
    $vaccination_history = $_POST['vaccination_history'] ?? '';
    $surgical_history = $_POST['surgical_history'] ?? '';
    $medication_history = $_POST['medication_history'] ?? '';
    $weight = $_POST['weight'] ?? null;
    $next_vet_visit = $_POST['next_vet_visit'] ?? null;
    $rabies_vaccine_date = $_POST['rabies_vaccine_date'] ?? null;
    $dhpp_vaccine_date = $_POST['dhpp_vaccine_date'] ?? null;
    $is_spayed_neutered = isset($_POST['is_spayed_neutered']) ? 1 : 0;
    $spay_neuter_date = $_POST['spay_neuter_date'] ?? null;
    
    $update_stmt = $conn->prepare("
        UPDATE pets SET 
        medical_notes = ?, previous_conditions = ?, vaccination_history = ?, 
        surgical_history = ?, medication_history = ?, weight = ?, next_vet_visit = ?,
        rabies_vaccine_date = ?, dhpp_vaccine_date = ?, is_spayed_neutered = ?, spay_neuter_date = ?,
        medical_history_updated_at = NOW()
        WHERE pet_id = ?
    ");
    
    $update_stmt->bind_param("sssssdsssisi", 
        $medical_notes, $previous_conditions, $vaccination_history,
        $surgical_history, $medication_history, $weight, $next_vet_visit,
        $rabies_vaccine_date, $dhpp_vaccine_date, $is_spayed_neutered, $spay_neuter_date,
        $pet_id
    );
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Medical record updated successfully!";
        header("Location: vet_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating medical record: " . $conn->error;
    }
}

// ✅ NEW: Handle quick vaccination update
if (isset($_POST['update_vaccination'])) {
    $pet_id = $_POST['pet_id'];
    $vaccine_type = $_POST['vaccine_type'];
    $vaccine_date = $_POST['vaccine_date'];
    
    if ($vaccine_type == 'rabies') {
        $update_stmt = $conn->prepare("UPDATE pets SET rabies_vaccine_date = ? WHERE pet_id = ?");
    } else {
        $update_stmt = $conn->prepare("UPDATE pets SET dhpp_vaccine_date = ? WHERE pet_id = ?");
    }
    
    $update_stmt->bind_param("si", $vaccine_date, $pet_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = ucfirst($vaccine_type) . " vaccination date updated!";
        header("Location: vet_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating vaccination: " . $conn->error;
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

// ✅ Create notifications for any pending appointments without notifications
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
        
        /* Patient Cards */
        .patient-card {
            border-left: 4px solid var(--blue);
            background: linear-gradient(135deg, #e3f2fd, #e1f5fe);
            transition: all 0.3s;
        }
        
        .patient-card:hover {
            transform: translateX(5px);
        }
        
        .reminder-card {
            border-left: 4px solid var(--orange);
            background: linear-gradient(135deg, #fff3e0, #fff8e1);
            transition: all 0.3s;
        }
        
        .reminder-card:hover {
            transform: translateX(5px);
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
        
        /* Vaccine Status */
        .vaccine-due { color: #e74c3c; font-weight: bold; }
        .vaccine-upcoming { color: #f39c12; font-weight: bold; }
        .vaccine-current { color: #27ae60; font-weight: bold; }
        
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
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="stats-number"><?php echo $today_count; ?></div>
                    <div class="stats-label">Today's Appointments</div>
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <div class="stats-number"><?php echo $pending_count; ?></div>
                    <div class="stats-label">Pending Requests</div>
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <div class="stats-number"><?php echo $confirmed_count; ?></div>
                    <div class="stats-label">Confirmed</div>
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                    <div class="stats-number"><?php echo $reminder_count; ?></div>
                    <div class="stats-label">Vaccine Reminders</div>
                    <i class="fas fa-syringe"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Appointments & Patients -->
            <div class="col-lg-6 mb-4">
                <!-- Pending Appointment Requests -->
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
                            <?php foreach (array_slice($pending_appointments, 0, 3) as $appointment): ?>
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
                                                        Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <span class="badge badge-pending">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <small class="text-muted">Date & Time</small>
                                                <div class="fw-semibold">
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> 
                                                    at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (!empty($appointment['reason'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Reason</small>
                                                <div class="small"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Quick Action Buttons -->
                                        <div class="action-buttons">
                                            <form method="POST" action="vet_dashboard.php" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <input type="hidden" name="vet_notes" value="Appointment confirmed by veterinarian">
                                                <button type="submit" name="update_status" class="btn btn-confirm btn-sm">
                                                    <i class="fas fa-check me-1"></i> Confirm
                                                </button>
                                            </form>
                                            
                                            <a href="vet_appointments.php" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i> View All
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Patients -->
                <div class="card-custom mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-paw me-2 text-info"></i>Recent Patients</h4>
                        <a href="vet_patients.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    
                    <?php if (empty($recent_patients)): ?>
                        <div class="empty-state">
                            <i class="fas fa-paw"></i>
                            <h5>No Patients</h5>
                            <p class="text-muted">No patients have been registered yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="patients-list">
                            <?php foreach ($recent_patients as $patient): ?>
                                <div class="card patient-card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="pet-avatar me-3">
                                                    <i class="fas fa-<?php echo strtolower($patient['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($patient['name']); ?></h6>
                                                    <small class="text-muted">
                                                        Owner: <?php echo htmlspecialchars($patient['owner_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($patient['species']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <small class="text-muted">Breed & Age</small>
                                                <div class="fw-semibold">
                                                    <?php echo htmlspecialchars($patient['breed']); ?> • 
                                                    <?php echo htmlspecialchars($patient['age']); ?> years
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Last Visit</small>
                                                <div class="fw-semibold">
                                                    <?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Quick Actions -->
                                        <div class="action-buttons">
                                            <button class="btn btn-outline-success btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#medicalRecordModal"
                                                    onclick="loadPetData(<?php echo $patient['pet_id']; ?>)">
                                                <i class="fas fa-file-medical me-1"></i> Medical Record
                                            </button>
                                            <a href="vet_patients.php?pet_id=<?php echo $patient['pet_id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-history me-1"></i> History
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Vaccine Reminders & Quick Actions -->
            <div class="col-lg-6 mb-4">
                <!-- Vaccine Reminders -->
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-syringe me-2 text-warning"></i>Vaccine Reminders</h4>
                        <span class="badge bg-warning"><?php echo $reminder_count; ?> Due</span>
                    </div>
                    
                    <?php if (empty($vaccination_reminders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-syringe text-success"></i>
                            <h5>No Upcoming Vaccinations</h5>
                            <p class="text-muted">All vaccinations are up to date.</p>
                        </div>
                    <?php else: ?>
                        <div class="reminders-list">
                            <?php foreach ($vaccination_reminders as $reminder): ?>
                                <div class="card reminder-card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="pet-avatar me-3">
                                                    <i class="fas fa-<?php echo strtolower($reminder['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($reminder['pet_name']); ?></h6>
                                                    <small class="text-muted">
                                                        Owner: <?php echo htmlspecialchars($reminder['owner_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="vaccination-dates mt-2">
                                            <?php 
                                            $today = new DateTime();
                                            $rabies_due = $reminder['rabies_vaccine_date'] ? new DateTime($reminder['rabies_vaccine_date']) : null;
                                            $dhpp_due = $reminder['dhpp_vaccine_date'] ? new DateTime($reminder['dhpp_vaccine_date']) : null;
                                            $next_visit = $reminder['next_vet_visit'] ? new DateTime($reminder['next_vet_visit']) : null;
                                            
                                            if ($rabies_due && $rabies_due <= $today): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">Rabies Vaccine:</small>
                                                    <span class="vaccine-due">OVERDUE (<?php echo $rabies_due->format('M j, Y'); ?>)</span>
                                                </div>
                                            <?php elseif ($rabies_due && $rabies_due <= (clone $today)->modify('+30 days')): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">Rabies Vaccine:</small>
                                                    <span class="vaccine-upcoming">Due <?php echo $rabies_due->format('M j, Y'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($dhpp_due && $dhpp_due <= $today): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">DHPP Vaccine:</small>
                                                    <span class="vaccine-due">OVERDUE (<?php echo $dhpp_due->format('M j, Y'); ?>)</span>
                                                </div>
                                            <?php elseif ($dhpp_due && $dhpp_due <= (clone $today)->modify('+30 days')): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">DHPP Vaccine:</small>
                                                    <span class="vaccine-upcoming">Due <?php echo $dhpp_due->format('M j, Y'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($next_visit && $next_visit <= (clone $today)->modify('+30 days')): ?>
                                                <div class="mb-1">
                                                    <small class="text-muted">Next Visit:</small>
                                                    <span class="vaccine-upcoming"><?php echo $next_visit->format('M j, Y'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Quick Actions -->
                                        <div class="action-buttons">
                                            <button class="btn btn-outline-warning btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#vaccineModal"
                                                    onclick="setVaccinePet(<?php echo $reminder['pet_id']; ?>)">
                                                <i class="fas fa-syringe me-1"></i> Update Vaccine
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#medicalRecordModal"
                                                    onclick="loadPetData(<?php echo $reminder['pet_id']; ?>)">
                                                <i class="fas fa-file-medical me-1"></i> Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions & Today's Schedule -->
                <div class="card-custom mt-4">
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
                        <button class="btn btn-outline-warning text-start" data-bs-toggle="modal" data-bs-target="#medicalRecordModal">
                            <i class="fas fa-plus me-2"></i>Add Medical Record
                        </button>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="card-custom mt-4">
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
            </div>
        </div>
    </div>
</div>

<!-- Medical Record Modal -->
<div class="modal fade" id="medicalRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_dashboard.php">
                <input type="hidden" name="update_medical_record" value="1">
                <input type="hidden" name="pet_id" id="medicalRecordPetId">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pet</label>
                            <select class="form-select" name="pet_id" id="petSelect" required onchange="loadPetData(this.value)">
                                <option value="">Choose a pet...</option>
                                <?php foreach ($recent_patients as $patient): ?>
                                    <option value="<?php echo $patient['pet_id']; ?>">
                                        <?php echo htmlspecialchars($patient['name']); ?> 
                                        (<?php echo htmlspecialchars($patient['species']); ?> - <?php echo htmlspecialchars($patient['owner_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="weight" id="petWeight" placeholder="e.g., 5.2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Next Visit</label>
                            <input type="date" class="form-control" name="next_vet_visit" id="nextVetVisit">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Rabies Vaccine Date</label>
                            <input type="date" class="form-control" name="rabies_vaccine_date" id="rabiesVaccineDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DHPP Vaccine Date</label>
                            <input type="date" class="form-control" name="dhpp_vaccine_date" id="dhppVaccineDate">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_spayed_neutered" id="isSpayedNeutered">
                                <label class="form-check-label" for="isSpayedNeutered">
                                    Spayed/Neutered
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Spay/Neuter Date</label>
                            <input type="date" class="form-control" name="spay_neuter_date" id="spayNeuterDate">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Medical Notes</label>
                        <textarea class="form-control" name="medical_notes" rows="3" 
                                  placeholder="Current medical observations, treatment notes..." id="medicalNotes"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Previous Conditions</label>
                        <textarea class="form-control" name="previous_conditions" rows="2" 
                                  placeholder="Known medical conditions, allergies..." id="previousConditions"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vaccination History</label>
                        <textarea class="form-control" name="vaccination_history" rows="2" 
                                  placeholder="Vaccination records and history..." id="vaccinationHistory"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Surgical History</label>
                        <textarea class="form-control" name="surgical_history" rows="2" 
                                  placeholder="Previous surgeries and procedures..." id="surgicalHistory"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Medication History</label>
                        <textarea class="form-control" name="medication_history" rows="2" 
                                  placeholder="Current and previous medications..." id="medicationHistory"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Medical Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vaccine Update Modal -->
<div class="modal fade" id="vaccineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Vaccination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_dashboard.php">
                <input type="hidden" name="update_vaccination" value="1">
                <input type="hidden" name="pet_id" id="vaccinePetId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vaccine Type</label>
                        <select class="form-select" name="vaccine_type" required>
                            <option value="">Select vaccine type...</option>
                            <option value="rabies">Rabies Vaccine</option>
                            <option value="dhpp">DHPP Vaccine</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vaccination Date</label>
                        <input type="date" class="form-control" name="vaccine_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will update the vaccination record and set reminders for the next dose.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Vaccine</button>
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
    }

    // Load pet data for medical record modal
    function loadPetData(petId) {
        document.getElementById('medicalRecordPetId').value = petId;
        document.getElementById('petSelect').value = petId;
        
        // In a real implementation, you would fetch pet data via AJAX
        // For now, we'll just set the form values to empty
        document.getElementById('petWeight').value = '';
        document.getElementById('nextVetVisit').value = '';
        document.getElementById('rabiesVaccineDate').value = '';
        document.getElementById('dhppVaccineDate').value = '';
        document.getElementById('isSpayedNeutered').checked = false;
        document.getElementById('spayNeuterDate').value = '';
        document.getElementById('medicalNotes').value = '';
        document.getElementById('previousConditions').value = '';
        document.getElementById('vaccinationHistory').value = '';
        document.getElementById('surgicalHistory').value = '';
        document.getElementById('medicationHistory').value = '';
        
        console.log('Loading data for pet ID:', petId);
    }

    // Set pet ID for vaccine modal
    function setVaccinePet(petId) {
        document.getElementById('vaccinePetId').value = petId;
    }
</script>
</body>
</html>
