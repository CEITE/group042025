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
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND DATE(created_at) = CURDATE()) as new_vets_today,
        (SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as completed_today
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
    <style>
        :root {
            --bg: #fff5f8;
            --card: #ffffff;
            --ink: #4a0e2e;
            --muted: #6b7280;
            --brand: #f06292;
            --brand-2: #ec407a;
            --warning: #f59e0b;
            --danger: #e11d48;
            --lav: #d63384;
            --success: #10b981;
            --info: #0ea5e9;
            --shadow: 0 10px 30px rgba(236,64,122,.1);
            --radius: 1.25rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(180deg, #fff0f6 0%, #fff5f8 40%, #fff5f8 100%);
            color: var(--ink);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Shell layout */
        .app-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
            gap: 24px;
            padding: 24px;
            max-width: 1920px;
            margin: 0 auto;
        }

        @media (max-width: 992px) {
            .app-shell {
                grid-template-columns: 1fr;
                padding: 16px;
                gap: 16px;
            }
        }

        /* Enhanced Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
            color: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .brand {
            font-weight: 800;
            color: #fff;
            font-size: 1.5rem;
        }

        .sidebar .nav-link {
            color: #ffe6ef;
            border-radius: 12px;
            padding: 14px 16px;
            font-weight: 600;
            transition: var(--transition);
            margin-bottom: 4px;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .sidebar .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.2);
            margin-right: 12px;
            transition: var(--transition);
        }

        .sidebar .nav-link.active .icon,
        .sidebar .nav-link:hover .icon {
            background: rgba(255,255,255,.3);
            transform: scale(1.1);
        }

        /* Enhanced Topbar */
        .topbar {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.8);
        }

        .topbar .search {
            flex: 1;
            max-width: 480px;
        }

        .form-control.search-input {
            border: none;
            background: #f8fafc;
            border-radius: 14px;
            padding: 12px 16px;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .form-control.search-input:focus {
            background: white;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
            transform: translateY(-2px);
        }

        /* Enhanced Cards */
        .card-soft {
            background: var(--card);
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.8);
            overflow: hidden;
        }

        .card-soft:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(236,64,122,.15);
        }

        /* Enhanced KPI Cards */
        .kpi {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            position: relative;
        }

        .kpi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--brand), var(--lav));
            opacity: 0;
            transition: var(--transition);
        }

        .kpi:hover::before {
            opacity: 1;
        }

        .kpi .bubble {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 24px;
            transition: var(--transition);
        }

        .kpi:hover .bubble {
            transform: scale(1.1) rotate(5deg);
        }

        .kpi small {
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-size: 0.75rem;
        }

        .kpi .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1;
            margin: 8px 0 4px;
            background: linear-gradient(135deg, var(--ink), var(--lav));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: var(--muted);
            font-size: 0.75rem;
        }

        .badge-dot::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Enhanced Progress Meter */
        .meter-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .meter {
            --p: 70;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(var(--brand) calc(var(--p)*1%), #e8eef6 0);
            display: grid;
            place-items: center;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .meter .hole {
            width: 64px;
            height: 64px;
            background: #fff;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 800;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Enhanced Table */
        .table {
            margin: 0;
        }

        .table th {
            border-top: none;
            font-weight: 700;
            color: var(--ink);
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
            color: var(--ink);
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
            color: var(--info);
        }

        .btn-edit:hover {
            background: var(--info);
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

        /* Enhanced Section Titles */
        .section-title {
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--ink), var(--lav));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtle {
            color: var(--muted);
            font-weight: 600;
        }

        /* Enhanced Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .toolbar .form-select, .toolbar .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .toolbar .form-select:focus, .toolbar .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            border: none;
            color: white;
            font-weight: 800;
            border-radius: 14px;
            padding: 12px 20px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(240, 98, 146, 0.3);
        }

        .btn-brand:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(240, 98, 146, 0.4);
            background: linear-gradient(135deg, var(--lav), var(--brand));
        }

        /* Stats Grid Enhancement */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        /* Loading Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            animation: pulse 2s infinite;
        }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .app-shell {
                gap: 16px;
                padding: 12px;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .topbar .search {
                max-width: 100%;
            }

            .toolbar {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-5">
                <div class="icon me-3"><i class="fa-solid fa-user-shield"></i></div>
                <div class="brand h4 mb-0">VetCareQR</div>
            </div>
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center active" href="admin_dashboard.php">
                    <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <span>Appointments</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-users"></i></span>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-paw"></i></span>
                    <span>Pets</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span>Analytics</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="#">
                    <span class="icon"><i class="fa-solid fa-gear"></i></span>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="mt-auto pt-4">
                <div class="admin-profile d-flex align-items-center p-3 rounded-3" style="background: rgba(255,255,255,0.1);">
                    <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=f06292&color=fff'; ?>" 
                         class="rounded-circle me-3" width="50" height="50" alt="Admin" />
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($admin['name']); ?></div>
                        <small class="text-white-50">Administrator</small>
                    </div>
                    <a href="logout.php" class="text-white-50"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="d-flex flex-column gap-4">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex align-items-center">
                    <h1 class="h4 mb-0 fw-bold">Admin Dashboard</h1>
                    <span class="badge bg-light text-dark ms-3">Veterinarian Management</span>
                </div>

                <div class="search ms-auto">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input class="form-control search-input" placeholder="Search veterinarians, appointments, pets..." />
                    </div>
                </div>

                <div class="position-relative">
                    <button class="btn btn-light rounded-circle position-relative">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                </div>

                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=f06292&color=fff'; ?>" 
                             class="rounded-circle" width="40" height="40" alt="Admin" />
                        <span class="fw-bold d-none d-md-inline"><?php echo htmlspecialchars($admin['name']); ?></span>
                        <i class="fa-solid fa-chevron-down text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

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

            <!-- Tabs & Toolbar -->
            <div class="card-soft p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="page-tabs">
                    <button class="btn-tab active">Overview</button>
                    <button class="btn-tab">Veterinarians</button>
                    <button class="btn-tab">Appointments</button>
                    <button class="btn-tab">Reports</button>
                </div>
                <div class="toolbar">
                    <div class="input-group" style="width: 180px">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-calendar"></i></span>
                        <input type="date" class="form-control border-start-0" id="datePicker" value="<?php echo date('Y-m-d'); ?>" />
                    </div>
                    <select class="form-select" style="width: 200px">
                        <option>All Veterinarians</option>
                        <option>Dr. Aman Sharma</option>
                        <option>Dr. Maria Santos</option>
                        <option>Dr. James Wilson</option>
                    </select>
                    <button class="btn-brand" id="addVeterinarian">
                        <i class="fa-solid fa-user-plus me-2"></i>Add Veterinarian
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="card-soft fade-in" style="animation-delay: 0.1s">
                    <div class="kpi">
                        <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Total Veterinarians</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $stats['total_vets'] ?? '0'; ?></div>
                                <span class="badge-dot" style="color:#10b981">+<?php echo $stats['new_vets_today'] ?? '0'; ?> today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.2s">
                    <div class="kpi">
                        <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Today's Appointments</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $stats['today_appointments'] ?? '0'; ?></div>
                                <span class="badge-dot" style="color:#f59e0b"><?php echo $stats['upcoming_appointments'] ?? '0'; ?> upcoming</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.3s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff0f5;color:#c2417a">
                            <i class="fa-solid fa-paw"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Registered Pets</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $stats['total_pets'] ?? '0'; ?></div>
                                <span class="badge-dot" style="color:#8b5cf6">Active</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.4s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff7e6;color:#b45309">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Pet Owners</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $stats['total_owners'] ?? '0'; ?></div>
                                <span class="badge-dot" style="color:#ef4444">Registered</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft p-4 fade-in" style="animation-delay: 0.5s">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="small text-uppercase text-600 subtle">System Load</div>
                            <div class="muted">Current Performance</div>
                        </div>
                        <div class="meter-wrap">
                            <div class="meter" id="systemLoad" style="--p:65">
                                <div class="hole"><span>65%</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="row g-4">
                <!-- Recent Veterinarians -->
                <div class="col-12 col-lg-8">
                    <div class="card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="section-title">Recent Veterinarians</div>
                            <a href="#" class="text-decoration-none fw-bold" style="color: var(--brand);">View All</a>
                        </div>

                        <div class="row g-4">
                            <?php if (!empty($recent_vets)): ?>
                                <?php foreach ($recent_vets as $vet): ?>
                                    <div class="col-12 col-md-6">
                                        <div class="card-soft p-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=f06292&color=fff'; ?>" 
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
                                            <div class="mt-3 d-flex gap-2">
                                                <button class="btn btn-action btn-edit flex-grow-1">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                                <button class="btn btn-action btn-deactivate">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-muted text-center py-4">No veterinarians found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- All Veterinarians Table -->
                    <div class="card-soft p-4 mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="section-title">All Veterinarians</div>
                            <div class="subtle">(<?php echo count($all_vets); ?> total)</div>
                        </div>
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
                                                        <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=f06292&color=fff'; ?>" 
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

                <!-- Quick Stats & Actions -->
                <div class="col-12 col-lg-4">
                    <div class="card-soft p-4 mb-4">
                        <div class="section-title mb-3">Quick Actions</div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary d-flex align-items-center justify-content-between p-3">
                                <span>Manage Veterinarians</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <button class="btn btn-outline-success d-flex align-items-center justify-content-between p-3">
                                <span>View Reports</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <button class="btn btn-outline-warning d-flex align-items-center justify-content-between p-3">
                                <span>System Settings</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <button class="btn btn-outline-info d-flex align-items-center justify-content-between p-3">
                                <span>Send Announcement</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-soft p-4">
                        <div class="section-title mb-3">System Overview</div>
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Database Size</span>
                                <span class="fw-bold">2.4 GB</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Active Sessions</span>
                                <span class="fw-bold">24</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Server Uptime</span>
                                <span class="fw-bold">99.8%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Pending Tasks</span>
                                <span class="fw-bold text-warning">7</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set system load meter
        function setSystemLoad(p) {
            const el = document.getElementById('systemLoad');
            el.style.setProperty('--p', p);
            el.querySelector('.hole span').textContent = p + '%';
        }
        setSystemLoad(65);

        // Add loading animation to cards
        document.querySelectorAll('.card-soft').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
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

        // Tab functionality
        document.querySelectorAll('.btn-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.btn-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
