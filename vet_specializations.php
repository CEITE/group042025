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

// Handle add specialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_specialization'])) {
    $new_spec = trim($_POST['new_specialization']);
    
    if (!empty($new_spec)) {
        // Check if specialization already exists
        $check_query = "SELECT COUNT(*) as count FROM specializations WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $new_spec);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = "Specialization '$new_spec' already exists!";
        } else {
            $insert_query = "INSERT INTO specializations (name, created_at) VALUES (?, NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("s", $new_spec);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success'] = "Specialization '$new_spec' added successfully!";
            } else {
                $_SESSION['error'] = "Error adding specialization: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
    } else {
        $_SESSION['error'] = "Please enter a specialization name!";
    }
}

// Handle delete specialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_specialization'])) {
    $spec_id = intval($_POST['spec_id']);
    
    // Check if any veterinarians are using this specialization
    $check_usage = "SELECT COUNT(*) as count FROM users WHERE specialization = (SELECT name FROM specializations WHERE id = ?)";
    $check_stmt = $conn->prepare($check_usage);
    $check_stmt->bind_param("i", $spec_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = "Cannot delete specialization. It is currently being used by " . $result['count'] . " veterinarian(s).";
    } else {
        $delete_query = "DELETE FROM specializations WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $spec_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success'] = "Specialization deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting specialization: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    }
}

// Get all specializations with veterinarian counts
$specializations_query = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM users WHERE specialization = s.name AND role = 'vet') as vet_count
    FROM specializations s 
    ORDER BY s.name
";
$specializations_result = $conn->query($specializations_query);
$specializations = [];
while ($row = $specializations_result->fetch_assoc()) {
    $specializations[] = $row;
}

// Get veterinarian count by specialization for chart
$chart_query = "
    SELECT specialization, COUNT(*) as vet_count 
    FROM users 
    WHERE role = 'vet' AND specialization IS NOT NULL AND specialization != '' 
    GROUP BY specialization 
    ORDER BY vet_count DESC 
    LIMIT 10
";
$chart_result = $conn->query($chart_query);
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Specializations - VetCareQR</title>
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

        .specialization-tag {
            background: rgba(59, 130, 246, 0.1);
            color: var(--brand);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .vet-count-badge {
            background: var(--success);
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
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
                <a class="nav-link d-flex align-items-center active" href="vet_specializations.php">
                    <i class="fa-solid fa-tags me-3"></i>
                    <span>Specializations</span>
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
                <h1 class="h4 mb-0 fw-bold">Manage Specializations</h1>
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

            <div class="row g-4">
                <!-- Add Specialization Form -->
                <div class="col-lg-4">
                    <div class="card-soft p-4">
                        <h5 class="fw-bold mb-3">Add New Specialization</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Specialization Name</label>
                                <input type="text" class="form-control" name="new_specialization" 
                                       placeholder="Enter specialization name" required>
                            </div>
                            <button type="submit" name="add_specialization" class="btn btn-brand w-100">
                                <i class="fas fa-plus me-2"></i>Add Specialization
                            </button>
                        </form>
                    </div>

                    <!-- Specializations Chart -->
                    <div class="card-soft p-4 mt-4">
                        <h5 class="fw-bold mb-3">Veterinarians by Specialization</h5>
                        <div style="height: 300px;">
                            <canvas id="specializationsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Specializations List -->
                <div class="col-lg-8">
                    <div class="card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">All Specializations</h5>
                            <span class="text-muted">
                                <?php echo count($specializations); ?> specializations found
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Specialization</th>
                                        <th>Veterinarians</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($specializations)): ?>
                                        <?php foreach ($specializations as $spec): ?>
                                            <tr>
                                                <td>
                                                    <span class="specialization-tag">
                                                        <?php echo htmlspecialchars($spec['name']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="vet-count-badge">
                                                        <?php echo $spec['vet_count']; ?> vet(s)
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($spec['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this specialization? This action cannot be undone.');">
                                                        <input type="hidden" name="spec_id" value="<?php echo $spec['id']; ?>">
                                                        <button type="submit" name="delete_specialization" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                <?php echo $spec['vet_count'] > 0 ? 'disabled title="Cannot delete - veterinarians are using this specialization"' : ''; ?>>
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-tags fa-2x mb-3 d-block"></i>
                                                No specializations found. Add your first specialization!
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Specializations Chart
        const chartData = {
            labels: <?php echo json_encode(array_column($chart_data, 'specialization')); ?>,
            datasets: [{
                label: 'Veterinarians',
                data: <?php echo json_encode(array_column($chart_data, 'vet_count')); ?>,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#64748b'
                ],
                borderWidth: 0
            }]
        };

        const ctx = document.getElementById('specializationsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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