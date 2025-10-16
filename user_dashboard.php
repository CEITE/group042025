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

// ✅ 5. Enhanced Dashboard Statistics with Vaccination Prediction
$totalPets = count($pets);
$vaccinatedPets = 0;
$upcomingReminders = 0;
$recentVisits = 0;
$petsNeedingVaccination = 0;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// Store vaccination predictions and wellness recommendations
$vaccinationPredictions = [];
$wellnessRecommendations = [];

foreach ($pets as $petId => $pet) {
    $hasVaccination = false;
    $lastVaccinationDate = null;
    $petVaccinationDue = false;
    $petRecommendations = [];
    
    foreach ($pet['records'] as $record) {
        // Check if service type indicates vaccination
        if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
            $hasVaccination = true;
            if ($record['service_date'] && (!$lastVaccinationDate || $record['service_date'] > $lastVaccinationDate)) {
                $lastVaccinationDate = $record['service_date'];
            }
        }
        
        // Check for upcoming reminders
        if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
            $upcomingReminders++;
        }
        
        // Check for recent visits
        if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
            $recentVisits++;
        }
    }
    
    // Predict vaccination schedule
    $vaccinationPrediction = predictVaccinationSchedule($pet, $lastVaccinationDate);
    $vaccinationPredictions[$petId] = $vaccinationPrediction;
    
    if ($vaccinationPrediction['needs_vaccination']) {
        $petsNeedingVaccination++;
        $petVaccinationDue = true;
    }
    
    // Generate wellness recommendations
    $wellnessRecommendations[$petId] = generateWellnessRecommendations($pet, $vaccinationPrediction);
    
    if ($hasVaccination) {
        $vaccinatedPets++;
    }
}

// ✅ 6. Vaccination Prediction Algorithm
function predictVaccinationSchedule($pet, $lastVaccinationDate) {
    $prediction = [
        'needs_vaccination' => false,
        'next_vaccination_date' => null,
        'recommended_vaccines' => [],
        'risk_level' => 'low',
        'days_until_due' => null,
        'message' => ''
    ];
    
    $species = strtolower($pet['species']);
    $age = floatval($pet['age']);
    $currentDate = new DateTime();
    
    // Core vaccination schedule based on species and age
    $coreVaccines = [];
    
    if ($species === 'dog') {
        $coreVaccines = [
            'Rabies' => 365, // Annual
            'DHPP' => 365,   // Annual
            'Bordetella' => 180, // Every 6 months
            'Leptospirosis' => 365
        ];
        
        // Puppy schedule
        if ($age < 1) {
            $coreVaccines['Puppy Series'] = 30; // Every 3-4 weeks until 16 weeks
        }
    } elseif ($species === 'cat') {
        $coreVaccines = [
            'Rabies' => 365, // Annual
            'FVRCP' => 365,  // Annual
            'Feline Leukemia' => 365
        ];
        
        // Kitten schedule
        if ($age < 1) {
            $coreVaccines['Kitten Series'] = 30; // Every 3-4 weeks until 16 weeks
        }
    }
    
    // If no vaccination history, pet needs initial vaccination
    if (!$lastVaccinationDate) {
        $prediction['needs_vaccination'] = true;
        $prediction['recommended_vaccines'] = array_keys($coreVaccines);
        $prediction['risk_level'] = 'high';
        $prediction['message'] = "Your {$species} needs initial vaccination series";
        return $prediction;
    }
    
    $lastVaxDate = new DateTime($lastVaccinationDate);
    $daysSinceLastVax = $currentDate->diff($lastVaxDate)->days;
    
    // Check which vaccines are due
    $dueVaccines = [];
    foreach ($coreVaccines as $vaccine => $frequencyDays) {
        if ($daysSinceLastVax >= $frequencyDays) {
            $dueVaccines[] = $vaccine;
        }
    }
    
    if (!empty($dueVaccines)) {
        $prediction['needs_vaccination'] = true;
        $prediction['recommended_vaccines'] = $dueVaccines;
        $prediction['risk_level'] = count($dueVaccines) > 2 ? 'high' : 'medium';
        
        // Calculate next due date (soonest overdue vaccine)
        $soonestDueDays = min(array_map(function($vaccine) use ($coreVaccines, $daysSinceLastVax) {
            return $coreVaccines[$vaccine] - $daysSinceLastVax;
        }, $dueVaccines));
        
        $prediction['days_until_due'] = abs($soonestDueDays);
        $nextDueDate = clone $currentDate;
        $nextDueDate->modify("+{$prediction['days_until_due']} days");
        $prediction['next_vaccination_date'] = $nextDueDate->format('Y-m-d');
        
        $prediction['message'] = "Vaccination due for: " . implode(', ', $dueVaccines);
    } else {
        // Find next vaccination date
        $nextVaxDays = min(array_map(function($frequency) use ($daysSinceLastVax) {
            return $frequency - $daysSinceLastVax;
        }, $coreVaccines));
        
        $prediction['days_until_due'] = $nextVaxDays;
        $nextDueDate = clone $currentDate;
        $nextDueDate->modify("+{$nextVaxDays} days");
        $prediction['next_vaccination_date'] = $nextDueDate->format('Y-m-d');
        $prediction['message'] = "Next vaccination due in {$nextVaxDays} days";
    }
    
    return $prediction;
}

// ✅ 7. Wellness Recommendations Algorithm
function generateWellnessRecommendations($pet, $vaccinationPrediction) {
    $recommendations = [];
    $species = strtolower($pet['species']);
    $age = floatval($pet['age']);
    $weight = floatval($pet['weight']);
    
    // Vaccination recommendations
    if ($vaccinationPrediction['needs_vaccination']) {
        $riskColor = $vaccinationPrediction['risk_level'] === 'high' ? 'danger' : 
                    ($vaccinationPrediction['risk_level'] === 'medium' ? 'warning' : 'info');
        
        $recommendations[] = [
            'type' => 'vaccination',
            'priority' => 'high',
            'title' => 'Vaccination Required',
            'message' => $vaccinationPrediction['message'],
            'action' => 'Schedule vet appointment',
            'icon' => 'fa-syringe',
            'color' => $riskColor
        ];
    }
    
    // Age-based recommendations
    if ($age < 1) {
        $recommendations[] = [
            'type' => 'developmental',
            'priority' => 'medium',
            'title' => 'Young Pet Care',
            'message' => "Your {$species} is still developing. Regular checkups are essential.",
            'action' => 'Monthly wellness check',
            'icon' => 'fa-baby',
            'color' => 'info'
        ];
    } elseif ($age > 7) {
        $recommendations[] = [
            'type' => 'senior_care',
            'priority' => 'medium',
            'title' => 'Senior Pet Care',
            'message' => "Senior pets need more frequent health monitoring.",
            'action' => 'Bi-annual senior screening',
            'icon' => 'fa-heart',
            'color' => 'warning'
        ];
    }
    
    // Weight monitoring
    if ($weight) {
        $idealWeightRange = $species === 'dog' ? [5, 40] : [3, 7]; // Simplified ranges
        if ($weight < $idealWeightRange[0] || $weight > $idealWeightRange[1]) {
            $recommendations[] = [
                'type' => 'nutrition',
                'priority' => 'medium',
                'title' => 'Weight Management',
                'message' => "Your pet's weight may need adjustment for optimal health.",
                'action' => 'Consult vet about diet',
                'icon' => 'fa-weight-scale',
                'color' => 'warning'
            ];
        }
    }
    
    // Seasonal recommendations
    $currentMonth = date('n');
    if ($currentMonth >= 3 && $currentMonth <= 6) {
        $recommendations[] = [
            'type' => 'seasonal',
            'priority' => 'low',
            'title' => 'Seasonal Alert',
            'message' => "Warmer months increase risk of parasites and heat-related issues.",
            'action' => 'Update parasite prevention',
            'icon' => 'fa-sun',
            'color' => 'info'
        ];
    }
    
    // General wellness
    $recommendations[] = [
        'type' => 'general',
        'priority' => 'low',
        'title' => 'Regular Exercise',
        'message' => "Daily exercise maintains physical and mental health.",
        'action' => '30 min daily activity',
        'icon' => 'fa-person-running',
        'color' => 'success'
    ];
    
    // Sort by priority
    usort($recommendations, function($a, $b) {
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    });
    
    return array_slice($recommendations, 0, 5); // Return top 5 recommendations
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
            --primary: #4a6cf7;
            --primary-light: #e8f0fe;
            --secondary: #ff6b9d;
            --secondary-light: #ffd6e7;
            --success: #2ecc71;
            --success-light: #eafaf1;
            --warning: #f39c12;
            --warning-light: #fef5e7;
            --danger: #e74c3c;
            --danger-light: #fdedec;
            --info: #3498db;
            --info-light: #e8f4fd;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: var(--dark);
        }
        
        /* Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, var(--primary), #3a57d8);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .brand {
            padding: 1.5rem 1.5rem 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .brand i {
            margin-right: 0.5rem;
            color: var(--secondary-light);
        }
        
        .profile {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            margin-bottom: 0.75rem;
        }
        
        .profile h6 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary);
        }
        
        .sidebar a.logout {
            margin-top: auto;
            color: rgba(255,255,255,0.7);
        }
        
        .sidebar a.logout:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .icon {
            width: 24px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 0;
            transition: var(--transition);
        }
        
        /* Topbar */
        .topbar {
            background: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-bottom: 1px solid #eaeaea;
        }
        
        .topbar h5 {
            font-weight: 600;
            color: var(--dark);
        }
        
        .topbar .text-end {
            text-align: right;
        }
        
        .topbar strong {
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .topbar small {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
        }
        
        /* Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: none;
            transition: var(--transition);
            height: 100%;
            text-align: center;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .stats-card h6 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stats-card h4 {
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }
        
        /* Cards */
        .card-custom {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .card-custom h4, .card-custom h5 {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Pet Cards */
        .pet-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .pet-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .pet-card-header {
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pet-card-body {
            padding: 1.25rem;
        }
        
        .pet-species-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        /* QR Code */
        .qr-preview {
            width: 80px;
            height: 80px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            padding: 5px;
            background: white;
        }
        
        /* Health Status */
        .health-status {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-good { background: var(--success); }
        .status-warning { background: var(--warning); }
        .status-bad { background: var(--danger); }
        
        /* Alerts */
        .alert-custom {
            border-radius: var(--radius);
            border: none;
            box-shadow: var(--shadow);
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: #3a57d8;
            border-color: #3a57d8;
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        /* Debug Info */
        .debug-info {
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--warning);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar .brand span, 
            .sidebar a span:not(.icon),
            .profile h6, 
            .profile small {
                display: none;
            }
            
            .profile img {
                width: 40px;
                height: 40px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .topbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .content-area {
                padding: 1rem;
            }
        }
        
        /* Wellness Section */
        .wellness-section {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .recommendation-card {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }
        
        .recommendation-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .recommendation-high { border-left-color: var(--danger); }
        .recommendation-medium { border-left-color: var(--warning); }
        .recommendation-low { border-left-color: var(--success); }
        
        /* Vaccination Timeline */
        .vaccination-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .vaccination-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .timeline-item.due::before { background: var(--danger); }
        .timeline-item.upcoming::before { background: var(--warning); }
        
        .prediction-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Search Bar */
        .search-container {
            position: relative;
        }
        
        .search-container .form-control {
            border-radius: 20px;
            padding-left: 2.5rem;
        }
        
        .search-container .fa-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-paw"></i> <span>PetMedQR</span></div>
        <div class="profile">
            <img src="https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>" alt="User">
            <h6><?php echo htmlspecialchars($user['name']); ?></h6>
            <small><?php echo htmlspecialchars($user['role']); ?></small>
        </div>
        <a href="user_dashboard.php" class="active">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> <span>Dashboard</span>
        </a>
        <a href="user_pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> <span>My Pets</span>
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> <span>QR Codes</span>
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> <span>Register Pet</span>
        </a>
        <a href="#">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> <span>Settings</span>
        </a>
        <a href="logout.php" class="logout">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> <span>Logout</span>
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-1">Good Morning, <span id="ownerName"><?php echo htmlspecialchars($user['name']); ?></span> 👋</h5>
                <small class="text-muted">Here's your pet health overview</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search pet, vaccine, vet..." class="form-control">
                </div>
                <div class="text-end">
                    <strong id="currentDate"></strong><br>
                    <small id="currentTime"></small>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
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

            <!-- Debug Info -->
            <div class="debug-info">
                <strong>Debug Info:</strong> 
                User ID: <?php echo $user_id; ?> | 
                Total Pets: <?php echo $totalPets; ?> | 
                Pets Needing Vaccination: <?php echo $petsNeedingVaccination; ?> |
                Total Records: <?php 
                    $totalRecords = 0;
                    foreach ($pets as $pet) {
                        $totalRecords += count($pet['records']);
                    }
                    echo $totalRecords;
                ?>
            </div>

            <!-- Stats Cards -->
            <div class="row stats-row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <i class="fa-solid fa-paw text-primary"></i>
                        <h6>Registered Pets</h6>
                        <h4 id="totalPets"><?php echo $totalPets; ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <i class="fa-solid fa-syringe text-success"></i>
                        <h6>Vaccinated Pets</h6>
                        <h4 id="vaccinatedPets"><?php echo $vaccinatedPets; ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <i class="fa-solid fa-bell text-warning"></i>
                        <h6>Vaccination Due</h6>
                        <h4 id="upcomingVaccines"><?php echo $petsNeedingVaccination; ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <i class="fa-solid fa-stethoscope text-info"></i>
                        <h6>Recent Visits</h6>
                        <h4 id="recentVisits"><?php echo $recentVisits; ?></h4>
                    </div>
                </div>
            </div>

            <!-- VACCINATION ALERTS -->
            <?php if ($petsNeedingVaccination > 0): ?>
                <div class="alert alert-warning alert-custom alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Vaccination Alert!</h6>
                            <p class="mb-0"><?php echo $petsNeedingVaccination; ?> of your pets need vaccination updates.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card-custom text-center">
                <h5><i class="fa-solid fa-paw me-2"></i>Manage Your Pets</h5>
                <p class="text-muted mb-3">Register your pets to track their medical records and generate QR codes</p>
                <a href="register_pet.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus-circle me-1"></i> Add New Pet
                </a>
            </div>

            <!-- Pets Section -->
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fa-solid fa-paw me-2"></i>Your Pets & Medical Records</h4>
                    <a href="register_pet.php" class="btn btn-primary btn-sm">
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
                            $prediction = $vaccinationPredictions[$pet['pet_id']];
                            
                            foreach ($pet['records'] as $record) {
                                if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
                                    $hasVaccination = true;
                                }
                                if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
                                    $hasRecentVisit = true;
                                }
                            }
                            
                            // Enhanced health status with vaccination prediction
                            if ($prediction['needs_vaccination']) {
                                $healthStatus = 'Vaccination Due';
                                $statusClass = 'status-bad';
                            } else {
                                $healthStatus = $hasVaccination ? 'Good Health' : 'Needs Vaccination';
                                $statusClass = $hasVaccination ? 'status-good' : 'status-warning';
                                if (!$hasVaccination && !$hasRecentVisit) {
                                    $healthStatus = 'Needs Checkup';
                                    $statusClass = 'status-bad';
                                }
                            }
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="pet-card">
                                    <div class="pet-card-header" style="background: <?php echo strtolower($pet['species']) == 'dog' ? 'var(--info-light)' : 'var(--secondary-light)'; ?>">
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                            <small class="text-muted"><?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?></small>
                                        </div>
                                        <div class="pet-species-icon" style="background: <?php echo strtolower($pet['species']) == 'dog' ? 'var(--info)' : 'var(--secondary)'; ?>">
                                            <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?> text-white"></i>
                                        </div>
                                    </div>
                                    <div class="pet-card-body">
                                        <!-- Vaccination Alert Badge -->
                                        <?php if ($prediction['needs_vaccination']): ?>
                                            <div class="alert alert-warning alert-sm d-flex align-items-center mb-3 py-2">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                <small class="flex-grow-1">Vaccination due</small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center mb-3">
                                            <div id="qrcode-<?php echo $pet['pet_id']; ?>" class="me-3 qr-preview"></div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years<br>
                                                        <strong>Gender:</strong> <?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?><br>
                                                        <strong>Registered:</strong> <?php echo date('M j, Y', strtotime($pet['date_registered'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="health-status">
                                            <span class="status-dot <?php echo $statusClass; ?>"></span>
                                            <small><?php echo $healthStatus; ?></small>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mt-3">
                                            <button class="btn btn-outline-primary btn-sm" onclick="showQRModal(<?php echo $pet['pet_id']; ?>)">
                                                <i class="fas fa-qrcode me-1"></i> View QR
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="downloadQRCode(<?php echo $pet['pet_id']; ?>)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                            <a href="user_pet_profile.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-primary btn-sm">
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
                <p class="text-muted">Scan this QR code to view medical records</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="downloadModalQr">
                    <i class="fas fa-download me-1"></i> Download
                </button>
            </div>
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
                registered: '<?php echo $pet['date_registered']; ?>'
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
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

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
    }

    // Function to show QR code in modal
    function showQRModal(petId) {
        const qrContainer = document.getElementById(`qrcode-${petId}`);
        const modalQrContainer = document.getElementById('modalQrContainer');
        const qrModalTitle = document.getElementById('qrModalTitle');
        
        if (!qrContainer || !modalQrContainer) return;
        
        const petName = qrContainer.getAttribute('data-pet-name');
        
        // Update modal title
        qrModalTitle.textContent = `QR Code - ${petName}`;
        
        // Copy QR code to modal
        modalQrContainer.innerHTML = qrContainer.innerHTML;
        
        // Update download button
        const downloadBtn = document.getElementById('downloadModalQr');
        downloadBtn.onclick = function() {
            downloadQRCode(petId);
        };
        
        // Show modal
        const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
        qrModal.show();
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
            const searchInput = document.querySelector('.search-container input');
            if (searchInput) searchInput.focus();
        }
    });

    // Responsive sidebar toggle for mobile
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (window.innerWidth <= 768) {
            if (sidebar.style.width === '260px') {
                sidebar.style.width = '70px';
                mainContent.style.marginLeft = '70px';
            } else {
                sidebar.style.width = '260px';
                mainContent.style.marginLeft = '260px';
            }
        }
    }

    // Add mobile menu button if needed
    if (window.innerWidth <= 768) {
        const topbar = document.querySelector('.topbar > div:first-child');
        const menuButton = document.createElement('button');
        menuButton.className = 'btn btn-primary d-md-none me-3';
        menuButton.innerHTML = '<i class="fas fa-bars"></i>';
        menuButton.onclick = toggleSidebar;
        topbar.insertBefore(menuButton, topbar.firstChild);
    }

    console.log('PetMedQR Dashboard initialized successfully!');
</script>
</body>
</html>
