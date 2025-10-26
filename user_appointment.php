<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ FIXED: Ensure vet_notifications table exists
$create_table_sql = "
CREATE TABLE IF NOT EXISTS vet_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    vet_id INT NOT NULL,
    message TEXT NOT NULL,
    appointment_id INT,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX vet_id_index (vet_id),
    INDEX is_read_index (is_read)
)";

if (!$conn->query($create_table_sql)) {
    error_log("Warning: Could not create vet_notifications table: " . $conn->error);
}

// ✅ FIXED: Function to create vet notification for ALL vets
function createVetNotification($conn, $message, $appointment_id = null) {
    try {
        // Get all vets
        $vets_stmt = $conn->prepare("SELECT user_id, name FROM users WHERE role = 'vet'");
        $vets_stmt->execute();
        $vets = $vets_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $success_count = 0;
        foreach ($vets as $vet) {
            $stmt = $conn->prepare("INSERT INTO vet_notifications (vet_id, message, appointment_id, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            if ($appointment_id) {
                $stmt->bind_param("isi", $vet['user_id'], $message, $appointment_id);
            } else {
                $null = null;
                $stmt->bind_param("isi", $vet['user_id'], $message, $null);
            }
            if ($stmt->execute()) {
                $success_count++;
                error_log("✅ Notification created for vet: " . $vet['name'] . " (ID: " . $vet['user_id'] . ")");
            } else {
                error_log("❌ Failed to create notification for vet: " . $vet['name'] . " - " . $conn->error);
            }
        }
        
        error_log("📊 Total notifications created: " . $success_count . " out of " . count($vets) . " vets");
        return $success_count;
        
    } catch (Exception $e) {
        error_log("🚨 Notification error: " . $e->getMessage());
        return 0;
    }
}

// Fetch user info
try {
    $stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        die("User not found!");
    }
    
    // Set default profile picture
    $profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($user['name']);
} catch (Exception $e) {
    die("Error fetching user: " . $e->getMessage());
}

// Fetch user's pets for appointment booking
try {
    $pets_stmt = $conn->prepare("SELECT pet_id, name, species, breed FROM pets WHERE user_id = ?");
    $pets_stmt->bind_param("i", $user_id);
    $pets_stmt->execute();
    $pets = $pets_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $pets = [];
    error_log("Error fetching pets: " . $e->getMessage());
}

// Fetch existing appointments
try {
    $appointments_stmt = $conn->prepare("
        SELECT a.*, p.name as pet_name, p.species 
        FROM appointments a 
        LEFT JOIN pets p ON a.pet_id = p.pet_id 
        WHERE a.user_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $appointments_stmt->bind_param("i", $user_id);
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $appointments = [];
    error_log("Error fetching appointments: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pet_id'])) {
    try {
        $pet_id = $_POST['pet_id'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $service_type = $_POST['service_type'];
        $reason = $_POST['reason'] ?? '';
        $preferred_vet = $_POST['preferred_vet'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Validate required fields
        if (empty($pet_id) || empty($appointment_date) || empty($appointment_time) || empty($service_type)) {
            $_SESSION['error'] = "Please fill in all required fields.";
        } else {
            // Check if the selected date is in the future
            $selected_datetime = strtotime($appointment_date . ' ' . $appointment_time);
            if ($selected_datetime <= time()) {
                $_SESSION['error'] = "Please select a future date and time for the appointment.";
            } else {
                // Get pet name and type for the appointment
                $pet_name = '';
                $pet_type = '';
                foreach($pets as $pet) {
                    if ($pet['pet_id'] == $pet_id) {
                        $pet_name = $pet['name'];
                        $pet_type = $pet['species']; // Using species as pet_type
                        break;
                    }
                }
                
                // Insert appointment
                $insert_stmt = $conn->prepare("
                    INSERT INTO appointments (
                        pet_id, pet_name, pet_type, user_id, appointment_date, 
                        appointment_time, service_type, reason, status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
                ");
                
                if ($insert_stmt) {
                    $insert_stmt->bind_param(
                        "issssssss", 
                        $pet_id, $pet_name, $pet_type, $user_id, $appointment_date, 
                        $appointment_time, $service_type, $reason, $notes
                    );
                    
                    if ($insert_stmt->execute()) {
                        $appointment_id = $insert_stmt->insert_id;
                        
                        // ✅ FIXED: Create notification for ALL vets with better debugging
                        $user_name = $user['name'];
                        $formatted_date = date('M j, Y', strtotime($appointment_date));
                        $formatted_time = date('g:i A', strtotime($appointment_time));
                        
                        $notification_message = "📅 New appointment booked by $user_name for $pet_name ($pet_type) on $formatted_date at $formatted_time - Service: $service_type";
                        
                        // Debug: Check how many vets exist
                        $vet_check = $conn->query("SELECT COUNT(*) as vet_count FROM users WHERE role = 'vet'");
                        $vet_count = $vet_check->fetch_assoc()['vet_count'];
                        error_log("🔍 Found $vet_count vets in the system");
                        
                        // Create notifications for all vets
                        $notifications_created = createVetNotification($conn, $notification_message, $appointment_id);
                        
                        if ($notifications_created > 0) {
                            $_SESSION['success'] = "Appointment booked successfully! Notified $notifications_created veterinarians.";
                        } else {
                            $_SESSION['success'] = "Appointment booked successfully! (No veterinarians were notified - please check system configuration)";
                        }
                        
                        header("Location: user_appointment.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "Error booking appointment: " . $insert_stmt->error;
                    }
                } else {
                    $_SESSION['error'] = "Error preparing statement: " . $conn->error;
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        error_log("🚨 Appointment booking error: " . $e->getMessage());
    }
}

// Handle appointment cancellation
if (isset($_GET['cancel_id'])) {
    try {
        $cancel_id = $_GET['cancel_id'];
        
        // Get appointment details first
        $get_stmt = $conn->prepare("
            SELECT a.*, p.name as pet_name 
            FROM appointments a 
            LEFT JOIN pets p ON a.pet_id = p.pet_id 
            WHERE a.appointment_id = ? AND a.user_id = ?
        ");
        $get_stmt->bind_param("ii", $cancel_id, $user_id);
        $get_stmt->execute();
        $appointment_details = $get_stmt->get_result()->fetch_assoc();
        
        if ($appointment_details) {
            $cancel_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND user_id = ?");
            $cancel_stmt->bind_param("ii", $cancel_id, $user_id);
            
            if ($cancel_stmt->execute()) {
                // Notify vet about cancellation
                $user_name = $user['name'];
                $cancel_message = "❌ Appointment cancelled by $user_name for " . $appointment_details['pet_name'] . " on " . $appointment_details['appointment_date'] . " at " . $appointment_details['appointment_time'];
                $cancellation_notifications = createVetNotification($conn, $cancel_message, $cancel_id);
                
                $_SESSION['success'] = "Appointment cancelled successfully! Notified $cancellation_notifications veterinarians.";
                header("Location: user_appointment.php");
                exit();
            } else {
                $_SESSION['error'] = "Error cancelling appointment: " . $cancel_stmt->error;
            }
        } else {
            $_SESSION['error'] = "Appointment not found or you don't have permission to cancel it.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// ✅ DEBUG: Check current state (optional - remove in production)
error_log("=== USER APPOINTMENT DEBUG ===");
error_log("User ID: $user_id");
error_log("Pets count: " . count($pets));
error_log("Appointments count: " . count($appointments));

// Check if there are any vets in the system
$vet_check = $conn->query("SELECT user_id, name FROM users WHERE role = 'vet'");
$vets = $vet_check->fetch_all(MYSQLI_ASSOC);
error_log("Vets in system: " . count($vets));
foreach ($vets as $vet) {
    error_log(" - Vet: " . $vet['name'] . " (ID: " . $vet['user_id'] . ")");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - VetCareQR</title>
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
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
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

        .sidebar .appointment-btn {
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 1rem 0;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .sidebar .appointment-btn:hover {
            background: linear-gradient(135deg, var(--dark-pink), var(--primary-pink));
            color: white;
            transform: translateY(-2px);
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
        
        .alert-custom {
            border-radius: 12px;
            border: none;
        }
        
        .appointment-card {
            border-left: 4px solid var(--primary-pink);
            transition: all 0.3s;
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-scheduled {
            background-color: var(--blue-light);
            color: var(--blue);
        }
        
        .status-confirmed {
            background-color: var(--green-light);
            color: var(--green);
        }
        
        .status-completed {
            background-color: #e8f5e8;
            color: #2ecc71;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #e74c3c;
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--dark-pink), var(--primary-pink));
            color: white;
        }
        
        .pet-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: var(--light-pink);
            color: var(--dark-pink);
        }
        
        .notification-debug {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.9rem;
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
        <div class="brand"><i class="fa-solid fa-paw"></i> VetCareQR</div>
        <div class="profile">
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                     alt="User" 
                     id="sidebarProfilePicture"
                     onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>'">
            </div>
            <h6 id="ownerNameSidebar"><?php echo htmlspecialchars($user['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
        </div>

        <a href="user_appointment.php" class="appointment-btn active">
            <i class="fas fa-calendar-plus"></i> Book Appointment
        </a>

        <a href="user_dashboard.php">
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
        <a href="user_settings.php">
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
                <h5 class="mb-0">Book Vet Appointment</h5>
                <small class="text-muted">Schedule your pet's next visit to the vet</small>
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

        <!-- Debug Information (Remove in production) -->
        <?php if (count($vets) === 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>System Notice:</strong> No veterinarians are currently registered in the system. 
                Notifications will not be sent until vets are added.
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Book Appointment Form -->
            <div class="col-lg-6 mb-4">
                <div class="card-custom">
                    <h4 class="mb-4"><i class="fas fa-calendar-plus me-2"></i>Book New Appointment</h4>
                    
                    <form method="POST" action="user_appointment.php" id="appointmentForm">
                        <div class="row">
                            <!-- Pet Selection -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Select Pet <span class="text-danger">*</span></label>
                                <?php if (empty($pets)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        You need to register a pet first before booking an appointment.
                                        <a href="register_pet.php" class="alert-link">Register your pet here</a>.
                                    </div>
                                <?php else: ?>
                                    <select class="form-select" name="pet_id" required id="petSelect">
                                        <option value="">Choose your pet...</option>
                                        <?php foreach ($pets as $pet): ?>
                                            <option value="<?php echo $pet['pet_id']; ?>">
                                                <?php echo htmlspecialchars($pet['name']); ?> (<?php echo htmlspecialchars($pet['species']); ?> - <?php echo htmlspecialchars($pet['breed']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <!-- Appointment Date -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                <input type="date" 
                                       class="form-control" 
                                       name="appointment_date" 
                                       id="appointmentDate"
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       required>
                                <small class="text-muted">Select a future date</small>
                            </div>

                            <!-- Appointment Time -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                <select class="form-select" name="appointment_time" required id="appointmentTime">
                                    <option value="">Select time...</option>
                                    <option value="09:00">9:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="12:00">12:00 PM</option>
                                    <option value="14:00">2:00 PM</option>
                                    <option value="15:00">3:00 PM</option>
                                    <option value="16:00">4:00 PM</option>
                                </select>
                            </div>

                            <!-- Service Type -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Service Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="service_type" required id="serviceType">
                                    <option value="">Select service...</option>
                                    <option value="General Checkup">General Checkup</option>
                                    <option value="Vaccination">Vaccination</option>
                                    <option value="Dental Care">Dental Care</option>
                                    <option value="Grooming">Grooming</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Surgery">Surgery</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Reason for Visit -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Reason for Visit</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Briefly describe the reason for your visit..."></textarea>
                            </div>

                            <!-- Preferred Veterinarian -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Preferred Veterinarian</label>
                                <select class="form-select" name="preferred_vet">
                                    <option value="">Any available</option>
                                    <option value="Dr. Smith">Dr. Smith</option>
                                    <option value="Dr. Johnson">Dr. Johnson</option>
                                    <option value="Dr. Williams">Dr. Williams</option>
                                    <option value="Dr. Brown">Dr. Brown</option>
                                </select>
                            </div>

                            <!-- Additional Notes -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Any additional information..."></textarea>
                            </div>

                            <!-- Submit Button -->
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary w-100" <?php echo empty($pets) ? 'disabled' : ''; ?> id="submitBtn">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </button>
                                <?php if (empty($pets)): ?>
                                    <small class="text-muted d-block mt-2 text-center">You need to register a pet first</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="col-lg-6 mb-4">
                <div class="card-custom">
                    <h4 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Your Appointments</h4>
                    
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No Appointments</h5>
                            <p class="text-muted">You haven't booked any appointments yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="card appointment-card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="pet-avatar me-3">
                                                    <i class="fas fa-<?php echo strtolower($appointment['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($appointment['pet_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['service_type']); ?></small>
                                                </div>
                                            </div>
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <small class="text-muted">Date & Time</small>
                                                <div class="fw-semibold">
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> 
                                                    at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Veterinarian</small>
                                                <div class="fw-semibold">
                                                    <?php echo !empty($appointment['preferred_vet']) ? htmlspecialchars($appointment['preferred_vet']) : 'Any available'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($appointment['reason'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">Reason</small>
                                                <div class="small"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <?php if ($appointment['status'] == 'scheduled' || $appointment['status'] == 'confirmed'): ?>
                                                <a href="user_appointment.php?cancel_id=<?php echo $appointment['appointment_id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to cancel this appointment? The veterinary team will be notified.')">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </a>
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
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date and time
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Set default date to tomorrow
        const dateInput = document.getElementById('appointmentDate');
        if (dateInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dateInput.value = tomorrow.toISOString().split('T')[0];
        }
        
        console.log('Appointment page loaded successfully');
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }
</script>
</body>
</html>
