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

    // Get recent appointments
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
}

// Handle actions (activate/deactivate/delete/edit/view)
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
                // Check if veterinarian has any appointments before deleting
                $check_appointments = "SELECT COUNT(*) as appointment_count FROM appointments WHERE user_id = ?";
                $stmt_check = $conn->prepare($check_appointments);
                $stmt_check->bind_param("i", $vet_id);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                $appointment_count = $result->fetch_assoc()['appointment_count'];
                $stmt_check->close();
                
                if ($appointment_count > 0) {
                    $_SESSION['error'] = "Cannot delete veterinarian. They have $appointment_count appointment(s) scheduled. Please reassign or cancel appointments first.";
                } else {
                    $update_query = "DELETE FROM users WHERE user_id = ? AND role = 'vet'";
                    $success_message = "Veterinarian deleted successfully!";
                }
                break;
                
            case 'update_profile':
                if (isset($_POST['name']) && isset($_POST['email']) && isset($_POST['specialization'])) {
                    $name = trim($_POST['name']);
                    $email = trim($_POST['email']);
                    $specialization = trim($_POST['specialization']);
                    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : null;
                    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
                    
                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['error'] = "Invalid email format!";
                        break;
                    }
                    
                    // Check if email already exists (excluding current vet)
                    $email_check = "SELECT user_id FROM users WHERE email = ? AND user_id != ? AND role = 'vet'";
                    $stmt_check = $conn->prepare($email_check);
                    $stmt_check->bind_param("si", $email, $vet_id);
                    $stmt_check->execute();
                    $email_result = $stmt_check->get_result();
                    
                    if ($email_result->num_rows > 0) {
                        $_SESSION['error'] = "Email already exists for another veterinarian!";
                        $stmt_check->close();
                        break;
                    }
                    $stmt_check->close();
                    
                    $update_query = "UPDATE users SET name = ?, email = ?, specialization = ?, phone_number = ?, status = ? WHERE user_id = ? AND role = 'vet'";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("sssssi", $name, $email, $specialization, $phone_number, $status, $vet_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Veterinarian profile updated successfully!";
                    } else {
                        $_SESSION['error'] = "Error updating veterinarian: " . $stmt->error;
                    }
                    $stmt->close();
                }
                break;
                
            case 'send_welcome_email':
                // Get veterinarian details
                $vet_query = "SELECT name, email FROM users WHERE user_id = ? AND role = 'vet'";
                $stmt = $conn->prepare($vet_query);
                $stmt->bind_param("i", $vet_id);
                $stmt->execute();
                $vet_result = $stmt->get_result();
                
                if ($vet_result->num_rows > 0) {
                    $vet = $vet_result->fetch_assoc();
                    
                    // In a real application, you would send an actual email here
                    // This is just a simulation
                    $email_sent = true; // Simulate email sending
                    
                    if ($email_sent) {
                        $_SESSION['success'] = "Welcome email sent to Dr. " . htmlspecialchars($vet['name']) . " at " . htmlspecialchars($vet['email']);
                    } else {
                        $_SESSION['error'] = "Failed to send welcome email. Please try again.";
                    }
                } else {
                    $_SESSION['error'] = "Veterinarian not found!";
                }
                $stmt->close();
                break;
                
            case 'reset_password':
                // Generate a temporary password
                $temp_password = bin2hex(random_bytes(4)); // 8 character temporary password
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $update_query = "UPDATE users SET password = ? WHERE user_id = ? AND role = 'vet'";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $hashed_password, $vet_id);
                
                if ($stmt->execute()) {
                    // Get vet email for notification (in real app, you'd send an email)
                    $vet_query = "SELECT name, email FROM users WHERE user_id = ?";
                    $vet_stmt = $conn->prepare($vet_query);
                    $vet_stmt->bind_param("i", $vet_id);
                    $vet_stmt->execute();
                    $vet_result = $vet_stmt->get_result();
                    $vet = $vet_result->fetch_assoc();
                    $vet_stmt->close();
                    
                    $_SESSION['success'] = "Password reset for Dr. " . htmlspecialchars($vet['name']) . ". Temporary password: " . $temp_password . " (Please notify the veterinarian)";
                } else {
                    $_SESSION['error'] = "Error resetting password: " . $stmt->error;
                }
                $stmt->close();
                break;
                
            default:
                $_SESSION['error'] = "Invalid action!";
        }
        
        // Execute the query for simple actions (not for update_profile which already executed)
        if (isset($update_query) && $action !== 'update_profile' && $action !== 'delete_with_appointments') {
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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_vets'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_vets = $_POST['selected_vets'];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($selected_vets as $vet_id) {
        $vet_id = intval($vet_id);
        
        switch ($bulk_action) {
            case 'activate_selected':
                $query = "UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'vet'";
                break;
            case 'deactivate_selected':
                $query = "UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'vet'";
                break;
            case 'delete_selected':
                // Check for appointments before deletion
                $check_appointments = "SELECT COUNT(*) as appointment_count FROM appointments WHERE user_id = ?";
                $stmt_check = $conn->prepare($check_appointments);
                $stmt_check->bind_param("i", $vet_id);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                $appointment_count = $result->fetch_assoc()['appointment_count'];
                $stmt_check->close();
                
                if ($appointment_count > 0) {
                    $error_count++;
                    continue 2; // Skip to next veterinarian
                }
                $query = "DELETE FROM users WHERE user_id = ? AND role = 'vet'";
                break;
            default:
                continue 2;
        }
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $vet_id);
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        } else {
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        $_SESSION['success'] = "Bulk action completed: $success_count veterinarian(s) updated successfully.";
    }
    if ($error_count > 0) {
        $_SESSION['error'] = "Failed to update $error_count veterinarian(s). Some may have appointments scheduled.";
    }
    
    // Redirect back
    header("Location: veterinarian.php");
    exit();
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
        }

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
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .kpi {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
        }

        .kpi .bubble {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 24px;
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

        .bulk-actions {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="me-3"><i class="fa-solid fa-user-shield fa-lg"></i></div>
                <div class="h4 mb-0 fw-bold">VetCareQR</div>
            </div>
            
            <nav class="nav flex-column gap-2">
                <a class="nav-link d-flex align-items-center" href="admin_dashboard.php">
                    <i class="fa-solid fa-gauge-high me-3"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center active" href="veterinarian.php">
                    <i class="fa-solid fa-user-doctor me-3"></i>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pet_owners.php">
                    <i class="fa-solid fa-users me-3"></i>
                    <span>Pet Owners</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pets.php">
                    <i class="fa-solid fa-paw me-3"></i>
                    <span>Pets</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="appointments.php">
                    <i class="fa-solid fa-calendar-check me-3"></i>
                    <span>Appointments</span>
                </a>
            </nav>

            <!-- Veterinarian Statistics in Sidebar -->
            <div class="sidebar-stats mt-4 p-3 rounded-3 bg-white bg-opacity-10">
                <h6 class="text-white mb-3">Veterinarian Stats</h6>
                <div class="stat-item text-white">
                    <span>Total Vets</span>
                    <span class="fw-bold"><?php echo $vet_stats['total_vets'] ?? 0; ?></span>
                </div>
                <div class="stat-item text-white">
                    <span>Active</span>
                    <span class="fw-bold"><?php echo $vet_stats['active_vets'] ?? 0; ?></span>
                </div>
                <div class="stat-item text-white">
                    <span>Pending</span>
                    <span class="fw-bold"><?php echo $vet_stats['pending_vets'] ?? 0; ?></span>
                </div>
            </div>

            <div class="mt-auto pt-4">
                <div class="admin-profile d-flex align-items-center p-3 rounded-3 bg-white bg-opacity-10">
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
            <div class="card-soft p-4">
                <div class="d-flex align-items-center">
                    <h1 class="h4 mb-0 fw-bold">Veterinarian Management</h1>
                    <span class="badge bg-light text-dark ms-3">Complete Overview</span>
                    <div class="ms-auto d-flex gap-3">
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text bg-transparent border-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input class="form-control border-0 bg-light" placeholder="Search..." />
                        </div>
                        <button class="btn btn-light rounded-circle position-relative">
                            <i class="fa-regular fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">3</span>
                        </button>
                    </div>
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
                <div class="card-soft">
                    <div class="kpi">
                        <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Total Veterinarians</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['total_vets'] ?? 0; ?></div>
                                <span class="badge bg-success bg-opacity-10 text-success">+<?php echo $vet_stats['new_today'] ?? 0; ?> today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft">
                    <div class="kpi">
                        <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Active Veterinarians</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['active_vets'] ?? 0; ?></div>
                                <span class="badge bg-success bg-opacity-10 text-success"><?php echo $vet_stats['active_this_week'] ?? 0; ?> this week</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff0f5;color:#c2417a">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Pending Approval</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['pending_vets'] ?? 0; ?></div>
                                <span class="badge bg-warning bg-opacity-10 text-warning">Needs review</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff7e6;color:#b45309">
                            <i class="fa-solid fa-user-clock"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Never Logged In</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $vet_stats['never_logged_in'] ?? 0; ?></div>
                                <span class="badge bg-danger bg-opacity-10 text-danger">Requires follow-up</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Veterinarians Table -->
                <div class="col-lg-8">
                    <div class="card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0 fw-bold">All Veterinarians</h5>
                            <a href="add_veterinarian.php" class="btn btn-brand">
                                <i class="fas fa-plus me-2"></i>Add New Veterinarian
                            </a>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <form method="POST" id="bulkForm" class="bulk-actions">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <select id="bulkAction" class="form-select w-auto d-inline-block">
                                        <option value="">Bulk Actions</option>
                                        <option value="activate_selected">Activate Selected</option>
                                        <option value="deactivate_selected">Deactivate Selected</option>
                                        <option value="delete_selected">Delete Selected</option>
                                    </select>
                                    <button type="button" class="btn btn-brand ms-2" onclick="if(handleBulkAction()) document.getElementById('bulkForm').submit();">
                                        Apply
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        <span id="selectedCount">0</span> veterinarian(s) selected
                                    </small>
                                </div>
                            </div>
                            <input type="hidden" name="bulk_action" id="bulkActionInput">
                        </form>
                        
                        <!-- Search and Filter Section -->
                        <form method="GET" action="veterinarian.php" class="row g-3 mb-4 p-3 bg-light rounded-3">
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
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                        </th>
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
                                                    <input type="checkbox" name="selected_vets[]" value="<?php echo $vet['user_id']; ?>" class="vet-checkbox" onchange="updateSelectedCount()">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=3b82f6&color=fff'; ?>" 
                                                             alt="<?php echo htmlspecialchars($vet['name']); ?>" 
                                                             class="rounded-circle me-3" width="45" height="45">
                                                        <div>
                                                            <div class="fw-bold">Dr. <?php echo htmlspecialchars($vet['name']); ?></div>
                                                            <small class="text-muted">ID: VET<?php echo str_pad($vet['user_id'], 4, '0', STR_PAD_LEFT); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-primary"><?php echo htmlspecialchars($vet['email']); ?></div>
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
                                                        <!-- View Profile Button -->
                                                        <button class="btn btn-sm btn-outline-primary me-1" title="View Profile" 
                                                                onclick="viewVetProfile(<?php echo $vet['user_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <!-- Edit Profile Button -->
                                                        <button class="btn btn-sm btn-outline-secondary me-1" title="Edit Profile" 
                                                                onclick="editVetProfile(<?php echo $vet['user_id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <!-- Status Toggle Button -->
                                                        <?php if ($vet['status'] === 'active'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="Deactivate"
                                                                        onclick="return confirm('Deactivate this veterinarian? They will not be able to log in until activated again.')">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($vet['status'] === 'inactive'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Activate">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($vet['status'] === 'pending'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Approve">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Send Welcome Email -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                            <input type="hidden" name="action" value="send_welcome_email">
                                                            <button type="submit" class="btn btn-sm btn-outline-info me-1" title="Send Welcome Email"
                                                                    onclick="return confirm('Send welcome email to this veterinarian?')">
                                                                <i class="fas fa-envelope"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Reset Password -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="vet_id" value="<?php echo $vet['user_id']; ?>">
                                                            <input type="hidden" name="action" value="reset_password">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="Reset Password"
                                                                    onclick="return confirm('Reset password for this veterinarian? A temporary password will be generated.')">
                                                                <i class="fas fa-key"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Button -->
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this veterinarian? This action cannot be undone and will remove all their data.');">
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
                </div>

                <!-- Sidebar with Recent Appointments -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card-soft p-4 mb-4">
                        <h5 class="fw-bold mb-3">Quick Actions</h5>
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
                        <h5 class="fw-bold mb-3">Upcoming Appointments</h5>
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
        function viewVetProfile(vetId) {
            // In a real implementation, this would fetch and display vet details in a modal
            window.location.href = 'vet_profile.php?id=' + vetId;
        }

        function editVetProfile(vetId) {
            // In a real implementation, this would open an edit modal
            window.location.href = 'edit_veterinarian.php?id=' + vetId;
        }

        // Bulk actions handler
        function handleBulkAction() {
            const bulkAction = document.getElementById('bulkAction').value;
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_vets[]"]:checked');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one veterinarian.');
                return false;
            }
            
            if (bulkAction === 'delete_selected') {
                return confirm(`Are you sure you want to delete ${selectedCheckboxes.length} selected veterinarian(s)? This action cannot be undone.`);
            }
            
            return true;
        }

        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_vets[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_vets[]"]:checked');
            document.getElementById('selectedCount').textContent = selectedCheckboxes.length;
        }

        // Set bulk action value before form submission
        document.getElementById('bulkForm').onsubmit = function() {
            document.getElementById('bulkActionInput').value = document.getElementById('bulkAction').value;
        };

        // Initialize selected count
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html>
