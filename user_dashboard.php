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
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Set default profile picture if none exists
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($user['name']);

// ✅ 3. Fetch user's pets & medical records
$query = "
SELECT 
    u.user_id,
    u.name AS owner_name,
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
    m.weight_date,
    m.weight AS record_weight,
    m.reminder_description,
    m.reminder_due_date,
    m.service_date,
    m.service_type,
    m.service_description,
    m.veterinarian,
    m.notes
FROM users u
LEFT JOIN pets p ON u.user_id = p.user_id
LEFT JOIN pet_medical_records m ON p.pet_id = m.pet_id
WHERE u.user_id = ?
ORDER BY p.pet_id, m.service_date DESC;
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("❌ SQL ERROR: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// ✅ 4. Process data for frontend display
$pets = [];
$currentPetId = null;
$petRecords = [];

while ($row = $result->fetch_assoc()) {
    // Skip if no pet exists for this user
    if (empty($row['pet_id'])) {
        continue;
    }
    
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
        $weightDate = ($row['weight_date'] !== '0000-00-00' && !empty($row['weight_date'])) ? $row['weight_date'] : null;
        
        // Only add record if there's valid data
        if ($serviceDate || $weightDate || !empty($row['service_type']) || !empty($row['reminder_description'])) {
            $petRecords[] = [
                'weight_date' => $weightDate,
                'weight' => $row['record_weight'] ?? null,
                'reminder_description' => $row['reminder_description'] ?? null,
                'reminder_due_date' => $row['reminder_due_date'] ?? null,
                'service_date' => $serviceDate,
                'service_type' => $row['service_type'] ?? null,
                'service_description' => $row['service_description'] ?? null,
                'veterinarian' => $row['veterinarian'] ?? null,
                'notes' => $row['notes'] ?? null
            ];
        }
    }
}

if ($currentPetId !== null) {
    $pets[$currentPetId]['records'] = $petRecords;
}

// ✅ 5. Fetch user's appointments
$appointments_stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species
    FROM appointments a 
    LEFT JOIN pets p ON a.pet_id = p.pet_id 
    WHERE a.user_id = ? 
    ORDER BY 
        CASE 
            WHEN a.status = 'pending' THEN 1
            WHEN a.status = 'scheduled' THEN 2
            WHEN a.status = 'confirmed' THEN 3
            WHEN a.status = 'completed' THEN 4
            ELSE 5
        END,
        a.appointment_date ASC,
        a.appointment_time ASC
");
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$user_appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count appointments by status
$appointment_stats = [
    'pending' => 0,
    'scheduled' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'total' => count($user_appointments)
];

foreach ($user_appointments as $appt) {
    if (isset($appointment_stats[$appt['status']])) {
        $appointment_stats[$appt['status']]++;
    }
}

// ✅ 6. Fetch user notifications
$notifications_stmt = $conn->prepare("
    SELECT * FROM user_notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 10
");
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ 7. Dashboard statistics
$totalPets = count($pets);
$vaccinatedPets = 0;
$upcomingReminders = 0;
$recentVisits = 0;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// Health monitoring data
$weightHistory = [];
$serviceFrequency = [];
$healthScores = [];

foreach ($pets as $pet) {
    $hasVaccination = false;
    $petWeightHistory = [];
    $serviceCount = 0;
    
    foreach ($pet['records'] as $record) {
        // Check if service type indicates vaccination
        if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
            $hasVaccination = true;
        }
        
        // Check for upcoming reminders
        if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
            $upcomingReminders++;
        }
        
        // Check for recent visits
        if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
            $recentVisits++;
        }
        
        // Collect weight history
        if (!empty($record['weight_date']) && !empty($record['weight'])) {
            $petWeightHistory[] = [
                'date' => $record['weight_date'],
                'weight' => floatval($record['weight'])
            ];
        }
        
        // Count services
        if (!empty($record['service_date'])) {
            $serviceCount++;
        }
    }
    
    // Store weight history for this pet
    if (!empty($petWeightHistory)) {
        $weightHistory[$pet['pet_id']] = [
            'pet_name' => $pet['pet_name'],
            'data' => $petWeightHistory
        ];
    }
    
    // Calculate service frequency
    if ($pet['date_registered']) {
        $registeredDays = max(1, (time() - strtotime($pet['date_registered'])) / (60 * 60 * 24));
        $serviceFrequency[$pet['pet_id']] = [
            'pet_name' => $pet['pet_name'],
            'services_per_month' => round(($serviceCount / $registeredDays) * 30, 2)
        ];
    }
    
    // Calculate health score (simplified)
    $healthScore = 70; // Base score
    if ($hasVaccination) $healthScore += 20;
    if ($serviceCount > 0) $healthScore += min(10, $serviceCount);
    if (!empty($petWeightHistory)) $healthScore += 10;
    
    $healthScores[$pet['pet_id']] = [
        'pet_name' => $pet['pet_name'],
        'score' => min(100, $healthScore),
        'vaccinated' => $hasVaccination,
        'service_count' => $serviceCount
    ];
    
    if ($hasVaccination) {
        $vaccinatedPets++;
    }
}

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
    <title>VetCareQR - Pet Medical Records & QR Generator</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
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

        /* Appointment Button Styles */
        .sidebar .appointment-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
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
            background: linear-gradient(135deg, var(--dark-pink), var(--primary-pink));
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
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
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
        
        .pet-card-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .pet-card-body {
            padding: 1rem;
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
        
        .status-good {
            background-color: var(--green);
        }
        
        .status-warning {
            background-color: var(--orange);
        }
        
        .status-bad {
            background-color: #e74c3c;
        }
        
        .medical-table {
            font-size: 0.9rem;
        }
        
        .medical-table th {
            background-color: #f8f9fa;
        }
        
        .qr-preview {
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .qr-preview:hover {
            transform: scale(1.05);
        }
        
        .qr-data-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .badge-service {
            background: linear-gradient(to right, var(--blue), #4a6cf7);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .badge-vaccine {
            background: linear-gradient(to right, var(--green), #2ecc71);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        
        .badge-checkup {
            background: linear-gradient(to right, var(--orange), #f39c12);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
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
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        
        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }
        
        .profile-picture-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .health-score {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
        }
        
        .score-excellent { color: var(--green); }
        .score-good { color: #7e57c2; }
        .score-fair { color: var(--orange); }
        .score-poor { color: #e74c3c; }
        
        .visualization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .viz-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        /* Appointment Status Badges */
        .badge-pending { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .badge-scheduled { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge-confirmed { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .badge-completed { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
        .badge-cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        
        .appointment-pending { border-left-color: #f39c12; background: #fef9e7; }
        .appointment-scheduled { border-left-color: #3498db; background: #ebf5fb; }
        .appointment-confirmed { border-left-color: #2ecc71; background: #eafaf1; }
        .appointment-completed { border-left-color: #95a5a6; background: #f8f9fa; }
        .appointment-cancelled { border-left-color: #e74c3c; background: #fdedec; }
        
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
            
            .visualization-grid {
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
                <div class="profile-picture-overlay" onclick="location.href='user_settings.php'">
                    <i class="fas fa-camera"></i>
                </div>
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
        <a href="user_appointments.php">
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
                <h5 class="mb-0">Good Morning, <span id="ownerName"><?php echo htmlspecialchars($user['name']); ?></span> 👋</h5>
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
                    <input type="text" placeholder="Search pet, vaccine, vet..." class="form-control">
                    <button class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-magnifying-glass"></i></button>
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
                <div class="stats-card" style="background: linear-gradient(135deg, var(--blue-light), #e3f2fd);">
                    <i class="fa-solid fa-paw text-primary"></i>
                    <h6>Registered Pets</h6>
                    <h4 id="totalPets"><?php echo $totalPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--green-light), #e8f5e8);">
                    <i class="fa-solid fa-syringe text-success"></i>
                    <h6>Vaccinated Pets</h6>
                    <h4 id="vaccinatedPets"><?php echo $vaccinatedPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--orange-light), #fff3e0);">
                    <i class="fa-solid fa-calendar-check text-warning"></i>
                    <h6>Upcoming Reminders</h6>
                    <h4 id="upcomingVaccines"><?php echo $upcomingReminders; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--light-pink), #fce4ec);">
                    <i class="fa-solid fa-stethoscope text-danger"></i>
                    <h6>Recent Visits</h6>
                    <h4 id="recentVisits"><?php echo $recentVisits; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #e8f6f3, #d0ece7);">
                    <i class="fa-solid fa-calendar-day text-info"></i>
                    <h6>Total Appointments</h6>
                    <h4 id="totalAppointments"><?php echo $appointment_stats['total']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #fdebd0, #fad7a0);">
                    <i class="fa-solid fa-clock text-warning"></i>
                    <h6>Pending</h6>
                    <h4 id="pendingAppointments"><?php echo $appointment_stats['pending']; ?></h4>
                </div>
            </div>
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
                    <?php 
                    $display_count = 0;
                    foreach ($user_appointments as $appt): 
                        if ($display_count >= 6) break;
                        $display_count++;
                    ?>
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
                                            <form method="POST" action="user_dashboard.php" class="d-inline">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                <input type="hidden" name="cancel_reason" value="Cancelled by user">
                                                <button type="submit" name="cancel_appointment" class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($appt['status'] == 'scheduled'): ?>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i> Confirm
                                                </button>
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
                                            <small class="text-success"><i class="fas fa-check-circle me-1"></i>Confirmed</small>
                                        <?php elseif ($appt['status'] == 'completed'): ?>
                                            <small class="text-secondary"><i class="fas fa-flag-checkered me-1"></i>Completed</small>
                                        <?php elseif ($appt['status'] == 'cancelled'): ?>
                                            <small class="text-danger"><i class="fas fa-ban me-1"></i>Cancelled</small>
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

        <!-- Health Monitoring Visualizations -->
        <?php if (!empty($pets)): ?>
        <div class="card-custom">
            <h4 class="mb-4"><i class="fas fa-chart-line me-2"></i>Health Monitoring Dashboard</h4>
            
            <div class="visualization-grid">
                <!-- Health Scores Chart -->
                <div class="viz-card">
                    <h6><i class="fas fa-heartbeat me-2"></i>Pet Health Scores</h6>
                    <div class="chart-container">
                        <canvas id="healthScoresChart"></canvas>
                    </div>
                </div>

                <!-- Weight Trends -->
                <div class="viz-card">
                    <h6><i class="fas fa-weight me-2"></i>Weight Trends</h6>
                    <div class="chart-container">
                        <canvas id="weightTrendsChart"></canvas>
                    </div>
                </div>

                <!-- Service Frequency -->
                <div class="viz-card">
                    <h6><i class="fas fa-calendar-alt me-2"></i>Vet Visit Frequency</h6>
                    <div class="chart-container">
                        <canvas id="serviceFrequencyChart"></canvas>
                    </div>
                </div>

                <!-- Health Distribution -->
                <div class="viz-card">
                    <h6><i class="fas fa-chart-pie me-2"></i>Health Status Distribution</h6>
                    <div class="chart-container">
                        <canvas id="healthDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Individual Pet Health Cards -->
            <div class="row mt-4">
                <?php foreach ($healthScores as $petId => $healthData): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title"><?php echo htmlspecialchars($healthData['pet_name']); ?></h6>
                                <?php
                                $scoreClass = 'score-poor';
                                if ($healthData['score'] >= 90) $scoreClass = 'score-excellent';
                                elseif ($healthData['score'] >= 75) $scoreClass = 'score-good';
                                elseif ($healthData['score'] >= 60) $scoreClass = 'score-fair';
                                ?>
                                <div class="health-score <?php echo $scoreClass; ?>">
                                    <?php echo $healthData['score']; ?>%
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <?php if ($healthData['vaccinated']): ?>
                                            <i class="fas fa-check-circle text-success me-1"></i>Vaccinated
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>Needs Vaccination
                                        <?php endif; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-stethoscope me-1"></i><?php echo $healthData['service_count']; ?> visits
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Add Pet Button -->
        <div class="card-custom text-center">
            <h5><i class="fa-solid fa-paw me-2"></i>Manage Your Pets</h5>
            <p class="text-muted">Register your pets to track their medical records and generate QR codes</p>
            <a href="register_pet.php" class="btn btn-primary">
                <i class="fa-solid fa-plus-circle me-1"></i> Add New Pet
            </a>
        </div>

        <!-- Pets Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-paw me-2"></i>Your Pets & Medical Records</h4>
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
                        <div class="col-md-6 col-lg-6 mb-3">
                            <div class="pet-card">
                                <div class="pet-card-header" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#e8f4fd' : '#fde8f2'; ?>">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?></small>
                                    </div>
                                    <div class="pet-species-icon" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#bbdefb' : '#f8bbd0'; ?>">
                                        <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                                    </div>
                                </div>
                                <div class="pet-card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div id="qrcode-<?php echo $pet['pet_id']; ?>" class="me-3 qr-preview"></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
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
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadQRCode(<?php echo $pet['pet_id']; ?>)">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- QR Data Preview -->
                                    <div id="qr-data-<?php echo $pet['pet_id']; ?>" class="qr-data-preview" style="display: none;"></div>
                                    
                                    <?php if (!empty($pet['records'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm medical-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Service Type</th>
                                                        <th>Description</th>
                                                        <th>Veterinarian</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pet['records'] as $record): ?>
                                                        <?php if (!empty($record['service_date']) && $record['service_date'] !== '0000-00-00'): ?>
                                                            <tr>
                                                                <td><?php echo date('M j, Y', strtotime($record['service_date'])); ?></td>
                                                                <td>
                                                                    <?php if (!empty($record['service_type'])): ?>
                                                                        <?php 
                                                                        $badgeClass = 'badge-service';
                                                                        if (stripos($record['service_type'], 'vaccin') !== false) {
                                                                            $badgeClass = 'badge-vaccine';
                                                                        } elseif (stripos($record['service_type'], 'check') !== false) {
                                                                            $badgeClass = 'badge-checkup';
                                                                        }
                                                                        ?>
                                                                        <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($record['service_description'] ?? '-'); ?></td>
                                                                <td><?php echo htmlspecialchars($record['veterinarian'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-1"></i> No medical records found for <?php echo htmlspecialchars($pet['pet_name']); ?>.
                                        </div>
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

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="modalQrContainer" class="mb-3"></div>
                <div id="modalQrData" class="qr-data-preview mb-3"></div>
                <p class="text-muted">Scan this QR code to view medical records</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="downloadModalQr">
                    <i class="fas fa-download me-1"></i> Download
                </button>
                <button type="button" class="btn btn-info" onclick="toggleQrData()">
                    <i class="fas fa-eye me-1"></i> View Data
                </button>
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
            <form method="POST" action="user_dashboard.php" id="cancelForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="cancel_reason" rows="3" placeholder="Please provide a reason for cancellation..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to cancel this appointment? This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Appointment</button>
                    <button type="submit" name="cancel_appointment" class="btn btn-danger">Cancel Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initialize the dashboard
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date and time
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Generate QR codes for pets
        <?php foreach ($pets as $pet): ?>
            generateQRCode('qrcode-<?php echo $pet['pet_id']; ?>', {
                petId: <?php echo $pet['pet_id']; ?>,
                petName: '<?php echo addslashes($pet['pet_name']); ?>',
                species: '<?php echo addslashes($pet['species']); ?>',
                breed: '<?php echo addslashes($pet['breed']); ?>',
                age: '<?php echo $pet['age']; ?>',
                color: '<?php echo addslashes($pet['color']); ?>',
                weight: '<?php echo $pet['weight']; ?>',
                birthDate: '<?php echo $pet['birth_date']; ?>',
                gender: '<?php echo addslashes($pet['gender']); ?>',
                medicalNotes: '<?php echo addslashes($pet['medical_notes']); ?>',
                vetContact: '<?php echo addslashes($pet['vet_contact']); ?>',
                registered: '<?php echo $pet['date_registered']; ?>',
                records: [
                    <?php foreach ($pet['records'] as $record): ?>
                        { 
                            service_date: '<?php echo $record['service_date'] ?? ''; ?>', 
                            service_type: '<?php echo addslashes($record['service_type'] ?? ''); ?>', 
                            service_description: '<?php echo addslashes($record['service_description'] ?? ''); ?>', 
                            veterinarian: '<?php echo addslashes($record['veterinarian'] ?? ''); ?>',
                            notes: '<?php echo addslashes($record['notes'] ?? ''); ?>'
                        },
                    <?php endforeach; ?>
                ]
            });
        <?php endforeach; ?>
        
        // Add click event to QR codes
        document.querySelectorAll('[id^="qrcode-"]').forEach(qrContainer => {
            qrContainer.style.cursor = 'pointer';
            qrContainer.onclick = function() {
                const petId = qrContainer.id.replace('qrcode-', '');
                showQRModal(petId);
            };
        });
        
        // Initialize health monitoring charts
        initializeHealthCharts();
        
        // Setup cancel appointment modals
        document.querySelectorAll('[data-appointment-id]').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                document.getElementById('cancelAppointmentId').value = appointmentId;
            });
        });
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

    // Health Monitoring Charts
    function initializeHealthCharts() {
        // Health Scores Bar Chart
        const healthScoresCtx = document.getElementById('healthScoresChart').getContext('2d');
        const healthScoresChart = new Chart(healthScoresCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($score) { return "'" . addslashes($score['pet_name']) . "'"; }, $healthScores)); ?>],
                datasets: [{
                    label: 'Health Score (%)',
                    data: [<?php echo implode(',', array_column($healthScores, 'score')); ?>],
                    backgroundColor: [
                        <?php 
                        foreach ($healthScores as $score) {
                            if ($score['score'] >= 90) echo "'#2ecc71',";
                            elseif ($score['score'] >= 75) echo "'#7e57c2',";
                            elseif ($score['score'] >= 60) echo "'#f39c12',";
                            else echo "'#e74c3c',";
                        }
                        ?>
                    ],
                    borderColor: '#333',
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
                            text: 'Health Score (%)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Health Score: ${context.parsed.y}%`;
                            }
                        }
                    }
                }
            }
        });

        // Weight Trends Line Chart
        <?php if (!empty($weightHistory)): ?>
        const weightTrendsCtx = document.getElementById('weightTrendsChart').getContext('2d');
        const weightTrendsChart = new Chart(weightTrendsCtx, {
            type: 'line',
            data: {
                datasets: [
                    <?php foreach ($weightHistory as $petId => $petData): ?>
                    {
                        label: '<?php echo addslashes($petData['pet_name']); ?>',
                        data: [
                            <?php foreach ($petData['data'] as $weightRecord): ?>
                            {
                                x: '<?php echo $weightRecord['date']; ?>',
                                y: <?php echo $weightRecord['weight']; ?>
                            },
                            <?php endforeach; ?>
                        ],
                        borderColor: '<?php echo getRandomColor($petId); ?>',
                        backgroundColor: '<?php echo getRandomColor($petId, 0.1); ?>',
                        tension: 0.4,
                        fill: false
                    },
                    <?php endforeach; ?>
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'month'
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Weight (kg)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Service Frequency Chart
        const serviceFrequencyCtx = document.getElementById('serviceFrequencyChart').getContext('2d');
        const serviceFrequencyChart = new Chart(serviceFrequencyCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($freq) { return "'" . addslashes($freq['pet_name']) . "'"; }, $serviceFrequency)); ?>],
                datasets: [{
                    label: 'Visits per Month',
                    data: [<?php echo implode(',', array_column($serviceFrequency, 'services_per_month')); ?>],
                    backgroundColor: '#4a6cf7',
                    borderColor: '#333',
                    borderWidth: 1
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
                            text: 'Visits per Month'
                        }
                    }
                }
            }
        });

        // Health Distribution Pie Chart
        const healthDistributionCtx = document.getElementById('healthDistributionChart').getContext('2d');
        
        // Calculate health status distribution
        let excellent = 0, good = 0, fair = 0, poor = 0;
        <?php foreach ($healthScores as $score): ?>
            <?php if ($score['score'] >= 90): ?>excellent++;
            <?php elseif ($score['score'] >= 75): ?>good++;
            <?php elseif ($score['score'] >= 60): ?>fair++;
            <?php else: ?>poor++;<?php endif; ?>
        <?php endforeach; ?>

        const healthDistributionChart = new Chart(healthDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent (90-100%)', 'Good (75-89%)', 'Fair (60-74%)', 'Poor (<60%)'],
                datasets: [{
                    data: [excellent, good, fair, poor],
                    backgroundColor: [
                        '#2ecc71',
                        '#7e57c2',
                        '#f39c12',
                        '#e74c3c'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
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

    // Helper function to generate random colors for charts
    function getRandomColor(seed, opacity = 1) {
        const colors = [
            '#e91e63', '#4a6cf7', '#2ecc71', '#f39c12', 
            '#9c27b0', '#2196f3', '#00bcd4', '#ff9800'
        ];
        const index = Math.abs(seed) % colors.length;
        return opacity < 1 ? 
            colors[index].replace(')', `, ${opacity})`).replace('rgb', 'rgba') : 
            colors[index];
    }

    // Update date and time display
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    // Function to generate QR code
    function generateQRCode(containerId, petData) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Format data for QR code
        let qrData = `PET MEDICAL RECORD\n`;
        qrData += `==================\n\n`;
        qrData += `BASIC INFORMATION:\n`;
        qrData += `------------------\n`;
        qrData += `Name: ${petData.petName}\n`;
        qrData += `Species: ${petData.species}\n`;
        qrData += `Breed: ${petData.breed || 'Unknown'}\n`;
        qrData += `Age: ${petData.age} years\n`;
        qrData += `Color: ${petData.color || 'Not specified'}\n`;
        qrData += `Weight: ${petData.weight ? petData.weight + ' kg' : 'Not specified'}\n`;
        qrData += `Birth Date: ${petData.birthDate ? new Date(petData.birthDate).toLocaleDateString() : 'Unknown'}\n`;
        qrData += `Gender: ${petData.gender || 'Not specified'}\n`;
        qrData += `Registered: ${new Date(petData.registered).toLocaleDateString()}\n\n`;
        
        qrData += `MEDICAL INFORMATION:\n`;
        qrData += `--------------------\n`;
        qrData += `Medical Notes: ${petData.medicalNotes || 'None'}\n`;
        qrData += `Veterinarian: ${petData.vetContact || 'Not specified'}\n\n`;
        
        qrData += `MEDICAL HISTORY:\n`;
        qrData += `----------------\n`;
        
        if (petData.records && petData.records.length > 0) {
            petData.records.forEach((record, index) => {
                if (record.service_date) {
                    qrData += `VISIT ${index + 1}:\n`;
                    qrData += `Date: ${record.service_date}\n`;
                    if (record.service_type) qrData += `Service: ${record.service_type}\n`;
                    if (record.service_description) qrData += `Description: ${record.service_description}\n`;
                    if (record.veterinarian) qrData += `Veterinarian: ${record.veterinarian}\n`;
                    if (record.notes) qrData += `Notes: ${record.notes}\n`;
                    qrData += `\n`;
                }
            });
        } else {
            qrData += `No medical records available.\n`;
        }
        
        qrData += `\nGenerated on: ${new Date().toLocaleDateString()}`;
        qrData += `\nOwner: ${document.getElementById('ownerName').textContent}`;
        qrData += `\nPet ID: ${petData.petId}`;
        
        // Generate QR code
        const qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        container.innerHTML = qr.createSvgTag({
            scalable: true,
            margin: 2,
            color: '#000',
            background: '#fff'
        });
        
        // Store the data for later use
        container.setAttribute('data-qr-content', qrData);
        container.setAttribute('data-pet-name', petData.petName);
        container.setAttribute('data-pet-id', petData.petId);
        
        // Also update the QR data preview
        const qrDataPreview = document.getElementById(`qr-data-${petData.petId}`);
        if (qrDataPreview) {
            qrDataPreview.textContent = qrData;
        }
    }

    // Function to show QR code in modal
    function showQRModal(petId) {
        const qrContainer = document.getElementById(`qrcode-${petId}`);
        const modalQrContainer = document.getElementById('modalQrContainer');
        const modalQrData = document.getElementById('modalQrData');
        const qrModalTitle = document.getElementById('qrModalTitle');
        
        if (!qrContainer || !modalQrContainer) return;
        
        const petName = qrContainer.getAttribute('data-pet-name');
        const qrContent = qrContainer.getAttribute('data-qr-content');
        
        // Update modal title
        qrModalTitle.textContent = `QR Code - ${petName}`;
        
        // Copy QR code to modal
        modalQrContainer.innerHTML = qrContainer.innerHTML;
        
        // Set QR data
        modalQrData.textContent = qrContent;
        modalQrData.style.display = 'none';
        
        // Update download button
        const downloadBtn = document.getElementById('downloadModalQr');
        downloadBtn.onclick = function() {
            downloadQRCode(petId);
        };
        
        // Show modal
        const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
        qrModal.show();
    }

    // Function to toggle QR data visibility in modal
    function toggleQrData() {
        const qrData = document.getElementById('modalQrData');
        if (qrData.style.display === 'none') {
            qrData.style.display = 'block';
        } else {
            qrData.style.display = 'none';
        }
    }

    // Function to download QR code as SVG
    function downloadQRCode(petId) {
        const qrContainer = document.getElementById(`qrcode-${petId}`);
        if (!qrContainer) return;
        
        const petName = qrContainer.getAttribute('data-pet-name');
        const svgElement = qrContainer.querySelector('svg');
        
        if (!svgElement) {
            alert('QR code not found!');
            return;
        }
        
        // Serialize SVG
        const serializer = new XMLSerializer();
        let source = serializer.serializeToString(svgElement);
        
        // Add namespace
        if (!source.match(/^<svg[^>]+xmlns="http\:\/\/www\.w3\.org\/2000\/svg"/)) {
            source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
        }
        if (!source.match(/^<svg[^>]+"http\:\/\/www\.w3\.org\/1999\/xlink"/)) {
            source = source.replace(/^<svg/, '<svg xmlns:xlink="http://www.w3.org/1999/xlink"');
        }
        
        // Add styling for better print quality
        source = source.replace('</svg>', '<style>text{font-family:Helvetica,Arial,sans-serif;}</style></svg>');
        
        // Convert to blob
        const blob = new Blob([source], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        
        // Create download link
        const downloadLink = document.createElement('a');
        downloadLink.href = url;
        downloadLink.download = `petmedqr-${petName.toLowerCase().replace(/\s+/g, '-')}-${petId}.svg`;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        URL.revokeObjectURL(url);
    }

    // Function to show cancel appointment modal
    function showCancelModal(appointmentId) {
        document.getElementById('cancelAppointmentId').value = appointmentId;
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        cancelModal.show();
    }

    // Search functionality
    document.addEventListener('keydown', function(e) {
        // Ctrl+K for search focus (common shortcut)
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.input-group input');
            if (searchInput) searchInput.focus();
        }
    });

    // Auto-refresh data every 5 minutes
    setInterval(() => {
        console.log('Auto-refreshing dashboard data...');
        // In a real application, you might want to fetch updated data
        // location.reload(); // Simple refresh for demo
    }, 300000); // 5 minutes

    console.log('PetMedQR Dashboard with Appointment Management initialized successfully!');
</script>
</body>
</html>

<?php
// Helper function for random colors in PHP
function getRandomColor($seed, $opacity = 1) {
    $colors = [
        '#e91e63', '#4a6cf7', '#2ecc71', '#f39c12', 
        '#9c27b0', '#2196f3', '#00bcd4', '#ff9800'
    ];
    $index = abs($seed) % count($colors);
    return $colors[$index];
}
?>
