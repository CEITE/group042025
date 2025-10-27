<?php
session_start();
include("conn.php");

// Check if user is logged in and is a vet
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vet') {
    header("Location: login.php");
    exit();
}

$vet_id = $_SESSION['user_id'];

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $vet_id);
$stmt->execute();
$vet = $stmt->get_result()->fetch_assoc();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Handle search and filters
$search = $_GET['search'] ?? '';
$species_filter = $_GET['species'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';

// Build query for patients
$query = "
    SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone,
           COUNT(a.appointment_id) as total_visits,
           MAX(a.appointment_date) as last_visit,
           MAX(a.created_at) as last_appointment
    FROM pets p 
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN appointments a ON p.pet_id = a.pet_id
    WHERE 1=1
";

$params = [];
$types = '';

// Add search filter
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR u.name LIKE ? OR p.breed LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

// Add species filter
if (!empty($species_filter) && in_array($species_filter, ['dog', 'cat'])) {
    $query .= " AND p.species = ?";
    $params[] = $species_filter;
    $types .= 's';
}

$query .= " GROUP BY p.pet_id";

// Add sorting
$allowed_sorts = ['name', 'species', 'last_visit', 'total_visits', 'created_at'];
if (in_array($sort_by, $allowed_sorts)) {
    if ($sort_by == 'last_visit') {
        $query .= " ORDER BY last_visit DESC";
    } else {
        $query .= " ORDER BY p.$sort_by ASC";
    }
} else {
    $query .= " ORDER BY p.name ASC";
}

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count total patients
$total_patients = count($patients);

// Count by species
$species_count_stmt = $conn->prepare("
    SELECT species, COUNT(*) as count 
    FROM pets 
    GROUP BY species
");
$species_count_stmt->execute();
$species_counts = $species_count_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dog_count = 0;
$cat_count = 0;
foreach ($species_counts as $count) {
    if ($count['species'] == 'dog') $dog_count = $count['count'];
    if ($count['species'] == 'cat') $cat_count = $count['count'];
}

// Handle medical record updates
if (isset($_POST['update_medical_record'])) {
    $pet_id = $_POST['pet_id'];
    $medical_notes = $_POST['medical_notes'] ?? '';
    $previous_conditions = $_POST['previous_conditions'] ?? '';
    $vaccination_history = $_POST['vaccination_history'] ?? '';
    $surgical_history = $_POST['surgical_history'] ?? '';
    $medication_history = $_POST['medication_history'] ?? '';
    $weight = $_POST['weight'] ?? null;
    $next_vet_visit = $_POST['next_vet_visit'] ?? null;
    $rabies_vaccine_date = $_POST['rabies_vaccine_date'] ?? null;
    $dhpp_vaccine_date = $_POST['dhpp_vaccine_date'] ?? null;
    $is_spayed_neutered = isset($_POST['is_spayed_neutered']) ? 1 : 0;
    $spay_neuter_date = $_POST['spay_neuter_date'] ?? null;
    
    $update_stmt = $conn->prepare("
        UPDATE pets SET 
        medical_notes = ?, previous_conditions = ?, vaccination_history = ?, 
        surgical_history = ?, medication_history = ?, weight = ?, next_vet_visit = ?,
        rabies_vaccine_date = ?, dhpp_vaccine_date = ?, is_spayed_neutered = ?, spay_neuter_date = ?,
        medical_history_updated_at = NOW()
        WHERE pet_id = ?
    ");
    
    $update_stmt->bind_param("sssssdsssisi", 
        $medical_notes, $previous_conditions, $vaccination_history,
        $surgical_history, $medication_history, $weight, $next_vet_visit,
        $rabies_vaccine_date, $dhpp_vaccine_date, $is_spayed_neutered, $spay_neuter_date,
        $pet_id
    );
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Medical record updated successfully!";
        header("Location: vet_patients.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating medical record: " . $conn->error;
    }
}

// Handle quick vaccination update
if (isset($_POST['update_vaccination'])) {
    $pet_id = $_POST['pet_id'];
    $vaccine_type = $_POST['vaccine_type'];
    $vaccine_date = $_POST['vaccine_date'];
    
    if ($vaccine_type == 'rabies') {
        $update_stmt = $conn->prepare("UPDATE pets SET rabies_vaccine_date = ? WHERE pet_id = ?");
    } else {
        $update_stmt = $conn->prepare("UPDATE pets SET dhpp_vaccine_date = ? WHERE pet_id = ?");
    }
    
    $update_stmt->bind_param("si", $vaccine_date, $pet_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = ucfirst($vaccine_type) . " vaccination date updated!";
        header("Location: vet_patients.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating vaccination: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Patients - VetCareQR</title>
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
            --green: #2ecc71;
            --orange: #f39c12;
            --red: #e74c3c;
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
        
        /* Patient Cards */
        .patient-card {
            border-left: 4px solid var(--blue);
            background: linear-gradient(135deg, #e3f2fd, #e1f5fe);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .pet-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--light-pink);
            color: var(--dark-pink);
            border: 3px solid white;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
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
        
        /* Action Buttons */
        .btn-medical { 
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-medical:hover {
            background: linear-gradient(135deg, #27ae60, #229954);
            transform: translateY(-2px);
        }
        
        .btn-vaccine { 
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-vaccine:hover {
            background: linear-gradient(135deg, #2980b9, #2471a3);
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        /* Vaccine Status */
        .vaccine-due { color: #e74c3c; font-weight: bold; }
        .vaccine-upcoming { color: #f39c12; font-weight: bold; }
        .vaccine-current { color: #27ae60; font-weight: bold; }
        
        /* Search and Filter */
        .search-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
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
        <div class="brand"><i class="fa-solid fa-paw"></i> VetCareQR</div>
        <div class="profile">
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                     alt="Vet" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            </div>
            <h6 id="vetNameSidebar">Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($vet['role']); ?></small>
        </div>

        <a href="vet_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_appointments.php">
            <div class="icon"><i class="fa-solid fa-calendar-check"></i></div> Appointments
        </a>
        <a href="vet_patients.php" class="active">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> Patients
        </a>
        <a href="vet_records.php">
            <div class="icon"><i class="fa-solid fa-file-medical"></i></div> Medical Records
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
                <h5 class="mb-0">Patient Management</h5>
                <small class="text-muted">View and manage all registered pets</small>
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="stats-number"><?php echo $total_patients; ?></div>
                    <div class="stats-label">Total Patients</div>
                    <i class="fas fa-paw"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <div class="stats-number"><?php echo $dog_count; ?></div>
                    <div class="stats-label">Dogs</div>
                    <i class="fas fa-dog"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <div class="stats-number"><?php echo $cat_count; ?></div>
                    <div class="stats-label">Cats</div>
                    <i class="fas fa-cat"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                    <div class="stats-number"><?php echo count(array_filter($patients, function($p) { return $p['total_visits'] > 0; })); ?></div>
                    <div class="stats-label">Active Patients</div>
                    <i class="fas fa-heartbeat"></i>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-box">
            <form method="GET" action="vet_patients.php">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Patients</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search by pet name, owner, or breed..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Species</label>
                        <select class="form-select" name="species" onchange="this.form.submit()">
                            <option value="">All Species</option>
                            <option value="dog" <?php echo $species_filter == 'dog' ? 'selected' : ''; ?>>Dogs</option>
                            <option value="cat" <?php echo $species_filter == 'cat' ? 'selected' : ''; ?>>Cats</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort" onchange="this.form.submit()">
                            <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="species" <?php echo $sort_by == 'species' ? 'selected' : ''; ?>>Species</option>
                            <option value="last_visit" <?php echo $sort_by == 'last_visit' ? 'selected' : ''; ?>>Last Visit</option>
                            <option value="total_visits" <?php echo $sort_by == 'total_visits' ? 'selected' : ''; ?>>Most Visits</option>
                            <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="vet_patients.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-refresh me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Patients List -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fas fa-paw me-2 text-primary"></i>All Patients</h4>
                <span class="badge bg-primary"><?php echo $total_patients; ?> Patients</span>
            </div>
            
            <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <i class="fas fa-paw"></i>
                    <h5>No Patients Found</h5>
                    <p class="text-muted">No patients match your search criteria.</p>
                    <a href="vet_patients.php" class="btn btn-primary mt-3">
                        <i class="fas fa-refresh me-2"></i> View All Patients
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($patients as $patient): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card patient-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="pet-avatar me-3">
                                                <i class="fas fa-<?php echo strtolower($patient['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($patient['name']); ?></h5>
                                                <small class="text-muted">
                                                    Owner: <?php echo htmlspecialchars($patient['owner_name']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst($patient['species']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted">Breed & Age</small>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars($patient['breed'] ?? 'Unknown'); ?> • 
                                                <?php echo htmlspecialchars($patient['age'] ?? 'Unknown'); ?> years
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Gender</small>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars($patient['gender'] ?? 'Unknown'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted">Total Visits</small>
                                            <div class="fw-semibold">
                                                <?php echo $patient['total_visits']; ?> visits
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Last Visit</small>
                                            <div class="fw-semibold">
                                                <?php echo $patient['last_visit'] ? date('M j, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vaccination Status -->
                                    <div class="vaccination-status mb-3">
                                        <?php 
                                        $today = new DateTime();
                                        $rabies_due = $patient['rabies_vaccine_date'] ? new DateTime($patient['rabies_vaccine_date']) : null;
                                        $dhpp_due = $patient['dhpp_vaccine_date'] ? new DateTime($patient['dhpp_vaccine_date']) : null;
                                        
                                        if ($rabies_due && $rabies_due <= $today): ?>
                                            <div class="mb-1">
                                                <small class="text-muted">Rabies:</small>
                                                <span class="vaccine-due">OVERDUE</span>
                                            </div>
                                        <?php elseif ($rabies_due && $rabies_due <= (clone $today)->modify('+30 days')): ?>
                                            <div class="mb-1">
                                                <small class="text-muted">Rabies:</small>
                                                <span class="vaccine-upcoming">Due Soon</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($dhpp_due && $dhpp_due <= $today): ?>
                                            <div class="mb-1">
                                                <small class="text-muted">DHPP:</small>
                                                <span class="vaccine-due">OVERDUE</span>
                                            </div>
                                        <?php elseif ($dhpp_due && $dhpp_due <= (clone $today)->modify('+30 days')): ?>
                                            <div class="mb-1">
                                                <small class="text-muted">DHPP:</small>
                                                <span class="vaccine-upcoming">Due Soon</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <button class="btn btn-medical btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#medicalRecordModal"
                                                onclick="loadPetData(<?php echo $patient['pet_id']; ?>)">
                                            <i class="fas fa-file-medical me-1"></i> Medical Record
                                        </button>
                                        <button class="btn btn-vaccine btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#vaccineModal"
                                                onclick="setVaccinePet(<?php echo $patient['pet_id']; ?>)">
                                            <i class="fas fa-syringe me-1"></i> Vaccine
                                        </button>
                                        <a href="vet_appointments.php?pet_id=<?php echo $patient['pet_id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-calendar-plus me-1"></i> Schedule
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

<!-- Medical Record Modal -->
<div class="modal fade" id="medicalRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_patients.php">
                <input type="hidden" name="update_medical_record" value="1">
                <input type="hidden" name="pet_id" id="medicalRecordPetId">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pet</label>
                            <select class="form-select" name="pet_id" id="petSelect" required onchange="loadPetData(this.value)">
                                <option value="">Choose a pet...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['pet_id']; ?>">
                                        <?php echo htmlspecialchars($patient['name']); ?> 
                                        (<?php echo htmlspecialchars($patient['species']); ?> - <?php echo htmlspecialchars($patient['owner_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="weight" id="petWeight" placeholder="e.g., 5.2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Next Visit</label>
                            <input type="date" class="form-control" name="next_vet_visit" id="nextVetVisit">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Rabies Vaccine Date</label>
                            <input type="date" class="form-control" name="rabies_vaccine_date" id="rabiesVaccineDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DHPP Vaccine Date</label>
                            <input type="date" class="form-control" name="dhpp_vaccine_date" id="dhppVaccineDate">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_spayed_neutered" id="isSpayedNeutered">
                                <label class="form-check-label" for="isSpayedNeutered">
                                    Spayed/Neutered
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Spay/Neuter Date</label>
                            <input type="date" class="form-control" name="spay_neuter_date" id="spayNeuterDate">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Medical Notes</label>
                        <textarea class="form-control" name="medical_notes" rows="3" 
                                  placeholder="Current medical observations, treatment notes..." id="medicalNotes"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Previous Conditions</label>
                        <textarea class="form-control" name="previous_conditions" rows="2" 
                                  placeholder="Known medical conditions, allergies..." id="previousConditions"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vaccination History</label>
                        <textarea class="form-control" name="vaccination_history" rows="2" 
                                  placeholder="Vaccination records and history..." id="vaccinationHistory"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Surgical History</label>
                        <textarea class="form-control" name="surgical_history" rows="2" 
                                  placeholder="Previous surgeries and procedures..." id="surgicalHistory"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Medication History</label>
                        <textarea class="form-control" name="medication_history" rows="2" 
                                  placeholder="Current and previous medications..." id="medicationHistory"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Medical Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vaccine Update Modal -->
<div class="modal fade" id="vaccineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Vaccination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_patients.php">
                <input type="hidden" name="update_vaccination" value="1">
                <input type="hidden" name="pet_id" id="vaccinePetId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vaccine Type</label>
                        <select class="form-select" name="vaccine_type" required>
                            <option value="">Select vaccine type...</option>
                            <option value="rabies">Rabies Vaccine</option>
                            <option value="dhpp">DHPP Vaccine</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vaccination Date</label>
                        <input type="date" class="form-control" name="vaccine_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will update the vaccination record and set reminders for the next dose.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Vaccine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
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
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    // Load pet data for medical record modal
    function loadPetData(petId) {
        document.getElementById('medicalRecordPetId').value = petId;
        document.getElementById('petSelect').value = petId;
        
        // In a real implementation, you would fetch pet data via AJAX
        // For now, we'll just set the form values to empty
        document.getElementById('petWeight').value = '';
        document.getElementById('nextVetVisit').value = '';
        document.getElementById('rabiesVaccineDate').value = '';
        document.getElementById('dhppVaccineDate').value = '';
        document.getElementById('isSpayedNeutered').checked = false;
        document.getElementById('spayNeuterDate').value = '';
        document.getElementById('medicalNotes').value = '';
        document.getElementById('previousConditions').value = '';
        document.getElementById('vaccinationHistory').value = '';
        document.getElementById('surgicalHistory').value = '';
        document.getElementById('medicationHistory').value = '';
        
        console.log('Loading data for pet ID:', petId);
    }

    // Set pet ID for vaccine modal
    function setVaccinePet(petId) {
        document.getElementById('vaccinePetId').value = petId;
    }
</script>
</body>
</html>