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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_vet.php");
    exit();
}

// Check if user has the correct role
if ($_SESSION['role'] !== 'vet') {
    session_unset();
    session_destroy();
    header("Location: login_vet.php");
    exit();
}

// Basic session security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$vet_id = $_SESSION['user_id'];

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $vet_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$vet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_record'])) {
        // Add new medical record using your table structure
        $pet_id = $_POST['pet_id'];
        $service_date = $_POST['service_date'];
        $service_time = $_POST['service_time'];
        $service_type = trim($_POST['service_type']);
        $service_description = trim($_POST['service_description']);
        $weight = trim($_POST['weight']);
        $notes = trim($_POST['notes']);
        $reminder_description = trim($_POST['reminder_description']);
        $reminder_due_date = $_POST['reminder_due_date'] ?: null;
        
        // Get pet and owner info
        $pet_info_stmt = $conn->prepare("
            SELECT p.*, u.name as owner_name, u.email as owner_email 
            FROM pets p 
            JOIN users u ON p.owner_id = u.user_id 
            WHERE p.pet_id = ?
        ");
        if ($pet_info_stmt) {
            $pet_info_stmt->bind_param("i", $pet_id);
            $pet_info_stmt->execute();
            $pet_info = $pet_info_stmt->get_result()->fetch_assoc();
            $pet_info_stmt->close();
            
            if ($pet_info) {
                $insert_stmt = $conn->prepare("
                    INSERT INTO pet_medical_records 
                    (owner_id, owner_name, pet_id, pet_name, species, breed, color, sex, dob, age, 
                     weight, status, tag, microchip, weight_date, reminder_description, reminder_due_date,
                     service_date, service_time, service_type, service_description, veterinarian, notes, 
                     generated_date, clinic_name, clinic_address, clinic_contact, owner_email) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
                ");
                if ($insert_stmt) {
                    $clinic_name = "BrightView Veterinary Clinic";
                    $clinic_address = "123 Veterinary Street, City";
                    $clinic_contact = "(555) 123-4567";
                    
                    $insert_stmt->bind_param("isisssssssssssssssssssssssss", 
                        $pet_info['owner_id'], $pet_info['owner_name'], $pet_id, $pet_info['name'],
                        $pet_info['species'], $pet_info['breed'], $pet_info['color'], $pet_info['gender'],
                        $pet_info['birth_date'], $pet_info['age'], $weight, $pet_info['status'],
                        $pet_info['tag_number'], $pet_info['microchip_number'], $service_date,
                        $reminder_description, $reminder_due_date, $service_date, $service_time,
                        $service_type, $service_description, $vet['name'], $notes,
                        $clinic_name, $clinic_address, $clinic_contact, $pet_info['owner_email']
                    );
                    
                    if ($insert_stmt->execute()) {
                        $_SESSION['success'] = "Medical record added successfully!";
                    } else {
                        $_SESSION['error'] = "Error adding medical record: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
            }
        }
    } elseif (isset($_POST['update_record'])) {
        // Update existing medical record
        $record_id = $_POST['record_id'];
        $service_date = $_POST['service_date'];
        $service_time = $_POST['service_time'];
        $service_type = trim($_POST['service_type']);
        $service_description = trim($_POST['service_description']);
        $weight = trim($_POST['weight']);
        $notes = trim($_POST['notes']);
        $reminder_description = trim($_POST['reminder_description']);
        $reminder_due_date = $_POST['reminder_due_date'] ?: null;
        
        $update_stmt = $conn->prepare("
            UPDATE pet_medical_records 
            SET service_date = ?, service_time = ?, service_type = ?, service_description = ?, 
                weight = ?, notes = ?, reminder_description = ?, reminder_due_date = ?,
                veterinarian = ?, generated_date = NOW()
            WHERE record_id = ?
        ");
        if ($update_stmt) {
            $update_stmt->bind_param("sssssssssi", 
                $service_date, $service_time, $service_type, $service_description,
                $weight, $notes, $reminder_description, $reminder_due_date,
                $vet['name'], $record_id
            );
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Medical record updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating medical record: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
    }
    
    header("Location: vet_records.php");
    exit();
}

// Handle delete record
if (isset($_GET['delete'])) {
    $record_id = $_GET['delete'];
    $delete_stmt = $conn->prepare("DELETE FROM pet_medical_records WHERE record_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("i", $record_id);
        if ($delete_stmt->execute()) {
            $_SESSION['success'] = "Medical record deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting medical record: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    }
    header("Location: vet_records.php");
    exit();
}

// Fetch all pets for dropdown
$pets_stmt = $conn->prepare("
    SELECT p.pet_id, p.name as pet_name, p.species, p.breed, p.age, p.gender, p.color,
           p.birth_date, p.status, p.tag_number, p.microchip_number,
           u.name as owner_name, u.email as owner_email, u.user_id as owner_id
    FROM pets p 
    JOIN users u ON p.owner_id = u.user_id 
    ORDER BY p.name
");
if ($pets_stmt) {
    $pets_stmt->execute();
    $pets = $pets_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pets_stmt->close();
} else {
    $pets = [];
}

// Fetch medical records - using your actual table structure
$records_stmt = $conn->prepare("
    SELECT * FROM pet_medical_records 
    ORDER BY service_date DESC, record_id DESC
");
if (!$records_stmt) {
    die("Prepare failed: " . $conn->error);
}
$records_stmt->execute();
$medical_records = $records_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$records_stmt->close();

// Fetch record counts for stats
$total_records = count($medical_records);
$today_records = 0;
$recent_records = 0;
$today = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-7 days'));

foreach ($medical_records as $record) {
    if ($record['service_date'] == $today) {
        $today_records++;
    }
    if ($record['service_date'] >= $week_ago) {
        $recent_records++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Vet Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --dark: #1f2937;
            --gray: #6b7280;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, var(--primary-light) 100%);
            margin: 0;
            color: var(--dark);
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
            color: var(--dark);
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
            background: linear-gradient(135deg, var(--danger), #e74c3c);
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
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: var(--radius);
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
        
        .record-item {
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .record-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .pet-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--primary-light);
            color: var(--primary-dark);
            margin-right: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .badge-species-dog {
            background: linear-gradient(135deg, var(--primary), #2980b9);
        }
        
        .badge-species-cat {
            background: linear-gradient(135deg, var(--warning), #e67e22);
        }
        
        .badge-species-other {
            background: linear-gradient(135deg, var(--success), #27ae60);
        }
        
        .service-badge {
            background: linear-gradient(135deg, var(--secondary), #7c3aed);
        }
        
        .vital-stats {
            background: var(--primary-light);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .vital-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .vital-item:last-child {
            border-bottom: none;
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
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-paw"></i> BrightView<br>Veterinary Clinic
        </div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Vet"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6>Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($vet['role']); ?></small>
        </div>

        <a href="vet_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_appointments.php">
            <div class="icon"><i class="fa-solid fa-calendar-check"></i></div> Appointments
        </a>
        <a href="vet_patients.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> Patients
        </a>
        <a href="vet_records.php" class="active">
            <div class="icon"><i class="fa-solid fa-file-medical"></i></div> Medical Records
        </a>
        <a href="vet_messages.php">
            <div class="icon"><i class="fa-solid fa-envelope"></i></div> Messages
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
                <h5 class="mb-0">Medical Records</h5>
                <small class="text-muted">Manage patient medical records and history</small>
            </div>
            <div class="d-flex align-items-center gap-3">
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
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <div class="stats-number"><?php echo $total_records; ?></div>
                    <div class="stats-label">Total Records</div>
                    <i class="fas fa-file-medical"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success), #00f2fe);">
                    <div class="stats-number"><?php echo $today_records; ?></div>
                    <div class="stats-label">Today's Records</div>
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning), #f5576c);">
                    <div class="stats-number"><?php echo $recent_records; ?></div>
                    <div class="stats-label">Recent (7 days)</div>
                    <i class="fas fa-history"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Medical Records List -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-file-medical me-2"></i>Medical Records</h4>
                        <span class="badge bg-primary"><?php echo $total_records; ?> records</span>
                    </div>
                    
                    <?php if (empty($medical_records)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical fa-2x mb-3"></i>
                            <h5>No Medical Records</h5>
                            <p class="text-muted">No medical records found. Start by adding a new record.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                                <i class="fas fa-plus me-2"></i>Add First Record
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="records-list">
                            <?php foreach ($medical_records as $record): ?>
                                <div class="record-item">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="pet-avatar">
                                                <i class="fas fa-<?php echo strtolower($record['species']) == 'dog' ? 'dog' : (strtolower($record['species']) == 'cat' ? 'cat' : 'paw'); ?>"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($record['pet_name']); ?></h5>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <span class="badge badge-species-<?php echo strtolower($record['species']); ?>">
                                                        <?php echo htmlspecialchars($record['species']); ?>
                                                    </span>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($record['breed']); ?></span>
                                                    <span class="badge service-badge"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                                </div>
                                                <small class="text-muted">
                                                    Owner: <?php echo htmlspecialchars($record['owner_name']); ?> | 
                                                    Service Date: <?php echo date('M j, Y', strtotime($record['service_date'])); ?>
                                                    <?php if ($record['service_time']): ?> at <?php echo htmlspecialchars($record['service_time']); ?><?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editRecordModal<?php echo $record['record_id']; ?>">
                                                        <i class="fas fa-edit me-2"></i>Edit Record
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="vet_records.php?delete=<?php echo $record['record_id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this medical record?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Pet Information -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Age: <?php echo htmlspecialchars($record['age']); ?></small>
                                            <small class="text-muted d-block">Color: <?php echo htmlspecialchars($record['color']); ?></small>
                                            <small class="text-muted d-block">Sex: <?php echo htmlspecialchars($record['sex']); ?></small>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if ($record['weight']): ?>
                                                <small class="text-muted d-block">Weight: <?php echo htmlspecialchars($record['weight']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($record['microchip']): ?>
                                                <small class="text-muted d-block">Microchip: <?php echo htmlspecialchars($record['microchip']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Service Information -->
                                    <div class="vital-stats">
                                        <h6 class="mb-3"><i class="fas fa-stethoscope me-2"></i>Service Details</h6>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="vital-item">
                                                    <span class="fw-semibold">Service Type:</span>
                                                    <span class="text-primary"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                                </div>
                                                <div class="vital-item">
                                                    <span class="fw-semibold">Description:</span>
                                                    <span><?php echo htmlspecialchars($record['service_description']); ?></span>
                                                </div>
                                                <div class="vital-item">
                                                    <span class="fw-semibold">Veterinarian:</span>
                                                    <span class="text-info"><?php echo htmlspecialchars($record['veterinarian']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <?php if ($record['notes']): ?>
                                        <div class="mt-3">
                                            <h6 class="text-secondary"><i class="fas fa-notes-medical me-2"></i>Notes</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Reminder -->
                                    <?php if ($record['reminder_description']): ?>
                                        <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded">
                                            <h6 class="mb-2"><i class="fas fa-bell me-2"></i>Reminder</h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($record['reminder_description']); ?></p>
                                            <?php if ($record['reminder_due_date']): ?>
                                                <small class="text-warning fw-semibold">
                                                    Due: <?php echo date('M j, Y', strtotime($record['reminder_due_date'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Record Modal -->
                                <div class="modal fade" id="editRecordModal<?php echo $record['record_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Medical Record - <?php echo htmlspecialchars($record['pet_name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="vet_records.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Service Date *</label>
                                                            <input type="date" class="form-control" name="service_date" 
                                                                   value="<?php echo htmlspecialchars($record['service_date']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Service Time</label>
                                                            <input type="time" class="form-control" name="service_time" 
                                                                   value="<?php echo htmlspecialchars($record['service_time']); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Service Type *</label>
                                                            <select class="form-select" name="service_type" required>
                                                                <option value="">Select service type...</option>
                                                                <option value="Checkup" <?php echo $record['service_type'] == 'Checkup' ? 'selected' : ''; ?>>Checkup</option>
                                                                <option value="Vaccination" <?php echo $record['service_type'] == 'Vaccination' ? 'selected' : ''; ?>>Vaccination</option>
                                                                <option value="Surgery" <?php echo $record['service_type'] == 'Surgery' ? 'selected' : ''; ?>>Surgery</option>
                                                                <option value="Dental" <?php echo $record['service_type'] == 'Dental' ? 'selected' : ''; ?>>Dental</option>
                                                                <option value="Grooming" <?php echo $record['service_type'] == 'Grooming' ? 'selected' : ''; ?>>Grooming</option>
                                                                <option value="Emergency" <?php echo $record['service_type'] == 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                                                                <option value="Other" <?php echo $record['service_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Weight</label>
                                                            <input type="text" class="form-control" name="weight" 
                                                                   value="<?php echo htmlspecialchars($record['weight']); ?>" placeholder="e.g., 5.2 kg">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Service Description *</label>
                                                        <textarea class="form-control" name="service_description" rows="3" required><?php echo htmlspecialchars($record['service_description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes</label>
                                                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($record['notes']); ?></textarea>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-8 mb-3">
                                                            <label class="form-label">Reminder Description</label>
                                                            <input type="text" class="form-control" name="reminder_description" 
                                                                   value="<?php echo htmlspecialchars($record['reminder_description']); ?>" placeholder="e.g., Next vaccination due">
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Reminder Due Date</label>
                                                            <input type="date" class="form-control" name="reminder_due_date" 
                                                                   value="<?php echo htmlspecialchars($record['reminder_due_date']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_record" class="btn btn-primary">Update Record</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add New Record -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Add New Record</h5>
                    <form method="POST" action="vet_records.php">
                        <div class="mb-3">
                            <label for="pet_id" class="form-label">Select Pet *</label>
                            <select class="form-select" id="pet_id" name="pet_id" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                    <option value="<?php echo $pet['pet_id']; ?>">
                                        <?php echo htmlspecialchars($pet['pet_name']); ?> (<?php echo htmlspecialchars($pet['species']); ?> - Owner: <?php echo htmlspecialchars($pet['owner_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Date *</label>
                                <input type="date" class="form-control" name="service_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Time</label>
                                <input type="time" class="form-control" name="service_time" value="<?php echo date('H:i'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Type *</label>
                                <select class="form-select" name="service_type" required>
                                    <option value="">Select service type...</option>
                                    <option value="Checkup">Checkup</option>
                                    <option value="Vaccination">Vaccination</option>
                                    <option value="Surgery">Surgery</option>
                                    <option value="Dental">Dental</option>
                                    <option value="Grooming">Grooming</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Weight</label>
                                <input type="text" class="form-control" name="weight" placeholder="e.g., 5.2 kg">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Description *</label>
                            <textarea class="form-control" name="service_description" rows="3" placeholder="Describe the service provided..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Reminder Description</label>
                                <input type="text" class="form-control" name="reminder_description" placeholder="e.g., Next vaccination due">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reminder Due Date</label>
                                <input type="date" class="form-control" name="reminder_due_date">
                            </div>
                        </div>
                        <button type="submit" name="add_record" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Medical Record
                        </button>
                    </form>
                </div>

                <!-- Quick Stats -->
                <div class="card-custom mt-4">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h5>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-primary"><?php echo count($pets); ?></h4>
                                <small>Total Pets</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-success"><?php echo $today_records; ?></h4>
                                <small>Today</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-warning"><?php echo $recent_records; ?></h4>
                                <small>This Week</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-info">Dr. <?php echo explode(' ', $vet['name'])[0]; ?></h4>
                                <small>Veterinarian</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime();
        setInterval(updateDateTime, 60000);
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    // Auto-close alerts after 5 seconds
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

