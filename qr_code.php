<?php
session_start();
include("conn.php");

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch logged-in user info
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ✅ Fetch user's pets with QR codes
$query = "
SELECT 
    p.pet_id,
    p.name AS pet_name,
    p.species,
    p.breed,
    p.age,
    p.color,
    p.weight,
    p.birth_date,
    p.gender,
    p.medical_notes,
    p.vet_contact,
    p.date_registered,
    p.qr_code,
    p.qr_code_data,
    COUNT(m.record_id) as total_records
FROM pets p
LEFT JOIN pet_medical_records m ON p.pet_id = m.pet_id
WHERE p.user_id = ?
GROUP BY p.pet_id
ORDER BY p.name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ✅ Get your domain for QR code URLs
$base_url = 'https://group042025.ceitesystems.com';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetMedQR - QR Codes</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #ffd6e7;
            --pink-2: #f7c5e0;
            --pink-dark: #ec4899;
            --pink-darker: #db2777;
            --pink-light: #fff4f8;
            --pink-gradient: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            --pink-gradient-light: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
            --blue: #4a6cf7;
            --green: #2ecc71;
            --orange: #f39c12;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--pink-2) 0%, var(--pink-dark) 100%);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.4rem;
            text-align: center;
            margin-bottom: 2.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sidebar .profile {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem 1rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: var(--shadow);
        }
        
        .sidebar .profile h6 {
            color: white;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .sidebar .profile small {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.8rem;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            border-radius: var(--radius);
            margin: 0.5rem 0;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .sidebar a .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.15);
            margin-right: 12px;
            transition: all 0.3s ease;
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }
        
        .sidebar a.active .icon, .sidebar a:hover .icon {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: white;
            background: rgba(219, 39, 119, 0.9);
            text-align: center;
            padding: 12px;
            border-radius: var(--radius);
            transition: all 0.3s ease;
            border: 1px solid rgba(219, 39, 119, 0.3);
        }
        
        .sidebar .logout:hover {
            background: var(--pink-darker);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 280px;
            min-height: 100vh;
        }
        
        /* Topbar */
        .topbar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background: linear-gradient(135deg, white 0%, var(--pink-light) 100%);
        }
        
        .topbar h5 {
            font-weight: 700;
            color: var(--pink-darker);
            margin-bottom: 0.25rem;
        }
        
        .topbar .text-muted {
            color: var(--gray) !important;
            font-size: 0.9rem;
        }
        
        /* Cards */
        .card-custom {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: none;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background: linear-gradient(135deg, white 0%, var(--pink-light) 100%);
        }
        
        /* QR Cards */
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .qr-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            box-shadow: var(--shadow);
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background: linear-gradient(135deg, white 0%, var(--pink-light) 100%);
        }
        
        .qr-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        
        .qr-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--pink-gradient);
        }
        
        .qr-card-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--pink-gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .qr-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: rotate(45deg);
        }
        
        .pet-species-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow);
        }
        
        .qr-card-body {
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .qr-container {
            display: inline-block;
            margin-bottom: 1.5rem;
            border: 2px solid var(--pink);
            border-radius: var(--radius);
            padding: 20px;
            background: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .qr-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 40%, rgba(236, 72, 153, 0.1) 50%, transparent 60%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .pet-info-badges {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .pet-info-badge {
            background: var(--pink-light);
            border: 1px solid var(--pink);
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--pink-darker);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .pet-info-badge:hover {
            background: var(--pink-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 2px solid transparent;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, white 0%, var(--pink-light) 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--pink-gradient);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--pink-darker);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: var(--pink-gradient);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .bulk-actions::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: rotate(45deg);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
            color: var(--pink-darker);
        }
        
        .empty-state h5 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--pink-darker);
        }
        
        /* Buttons */
        .btn {
            border-radius: 25px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-primary {
            background: var(--pink-gradient);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%);
            border: none;
        }
        
        .btn-outline-primary {
            border-color: var(--pink-dark);
            color: var(--pink-dark);
        }
        
        .btn-outline-primary:hover {
            background: var(--pink-dark);
            color: white;
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: white;
        }
        
        /* Badges */
        .badge {
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 600;
        }
        
        .bg-primary {
            background: var(--pink-gradient) !important;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 250px;
            }
            .main-content {
                margin-left: 250px;
            }
        }
        
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .qr-grid {
                grid-template-columns: 1fr;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Print Styles */
        .print-only {
            display: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .qr-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
            body {
                background: white !important;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--pink-dark);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--pink-darker);
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Floating Elements */
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Alert Styles */
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: var(--radius);
        }

        .qr-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--pink-darker);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-paw"></i> 
            <span>PetMedQR</span>
        </div>
        <div class="profile">
            <img src="https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>" alt="User">
            <h6><?php echo htmlspecialchars($user['name']); ?></h6>
            <small><?php echo htmlspecialchars($user['role']); ?></small>
        </div>
        <a href="user_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> 
            <span>Dashboard</span>
        </a>
        <a href="pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> 
            <span>My Pets</span>
        </a>
        <a href="qr_codes.php" class="active">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> 
            <span>QR Codes</span>
        </a>
        <a href="#" data-bs-toggle="modal" data-bs-target="#addPetModal">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> 
            <span>Register Pet</span>
        </a>
        <a href="#">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> 
            <span>Settings</span>
        </a>
        <a href="logout.php" class="logout">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> 
            <span>Logout</span>
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-1">
                    <i class="fas fa-qrcode me-2"></i>
                    QR Codes 
                    <span class="badge bg-primary ms-2"><?php echo count($pets); ?></span>
                </h5>
                <small class="text-muted">Professional medical QR codes that direct to our landing page</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-success no-print" onclick="printAllQRCodes()">
                    <i class="fas fa-print me-1"></i> Print All
                </button>
                <button class="btn btn-primary no-print" onclick="downloadAllQRCodes()">
                    <i class="fas fa-download me-1"></i> Download All
                </button>
                <div class="text-end no-print">
                    <strong id="currentDate" class="d-block"></strong>
                    <small id="currentTime" class="text-muted"></small>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-2 fs-5"></i>
                    <div class="flex-grow-1"><?php echo $_SESSION['success']; ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- QR Codes Statistics -->
        <div class="stats-grid no-print">
            <div class="stat-card floating">
                <div class="stat-number"><?php echo count($pets); ?></div>
                <div class="stat-label">Total QR Codes</div>
            </div>
            <?php
            $dogs = array_filter($pets, function($pet) { return strtolower($pet['species']) === 'dog'; });
            $cats = array_filter($pets, function($pet) { return strtolower($pet['species']) === 'cat'; });
            $others = count($pets) - count($dogs) - count($cats);
            ?>
            <div class="stat-card floating" style="animation-delay: 0.2s;">
                <div class="stat-number"><?php echo count($dogs); ?></div>
                <div class="stat-label">Dog QR Codes</div>
            </div>
            <div class="stat-card floating" style="animation-delay: 0.4s;">
                <div class="stat-number"><?php echo count($cats); ?></div>
                <div class="stat-label">Cat QR Codes</div>
            </div>
            <div class="stat-card floating" style="animation-delay: 0.6s;">
                <div class="stat-number"><?php echo $others; ?></div>
                <div class="stat-label">Other Pets</div>
            </div>
        </div>

        <!-- QR Type Info -->
        <div class="card-custom no-print">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6><i class="fas fa-info-circle me-2 text-primary"></i>About Our QR Codes</h6>
                    <p class="mb-0 text-muted">These QR codes direct to our professional medical landing page where clinics can view pet information and access our full system.</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary me-2">Professional</span>
                    <span class="badge bg-success">Secure</span>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions no-print">
            <div class="position-relative">
                <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                <div class="action-buttons">
                    <button class="btn btn-outline-light" onclick="downloadAllQRCodes()">
                        <i class="fas fa-download me-1"></i> Download All as ZIP
                    </button>
                    <button class="btn btn-outline-light" onclick="printAllQRCodes()">
                        <i class="fas fa-print me-1"></i> Print All QR Codes
                    </button>
                    <button class="btn btn-outline-light" onclick="showQRDataModal()">
                        <i class="fas fa-eye me-1"></i> View All QR Data
                    </button>
                    <button class="btn btn-outline-light" onclick="testLandingPage()">
                        <i class="fas fa-external-link me-1"></i> Test Landing Page
                    </button>
                </div>
            </div>
        </div>

        <?php if (empty($pets)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-qrcode floating"></i>
                <h5>No QR Codes Available</h5>
                <p class="text-muted mb-4">You haven't registered any pets yet. Register a pet to generate QR codes!</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addPetModal">
                    <i class="fa-solid fa-plus me-2"></i> Register Your First Pet
                </button>
            </div>
        <?php else: ?>
            <!-- Print Header -->
            <div class="print-only text-center mb-4">
                <h3>Pet Medical QR Codes</h3>
                <p>Generated for: <?php echo htmlspecialchars($user['name']); ?></p>
                <p>Date: <?php echo date('F j, Y'); ?></p>
                <hr>
            </div>

            <!-- QR Codes Grid -->
            <div class="qr-grid">
                <?php foreach ($pets as $index => $pet): ?>
                    <div class="qr-card floating" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                        <div class="qr-card-header">
                            <div>
                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($pet['pet_name']); ?></h6>
                                <small class="opacity-90"><?php echo htmlspecialchars($pet['species']); ?> • <?php echo htmlspecialchars($pet['breed']) ?: 'Mixed'; ?></small>
                            </div>
                            <div class="pet-species-icon">
                                <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                            </div>
                        </div>
                        <div class="qr-card-body">
                            <div class="qr-type-badge">
                                <i class="fas fa-external-link me-1"></i> Landing Page
                            </div>
                            <div class="qr-container">
                                <div id="qrcode-<?php echo $pet['pet_id']; ?>" class="qr-preview-large"></div>
                            </div>
                            
                            <div class="pet-info-badges">
                                <span class="pet-info-badge">
                                    <i class="fas fa-birthday-cake"></i>
                                    <?php echo htmlspecialchars($pet['age']); ?> yrs
                                </span>
                                <span class="pet-info-badge">
                                    <i class="fas fa-venus-mars"></i>
                                    <?php echo htmlspecialchars($pet['gender']) ?: 'Unknown'; ?>
                                </span>
                                <span class="pet-info-badge">
                                    <i class="fas fa-weight"></i>
                                    <?php echo htmlspecialchars($pet['weight']) ? $pet['weight'] . ' kg' : 'N/A'; ?>
                                </span>
                            </div>
                            
                            <div class="action-buttons no-print">
                                <button class="btn btn-primary btn-action" onclick="downloadQRCode(<?php echo $pet['pet_id']; ?>)">
                                    <i class="fas fa-download"></i> Download
                                </button>
                                <button class="btn btn-info btn-action" onclick="showQRModal(<?php echo $pet['pet_id']; ?>)">
                                    <i class="fas fa-expand"></i> Enlarge
                                </button>
                                <button class="btn btn-success btn-action" onclick="printQRCode(<?php echo $pet['pet_id']; ?>)">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="btn btn-outline-primary btn-action" onclick="testPetLandingPage(<?php echo $pet['pet_id']; ?>)">
                                    <i class="fas fa-external-link"></i> Test
                                </button>
                            </div>
                            
                            <!-- Print instructions -->
                            <div class="print-only mt-3">
                                <small class="text-muted">
                                    <strong>Scan this QR code to access medical records for <?php echo htmlspecialchars($pet['pet_name']); ?></strong><br>
                                    Pet ID: <?php echo htmlspecialchars($pet['pet_id']); ?> | 
                                    Registered: <?php echo date('M j, Y', strtotime($pet['date_registered'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
            <div class="modal-header" style="background: var(--pink-gradient); color: white; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <h5 class="modal-title fw-bold" id="qrModalTitle">
                    <i class="fas fa-qrcode me-2"></i>QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="modalQrContainer" class="mb-4"></div>
                <div id="modalQrData" class="qr-data-preview mb-3" style="display: none; background: var(--pink-light); border-radius: var(--radius); padding: 1rem; border: 1px solid var(--pink);"></div>
                <p class="text-muted mb-0">Scan this QR code to view our professional medical landing page</p>
                <div class="mt-3">
                    <small class="text-muted" id="modalQrUrl"></small>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--gray-light);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="downloadModalQr">
                    <i class="fas fa-download me-1"></i> Download
                </button>
                <button type="button" class="btn btn-info" onclick="toggleQrData()">
                    <i class="fas fa-eye me-1"></i> View URL
                </button>
                <button type="button" class="btn btn-success" id="testModalQr">
                    <i class="fas fa-external-link me-1"></i> Test
                </button>
            </div>
        </div>
    </div>
</div>

<!-- All QR Data Modal -->
<div class="modal fade" id="allQrDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
            <div class="modal-header" style="background: var(--pink-gradient); color: white; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-database me-2"></i>All QR Code URLs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="allQrDataContent" class="qr-data-preview" style="max-height: 60vh; overflow-y: auto; margin: 0; border-radius: 0; background: var(--pink-light); padding: 1rem;"></div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--gray-light);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadAllQRData()">
                    <i class="fas fa-download me-1"></i> Download URLs
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date and time
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Generate QR codes for pets
        <?php foreach ($pets as $pet): ?>
            generateQRCode('qrcode-<?php echo $pet['pet_id']; ?>', {
                petId: <?php echo $pet['pet_id']; ?>,
                petName: '<?php echo addslashes($pet['pet_name']); ?>',
                species: '<?php echo addslashes($pet['species']); ?>',
                breed: '<?php echo addslashes($pet['breed']); ?>',
                age: '<?php echo $pet['age']; ?>',
                color: '<?php echo addslashes($pet['color']); ?>',
                weight: '<?php echo $pet['weight']; ?>',
                birthDate: '<?php echo $pet['birth_date']; ?>',
                gender: '<?php echo addslashes($pet['gender']); ?>',
                medicalNotes: '<?php echo addslashes($pet['medical_notes']); ?>',
                vetContact: '<?php echo addslashes($pet['vet_contact']); ?>',
                registered: '<?php echo $pet['date_registered']; ?>'
            });
        <?php endforeach; ?>
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

    // Update date and time display
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    // Function to generate QR code with landing page URL
    function generateQRCode(containerId, petData) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // ✅ Create landing page URL instead of raw text
        const landingPageUrl = `<?php echo $base_url; ?>/pet-medical-access.php?pet_id=${petData.petId}&pet_name=${encodeURIComponent(petData.petName)}`;
        
        // Use the URL as QR code content
        const qrData = landingPageUrl;
        
        // Generate QR code
        const qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        container.innerHTML = qr.createSvgTag({
            scalable: true,
            margin: 2,
            color: '#000',
            background: '#fff'
        });
        
        // Store both URL and original data
        container.setAttribute('data-qr-content', qrData);
        container.setAttribute('data-pet-name', petData.petName);
        container.setAttribute('data-pet-id', petData.petId);
        container.setAttribute('data-landing-url', landingPageUrl);
        container.setAttribute('data-pet-full-data', JSON.stringify(petData));
        
        // Style the SVG
        const svg = container.querySelector('svg');
        if (svg) {
            svg.style.width = '100%';
            svg.style.height = 'auto';
            svg.style.maxWidth = '200px';
            svg.style.borderRadius = '8px';
        }
    }
    
    // Function to show QR code in modal
    function showQRModal(petId) {
        const container = document.getElementById(`qrcode-${petId}`);
        if (!container) return;
        
        const modalTitle = document.getElementById('qrModalTitle');
        const modalContainer = document.getElementById('modalQrContainer');
        const modalDataContainer = document.getElementById('modalQrData');
        const modalQrUrl = document.getElementById('modalQrUrl');
        const petName = container.getAttribute('data-pet-name');
        const landingUrl = container.getAttribute('data-landing-url');
        
        modalTitle.textContent = `${petName} - Medical QR Code`;
        modalDataContainer.style.display = 'none';
        modalQrUrl.textContent = landingUrl;
        
        // Create larger QR code for modal
        const qrData = container.getAttribute('data-qr-content');
        const qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        modalContainer.innerHTML = qr.createSvgTag({
            scalable: true,
            margin: 4,
            color: '#000',
            background: '#fff'
        });
        
        // Set the QR data for the modal
        modalDataContainer.textContent = qrData;
        
        // Style the SVG
        const svg = modalContainer.querySelector('svg');
        if (svg) {
            svg.style.width = '300px';
            svg.style.height = '300px';
        }
        
        // Set download functionality
        document.getElementById('downloadModalQr').onclick = function() {
            downloadQRCode(petId);
        };
        
        // Set test functionality
        document.getElementById('testModalQr').onclick = function() {
            testPetLandingPage(petId);
        };
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('qrModal'));
        modal.show();
    }
    
    // Toggle QR data visibility in modal
    function toggleQrData() {
        const modalDataContainer = document.getElementById('modalQrData');
        modalDataContainer.style.display = modalDataContainer.style.display === 'none' ? 'block' : 'none';
    }
    
    // Function to download QR code
    function downloadQRCode(petId) {
        const container = document.getElementById(`qrcode-${petId}`);
        if (!container) return;
        
        const svg = container.querySelector('svg');
        if (!svg) return;
        
        const petName = container.getAttribute('data-pet-name');
        
        // Convert SVG to data URL
        const svgData = new XMLSerializer().serializeToString(svg);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const svgUrl = URL.createObjectURL(svgBlob);
        
        // Create image to convert to canvas
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.width;
            canvas.height = img.height;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Create download link
            const a = document.createElement('a');
            a.download = `${petName}_medical_qr.png`;
            a.href = canvas.toDataURL('image/png');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            URL.revokeObjectURL(svgUrl);
        };
        
        img.src = svgUrl;
    }
    
    // Function to print individual QR code
    function printQRCode(petId) {
        const container = document.getElementById(`qrcode-${petId}`);
        if (!container) return;
        
        const petName = container.getAttribute('data-pet-name');
        const landingUrl = container.getAttribute('data-landing-url');
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>QR Code - ${petName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                        .qr-container { margin: 20px auto; }
                        .pet-info { margin: 20px 0; }
                        .url-info { background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <h2>${petName} - Medical QR Code</h2>
                    <div class="qr-container">${container.innerHTML}</div>
                    <div class="pet-info">
                        <p><strong>Scan this QR code to access our medical records landing page</strong></p>
                        <div class="url-info">URL: ${landingUrl}</div>
                        <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
    
    // Function to print all QR codes
    function printAllQRCodes() {
        window.print();
    }
    
    // Function to test landing page for specific pet
    function testPetLandingPage(petId) {
        const container = document.getElementById(`qrcode-${petId}`);
        if (!container) return;
        
        const landingUrl = container.getAttribute('data-landing-url');
        window.open(landingUrl, '_blank');
    }
    
    // Function to test main landing page
    function testLandingPage() {
        window.open('<?php echo $base_url; ?>/pet-medical-access.php', '_blank');
    }
    
    // Function to download all QR codes as ZIP (placeholder)
    function downloadAllQRCodes() {
        alert('Bulk QR code download feature will be implemented soon! This would typically generate a ZIP file containing all QR codes.');
    }
    
    // Function to show all QR data in modal
    function showQRDataModal() {
        const allData = [];
        <?php foreach ($pets as $pet): ?>
            const container<?php echo $pet['pet_id']; ?> = document.getElementById('qrcode-<?php echo $pet['pet_id']; ?>');
            if (container<?php echo $pet['pet_id']; ?>) {
                const landingUrl = container<?php echo $pet['pet_id']; ?>.getAttribute('data-landing-url');
                const petName = container<?php echo $pet['pet_id']; ?>.getAttribute('data-pet-name');
                allData.push(`Pet: ${petName}\nURL: ${landingUrl}\n\n`);
            }
        <?php endforeach; ?>
        
        document.getElementById('allQrDataContent').textContent = allData.join('');
        const modal = new bootstrap.Modal(document.getElementById('allQrDataModal'));
        modal.show();
    }
    
    // Function to download all QR data
    function downloadAllQRData() {
        const content = document.getElementById('allQrDataContent').textContent;
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.download = 'all_pet_qr_urls.txt';
        a.href = url;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
</script>
</body>
</html>




