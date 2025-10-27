<?php
session_start();
include("conn.php");

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

// Set default profile picture if none exists
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// ✅ 3. Fetch all appointments with pet and owner details
$query = "
SELECT 
    a.appointment_id,
    a.pet_id,
    a.user_id,
    a.appointment_date,
    a.appointment_time,
    a.service_type,
    a.reason,
    a.status,
    a.notes,
    a.created_at,
    p.name AS pet_name,
    p.species,
    p.breed,
    p.age,
    u.name AS owner_name,
    u.email AS owner_email,
    u.phone_number AS owner_phone
FROM appointments a
JOIN pets p ON a.pet_id = p.pet_id
JOIN users u ON a.user_id = u.user_id
WHERE a.appointment_date >= CURDATE()
ORDER BY a.appointment_date ASC, a.appointment_time ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("❌ SQL ERROR: " . $conn->error);
}

$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ 4. Fetch appointment statistics
$stats_query = "
SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today
FROM appointments
WHERE appointment_date >= CURDATE()
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// ✅ 5. Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    
    if ($_POST['action'] === 'update_status') {
        $new_status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $update_query = "UPDATE appointments SET status = ?, notes = ? WHERE appointment_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $new_status, $notes, $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment status updated successfully!";
            header("Location: vet_appointments.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating appointment: " . $conn->error;
        }
    }
    
    // ✅ NEW: Handle quick confirm action
    if ($_POST['action'] === 'confirm_appointment') {
        $update_query = "UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment confirmed successfully!";
            header("Location: vet_appointments.php");
            exit();
        } else {
            $_SESSION['error'] = "Error confirming appointment: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'add_appointment') {
        $pet_id = $_POST['pet_id'];
        $user_id = $_POST['user_id'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $service_type = $_POST['service_type'];
        $reason = $_POST['reason'];
        $status = 'scheduled';
        
        $insert_query = "
        INSERT INTO appointments (pet_id, user_id, appointment_date, appointment_time, service_type, reason, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisssss", $pet_id, $user_id, $appointment_date, $appointment_time, $service_type, $reason, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment scheduled successfully!";
            header("Location: vet_appointments.php");
            exit();
        } else {
            $_SESSION['error'] = "Error scheduling appointment: " . $conn->error;
        }
    }
}

// ✅ 6. Fetch all pets and owners for the add appointment form
$pets_query = "
SELECT p.pet_id, p.name AS pet_name, p.species, p.breed, u.user_id, u.name AS owner_name
FROM pets p 
JOIN users u ON p.user_id = u.user_id 
ORDER BY u.name, p.name
";
$pets_result = $conn->query($pets_query);
$pets = $pets_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCareQR - Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ec4899;
            --primary-dark: #db2777;
            --primary-light: #fbcfe8;
            --secondary: #8b5cf6;
            --accent: #f97316;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #fdf2f8;
            --dark: #1f2937;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #f3e8ff 100%);
            color: #374151;
            line-height: 1.6;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .brand {
            font-weight: 800;
            font-size: 1.5rem;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }
        
        .brand i {
            font-size: 1.8rem;
        }
        
        .profile {
            text-align: center;
            margin-bottom: 3rem;
            padding: 1.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .profile img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin-bottom: 1rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .profile h6 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: white;
        }
        
        .profile small {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            border-radius: 16px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.2rem;
        }
        
        .logout-btn {
            margin-top: auto;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
        }
        
        .logout-btn:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateX(5px);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        
        .topbar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        .welcome-section h4 {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .welcome-section p {
            color: #6b7280;
            margin-bottom: 0;
        }
        
        .datetime-display {
            text-align: right;
        }
        
        .datetime-display strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        .datetime-display small {
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            padding: 2rem 1.5rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-card h6 {
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .stats-card h4 {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0;
        }
        
        /* Custom Cards */
        .card-custom {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        .card-custom h4 {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-custom h4 i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 0.75rem 1.5rem;
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .btn-success:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Appointment Cards */
        .appointment-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            margin-bottom: 1.5rem;
        }
        
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .appointment-header {
            padding: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .status-scheduled { 
            background: linear-gradient(135deg, var(--warning), #d97706);
        }
        .status-confirmed { 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        .status-completed { 
            background: linear-gradient(135deg, var(--success), #059669);
        }
        .status-cancelled { 
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }
        
        .appointment-body {
            padding: 1.5rem;
        }
        
        .appointment-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .appointment-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .appointment-info-label {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .appointment-info-value {
            font-weight: 500;
            color: #374151;
        }
        
        /* Badges */
        .badge-service {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-vaccine {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-checkup {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-emergency {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .empty-state h5 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        /* Alerts */
        .alert {
            border-radius: 16px;
            border: none;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--hover-shadow);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
            padding: 1.5rem 2rem;
        }
        
        .modal-title {
            font-weight: 700;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 16px;
            padding: 0.75rem 1rem;
            border: 2px solid #f3f4f6;
            background: #fdf2f8;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
            transform: translateY(-2px);
            background: white;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .appointment-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .appointment-info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Enhanced Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-stethoscope"></i> VetCareQR
        </div>
        
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Veterinarian"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6>Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small>Veterinarian</small>
        </div>
        
        <div class="nav-links">
            <a href="vet_dashboard.php" class="nav-link">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
            <a href="vet_patients.php" class="nav-link">
                <i class="fa-solid fa-paw"></i> All Patients
            </a>
            <a href="vet_appointments.php" class="nav-link active">
                <i class="fa-solid fa-calendar-check"></i> Appointments
            </a>
            <a href="vet_records.php" class="nav-link">
                <i class="fa-solid fa-file-medical"></i> Medical Records
            </a>
            <a href="vet_settings.php" class="nav-link">
                <i class="fa-solid fa-gear"></i> Settings
            </a>
            <a href="logout.php" class="nav-link logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Enhanced Topbar -->
        <div class="topbar">
            <div class="welcome-section">
                <h4>Appointment Management</h4>
                <p>Schedule and manage veterinary appointments efficiently</p>
            </div>
            <div class="d-flex align-items-center gap-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                    <i class="fa-solid fa-plus me-2"></i> New Appointment
                </button>
                <div class="datetime-display">
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

        <!-- Enhanced Stats Cards -->
        <div class="row stats-row">
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-calendar"></i>
                    <h6>Total</h6>
                    <h4><?php echo $stats['total_appointments']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-clock"></i>
                    <h6>Scheduled</h6>
                    <h4><?php echo $stats['scheduled']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-check"></i>
                    <h6>Confirmed</h6>
                    <h4><?php echo $stats['confirmed']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-check-double"></i>
                    <h6>Completed</h6>
                    <h4><?php echo $stats['completed']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-times"></i>
                    <h6>Cancelled</h6>
                    <h4><?php echo $stats['cancelled']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="stats-card">
                    <i class="fa-solid fa-sun"></i>
                    <h6>Today</h6>
                    <h4><?php echo $stats['today']; ?></h4>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fa-solid fa-list"></i>Upcoming Appointments</h4>
                <span class="badge-service"><?php echo count($appointments); ?> Appointments</span>
            </div>
            
            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-times"></i>
                    <h5>No Upcoming Appointments</h5>
                    <p class="text-muted">No appointments are scheduled for the future.</p>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                        <i class="fa-solid fa-plus me-2"></i> Schedule First Appointment
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="col-12 mb-4">
                            <div class="appointment-card">
                                <div class="appointment-header status-<?php echo $appointment['status']; ?>">
                                    <div>
                                        <h6 class="mb-2">
                                            <i class="fa-solid fa-calendar-day me-2"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                            at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </h6>
                                        <small>
                                            <?php echo htmlspecialchars($appointment['pet_name']); ?> • 
                                            <?php echo htmlspecialchars($appointment['species']); ?> • 
                                            Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="status-badge bg-light text-dark">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                        <div class="action-buttons">
                                            <!-- ✅ NEW: Confirm Appointment Button -->
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <form method="POST" action="vet_appointments.php" class="d-inline">
                                                    <input type="hidden" name="action" value="confirm_appointment">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            title="Confirm Appointment"
                                                            onclick="return confirm('Are you sure you want to confirm this appointment?')">
                                                        <i class="fa-solid fa-check me-1"></i> Confirm
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-light" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editAppointmentModal"
                                                    onclick="editAppointment(<?php echo $appointment['appointment_id']; ?>)"
                                                    title="Edit Appointment">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-body">
                                    <div class="appointment-info-grid">
                                        <div class="appointment-info-item">
                                            <span class="appointment-info-label">Service Type</span>
                                            <span class="appointment-info-value">
                                                <span class="badge-service">
                                                    <?php echo htmlspecialchars($appointment['service_type']); ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="appointment-info-item">
                                            <span class="appointment-info-label">Reason for Visit</span>
                                            <span class="appointment-info-value"><?php echo htmlspecialchars($appointment['reason']); ?></span>
                                        </div>
                                        <div class="appointment-info-item">
                                            <span class="appointment-info-label">Owner Contact</span>
                                            <span class="appointment-info-value"><?php echo htmlspecialchars($appointment['owner_phone']); ?></span>
                                        </div>
                                        <div class="appointment-info-item">
                                            <span class="appointment-info-label">Owner Email</span>
                                            <span class="appointment-info-value"><?php echo htmlspecialchars($appointment['owner_email']); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="mt-3 p-3 bg-light rounded">
                                            <strong class="text-primary">Veterinarian Notes:</strong> 
                                            <span class="text-muted"><?php echo htmlspecialchars($appointment['notes']); ?></span>
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

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_appointments.php">
                <input type="hidden" name="action" value="add_appointment">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pet_id" class="form-label">Select Pet *</label>
                            <select class="form-select" id="pet_id" name="pet_id" required onchange="updateOwnerInfo()">
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                    <option value="<?php echo $pet['pet_id']; ?>" data-owner-id="<?php echo $pet['user_id']; ?>">
                                        <?php echo htmlspecialchars($pet['pet_name']); ?> 
                                        (<?php echo htmlspecialchars($pet['species']); ?> - <?php echo htmlspecialchars($pet['breed']); ?>)
                                        - Owner: <?php echo htmlspecialchars($pet['owner_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Owner ID</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="appointment_date" class="form-label">Appointment Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="appointment_time" class="form-label">Appointment Time *</label>
                            <input type="time" class="form-control" id="appointment_time" name="appointment_time" 
                                   min="08:00" max="18:00" required>
                        </div>
                    </div>
                    
                    <div class="row">
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
                        <div class="col-md-6 mb-3">
                            <label for="reason" class="form-label">Reason for Visit *</label>
                            <input type="text" class="form-control" id="reason" name="reason" 
                                   placeholder="Brief reason for the appointment" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="vet_appointments.php" id="editAppointmentForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="editAppointmentId">
                
                <div class="modal-body" id="editAppointmentModalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Appointment</button>
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

// Update owner info when pet is selected
function updateOwnerInfo() {
    const petSelect = document.getElementById('pet_id');
    const selectedOption = petSelect.options[petSelect.selectedIndex];
    const ownerId = selectedOption.getAttribute('data-owner-id');
    document.getElementById('user_id').value = ownerId;
}

// Edit appointment functionality
function editAppointment(appointmentId) {
    document.getElementById('editAppointmentId').value = appointmentId;
    
    // Show loading state
    document.getElementById('editAppointmentModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading appointment details...</p>
        </div>
    `;
    
    const editModal = new bootstrap.Modal(document.getElementById('editAppointmentModal'));
    editModal.show();
    
    // Fetch appointment data via AJAX
    fetch('get_appointment.php?appointment_id=' + appointmentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editAppointmentModalBody').innerHTML = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Pet</span>
                                <span class="appointment-info-value">${data.appointment.pet_name}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Owner</span>
                                <span class="appointment-info-value">${data.appointment.owner_name}</span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Date</span>
                                <span class="appointment-info-value">${new Date(data.appointment.appointment_date).toLocaleDateString()}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Time</span>
                                <span class="appointment-info-value">${data.appointment.appointment_time}</span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Service</span>
                                <span class="appointment-info-value">
                                    <span class="badge-service">${data.appointment.service_type}</span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="appointment-info-item">
                                <span class="appointment-info-label">Reason</span>
                                <span class="appointment-info-value">${data.appointment.reason}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="edit_status" class="form-label">Status *</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="scheduled" ${data.appointment.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                            <option value="confirmed" ${data.appointment.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="completed" ${data.appointment.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${data.appointment.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Veterinarian Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="4" 
                                  placeholder="Add any notes or observations about this appointment...">${data.appointment.notes || ''}</textarea>
                    </div>
                `;
            } else {
                document.getElementById('editAppointmentModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading appointment: ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('editAppointmentModalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Network error: Could not load appointment details.
                </div>
            `;
            console.error('Error:', error);
        });
}

// Set minimum date to today for appointment scheduling
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('appointment_date').min = today;
    
    // Auto-close alerts after 5 seconds
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
