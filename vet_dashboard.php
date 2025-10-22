<?php
session_start();
include("conn.php");

// ✅ 1. Check if vet is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vet') {
    header("Location: login.php");
    exit();
}

$vet_id = $_SESSION['user_id'];

// ✅ 2. Fetch logged-in vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

// Set default profile picture if none exists
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// ✅ 3. Fetch all pets with their medical records for vet to update
$query = "
SELECT 
    u.user_id,
    u.name AS owner_name,
    u.email AS owner_email,
    u.phone AS owner_phone,
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
    m.record_id,
    m.weight_date,
    m.weight AS record_weight,
    m.reminder_description,
    m.reminder_due_date,
    m.service_date,
    m.service_type,
    m.service_description,
    m.veterinarian,
    m.notes,
    m.created_at
FROM pets p
JOIN users u ON p.user_id = u.user_id
LEFT JOIN pet_medical_records m ON p.pet_id = m.pet_id
ORDER BY p.pet_id, m.service_date DESC;
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("❌ SQL ERROR: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();

// ✅ 4. Process data for vet dashboard
$pets = [];
$currentPetId = null;
$petRecords = [];

while ($row = $result->fetch_assoc()) {
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
            'owner_name' => $row['owner_name'],
            'owner_email' => $row['owner_email'],
            'owner_phone' => $row['owner_phone']
        ];
    }
    
    // Only add medical records if they exist
    if (!empty($row['record_id'])) {
        $serviceDate = ($row['service_date'] !== '0000-00-00' && !empty($row['service_date'])) ? $row['service_date'] : null;
        $weightDate = ($row['weight_date'] !== '0000-00-00' && !empty($row['weight_date'])) ? $row['weight_date'] : null;
        
        // Only add record if there's valid data
        if ($serviceDate || $weightDate || !empty($row['service_type']) || !empty($row['reminder_description'])) {
            $petRecords[] = [
                'record_id' => $row['record_id'],
                'weight_date' => $weightDate,
                'weight' => $row['record_weight'] ?? null,
                'reminder_description' => $row['reminder_description'] ?? null,
                'reminder_due_date' => $row['reminder_due_date'] ?? null,
                'service_date' => $serviceDate,
                'service_type' => $row['service_type'] ?? null,
                'service_description' => $row['service_description'] ?? null,
                'veterinarian' => $row['veterinarian'] ?? null,
                'notes' => $row['notes'] ?? null,
                'created_at' => $row['created_at'] ?? null
            ];
        }
    }
}

if ($currentPetId !== null) {
    $pets[$currentPetId]['records'] = $petRecords;
}

// ✅ 5. Dashboard statistics for vet
$totalPets = count($pets);
$totalOwners = count(array_unique(array_column($pets, 'owner_name')));
$recentVisits = 0;
$pendingFollowups = 0;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

foreach ($pets as $pet) {
    foreach ($pet['records'] as $record) {
        // Check for recent visits
        if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
            $recentVisits++;
        }
        
        // Check for pending followups
        if (!empty($record['reminder_due_date']) && $record['reminder_due_date'] >= date('Y-m-d')) {
            $pendingFollowups++;
        }
    }
}

// ✅ 6. Handle medical record updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_medical_record') {
        $pet_id = $_POST['pet_id'];
        $service_date = $_POST['service_date'];
        $service_type = $_POST['service_type'];
        $service_description = $_POST['service_description'];
        $weight = $_POST['weight'] ?? null;
        $weight_date = $_POST['weight_date'] ?? null;
        $reminder_description = $_POST['reminder_description'] ?? null;
        $reminder_due_date = $_POST['reminder_due_date'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        $insert_query = "
        INSERT INTO pet_medical_records 
        (pet_id, service_date, service_type, service_description, weight, weight_date, reminder_description, reminder_due_date, notes, veterinarian) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isssdsssss", 
            $pet_id, 
            $service_date, 
            $service_type, 
            $service_description, 
            $weight,
            $weight_date,
            $reminder_description,
            $reminder_due_date,
            $notes,
            $vet['name']
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Medical record added successfully!";
            // Refresh page to show updated records
            header("Location: vet_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Error adding medical record: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'update_medical_record') {
        $record_id = $_POST['record_id'];
        $service_date = $_POST['service_date'];
        $service_type = $_POST['service_type'];
        $service_description = $_POST['service_description'];
        $weight = $_POST['weight'] ?? null;
        $weight_date = $_POST['weight_date'] ?? null;
        $reminder_description = $_POST['reminder_description'] ?? null;
        $reminder_due_date = $_POST['reminder_due_date'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        $update_query = "
        UPDATE pet_medical_records 
        SET service_date = ?, service_type = ?, service_description = ?, weight = ?, weight_date = ?, 
            reminder_description = ?, reminder_due_date = ?, notes = ?, veterinarian = ?
        WHERE record_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssdsssssi", 
            $service_date, 
            $service_type, 
            $service_description, 
            $weight,
            $weight_date,
            $reminder_description,
            $reminder_due_date,
            $notes,
            $vet['name'],
            $record_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Medical record updated successfully!";
            header("Location: vet_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating medical record: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'delete_medical_record') {
        $record_id = $_POST['record_id'];
        
        $delete_query = "DELETE FROM pet_medical_records WHERE record_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $record_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Medical record deleted successfully!";
            header("Location: vet_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Error deleting medical record: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCareQR - Veterinary Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-light: #5dade2;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fb;
            margin: 0;
            color: #333;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: white;
            padding: 2rem 1rem;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary);
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
            background: var(--light);
            margin-right: 10px;
            color: var(--primary);
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: var(--primary-light);
            color: white;
        }
        
        .sidebar a.active .icon, .sidebar a:hover .icon {
            background: rgba(255,255,255,0.2);
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
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            height: 100%;
            background: var(--light);
        }
        
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .pet-card {
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .pet-card-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--primary-light);
            color: white;
        }
        
        .pet-card-body {
            padding: 1rem;
        }
        
        .btn-vet {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-vet:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .medical-table {
            font-size: 0.9rem;
        }
        
        .medical-table th {
            background-color: #f8f9fa;
        }
        
        .badge-service {
            background: var(--primary);
            color: white;
        }
        
        .badge-vaccine {
            background: var(--success);
            color: white;
        }
        
        .badge-checkup {
            background: var(--warning);
            color: white;
        }
        
        .badge-emergency {
            background: var(--accent);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 40px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
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
        <div class="brand"><i class="fa-solid fa-stethoscope"></i> VetCareQR</div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Veterinarian"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6><?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted">Veterinarian</small>
        </div>
        <a href="vet_dashboard.php" class="active">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_patients.php">
            <div class="icon"><i class="fa-solid fa-paw"></i></div> All Patients
        </a>
        <a href="vet_appointments.php">
            <div class="icon"><i class="fa-solid fa-calendar-check"></i></div> Appointments
        </a>
        <a href="vet_records.php">
            <div class="icon"><i class="fa-solid fa-file-medical"></i></div> Medical Records
        </a>
        <a href="vet_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout" style="background: var(--accent); color: white; margin-top: auto;">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Welcome, Dr. <?php echo htmlspecialchars($vet['name']); ?> 👨‍⚕️</h5>
                <small class="text-muted">Veterinary Dashboard - Manage Patient Records</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Search pets, owners..." class="form-control" style="width: 300px;">
                </div>
                <div class="text-end">
                    <strong id="currentDate"></strong><br>
                    <small id="currentTime"></small>
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
        <div class="row stats-row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-paw"></i>
                    <h6>Total Patients</h6>
                    <h4><?php echo $totalPets; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-users"></i>
                    <h6>Pet Owners</h6>
                    <h4><?php echo $totalOwners; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <i class="fa-solid-calendar-check"></i>
                    <h6>Recent Visits (30d)</h6>
                    <h4><?php echo $recentVisits; ?></h4>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-bell"></i>
                    <h6>Pending Follow-ups</h6>
                    <h4><?php echo $pendingFollowups; ?></h4>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h4>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <button class="btn btn-vet w-100" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Medical Record
                    </button>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="vet_patients.php" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-search me-1"></i> View All Patients
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="vet_appointments.php" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-calendar me-1"></i> Schedule Appointment
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="vet_records.php" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-file-medical me-1"></i> Medical History
                    </a>
                </div>
            </div>
        </div>

        <!-- Patients & Medical Records Section -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-paw me-2"></i>All Patients & Medical Records</h4>
                <span class="badge bg-primary"><?php echo $totalPets; ?> Pets</span>
            </div>
            
            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-paw"></i>
                    <h5>No Patients Found</h5>
                    <p class="text-muted">No pets have been registered in the system yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pets as $pet): ?>
                        <div class="col-12 mb-4">
                            <div class="pet-card">
                                <div class="pet-card-header">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                        <small>
                                            <?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?> 
                                            • Owner: <?php echo htmlspecialchars($pet['owner_name']); ?>
                                            • Tel: <?php echo htmlspecialchars($pet['owner_phone']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-light" onclick="addRecordForPet(<?php echo $pet['pet_id']; ?>, '<?php echo addslashes($pet['pet_name']); ?>')">
                                            <i class="fa-solid fa-plus me-1"></i> Add Record
                                        </button>
                                    </div>
                                </div>
                                <div class="pet-card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Gender:</strong> <?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Color:</strong> <?php echo htmlspecialchars($pet['color']) ?: 'Not specified'; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Weight:</strong> <?php echo $pet['weight'] ? $pet['weight'] . ' kg' : 'Not recorded'; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($pet['records'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm medical-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Service Type</th>
                                                        <th>Description</th>
                                                        <th>Weight</th>
                                                        <th>Reminder</th>
                                                        <th>Veterinarian</th>
                                                        <th>Actions</th>
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
                                                                        } elseif (stripos($record['service_type'], 'emergency') !== false) {
                                                                            $badgeClass = 'badge-emergency';
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($record['service_description'] ?? '-'); ?></td>
                                                                <td><?php echo $record['weight'] ? $record['weight'] . ' kg' : '-'; ?></td>
                                                                <td>
                                                                    <?php if (!empty($record['reminder_description'])): ?>
                                                                        <small>
                                                                            <?php echo htmlspecialchars($record['reminder_description']); ?>
                                                                            <?php if (!empty($record['reminder_due_date'])): ?>
                                                                                <br><strong>Due: <?php echo date('M j, Y', strtotime($record['reminder_due_date'])); ?></strong>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($record['veterinarian'] ?? '-'); ?></td>
                                                                <td>
                                                                    <div class="action-buttons">
                                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                                onclick="editRecord(<?php echo $record['record_id']; ?>)"
                                                                                title="Edit Record">
                                                                            <i class="fa-solid fa-edit"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                                onclick="deleteRecord(<?php echo $record['record_id']; ?>)"
                                                                                title="Delete Record">
                                                                            <i class="fa-solid fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
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

<!-- Add Medical Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRecordModalTitle">Add Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_dashboard.php">
                <input type="hidden" name="action" value="add_medical_record">
                <input type="hidden" name="pet_id" id="modalPetId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="service_date" class="form-label">Service Date *</label>
                            <input type="date" class="form-control" id="service_date" name="service_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="service_type" class="form-label">Service Type *</label>
                            <select class="form-select" id="service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="Vaccination">Vaccination</option>
                                <option value="Check-up">Check-up</option>
                                <option value="Dental Cleaning">Dental Cleaning</option>
                                <option value="Surgery">Surgery</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Grooming">Grooming</option>
                                <option value="Laboratory Test">Laboratory Test</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="service_description" class="form-label">Service Description *</label>
                        <textarea class="form-control" id="service_description" name="service_description" 
                                  rows="3" placeholder="Describe the service performed..." required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight" 
                                   placeholder="Enter weight in kg">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="weight_date" class="form-label">Weight Date</label>
                            <input type="date" class="form-control" id="weight_date" name="weight_date">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reminder_description" class="form-label">Reminder Description</label>
                            <input type="text" class="form-control" id="reminder_description" name="reminder_description" 
                                   placeholder="e.g., Next vaccination due">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="reminder_due_date" class="form-label">Reminder Due Date</label>
                            <input type="date" class="form-control" id="reminder_due_date" name="reminder_due_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" 
                                  rows="3" placeholder="Any additional notes or observations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-vet">Save Medical Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medical Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_dashboard.php" id="editRecordForm">
                <input type="hidden" name="action" value="update_medical_record">
                <input type="hidden" name="record_id" id="editRecordId">
                
                <div class="modal-body" id="editRecordModalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-vet">Update Medical Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_dashboard.php" id="deleteRecordForm">
                <input type="hidden" name="action" value="delete_medical_record">
                <input type="hidden" name="record_id" id="deleteRecordId">
                
                <div class="modal-body">
                    <p>Are you sure you want to delete this medical record? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Record</button>
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
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterPets(searchTerm);
            });
        }
        
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

    // Filter pets based on search term
    function filterPets(searchTerm) {
        const petCards = document.querySelectorAll('.pet-card');
        
        petCards.forEach(card => {
            const petName = card.querySelector('h5').textContent.toLowerCase();
            const ownerName = card.querySelector('small').textContent.toLowerCase();
            const species = card.querySelector('.row .col-md-3:first-child').textContent.toLowerCase();
            
            if (petName.includes(searchTerm) || ownerName.includes(searchTerm) || species.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Add record for specific pet
    function addRecordForPet(petId, petName) {
        document.getElementById('modalPetId').value = petId;
        document.getElementById('addRecordModalTitle').textContent = `Add Medical Record - ${petName}`;
        
        const modal = new bootstrap.Modal(document.getElementById('addRecordModal'));
        modal.show();
    }

    // Edit medical record
    function editRecord(recordId) {
        document.getElementById('editRecordId').value = recordId;
        
        // Load record data via AJAX
        fetch('get_record_data.php?record_id=' + recordId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('editRecordModalBody').innerHTML = data;
                const modal = new bootstrap.Modal(document.getElementById('editRecordModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error loading record data:', error);
                alert('Error loading record data. Please try again.');
            });
    }

    // Delete medical record
    function deleteRecord(recordId) {
        document.getElementById('deleteRecordId').value = recordId;
        const modal = new bootstrap.Modal(document.getElementById('deleteRecordModal'));
        modal.show();
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+K for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.focus();
        }
        
        // Ctrl+N for new record
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('addRecordModal'));
            modal.show();
        }
    });

    // Auto-fill current date for weight if not specified
    document.getElementById('weight').addEventListener('input', function() {
        const weightDate = document.getElementById('weight_date');
        if (this.value && !weightDate.value) {
            weightDate.value = '<?php echo date('Y-m-d'); ?>';
        }
    });

    console.log('Vet Dashboard initialized successfully!');
</script>
</body>
</html>
