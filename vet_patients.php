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
           MAX(a.created_at) as last_appointment,
           (SELECT COUNT(*) FROM pet_medical_records pmr WHERE pmr.pet_id = p.pet_id) as total_records,
           (SELECT MAX(service_date) FROM pet_medical_records pmr WHERE pmr.pet_id = p.pet_id) as last_record_date
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
$allowed_sorts = ['name', 'species', 'last_visit', 'total_visits', 'created_at', 'last_record_date'];
if (in_array($sort_by, $allowed_sorts)) {
    if ($sort_by == 'last_visit' || $sort_by == 'last_record_date') {
        $query .= " ORDER BY $sort_by DESC";
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

// Handle new medical record creation using your existing table structure
if (isset($_POST['add_medical_record'])) {
    $pet_id = $_POST['pet_id'];
    
    // Get pet and owner info
    $pet_info_stmt = $conn->prepare("
        SELECT p.*, u.name as owner_name, u.email as owner_email, u.user_id as owner_id 
        FROM pets p 
        LEFT JOIN users u ON p.user_id = u.user_id 
        WHERE p.pet_id = ?
    ");
    $pet_info_stmt->bind_param("i", $pet_id);
    $pet_info_stmt->execute();
    $pet_info = $pet_info_stmt->get_result()->fetch_assoc();
    
    if ($pet_info) {
        $service_type = $_POST['service_type'] ?? 'Check-up';
        $service_description = $_POST['service_description'] ?? '';
        $weight = $_POST['weight'] ?? $pet_info['weight'];
        $notes = $_POST['notes'] ?? '';
        $veterinarian = $vet['name'];
        $blood_test = $_POST['blood_test'] ?? '';
        $rbc_cbc = $_POST['rbc_cbc'] ?? '';
        
        // Insert medical record using your existing table structure
        $insert_stmt = $conn->prepare("
            INSERT INTO pet_medical_records 
            (owner_id, owner_name, pet_id, pet_name, species, breed, color, sex, dob, age, weight, 
             service_date, service_time, service_type, service_description, veterinarian, notes, 
             weight_date, owner_email, blood_test, rbc_cbc, generated_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, CURDATE(), ?, ?, ?, NOW())
        ");
        
        $insert_stmt->bind_param("issssssssdssssssssss", 
            $pet_info['owner_id'], $pet_info['owner_name'], $pet_id, $pet_info['name'], 
            $pet_info['species'], $pet_info['breed'], $pet_info['color'], $pet_info['gender'],
            $pet_info['birth_date'], $pet_info['age'], $weight, $service_type, $service_description,
            $veterinarian, $notes, $pet_info['owner_email'], $blood_test, $rbc_cbc
        );
        
        if ($insert_stmt->execute()) {
            // Update pet's weight and last vet visit
            if ($weight) {
                $update_pet_stmt = $conn->prepare("UPDATE pets SET weight = ?, last_vet_visit = CURDATE() WHERE pet_id = ?");
                $update_pet_stmt->bind_param("di", $weight, $pet_id);
                $update_pet_stmt->execute();
            } else {
                $update_pet_stmt = $conn->prepare("UPDATE pets SET last_vet_visit = CURDATE() WHERE pet_id = ?");
                $update_pet_stmt->bind_param("i", $pet_id);
                $update_pet_stmt->execute();
            }
            
            $_SESSION['success'] = "Medical record added successfully!";
            header("Location: vet_patients.php");
            exit();
        } else {
            $_SESSION['error'] = "Error adding medical record: " . $conn->error;
        }
    }
}

// Handle quick vaccination update
if (isset($_POST['update_vaccination'])) {
    $pet_id = $_POST['pet_id'];
    $vaccine_type = $_POST['vaccine_type'];
    $vaccine_date = $_POST['vaccine_date'];
    
    // Update pet vaccination date
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

// Fetch medical records for a specific pet (for the view history modal)
if (isset($_GET['view_records'])) {
    $pet_id = $_GET['view_records'];
    $records_stmt = $conn->prepare("
        SELECT * FROM pet_medical_records 
        WHERE pet_id = ? 
        ORDER BY service_date DESC, generated_date DESC
    ");
    $records_stmt->bind_param("i", $pet_id);
    $records_stmt->execute();
    $medical_records = $records_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get pet info for the modal
    $pet_stmt = $conn->prepare("SELECT p.*, u.name as owner_name FROM pets p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
    $pet_stmt->bind_param("i", $pet_id);
    $pet_stmt->execute();
    $current_pet = $pet_stmt->get_result()->fetch_assoc();
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
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
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
            transition: transform 0.3s;
        }
        
        .card-custom:hover {
            transform: translateY(-2px);
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
        
        /* Patient Cards */
        .patient-card {
            border-left: 4px solid var(--primary);
            background: linear-gradient(135deg, var(--primary-light), #e1f5fe);
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
            background: var(--primary-light);
            color: var(--primary-dark);
            border: 3px solid white;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .alert-custom {
            border-radius: var(--radius);
            border: none;
        }
        
        /* Action Buttons */
        .btn-medical { 
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-medical:hover {
            background: linear-gradient(135deg, #27ae60, #229954);
            transform: translateY(-2px);
        }
        
        .btn-vaccine { 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-vaccine:hover {
            background: linear-gradient(135deg, var(--primary-dark), #2471a3);
            transform: translateY(-2px);
        }
        
        .btn-history { 
            background: linear-gradient(135deg, var(--secondary), #8e44ad);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-history:hover {
            background: linear-gradient(135deg, #8e44ad, #7d3c98);
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        /* Vaccine Status */
        .vaccine-due { color: var(--danger); font-weight: bold; }
        .vaccine-upcoming { color: var(--warning); font-weight: bold; }
        .vaccine-current { color: var(--success); font-weight: bold; }
        
        /* Medical Record Cards */
        .record-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .record-checkup { border-left-color: var(--primary); background: linear-gradient(135deg, var(--primary-light), #eaf2f8); }
        .record-vaccination { border-left-color: var(--success); background: linear-gradient(135deg, var(--success-light), #e8f8f5); }
        .record-surgery { border-left-color: var(--danger); background: linear-gradient(135deg, var(--danger-light), #fadbd8); }
        .record-dental { border-left-color: var(--secondary); background: linear-gradient(135deg, #f4ecf7, #f2eef5); }
        .record-emergency { border-left-color: var(--warning); background: linear-gradient(135deg, var(--warning-light), #fef5e7); }
        
        .record-card:hover {
            transform: translateX(5px);
        }
        
        /* Search and Filter */
        .search-box {
            background: white;
            border-radius: var(--radius);
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
                <div class="stats-card" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <div class="stats-number"><?php echo $total_patients; ?></div>
                    <div class="stats-label">Total Patients</div>
                    <i class="fas fa-paw"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning), #f5576c);">
                    <div class="stats-number"><?php echo $dog_count; ?></div>
                    <div class="stats-label">Dogs</div>
                    <i class="fas fa-dog"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success), #00f2fe);">
                    <div class="stats-number"><?php echo $cat_count; ?></div>
                    <div class="stats-label">Cats</div>
                    <i class="fas fa-cat"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary), #38f9d7);">
                    <div class="stats-number">
                        <?php 
                        $total_records = array_sum(array_column($patients, 'total_records'));
                        echo $total_records; 
                        ?>
                    </div>
                    <div class="stats-label">Medical Records</div>
                    <i class="fas fa-file-medical"></i>
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
                            <option value="last_record_date" <?php echo $sort_by == 'last_record_date' ? 'selected' : ''; ?>>Last Record</option>
                            <option value="total_visits" <?php echo $sort_by == 'total_visits' ? 'selected' : ''; ?>>Most Visits</option>
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
                                            <small class="text-muted">Medical Records</small>
                                            <div class="fw-semibold">
                                                <?php echo $patient['total_records']; ?> records
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Last Record</small>
                                            <div class="fw-semibold">
                                                <?php echo $patient['last_record_date'] ? date('M j, Y', strtotime($patient['last_record_date'])) : 'No records'; ?>
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
                                                data-bs-target="#addRecordModal"
                                                onclick="setPetId(<?php echo $patient['pet_id']; ?>)">
                                            <i class="fas fa-plus me-1"></i> Add Record
                                        </button>
                                        <button class="btn btn-history btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewRecordsModal"
                                                onclick="viewRecords(<?php echo $patient['pet_id']; ?>)">
                                            <i class="fas fa-history me-1"></i> View History
                                        </button>
                                        <button class="btn btn-vaccine btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#vaccineModal"
                                                onclick="setVaccinePet(<?php echo $patient['pet_id']; ?>)">
                                            <i class="fas fa-syringe me-1"></i> Vaccine
                                        </button>
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

<!-- Add Medical Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_patients.php">
                <input type="hidden" name="add_medical_record" value="1">
                <input type="hidden" name="pet_id" id="recordPetId">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pet</label>
                            <select class="form-select" name="pet_id" id="petSelect" required onchange="setPetId(this.value)">
                                <option value="">Choose a pet...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['pet_id']; ?>">
                                        <?php echo htmlspecialchars($patient['name']); ?> 
                                        (<?php echo htmlspecialchars($patient['species']); ?> - <?php echo htmlspecialchars($patient['owner_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Type *</label>
                            <select class="form-select" name="service_type" required>
                                <option value="Check-up">Check-up</option>
                                <option value="Vaccination">Vaccination</option>
                                <option value="Surgery">Surgery</option>
                                <option value="Dental">Dental</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Laboratory Test">Laboratory Test</option>
                                <option value="Follow-up">Follow-up</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="weight" placeholder="e.g., 5.2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Test Results</label>
                            <input type="text" class="form-control" name="blood_test" placeholder="Blood test findings...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">RBC/CBC Results</label>
                            <input type="text" class="form-control" name="rbc_cbc" placeholder="RBC/CBC results...">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Service Description</label>
                        <textarea class="form-control" name="service_description" rows="3" 
                                  placeholder="Describe the service provided, findings, treatment..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="Any additional observations or recommendations..."></textarea>
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

<!-- View Medical Records History Modal -->
<div class="modal fade" id="viewRecordsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Records History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="recordsHistoryContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
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

    // Set pet ID for medical record modal
    function setPetId(petId) {
        document.getElementById('recordPetId').value = petId;
        document.getElementById('petSelect').value = petId;
    }

    // Set pet ID for vaccine modal
    function setVaccinePet(petId) {
        document.getElementById('vaccinePetId').value = petId;
    }

    // View medical records history
    function viewRecords(petId) {
        // Show loading state
        document.getElementById('recordsHistoryContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading medical records...</p>
            </div>
        `;
        
        // Fetch medical records via AJAX
        fetch(`vet_patients.php?view_records=${petId}`)
            .then(response => response.text())
            .then(html => {
                // This would normally be an AJAX call, but for simplicity we'll redirect
                window.location.href = `vet_patients.php?view_records=${petId}#recordsHistoryContent`;
            })
            .catch(error => {
                document.getElementById('recordsHistoryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading medical records. Please try again.
                    </div>
                `;
            });
    }

    // If we're viewing records (from URL parameter), show the modal
    <?php if (isset($_GET['view_records'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const viewRecordsModal = new bootstrap.Modal(document.getElementById('viewRecordsModal'));
        viewRecordsModal.show();
        
        // Populate the modal with records
        const recordsContent = document.getElementById('recordsHistoryContent');
        recordsContent.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6>Medical Records for <strong><?php echo htmlspecialchars($current_pet['name']); ?></strong></h6>
                <span class="badge bg-primary"><?php echo count($medical_records); ?> Records</span>
            </div>
            
            <?php if (empty($medical_records)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-medical"></i>
                    <h6>No Medical Records</h6>
                    <p class="text-muted">No medical records found for this pet.</p>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRecordModal" onclick="setPetId(<?php echo $current_pet['pet_id']; ?>)">
                        <i class="fas fa-plus me-1"></i> Add First Record
                    </button>
                </div>
            <?php else: ?>
                <div class="medical-records-list">
                    <?php foreach ($medical_records as $record): ?>
                        <div class="card record-card record-<?php echo strtolower(str_replace(' ', '-', $record['service_type'])); ?> mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($record['service_type']); ?></h6>
                                        <small class="text-muted">
                                            Date: <?php echo date('M j, Y', strtotime($record['service_date'])); ?> • 
                                            Veterinarian: <?php echo htmlspecialchars($record['veterinarian']); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-secondary">
                                        <?php echo date('g:i A', strtotime($record['generated_date'])); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($record['service_description'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Service Description:</small>
                                        <div class="small"><?php echo htmlspecialchars($record['service_description']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['weight'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Weight:</small>
                                        <span class="small"><?php echo $record['weight']; ?> kg</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['blood_test'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Blood Test:</small>
                                        <span class="small"><?php echo htmlspecialchars($record['blood_test']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['rbc_cbc'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">RBC/CBC:</small>
                                        <span class="small"><?php echo htmlspecialchars($record['rbc_cbc']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['notes'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Notes:</small>
                                        <div class="small"><?php echo htmlspecialchars($record['notes']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        `;
    });
    <?php endif; ?>
</script>
</body>

</html>
