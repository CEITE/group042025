<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Check if user is logged in and is LGU
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lgu') {
    header("Location: login_lgu.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$lgu_name = $_SESSION['name'];
$city = $_SESSION['city'] ?? 'Not Set';
$province = $_SESSION['province'] ?? 'Not Set';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                $title = trim($_POST['title']);
                $message = trim($_POST['message']);
                $announcement_type = $_POST['announcement_type'];
                
                if (!empty($title) && !empty($message)) {
                    // Check if announcements table exists, if not create it
                    $check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
                    if ($check_table->num_rows == 0) {
                        $create_table = $conn->query("CREATE TABLE announcements (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            lgu_id INT NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            message TEXT NOT NULL,
                            type ENUM('general', 'vaccination', 'emergency', 'event') DEFAULT 'general',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (lgu_id) REFERENCES users(user_id) ON DELETE CASCADE
                        )");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO announcements (lgu_id, title, message, type) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isss", $user_id, $title, $message, $announcement_type);
                        if ($stmt->execute()) {
                            $_SESSION['success'] = "Announcement created successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to create announcement.";
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'update_medical_record':
                $record_id = $_POST['record_id'];
                $service_type = trim($_POST['service_type']);
                $service_description = trim($_POST['service_description']);
                $service_date = $_POST['service_date'];
                $veterinarian = trim($_POST['veterinarian']);
                $notes = trim($_POST['notes']);
                $status = $_POST['status'];
                $clinic_name = trim($_POST['clinic_name']);
                $clinic_address = trim($_POST['clinic_address']);
                
                $stmt = $conn->prepare("UPDATE pet_medical_records SET 
                    service_type = ?, 
                    service_description = ?, 
                    service_date = ?, 
                    veterinarian = ?, 
                    notes = ?, 
                    status = ?, 
                    clinic_name = ?, 
                    clinic_address = ? 
                    WHERE record_id = ?");
                
                if ($stmt) {
                    $stmt->bind_param("ssssssssi", $service_type, $service_description, $service_date, $veterinarian, $notes, $status, $clinic_name, $clinic_address, $record_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Medical record updated successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to update medical record.";
                    }
                    $stmt->close();
                }
                break;
                
            case 'add_medical_record':
                $pet_id = $_POST['pet_id'];
                $service_type = trim($_POST['service_type']);
                $service_description = trim($_POST['service_description']);
                $service_date = $_POST['service_date'];
                $veterinarian = trim($_POST['veterinarian']);
                $notes = trim($_POST['notes']);
                $status = $_POST['status'];
                $clinic_name = trim($_POST['clinic_name']);
                $clinic_address = trim($_POST['clinic_address']);
                
                // Get owner info for the pet
                $owner_query = $conn->query("SELECT owner_id, name FROM pets WHERE pet_id = $pet_id");
                if ($owner_query && $owner_query->num_rows > 0) {
                    $owner_data = $owner_query->fetch_assoc();
                    $owner_id = $owner_data['owner_id'];
                    $owner_name = $owner_data['name'];
                    
                    // Get pet info
                    $pet_query = $conn->query("SELECT name, species, breed FROM pets WHERE pet_id = $pet_id");
                    $pet_data = $pet_query->fetch_assoc();
                    
                    $stmt = $conn->prepare("INSERT INTO pet_medical_records (
                        owner_id, owner_name, pet_id, pet_name, species, breed, 
                        service_type, service_description, service_date, veterinarian, 
                        notes, status, clinic_name, clinic_address, generated_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    if ($stmt) {
                        $stmt->bind_param("isssssssssssss", 
                            $owner_id, $owner_name, $pet_id, $pet_data['name'], $pet_data['species'], $pet_data['breed'],
                            $service_type, $service_description, $service_date, $veterinarian,
                            $notes, $status, $clinic_name, $clinic_address
                        );
                        if ($stmt->execute()) {
                            $_SESSION['success'] = "Medical record added successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to add medical record.";
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'update_profile':
                $name = trim($_POST['name']);
                $phone_number = trim($_POST['phone_number']);
                $region = trim($_POST['region']);
                $province = trim($_POST['province']);
                $city = trim($_POST['city']);
                $barangay = trim($_POST['barangay']);
                $address = trim($_POST['address']);
                
                $stmt = $conn->prepare("UPDATE users SET name = ?, phone_number = ?, region = ?, province = ?, city = ?, barangay = ?, address = ? WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssssssi", $name, $phone_number, $region, $province, $city, $barangay, $address, $user_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Profile updated successfully!";
                        $_SESSION['name'] = $name;
                        $_SESSION['city'] = $city;
                        $_SESSION['province'] = $province;
                        $lgu_name = $name;
                    } else {
                        $_SESSION['error'] = "Failed to update profile.";
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Get statistics data
$stats = [
    'total_pets' => 0,
    'total_vets' => 0,
    'total_owners' => 0,
    'total_lgu' => 0,
    'total_medical_records' => 0
];

// Check if pets table exists and count pets
$check_pets = $conn->query("SHOW TABLES LIKE 'pets'");
if ($check_pets->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as total FROM pets");
    if ($result) {
        $stats['total_pets'] = $result->fetch_assoc()['total'];
    }
}

// Count users by role
$result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch($row['role']) {
            case 'vet':
                $stats['total_vets'] = $row['count'];
                break;
            case 'owner':
                $stats['total_owners'] = $row['count'];
                break;
            case 'lgu':
                $stats['total_lgu'] = $row['count'];
                break;
        }
    }
}

// Count medical records
$check_medical = $conn->query("SHOW TABLES LIKE 'pet_medical_records'");
if ($check_medical->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as total FROM pet_medical_records");
    if ($result) {
        $stats['total_medical_records'] = $result->fetch_assoc()['total'];
    }
}

// Get all pets with their medical records
$pets_with_records = [];
$check_pets = $conn->query("SHOW TABLES LIKE 'pets'");
if ($check_pets->num_rows > 0) {
    $query = "SELECT 
                p.pet_id,
                p.name as pet_name,
                p.species,
                p.breed,
                p.age,
                p.created_at as pet_created,
                u.name as owner_name,
                u.email as owner_email,
                u.phone_number as owner_phone,
                pmr.record_id,
                pmr.service_type,
                pmr.service_description,
                pmr.service_date,
                pmr.veterinarian,
                pmr.notes,
                pmr.status,
                pmr.generated_date,
                pmr.clinic_name,
                pmr.clinic_address
              FROM pets p
              LEFT JOIN users u ON p.owner_id = u.user_id
              LEFT JOIN pet_medical_records pmr ON p.pet_id = pmr.pet_id
              ORDER BY p.name, pmr.generated_date DESC";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pet_id = $row['pet_id'];
            if (!isset($pets_with_records[$pet_id])) {
                $pets_with_records[$pet_id] = [
                    'pet_id' => $row['pet_id'],
                    'pet_name' => $row['pet_name'],
                    'species' => $row['species'],
                    'breed' => $row['breed'],
                    'age' => $row['age'],
                    'pet_created' => $row['pet_created'],
                    'owner_name' => $row['owner_name'],
                    'owner_email' => $row['owner_email'],
                    'owner_phone' => $row['owner_phone'],
                    'medical_records' => []
                ];
            }
            
            if ($row['record_id']) {
                $pets_with_records[$pet_id]['medical_records'][] = [
                    'record_id' => $row['record_id'],
                    'service_type' => $row['service_type'],
                    'service_description' => $row['service_description'],
                    'service_date' => $row['service_date'],
                    'veterinarian' => $row['veterinarian'],
                    'notes' => $row['notes'],
                    'status' => $row['status'],
                    'generated_date' => $row['generated_date'],
                    'clinic_name' => $row['clinic_name'],
                    'clinic_address' => $row['clinic_address']
                ];
            }
        }
    }
}

// Get user's current profile data
$user_data = [];
$result = $conn->query("SELECT name, email, phone_number, region, province, city, barangay, address FROM users WHERE user_id = $user_id");
if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
}

// Get recent announcements
$announcements = [];
$check_announcements = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($check_announcements->num_rows > 0) {
    $result = $conn->query("SELECT * FROM announcements WHERE lgu_id = $user_id ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Dashboard - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #dbeafe;
            --secondary: #1e40af;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e5e7eb;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .dashboard-card .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .dashboard-card .card-body {
            padding: 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .badge {
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 500;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #374151;
            background: #f8fafc;
        }
        
        .pet-record {
            border-left: 4px solid var(--primary);
            background: #f8fafc;
        }
        
        .medical-record {
            border-left: 4px solid var(--success);
            background: #f0fdf4;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <div class="d-flex align-items-center mb-4">
                        <i class="fas fa-landmark fa-2x me-2"></i>
                        <h4 class="mb-0">LGU Portal</h4>
                    </div>
                    <div class="mb-4 p-3 bg-white bg-opacity-10 rounded">
                        <small class="opacity-75">Welcome,</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($lgu_name); ?></div>
                        <small class="opacity-75"><?php echo htmlspecialchars($city . ', ' . $province); ?></small>
                    </div>
                </div>
                
                <nav class="nav flex-column p-3">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#pets-section">
                        <i class="fas fa-paw"></i> Pets & Medical Records
                    </a>
                    <a class="nav-link" href="#announcements">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                    <a class="nav-link" href="#analytics">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                    <a class="nav-link" href="#profile">
                        <i class="fas fa-user-cog"></i> Profile Settings
                    </a>
                    <div class="mt-4 pt-3 border-top border-white border-opacity-25">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ml-auto p-0">
                <!-- Navbar -->
                <nav class="navbar navbar-expand-lg navbar-light">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-outline-primary me-3 d-md-none">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h5 class="mb-0 text-dark">LGU Dashboard</h5>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="dropdown me-3">
                                <button class="btn btn-outline-primary btn-sm position-relative" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-dot"></span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <h6 class="dropdown-header">Notifications</h6>
                                    <a class="dropdown-item" href="#">New medical records available</a>
                                    <a class="dropdown-item" href="#">System updates completed</a>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#profile" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li><a class="dropdown-item" href="#profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Main Content Area -->
                <div class="p-4">
                    <!-- Alerts -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $_SESSION['error']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Pets</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_pets']); ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-paw fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Medical Records</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_medical_records']); ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-file-medical fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pet Owners</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_owners']); ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-users fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Veterinarians</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_vets']); ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-user-md fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pets and Medical Records Section -->
                    <div class="dashboard-card" id="pets-section">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-paw me-2"></i>Pets & Medical Records</span>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalRecordModal">
                                <i class="fas fa-plus me-1"></i>Add Medical Record
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pets_with_records)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-paw fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Pets Found</h5>
                                    <p class="text-muted">No pets have been registered in the system yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="petsAccordion">
                                    <?php foreach ($pets_with_records as $pet): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pet<?php echo $pet['pet_id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($pet['pet_name']); ?></strong>
                                                        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($pet['species']); ?></span>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($pet['breed']); ?></span>
                                                        <span class="badge bg-info">Age: <?php echo htmlspecialchars($pet['age']); ?></span>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted">Owner: <?php echo htmlspecialchars($pet['owner_name']); ?></small>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="pet<?php echo $pet['pet_id']; ?>" class="accordion-collapse collapse" data-bs-parent="#petsAccordion">
                                            <div class="accordion-body">
                                                <!-- Pet Information -->
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <h6>Pet Information</h6>
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($pet['pet_name']); ?></p>
                                                        <p><strong>Species:</strong> <?php echo htmlspecialchars($pet['species']); ?></p>
                                                        <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?></p>
                                                        <p><strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Owner Information</h6>
                                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($pet['owner_email']); ?></p>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($pet['owner_phone']); ?></p>
                                                    </div>
                                                </div>

                                                <!-- Medical Records -->
                                                <h6>Medical Records</h6>
                                                <?php if (empty($pet['medical_records'])): ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        No medical records found for this pet.
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($pet['medical_records'] as $record): ?>
                                                    <div class="card medical-record mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <div>
                                                                    <span class="badge bg-<?php 
                                                                        switch($record['status']) {
                                                                            case 'completed': echo 'success'; break;
                                                                            case 'under_treatment': echo 'warning'; break;
                                                                            case 'pending': echo 'secondary'; break;
                                                                            default: echo 'secondary';
                                                                        }
                                                                    ?>"><?php echo ucfirst($record['status']); ?></span>
                                                                </div>
                                                                <div>
                                                                    <button class="btn btn-sm btn-outline-primary" 
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#editMedicalRecordModal"
                                                                            data-record-id="<?php echo $record['record_id']; ?>"
                                                                            data-service-type="<?php echo htmlspecialchars($record['service_type'] ?? ''); ?>"
                                                                            data-service-description="<?php echo htmlspecialchars($record['service_description'] ?? ''); ?>"
                                                                            data-service-date="<?php echo $record['service_date']; ?>"
                                                                            data-veterinarian="<?php echo htmlspecialchars($record['veterinarian'] ?? ''); ?>"
                                                                            data-notes="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>"
                                                                            data-status="<?php echo $record['status']; ?>"
                                                                            data-clinic-name="<?php echo htmlspecialchars($record['clinic_name'] ?? ''); ?>"
                                                                            data-clinic-address="<?php echo htmlspecialchars($record['clinic_address'] ?? ''); ?>">
                                                                        <i class="fas fa-edit"></i> Edit
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($record['service_type'])): ?>
                                                                <p><strong>Service Type:</strong> <?php echo htmlspecialchars($record['service_type']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['service_description'])): ?>
                                                                <p><strong>Description:</strong> <?php echo htmlspecialchars($record['service_description']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['veterinarian'])): ?>
                                                                <p><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['clinic_name'])): ?>
                                                                <p><strong>Clinic:</strong> <?php echo htmlspecialchars($record['clinic_name']); ?></p>
                                                            <?php endif; ?>
                                                            <small class="text-muted">
                                                                Service Date: <?php echo date('M d, Y', strtotime($record['service_date'])); ?> | 
                                                                Generated: <?php echo date('M d, Y', strtotime($record['generated_date'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rest of the dashboard content (announcements, analytics, etc.) -->
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Quick Actions -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                                <i class="fas fa-bullhorn me-2"></i>Create Announcement
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#profileModal">
                                                <i class="fas fa-user-edit me-2"></i>Update Profile
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-outline-primary w-100 mb-2" onclick="generateReport()">
                                                <i class="fas fa-file-pdf me-2"></i>Generate Report
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button class="btn btn-outline-primary w-100 mb-2" onclick="viewAnalytics()">
                                                <i class="fas fa-chart-bar me-2"></i>View Analytics
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Visualization -->
                            <div class="row" id="analytics">
                                <div class="col-md-6">
                                    <div class="dashboard-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-pie me-2"></i>Medical Record Status
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="medicalStatusChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="dashboard-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-line me-2"></i>Records Trend
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="recordsTrendChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Recent Announcements -->
                            <div class="dashboard-card" id="announcements">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bullhorn me-2"></i>Recent Announcements</span>
                                    <span class="badge bg-primary"><?php echo count($announcements); ?></span>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($announcements)): ?>
                                        <p class="text-muted text-center">No announcements yet</p>
                                        <div class="text-center">
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                                Create First Announcement
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                        <div class="mb-3 p-3 border rounded">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                            <p class="text-muted small mb-1"><?php echo substr($announcement['message'], 0, 100); ?>...</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-secondary"><?php echo ucfirst($announcement['type']); ?></span>
                                                <small class="text-muted"><?php echo date('M d', strtotime($announcement['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- System Information -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <i class="fas fa-info-circle me-2"></i>System Information
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">LGU Name</small>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($lgu_name); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Location</small>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($city . ', ' . $province); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Total Medical Records</small>
                                        <div class="fw-semibold"><?php echo number_format($stats['total_medical_records']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Last Login</small>
                                        <div class="fw-semibold"><?php echo date('M d, Y g:i A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Create Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_announcement">
                        <div class="mb-3">
                            <label class="form-label">Announcement Type</label>
                            <select class="form-select" name="announcement_type" required>
                                <option value="general">General</option>
                                <option value="vaccination">Vaccination Drive</option>
                                <option value="emergency">Emergency</option>
                                <option value="event">Municipal Event</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required placeholder="Enter announcement title">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="4" required placeholder="Enter announcement message"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Publish Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Medical Record Modal -->
    <div class="modal fade" id="editMedicalRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_medical_record">
                        <input type="hidden" name="record_id" id="edit_record_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Type</label>
                                <input type="text" class="form-control" name="service_type" id="edit_service_type" placeholder="e.g., Vaccination, Check-up">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Date</label>
                                <input type="date" class="form-control" name="service_date" id="edit_service_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Description</label>
                            <textarea class="form-control" name="service_description" id="edit_service_description" rows="3" placeholder="Describe the service provided"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Veterinarian</label>
                                <input type="text" class="form-control" name="veterinarian" id="edit_veterinarian" placeholder="Veterinarian name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="under_treatment">Under Treatment</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="3" placeholder="Additional notes"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Clinic Name</label>
                                <input type="text" class="form-control" name="clinic_name" id="edit_clinic_name" placeholder="Clinic name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Clinic Address</label>
                                <input type="text" class="form-control" name="clinic_address" id="edit_clinic_address" placeholder="Clinic address">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Medical Record Modal -->
    <div class="modal fade" id="addMedicalRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_medical_record">
                        <div class="mb-3">
                            <label class="form-label">Select Pet</label>
                            <select class="form-select" name="pet_id" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets_with_records as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>">
                                    <?php echo htmlspecialchars($pet['pet_name'] . ' - ' . $pet['owner_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Type</label>
                                <input type="text" class="form-control" name="service_type" placeholder="e.g., Vaccination, Check-up">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Date</label>
                                <input type="date" class="form-control" name="service_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Description</label>
                            <textarea class="form-control" name="service_description" rows="3" placeholder="Describe the service provided"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Veterinarian</label>
                                <input type="text" class="form-control" name="veterinarian" placeholder="Veterinarian name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="under_treatment">Under Treatment</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Clinic Name</label>
                                <input type="text" class="form-control" name="clinic_name" placeholder="Clinic name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Clinic Address</label>
                                <input type="text" class="form-control" name="clinic_address" placeholder="Clinic address">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Settings Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update LGU Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LGU Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="region" value="<?php echo htmlspecialchars($user_data['region'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Province</label>
                                <input type="text" class="form-control" name="province" value="<?php echo htmlspecialchars($user_data['province'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City/Municipality</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Barangay</label>
                                <input type="text" class="form-control" name="barangay" value="<?php echo htmlspecialchars($user_data['barangay'] ?? ''); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Full Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Medical Record Modal Handler
        const editMedicalRecordModal = document.getElementById('editMedicalRecordModal');
        if (editMedicalRecordModal) {
            editMedicalRecordModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const recordId = button.getAttribute('data-record-id');
                const serviceType = button.getAttribute('data-service-type');
                const serviceDescription = button.getAttribute('data-service-description');
                const serviceDate = button.getAttribute('data-service-date');
                const veterinarian = button.getAttribute('data-veterinarian');
                const notes = button.getAttribute('data-notes');
                const status = button.getAttribute('data-status');
                const clinicName = button.getAttribute('data-clinic-name');
                const clinicAddress = button.getAttribute('data-clinic-address');
                
                const modal = this;
                modal.querySelector('#edit_record_id').value = recordId;
                modal.querySelector('#edit_service_type').value = serviceType || '';
                modal.querySelector('#edit_service_description').value = serviceDescription || '';
                modal.querySelector('#edit_service_date').value = serviceDate || '';
                modal.querySelector('#edit_veterinarian').value = veterinarian || '';
                modal.querySelector('#edit_notes').value = notes || '';
                modal.querySelector('#edit_status').value = status;
                modal.querySelector('#edit_clinic_name').value = clinicName || '';
                modal.querySelector('#edit_clinic_address').value = clinicAddress || '';
            });
        }

        // Data Visualization Charts
        const medicalStatusCtx = document.getElementById('medicalStatusChart').getContext('2d');
        const medicalStatusChart = new Chart(medicalStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Completed', 'Under Treatment'],
                datasets: [{
                    data: [12, 15, 8],
                    backgroundColor: ['#6b7280', '#10b981', '#f59e0b'],
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

        const recordsTrendCtx = document.getElementById('recordsTrendChart').getContext('2d');
        const recordsTrendChart = new Chart(recordsTrendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Medical Records',
                    data: [5, 8, 12, 10, 15, 18],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function generateReport() {
            alert('Medical records report generation would be implemented here!');
        }

        function viewAnalytics() {
            document.getElementById('analytics').scrollIntoView({ behavior: 'smooth' });
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
