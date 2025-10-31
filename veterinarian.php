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

// Get veterinarian statistics
$vet_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'active') as active_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'inactive') as inactive_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND status = 'pending') as pending_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND last_login IS NULL) as never_logged_in,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND DATE(created_at) = CURDATE()) as new_today,
        (SELECT COUNT(*) FROM users WHERE role = 'vet') as total_vets,
        (SELECT COUNT(*) FROM users WHERE role = 'vet' AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_this_week
";
$vet_stats_result = $conn->query($vet_stats_query);
$vet_stats = $vet_stats_result->fetch_assoc();

// Handle search and filter functionality
$search_term = '';
$status_filter = '';
$specialization_filter = '';
$where_conditions = ["role = 'vet'"];
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR specialization LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= 'sss';
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $_GET['status'];
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (isset($_GET['specialization']) && !empty($_GET['specialization'])) {
    $specialization_filter = $_GET['specialization'];
    $where_conditions[] = "specialization = ?";
    $params[] = $specialization_filter;
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

// Get unique specializations for filter
$specializations_query = "SELECT DISTINCT specialization FROM users WHERE role = 'vet' AND specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
$specializations_result = $conn->query($specializations_query);
$specializations = [];
if ($specializations_result) {
    while ($row = $specializations_result->fetch_assoc()) {
        $specializations[] = $row['specialization'];
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
        $redirect_url = "veterinarian.php";
        $query_params = [];
        if ($search_term) $query_params[] = "search=" . urlencode($search_term);
        if ($status_filter) $query_params[] = "status=" . urlencode($status_filter);
        if ($specialization_filter) $query_params[] = "specialization=" . urlencode($specialization_filter);
        if ($query_params) $redirect_url .= "?" . implode('&', $query_params);
        
        header("Location: $redirect_url");
        exit();
    }
}

// Get recent appointments for veterinarians - CORRECTED QUERY
$recent_appointments_query = "
    SELECT a.appointment_id, a.pet_id, a.appointment_date, a.appointment_time, a.status, 
           a.service_type, a.pet_name, u.name as vet_name, po.name as owner_name
    FROM appointments a
    JOIN users u ON a.user_id = u.user_id AND u.role = 'vet'
    LEFT JOIN pets p ON a.pet_id = p.pet_id
    LEFT JOIN users po ON p.owner_id = po.user_id
    WHERE a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
";

$recent_appointments_result = $conn->query($recent_appointments_query);
$recent_appointments = [];
if ($recent_appointments_result) {
    while ($row = $recent_appointments_result->fetch_assoc()) {
        $recent_appointments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Management - VetCareQR</title>
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

        /* ... (rest of your CSS remains the same) ... */
        
        /* Appointment List */
        .appointment-item {
            border-left: 3px solid var(--brand);
            padding: 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .appointment-status-pending {
            color: var(--warning);
            font-weight: 600;
        }

        .appointment-status-confirmed {
            color: var(--info);
            font-weight: 600;
        }

        .appointment-status-completed {
            color: var(--success);
            font-weight: 600;
        }

        .appointment-status-cancelled {
            color: var(--danger);
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
                <a class="nav-link d-flex align-items-center" href="admin_dashboard.php">
                    <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center active" href="veterinarian.php">
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
                    <span>Total Vets</span>
                    <span class="stat-value"><?php echo $vet_stats['total_vets']; ?></span>
                </div>
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
                    <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
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
                    <h1 class="h4 mb-0 fw-bold">Veterinarian Management</h1>
                    <span class="badge bg-light text-dark ms-3">Complete Overview</span>
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
                        <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
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

            <!-- Veterinarian Statistics Cards -->
            <div class="stats-grid">
                <div class="card-soft fade-in" style="animation-delay: 0.1s">
                    <div class="kpi">
                        <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Total Veterinarians</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['total_vets']; ?></div>
                                <span class="badge-dot" style="color:#10b981">+<?php echo $vet_stats['new_today']; ?> today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.2s">
                    <div class="kpi">
                        <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Active Veterinarians</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['active_vets']; ?></div>
                                <span class="badge-dot" style="color:#10b981"><?php echo $vet_stats['active_this_week']; ?> this week</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.3s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff0f5;color:#c2417a">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Pending Approval</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['pending_vets']; ?></div>
                                <span class="badge-dot" style="color:#f59e0b">Needs review</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.4s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff7e6;color:#b45309">
                            <i class="fa-solid fa-user-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Never Logged In</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['never_logged_in']; ?></div>
                                <span class="badge-dot" style="color:#ef4444">Requires follow-up</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title">Manage Veterinarians</div>
                    <div class="search-results">
                        <?php if ($search_term || $status_filter || $specialization_filter): ?>
                            Showing <?php echo count($all_vets); ?> results
                            <?php if ($search_term): ?> for "<?php echo htmlspecialchars($search_term); ?>"<?php endif; ?>
                            <?php if ($status_filter): ?> with status "<?php echo htmlspecialchars($status_filter); ?>"<?php endif; ?>
                            <?php if ($specialization_filter): ?> in "<?php echo htmlspecialchars($specialization_filter); ?>"<?php endif; ?>
                        <?php else: ?>
                            Total Veterinarians: <?php echo count($all_vets); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="GET" action="veterinarian.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" 
                                   placeholder="Search by name, email, or specialization..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="specialization">
                            <option value="">All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>" 
                                    <?php echo ($specialization_filter === $spec) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-brand w-100">Search</button>
                        <?php if ($search_term || $status_filter || $specialization_filter): ?>
                            <a href="veterinarian.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="row g-4">
                <!-- Veterinarians Table -->
                <div class="col-lg-8">
                    <div class="card-soft p-4 fade-in">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="section-title mb-0">All Veterinarians</h5>
                            <a href="add_veterinarian.php" class="btn btn-brand">
                                <i class="fas fa-plus me-2"></i>Add New Veterinarian
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Veterinarian</th>
                                        <th>Contact Information</th>
                                        <th>Specialization</th>
                                        <th>Registration Date</th>
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
                                                        <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=3b82f6&color=fff'; ?>" 
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
                                                        <button class="btn btn-action btn-view me-1" title="View Profile" onclick="viewVetProfile(<?php echo $vet['user_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-action btn-edit me-1" title="Edit Profile" onclick="editVetProfile(<?php echo $vet['user_id']; ?>)">
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
                                            <td colspan="6" class="text-center py-4 text-muted">
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
                </div>

                <!-- Sidebar with Recent Appointments and Quick Stats -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card-soft p-4 mb-4">
                        <h5 class="section-title mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="add_veterinarian.php" class="btn btn-brand">
                                <i class="fas fa-user-plus me-2"></i>Add New Veterinarian
                            </a>
                            <a href="vet_specializations.php" class="btn btn-outline-primary">
                                <i class="fas fa-tags me-2"></i>Manage Specializations
                            </a>
                            <a href="vet_reports.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>Generate Reports
                            </a>
                        </div>
                    </div>

                    <!-- Upcoming Appointments -->
                    <div class="card-soft p-4">
                        <h5 class="section-title mb-3">Upcoming Appointments</h5>
                        <?php if (!empty($recent_appointments)): ?>
                            <?php foreach ($recent_appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong><?php echo htmlspecialchars($appointment['pet_name']); ?></strong>
                                        <small class="appointment-status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </small>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <i class="fas fa-user-md me-1"></i>Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <i class="fas fa-stethoscope me-1"></i><?php echo htmlspecialchars($appointment['service_type']); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                                No upcoming appointments
                            </div>
                        <?php endif; ?>
                    </div>
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

        // Veterinarian profile functions
        function viewVetProfile(vetId) {
            // Implement view veterinarian profile functionality
            window.location.href = 'vet_profile.php?id=' + vetId;
        }

        function editVetProfile(vetId) {
            // Implement edit veterinarian profile functionality
            window.location.href = 'edit_veterinarian.php?id=' + vetId;
        }

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

        // Status filter change
        const statusFilter = document.querySelector('select[name="status"]');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // Specialization filter change
        const specializationFilter = document.querySelector('select[name="specialization"]');
        if (specializationFilter) {
            specializationFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }
    </script>
</body>
</html>

