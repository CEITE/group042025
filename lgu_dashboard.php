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
                        // Create announcements table
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
                        // Update session variables
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

// Get statistics data - using your existing database structure
$stats = [
    'total_pets' => 0,
    'total_vets' => 0,
    'total_owners' => 0,
    'total_lgu' => 0
];

// Count total pets (if pets table exists)
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

// Get recent users in the same area
$recent_users = [];
$result = $conn->query("SELECT name, email, role, created_at FROM users 
                       WHERE (city = '$city' AND province = '$province') OR role = 'lgu'
                       ORDER BY created_at DESC LIMIT 8");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
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
        
        .user-role-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
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
                    <a class="nav-link" href="#announcements">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                    <a class="nav-link" href="#users">
                        <i class="fas fa-users"></i> User Management
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
                                    <a class="dropdown-item" href="#">System is running well</a>
                                    <a class="dropdown-item" href="#">Welcome to LGU Dashboard</a>
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
                                        <h6 class="text-muted mb-2">Pet Owners</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_owners']); ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-users fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Veterinarians</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_vets']); ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-user-md fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="stat-card danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">LGU Officials</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_lgu']); ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-landmark fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
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
                            
                            <!-- Recent Users -->
                            <div class="dashboard-card" id="users">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-users me-2"></i>Recent Users in <?php echo htmlspecialchars($city); ?></span>
                                    <span class="badge bg-primary"><?php echo count($recent_users); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($user['role']) {
                                                                case 'vet': echo 'warning'; break;
                                                                case 'owner': echo 'success'; break;
                                                                case 'lgu': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?> user-role-badge"><?php echo ucfirst($user['role']); ?></span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
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
                                            <i class="fas fa-chart-pie me-2"></i>User Distribution
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="userDistributionChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="dashboard-card">
                                        <div class="card-header">
                                            <i class="fas fa-chart-line me-2"></i>Registration Trends
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="registrationTrendChart"></canvas>
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
                                        <small class="text-muted d-block">Total Users</small>
                                        <div class="fw-semibold"><?php echo number_format($stats['total_owners'] + $stats['total_vets'] + $stats['total_lgu']); ?></div>
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
        // Data Visualization Charts
        const userDistributionCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userDistributionChart = new Chart(userDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pet Owners', 'Veterinarians', 'LGU Officials'],
                datasets: [{
                    data: [
                        <?php echo $stats['total_owners']; ?>,
                        <?php echo $stats['total_vets']; ?>, 
                        <?php echo $stats['total_lgu']; ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
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

        const registrationTrendCtx = document.getElementById('registrationTrendChart').getContext('2d');
        const registrationTrendChart = new Chart(registrationTrendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'User Registrations',
                    data: [12, 19, 15, 25, 22, 30],
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
            alert('Report generation feature would be implemented here!');
            // This would typically generate a PDF report
        }

        function viewAnalytics() {
            // Scroll to analytics section
            document.getElementById('analytics').scrollIntoView({ behavior: 'smooth' });
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
