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
$city = $_SESSION['city'];
$province = $_SESSION['province'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                $title = trim($_POST['title']);
                $message = trim($_POST['message']);
                $announcement_type = $_POST['announcement_type'];
                
                if (!empty($title) && !empty($message)) {
                    $stmt = $conn->prepare("INSERT INTO announcements (lgu_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $user_id, $title, $message, $announcement_type);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Announcement created successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to create announcement.";
                    }
                    $stmt->close();
                }
                break;
                
            case 'update_medical_record':
                $record_id = $_POST['record_id'];
                $status = $_POST['status'];
                $notes = trim($_POST['notes']);
                
                $stmt = $conn->prepare("UPDATE medical_records SET status = ?, lgu_notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssi", $status, $notes, $record_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Medical record updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update medical record.";
                }
                $stmt->close();
                break;
                
            case 'send_notification':
                $user_ids = $_POST['user_ids'];
                $notification_title = trim($_POST['notification_title']);
                $notification_message = trim($_POST['notification_message']);
                
                if (!empty($user_ids) && !empty($notification_title) && !empty($notification_message)) {
                    foreach ($user_ids as $target_user_id) {
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'lgu_announcement', NOW())");
                        $stmt->bind_param("iss", $target_user_id, $notification_title, $notification_message);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $_SESSION['success'] = "Notifications sent successfully!";
                }
                break;
        }
    }
}

// Get statistics data
$stats = [
    'total_pets' => 0,
    'total_vets' => 0,
    'pending_records' => 0,
    'vaccination_rate' => 0
];

// Total pets in LGU area
$result = $conn->query("SELECT COUNT(*) as total FROM pets WHERE city = '$city' AND province = '$province'");
if ($result) {
    $stats['total_pets'] = $result->fetch_assoc()['total'];
}

// Total vets in LGU area
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'vet' AND city = '$city' AND province = '$province'");
if ($result) {
    $stats['total_vets'] = $result->fetch_assoc()['total'];
}

// Pending medical records
$result = $conn->query("SELECT COUNT(*) as total FROM medical_records mr 
                       JOIN pets p ON mr.pet_id = p.id 
                       WHERE p.city = '$city' AND p.province = '$province' 
                       AND mr.status = 'pending'");
if ($result) {
    $stats['pending_records'] = $result->fetch_assoc()['total'];
}

// Vaccination rate (simplified calculation)
$result = $conn->query("SELECT COUNT(DISTINCT pet_id) as vaccinated FROM vaccination_records vr 
                       JOIN pets p ON vr.pet_id = p.id 
                       WHERE p.city = '$city' AND p.province = '$province' 
                       AND vr.vaccination_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)");
$vaccinated = $result ? $result->fetch_assoc()['vaccinated'] : 0;
$stats['vaccination_rate'] = $stats['total_pets'] > 0 ? round(($vaccinated / $stats['total_pets']) * 100, 1) : 0;

// Get recent medical records for monitoring
$medical_records = [];
$result = $conn->query("SELECT mr.*, p.name as pet_name, p.owner_name, u.name as vet_name 
                       FROM medical_records mr 
                       JOIN pets p ON mr.pet_id = p.id 
                       LEFT JOIN users u ON mr.vet_id = u.user_id 
                       WHERE p.city = '$city' AND p.province = '$province' 
                       ORDER BY mr.created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $medical_records[] = $row;
    }
}

// Get recent announcements
$announcements = [];
$result = $conn->query("SELECT * FROM announcements WHERE lgu_id = $user_id ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Get vaccination data for charts
$vaccination_data = [];
$result = $conn->query("SELECT MONTH(vaccination_date) as month, COUNT(*) as count 
                       FROM vaccination_records vr 
                       JOIN pets p ON vr.pet_id = p.id 
                       WHERE p.city = '$city' AND p.province = '$province' 
                       AND YEAR(vaccination_date) = YEAR(NOW()) 
                       GROUP BY MONTH(vaccination_date) 
                       ORDER BY month");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vaccination_data[$row['month']] = $row['count'];
    }
}

// Get pet registration data for charts
$registration_data = [];
$result = $conn->query("SELECT MONTH(created_at) as month, COUNT(*) as count 
                       FROM pets 
                       WHERE city = '$city' AND province = '$province' 
                       AND YEAR(created_at) = YEAR(NOW()) 
                       GROUP BY MONTH(created_at) 
                       ORDER BY month");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $registration_data[$row['month']] = $row['count'];
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
        
        .notification-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                    <a class="nav-link" href="#medical-records">
                        <i class="fas fa-file-medical"></i> Medical Records
                    </a>
                    <a class="nav-link" href="#announcements">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                    <a class="nav-link" href="#notifications">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                    <a class="nav-link" href="#analytics">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                    <a class="nav-link" href="#events">
                        <i class="fas fa-calendar-alt"></i> Municipal Events
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
                                    <a class="dropdown-item" href="#">New vaccination records</a>
                                    <a class="dropdown-item" href="#">Pending approvals</a>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                                        <h6 class="text-muted mb-2">Veterinarians</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_vets']); ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-user-md fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pending Records</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['pending_records']); ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-file-medical fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Vaccination Rate</h6>
                                        <h3 class="mb-0"><?php echo $stats['vaccination_rate']; ?>%</h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-syringe fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Medical Records Monitoring -->
                            <div class="dashboard-card" id="medical-records">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-medical me-2"></i>Recent Medical Records</span>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateRecordModal">
                                        <i class="fas fa-edit me-1"></i>Update Records
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Pet Name</th>
                                                    <th>Owner</th>
                                                    <th>Veterinarian</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($medical_records as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($record['pet_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['owner_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['vet_name'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($record['status']) {
                                                                case 'completed': echo 'success'; break;
                                                                case 'pending': echo 'warning'; break;
                                                                case 'cancelled': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>"><?php echo ucfirst($record['status']); ?></span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($record['created_at'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#recordDetailModal"
                                                                data-record-id="<?php echo $record['id']; ?>"
                                                                data-pet-name="<?php echo htmlspecialchars($record['pet_name']); ?>"
                                                                data-status="<?php echo $record['status']; ?>"
                                                                data-notes="<?php echo htmlspecialchars($record['lgu_notes'] ?? ''); ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Visualization -->
                            <div class="row" id="analytics">
                                <div class="col-md-6">
                                    <div class="dashboard-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-line me-2"></i>Vaccination Trends
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="vaccinationChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="dashboard-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar me-2"></i>Pet Registrations
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="registrationChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Quick Actions -->
                            <div class="dashboard-card">
                                <div class="card-header">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#announcementModal">
                                            <i class="fas fa-bullhorn me-2"></i>Create Announcement
                                        </button>
                                        <button class="btn btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#notificationModal">
                                            <i class="fas fa-bell me-2"></i>Send Notifications
                                        </button>
                                        <button class="btn btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#eventModal">
                                            <i class="fas fa-calendar-plus me-2"></i>Add Municipal Event
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="generateReport()">
                                            <i class="fas fa-file-pdf me-2"></i>Generate Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Announcements -->
                            <div class="dashboard-card" id="announcements">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bullhorn me-2"></i>Recent Announcements</span>
                                    <span class="badge bg-primary"><?php echo count($announcements); ?></span>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($announcements)): ?>
                                        <p class="text-muted text-center">No announcements yet</p>
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
                            
                            <!-- System Alerts -->
                            <div class="dashboard-card" id="notifications">
                                <div class="card-header">
                                    <i class="fas fa-exclamation-triangle me-2"></i>System Alerts
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-clock me-2"></i>
                                        <strong>15 medical records</strong> pending review
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Vaccination drive scheduled for next month
                                    </div>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check me-2"></i>
                                        System updated to latest version
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
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="4" required></textarea>
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

    <!-- Update Medical Record Modal -->
    <div class="modal fade" id="updateRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_medical_record">
                        <div class="mb-3">
                            <label class="form-label">Select Record</label>
                            <select class="form-select" name="record_id" required>
                                <option value="">Choose a record...</option>
                                <?php foreach ($medical_records as $record): ?>
                                <option value="<?php echo $record['id']; ?>">
                                    <?php echo htmlspecialchars($record['pet_name'] . ' - ' . $record['owner_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">LGU Notes</label>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Add comments or notes..."></textarea>
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

    <!-- Send Notifications Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_notification">
                        <div class="mb-3">
                            <label class="form-label">Target Users</label>
                            <select class="form-select" name="user_ids[]" multiple required>
                                <option value="all">All Pet Owners</option>
                                <option value="vet_all">All Veterinarians</option>
                                <!-- Additional options would be populated dynamically -->
                            </select>
                            <div class="form-text">Hold Ctrl to select multiple users</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notification Title</label>
                            <input type="text" class="form-control" name="notification_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="notification_message" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Notifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Municipal Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Municipal Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Event Title</label>
                            <input type="text" class="form-control" name="event_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Event Date</label>
                            <input type="datetime-local" class="form-control" name="event_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="event_location" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="event_description" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="addEvent()">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data Visualization Charts
        const vaccinationCtx = document.getElementById('vaccinationChart').getContext('2d');
        const vaccinationChart = new Chart(vaccinationCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Vaccinations',
                    data: [65, 59, 80, 81, 56, 55, 40, 45, 60, 70, 75, 80],
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

        const registrationCtx = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(registrationCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Pet Registrations',
                    data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45],
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    borderWidth: 1
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

        // Modal handlers
        const recordDetailModal = document.getElementById('recordDetailModal');
        if (recordDetailModal) {
            recordDetailModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const recordId = button.getAttribute('data-record-id');
                const petName = button.getAttribute('data-pet-name');
                const status = button.getAttribute('data-status');
                const notes = button.getAttribute('data-notes');
                
                const modal = this;
                modal.querySelector('#recordPetName').textContent = petName;
                modal.querySelector('#recordStatus').textContent = status;
                modal.querySelector('#recordNotes').value = notes || 'No notes available.';
            });
        }

        function generateReport() {
            alert('Report generation feature would be implemented here!');
            // This would typically generate a PDF or Excel report
        }

        function addEvent() {
            alert('Event added successfully!');
            // This would typically save the event to the database
            document.getElementById('eventModal').querySelector('form').reset();
            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            // This would typically refresh the data from the server
            console.log('Refreshing dashboard data...');
        }, 300000);
    </script>
</body>
</html>