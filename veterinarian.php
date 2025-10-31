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

// Initialize variables
$search_term = '';
$status_filter = '';
$specialization_filter = '';
$all_vets = [];
$vet_stats = [];
$specializations = [];
$recent_appointments = [];

try {
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
    if ($vet_stats_result) {
        $vet_stats = $vet_stats_result->fetch_assoc();
    }

    // Handle search and filter functionality
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
    if ($vets_result) {
        while ($row = $vets_result->fetch_assoc()) {
            $all_vets[] = $row;
        }
    }
    $stmt->close();

    // Get unique specializations for filter
    $specializations_query = "SELECT DISTINCT specialization FROM users WHERE role = 'vet' AND specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
    $specializations_result = $conn->query($specializations_query);
    if ($specializations_result) {
        while ($row = $specializations_result->fetch_assoc()) {
            $specializations[] = $row['specialization'];
        }
    }

    // Get recent appointments - SIMPLIFIED QUERY
    $recent_appointments_query = "
        SELECT a.appointment_id, a.pet_id, a.appointment_date, a.appointment_time, a.status, 
               a.service_type, a.pet_name, u.name as vet_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.appointment_date >= CURDATE() 
        AND a.status IN ('pending', 'confirmed')
        AND u.role = 'vet'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ";

    $recent_appointments_result = $conn->query($recent_appointments_query);
    if ($recent_appointments_result) {
        while ($row = $recent_appointments_result->fetch_assoc()) {
            $recent_appointments[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Error in veterinarian.php: " . $e->getMessage());
    // Continue execution but show error in debug mode
    if (ini_get('display_errors')) {
        echo "<!-- Error: " . $e->getMessage() . " -->";
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
            min-height: 100vh;
        }

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

        .sidebar {
            background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
            color: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .card-soft {
            background: var(--card);
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .card-soft:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.15);
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            border: none;
            color: white;
            font-weight: 800;
            border-radius: 14px;
            padding: 12px 20px;
            transition: var(--transition);
        }

        .specialization-tag {
            background: rgba(59, 130, 246, 0.1);
            color: var(--brand);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
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
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="me-3"><i class="fa-solid fa-user-shield"></i></div>
                <div class="h4 mb-0">VetCareQR</div>
            </div>
            
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center text-white" href="admin_dashboard.php">
                    <i class="fa-solid fa-gauge-high me-2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center text-white bg-white bg-opacity-25 rounded" href="veterinarian.php">
                    <i class="fa-solid fa-user-doctor me-2"></i>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center text-white" href="pet_owners.php">
                    <i class="fa-solid fa-users me-2"></i>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center text-white" href="pets.php">
                    <i class="fa-solid fa-paw me-2"></i>
                    <span>Pets</span>
                </a>
            </nav>

            <div class="mt-auto pt-4">
                <div class="admin-profile d-flex align-items-center p-3 rounded-3 bg-white bg-opacity-10">
                    <img src="<?php echo $admin['profile_picture'] ? htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name']) . '&background=3b82f6&color=fff'; ?>" 
                         class="rounded-circle me-3" width="50" height="50" alt="Admin" />
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($admin['name']); ?></div>
                        <small class="text-white-50">Administrator</small>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="d-flex flex-column gap-4">
            <!-- Topbar -->
            <div class="card-soft p-3">
                <div class="d-flex align-items-center">
                    <h1 class="h4 mb-0 fw-bold">Veterinarian Management</h1>
                    <span class="badge bg-light text-dark ms-3">Complete Overview</span>
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

            <!-- Simple Veterinarians Table -->
            <div class="card-soft p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">All Veterinarians</h5>
                    <a href="add_veterinarian.php" class="btn btn-brand">
                        <i class="fas fa-plus me-2"></i>Add New Veterinarian
                    </a>
                </div>
                
                <!-- Search Form -->
                <form method="GET" action="veterinarian.php" class="row g-3 mb-4">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by name, email, or specialization..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
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
                        <button type="submit" class="btn btn-brand w-100">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Veterinarian</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_vets)): ?>
                                <?php foreach ($all_vets as $vet): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=3b82f6&color=fff'; ?>" 
                                                     alt="<?php echo htmlspecialchars($vet['name']); ?>" 
                                                     class="rounded-circle me-3" width="40" height="40">
                                                <div>
                                                    <div class="fw-bold">Dr. <?php echo htmlspecialchars($vet['name']); ?></div>
                                                    <small class="text-muted">ID: VET<?php echo str_pad($vet['user_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($vet['email']); ?></td>
                                        <td>
                                            <span class="specialization-tag"><?php echo $vet['specialization'] ? htmlspecialchars($vet['specialization']) : 'General Practice'; ?></span>
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
                                                <button class="btn btn-sm btn-outline-primary me-1" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary me-1" title="Edit Profile">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($vet['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="Deactivate">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this veterinarian? This action cannot be undone.');">
                                                    <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
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
</body>
</html>
