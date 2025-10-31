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
    header("Location: login_admin.php");
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

// Handle search functionality for pet owners
$search_term = '';
$status_filter = '';
$where_conditions = ["u.role = 'owner'"];
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= 'ss';
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $_GET['status'];
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Build the query for pet owners - FIXED JOIN CONDITION
$where_clause = implode(' AND ', $where_conditions);
$owners_query = "
    SELECT u.user_id, u.name, u.email, u.profile_picture, u.created_at, u.last_login, u.status, u.phone_number,
           COUNT(p.pet_id) as pet_count
    FROM users u
    LEFT JOIN pets p ON u.user_id = p.owner_id  -- FIXED: Changed p.user_id to p.owner_id
    WHERE $where_clause
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
";

// Debug: Uncomment to see the actual query
// echo "Query: " . $owners_query . "<br>";
// echo "Params: " . print_r($params, true) . "<br>";

// Get all pet owners
$stmt = $conn->prepare($owners_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$owners_result = $stmt->get_result();
$all_owners = [];
if ($owners_result) {
    while ($row = $owners_result->fetch_assoc()) {
        $all_owners[] = $row;
    }
}
$stmt->close();

// Handle actions (activate/deactivate/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'activate':
                $update_query = "UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'owner'";
                $success_message = "Pet owner activated successfully!";
                break;
            case 'deactivate':
                $update_query = "UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'owner'";
                $success_message = "Pet owner deactivated successfully!";
                break;
            case 'delete':
                $update_query = "DELETE FROM users WHERE user_id = ? AND role = 'owner'";
                $success_message = "Pet owner deleted successfully!";
                break;
            default:
                $update_query = null;
        }
        
        if ($update_query) {
            $stmt = $conn->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = $success_message;
                } else {
                    $_SESSION['error'] = "Error updating pet owner: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Redirect back with search parameters
        $redirect_url = "pet_owners.php";
        $query_params = [];
        if ($search_term) $query_params[] = "search=" . urlencode($search_term);
        if ($status_filter) $query_params[] = "status=" . urlencode($status_filter);
        if ($query_params) $redirect_url .= "?" . implode('&', $query_params);
        
        header("Location: $redirect_url");
        exit();
    }
}

// Get pet owner statistics
$owner_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'owner' AND status = 'active') as active_owners,
        (SELECT COUNT(*) FROM users WHERE role = 'owner' AND status = 'inactive') as inactive_owners,
        (SELECT COUNT(*) FROM users WHERE role = 'owner' AND status = 'pending') as pending_owners,
        (SELECT COUNT(*) FROM users WHERE role = 'owner' AND last_login IS NULL) as never_logged_in,
        (SELECT COUNT(*) FROM users WHERE role = 'owner' AND DATE(created_at) = CURDATE()) as new_today
";
$owner_stats_result = $conn->query($owner_stats_query);
$owner_stats = $owner_stats_result->fetch_assoc();

// Get total statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'owner') as total_owners,
        (SELECT COUNT(*) FROM pets) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE status = 'scheduled') as upcoming_appointments,
        (SELECT COUNT(*) FROM appointments WHERE DATE(created_at) = CURDATE()) as today_appointments
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Owners Management - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
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

        /* ... (keep all your existing CSS styles) ... */
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
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
                    <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center active" href="pet_owners.php">
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

            <!-- Pet Owner Statistics in Sidebar -->
            <div class="sidebar-stats">
                <h6 class="text-white mb-3">Pet Owner Stats</h6>
                <div class="stat-item">
                    <span>Active</span>
                    <span class="stat-value"><?php echo $owner_stats['active_owners']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Inactive</span>
                    <span class="stat-value"><?php echo $owner_stats['inactive_owners']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Pending</span>
                    <span class="stat-value"><?php echo $owner_stats['pending_owners']; ?></span>
                </div>
                <div class="stat-item">
                    <span>New Today</span>
                    <span class="stat-value"><?php echo $owner_stats['new_today']; ?></span>
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
                    <h1 class="h4 mb-0 fw-bold">Pet Owners Management</h1>
                    <span class="badge bg-light text-dark ms-3">BrightView Veterinary Clinic</span>
                </div>

                <!-- REMOVED DUPLICATE SEARCH BAR FROM TOPBAR -->
                <!-- The main search is in the search section below -->

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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="card-soft fade-in" style="animation-delay: 0.1s">
                    <div class="kpi">
                        <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Total Pet Owners</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $stats['total_owners'] ?? '0'; ?></div>
                                <span class="badge-dot" style="color:#10b981">+<?php echo $owner_stats['new_today']; ?> today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.2s">
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

                <div class="card-soft fade-in" style="animation-delay: 0.3s">
                    <div class="kpi">
                        <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
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

                <div class="card-soft fade-in" style="animation-delay: 0.4s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff7e6;color:#b45309">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Active Owners</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $owner_stats['active_owners']; ?></div>
                                <span class="badge-dot" style="color:#ef4444"><?php echo $owner_stats['inactive_owners']; ?> inactive</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title">Pet Owner Management</div>
                    <div class="search-results">
                        <?php if ($search_term || $status_filter): ?>
                            Showing <?php echo count($all_owners); ?> results
                            <?php if ($search_term): ?> for "<?php echo htmlspecialchars($search_term); ?>"<?php endif; ?>
                            <?php if ($status_filter): ?> with status "<?php echo htmlspecialchars($status_filter); ?>"<?php endif; ?>
                        <?php else: ?>
                            Total Pet Owners: <?php echo count($all_owners); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="GET" action="pet_owners.php" class="row g-3 align-items-end" id="searchForm">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" id="searchInput"
                                   placeholder="Search pet owners by name or email..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="status" id="statusSelect">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-brand w-100">Search</button>
                        <?php if ($search_term || $status_filter): ?>
                            <a href="pet_owners.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Pet Owners Table -->
            <div class="card-soft p-4 fade-in">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Pet Owner</th>
                                <th>Contact Information</th>
                                <th>Pets Registered</th>
                                <th>Registration Date</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_owners)): ?>
                                <?php foreach ($all_owners as $owner): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <img src="<?php echo $owner['profile_picture'] ? htmlspecialchars($owner['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($owner['name']) . '&background=8b5cf6&color=fff'; ?>" 
                                                     alt="<?php echo htmlspecialchars($owner['name']); ?>" 
                                                     class="user-avatar">
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($owner['name']); ?></div>
                                                    <div class="user-email">ID: OWN<?php echo str_pad($owner['user_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-email"><?php echo htmlspecialchars($owner['email']); ?></div>
                                            <small class="text-muted"><?php echo $owner['phone_number'] ? htmlspecialchars($owner['phone_number']) : 'N/A'; ?></small>
                                        </td>
                                        <td>
                                            <span class="pet-count-badge">
                                                <i class="fas fa-paw me-1"></i>
                                                <?php echo $owner['pet_count']; ?> pet(s)
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($owner['created_at'])); ?></td>
                                        <td>
                                            <?php if ($owner['last_login']): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($owner['last_login'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never logged in</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = 'status-active';
                                            $status_text = 'Active';
                                            if ($owner['status'] === 'inactive') {
                                                $status_class = 'status-inactive';
                                                $status_text = 'Inactive';
                                            } elseif ($owner['status'] === 'pending') {
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
                                                <?php if ($owner['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $owner['user_id']; ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" class="btn btn-action btn-deactivate me-1" title="Deactivate">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $owner['user_id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="btn btn-action btn-activate me-1" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this pet owner? This action cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?php echo $owner['user_id']; ?>">
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
                                        <i class="fas fa-users fa-2x mb-3 d-block"></i>
                                        <?php if ($search_term): ?>
                                            No pet owners found matching "<?php echo htmlspecialchars($search_term); ?>"
                                        <?php else: ?>
                                            No pet owners found in the system.
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

        // Real-time search functionality - FIXED
        const searchInput = document.getElementById('searchInput');
        const statusSelect = document.getElementById('statusSelect');
        const searchForm = document.getElementById('searchForm');

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        searchForm.submit();
                    }
                }, 800);
            });
        }

        // Auto-submit when status changes
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                searchForm.submit();
            });
        }

        // Add loading state to search button
        const searchButton = searchForm.querySelector('button[type="submit"]');
        if (searchButton) {
            searchForm.addEventListener('submit', function() {
                searchButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Searching...';
                searchButton.disabled = true;
            });
        }
    </script>
</body>
</html>
