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
            --pink: #ffd6e7;
            --pink-2: #f7c5e0;
            --pink-light: #fff4f8;
            --blue: #4a6cf7;
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
            --red: #e74c3c;
            --red-light: #fdedec;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        /* ... (keep existing styles) ... */
        
        /* NEW STYLES FOR VACCINATION ALERTS AND RECOMMENDATIONS */
        
        .alert-vaccination {
            border-left: 4px solid var(--red);
            background: var(--red-light);
        }
        
        .alert-wellness {
            border-left: 4px solid var(--green);
            background: var(--green-light);
        }
        
        .recommendation-card {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            transition: transform 0.2s;
        }
        
        .recommendation-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .recommendation-high {
            border-left: 4px solid var(--red);
        }
        
        .recommendation-medium {
            border-left: 4px solid var(--orange);
        }
        
        .recommendation-low {
            border-left: 4px solid var(--green);
        }
        
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
            background: var(--blue);
        }
        
        .timeline-item.due::before {
            background: var(--red);
        }
        
        .timeline-item.upcoming::before {
            background: var(--orange);
        }
        
        .prediction-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .wellness-section {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .risk-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .risk-high { background-color: var(--red); }
        .risk-medium { background-color: var(--orange); }
        .risk-low { background-color: var(--green); }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-paw"></i> PetMedQR</div>
        <div class="profile">
            <img src="https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>" alt="User">
            <h6 id="ownerNameSidebar"><?php echo htmlspecialchars($user['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
        </div>
        <a href="user_dashboard.php" class="active">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="user_pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> My Pets
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> QR Codes
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> Register Pet
        </a>
        <a href="#">
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

        <!-- VACCINATION ALERTS -->
        <?php if ($petsNeedingVaccination > 0): ?>
            <div class="alert alert-vaccination alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Vaccination Alert!</h6>
                        <p class="mb-0"><?php echo $petsNeedingVaccination; ?> of your pets need vaccination updates.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
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
        <div class="row stats-row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card" style="background-color: var(--blue-light);">
                    <i class="fa-solid fa-paw text-primary"></i>
                    <h6>Registered Pets</h6>
                    <h4 id="totalPets"><?php echo $totalPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card" style="background-color: var(--green-light);">
                    <i class="fa-solid fa-syringe text-success"></i>
                    <h6>Vaccinated Pets</h6>
                    <h4 id="vaccinatedPets"><?php echo $vaccinatedPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card" style="background-color: <?php echo $petsNeedingVaccination > 0 ? 'var(--red-light)' : 'var(--orange-light)'; ?>;">
                    <i class="fa-solid fa-bell text-<?php echo $petsNeedingVaccination > 0 ? 'danger' : 'warning'; ?>"></i>
                    <h6>Vaccination Due</h6>
                    <h4 id="upcomingVaccines"><?php echo $petsNeedingVaccination; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card" style="background-color: var(--pink-light);">
                    <i class="fa-solid fa-stethoscope text-danger"></i>
                    <h6>Recent Visits</h6>
                    <h4 id="recentVisits"><?php echo $recentVisits; ?></h4>
                </div>
            </div>
        </div>

        <!-- WELLNESS RECOMMENDATIONS SECTION -->
        <?php if (!empty($pets)): ?>
        <div class="wellness-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Health & Wellness Recommendations</h4>
                <small class="text-muted">AI-powered insights for your pets</small>
            </div>
            
            <div class="row">
                <?php foreach ($pets as $petId => $pet): ?>
                    <?php $recommendations = $wellnessRecommendations[$petId] ?? []; ?>
                    <?php if (!empty($recommendations)): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card-custom">
                            <h6 class="mb-3">
                                <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($pet['pet_name']); ?>
                                <span class="badge bg-<?php echo $vaccinationPredictions[$petId]['risk_level'] === 'high' ? 'danger' : 
                                                       ($vaccinationPredictions[$petId]['risk_level'] === 'medium' ? 'warning' : 'success'); ?> prediction-badge ms-2">
                                    <?php echo ucfirst($vaccinationPredictions[$petId]['risk_level']); ?> Risk
                                </span>
                            </h6>
                            
                            <?php foreach ($recommendations as $rec): ?>
                                <div class="recommendation-card recommendation-<?php echo $rec['priority']; ?>">
                                    <div class="d-flex align-items-start">
                                        <i class="fas <?php echo $rec['icon']; ?> fa-lg text-<?php echo $rec['color']; ?> me-3 mt-1"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo $rec['title']; ?></h6>
                                            <p class="mb-1 small"><?php echo $rec['message']; ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-lightbulb me-1"></i><?php echo $rec['action']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- VACCINATION PREDICTION TIMELINE -->
        <?php if (!empty($pets)): ?>
        <div class="wellness-section">
            <h4 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Vaccination Schedule</h4>
            
            <div class="vaccination-timeline">
                <?php foreach ($pets as $petId => $pet): ?>
                    <?php $prediction = $vaccinationPredictions[$petId]; ?>
                    <div class="timeline-item <?php echo $prediction['needs_vaccination'] ? 'due' : 'upcoming'; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($pet['pet_name']); ?></h6>
                                <p class="mb-1 small"><?php echo $prediction['message']; ?></p>
                                <?php if (!empty($prediction['recommended_vaccines'])): ?>
                                    <div class="mt-1">
                                        <?php foreach ($prediction['recommended_vaccines'] as $vaccine): ?>
                                            <span class="badge bg-danger me-1"><?php echo $vaccine; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if ($prediction['next_vaccination_date']): ?>
                                    <small class="text-muted d-block">
                                        <?php echo $prediction['needs_vaccination'] ? 'Overdue since' : 'Next due'; ?>
                                    </small>
                                    <strong class="<?php echo $prediction['needs_vaccination'] ? 'text-danger' : 'text-warning'; ?>">
                                        <?php echo date('M j, Y', strtotime($prediction['next_vaccination_date'])); ?>
                                    </strong>
                                <?php endif; ?>
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
                                    <!-- Vaccination Alert Badge -->
                                    <?php if ($prediction['needs_vaccination']): ?>
                                        <div class="alert alert-warning alert-sm d-flex align-items-center mb-3 py-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <small class="flex-grow-1">Vaccination due: <?php echo implode(', ', $prediction['recommended_vaccines']); ?></small>
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

<!-- Test Button -->
<button class="btn btn-info test-btn" onclick="testAllQRCodes()">
    <i class="fas fa-bug me-1"></i> Test QR Codes
</button>

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
                vaccinationPrediction: <?php echo json_encode($vaccinationPredictions[$pet['pet_id']]); ?>,
                wellnessRecommendations: <?php echo json_encode($wellnessRecommendations[$pet['pet_id']]); ?>,
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
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Initialize vaccination reminder tooltips
        initializeVaccinationTooltips();
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
        
        // Add vaccination prediction if available
        if (petData.vaccinationPrediction) {
            qrData += `VACCINATION STATUS:\n`;
            qrData += `-------------------\n`;
            qrData += `Status: ${petData.vaccinationPrediction.needs_vaccination ? 'VACCINATION DUE' : 'Up to date'}\n`;
            qrData += `Message: ${petData.vaccinationPrediction.message}\n`;
            if (petData.vaccinationPrediction.recommended_vaccines.length > 0) {
                qrData += `Recommended: ${petData.vaccinationPrediction.recommended_vaccines.join(', ')}\n`;
            }
            qrData += `\n`;
        }
        
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

    // Initialize vaccination tooltips
    function initializeVaccinationTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Function to test all QR codes (for development)
    function testAllQRCodes() {
        const qrContainers = document.querySelectorAll('[id^="qrcode-"]');
        console.log(`Testing ${qrContainers.length} QR codes...`);
        
        qrContainers.forEach(container => {
            const petId = container.id.replace('qrcode-', '');
            const petName = container.getAttribute('data-pet-name');
            const hasContent = container.getAttribute('data-qr-content');
            
            console.log(`Pet ID: ${petId}, Name: ${petName}, Has Data: ${!!hasContent}`);
            
            if (!hasContent) {
                console.warn(`No QR data for pet ${petName} (ID: ${petId})`);
            }
        });
        
        alert(`Tested ${qrContainers.length} QR codes. Check console for details.`);
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

    // Pet card interactions
    document.querySelectorAll('.pet-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons or links
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('button') || e.target.closest('a')) {
                return;
            }
            
            // Expand/collapse medical records
            const recordsTable = this.querySelector('.table-responsive');
            if (recordsTable) {
                recordsTable.style.display = recordsTable.style.display === 'none' ? 'block' : 'none';
            }
        });
    });

    // Health status tooltips
    document.querySelectorAll('.health-status').forEach(status => {
        status.setAttribute('title', 'Click for details');
        status.style.cursor = 'help';
        
        status.addEventListener('click', function() {
            const petCard = this.closest('.pet-card');
            const petName = petCard.querySelector('h5').textContent;
            const statusText = this.querySelector('small').textContent;
            
            alert(`Health Status for ${petName}: ${statusText}\n\nThis status is based on vaccination records and recent vet visits.`);
        });
    });

    // Responsive sidebar toggle for mobile
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
    }

    // Add mobile menu button if needed
    if (window.innerWidth <= 768) {
        const topbar = document.querySelector('.topbar');
        const menuButton = document.createElement('button');
        menuButton.className = 'btn btn-primary d-md-none';
        menuButton.innerHTML = '<i class="fas fa-bars"></i>';
        menuButton.onclick = toggleSidebar;
        topbar.insertBefore(menuButton, topbar.firstChild);
    }

    // Auto-refresh data every 5 minutes
    setInterval(() => {
        console.log('Auto-refreshing dashboard data...');
        // In a real application, you might want to fetch updated data
        // location.reload(); // Simple refresh for demo
    }, 300000); // 5 minutes

    // Export functions for global access (for debugging)
    window.PetMedQR = {
        generateQRCode,
        showQRModal,
        downloadQRCode,
        testAllQRCodes,
        toggleQrData
    };

    console.log('PetMedQR Dashboard initialized successfully!');
</script>
</body>
</html>
