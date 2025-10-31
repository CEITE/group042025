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
        // Add new medical record
        $pet_id = $_POST['pet_id'];
        $diagnosis = trim($_POST['diagnosis']);
        $treatment = trim($_POST['treatment']);
        $medications = trim($_POST['medications']);
        $notes = trim($_POST['notes']);
        $weight = $_POST['weight'] ?: null;
        $temperature = $_POST['temperature'] ?: null;
        $next_checkup = $_POST['next_checkup'] ?: null;
        
        if (empty($diagnosis) || empty($treatment)) {
            $_SESSION['error'] = "Diagnosis and treatment are required fields.";
        } else {
            $insert_stmt = $conn->prepare("
                INSERT INTO medical_records 
                (pet_id, vet_id, diagnosis, treatment, medications, notes, weight, temperature, next_checkup_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if ($insert_stmt) {
                $insert_stmt->bind_param("iissssddss", $pet_id, $vet_id, $diagnosis, $treatment, $medications, $notes, $weight, $temperature, $next_checkup);
                if ($insert_stmt->execute()) {
                    $_SESSION['success'] = "Medical record added successfully!";
                } else {
                    $_SESSION['error'] = "Error adding medical record: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
        }
    } elseif (isset($_POST['update_record'])) {
        // Update existing medical record
        $record_id = $_POST['record_id'];
        $diagnosis = trim($_POST['diagnosis']);
        $treatment = trim($_POST['treatment']);
        $medications = trim($_POST['medications']);
        $notes = trim($_POST['notes']);
        $weight = $_POST['weight'] ?: null;
        $temperature = $_POST['temperature'] ?: null;
        $next_checkup = $_POST['next_checkup'] ?: null;
        
        $update_stmt = $conn->prepare("
            UPDATE medical_records 
            SET diagnosis = ?, treatment = ?, medications = ?, notes = ?, weight = ?, temperature = ?, next_checkup_date = ?, updated_at = NOW()
            WHERE record_id = ? AND vet_id = ?
        ");
        if ($update_stmt) {
            $update_stmt->bind_param("ssssddssii", $diagnosis, $treatment, $medications, $notes, $weight, $temperature, $next_checkup, $record_id, $vet_id);
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
    $delete_stmt = $conn->prepare("DELETE FROM medical_records WHERE record_id = ? AND vet_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $record_id, $vet_id);
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
    SELECT p.pet_id, p.name as pet_name, p.species, p.breed, u.name as owner_name 
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

// Fetch medical records with pet and owner info
$records_stmt = $conn->prepare("
    SELECT mr.*, p.name as pet_name, p.species, p.breed, p.age, p.gender, u.name as owner_name, u.email as owner_email
    FROM medical_records mr
    JOIN pets p ON mr.pet_id = p.pet_id
    JOIN users u ON p.owner_id = u.user_id
    WHERE mr.vet_id = ?
    ORDER BY mr.created_at DESC
");
if (!$records_stmt) {
    die("Prepare failed: " . $conn->error);
}
$records_stmt->bind_param("i", $vet_id);
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
    $record_date = date('Y-m-d', strtotime($record['created_at']));
    if ($record_date == $today) {
        $today_records++;
    }
    if ($record_date >= $week_ago) {
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
                                                    <span class="badge bg-light text-dark">Age: <?php echo htmlspecialchars($record['age']); ?></span>
                                                </div>
                                                <small class="text-muted">
                                                    Owner: <?php echo htmlspecialchars($record['owner_name']); ?> | 
                                                    Record Date: <?php echo date('M j, Y g:i A', strtotime($record['created_at'])); ?>
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

                                    <!-- Vital Stats -->
                                    <?php if ($record['weight'] || $record['temperature']): ?>
                                        <div class="vital-stats">
                                            <h6 class="mb-3"><i class="fas fa-heartbeat me-2"></i>Vital Statistics</h6>
                                            <div class="row">
                                                <?php if ($record['weight']): ?>
                                                    <div class="col-md-6">
                                                        <div class="vital-item">
                                                            <span class="fw-semibold">Weight:</span>
                                                            <span class="text-primary"><?php echo htmlspecialchars($record['weight']); ?> kg</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($record['temperature']): ?>
                                                    <div class="col-md-6">
                                                        <div class="vital-item">
                                                            <span class="fw-semibold">Temperature:</span>
                                                            <span class="text-warning"><?php echo htmlspecialchars($record['temperature']); ?> °C</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Medical Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <h6 class="text-primary"><i class="fas fa-diagnoses me-2"></i>Diagnosis</h6>
                                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                            
                                            <h6 class="text-success"><i class="fas fa-pills me-2"></i>Medications</h6>
                                            <p class="mb-0"><?php echo $record['medications'] ? nl2br(htmlspecialchars($record['medications'])) : 'No medications prescribed'; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-info"><i class="fas fa-stethoscope me-2"></i>Treatment</h6>
                                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                            
                                            <h6 class="text-secondary"><i class="fas fa-notes-medical me-2"></i>Additional Notes</h6>
                                            <p class="mb-0"><?php echo $record['notes'] ? nl2br(htmlspecialchars($record['notes'])) : 'No additional notes'; ?></p>
                                        </div>
                                    </div>

                                    <?php if ($record['next_checkup_date']): ?>
                                        <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded">
                                            <h6 class="mb-2"><i class="fas fa-calendar-check me-2"></i>Next Checkup</h6>
                                            <p class="mb-0 text-warning fw-semibold">
                                                <?php echo date('M j, Y', strtotime($record['next_checkup_date'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Record Modal -->
                                <div class="modal fade" id="editRecordModal<?php echo $record['record_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Medical Record</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="vet_records.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Weight (kg)</label>
                                                            <input type="number" step="0.1" class="form-control" name="weight" 
                                                                   value="<?php echo htmlspecialchars($record['weight'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Temperature (°C)</label>
                                                            <input type="number" step="0.1" class="form-control" name="temperature" 
                                                                   value="<?php echo htmlspecialchars($record['temperature'] ?? ''); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Diagnosis *</label>
                                                        <textarea class="form-control" name="diagnosis" rows="3" required><?php echo htmlspecialchars($record['diagnosis']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Treatment *</label>
                                                        <textarea class="form-control" name="treatment" rows="3" required><?php echo htmlspecialchars($record['treatment']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Medications</label>
                                                        <textarea class="form-control" name="medications" rows="2"><?php echo htmlspecialchars($record['medications'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Additional Notes</label>
                                                        <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Next Checkup Date</label>
                                                        <input type="date" class="form-control" name="next_checkup" 
                                                               value="<?php echo $record['next_checkup_date'] ? date('Y-m-d', strtotime($record['next_checkup_date'])) : ''; ?>">
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

            <!-- Quick Actions & Add Record -->
            <div class="col-lg-4">
                <!-- Add New Record -->
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
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="weight" placeholder="e.g., 5.2">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Temperature (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="temperature" placeholder="e.g., 38.5">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Diagnosis *</label>
                            <textarea class="form-control" name="diagnosis" rows="3" placeholder="Enter diagnosis..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Treatment *</label>
                            <textarea class="form-control" name="treatment" rows="3" placeholder="Enter treatment plan..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medications</label>
                            <textarea class="form-control" name="medications" rows="2" placeholder="List prescribed medications..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Any additional observations..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next Checkup Date</label>
                            <input type="date" class="form-control" name="next_checkup">
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