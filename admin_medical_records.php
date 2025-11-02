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

// Handle Medical Record Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_record'])) {
    $pet_id = intval($_POST['pet_id']);
    $vet_id = intval($_POST['vet_id']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $medications = trim($_POST['medications']);
    $notes = trim($_POST['notes']);
    $visit_date = $_POST['visit_date'];
    $next_visit = $_POST['next_visit'] ?: null;
    
    $insert_query = "INSERT INTO medical_records (pet_id, vet_id, diagnosis, treatment, medications, notes, visit_date, next_visit_date, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iissssss", $pet_id, $vet_id, $diagnosis, $treatment, $medications, $notes, $visit_date, $next_visit);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Medical record created successfully!";
    } else {
        $_SESSION['error'] = "Error creating medical record: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Medical Record Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $record_id = intval($_POST['record_id']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $medications = trim($_POST['medications']);
    $notes = trim($_POST['notes']);
    $visit_date = $_POST['visit_date'];
    $next_visit = $_POST['next_visit'] ?: null;
    
    $update_query = "UPDATE medical_records SET diagnosis = ?, treatment = ?, medications = ?, notes = ?, visit_date = ?, next_visit_date = ?, updated_at = NOW() WHERE record_id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssssssi", $diagnosis, $treatment, $medications, $notes, $visit_date, $next_visit, $record_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Medical record updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating medical record: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Medical Record Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    $record_id = intval($_POST['record_id']);
    
    $delete_query = "DELETE FROM medical_records WHERE record_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $record_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Medical record deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting medical record: " . $stmt->error;
    }
    $stmt->close();
}

// Get all medical records with related information
$records_query = "
    SELECT 
        mr.*,
        p.name as pet_name,
        p.species as pet_species,
        p.breed as pet_breed,
        u_owner.name as owner_name,
        u_vet.name as vet_name,
        u_vet.specialization as vet_specialization
    FROM medical_records mr
    LEFT JOIN pets p ON mr.pet_id = p.pet_id
    LEFT JOIN users u_owner ON p.owner_id = u_owner.user_id
    LEFT JOIN users u_vet ON mr.vet_id = u_vet.user_id
    ORDER BY mr.visit_date DESC, mr.created_at DESC
";

$records_result = $conn->query($records_query);
$all_records = [];
if ($records_result) {
    while ($row = $records_result->fetch_assoc()) {
        $all_records[] = $row;
    }
}

// Get pets for dropdown
$pets_query = "SELECT pet_id, name, species, breed FROM pets ORDER BY name";
$pets_result = $conn->query($pets_query);
$pets = [];
if ($pets_result) {
    while ($row = $pets_result->fetch_assoc()) {
        $pets[] = $row;
    }
}

// Get veterinarians for dropdown
$vets_query = "SELECT user_id, name, specialization FROM users WHERE role = 'vet' ORDER BY name";
$vets_result = $conn->query($vets_query);
$vets = [];
if ($vets_result) {
    while ($row = $vets_result->fetch_assoc()) {
        $vets[] = $row;
    }
}

// Get medical records statistics
$records_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM medical_records) as total_records,
        (SELECT COUNT(*) FROM medical_records WHERE DATE(visit_date) = CURDATE()) as today_records,
        (SELECT COUNT(*) FROM medical_records WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_records,
        (SELECT COUNT(*) FROM medical_records WHERE next_visit_date IS NOT NULL AND next_visit_date >= CURDATE()) as upcoming_visits
";

$records_stats_result = $conn->query($records_stats_query);
$records_stats = $records_stats_result ? $records_stats_result->fetch_assoc() : [
    'total_records' => 0,
    'today_records' => 0,
    'weekly_records' => 0,
    'upcoming_visits' => 0
];

// Get recent medical records for quick view
$recent_records_query = "
    SELECT 
        mr.record_id,
        mr.diagnosis,
        mr.visit_date,
        p.name as pet_name,
        u_vet.name as vet_name
    FROM medical_records mr
    LEFT JOIN pets p ON mr.pet_id = p.pet_id
    LEFT JOIN users u_vet ON mr.vet_id = u_vet.user_id
    ORDER BY mr.created_at DESC 
    LIMIT 5
";

$recent_records_result = $conn->query($recent_records_query);
$recent_records = [];
if ($recent_records_result) {
    while ($row = $recent_records_result->fetch_assoc()) {
        $recent_records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - VetCareQR</title>
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

        /* Medical Records Specific Styles */
        .record-card {
            border-left: 4px solid var(--info);
        }

        .diagnosis-tag {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .medication-list {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .visit-date {
            font-weight: 600;
            color: var(--ink);
        }

        .next-visit {
            color: var(--warning);
            font-weight: 600;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: var(--radius);
            border: none;
            box-shadow: var(--shadow);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            color: white;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        /* Recent Records */
        .recent-record {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: var(--transition);
        }

        .recent-record:hover {
            background: #f8fafc;
        }

        .recent-record:last-child {
            border-bottom: none;
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
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
                    <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
                    <span>Veterinarians</span>
                </a>
                <a class="nav-link d-flex align-items-center" href="pet_owner.php">
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
                <a class="nav-link d-flex align-items-center active" href="medical_records.php">
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

            <!-- Medical Records Statistics in Sidebar -->
            <div class="sidebar-stats">
                <h6 class="text-white mb-3">Records Overview</h6>
                <div class="stat-item">
                    <span>Total Records</span>
                    <span class="stat-value"><?php echo $records_stats['total_records']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Today</span>
                    <span class="stat-value"><?php echo $records_stats['today_records']; ?></span>
                </div>
                <div class="stat-item">
                    <span>This Week</span>
                    <span class="stat-value"><?php echo $records_stats['weekly_records']; ?></span>
                </div>
                <div class="stat-item">
                    <span>Upcoming</span>
                    <span class="stat-value"><?php echo $records_stats['upcoming_visits']; ?></span>
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
                    <a href="logout_admin.php" class="text-white-50"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="d-flex flex-column gap-4">
            <!-- Topbar -->
            <div class="topbar">
                <div class="d-flex align-items-center">
                    <h1 class="h4 mb-0 fw-bold">Medical Records</h1>
                    <span class="badge bg-light text-dark ms-3">BrightView Veterinary Clinic</span>
                </div>

                <div class="search ms-auto">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input class="form-control search-input" placeholder="Search medical records..." />
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="card-soft fade-in" style="animation-delay: 0.1s">
                    <div class="kpi">
                        <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
                            <i class="fa-solid fa-file-medical"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Total Records</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $records_stats['total_records']; ?></div>
                                <span class="badge-dot" style="color:#10b981">Comprehensive</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.2s">
                    <div class="kpi">
                        <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Today's Records</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $records_stats['today_records']; ?></div>
                                <span class="badge-dot" style="color:#f59e0b">Active</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.3s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff0f5;color:#c2417a">
                            <i class="fa-solid fa-calendar-week"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Weekly Records</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $records_stats['weekly_records']; ?></div>
                                <span class="badge-dot" style="color:#8b5cf6">Recent</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-soft fade-in" style="animation-delay: 0.4s">
                    <div class="kpi">
                        <div class="bubble" style="background:#fff7e6;color:#b45309">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small>Upcoming Visits</small>
                            <div class="d-flex align-items-end gap-2">
                                <div class="stat-value"><?php echo $records_stats['upcoming_visits']; ?></div>
                                <span class="badge-dot" style="color:#ef4444">Scheduled</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Records -->
                <div class="col-lg-4">
                    <div class="card-soft p-4 fade-in">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="section-title">Recent Records</div>
                            <span class="badge bg-light text-dark">Latest</span>
                        </div>
                        
                        <div class="recent-records">
                            <?php if (!empty($recent_records)): ?>
                                <?php foreach ($recent_records as $record): ?>
                                    <div class="recent-record">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="user-name"><?php echo htmlspecialchars($record['pet_name']); ?></div>
                                            <small class="text-muted"><?php echo date('M j', strtotime($record['visit_date'])); ?></small>
                                        </div>
                                        <div class="diagnosis-tag d-inline-block mb-2">
                                            <?php echo htmlspecialchars(substr($record['diagnosis'], 0, 30)) . (strlen($record['diagnosis']) > 30 ? '...' : ''); ?>
                                        </div>
                                        <div class="user-email">By Dr. <?php echo htmlspecialchars($record['vet_name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-file-medical fa-2x mb-3 d-block"></i>
                                    No recent medical records found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Medical Records Management -->
                <div class="col-lg-8">
                    <div class="card-soft p-4 fade-in">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="section-title">Medical Records Management</div>
                            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#createRecordModal">
                                <i class="fa-solid fa-plus me-2"></i>Add New Record
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Pet & Owner</th>
                                        <th>Diagnosis</th>
                                        <th>Veterinarian</th>
                                        <th>Visit Date</th>
                                        <th>Next Visit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($all_records)): ?>
                                        <?php foreach ($all_records as $record): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info">
                                                        <div class="pet-avatar bg-light d-flex align-items-center justify-content-center">
                                                            <i class="fa-solid fa-paw text-muted"></i>
                                                        </div>
                                                        <div>
                                                            <div class="user-name"><?php echo htmlspecialchars($record['pet_name']); ?></div>
                                                            <div class="user-email">Owner: <?php echo htmlspecialchars($record['owner_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($record['pet_species']); ?> • <?php echo htmlspecialchars($record['pet_breed']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="diagnosis-tag"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 50)) . (strlen($record['diagnosis']) > 50 ? '...' : ''); ?></div>
                                                    <?php if ($record['treatment']): ?>
                                                        <small class="text-muted">Treatment: <?php echo htmlspecialchars(substr($record['treatment'], 0, 30)) . (strlen($record['treatment']) > 30 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="user-name">Dr. <?php echo htmlspecialchars($record['vet_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['vet_specialization']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="visit-date"><?php echo date('M j, Y', strtotime($record['visit_date'])); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($record['next_visit_date']): ?>
                                                        <div class="next-visit"><?php echo date('M j, Y', strtotime($record['next_visit_date'])); ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not scheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-action btn-view me-1" title="View Record" onclick="viewRecord(<?php echo $record['record_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-action btn-edit me-1" title="Edit Record" onclick="editRecord(<?php echo $record['record_id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this medical record? This action cannot be undone.');">
                                                            <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                                            <input type="hidden" name="delete_record" value="1">
                                                            <button type="submit" class="btn btn-action btn-delete" title="Delete Record">
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
                                                <i class="fas fa-file-medical fa-2x mb-3 d-block"></i>
                                                No medical records found in the system.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Medical Record Modal -->
    <div class="modal fade" id="createRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-plus me-2"></i>Add New Medical Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pet *</label>
                                <select class="form-select" name="pet_id" required>
                                    <option value="">Select Pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?php echo $pet['pet_id']; ?>">
                                            <?php echo htmlspecialchars($pet['name']); ?> (<?php echo htmlspecialchars($pet['species']); ?> - <?php echo htmlspecialchars($pet['breed']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Veterinarian *</label>
                                <select class="form-select" name="vet_id" required>
                                    <option value="">Select Veterinarian</option>
                                    <?php foreach ($vets as $vet): ?>
                                        <option value="<?php echo $vet['user_id']; ?>">
                                            Dr. <?php echo htmlspecialchars($vet['name']); ?> (<?php echo htmlspecialchars($vet['specialization']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis *</label>
                                <textarea class="form-control" name="diagnosis" rows="3" placeholder="Enter diagnosis details..." required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Treatment</label>
                                <textarea class="form-control" name="treatment" rows="3" placeholder="Enter treatment details..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Medications</label>
                                <textarea class="form-control" name="medications" rows="2" placeholder="List prescribed medications..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes or observations..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Visit Date *</label>
                                <input type="date" class="form-control" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Next Visit Date</label>
                                <input type="date" class="form-control" name="next_visit">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_record" class="btn btn-brand">Create Record</button>
                    </div>
                </form>
            </div>
        </div>
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

        // View Record Function
        function viewRecord(recordId) {
            alert('Viewing record ID: ' + recordId + '\nThis would open a detailed view modal in a real implementation.');
            // In a real implementation, this would fetch record details via AJAX
            // and display them in a modal
        }

        // Edit Record Function
        function editRecord(recordId) {
            alert('Editing record ID: ' + recordId + '\nThis would open an edit form in a real implementation.');
            // In a real implementation, this would fetch record details via AJAX
            // and populate an edit modal form
        }

        // Search functionality
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tableRows = document.querySelectorAll('tbody tr');
                
                tableRows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>