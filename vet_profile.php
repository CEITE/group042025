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

// Get veterinarian ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: veterinarian.php");
    exit();
}

$vet_id = intval($_GET['id']);

// Get veterinarian details
$vet_query = "SELECT * FROM users WHERE user_id = ? AND role = 'vet'";
$vet_stmt = $conn->prepare($vet_query);
$vet_stmt->bind_param("i", $vet_id);
$vet_stmt->execute();
$vet_result = $vet_stmt->get_result();

if ($vet_result->num_rows === 0) {
    $_SESSION['error'] = "Veterinarian not found!";
    header("Location: veterinarian.php");
    exit();
}

$vet = $vet_result->fetch_assoc();
$vet_stmt->close();

// Get veterinarian statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'completed') as completed_appointments,
        (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'pending') as pending_appointments,
        (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'confirmed') as confirmed_appointments,
        (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND appointment_date >= CURDATE()) as upcoming_appointments,
        (SELECT AVG(rating) FROM appointments WHERE user_id = ? AND rating IS NOT NULL) as avg_rating,
        (SELECT COUNT(DISTINCT pet_id) FROM appointments WHERE user_id = ?) as unique_patients
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("iiiiii", $vet_id, $vet_id, $vet_id, $vet_id, $vet_id, $vet_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$vet_stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Get recent appointments
$recent_appointments_query = "
    SELECT a.*, p.name as pet_name, p.breed, p.species, o.name as owner_name
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users o ON p.owner_id = o.user_id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
";

$recent_stmt = $conn->prepare($recent_appointments_query);
$recent_stmt->bind_param("i", $vet_id);
$recent_stmt->execute();
$recent_appointments = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_stmt->close();

// Get appointment history by month for chart
$appointment_history_query = "
    SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        COUNT(*) as appointment_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM appointments 
    WHERE user_id = ? AND appointment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month ASC
";

$history_stmt = $conn->prepare($appointment_history_query);
$history_stmt->bind_param("i", $vet_id);
$history_stmt->execute();
$appointment_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. <?php echo htmlspecialchars($vet['name']); ?> - Profile - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .profile-header {
            background: linear-gradient(135deg, var(--brand), var(--lav));
            color: white;
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--ink), var(--lav));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .specialization-tag {
            background: rgba(59, 130, 246, 0.1);
            color: var(--brand);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
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
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: var(--transition);
        }

        .appointment-item:hover {
            transform: translateX(5px);
            background: #f1f5f9;
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
                <a class="nav-link d-flex align-items-center" href="veterinarian.php">
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
            <!-- Back Button -->
            <div class="d-flex align-items-center mb-3">
                <a href="veterinarian.php" class="btn btn-outline-primary btn-sm me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Veterinarians
                </a>
                <h1 class="h4 mb-0 fw-bold">Veterinarian Profile</h1>
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <img src="<?php echo $vet['profile_picture'] ? htmlspecialchars($vet['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($vet['name']) . '&background=ffffff&color=3b82f6&size=200'; ?>" 
                                 class="rounded-circle me-4" width="120" height="120" alt="Dr. <?php echo htmlspecialchars($vet['name']); ?>">
                            <div>
                                <h1 class="h2 mb-2 fw-bold">Dr. <?php echo htmlspecialchars($vet['name']); ?></h1>
                                <p class="mb-2 opacity-90">
                                    <i class="fas fa-stethoscope me-2"></i>
                                    <?php echo $vet['specialization'] ? htmlspecialchars($vet['specialization']) : 'General Practice'; ?>
                                </p>
                                <p class="mb-3 opacity-90">
                                    <i class="fas fa-id-card me-2"></i>
                                    ID: VET<?php echo str_pad($vet['user_id'], 4, '0', STR_PAD_LEFT); ?>
                                </p>
                                <div class="d-flex gap-2">
                                    <span class="status-badge <?php echo $vet['status'] === 'active' ? 'status-active' : ($vet['status'] === 'inactive' ? 'status-inactive' : 'status-pending'); ?>">
                                        <?php echo ucfirst($vet['status']); ?>
                                    </span>
                                    <?php if ($vet_stats['avg_rating']): ?>
                                        <span class="specialization-tag">
                                            <i class="fas fa-star me-1 text-warning"></i>
                                            <?php echo number_format($vet_stats['avg_rating'], 1); ?> Rating
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <a href="edit_veterinarian.php?id=<?php echo $vet_id; ?>" class="btn btn-light">
                                <i class="fas fa-edit me-2"></i>Edit Profile
                            </a>
                            <a href="veterinarian.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column - Profile Info & Stats -->
                <div class="col-lg-4">
                    <!-- Contact Information -->
                    <div class="card-soft p-4 mb-4">
                        <h5 class="fw-bold mb-3">Contact Information</h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Email Address</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($vet['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($vet['email']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Phone Number</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-phone text-primary me-2"></i>
                                    <span><?php echo $vet['phone_number'] ? htmlspecialchars($vet['phone_number']) : 'Not provided'; ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Registration Date</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-plus text-primary me-2"></i>
                                    <span><?php echo date('F j, Y', strtotime($vet['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Last Login</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                    <span><?php echo $vet['last_login'] ? date('F j, Y g:i A', strtotime($vet['last_login'])) : 'Never logged in'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Statistics -->
                    <div class="card-soft p-4">
                        <h5 class="fw-bold mb-3">Performance Statistics</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $vet_stats['completed_appointments'] ?? 0; ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $vet_stats['upcoming_appointments'] ?? 0; ?></div>
                                    <div class="stat-label">Upcoming</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $vet_stats['unique_patients'] ?? 0; ?></div>
                                    <div class="stat-label">Patients</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number">
                                        <?php echo $vet_stats['avg_rating'] ? number_format($vet_stats['avg_rating'], 1) : 'N/A'; ?>
                                    </div>
                                    <div class="stat-label">Rating</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Appointments & Activity -->
                <div class="col-lg-8">
                    <!-- Appointment Statistics Chart -->
                    <div class="card-soft p-4 mb-4">
                        <h5 class="fw-bold mb-3">Appointment Trends</h5>
                        <div style="height: 300px;">
                            <canvas id="appointmentChart"></canvas>
                        </div>
                    </div>

                    <!-- Recent Appointments -->
                    <div class="card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Recent Appointments</h5>
                            <a href="appointments.php?vet_id=<?php echo $vet_id; ?>" class="btn btn-sm btn-outline-primary">
                                View All Appointments
                            </a>
                        </div>
                        
                        <?php if (!empty($recent_appointments)): ?>
                            <div class="appointment-list">
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <strong><?php echo htmlspecialchars($appointment['pet_name']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($appointment['species']); ?> • <?php echo htmlspecialchars($appointment['breed']); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-muted small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <span class="badge bg-<?php 
                                                    echo $appointment['status'] === 'completed' ? 'success' : 
                                                         ($appointment['status'] === 'confirmed' ? 'primary' : 
                                                         ($appointment['status'] === 'pending' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>No appointments found for this veterinarian.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Appointment Chart
        const appointmentData = {
            labels: <?php echo json_encode(array_column($appointment_history, 'month')); ?>,
            datasets: [{
                label: 'Total Appointments',
                data: <?php echo json_encode(array_column($appointment_history, 'appointment_count')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Completed Appointments',
                data: <?php echo json_encode(array_column($appointment_history, 'completed_count')); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };

        const ctx = document.getElementById('appointmentChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: appointmentData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>