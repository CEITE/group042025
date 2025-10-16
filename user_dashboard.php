<?php
session_start();
include("conn.php");

// ✅ 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ 2. Fetch logged-in user info
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

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

// ✅ 5. Dashboard statistics
$totalPets = count($pets);
$vaccinatedPets = 0;
$upcomingReminders = 0;
$recentVisits = 0;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// ✅ 6. PREDICTION & ALERT SYSTEM
$vaccinationAlerts = [];
$healthPredictions = [];
$upcomingVaccinations = [];

foreach ($pets as $pet) {
    $hasVaccination = false;
    $lastVaccinationDate = null;
    $petAlerts = [];
    $petPredictions = [];
    
    foreach ($pet['records'] as $record) {
        // Check if service type indicates vaccination
        if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
            $hasVaccination = true;
            $lastVaccinationDate = $record['service_date'];
        }
        
        // Check for upcoming reminders
        if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
            $upcomingReminders++;
            
            // Add to vaccination alerts if it's a vaccination reminder
            if (stripos($record['reminder_description'] ?? '', 'vaccin') !== false) {
                $petAlerts[] = [
                    'type' => 'vaccination',
                    'due_date' => $record['reminder_due_date'],
                    'description' => $record['reminder_description'],
                    'priority' => 'high'
                ];
                
                $upcomingVaccinations[] = [
                    'pet_name' => $pet['pet_name'],
                    'due_date' => $record['reminder_due_date'],
                    'description' => $record['reminder_description']
                ];
            }
        }
        
        // Check for recent visits
        if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
            $recentVisits++;
        }
        
        // Weight-based predictions
        if (!empty($record['weight']) && !empty($record['weight_date'])) {
            $currentWeight = $record['weight'];
            $species = strtolower($pet['species']);
            $breed = strtolower($pet['breed']);
            
            // Simple weight prediction algorithm
            if ($species === 'dog') {
                $idealWeightRanges = [
                    'small' => ['min' => 2, 'max' => 10],
                    'medium' => ['min' => 11, 'max' => 25],
                    'large' => ['min' => 26, 'max' => 45]
                ];
                
                // Determine breed size (simplified)
                $size = 'medium';
                if (strpos($breed, 'chihuahua') !== false || strpos($breed, 'poodle') !== false) {
                    $size = 'small';
                } elseif (strpos($breed, 'german shepherd') !== false || strpos($breed, 'labrador') !== false) {
                    $size = 'large';
                }
                
                $idealMin = $idealWeightRanges[$size]['min'];
                $idealMax = $idealWeightRanges[$size]['max'];
                
                if ($currentWeight < $idealMin) {
                    $petPredictions[] = [
                        'type' => 'weight',
                        'message' => "{$pet['pet_name']} is underweight for their breed size",
                        'priority' => 'medium',
                        'recommendation' => 'Consult vet about nutrition plan'
                    ];
                } elseif ($currentWeight > $idealMax) {
                    $petPredictions[] = [
                        'type' => 'weight',
                        'message' => "{$pet['pet_name']} is overweight for their breed size",
                        'priority' => 'medium',
                        'recommendation' => 'Consider diet adjustment and exercise'
                    ];
                }
            }
        }
    }
    
    // Vaccination status prediction
    if ($lastVaccinationDate) {
        $lastVax = new DateTime($lastVaccinationDate);
        $now = new DateTime();
        $interval = $lastVax->diff($now);
        $monthsSinceVax = $interval->y * 12 + $interval->m;
        
        // Predict next vaccination due (typically 1-3 years for most vaccines)
        if ($monthsSinceVax >= 12) {
            $nextDue = clone $lastVax;
            $nextDue->modify('+1 year');
            
            $petPredictions[] = [
                'type' => 'vaccination',
                'message' => "{$pet['pet_name']} may be due for annual vaccination soon",
                'priority' => 'high',
                'recommendation' => 'Schedule vaccination appointment',
                'due_date' => $nextDue->format('Y-m-d')
            ];
        }
    } elseif (!$hasVaccination) {
        $petAlerts[] = [
            'type' => 'vaccination',
            'due_date' => date('Y-m-d'),
            'description' => 'No vaccination records found',
            'priority' => 'high'
        ];
    }
    
    // Age-based health predictions
    $age = floatval($pet['age']);
    if ($age >= 7) {
        $petPredictions[] = [
            'type' => 'age',
            'message' => "{$pet['pet_name']} is a senior pet",
            'priority' => 'low',
            'recommendation' => 'Consider more frequent health checkups'
        ];
    }
    
    if ($hasVaccination) {
        $vaccinatedPets++;
    }
    
    // Add pet-specific alerts and predictions to global arrays
    if (!empty($petAlerts)) {
        $vaccinationAlerts = array_merge($vaccinationAlerts, $petAlerts);
    }
    if (!empty($petPredictions)) {
        $healthPredictions = array_merge($healthPredictions, $petPredictions);
    }
}

// Sort alerts by priority and date
usort($vaccinationAlerts, function($a, $b) {
    $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
    if ($a['priority'] !== $b['priority']) {
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    }
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});

// Sort predictions by priority
usort($healthPredictions, function($a, $b) {
    $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
    return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
});

// ✅ 7. Fetch all users (for admin view)
$allUsers = [];
if ($user['role'] === 'admin') {
    $usersQuery = "SELECT user_id, name, email, role, date_created FROM users ORDER BY date_created DESC";
    $usersResult = $conn->query($usersQuery);
    if ($usersResult) {
        while ($userRow = $usersResult->fetch_assoc()) {
            $allUsers[] = $userRow;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetMedQR - Pet Medical Records & QR Generator</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #bf3b78;
            --primary-light: #ffd6e7;
            --primary-dark: #8c2859;
            --secondary: #4a6cf7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #2a2e34;
            --light: #f8f9fa;
            --gray: #6c757d;
            --radius: 16px;
            --radius-sm: 8px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar - Same as before, keeping it concise */
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        /* Header - Same as before */
        
        /* Stats Cards - Same as before */
        
        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow);
        }
        
        .alert-banner.warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .alert-banner.info {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .alert-banner i {
            font-size: 1.8rem;
            margin-right: 1rem;
        }
        
        .alert-content h4 {
            margin: 0 0 0.25rem;
            font-weight: 700;
        }
        
        .alert-content p {
            margin: 0;
            opacity: 0.9;
        }
        
        /* Wellness Recommendations */
        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        .recommendation-card {
            border-radius: var(--radius-sm);
            padding: 1.25rem;
            border-left: 4px solid;
            transition: var(--transition);
            background: var(--light);
        }
        
        .recommendation-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .recommendation-card.high {
            border-left-color: var(--danger);
        }
        
        .recommendation-card.medium {
            border-left-color: var(--warning);
        }
        
        .recommendation-card.low {
            border-left-color: var(--success);
        }
        
        .recommendation-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .recommendation-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .recommendation-card.high .recommendation-icon {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .recommendation-card.medium .recommendation-icon {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning);
        }
        
        .recommendation-card.low .recommendation-icon {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .recommendation-title {
            font-weight: 700;
            margin: 0 0 0.25rem;
            font-size: 1.05rem;
        }
        
        .recommendation-pet {
            font-size: 0.85rem;
            color: var(--gray);
            margin: 0;
        }
        
        .recommendation-content {
            margin-bottom: 1rem;
        }
        
        .recommendation-message {
            margin-bottom: 0.75rem;
            color: var(--dark);
        }
        
        .recommendation-action {
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .recommendation-action i {
            margin-left: 0.5rem;
            transition: var(--transition);
        }
        
        .recommendation-action:hover i {
            transform: translateX(3px);
        }
        
        /* Vaccination Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            background: var(--light);
            border-radius: var(--radius-sm);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .timeline-item.due::before {
            background: var(--danger);
        }
        
        .timeline-item.upcoming::before {
            background: var(--warning);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .timeline-pet {
            font-weight: 700;
            color: var(--dark);
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--danger);
        }
        
        .timeline-item.upcoming .timeline-date {
            color: var(--warning);
        }
        
        .timeline-vaccines {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .vaccine-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .timeline-message {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Section Styles - Same as before */
        
        /* Rest of your existing CSS styles remain the same */
        
        /* Add any missing styles from your original CSS here */
        
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar - Same as before -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-paw"></i>
                <h2>PetMedQR</h2>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="https://i.pravatar.cc/150?u=<?php echo urlencode($user['email']); ?>" alt="User Avatar">
                </div>
                <h3 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                <p class="user-role"><?php echo htmlspecialchars($user['role']); ?></p>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a href="user_dashboard.php" class="nav-link active">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="user_pet_profile.php" class="nav-link">
                        <i class="fas fa-paw"></i>
                        <span>My Pets</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="qr_code.php" class="nav-link">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Codes</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="register_pet.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Register Pet</span>
                    </a>
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <div class="nav-item">
                    <a href="user_management.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Good Morning, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
                    <p>Here's your pet health overview for today</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search pets, vaccines, vets...">
                    </div>
                    
                    <div class="date-display">
                        <div class="current-date">Monday, October 16, 2023</div>
                        <div class="current-time">10:30 AM</div>
                    </div>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <div class="stat-value"><?php echo $totalPets; ?></div>
                    <div class="stat-label">Registered Pets</div>
                </div>
                
                <div class="stat-card vaccinated">
                    <div class="stat-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div class="stat-value"><?php echo $vaccinatedPets; ?></div>
                    <div class="stat-label">Vaccinated Pets</div>
                </div>
                
                <div class="stat-card vaccination-due">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-value"><?php echo $upcomingReminders; ?></div>
                    <div class="stat-label">Upcoming Reminders</div>
                </div>
                
                <div class="stat-card recent-visits">
                    <div class="stat-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-value"><?php echo $recentVisits; ?></div>
                    <div class="stat-label">Recent Visits</div>
                </div>
            </div>
            
            <!-- ALERT BANNERS -->
            <?php if (!empty($vaccinationAlerts)): ?>
            <div class="alert-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h4>Vaccination Alert!</h4>
                    <p>You have <?php echo count($vaccinationAlerts); ?> vaccination-related alerts that need attention.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($healthPredictions)): ?>
            <div class="alert-banner info">
                <i class="fas fa-heartbeat"></i>
                <div class="alert-content">
                    <h4>Health Insights</h4>
                    <p>We've generated <?php echo count($healthPredictions); ?> personalized health predictions for your pets.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Wellness Recommendations & Predictions -->
            <?php if (!empty($healthPredictions)): ?>
            <div class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title"><i class="fas fa-heartbeat"></i> Health & Wellness Predictions</h2>
                        <p class="section-subtitle">AI-powered insights for your pets' wellbeing</p>
                    </div>
                </div>
                
                <div class="recommendation-grid">
                    <?php foreach (array_slice($healthPredictions, 0, 6) as $prediction): ?>
                    <div class="recommendation-card <?php echo $prediction['priority']; ?>">
                        <div class="recommendation-header">
                            <div class="recommendation-icon">
                                <i class="fas 
                                    <?php 
                                    switch($prediction['type']) {
                                        case 'vaccination': echo 'fa-syringe'; break;
                                        case 'weight': echo 'fa-weight-scale'; break;
                                        case 'age': echo 'fa-birthday-cake'; break;
                                        default: echo 'fa-stethoscope';
                                    }
                                    ?>
                                "></i>
                            </div>
                            <div>
                                <h3 class="recommendation-title">
                                    <?php 
                                    switch($prediction['priority']) {
                                        case 'high': echo 'Priority Alert'; break;
                                        case 'medium': echo 'Health Notice'; break;
                                        case 'low': echo 'Wellness Tip'; break;
                                    }
                                    ?>
                                </h3>
                                <p class="recommendation-pet">
                                    <?php 
                                    // Extract pet name from message
                                    preg_match('/^(.*?)\s/', $prediction['message'], $matches);
                                    echo $matches[1] ?? 'General';
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="recommendation-content">
                            <p class="recommendation-message"><?php echo $prediction['message']; ?></p>
                            <p class="recommendation-message"><strong>Recommendation:</strong> <?php echo $prediction['recommendation']; ?></p>
                            <?php if (!empty($prediction['due_date'])): ?>
                            <p class="recommendation-message"><strong>Due:</strong> <?php echo date('M j, Y', strtotime($prediction['due_date'])); ?></p>
                            <?php endif; ?>
                            <a href="register_pet.php" class="recommendation-action">
                                Take action
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Vaccination Timeline -->
            <?php if (!empty($upcomingVaccinations)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Vaccination Schedule</h2>
                </div>
                
                <div class="timeline">
                    <?php foreach ($upcomingVaccinations as $vaccination): ?>
                    <?php
                    $dueDate = new DateTime($vaccination['due_date']);
                    $now = new DateTime();
                    $isOverdue = $dueDate < $now;
                    ?>
                    <div class="timeline-item <?php echo $isOverdue ? 'due' : 'upcoming'; ?>">
                        <div class="timeline-header">
                            <div class="timeline-pet"><?php echo $vaccination['pet_name']; ?></div>
                            <div class="timeline-date">
                                <?php echo $isOverdue ? 'Overdue' : 'Due'; ?>: <?php echo date('M j, Y', strtotime($vaccination['due_date'])); ?>
                            </div>
                        </div>
                        <div class="timeline-vaccines">
                            <span class="vaccine-badge">Vaccination</span>
                        </div>
                        <p class="timeline-message"><?php echo $vaccination['description']; ?></p>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($upcomingVaccinations) && !empty($pets)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                        <p class="text-muted">All vaccinations are up to date!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- User Management Section (Admin Only) -->
            <?php if ($user['role'] === 'admin' && !empty($allUsers)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-users"></i> User Management</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $userRow): ?>
                            <tr>
                                <td><?php echo $userRow['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($userRow['name']); ?></td>
                                <td><?php echo htmlspecialchars($userRow['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $userRow['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                        <?php echo ucfirst($userRow['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($userRow['date_created'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline text-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="qr-section">
                <h3>Manage Your Pets with Ease</h3>
                <p>Register your pets to track their medical records, generate QR codes, and receive personalized health recommendations.</p>
                <a href="register_pet.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Add New Pet
                </a>
            </div>
            
            <!-- Pets Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-paw"></i> Your Pets</h2>
                    <a href="register_pet.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Add Pet
                    </a>
                </div>
                
                <?php if (empty($pets)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-paw fa-4x text-muted mb-3"></i>
                        <h4>No Pets Registered</h4>
                        <p class="text-muted">You haven't added any pets yet. Register your first pet to get started!</p>
                        <a href="register_pet.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-1"></i> Add Your First Pet
                        </a>
                    </div>
                <?php else: ?>
                    <div class="pets-grid">
                        <?php foreach ($pets as $pet): ?>
                            <!-- Pet cards remain the same as before -->
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

    <!-- JavaScript remains the same as before -->
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
            
            // Auto-close alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Add animation to cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe cards for animation
            document.querySelectorAll('.stat-card, .pet-card').forEach(card => {
                card.style.opacity = 0;
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });

        // Update date and time display
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.querySelector('.current-date').textContent = now.toLocaleDateString('en-US', options);
            document.querySelector('.current-time').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
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
            qrData += `\nOwner: <?php echo htmlspecialchars($user['name']); ?>`;
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

        // Search functionality
        document.addEventListener('keydown', function(e) {
            // Ctrl+K for search focus (common shortcut)
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-box input');
                if (searchInput) searchInput.focus();
            }
        });

        // Export functions for global access (for debugging)
        window.PetMedQR = {
            generateQRCode,
            showQRModal,
            downloadQRCode,
            toggleQrData
        };

        console.log('PetMedQR Dashboard initialized successfully!');
    </script>
</body>
</html>
