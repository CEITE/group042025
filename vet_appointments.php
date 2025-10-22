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
        
        .appointment-card {
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s;
            border: none;
            box-shadow: var(--card-shadow);
            margin-bottom: 1rem;
        }
        
        .appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }
        
        .appointment-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
        }
        
        .status-scheduled { background: var(--warning); }
        .status-confirmed { background: var(--primary); }
        .status-completed { background: var(--success); }
        .status-cancelled { background: var(--accent); }
        
        .btn-vet {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-vet:hover {
            background: var(--primary-dark);
            color: white;
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
        
        .calendar-day {
            border: 1px solid #dee2e6;
            padding: 0.5rem;
            min-height: 120px;
            background: white;
        }
        
        .calendar-day.today {
            background: #e3f2fd;
            border-color: var(--primary);
        }
        
        .calendar-day.has-appointments {
            background: #fff3cd;
        }
        
        .appointment-badge {
            font-size: 0.7rem;
            margin: 1px;
            cursor: pointer;
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
        <div class="brand"><i class="fa-solid fa-stethoscope"></i> VetCareQR</div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Veterinarian"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6><?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted">Veterinarian</small>
        </div>
        <a href="vet_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_patients.php">
            <div class="icon"><i class="fa-solid fa-paw"></i></div> All Patients
        </a>
        <a href="vet_appointments.php" class="active">
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
                <h5 class="mb-0">Appointment Management</h5>
                <small class="text-muted">Schedule and manage veterinary appointments</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-vet" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                    <i class="fa-solid fa-plus me-1"></i> New Appointment
                </button>
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
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-calendar"></i>
                    <h6>Total</h6>
                    <h4><?php echo $stats['total_appointments']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-clock"></i>
                    <h6>Scheduled</h6>
                    <h4><?php echo $stats['scheduled']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-check"></i>
                    <h6>Confirmed</h6>
                    <h4><?php echo $stats['confirmed']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-check-double"></i>
                    <h6>Completed</h6>
                    <h4><?php echo $stats['completed']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-times"></i>
                    <h6>Cancelled</h6>
                    <h4><?php echo $stats['cancelled']; ?></h4>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="stats-card">
                    <i class="fa-solid fa-sun"></i>
                    <h6>Today</h6>
                    <h4><?php echo $stats['today']; ?></h4>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="card-custom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-list me-2"></i>Upcoming Appointments</h4>
                <span class="badge bg-primary"><?php echo count($appointments); ?> Appointments</span>
            </div>
            
            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-times"></i>
                    <h5>No Upcoming Appointments</h5>
                    <p class="text-muted">No appointments are scheduled for the future.</p>
                    <button class="btn btn-vet" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                        <i class="fa-solid fa-plus me-1"></i> Schedule First Appointment
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="col-12 mb-3">
                            <div class="appointment-card">
                                <div class="appointment-header status-<?php echo $appointment['status']; ?>">
                                    <div>
                                        <h6 class="mb-0">
                                            <i class="fa-solid fa-calendar-day me-1"></i>
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                            at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                        </h6>
                                        <small>
                                            <?php echo htmlspecialchars($appointment['pet_name']); ?> • 
                                            <?php echo htmlspecialchars($appointment['species']); ?> • 
                                            Owner: <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                        <button class="btn btn-sm btn-light" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAppointmentModal"
                                                onclick="editAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Service:</strong>
                                            <span class="badge badge-service ms-1">
                                                <?php echo htmlspecialchars($appointment['service_type']); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Reason:</strong> 
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Contact:</strong> 
                                            <?php echo htmlspecialchars($appointment['owner_phone']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="mt-2">
                                            <strong>Notes:</strong> 
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
                    <button type="submit" class="btn btn-vet">Schedule Appointment</button>
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
                    <button type="submit" class="btn btn-vet">Update Appointment</button>
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
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Pet:</strong> ${data.appointment.pet_name}
                        </div>
                        <div class="col-md-6">
                            <strong>Owner:</strong> ${data.appointment.owner_name}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Date:</strong> ${new Date(data.appointment.appointment_date).toLocaleDateString()}
                        </div>
                        <div class="col-md-6">
                            <strong>Time:</strong> ${data.appointment.appointment_time}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Service:</strong> ${data.appointment.service_type}
                        </div>
                        <div class="col-md-6">
                            <strong>Reason:</strong> ${data.appointment.reason}
                        </div>
                    </div>
                    <div class="mb-3">
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