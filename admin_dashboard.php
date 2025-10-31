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

// Handle search functionality
$search_term = '';
$status_filter = '';
$where_conditions = ["role = 'vet'"];
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= 'ss';
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $_GET['status'];
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Build the query
$where_clause = implode(' AND ', $where_conditions);
$vets_query = "
    SELECT user_id, name, email, profile_picture, created_at, last_login, status, phone_number, specialization
    FROM users 
    WHERE $where_clause
    ORDER BY created_at DESC
";

// Get all veterinarians
$stmt = $conn->prepare($vets_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vets_result = $stmt->get_result();
$all_vets = [];
if ($vets_result) {
    while ($row = $vets_result->fetch_assoc()) {
        $all_vets[] = $row;
    }
}
$stmt->close();

// Handle actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['vet_id'])) {
        $vet_id = intval($_POST['vet_id']);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'activate':
                $update_query = "UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'vet'";
                $success_message = "Veterinarian activated successfully!";
                break;
            case 'deactivate':
                $update_query = "UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'vet'";
                $success_message = "Veterinarian deactivated successfully!";
                break;
            case 'delete':
                $update_query = "DELETE FROM users WHERE user_id = ? AND role = 'vet'";
                $success_message = "Veterinarian deleted successfully!";
                break;
            default:
                $update_query = null;
        }
        
        if ($update_query) {
            $stmt = $conn->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param("i", $vet_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = $success_message;
                } else {
                    $_SESSION['error'] = "Error updating veterinarian: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Redirect back with search parameters
        $redirect_url = "admin_dashboard.php";
        $query_params = [];
        if ($search_term) $query_params[] = "search=" . urlencode($search_term);
        if ($status_filter) $query_params[] = "status=" . urlencode($status_filter);
        if ($query_params) $redirect_url .= "?" . implode('&', $query_params);
        
        header("Location: $redirect_url");
        exit();
    }
}

// Get recent veterinarians (for the sidebar/cards)
$recent_vets_query = "
    SELECT user_id, name, email, profile_picture, created_at, last_login, status
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

// Get veterinarian statistics
$vet_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'active') as active_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'inactive') as inactive_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'pending') as pending_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND last_login IS NULL) as never_logged_in,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND DATE(created_at) = CURDATE()) as new_today
";
$vet_stats_result = $conn->query($vet_stats_query);
$vet_stats = $vet_stats_result->fetch_assoc();
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
    --bg: #f0f8ff;
    --card: #ffffff;
    --ink: #1e3a8a;
    --muted: #64748b;
    --brand: #3b82f6;
    --brand-2: #2563eb;
    --warning: #f59e0b;
    --danger: #dc2626;
    --lav: #1d4ed8;
    --success: #059669;
    --info: #0ea5e9;
    --shadow: 0 10px 30px rgba(59, 130, 246, 0.1);
    --radius: 1.25rem;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(180deg, #f0f9ff 0%, #f0f8ff 40%, #f0f8ff 100%);
    color: var(--ink);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
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
    color: #e0f2fe;
    border-radius: 12px;
    padding: 14px 16px;
    font-weight: 600;
    transition: var(--transition);
    margin-bottom: 4px;
    text-decoration: none;
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

/* Stats in sidebar */
.sidebar-stats {
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 16px;
    margin: 20px 0;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-value {
    font-weight: 700;
    font-size: 1.1rem;
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
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.15);
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

/* Enhanced Table */
.table {
    margin: 0;
    font-size: 0.9rem;
}

.table th {
    border-top: none;
    font-weight: 700;
    color: var(--ink);
    padding: 1rem 0.75rem;
    background: #f8fafc;
    font-size: 0.85rem;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
    border-color: #f1f5f9;
    font-size: 0.85rem;
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
    width: 45px;
    height: 45px;
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
    font-size: 0.9rem;
}

.user-email {
    color: #64748b;
    font-size: 0.8rem;
    margin: 0;
}

.user-specialization {
    font-size: 0.75rem;
    color: var(--brand);
    font-weight: 600;
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
    background: rgba(5, 150, 105, 0.1);
    color: var(--success);
}

.status-inactive {
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
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

.btn-activate {
    background: rgba(5, 150, 105, 0.1);
    color: var(--success);
}

.btn-activate:hover {
    background: var(--success);
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
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
}

.btn-delete:hover {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.btn-view:hover {
    background: #8b5cf6;
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
    font-size: 0.9rem;
}

.toolbar .form-select:focus, .toolbar .form-control:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-brand {
    background: linear-gradient(135deg, var(--brand), var(--lav));
    border: none;
    color: white;
    font-weight: 800;
    border-radius: 14px;
    padding: 12px 20px;
    transition: var(--transition);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-brand:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    background: linear-gradient(135deg, var(--lav), var(--brand));
}

/* Search Section */
.search-section {
    background: var(--card);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.search-results {
    font-size: 0.9rem;
    color: var(--muted);
}

/* Stats Grid Enhancement */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
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

/* Veterinarian Card */
.vet-card {
    border-left: 4px solid var(--brand);
}

.specialization-tag {
    background: rgba(59, 130, 246, 0.1);
    color: var(--brand);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="icon me-3"><i class="fa-solid fa-user-shield"></i></div>
                <div class="brand h4 mb-0">VetCareQR</div>
            </div>
            
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center active" href="admin_dashboard.php">
                    <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
                    <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pet_owners.php">
                    <span class="icon"><i class="fa-solid fa-users"></i></span>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pets.php">
                    <span class="icon"><i class="fa-solid fa-paw"></i></span>
                    <span>Pets</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="appointments.php">
                    <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <span>Appointments</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="medical_records.php">
                    <span class="icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span>Medical Records</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="analytics.php">
                    <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span>Analytics</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="settings.php">
                    <span class="icon"><i class="fa-solid fa-gear"></i></span>
                    <span>Settings</span>
                </a>
            </nav>

            <!-- Veterinarian Statistics in Sidebar -->
            <div class="sidebar-stats">
                <h6 class="text-white mb-3">Veterinarian Stats</h6>
                <div class="stat-item">
                    <span>Active</span>
                    <span class="stat-value"><?php echo $vet_stats['active_vets']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Inactive</span>
                    <span class="stat-value"><?php echo $vet_stats['inactive_vets']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Pending</span>
                    <span class="stat-value"><?php echo $vet_stats['pending_vets']; ?></span>
                </div>
                <div class="stat-item">
                    <span>New Today</span>
                    <span class="stat-value"><?php echo $vet_stats['new_today']; ?></span>
                </div>
            </div>

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
                        <input class="form-control search-input" placeholder="Search veterinarians..." />
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
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title">Veterinarian Management</div>
                    <div class="search-results">
                        <?php if ($search_term || $status_filter): ?>
                            Showing <?php echo count($all_vets); ?> results
                            <?php if ($search_term): ?> for "<?php echo htmlspecialchars($search_term); ?>"<?php endif; ?>
                            <?php if ($status_filter): ?> with status "<?php echo htmlspecialchars($status_filter); ?>"<?php endif; ?>
                        <?php else: ?>
                            Total Veterinarians: <?php echo count($all_vets); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="GET" action="admin_dashboard.php" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" 
                                   placeholder="Search veterinarians by name or email..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-brand w-100">Search</button>
                        <?php if ($search_term || $status_filter): ?>
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Veterinarians Table -->
            <div class="card-soft p-4 fade-in">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Veterinarian</th>
                                <th>Contact Information</th>
                                <th>Specialization</th>
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
                                                    <div class="user-name">Dr. <?php echo htmlspecialchars($vet['name']); ?></div>
                                                    <div class="user-email">ID: VET<?php echo str_pad($vet['user_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-email"><?php echo htmlspecialchars($vet['email']); ?></div>
                                            <small class="text-muted"><?php echo $vet['phone_number'] ? htmlspecialchars($vet['phone_number']) : 'N/A'; ?></small>
                                        </td>
                                        <td>
                                            <span class="specialization-tag"><?php echo $vet['specialization'] ? htmlspecialchars($vet['specialization']) : 'General Practice'; ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($vet['created_at'])); ?></td>
                                        <td>
                                            <?php if ($vet['last_login']): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($vet['last_login'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never logged in</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = 'status-active';
                                            $status_text = 'Active';
                                            if ($vet['status'] === 'inactive') {
                                                $status_class = 'status-inactive';
                                                $status_text = 'Inactive';
                                            } elseif ($vet['status'] === 'pending') {
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-action btn-view me-1" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-action btn-edit me-1" title="Edit Profile">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($vet['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" class="btn btn-action btn-deactivate me-1" title="Deactivate">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-action btn-activate me-1" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this veterinarian? This action cannot be undone.');">
                                                    <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-action btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-user-md fa-2x mb-3 d-block"></i>
                                        <?php if ($search_term): ?>
                                            No veterinarians found matching "<?php echo htmlspecialchars($search_term); ?>"
                                        <?php else: ?>
                                            No veterinarians found in the system.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                this.disabled = true;

                // Reset after 2 seconds (for demo purposes)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 2000);
            });
        });

        // Real-time search functionality
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });
        }
    </script>
</body>
</html>

