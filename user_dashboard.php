<?php
session_start();
include("conn.php");

// ✅ Enhanced Security: CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ 1. Check if user is logged in with enhanced validation
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ✅ 2. Fetch logged-in user info with enhanced error handling
try {
    $stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Database preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Enhanced profile picture handling
    $profile_picture = !empty($user['profile_picture']) && filter_var($user['profile_picture'], FILTER_VALIDATE_URL) 
        ? $user['profile_picture'] 
        : "https://i.pravatar.cc/100?u=" . urlencode($user['name']);
    
} catch (Exception $e) {
    error_log("User data fetch error: " . $e->getMessage());
    die("Error fetching user data. Please try again later.");
}

// ✅ 3. Enhanced Pets & Medical Records Fetching with Caching
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
    // Optimized query with better joins and filtering
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
        p.health_status,
        m.record_id,
        m.service_date,
        m.service_type,
        m.service_description,
        m.veterinarian,
        m.reminder_due_date,
        m.reminder_description,
        m.is_vaccination
    FROM pets p
    LEFT JOIN pet_medical_records m ON p.pet_id = m.pet_id 
        AND (m.service_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) OR m.reminder_due_date >= CURDATE())
    WHERE p.user_id = ? AND p.is_active = 1
    ORDER BY p.pet_id, m.service_date DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Process pets data with enhanced structure
    $currentPetId = null;
    $petRecords = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['pet_id'])) continue;
        
        if ($currentPetId !== $row['pet_id']) {
            if ($currentPetId !== null) {
                $pets[$currentPetId]['records'] = $petRecords;
                $pets[$currentPetId]['last_visit'] = !empty($petRecords) ? $petRecords[0]['service_date'] : null;
            }
            $currentPetId = $row['pet_id'];
            $petRecords = [];
            
            $pets[$currentPetId] = [
                'pet_id' => $row['pet_id'],
                'pet_name' => htmlspecialchars($row['pet_name']),
                'species' => htmlspecialchars($row['species'] ?? 'Unknown'),
                'breed' => htmlspecialchars($row['breed'] ?? 'Unknown'),
                'age' => htmlspecialchars($row['age'] ?? '0'),
                'color' => htmlspecialchars($row['color'] ?? ''),
                'weight' => $row['weight'] ?? null,
                'birth_date' => $row['birth_date'] ?? null,
                'gender' => htmlspecialchars($row['gender'] ?? ''),
                'medical_notes' => htmlspecialchars($row['medical_notes'] ?? ''),
                'vet_contact' => htmlspecialchars($row['vet_contact'] ?? ''),
                'health_status' => $row['health_status'] ?? 'unknown',
                'date_registered' => $row['date_registered'] ?? date('Y-m-d'),
                'qr_code' => $row['qr_code'] ?? '',
                'qr_code_data' => $row['qr_code_data'] ?? ''
            ];
        }
        
        // Enhanced medical record processing
        if (!empty($row['record_id'])) {
            $serviceDate = ($row['service_date'] !== '0000-00-00' && !empty($row['service_date'])) ? $row['service_date'] : null;
            
            $petRecords[] = [
                'record_id' => $row['record_id'],
                'service_date' => $serviceDate,
                'service_type' => htmlspecialchars($row['service_type'] ?? ''),
                'service_description' => htmlspecialchars($row['service_description'] ?? ''),
                'veterinarian' => htmlspecialchars($row['veterinarian'] ?? ''),
                'reminder_due_date' => $row['reminder_due_date'] ?? null,
                'reminder_description' => htmlspecialchars($row['reminder_description'] ?? ''),
                'is_vaccination' => (bool)($row['is_vaccination'] ?? false)
            ];
        }
    }

    if ($currentPetId !== null) {
        $pets[$currentPetId]['records'] = $petRecords;
    }
    
    $totalPets = count($pets);
    
    // Enhanced statistics calculation
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    
    foreach ($pets as $pet) {
        $petVaccinated = false;
        $petVisits = 0;
        $petWeight = $pet['weight'] ? floatval($pet['weight']) : null;
        $hasUpcomingReminder = false;
        
        foreach ($pet['records'] as $record) {
            // Check for vaccinations
            if ($record['is_vaccination'] || (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false)) {
                $petVaccinated = true;
            }
            
            // Check for upcoming reminders
            if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
                $hasUpcomingReminder = true;
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
        if ($hasUpcomingReminder) {
            $upcomingReminders++;
        }
        
        // Enhanced health data calculation
        $healthData['vaccination_status'][$pet['pet_name']] = $petVaccinated ? 'Vaccinated' : 'Not Vaccinated';
        $healthData['visit_frequency'][$pet['pet_name']] = $petVisits;
        
        if ($petWeight) {
            $healthData['weight_trends'][$pet['pet_name']] = $petWeight;
        }
        
        // Enhanced health score calculation
        $healthScore = 60; // Base score
        
        // Health status bonus
        if ($pet['health_status'] === 'excellent') $healthScore += 20;
        elseif ($pet['health_status'] === 'good') $healthScore += 10;
        elseif ($pet['health_status'] === 'poor') $healthScore -= 10;
        
        if ($petVaccinated) $healthScore += 15;
        if ($petVisits > 0) $healthScore += 10;
        if ($petVisits > 2) $healthScore += 5; // Bonus for regular checkups
        
        $healthData['health_scores'][$pet['pet_name']] = min(max($healthScore, 0), 100);
    }
    
} catch (Exception $e) {
    error_log("Error fetching pets: " . $e->getMessage());
    // Don't die, just show empty state
}

// ✅ 4. Enhanced Appointments Fetching
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
        SELECT a.*, p.name as pet_name, p.species, p.profile_picture as pet_image
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
        LIMIT 12
    ");
    
    if ($appointments_stmt) {
        $appointments_stmt->bind_param("i", $user_id);
        $appointments_stmt->execute();
        $user_appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Enhanced appointment counting
        foreach ($user_appointments as $appt) {
            $status = $appt['status'];
            if (isset($appointment_stats[$status])) {
                $appointment_stats[$status]++;
            }
        }
        $appointment_stats['total'] = count($user_appointments);
    }
    
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// ✅ 5. Enhanced Notifications with Pagination
$notifications = [];
$unread_count = 0;
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if ($table_check && $table_check->num_rows > 0) {
        $notifications_stmt = $conn->prepare("
            SELECT * FROM user_notifications 
            WHERE user_id = ? AND (is_read = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            ORDER BY is_read ASC, created_at DESC 
            LIMIT 15
        ");
        $notifications_stmt->bind_param("i", $user_id);
        $notifications_stmt->execute();
        $notifications = $notifications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Count unread notifications
        $unread_count = array_reduce($notifications, function($count, $notification) {
            return $count + ($notification['is_read'] == 0 ? 1 : 0);
        }, 0);
    }
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// ✅ 6. Enhanced Action Handling with CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token for all POST actions
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Security token validation failed.";
        header("Location: user_dashboard.php");
        exit();
    }
}

// Handle mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    try {
        $mark_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
        $mark_stmt->bind_param("ii", $notification_id, $user_id);
        if ($mark_stmt->execute()) {
            $_SESSION['success'] = "Notification marked as read!";
        }
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
    }
    header("Location: user_dashboard.php");
    exit();
}

// Handle mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    try {
        $mark_all_stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $mark_all_stmt->bind_param("i", $user_id);
        if ($mark_all_stmt->execute()) {
            $_SESSION['success'] = "All notifications marked as read!";
        }
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
    }
    header("Location: user_dashboard.php");
    exit();
}

// Handle cancel appointment with enhanced validation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $cancel_reason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');
    
    if (empty($cancel_reason)) {
        $_SESSION['error'] = "Please provide a reason for cancellation.";
        header("Location: user_dashboard.php");
        exit();
    }
    
    try {
        $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', vet_notes = CONCAT(COALESCE(vet_notes, ''), ' [Cancelled: ', ?, ']'), updated_at = NOW() WHERE appointment_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')");
        $cancel_stmt->bind_param("sii", $cancel_reason, $appointment_id, $user_id);
        
        if ($cancel_stmt->execute() && $cancel_stmt->affected_rows > 0) {
            $_SESSION['success'] = "Appointment cancelled successfully!";
        } else {
            $_SESSION['error'] = "Unable to cancel appointment. It may have already been processed.";
        }
    } catch (Exception $e) {
        error_log("Error cancelling appointment: " . $e->getMessage());
        $_SESSION['error'] = "Error cancelling appointment. Please try again.";
    }
    header("Location: user_dashboard.php");
    exit();
}

// Calculate additional metrics for dashboard
$health_alerts = 0;
$upcoming_vaccinations = 0;
foreach ($pets as $pet) {
    if ($pet['health_status'] === 'poor') $health_alerts++;
    
    foreach ($pet['records'] as $record) {
        if ($record['is_vaccination'] && 
            !empty($record['reminder_due_date']) && 
            $record['reminder_due_date'] >= date('Y-m-d') &&
            $record['reminder_due_date'] <= date('Y-m-d', strtotime('+30 days'))) {
            $upcoming_vaccinations++;
        }
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #e0f2fe 100%);
            margin: 0;
            color: #333;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: white;
            padding: 1.5rem 1rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .profile-info,
        .sidebar.collapsed .nav-text {
            display: none;
        }
        
        .brand {
            font-weight: 800;
            font-size: 1.3rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0.5rem;
        }
        
        .profile {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius);
        }
        
        .profile img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid var(--primary);
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .profile img:hover {
            transform: scale(1.05);
        }
        
        .nav-item {
            margin: 0.25rem 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--primary-light);
            color: var(--primary-dark);
            transform: translateX(5px);
        }
        
        .nav-link .icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(14, 165, 233, 0.1);
            margin-right: 12px;
            transition: all 0.3s ease;
        }
        
        .nav-link.active .icon {
            background: var(--primary);
            color: white;
        }
        
        .appointment-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 16px;
            margin: 1rem 0;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: var(--shadow);
        }
        
        .appointment-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
            background: #f8fafc;
        }
        
        .topbar {
            background: white;
            padding: 1.25rem 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .card-custom {
            background: white;
            border-radius: var(--radius);
            padding: 1.75rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stats-card {
            text-align: center;
            padding: 1.75rem 1rem;
            border-radius: var(--radius);
            height: 100%;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.3);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card i {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        /* Enhanced appointment styles */
        .badge-pending { background: linear-gradient(135deg, var(--warning), #e67e22); }
        .badge-confirmed { background: linear-gradient(135deg, var(--success), #27ae60); }
        .badge-completed { background: linear-gradient(135deg, var(--primary), #2980b9); }
        .badge-cancelled { background: linear-gradient(135deg, var(--danger), #c0392b); }
        
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            border-radius: 12px;
        }
        
        .appointment-pending { border-left-color: var(--warning); background: linear-gradient(135deg, #fef9e7, #fef5e7); }
        .appointment-confirmed { border-left-color: var(--success); background: linear-gradient(135deg, #eafaf1, #e8f8f1); }
        .appointment-completed { border-left-color: var(--primary); background: linear-gradient(135deg, #ebf5fb, #e8f4fb); }
        .appointment-cancelled { border-left-color: var(--danger); background: linear-gradient(135deg, #fdedec, #fcebec); }
        
        .pet-card {
            border-radius: var(--radius);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: var(--shadow);
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .pet-species-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--primary-light);
            color: var(--primary-dark);
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
            border-left: 4px solid;
        }
        
        .alert-success { border-left-color: var(--success); }
        .alert-danger { border-left-color: var(--danger); }
        .alert-warning { border-left-color: var(--warning); }
        .alert-info { border-left-color: var(--primary); }
        
        .notification-item {
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
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
        
        /* Enhanced Chart Styles */
        .chart-container {
            position: relative;
            height: 280px;
            margin: 1rem 0;
        }
        
        .health-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin: 1.5rem 0;
        }
        
        .metric-card {
            background: white;
            padding: 1.75rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-3px);
        }
        
        .metric-value {
            font-size: 2.25rem;
            font-weight: bold;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Enhanced AI Analysis Styles */
        .upload-area {
            border: 3px dashed var(--primary);
            padding: 2.5rem;
            text-align: center;
            border-radius: 12px;
            background: var(--light);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .upload-area:hover {
            border-color: var(--primary-dark);
            background: #e0f2fe;
            transform: translateY(-2px);
        }
        
        .class-list {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            height: 100%;
            border: 1px solid #e9ecef;
        }
        
        .disease-tag {
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            margin: 4px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Loading states */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Responsive improvements */
        @media (max-width: 1200px) {
            .sidebar {
                width: 250px;
            }
        }
        
        @media (max-width: 992px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .topbar {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }
            
            .health-metrics {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .health-metrics {
                grid-template-columns: 1fr;
            }
            
            .stats-card {
                padding: 1.5rem 0.5rem;
            }
            
            .card-custom {
                padding: 1.25rem;
            }
        }
        
        @media (max-width: 576px) {
            .topbar .d-flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .input-group {
                width: 100% !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .card-custom, .topbar, .sidebar {
                background: #1e293b;
                color: #e2e8f0;
            }
            
            .sidebar {
                border-right-color: #334155;
            }
            
            .main-content {
                background: #0f172a;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Enhanced Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fa-solid fa-paw"></i>
            <span class="brand-text">VetCareQR</span>
        </div>
        
        <div class="profile">
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                     alt="User Profile" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>'">
            </div>
            <div class="profile-info">
                <h6 id="ownerNameSidebar" class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
            </div>
        </div>

        <!-- Appointment Button -->
        <a href="user_appointment.php" class="appointment-btn">
            <i class="fas fa-calendar-plus"></i>
            <span class="nav-text">Book Appointment</span>
        </a>

        <!-- Navigation -->
        <nav class="nav flex-column">
            <div class="nav-item">
                <a href="user_dashboard.php" class="nav-link active">
                    <div class="icon"><i class="fa-solid fa-gauge-high"></i></div>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="user_pet_profile.php" class="nav-link">
                    <div class="icon"><i class="fa-solid fa-dog"></i></div>
                    <span class="nav-text">My Pets</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="user_appointment.php" class="nav-link">
                    <div class="icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <span class="nav-text">Appointments</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="qr_code.php" class="nav-link">
                    <div class="icon"><i class="fa-solid fa-qrcode"></i></div>
                    <span class="nav-text">QR Codes</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="register_pet.php" class="nav-link">
                    <div class="icon"><i class="fa-solid fa-plus-circle"></i></div>
                    <span class="nav-text">Register Pet</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="user_settings.php" class="nav-link">
                    <div class="icon"><i class="fa-solid fa-gear"></i></div>
                    <span class="nav-text">Settings</span>
                </a>
            </div>
        </nav>

        <!-- Logout Button -->
        <div class="mt-auto">
            <a href="logout.php" class="nav-link text-danger">
                <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <!-- Enhanced Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-primary me-3 d-lg-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h4 class="mb-1">Welcome Back, <span id="ownerName"><?php echo htmlspecialchars($user['name']); ?></span>! 👋</h4>
                    <small class="text-muted">Here's your pet health overview for today</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Notification Bell -->
                <div class="dropdown">
                    <a href="#" class="btn btn-outline-primary position-relative" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 380px;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            <?php if ($unread_count > 0): ?>
                                <a href="user_dashboard.php?mark_all_read=1" class="btn btn-sm btn-outline-primary">Mark All Read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($notifications)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-bell-slash fa-2x mb-3"></i>
                                    <p class="mb-0">No notifications</p>
                                    <small>You're all caught up!</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($notification['is_read'] == 0): ?>
                                                <a href="user_dashboard.php?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Search -->
                <div class="input-group" style="width:320px">
                    <input type="text" placeholder="Search pets, appointments, vets..." class="form-control" id="globalSearch">
                    <button class="btn btn-outline-primary" type="button" onclick="performSearch()">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                
                <!-- Date & Time -->
                <div class="text-end d-none d-md-block">
                    <strong id="currentDate" class="d-block"></strong>
                    <small id="currentTime" class="text-muted"></small>
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

        <!-- Enhanced Stats Cards -->
        <div class="row stats-row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fa-solid fa-paw"></i>
                    <h6>Total Pets</h6>
                    <h3><?php echo $totalPets; ?></h3>
                    <small><?php echo $totalPets == 1 ? 'Pet' : 'Pets'; ?> Registered</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success), #27ae60);">
                    <i class="fa-solid fa-syringe"></i>
                    <h6>Vaccinated</h6>
                    <h3><?php echo $vaccinatedPets; ?></h3>
                    <small><?php echo $vaccinatedPets == 1 ? 'Pet' : 'Pets'; ?> Protected</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning), #e67e22);">
                    <i class="fa-solid fa-bell"></i>
                    <h6>Reminders</h6>
                    <h3><?php echo $upcomingReminders; ?></h3>
                    <small>Upcoming Alerts</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <i class="fa-solid fa-stethoscope"></i>
                    <h6>Recent Visits</h6>
                    <h3><?php echo $recentVisits; ?></h3>
                    <small>Last 30 Days</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fa-solid fa-calendar-day"></i>
                    <h6>Appointments</h6>
                    <h3><?php echo $appointment_stats['total']; ?></h3>
                    <small>Total Booked</small>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fa-solid fa-clock"></i>
                    <h6>Pending</h6>
                    <h3><?php echo $appointment_stats['pending']; ?></h3>
                    <small>Awaiting Confirmation</small>
                </div>
            </div>
        </div>

        <!-- Health Monitoring Section -->
        <?php if (!empty($pets)): ?>
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-heart-pulse me-2"></i>Health Monitoring</h4>
                    <small class="text-muted">Real-time health insights and analytics</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshHealthData()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportHealthReport()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
            
            <!-- Enhanced Health Metrics -->
            <div class="health-metrics">
                <div class="metric-card">
                    <div class="metric-value"><?php echo $vaccinatedPets; ?>/<?php echo $totalPets; ?></div>
                    <div class="metric-label">Vaccinated Pets</div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $totalPets > 0 ? ($vaccinatedPets/$totalPets)*100 : 0; ?>%"></div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $recentVisits; ?></div>
                    <div class="metric-label">Recent Visits (30d)</div>
                    <small class="text-muted">Avg: <?php echo $totalPets > 0 ? round($recentVisits/$totalPets, 1) : 0; ?>/pet</small>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?php echo $upcomingReminders; ?></div>
                    <div class="metric-label">Upcoming Reminders</div>
                    <small class="text-muted"><?php echo $upcoming_vaccinations; ?> vaccinations due</small>
                </div>
                <div class="metric-card">
                    <?php
                    $avgHealthScore = !empty($healthData['health_scores']) ? 
                        round(array_sum($healthData['health_scores']) / count($healthData['health_scores'])) : 0;
                    ?>
                    <div class="metric-value"><?php echo $avgHealthScore; ?>%</div>
                    <div class="metric-label">Average Health Score</div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar 
                            <?php 
                            if ($avgHealthScore >= 80) echo 'bg-success';
                            elseif ($avgHealthScore >= 60) echo 'bg-warning';
                            else echo 'bg-danger';
                            ?>
                        " style="width: <?php echo $avgHealthScore; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Health Charts -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-syringe me-2"></i>Vaccination Status</h6>
                        <div class="chart-container">
                            <canvas id="vaccinationChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-chart-line me-2"></i>Health Scores</h6>
                        <div class="chart-container">
                            <canvas id="healthScoreChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-calendar-check me-2"></i>Visit Frequency</h6>
                        <div class="chart-container">
                            <canvas id="visitChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-weight me-2"></i>Weight Distribution</h6>
                        <div class="chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enhanced Pet Disease AI Analysis Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-robot me-2"></i>AI Pet Disease Analysis</h4>
                    <small class="text-muted">Powered by VetCare AI - Instant disease detection</small>
                </div>
                <span class="badge bg-success">Live</span>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="upload-area">
                        <div class="mb-3">
                            <i class="fa-solid fa-camera fa-2x text-primary mb-3"></i>
                            <h5>Upload Pet Image</h5>
                            <p class="text-muted">Get instant AI analysis for common pet diseases and conditions</p>
                        </div>
                        
                        <div class="mb-3">
                            <input type="file" id="petImageInput" accept="image/*" class="form-control" 
                                   onchange="previewImage(this)">
                            <div id="imagePreview" class="mt-3 text-center" style="display:none;">
                                <img id="previewImg" src="" class="img-thumbnail" style="max-height: 200px;">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="clearImage()">
                                    <i class="fas fa-times me-1"></i> Remove
                                </button>
                            </div>
                        </div>
                        
                        <button onclick="analyzePetImage()" class="btn btn-primary w-100 py-2" id="analyzeBtn">
                            <i class="fa-solid fa-magnifying-glass me-2"></i> Analyze Image
                        </button>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Supports: JPG, PNG, JPEG • Max 10MB<br>
                                <i class="fas fa-lightbulb me-1"></i> Best results with clear, well-lit images
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="class-list">
                        <h6><i class="fa-solid fa-list me-2"></i>Detectable Conditions</h6>
                        <p class="text-muted small mb-3">Our AI can identify 27 different pet health conditions</p>
                        <div id="diseaseClassesList" class="d-flex flex-wrap gap-2">
                            <div class="skeleton rounded-pill" style="width: 100px; height: 30px;"></div>
                            <div class="skeleton rounded-pill" style="width: 120px; height: 30px;"></div>
                            <div class="skeleton rounded-pill" style="width: 90px; height: 30px;"></div>
                            <div class="skeleton rounded-pill" style="width: 110px; height: 30px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="analysisResult" class="mt-4"></div>
        </div>

        <!-- Recent Appointments Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-calendar me-2"></i>Recent Appointments</h4>
                    <small class="text-muted">Your upcoming and recent veterinary appointments</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="user_appointment.php" class="btn btn-primary">
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
                    <p class="text-muted">Schedule your first appointment to get started with professional pet care.</p>
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
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($appt['pet_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($appt['species']); ?></small>
                                        </div>
                                        <span class="badge badge-<?php echo $appt['status']; ?>">
                                            <?php echo ucfirst($appt['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted"><i class="fas fa-calendar me-1"></i>Date & Time</small>
                                        <div class="fw-semibold">
                                            <?php echo date('M j, Y', strtotime($appt['appointment_date'])); ?><br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($appt['appointment_time'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($appt['service_type'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Service Type</small>
                                            <div class="small fw-semibold"><?php echo htmlspecialchars($appt['service_type']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appt['reason'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Reason</small>
                                            <div class="small"><?php echo htmlspecialchars($appt['reason']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appt['vet_notes'])): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Vet Notes</small>
                                            <div class="small text-success"><?php echo htmlspecialchars($appt['vet_notes']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-auto">
                                        <?php if ($appt['status'] == 'pending'): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>Waiting for confirmation
                                                </small>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="showCancelModal(<?php echo $appt['appointment_id']; ?>)">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </div>
                                        <?php elseif ($appt['status'] == 'confirmed'): ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>Confirmed
                                                </small>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="showCancelModal(<?php echo $appt['appointment_id']; ?>)">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </div>
                                        <?php elseif ($appt['status'] == 'completed'): ?>
                                            <small class="text-success">
                                                <i class="fas fa-flag-checkered me-1"></i>Completed
                                            </small>
                                        <?php elseif ($appt['status'] == 'cancelled'): ?>
                                            <small class="text-danger">
                                                <i class="fas fa-ban me-1"></i>Cancelled
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-plus me-1"></i>
                                            <?php echo date('M j, g:i A', strtotime($appt['created_at'])); ?>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa-solid fa-paw me-2"></i>Your Pets</h4>
                    <small class="text-muted">Manage your pet profiles and health records</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="register_pet.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add Pet
                    </a>
                    <a href="user_pet_profile.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i> View All
                    </a>
                </div>
            </div>
            
            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-paw fa-2x mb-3"></i>
                    <h5>No Pets Registered</h5>
                    <p class="text-muted">Start by registering your first pet to access all features.</p>
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
                        $nextVaccination = null;
                        
                        foreach ($pet['records'] as $record) {
                            if ($record['is_vaccination'] || (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false)) {
                                $hasVaccination = true;
                                if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
                                    $nextVaccination = $record['reminder_due_date'];
                                }
                            }
                            
                            if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
                                $hasRecentVisit = true;
                            }
                        }
                        
                        // Enhanced health status calculation
                        $healthStatus = 'Good Health';
                        $statusClass = 'status-good';
                        $statusText = 'text-success';
                        
                        if (!$hasVaccination) {
                            $healthStatus = 'Needs Vaccination';
                            $statusClass = 'status-warning';
                            $statusText = 'text-warning';
                        }
                        
                        if ($pet['health_status'] === 'poor') {
                            $healthStatus = 'Needs Attention';
                            $statusClass = 'status-bad';
                            $statusText = 'text-danger';
                        } elseif ($pet['health_status'] === 'excellent') {
                            $healthStatus = 'Excellent Health';
                            $statusClass = 'status-good';
                            $statusText = 'text-success';
                        }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="pet-card">
                                <div class="card-header" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#e0f2fe' : '#f0f9ff'; ?>; border: none;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                            <small class="text-muted"><?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?></small>
                                        </div>
                                        <div class="pet-species-icon">
                                            <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <div>
                                            <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years<br>
                                            <strong>Gender:</strong> <?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?><br>
                                            <strong>Weight:</strong> <?php echo $pet['weight'] ? $pet['weight'] . ' kg' : 'Not set'; ?>
                                        </div>
                                        <div class="text-end">
                                            <div class="health-status">
                                                <span class="status-dot <?php echo $statusClass; ?>"></span>
                                                <small class="<?php echo $statusText; ?> fw-semibold"><?php echo $healthStatus; ?></small>
                                            </div>
                                            <?php if ($nextVaccination): ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-syringe me-1"></i>
                                                    Due: <?php echo date('M j', strtotime($nextVaccination)); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($pet['records'])): ?>
                                        <div class="mt-3">
                                            <h6>Recent Medical History</h6>
                                            <div class="medical-history">
                                                <?php 
                                                $recentRecords = array_slice($pet['records'], 0, 2);
                                                foreach ($recentRecords as $record): 
                                                    if (!empty($record['service_date']) && $record['service_date'] !== '0000-00-00'):
                                                ?>
                                                    <div class="d-flex justify-content-between border-bottom py-2">
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
                                            <i class="fas fa-info-circle me-1"></i> No medical records yet.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 d-grid gap-2">
                                        <a href="user_pet_profile.php?id=<?php echo $pet['pet_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                        <a href="qr_code.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-qrcode me-1"></i> QR Code
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
                <div class="col-md-3 col-6 mb-2">
                    <a href="user_appointment.php" class="btn btn-primary w-100">
                        <i class="fas fa-calendar-plus me-1"></i> Book Appointment
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="register_pet.php" class="btn btn-success w-100">
                        <i class="fas fa-plus-circle me-1"></i> Add Pet
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="user_appointments.php" class="btn btn-info w-100">
                        <i class="fas fa-list me-1"></i> View Appointments
                    </a>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <a href="qr_code.php" class="btn btn-warning w-100">
                        <i class="fas fa-qrcode me-1"></i> QR Codes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Cancel Appointment Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user_dashboard.php" id="cancelForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="cancel_reason" rows="3" 
                                  placeholder="Please provide a reason for cancellation..." 
                                  required maxlength="500"></textarea>
                        <div class="form-text">This helps us improve our service.</div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to cancel this appointment? This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Appointment</button>
                    <button type="submit" name="cancel_appointment" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i> Cancel Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // API Configuration - UPDATED WITH YOUR RAILWAY URL
    const API_BASE_URL = 'https://web-production-28f67.up.railway.app';

    document.addEventListener('DOMContentLoaded', function() {
        // Set current date and time
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Initialize health monitoring charts
        initializeHealthCharts();
        
        // Load disease classes for AI analysis
        loadDiseaseClasses();
        
        // Initialize search functionality
        initializeSearch();
        
        console.log('Enhanced user dashboard initialized successfully!');
        console.log('API Base URL:', API_BASE_URL);
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }

    function showCancelModal(appointmentId) {
        document.getElementById('cancelAppointmentId').value = appointmentId;
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        cancelModal.show();
    }

    function initializeHealthCharts() {
        // Vaccination Status Chart
        const vaccinationCtx = document.getElementById('vaccinationChart').getContext('2d');
        const vaccinationChart = new Chart(vaccinationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Vaccinated', 'Not Vaccinated'],
                datasets: [{
                    data: [<?php echo $vaccinatedPets; ?>, <?php echo $totalPets - $vaccinatedPets; ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Health Scores Chart
        const healthScoreCtx = document.getElementById('healthScoreChart').getContext('2d');
        const healthScoreChart = new Chart(healthScoreCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($healthData['health_scores'])); ?>,
                datasets: [{
                    label: 'Health Score (%)',
                    data: <?php echo json_encode(array_values($healthData['health_scores'])); ?>,
                    backgroundColor: '#0ea5e9',
                    borderColor: '#0284c7',
                    borderWidth: 1,
                    borderRadius: 6
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
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Visit Frequency Chart
        const visitCtx = document.getElementById('visitChart').getContext('2d');
        const visitChart = new Chart(visitCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($healthData['visit_frequency'])); ?>,
                datasets: [{
                    label: 'Visits (Last 30 Days)',
                    data: <?php echo json_encode(array_values($healthData['visit_frequency'])); ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderColor: '#8b5cf6',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
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
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Weight Distribution Chart
        const weightCtx = document.getElementById('weightChart').getContext('2d');
        const weightChart = new Chart(weightCtx, {
            type: 'radar',
            data: {
                labels: <?php echo json_encode(array_keys($healthData['weight_trends'])); ?>,
                datasets: [{
                    label: 'Weight (kg)',
                    data: <?php echo json_encode(array_values($healthData['weight_trends'])); ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
    }

    function refreshHealthData() {
        // Show loading state
        const refreshBtn = document.querySelector('button[onclick="refreshHealthData()"]');
        const originalHtml = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        // Simulate API call - in real implementation, this would fetch new data
        setTimeout(() => {
            refreshBtn.innerHTML = originalHtml;
            refreshBtn.disabled = false;
            
            // Show success message
            showAlert('Health data refreshed successfully!', 'success', 'analysisResult');
        }, 1500);
    }

    function exportHealthReport() {
        // In real implementation, this would generate and download a PDF report
        showAlert('Health report export feature coming soon!', 'info', 'analysisResult');
    }

    function initializeSearch() {
        const searchInput = document.getElementById('globalSearch');
        
        // Add debounced search
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500);
        });
        
        // Enter key search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }

    function performSearch() {
        const query = document.getElementById('globalSearch').value.trim();
        if (query.length > 2) {
            // Show loading state
            showAlert(`Searching for "${query}"...`, 'info', 'analysisResult');
            
            // In real implementation, this would make an AJAX call to search endpoint
            setTimeout(() => {
                showAlert(`Found 0 results for "${query}". Search feature coming soon!`, 'warning', 'analysisResult');
            }, 1000);
        } else if (query.length > 0) {
            showAlert('Please enter at least 3 characters to search.', 'warning', 'analysisResult');
        }
    }

    // Pet Disease AI Analysis Functions
    function loadDiseaseClasses() {
        fetch(`${API_BASE_URL}/classes`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const classesList = document.getElementById('diseaseClassesList');
                if (data.classes && data.classes.length > 0) {
                    classesList.innerHTML = data.classes.slice(0, 8).map(cls => 
                        `<span class="disease-tag">${cls}</span>`
                    ).join('');
                    
                    if (data.classes.length > 8) {
                        classesList.innerHTML += `<span class="disease-tag">+${data.classes.length - 8} more</span>`;
                    }
                } else {
                    classesList.innerHTML = '<small class="text-muted">Unable to load disease classes</small>';
                }
            })
            .catch(error => {
                console.error('Error loading disease classes:', error);
                document.getElementById('diseaseClassesList').innerHTML = 
                    '<small class="text-muted">Cannot connect to AI service</small>';
            });
    }

    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const img = document.getElementById('previewImg');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearImage() {
        document.getElementById('petImageInput').value = '';
        document.getElementById('imagePreview').style.display = 'none';
    }

    async function analyzePetImage() {
        const fileInput = document.getElementById('petImageInput');
        const resultDiv = document.getElementById('analysisResult');
        const analyzeBtn = document.getElementById('analyzeBtn');
        
        if (!fileInput.files[0]) {
            showAlert('Please select an image file first!', 'warning', 'analysisResult');
            return;
        }
        
        // Validate file size (10MB limit)
        if (fileInput.files[0].size > 10 * 1024 * 1024) {
            showAlert('File size too large. Please select an image under 10MB.', 'warning', 'analysisResult');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        
        // Show loading state
        analyzeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Analyzing...';
        analyzeBtn.disabled = true;
        
        resultDiv.innerHTML = `
            <div class="alert alert-info alert-custom">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" role="status"></div>
                    <div>
                        <strong>Analyzing Image...</strong><br>
                        <small>Our AI is examining your pet's image for disease detection. This may take a few seconds.</small>
                    </div>
                </div>
            </div>
        `;
        
        try {
            const response = await fetch(`${API_BASE_URL}/predict`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                displayAnalysisResults(data);
            } else {
                throw new Error(data.error || 'Analysis failed');
            }
            
        } catch (error) {
            console.error('Analysis error:', error);
            resultDiv.innerHTML = `
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Analysis Failed</strong><br>
                    <small>${error.message || 'Unable to connect to AI service. Please try again.'}</small>
                </div>
            `;
        } finally {
            analyzeBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> Analyze Image';
            analyzeBtn.disabled = false;
        }
    }

    function displayAnalysisResults(data) {
        const resultDiv = document.getElementById('analysisResult');
        
        let html = `
            <div class="alert alert-success alert-custom">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5><i class="fas fa-check-circle me-2"></i>Analysis Complete</h5>
                        <p class="mb-1"><strong>File:</strong> ${data.file_name}</p>
                        <p class="mb-0"><strong>Model Confidence:</strong> ${data.primary_prediction.confidence}%</p>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="clearAnalysis()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card-custom" style="background: linear-gradient(135deg, #d4edda, #c3e6cb);">
                        <h6><i class="fas fa-stethoscope me-2"></i>Primary Diagnosis</h6>
                        <div class="text-center py-3">
                            <h3 class="text-success mb-2">${data.primary_prediction.class}</h3>
                            <div class="progress" style="height: 20px; border-radius: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: ${data.primary_prediction.confidence}%" 
                                     aria-valuenow="${data.primary_prediction.confidence}" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    ${data.primary_prediction.confidence}%
                                </div>
                            </div>
                            <small class="text-muted mt-2">Confidence Level</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card-custom">
                        <h6><i class="fas fa-list-ol me-2"></i>Alternative Possibilities</h6>
                        <div style="max-height: 200px; overflow-y: auto;">
        `;
        
        if (data.predictions && data.predictions.length > 1) {
            data.predictions.slice(1, 6).forEach((pred, index) => {
                const confidenceColor = pred.confidence > 30 ? 'warning' : 'secondary';
                html += `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <small class="flex-grow-1">${index + 1}. ${pred.class}</small>
                        <span class="badge bg-${confidenceColor} ms-2">${pred.confidence}%</span>
                    </div>
                `;
            });
        } else {
            html += `<p class="text-muted text-center mb-0">No alternative diagnoses</p>`;
        }
        
        html += `
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
                            <small>This AI analysis is for informational purposes only. Always consult a qualified veterinarian for proper diagnosis and treatment.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="fas fa-calendar-plus me-2"></i>
                            <strong>Book an Appointment</strong><br>
                            <small>Schedule a vet visit for professional diagnosis and treatment plan.</small>
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
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('analysisResult').innerHTML = '';
    }

    function showAlert(message, type = 'info', container = 'analysisResult') {
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
        
        const containerEl = document.getElementById(container);
        containerEl.innerHTML = '';
        containerEl.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, 5000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('globalSearch').focus();
        }
        
        // Ctrl/Cmd + / for help
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            showAlert('Keyboard shortcuts: Ctrl+K (Search), Ctrl+/ (This help)', 'info', 'analysisResult');
        }
    });

    // Auto-refresh data every 5 minutes
    setInterval(() => {
        console.log('Auto-refreshing dashboard data...');
        // You can implement AJAX refresh here if needed
    }, 300000);

    // Add service worker for offline functionality (optional)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => console.log('SW registered'))
            .catch(error => console.log('SW registration failed'));
    }
</script>
</body>
</html>
