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

// Redirect if not logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT name, email, profile_picture FROM users WHERE user_id = ?";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin = $admin_result->fetch_assoc();
$admin_stmt->close();

// Get statistics
$stats = [];
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'vet') as total_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'owner') as total_owners,
        (SELECT COUNT(*) FROM pets) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE status = 'scheduled') as upcoming_appointments,
        (SELECT COUNT(*) FROM appointments WHERE DATE(created_at) = CURDATE()) as today_appointments,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND DATE(created_at) = CURDATE()) as new_vets_today
";

$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}

// Get recent veterinarians
$recent_vets_query = "
    SELECT user_id, name, email, profile_picture, created_at, last_login 
    FROM users 
    WHERE role = 'vet' 
    ORDER BY created_at DESC 
    LIMIT 5
";
$recent_vets_result = $conn->query($recent_vets_query);
$recent_vets = [];
if ($recent_vets_result) {
    while ($row = $recent_vets_result->fetch_assoc()) {
        $recent_vets[] = $row;
    }
}

// Get all veterinarians for the table
$vets_query = "
    SELECT user_id, name, email, profile_picture, created_at, last_login 
    FROM users 
    WHERE role = 'vet' 
    ORDER BY created_at DESC
";
$vets_result = $conn->query($vets_query);
$all_vets = [];
if ($vets_result) {
    while ($row = $vets_result->fetch_assoc()) {
        $all_vets[] = $row;
    }
}

// Handle actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['vet_id'])) {
        $vet_id = intval($_POST['vet_id']);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'activate':
                $update_query = "UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'vet'";
                break;
            case 'deactivate':
                $update_query = "UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'vet'";
                break;
            case 'delete':
                $update_query = "DELETE FROM users WHERE user_id = ? AND role = 'vet'";
                break;
            default:
                $update_query = null;
        }
        
        if ($update_query) {
            $stmt = $conn->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param("i", $vet_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Veterinarian updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating veterinarian: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        header("Location: admin_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: fixed;
            width: 280px;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 80px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card.success {
            border-left-color: var(--success);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        
        .stat-card.success .stat-number {
            color: var(--success);
        }
        
        .stat-card.warning .stat-number {
            color: var(--warning);
        }
        
        .stat-card.danger .stat-number {
            color: var(--danger);
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .card-title {
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table {
            margin: 0;
        }
        
        .table th {
            border-top: none;
            font-weight: 700;
            color: var(--dark);
            padding: 1.2rem 0.75rem;
            background: #f8fafc;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #f1f5f9;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .user-email {
            color: #64748b;
            font-size: 0.85rem;
            margin: 0;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: rgba(14, 165, 233, 0.1);
            color: var(--primary);
        }
        
        .btn-edit:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-deactivate {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .btn-deactivate:hover {
            background: var(--warning);
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
        }
        
        .admin-info h6 {
            margin: 0;
            font-weight: 700;
        }
        
        .admin-info small {
            opacity: 0.8;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .toggle-sidebar:hover {
            background: var(--primary-light);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.collapsed {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-user-shield me-2"></i>
            <span class="logo-text">VetCareQR</span>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-user-md"></i>
                    <span class="nav-text">Veterinarians</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-paw"></i>
                    <span class="nav-text">Pet Owners</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-text">Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-stethoscope"></i>
                    <span class="nav-text">Medical Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">System Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-bell"></i>
                    <span class="nav-text">Notifications</span>
                    <span class="badge bg-danger ms-auto">3</span>
                </a>
            </li>
        </ul>
        
        <div class="admin-profile">
            <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=0ea5e9&color=fff'; ?>" 
                 alt="Admin" class="admin-avatar">
            <div class="admin-info">
                <h6><?php echo htmlspecialchars($admin['name']); ?></h6>
                <small>Administrator</small>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="d-flex align-items-center ms-auto">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($admin['name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Success/Error Messages -->
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
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-1 fw-bold">Admin Dashboard</h2>
                    <p class="text-muted mb-0">Monitor and manage veterinarians in the system</p>
                </div>
                <div>
                    <button class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Veterinarian
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['total_vets'] ?? '0'; ?></div>
                                <div class="stat-label">Total Veterinarians</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['total_owners'] ?? '0'; ?></div>
                                <div class="stat-label">Pet Owners</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-paw"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['upcoming_appointments'] ?? '0'; ?></div>
                                <div class="stat-label">Upcoming Appointments</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $stats['new_vets_today'] ?? '0'; ?></div>
                                <div class="stat-label">New Vets Today</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Veterinarians -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-clock text-warning"></i>
                                Recently Added Veterinarians
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_vets)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_vets as $vet): ?>
                                        <div class="list-group-item d-flex align-items-center px-0">
                                            <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=0ea5e9&color=fff'; ?>" 
                                                 alt="<?php echo htmlspecialchars($vet['name']); ?>" 
                                                 class="user-avatar me-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($vet['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($vet['email']); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($vet['created_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No veterinarians found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-bolt text-primary"></i>
                                Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <button class="btn btn-outline-primary w-100 h-100 py-3">
                                        <i class="fas fa-user-md fa-2x mb-2"></i>
                                        <div>Manage Vets</div>
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button class="btn btn-outline-success w-100 h-100 py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                        <div>View Reports</div>
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button class="btn btn-outline-warning w-100 h-100 py-3">
                                        <i class="fas fa-cog fa-2x mb-2"></i>
                                        <div>System Settings</div>
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button class="btn btn-outline-info w-100 h-100 py-3">
                                        <i class="fas fa-bell fa-2x mb-2"></i>
                                        <div>Notifications</div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Veterinarians Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-list text-primary"></i>
                        All Veterinarians
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Veterinarian</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Last Login</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($all_vets)): ?>
                                    <?php foreach ($all_vets as $vet): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=0ea5e9&color=fff'; ?>" 
                                                         alt="<?php echo htmlspecialchars($vet['name']); ?>" 
                                                         class="user-avatar">
                                                    <div>
                                                        <div class="user-name"><?php echo htmlspecialchars($vet['name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($vet['email']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($vet['created_at'])); ?></td>
                                            <td>
                                                <?php if ($vet['last_login']): ?>
                                                    <?php echo date('M j, Y g:i A', strtotime($vet['last_login'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-active">Active</span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-action btn-edit me-1">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-action btn-deactivate me-1">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                    <button class="btn btn-action btn-delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-user-md fa-2x mb-3 d-block"></i>
                                            No veterinarians found in the system.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const logoText = document.querySelector('.logo-text');
            const navTexts = document.querySelectorAll('.nav-text');
            
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                logoText.style.display = 'none';
                navTexts.forEach(text => text.style.display = 'none');
            } else {
                logoText.style.display = 'inline';
                navTexts.forEach(text => text.style.display = 'inline');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

        // Add loading state to buttons
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', function() {
                if (this.type !== 'submit' && !this.classList.contains('dropdown-toggle')) {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    this.disabled = true;
                    
                    // Reset after 2 seconds (for demo purposes)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                }
            });
        });
    </script>
</body>
</html>
