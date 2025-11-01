

<?php
session_start();
include("conn.php");

// ✅ 1. Check if user is logged in AND has correct role (owner)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    // Store role for redirect before destroying session
    $user_role = $_SESSION['role'] ?? '';
    
    // Clear session
    session_unset();
    session_destroy();
    
    // Redirect based on role
    switch ($user_role) {
        case 'admin':
            header("Location: login_admin.php");
            break;
        case 'vet':
            header("Location: login_vet.php");
            break;
        case 'lgu':
            header("Location: login_lgu.php");
            break;
        default:
            header("Location: login.php");
            break;
    }
    exit();
}

// Validate session and prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
    $_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
} else if (time() - $_SESSION['created'] > 1800) {
    // session started more than 30 minutes ago
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Validate session security
if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR'] || $_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=session_invalid");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ 2. Fetch logged-in user info with profile picture
try {
    $stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Set default profile picture if none exists
    $profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($user['name']);
    
    $stmt->close();
    
} catch (Exception $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// ✅ 3. Fetch user's pets & medical records
$pets = [];
$totalPets = 0;
$vaccinatedPets = 0;
$upcomingReminders = 0;
$recentVisits = 0;

// Health monitoring data for charts
$healthData = [
    'vaccination_status' => [],
    'visit_frequency' => [],
    'weight_trends' => [],
    'health_scores' => []
];

try {
    $query = "
    SELECT 
        p.pet_id,
        p.name AS pet_name,
        p.species,
        p.breed,
        p.age,
        p.color,
        p.weight,
        p.birth_date,
        p.gender,
        p.medical_notes,
        p.vet_contact,
        p.date_registered,
        p.qr_code,
        p.qr_code_data,
        m.record_id,
        m.service_date,
        m.service_type,
        m.service_description,
        m.veterinarian,
        m.reminder_due_date,
        m.reminder_description
    FROM pets p
    LEFT JOIN pet_medical_records m ON p.pet_id = m.pet_id
    WHERE p.user_id = ?
    ORDER BY p.pet_id, m.service_date DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Process pets data
    $currentPetId = null;
    $petRecords = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['pet_id'])) continue;
        
        if ($currentPetId !== $row['pet_id']) {
            if ($currentPetId !== null) {
                $pets[$currentPetId]['records'] = $petRecords;
            }
            $currentPetId = $row['pet_id'];
            $petRecords = [];
            
            $pets[$currentPetId] = [
                'pet_id' => $row['pet_id'],
                'pet_name' => $row['pet_name'],
                'species' => $row['species'] ?? 'Unknown',
                'breed' => $row['breed'] ?? 'Unknown',
                'age' => $row['age'] ?? '0',
                'color' => $row['color'] ?? '',
                'weight' => $row['weight'] ?? null,
                'birth_date' => $row['birth_date'] ?? null,
                'gender' => $row['gender'] ?? '',
                'medical_notes' => $row['medical_notes'] ?? '',
                'vet_contact' => $row['vet_contact'] ?? '',
                'date_registered' => $row['date_registered'] ?? date('Y-m-d'),
                'qr_code' => $row['qr_code'] ?? '',
                'qr_code_data' => $row['qr_code_data'] ?? ''
            ];
        }
        
        // Only add medical records if they exist
        if (!empty($row['record_id'])) {
            $serviceDate = ($row['service_date'] !== '0000-00-00' && !empty($row['service_date'])) ? $row['service_date'] : null;
            
            if ($serviceDate || !empty($row['service_type']) || !empty($row['reminder_description'])) {
                $petRecords[] = [
                    'service_date' => $serviceDate,
                    'service_type' => $row['service_type'] ?? null,
                    'service_description' => $row['service_description'] ?? null,
                    'veterinarian' => $row['veterinarian'] ?? null,
                    'reminder_due_date' => $row['reminder_due_date'] ?? null,
                    'reminder_description' => $row['reminder_description'] ?? null
                ];
            }
        }
    }

    if ($currentPetId !== null) {
        $pets[$currentPetId]['records'] = $petRecords;
    }
    
    $totalPets = count($pets);
    
    // Calculate statistics and health data
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    
    foreach ($pets as $pet) {
        $petVaccinated = false;
        $petVisits = 0;
        $petWeight = $pet['weight'] ? floatval($pet['weight']) : null;
        
        foreach ($pet['records'] as $record) {
            // Check for vaccinations
            if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
                $petVaccinated = true;
            }
            
            // Check for upcoming reminders
            if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
                $upcomingReminders++;
            }
            
            // Check for recent visits
            if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
                $recentVisits++;
                $petVisits++;
            }
        }
        
        if ($petVaccinated) {
            $vaccinatedPets++;
        }
        
        // Build health data for charts
        $healthData['vaccination_status'][$pet['pet_name']] = $petVaccinated ? 'Vaccinated' : 'Not Vaccinated';
        $healthData['visit_frequency'][$pet['pet_name']] = $petVisits;
        
        if ($petWeight) {
            $healthData['weight_trends'][$pet['pet_name']] = $petWeight;
        }
        
        // Calculate health score (simplified)
        $healthScore = 70; // Base score
        if ($petVaccinated) $healthScore += 20;
        if ($petVisits > 0) $healthScore += 10;
        $healthData['health_scores'][$pet['pet_name']] = min($healthScore, 100);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error fetching pets: " . $e->getMessage());
}

// ✅ 4. Fetch user's appointments
$user_appointments = [];
$appointment_stats = [
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'total' => 0
];

try {
    $appointments_stmt = $conn->prepare("
        SELECT a.*, p.name as pet_name, p.species
        FROM appointments a 
        LEFT JOIN pets p ON a.pet_id = p.pet_id 
        WHERE a.user_id = ? 
        ORDER BY 
            CASE 
                WHEN a.status = 'pending' THEN 1
                WHEN a.status = 'confirmed' THEN 2
                WHEN a.status = 'completed' THEN 3
                ELSE 4
            END,
            a.appointment_date ASC,
            a.appointment_time ASC
        LIMIT 10
    ");
    
    if ($appointments_stmt) {
        $appointments_stmt->bind_param("i", $user_id);
        $appointments_stmt->execute();
        $user_appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Count appointments by status
        foreach ($user_appointments as $appt) {
            if (isset($appointment_stats[$appt['status']])) {
                $appointment_stats[$appt['status']]++;
            }
        }
        $appointment_stats['total'] = count($user_appointments);
        $appointments_stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// ✅ 5. Fetch user notifications
$notifications = [];
try {
    // Check if user_notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if ($table_check && $table_check->num_rows > 0) {
        $notifications_stmt = $conn->prepare("
            SELECT * FROM user_notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        if ($notifications_stmt) {
            $notifications_stmt->bind_param("i", $user_id);
            $notifications_stmt->execute();
            $notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $notifications_stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// ✅ 6. Handle actions
// Handle mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    if ($mark_stmt) {
        $mark_stmt->bind_param("ii", $notification_id, $user_id);
        $mark_stmt->execute();
        $mark_stmt->close();
    }
    header("Location: user_dashboard.php");
    exit();
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    if ($mark_all_stmt) {
        $mark_all_stmt->bind_param("i", $user_id);
        if ($mark_all_stmt->execute()) {
            $_SESSION['success'] = "All notifications marked as read!";
        }
        $mark_all_stmt->close();
    }
    header("Location: user_dashboard.php");
    exit();
}

// Handle cancel appointment
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'] ?? '';
    $cancel_reason = $_POST['cancel_reason'] ?? 'Cancelled by user';
    
    if (!empty($appointment_id)) {
        $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', vet_notes = ?, updated_at = NOW() WHERE appointment_id = ? AND user_id = ?");
        if ($cancel_stmt) {
            $cancel_stmt->bind_param("sii", $cancel_reason, $appointment_id, $user_id);
            
            if ($cancel_stmt->execute()) {
                $_SESSION['success'] = "Appointment cancelled successfully!";
            } else {
                $_SESSION['error'] = "Error cancelling appointment.";
            }
            $cancel_stmt->close();
        }
        
        header("Location: user_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCareQR - User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #e0f2fe 100%);
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
            background: var(--light);
            color: var(--primary-dark);
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

        .sidebar .appointment-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 1rem 0;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .sidebar .appointment-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            transform: translateY(-2px);
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
        
        /* Appointment Status Styles */
        .badge-pending { background: linear-gradient(135deg, var(--warning), #e67e22); }
        .badge-confirmed { background: linear-gradient(135deg, var(--success), #27ae60); }
        .badge-completed { background: linear-gradient(135deg, var(--primary), #2980b9); }
        .badge-cancelled { background: linear-gradient(135deg, var(--danger), #c0392b); }
        
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .appointment-pending { border-left-color: var(--warning); background: #fef9e7; }
        .appointment-confirmed { border-left-color: var(--success); background: #eafaf1; }
        .appointment-completed { border-left-color: var(--primary); background: #ebf5fb; }
        .appointment-cancelled { border-left-color: var(--danger); background: #fdedec; }
        
        .pet-card {
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
            border: none;
            box-shadow: var(--shadow);
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
        }
        
        .pet-species-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .notification-item.unread {
            background: var(--light);
            border-left-color: var(--primary-dark);
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        .health-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-good { background-color: var(--success); }
        .status-warning { background-color: var(--warning); }
        .status-bad { background-color: var(--danger); }
        
        /* Chart Styles */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .health-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .metric-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* AI Analysis Styles */
        .upload-area {
            border: 3px dashed var(--primary);
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            background: var(--light);
            transition: all 0.3s;
            height: 100%;
        }
        
        .upload-area:hover {
            border-color: var(--primary-dark);
            background: #e0f2fe;
        }
        
        .class-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            height: 100%;
        }
        
        .disease-tag {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 4px;
            display: inline-block;
            font-size: 0.8rem;
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
            
            .health-metrics {
                grid-template-columns: 1fr;
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
                     alt="User" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>'">
            </div>
            <h6 id="ownerNameSidebar"><?php echo htmlspecialchars($user['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
        </div>

        <!-- Appointment Button -->
        <a href="user_appointment.php" class="appointment-btn">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </a>

        <a href="user_dashboard.php" class="active">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="user_pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> My Pets
        </a>
        <a href="user_appointment.php">
            <div class="icon"><i class="fa-solid fa-calendar-days"></i></div> My Appointments
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> QR Codes
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> Register Pet
        </a>
        <a href="user_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Welcome Back, <span id="ownerName"><?php echo htmlspecialchars($user['name']); ?></span>! 👋</h5>
                <small class="text-muted">Here's your pet health overview</small>
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
                                <a href="user_dashboard.php?mark_all_read=1" class="btn btn-sm btn-outline-primary">Mark All Read</a>
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
                                            <a href="user_dashboard.php?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="input-group" style="width:300px">
                    <input type="text" placeholder="Search pet, appointment, vet..." class="form-control">
                    <button class="btn btn-outline-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i></button>
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
        <div class="row stats-row mb-4">
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fa-solid fa-paw"></i>
                    <h6>Registered Pets</h6>
                    <h4><?php echo $totalPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success), #27ae60);">
                    <i class="fa-solid fa-syringe"></i>
                    <h6>Vaccinated Pets</h6>
                    <h4><?php echo $vaccinatedPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning), #e67e22);">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h6>Upcoming Reminders</h6>
                    <h4><?php echo $upcomingReminders; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <i class="fa-solid fa-stethoscope"></i>
                    <h6>Recent Visits</h6>
                    <h4><?php echo $recentVisits; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fa-solid fa-calendar-day"></i>
                    <h6>Total Appointments</h6>
                    <h4><?php echo $appointment_stats['total']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fa-solid fa-clock"></i>
                    <h6>Pending</h6>
                    <h4><?php echo $appointment_stats['pending']; ?></h4>
                </div>
            </div>
        </div>

        <!-- Health Monitoring Section -->
        <?php if (!empty($pets)): ?>
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fa-solid fa-heart-pulse me-2"></i>Health Monitoring</h4>
                <small class="text-muted">Real-time health insights</small>
            </div>
            
            <!-- Health Metrics -->
            <div class="health-metrics">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $vaccinatedPets; ?>/<?php echo $totalPets; ?></div>
                    <div class="metric-label">Vaccinated Pets</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $recentVisits; ?></div>
                    <div class="metric-label">Visits (30 days)</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $upcomingReminders; ?></div>
                    <div class="metric-label">Upcoming Reminders</div>
                </div>
                <div class="metric-card">
                    <?php
                    $avgHealthScore = !empty($healthData['health_scores']) ? 
                        round(array_sum($healthData['health_scores']) / count($healthData['health_scores'])) : 0;
                    ?>
                    <div class="metric-value"><?php echo $avgHealthScore; ?>%</div>
                    <div class="metric-label">Avg Health Score</div>
                </div>
            </div>

            <!-- Health Charts -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6>Vaccination Status</h6>
                        <div class="chart-container">
                            <canvas id="vaccinationChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6>Health Scores</h6>
                        <div class="chart-container">
                            <canvas id="healthScoreChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6>Visit Frequency (Last 30 Days)</h6>
                        <div class="chart-container">
                            <canvas id="visitChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6>Weight Distribution</h6>
                        <div class="chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pet Disease AI Analysis Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fa-solid fa-robot me-2"></i>AI Pet Disease Analysis</h4>
                <small class="text-muted">Powered by VetCare AI</small>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="upload-area">
                        <h5><i class="fa-solid

                    <i class="fa-solid fa-cloud-upload-alt fa-3x mb-3 text-primary"></i></h5>
                        <h5>Upload Pet Image for Analysis</h5>
                        <p class="text-muted mb-3">Upload a clear photo of your pet for AI-powered disease detection</p>
                        <form id="aiAnalysisForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <select class="form-select" id="petSelect" required>
                                    <option value="">Select Pet</option>
                                    <?php foreach($pets as $pet): ?>
                                        <option value="<?php echo $pet['pet_id']; ?>"><?php echo htmlspecialchars($pet['pet_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="file" class="form-control" id="petImage" accept="image/*" required>
                                <div class="form-text">Supported formats: JPG, PNG, JPEG (Max 5MB)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-microscope me-2"></i>Analyze Image
                            </button>
                        </form>
                        <div id="analysisResult" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <h6>Analysis Result:</h6>
                                <p id="resultText" class="mb-0"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="class-list">
                        <h5 class="mb-3">Detectable Conditions</h5>
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-primary">Dogs</h6>
                                <span class="disease-tag">Skin Allergies</span>
                                <span class="disease-tag">Ear Infections</span>
                                <span class="disease-tag">Hot Spots</span>
                                <span class="disease-tag">Mange</span>
                                <span class="disease-tag">Ringworm</span>
                                <span class="disease-tag">Arthritis</span>
                            </div>
                            <div class="col-6">
                                <h6 class="text-primary">Cats</h6>
                                <span class="disease-tag">Flea Allergy</span>
                                <span class="disease-tag">Respiratory Issues</span>
                                <span class="disease-tag">Dental Disease</span>
                                <span class="disease-tag">Urinary Problems</span>
                                <span class="disease-tag">Eye Infections</span>
                                <span class="disease-tag">Hairballs</span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h6 class="text-success">Healthy Signs</h6>
                            <ul class="list-unstyled small">
                                <li><i class="fa-solid fa-check text-success me-2"></i>Clear eyes and nose</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Clean ears and skin</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Normal appetite and energy</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Regular breathing</li>
                            </ul>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <small><i class="fa-solid fa-exclamation-triangle me-2"></i><strong>Note:</strong> AI analysis is for preliminary screening only. Always consult a veterinarian for accurate diagnosis.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Pets & Appointments -->
        <div class="row">
            <!-- Recent Pets -->
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fa-solid fa-paw me-2"></i>My Pets</h5>
                        <a href="user_pet_profile.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    
                    <?php if (empty($pets)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-dog"></i>
                            <h5>No Pets Registered</h5>
                            <p class="text-muted">You haven't registered any pets yet.</p>
                            <a href="register_pet.php" class="btn btn-primary">
                                <i class="fa-solid fa-plus me-2"></i>Register First Pet
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php 
                            $counter = 0;
                            foreach ($pets as $pet): 
                                if ($counter >= 4) break;
                                $counter++;
                            ?>
                                <div class="col-sm-6 mb-3">
                                    <div class="pet-card card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h6>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($pet['species']); ?></span>
                                            </div>
                                            <p class="card-text small text-muted mb-2">
                                                Breed: <?php echo htmlspecialchars($pet['breed']); ?><br>
                                                Age: <?php echo htmlspecialchars($pet['age']); ?> years
                                            </p>
                                            <div class="health-status">
                                                <?php
                                                $healthScore = $healthData['health_scores'][$pet['pet_name']] ?? 70;
                                                $statusClass = $healthScore >= 80 ? 'status-good' : ($healthScore >= 60 ? 'status-warning' : 'status-bad');
                                                $statusText = $healthScore >= 80 ? 'Good' : ($healthScore >= 60 ? 'Fair' : 'Needs Attention');
                                                ?>
                                                <span class="status-dot <?php echo $statusClass; ?>"></span>
                                                <small>Health: <?php echo $statusText; ?> (<?php echo $healthScore; ?>%)</small>
                                            </div>
                                            <div class="mt-3">
                                                <a href="pet_details.php?id=<?php echo $pet['pet_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fa-solid fa-calendar me-2"></i>Recent Appointments</h5>
                        <a href="user_appointment.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    
                    <?php if (empty($user_appointments)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <h5>No Appointments</h5>
                            <p class="text-muted">You haven't booked any appointments yet.</p>
                            <a href="user_appointment.php" class="btn btn-primary">
                                <i class="fa-solid fa-calendar-plus me-2"></i>Book Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="appointment-list">
                            <?php foreach ($user_appointments as $appointment): ?>
                                <div class="appointment-card p-3 rounded appointment-<?php echo $appointment['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($appointment['pet_name'] ?? 'Unknown Pet'); ?></h6>
                                            <p class="mb-1 small">
                                                <i class="fa-solid fa-calendar me-1"></i>
                                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                                at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                            <p class="mb-1 small">
                                                <i class="fa-solid fa-stethoscope me-1"></i>
                                                <?php echo htmlspecialchars($appointment['service_type'] ?? 'General Checkup'); ?>
                                            </p>
                                            <?php if (!empty($appointment['vet_notes'])): ?>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fa-solid fa-note-sticky me-1"></i>
                                                    <?php echo htmlspecialchars($appointment['vet_notes']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge badge-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                                <button class="btn btn-sm btn-outline-danger mt-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#cancelModal"
                                                        data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card-custom mt-4">
            <h5 class="mb-3"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h5>
            <div class="row text-center">
                <div class="col-md-2 col-4 mb-3">
                    <a href="register_pet.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="fa-solid fa-plus fa-2x mb-2"></i><br>
                        <small>Add Pet</small>
                    </a>
                </div>
                <div class="col-md-2 col-4 mb-3">
                    <a href="user_appointment.php" class="btn btn-outline-success w-100 py-3">
                        <i class="fa-solid fa-calendar-plus fa-2x mb-2"></i><br>
                        <small>Book Visit</small>
                    </a>
                </div>
                <div class="col-md-2 col-4 mb-3">
                    <a href="qr_code.php" class="btn btn-outline-info w-100 py-3">
                        <i class="fa-solid fa-qrcode fa-2x mb-2"></i><br>
                        <small>QR Codes</small>
                    </a>
                </div>
                <div class="col-md-2 col-4 mb-3">
                    <a href="user_pet_profile.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="fa-solid fa-paw fa-2x mb-2"></i><br>
                        <small>Pet Profiles</small>
                    </a>
                </div>
                <div class="col-md-2 col-4 mb-3">
                    <a href="user_settings.php" class="btn btn-outline-secondary w-100 py-3">
                        <i class="fa-solid fa-gear fa-2x mb-2"></i><br>
                        <small>Settings</small>
                    </a>
                </div>
                <div class="col-md-2 col-4 mb-3">
                    <a href="help.php" class="btn btn-outline-dark w-100 py-3">
                        <i class="fa-solid fa-circle-question fa-2x mb-2"></i><br>
                        <small>Help</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for cancellation:</label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" placeholder="Please provide a reason for cancellation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="cancel_appointment" class="btn btn-danger">Cancel Appointment</button>
                </div>
            </form>
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

    // Cancel Appointment Modal
    const cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const appointmentId = button.getAttribute('data-appointment-id');
            document.getElementById('cancelAppointmentId').value = appointmentId;
        });
    }

    // AI Analysis Form
    document.getElementById('aiAnalysisForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const resultDiv = document.getElementById('analysisResult');
        const resultText = document.getElementById('resultText');
        
        // Simulate AI analysis (in real implementation, this would call an API)
        resultText.textContent = "Analysis in progress... This feature would integrate with our AI model to analyze pet images for common diseases and health issues.";
        resultDiv.style.display = 'block';
        
        // Simulate API call delay
        setTimeout(() => {
            const sampleResults = [
                "Analysis complete: No obvious signs of disease detected. Your pet appears healthy!",
                "Potential skin irritation detected. Recommend consulting with your veterinarian.",
                "Ear infection suspected based on image analysis. Please schedule a vet visit.",
                "Healthy coat and eyes detected. Your pet shows no visible signs of illness."
            ];
            const randomResult = sampleResults[Math.floor(Math.random() * sampleResults.length)];
            resultText.textContent = randomResult;
        }, 2000);
    });

    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($pets)): ?>
            // Vaccination Status Chart
            const vaccinationCtx = document.getElementById('vaccinationChart')?.getContext('2d');
            if (vaccinationCtx) {
                new Chart(vaccinationCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Vaccinated', 'Not Vaccinated'],
                        datasets: [{
                            data: [<?php echo $vaccinatedPets; ?>, <?php echo $totalPets - $vaccinatedPets; ?>],
                            backgroundColor: ['#10b981', '#ef4444'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Health Score Chart
            const healthScoreCtx = document.getElementById('healthScoreChart')?.getContext('2d');
            if (healthScoreCtx && <?php echo !empty($healthData['health_scores']) ? 'true' : 'false'; ?>) {
                new Chart(healthScoreCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_keys($healthData['health_scores'])); ?>,
                        datasets: [{
                            label: 'Health Score %',
                            data: <?php echo json_encode(array_values($healthData['health_scores'])); ?>,
                            backgroundColor: function(context) {
                                const value = context.raw;
                                if (value >= 80) return '#10b981';
                                if (value >= 60) return '#f59e0b';
                                return '#ef4444';
                            },
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Health Score %'
                                }
                            }
                        }
                    }
                });
            }

            // Visit Frequency Chart
            const visitCtx = document.getElementById('visitChart')?.getContext('2d');
            if (visitCtx && <?php echo !empty($healthData['visit_frequency']) ? 'true' : 'false'; ?>) {
                new Chart(visitCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_keys($healthData['visit_frequency'])); ?>,
                        datasets: [{
                            label: 'Visits (Last 30 Days)',
                            data: <?php echo json_encode(array_values($healthData['visit_frequency'])); ?>,
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14, 165, 233, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Visits'
                                }
                            }
                        }
                    }
                });
            }

            // Weight Chart
            const weightCtx = document.getElementById('weightChart')?.getContext('2d');
            if (weightCtx && <?php echo !empty($healthData['weight_trends']) ? 'true' : 'false'; ?>) {
                new Chart(weightCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_keys($healthData['weight_trends'])); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($healthData['weight_trends'])); ?>,
                            backgroundColor: [
                                '#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', 
                                '#ef4444', '#6366f1', '#ec4899', '#06b6d4'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.raw + ' kg';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        <?php endif; ?>
    });

    // Auto-dismiss alerts after 5 seconds
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
