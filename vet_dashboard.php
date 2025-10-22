<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ 1. Check if vet is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vet') {
    header("Location: login_vet.php");
    exit();
}

$vet_id = $_SESSION['user_id'];

// ✅ 2. Fetch logged-in vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

if (!$vet) {
    die("Vet not found in database");
}

// Set default profile picture if none exists
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// ✅ 3. SIMPLIFIED: Fetch all pets with basic info
$pets_query = "
SELECT 
    p.pet_id,
    p.name AS pet_name,
    p.species,
    p.breed,
    p.age,
    p.color,
    p.weight,
    p.gender,
    u.name AS owner_name,
    u.email AS owner_email,
    u.phone_number AS owner_phone
FROM pets p
JOIN users u ON p.user_id = u.user_id
ORDER BY p.name
";

$pets_result = $conn->query($pets_query);
if (!$pets_result) {
    die("Error fetching pets: " . $conn->error);
}
$pets = $pets_result->fetch_all(MYSQLI_ASSOC);

// ✅ 4. Fetch recent medical records separately
$records_query = "
SELECT 
    m.record_id,
    m.pet_id,
    m.service_date,
    m.service_type,
    m.service_description,
    m.weight,
    m.weight_date,
    m.reminder_description,
    m.reminder_due_date,
    m.notes,
    m.veterinarian,
    p.name AS pet_name,
    u.name AS owner_name
FROM pet_medical_records m
JOIN pets p ON m.pet_id = p.pet_id
JOIN users u ON p.user_id = u.user_id
ORDER BY m.service_date DESC
LIMIT 50
";

$records_result = $conn->query($records_query);
if (!$records_result) {
    die("Error fetching records: " . $conn->error);
}
$recent_records = $records_result->fetch_all(MYSQLI_ASSOC);

// ✅ 5. Dashboard statistics
$totalPets = count($pets);
$totalOwners = count(array_unique(array_column($pets, 'owner_name')));

// Count recent visits (last 30 days)
$recentVisits = 0;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
foreach ($recent_records as $record) {
    if ($record['service_date'] && $record['service_date'] >= $thirtyDaysAgo) {
        $recentVisits++;
    }
}

// Count pending follow-ups
$pendingFollowups = 0;
$today = date('Y-m-d');
foreach ($recent_records as $record) {
    if ($record['reminder_due_date'] && $record['reminder_due_date'] >= $today) {
        $pendingFollowups++;
    }
}

// ✅ 6. Handle medical record updates (SIMPLIFIED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_medical_record') {
        $pet_id = $_POST['pet_id'];
        $service_date = $_POST['service_date'];
        $service_type = $_POST['service_type'];
        $service_description = $_POST['service_description'];
        $weight = !empty($_POST['weight']) ? $_POST['weight'] : null;
        $weight_date = !empty($_POST['weight_date']) ? $_POST['weight_date'] : null;
        $reminder_description = !empty($_POST['reminder_description']) ? $_POST['reminder_description'] : null;
        $reminder_due_date = !empty($_POST['reminder_due_date']) ? $_POST['reminder_due_date'] : null;
        $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;
        
        $insert_query = "
        INSERT INTO pet_medical_records 
        (pet_id, service_date, service_type, service_description, weight, weight_date, reminder_description, reminder_due_date, notes, veterinarian) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        if ($stmt) {
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
            } else {
                $_SESSION['error'] = "Error adding medical record: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
        
        header("Location: vet_dashboard.php");
        exit();
    }
    
    // Handle update medical record
    if ($_POST['action'] === 'update_medical_record') {
        $record_id = $_POST['record_id'];
        $service_date = $_POST['service_date'];
        $service_type = $_POST['service_type'];
        $service_description = $_POST['service_description'];
        $weight = !empty($_POST['weight']) ? $_POST['weight'] : null;
        $weight_date = !empty($_POST['weight_date']) ? $_POST['weight_date'] : null;
        $reminder_description = !empty($_POST['reminder_description']) ? $_POST['reminder_description'] : null;
        $reminder_due_date = !empty($_POST['reminder_due_date']) ? $_POST['reminder_due_date'] : null;
        $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;
        
        $update_query = "
        UPDATE pet_medical_records 
        SET service_date = ?, service_type = ?, service_description = ?, weight = ?, weight_date = ?, 
            reminder_description = ?, reminder_due_date = ?, notes = ?, veterinarian = ?
        WHERE record_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
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
            } else {
                $_SESSION['error'] = "Error updating medical record: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
        
        header("Location: vet_dashboard.php");
        exit();
    }
    
    // Handle delete medical record
    if ($_POST['action'] === 'delete_medical_record') {
        $record_id = $_POST['record_id'];
        
        $delete_query = "DELETE FROM pet_medical_records WHERE record_id = ?";
        $stmt = $conn->prepare($delete_query);
        if ($stmt) {
            $stmt->bind_param("i", $record_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Medical record deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting medical record: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database error: " . $conn->error;
        }
        
        header("Location: vet_dashboard.php");
        exit();
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
        
        .btn-vet {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-vet:hover {
            background: var(--primary-dark);
            color: white;
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
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .badge-service {
            background: var(--primary);
        }
        
        .badge-vaccine {
            background: var(--success);
        }
        
        .badge-checkup {
            background: var(--warning);
        }
        
        .badge-emergency {
            background: var(--accent);
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
            <div class="text-end">
                <strong id="currentDate"></strong><br>
                <small id="currentTime"></small>
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
                    <i class="fa-solid fa-calendar-check"></i>
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
                    <a href="vet_records.php" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-file-medical me-1"></i> Medical History
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="#" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-print me-1"></i> Generate Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Medical Records -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-clock me-2"></i>Recent Medical Records</h4>
                <span class="badge bg-primary"><?php echo count($recent_records); ?> Records</span>
            </div>
            
            <?php if (empty($recent_records)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-file-medical"></i>
                    <h5>No Medical Records Found</h5>
                    <p class="text-muted">No medical records have been added yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Pet</th>
                                <th>Owner</th>
                                <th>Service Type</th>
                                <th>Description</th>
                                <th>Veterinarian</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['service_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['pet_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['owner_name']); ?></td>
                                    <td>
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
                                    </td>
                                    <td><?php echo htmlspecialchars($record['service_description']); ?></td>
                                    <td><?php echo htmlspecialchars($record['veterinarian']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editRecord(<?php echo $record['record_id']; ?>)"
                                                    title="Edit Record">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteRecord(<?php echo $record['record_id']; ?>)"
                                                    title="Delete Record">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Patients -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-paw me-2"></i>All Patients</h4>
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
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($pet['pet_name']); ?></h5>
                                    <p class="card-text">
                                        <strong>Species:</strong> <?php echo htmlspecialchars($pet['species']); ?><br>
                                        <strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?><br>
                                        <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years<br>
                                        <strong>Owner:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?><br>
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($pet['owner_phone']); ?>
                                    </p>
                                    <button class="btn btn-sm btn-vet" 
                                            onclick="addRecordForPet(<?php echo $pet['pet_id']; ?>, '<?php echo addslashes($pet['pet_name']); ?>')">
                                        <i class="fa-solid fa-plus me-1"></i> Add Record
                                    </button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Real-time clock
function updateDateTime() {
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}
setInterval(updateDateTime, 1000);
updateDateTime();

// Add record for specific pet
function addRecordForPet(petId, petName) {
    document.getElementById('modalPetId').value = petId;
    document.getElementById('addRecordModalTitle').textContent = `Add Medical Record for ${petName}`;
    
    // Reset form
    document.getElementById('service_description').value = '';
    document.getElementById('weight').value = '';
    document.getElementById('reminder_description').value = '';
    document.getElementById('notes').value = '';
    
    new bootstrap.Modal(document.getElementById('addRecordModal')).show();
}

// Edit record functionality
function editRecord(recordId) {
    document.getElementById('editRecordId').value = recordId;
    
    // Show loading state
    document.getElementById('editRecordModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading record details...</p>
        </div>
    `;
    
    const editModal = new bootstrap.Modal(document.getElementById('editRecordModal'));
    editModal.show();
    
    // Fetch record data via AJAX
    fetch('get_medical_record.php?record_id=' + recordId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editRecordModalBody').innerHTML = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_service_date" class="form-label">Service Date *</label>
                            <input type="date" class="form-control" id="edit_service_date" name="service_date" 
                                   value="${data.record.service_date}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_service_type" class="form-label">Service Type *</label>
                            <select class="form-select" id="edit_service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="Vaccination" ${data.record.service_type === 'Vaccination' ? 'selected' : ''}>Vaccination</option>
                                <option value="Check-up" ${data.record.service_type === 'Check-up' ? 'selected' : ''}>Check-up</option>
                                <option value="Dental Cleaning" ${data.record.service_type === 'Dental Cleaning' ? 'selected' : ''}>Dental Cleaning</option>
                                <option value="Surgery" ${data.record.service_type === 'Surgery' ? 'selected' : ''}>Surgery</option>
                                <option value="Emergency" ${data.record.service_type === 'Emergency' ? 'selected' : ''}>Emergency</option>
                                <option value="Grooming" ${data.record.service_type === 'Grooming' ? 'selected' : ''}>Grooming</option>
                                <option value="Laboratory Test" ${data.record.service_type === 'Laboratory Test' ? 'selected' : ''}>Laboratory Test</option>
                                <option value="Other" ${data.record.service_type === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_service_description" class="form-label">Service Description *</label>
                        <textarea class="form-control" id="edit_service_description" name="service_description" 
                                  rows="3" placeholder="Describe the service performed..." required>${data.record.service_description || ''}</textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="edit_weight" name="weight" 
                                   value="${data.record.weight || ''}" placeholder="Enter weight in kg">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_weight_date" class="form-label">Weight Date</label>
                            <input type="date" class="form-control" id="edit_weight_date" name="weight_date" 
                                   value="${data.record.weight_date || ''}">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_reminder_description" class="form-label">Reminder Description</label>
                            <input type="text" class="form-control" id="edit_reminder_description" name="reminder_description" 
                                   value="${data.record.reminder_description || ''}" placeholder="e.g., Next vaccination due">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_reminder_due_date" class="form-label">Reminder Due Date</label>
                            <input type="date" class="form-control" id="edit_reminder_due_date" name="reminder_due_date" 
                                   value="${data.record.reminder_due_date || ''}">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" 
                                  rows="3" placeholder="Any additional notes or observations...">${data.record.notes || ''}</textarea>
                    </div>
                `;
            } else {
                document.getElementById('editRecordModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading record: ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('editRecordModalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Network error: Could not load record details.
                </div>
            `;
            console.error('Error:', error);
        });
}

// Delete record functionality
function deleteRecord(recordId) {
    document.getElementById('deleteRecordId').value = recordId;
    new bootstrap.Modal(document.getElementById('deleteRecordModal')).show();
}

// Auto-close alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>

