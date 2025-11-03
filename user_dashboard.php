<?php
session_start();
include("conn.php");

// ✅ 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ 2. Fetch logged-in user info with profile picture
try {
    $stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Set default profile picture if none exists
    $profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($user['name']);
    
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
        $notifications_stmt->bind_param("i", $user_id);
        $notifications_stmt->execute();
        $notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// ✅ 6. Handle actions
// Handle mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $mark_stmt->bind_param("ii", $notification_id, $user_id);
    $mark_stmt->execute();
    header("Location: user_dashboard.php");
    exit();
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    $mark_all_stmt->bind_param("i", $user_id);
    if ($mark_all_stmt->execute()) {
        $_SESSION['success'] = "All notifications marked as read!";
        header("Location: user_dashboard.php");
        exit();
    }
}

// Handle cancel appointment
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancel_reason = $_POST['cancel_reason'] ?? 'Cancelled by user';
    
    $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', vet_notes = ?, updated_at = NOW() WHERE appointment_id = ? AND user_id = ?");
    $cancel_stmt->bind_param("sii", $cancel_reason, $appointment_id, $user_id);
    
    if ($cancel_stmt->execute()) {
        $_SESSION['success'] = "Appointment cancelled successfully!";
        header("Location: user_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error cancelling appointment.";
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
        
        .api-status {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-connected { background: #d1fae5; color: #065f46; }
        .status-disconnected { background: #fee2e2; color: #991b1b; }
        .status-demo { background: #fef3c7; color: #92400e; }
        
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
                <div class="d-flex align-items-center gap-2">
                    <span id="apiStatus" class="api-status status-connected">Roboflow Connected</span>
                    <small class="text-muted">Powered by Roboflow AI</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="upload-area">
                        <h5><i class="fa-solid fa-camera me-2"></i>Upload Pet Image</h5>
                        <p class="text-muted mb-3">Get instant AI analysis for common pet diseases</p>
                        
                        <input type="file" id="petImageInput" accept="image/*" class="form-control mb-3">
                        <button onclick="analyzeWithRoboflow()" class="btn btn-primary w-100" id="analyzeBtn">
                            <i class="fa-solid fa-magnifying-glass me-2"></i> Analyze with Roboflow
                        </button>
                        
                        <small class="text-muted">
                            Supports: JPG, PNG, JPEG • Max 10MB<br>
                            <i class="fa-solid fa-lightbulb me-1"></i> Best results with clear, well-lit images
                        </small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="class-list">
                        <h6><i class="fa-solid fa-list me-2"></i>Detectable Conditions</h6>
                        <div id="diseaseClassesList">
                            <span class="disease-tag">Skin Conditions</span>
                            <span class="disease-tag">Ear Infections</span>
                            <span class="disease-tag">Eye Problems</span>
                            <span class="disease-tag">Dental Issues</span>
                            <span class="disease-tag">Coat Problems</span>
                            <span class="disease-tag">Wounds</span>
                            <span class="disease-tag">Tumors</span>
                            <span class="disease-tag">Parasites</span>
                            <span class="disease-tag">Allergies</span>
                            <span class="disease-tag">Infections</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="analysisResult" class="mt-4"></div>
        </div>

        <!-- Recent Appointments Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fa-solid fa-calendar me-2"></i>Recent Appointments</h4>
                <div>
                    <a href="user_appointment.php" class="btn btn-primary me-2">
                        <i class="fa-solid fa-plus me-1"></i> New Appointment
                    </a>
                    <a href="user_appointments.php" class="btn btn-outline-primary">
                        <i class="fa-solid fa-list me-1"></i> View All
                    </a>
                </div>
            </div>
            
            <?php if (empty($user_appointments)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-plus fa-2x mb-3"></i>
                    <h5>No Appointments Yet</h5>
                    <p class="text-muted">You haven't booked any appointments yet.</p>
                    <a href="user_appointment.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Book Your First Appointment
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($user_appointments as $appt): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card appointment-card appointment-<?php echo $appt['status']; ?> h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($appt['pet_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($appt['service_type']); ?></small>
                                        </div>
                                        <span class="badge badge-<?php echo $appt['status']; ?>">
                                            <?php echo ucfirst($appt['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Date & Time</small>
                                        <div class="fw-semibold">
                                            <?php echo date('M j, Y', strtotime($appt['appointment_date'])); ?><br>
                                            <small><?php echo date('g:i A', strtotime($appt['appointment_time'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($appt['reason'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Reason</small>
                                            <div class="small"><?php echo htmlspecialchars($appt['reason']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appt['vet_notes'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Vet Notes</small>
                                            <div class="small text-success"><?php echo htmlspecialchars($appt['vet_notes']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <?php if ($appt['status'] == 'pending'): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>Waiting for vet confirmation
                                                </small>
                                                <form method="POST" action="user_dashboard.php" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <input type="hidden" name="cancel_reason" value="Cancelled by user">
                                                    <button type="submit" name="cancel_appointment" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($appt['status'] == 'confirmed'): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>Confirmed by Veterinarian
                                                </small>
                                                <form method="POST" action="user_dashboard.php" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <input type="hidden" name="cancel_reason" value="Cancelled by user">
                                                    <button type="submit" name="cancel_appointment" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($appt['status'] == 'completed'): ?>
                                            <small class="text-success">
                                                <i class="fas fa-flag-checkered me-1"></i>Appointment Completed
                                            </small>
                                        <?php elseif ($appt['status'] == 'cancelled'): ?>
                                            <small class="text-danger">
                                                <i class="fas fa-ban me-1"></i>Appointment Cancelled
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Created: <?php echo date('M j, g:i A', strtotime($appt['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($appointment_stats['total'] > 6): ?>
                    <div class="text-center mt-4">
                        <a href="user_appointments.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i> View All <?php echo $appointment_stats['total']; ?> Appointments
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Pets Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-paw me-2"></i>Your Pets</h4>
                <a href="register_pet.php" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-plus me-1"></i> Add Pet
                </a>
            </div>
            
            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-paw"></i>
                    <h5>No Pets Registered</h5>
                    <p class="text-muted">You haven't added any pets yet. Register your first pet to get started!</p>
                    <a href="register_pet.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add Your First Pet
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pets as $pet): ?>
                        <?php
                        $hasVaccination = false;
                        $hasRecentVisit = false;
                        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                        
                        foreach ($pet['records'] as $record) {
                            if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
                                $hasVaccination = true;
                            }
                            if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
                                $hasRecentVisit = true;
                            }
                        }
                        
                        // Determine health status
                        $healthStatus = $hasVaccination ? 'Good Health' : 'Needs Vaccination';
                        $statusClass = $hasVaccination ? 'status-good' : 'status-warning';
                        if (!$hasVaccination && !$hasRecentVisit) {
                            $healthStatus = 'Needs Checkup';
                            $statusClass = 'status-bad';
                        }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="pet-card">
                                <div class="pet-card-header" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#e0f2fe' : '#f0f9ff'; ?>">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?></small>
                                    </div>
                                    <div class="pet-species-icon" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#bae6fd' : '#e0f2fe'; ?>">
                                        <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <div>
                                            <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years<br>
                                            <strong>Gender:</strong> <?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?><br>
                                            <strong>Registered:</strong> <?php echo date('M j, Y', strtotime($pet['date_registered'])); ?>
                                        </div>
                                        <div class="text-end">
                                            <div class="health-status">
                                                <span class="status-dot <?php echo $statusClass; ?>"></span>
                                                <small><?php echo $healthStatus; ?></small>
                                            </div>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($pet['pet_id']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($pet['records'])): ?>
                                        <div class="mt-3">
                                            <h6>Recent Medical History</h6>
                                            <div class="medical-history">
                                                <?php 
                                                $recentRecords = array_slice($pet['records'], 0, 3);
                                                foreach ($recentRecords as $record): 
                                                    if (!empty($record['service_date']) && $record['service_date'] !== '0000-00-00'):
                                                ?>
                                                    <div class="d-flex justify-content-between border-bottom py-1">
                                                        <small><?php echo htmlspecialchars($record['service_type'] ?? 'Checkup'); ?></small>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($record['service_date'])); ?></small>
                                                    </div>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0 mt-3">
                                            <i class="fas fa-info-circle me-1"></i> No medical records found.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="user_pet_profile.php?id=<?php echo $pet['pet_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card-custom text-center">
            <h5><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h5>
            <p class="text-muted">Manage your pets and appointments quickly</p>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="user_appointment.php" class="btn btn-primary w-100">
                        <i class="fas fa-calendar-plus me-1"></i> Book Appointment
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="register_pet.php" class="btn btn-success w-100">
                        <i class="fas fa-plus-circle me-1"></i> Add Pet
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="user_appointments.php" class="btn btn-info w-100">
                        <i class="fas fa-list me-1"></i> View Appointments
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="qr_code.php" class="btn btn-warning w-100">
                        <i class="fas fa-qrcode me-1"></i> QR Codes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // CORRECT Roboflow Configuration from your dashboard
    const ROBOFLOW_CONFIG = {
        apiKey: 'HKh4FfDexdGHtMtqv6Zc',
        workspace: 'vetcarepredictionapi',
        workflow_id: 'custom-workflow-2',
        api_url: 'https://serverless.roboflow.com'
    };

    // CORRECT API endpoint for workflows
    const ROBOFLOW_WORKFLOW_API = `${ROBOFLOW_CONFIG.api_url}/${ROBOFLOW_CONFIG.workspace}/${ROBOFLOW_CONFIG.workflow_id}`;

    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        initializeHealthCharts();
        
        console.log('Roboflow workflow integration ready!');
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    function initializeHealthCharts() {
        // ... keep your existing chart code ...
        // Vaccination Status Chart
        const vaccinationCtx = document.getElementById('vaccinationChart').getContext('2d');
        const vaccinationChart = new Chart(vaccinationCtx, {
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

        // ... keep other charts the same ...
    }

    // CORRECT Roboflow analysis function
    async function analyzeWithRoboflow() {
        const fileInput = document.getElementById('petImageInput');
        const resultDiv = document.getElementById('analysisResult');
        const analyzeBtn = document.getElementById('analyzeBtn');
        
        if (!fileInput.files[0]) {
            showAlert('Please select an image file first!', 'warning');
            return;
        }
        
        // Show loading state
        analyzeBtn.disabled = true;
        analyzeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Analyzing with Roboflow...';
        
        resultDiv.innerHTML = `
            <div class="alert alert-info alert-custom">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" role="status"></div>
                    <div>
                        <strong>Analyzing with Roboflow Workflow...</strong><br>
                        <small>Using your custom workflow: ${ROBOFLOW_CONFIG.workflow_id}</small>
                    </div>
                </div>
            </div>
        `;
        
        try {
            // Convert image to base64 for the workflow API
            const base64Image = await convertToBase64(fileInput.files[0]);
            
            // Prepare the request body exactly as Roboflow expects
            const requestBody = {
                image: {
                    type: "base64",
                    value: base64Image
                }
            };
            
            const response = await fetch(ROBOFLOW_WORKFLOW_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${ROBOFLOW_CONFIG.apiKey}`
                },
                body: JSON.stringify(requestBody)
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`Roboflow API error: ${response.status} - ${errorData.error || 'Unknown error'}`);
            }
            
            const data = await response.json();
            displayRoboflowResults(data, fileInput.files[0].name);
            
        } catch (error) {
            console.error('Roboflow analysis error:', error);
            
            let errorMessage = error.message || 'Unable to connect to Roboflow API.';
            
            if (error.message.includes('401')) {
                errorMessage = 'API authentication failed. Please check your API key.';
            } else if (error.message.includes('404')) {
                errorMessage = 'Workflow not found. Please check your workflow ID.';
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Analysis Failed</strong><br>
                    <small>${errorMessage}</small>
                    <div class="mt-2">
                        <small class="text-muted">
                            API: ${ROBOFLOW_WORKFLOW_API}<br>
                            Workflow: ${ROBOFLOW_CONFIG.workflow_id}<br>
                            Workspace: ${ROBOFLOW_CONFIG.workspace}
                        </small>
                    </div>
                </div>
            `;
        } finally {
            // Reset button
            analyzeBtn.disabled = false;
            analyzeBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> Analyze with Roboflow';
        }
    }

    // Helper function to convert file to base64
    function convertToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => {
                // Remove the data:image/...;base64, prefix
                const base64 = reader.result.split(',')[1];
                resolve(base64);
            };
            reader.onerror = error => reject(error);
        });
    }

    // Test connection function
    async function testRoboflowConnection() {
        const resultDiv = document.getElementById('analysisResult');
        
        resultDiv.innerHTML = `
            <div class="alert alert-info alert-custom">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" role="status"></div>
                    <div>
                        <strong>Testing Roboflow Workflow Connection...</strong><br>
                        <small>Checking workflow: ${ROBOFLOW_CONFIG.workflow_id}</small>
                    </div>
                </div>
            </div>
        `;
        
        try {
            // Simple GET request to check if workflow exists
            const response = await fetch(`${ROBOFLOW_WORKFLOW_API}/info`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${ROBOFLOW_CONFIG.apiKey}`
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                resultDiv.innerHTML = `
                    <div class="alert alert-success alert-custom">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Workflow Connection Successful!</strong><br>
                        <small>Your Roboflow workflow is ready to use.</small>
                        <div class="mt-2">
                            <small class="text-muted">
                                Workspace: ${ROBOFLOW_CONFIG.workspace}<br>
                                Workflow: ${ROBOFLOW_CONFIG.workflow_id}<br>
                                API: ${ROBOFLOW_CONFIG.api_url}
                            </small>
                        </div>
                    </div>
                `;
            } else {
                throw new Error(`Workflow check failed: ${response.status}`);
            }
            
        } catch (error) {
            console.error('Connection test error:', error);
            resultDiv.innerHTML = `
                <div class="alert alert-warning alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Workflow Connection Test</strong><br>
                    <small>${error.message}</small>
                    <div class="mt-2">
                        <small class="text-muted">
                            This doesn't mean your workflow won't work.<br>
                            Try analyzing an image to test the actual functionality.
                        </small>
                    </div>
                </div>
            `;
        }
    }

    function displayRoboflowResults(data, fileName) {
        const resultDiv = document.getElementById('analysisResult');
        
        // Handle workflow response - adjust based on your actual workflow output
        const results = data.results || data.predictions || [data];
        const topResult = results[0] || { class: 'Healthy', confidence: 0 };
        
        let html = `
            <div class="alert alert-success alert-custom">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5><i class="fas fa-check-circle me-2"></i>Workflow Analysis Complete!</h5>
                        <p class="mb-1"><strong>File:</strong> ${fileName}</p>
                        <p class="mb-0"><strong>Workflow:</strong> ${ROBOFLOW_CONFIG.workflow_id}</p>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="clearAnalysis()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom" style="background: linear-gradient(135deg, #d4edda, #c3e6cb);">
                        <h6><i class="fas fa-stethoscope me-2"></i>Primary Result</h6>
                        <div class="text-center py-3">
                            <h3 class="text-success mb-2">${topResult.class || 'Unknown'}</h3>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: ${((topResult.confidence || topResult.score || 0) * 100).toFixed(1)}%" 
                                     aria-valuenow="${((topResult.confidence || topResult.score || 0) * 100).toFixed(1)}" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    ${((topResult.confidence || topResult.score || 0) * 100).toFixed(1)}%
                                </div>
                            </div>
                            <small class="text-muted mt-2">Confidence Level</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-code me-2"></i>Raw Response</h6>
                        <div style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 11px; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                            ${JSON.stringify(data, null, 2)}
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-custom mt-3">
                <h6><i class="fas fa-lightbulb me-2"></i>Recommended Actions</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Consult a Veterinarian</strong><br>
                            <small>AI analysis is for informational purposes only.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="fas fa-calendar-plus me-2"></i>
                            <strong>Book Appointment</strong><br>
                            <small>Schedule a professional diagnosis.</small>
                            <div class="mt-2">
                                <a href="user_appointment.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-calendar-plus me-1"></i> Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        resultDiv.innerHTML = html;
    }

    function clearAnalysis() {
        document.getElementById('petImageInput').value = '';
        document.getElementById('analysisResult').innerHTML = '';
    }

    function showAlert(message, type = 'info') {
        const alertClass = {
            'success': 'alert-success',
            'warning': 'alert-warning', 
            'danger': 'alert-danger',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-custom alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.getElementById('analysisResult').innerHTML = '';
        document.getElementById('analysisResult').appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, 5000);
    }

    // Search functionality
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.input-group input');
            if (searchInput) searchInput.focus();
        }
    });
</script>
</body>
</html>



