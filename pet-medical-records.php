<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Check if pet_id is provided
if (!isset($_GET['pet_id'])) {
    header("Location: user_pet_profile.php");
    exit();
}

$pet_id = $_GET['pet_id'];

// ✅ Verify pet belongs to user and get pet info
$stmt = $conn->prepare("SELECT p.*, u.name as owner_name FROM pets p 
                       JOIN users u ON p.user_id = u.user_id 
                       WHERE p.pet_id = ? AND p.user_id = ?");
$stmt->bind_param("ii", $pet_id, $user_id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();

if (!$pet) {
    header("Location: user_pet_profile.php");
    exit();
}

// ✅ Fetch user info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ✅ Fetch medical records for this pet using YOUR actual column names
$query = "
SELECT 
    record_id,
    owner_id,
    owner_name,
    pet_id,
    pet_name,
    species,
    breed,
    color,
    sex,
    dob,
    age,
    weight,
    status,
    tag,
    microchip,
    weight_date,
    reminder_description,
    reminder_due_date,
    service_date,
    service_time,
    service_type,
    service_description,
    veterinarian,
    notes,
    generated_date,
    clinic_name,
    clinic_address,
    clinic_contact
FROM pet_medical_records 
WHERE pet_id = ? 
ORDER BY service_date DESC, generated_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$medical_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - <?php echo htmlspecialchars($pet['name']); ?> - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #ffd6e7;
            --pink-2: #f7c5e0;
            --pink-light: #fff4f8;
            --blue: #4a6cf7;
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fb;
            margin: 0;
            color: #333;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--pink-2);
            padding: 2rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid rgba(0,0,0,0.1);
            object-fit: cover;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            margin: .3rem 0;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: .2s;
        }
        
        .sidebar a .icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.6);
            margin-right: 10px;
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: var(--pink);
            color: #000;
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: #fff;
            background: #dc3545;
            text-align: center;
            padding: 10px;
            border-radius: 10px;
        }
        
        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .pet-header {
            background: linear-gradient(135deg, var(--pink-light), var(--blue-light));
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .record-card {
            border-left: 4px solid var(--blue);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
            background: white;
            border-radius: 0 8px 8px 0;
            box-shadow: var(--shadow);
        }
        
        .record-card:hover {
            transform: translateX(5px);
        }
        
        .record-header {
            background: var(--pink-light);
            padding: 1rem 1.5rem;
            border-radius: 0 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-body {
            padding: 1.5rem;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .detail-item {
            background: var(--pink-light);
            padding: 1rem;
            border-radius: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .pet-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: white;
            border: 4px solid white;
            box-shadow: var(--shadow);
        }
        
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .record-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-paw"></i> PetMedQR</div>
        <div class="profile">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="User">
            <?php else: ?>
                <img src="https://i.pravatar.cc/100?u=<?php echo urlencode($user['email']); ?>" alt="User">
            <?php endif; ?>
            <h6><?php echo htmlspecialchars($user['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
        </div>
        <a href="user_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="user_pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> My Pets
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> QR Codes
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> Register Pet
        </a>
        <a href="user_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Medical Records</h5>
                <small class="text-muted">Viewing medical history for <?php echo htmlspecialchars($pet['name']); ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="user_pet_profile.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Pets
                </a>
                <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary">
                    <i class="fa-solid fa-plus-circle me-1"></i> Add Record
                </a>
            </div>
        </div>

        <!-- Pet Header -->
        <div class="pet-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="pet-avatar me-3" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#bbdefb' : '#f8bbd0'; ?>">
                            <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                        </div>
                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars($pet['name']); ?></h2>
                            <p class="mb-1">
                                <strong>Species:</strong> <?php echo htmlspecialchars($pet['species']); ?> • 
                                <strong>Breed:</strong> <?php echo htmlspecialchars($pet['breed']); ?> • 
                                <strong>Age:</strong> <?php echo htmlspecialchars($pet['age']); ?> years
                            </p>
                            <p class="mb-0">
                                <strong>Owner:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary fs-6">Pet ID: <?php echo htmlspecialchars($pet['pet_id']); ?></span>
                    <span class="badge bg-success fs-6"><?php echo count($medical_records); ?> Records</span>
                </div>
            </div>
        </div>

        <?php if (empty($medical_records)): ?>
            <div class="card-custom text-center">
                <div class="empty-state">
                    <i class="fa-solid fa-file-medical"></i>
                    <h5>No Medical Records Found</h5>
                    <p class="text-muted">No medical records have been added for <?php echo htmlspecialchars($pet['name']); ?> yet.</p>
                    <a href="add_medical_record.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add First Record
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card-custom">
                <h5 class="mb-3"><i class="fa-solid fa-file-waveform me-2"></i>Medical History (<?php echo count($medical_records); ?> records)</h5>
                
                <?php foreach ($medical_records as $record): ?>
                    <div class="record-card">
                        <div class="record-header">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fa-solid fa-calendar-check me-2"></i>
                                    <?php if (!empty($record['service_date'])): ?>
                                        Service Date: <?php echo date('F j, Y', strtotime($record['service_date'])); ?>
                                    <?php else: ?>
                                        Record Date: <?php echo date('F j, Y', strtotime($record['generated_date'])); ?>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fa-solid fa-user-doctor me-1"></i>
                                    <?php echo !empty($record['veterinarian']) ? htmlspecialchars($record['veterinarian']) : 'Veterinarian not specified'; ?>
                                    <?php if (!empty($record['clinic_name'])): ?>
                                        at <?php echo htmlspecialchars($record['clinic_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-info">Record #<?php echo $record['record_id']; ?></span>
                                <?php if (!empty($record['service_type'])): ?>
                                    <span class="badge bg-warning"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="record-body">
                            <?php if (!empty($record['service_description'])): ?>
                                <div class="mb-3">
                                    <strong><i class="fa-solid fa-stethoscope me-2"></i>Service Description:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($record['service_description']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-grid">
                                <?php if (!empty($record['service_type'])): ?>
                                    <div class="detail-item">
                                        <strong><i class="fa-solid fa-hand-holding-medical me-2"></i>Service Type:</strong>
                                        <?php echo htmlspecialchars($record['service_type']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['service_time'])): ?>
                                    <div class="detail-item">
                                        <strong><i class="fa-solid fa-clock me-2"></i>Service Time:</strong>
                                        <?php echo htmlspecialchars($record['service_time']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['weight'])): ?>
                                    <div class="detail-item">
                                        <strong><i class="fa-solid fa-weight-scale me-2"></i>Weight:</strong>
                                        <?php echo htmlspecialchars($record['weight']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['weight_date'])): ?>
                                    <div class="detail-item">
                                        <strong><i class="fa-solid fa-calendar-day me-2"></i>Weight Date:</strong>
                                        <?php echo date('M j, Y', strtotime($record['weight_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($record['reminder_description'])): ?>
                                <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded">
                                    <strong><i class="fa-solid fa-bell me-2"></i>Reminder:</strong>
                                    <p class="mb-1"><?php echo htmlspecialchars($record['reminder_description']); ?></p>
                                    <?php if (!empty($record['reminder_due_date'])): ?>
                                        <small class="text-muted">
                                            Due: <?php echo date('M j, Y', strtotime($record['reminder_due_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['notes'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <strong><i class="fa-solid fa-note-sticky me-2"></i>Additional Notes:</strong>
                                    <p class="mb-0 mt-1"><?php echo htmlspecialchars($record['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['clinic_name']) || !empty($record['clinic_address']) || !empty($record['clinic_contact'])): ?>
                                <div class="mt-3 p-3 bg-info bg-opacity-10 rounded">
                                    <strong><i class="fa-solid fa-hospital me-2"></i>Clinic Information:</strong>
                                    <div class="mt-1">
                                        <?php if (!empty($record['clinic_name'])): ?>
                                            <div><strong>Name:</strong> <?php echo htmlspecialchars($record['clinic_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['clinic_address'])): ?>
                                            <div><strong>Address:</strong> <?php echo htmlspecialchars($record['clinic_address']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['clinic_contact'])): ?>
                                            <div><strong>Contact:</strong> <?php echo htmlspecialchars($record['clinic_contact']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
