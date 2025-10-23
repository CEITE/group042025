<?php
// pet-medical-access.php - ENHANCED DESIGN
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';

// Initialize variables
$pet_data = null;
$medical_records = [];

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch pet data if connection successful
        if ($pet_id > 0 && isset($conn)) {
            // Get ALL pet data including medical fields
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
                FROM pets p 
                LEFT JOIN users u ON p.user_id = u.user_id 
                WHERE p.pet_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $pet_data = $result->fetch_assoc();
                }
                $stmt->close();
            }
            
            // Fetch ALL medical records from pet_medical_records table
            $stmt = $conn->prepare("
                SELECT * FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY record_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $medical_records = $result->fetch_all(MYSQLI_ASSOC);
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    // Silent fail - we'll use the basic data
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #ffd6e7;
            --pink-dark: #ec4899;
            --pink-darker: #db2777;
            --pink-light: #fff4f8;
            --pink-gradient: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            --blue: #3b82f6;
            --blue-light: #dbeafe;
            --green: #10b981;
            --green-light: #d1fae5;
            --purple: #8b5cf6;
            --purple-light: #ede9fe;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #f0f9ff 100%);
            min-height: 100vh;
            color: #1f2937;
            line-height: 1.7;
        }
        
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 4rem 2rem;
            border-radius: var(--radius);
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-lg);
        }
        
        .medical-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 6s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .pet-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 2rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .medical-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .medical-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .medical-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--pink-gradient);
        }
        
        .card-header-custom {
            background: var(--pink-light);
            border-bottom: 2px solid var(--pink);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--pink-darker);
            font-size: 1.2rem;
        }
        
        .card-header-blue {
            background: var(--blue-light);
            border-bottom: 2px solid var(--blue);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--blue);
            font-size: 1.2rem;
        }
        
        .card-header-green {
            background: var(--green-light);
            border-bottom: 2px solid var(--green);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--green);
            font-size: 1.2rem;
        }
        
        .record-item {
            background: linear-gradient(135deg, var(--pink-light) 0%, #fff 100%);
            border-radius: var(--radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border-left: 5px solid var(--pink-dark);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.1);
        }
        
        .record-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
        }
        
        .history-item {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            border-radius: var(--radius-sm);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid #6c757d;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.08);
        }
        
        .history-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.12);
        }
        
        .medical-content {
            background: rgba(255, 255, 255, 0.7);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(0, 0, 0, 0.08);
            white-space: pre-line;
            line-height: 1.8;
            font-size: 1.05rem;
            backdrop-filter: blur(10px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #d1d5db;
            opacity: 0.7;
        }
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            margin: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--pink);
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.08);
        }
        
        .info-card i {
            font-size: 2rem;
            color: var(--pink-darker);
            margin-bottom: 1rem;
        }
        
        .contact-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: var(--radius);
            padding: 2rem;
            margin: 2rem 0;
            border: 2px solid #bae6fd;
        }
        
        .emergency-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: var(--radius);
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }
        
        .floating-action {
            animation: float 4s ease-in-out infinite;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .medical-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            background: var(--pink-gradient);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--pink), transparent);
            margin: 3rem 0;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Header -->
        <div class="medical-header">
            <div class="pet-avatar floating">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Profile</h1>
            <p class="lead mb-4 opacity-90">Complete Medical History & Healthcare Records</p>
            <div class="d-flex flex-wrap justify-content-center">
                <span class="stats-badge">
                    <i class="fas fa-shield-alt"></i> Secure QR Access
                </span>
                <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                <span class="stats-badge">
                    <i class="fas fa-history"></i> Medical History Available
                </span>
                <?php endif; ?>
                <?php if (!empty($medical_records)): ?>
                <span class="stats-badge">
                    <i class="fas fa-file-medical"></i> <?php echo count($medical_records); ?> Visit Records
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xxl-10 col-xl-12">
                <!-- Emergency Banner -->
                <div class="emergency-banner pulse">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                        </div>
                        <div class="col">
                            <h4 class="text-warning mb-2">Emergency Medical Access</h4>
                            <p class="mb-0">This QR code provides instant access to vital medical information for emergency veterinary care.</p>
                        </div>
                    </div>
                </div>

                <!-- Pet Information -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h3 class="mb-0">
                            <i class="fas fa-paw me-3"></i>Pet Information
                        </h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($pet_data): ?>
                            <div class="info-grid">
                                <div class="info-card">
                                    <i class="fas fa-id-card"></i>
                                    <h6>Basic Info</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                    <p class="mb-1"><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                                    <p class="mb-1"><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                    <p class="mb-0"><strong>Age:</strong> <?php echo htmlspecialchars($pet_data['age']); ?> years</p>
                                </div>
                                
                                <div class="info-card">
                                    <i class="fas fa-venus-mars"></i>
                                    <h6>Physical Details</h6>
                                    <p class="mb-1"><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                    <p class="mb-1"><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></p>
                                    <p class="mb-1"><strong>Weight:</strong> <?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></p>
                                    <p class="mb-0"><strong>Birth Date:</strong> <?php echo !empty($pet_data['birth_date']) ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Not specified'; ?></p>
                                </div>
                                
                                <div class="info-card">
                                    <i class="fas fa-id-badge"></i>
                                    <h6>Identification</h6>
                                    <p class="mb-1"><strong>Pet ID:</strong> #<?php echo htmlspecialchars($pet_data['pet_id']); ?></p>
                                    <p class="mb-1"><strong>Registered:</strong> <?php echo !empty($pet_data['date_registered']) ? date('M j, Y', strtotime($pet_data['date_registered'])) : 'Unknown'; ?></p>
                                    <?php if ($pet_data['owner_name']): ?>
                                        <p class="mb-0"><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($pet_data['medical_notes'])): ?>
                                <div class="mt-4 p-4 bg-light rounded-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="medical-icon">
                                            <i class="fas fa-file-medical"></i>
                                        </div>
                                        <h4 class="text-pink-darker mb-0">Current Medical Notes</h4>
                                    </div>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-paw"></i>
                                <h4 class="text-muted"><?php echo $pet_name; ?></h4>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                                <p class="text-muted">Basic information available in full system</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="section-divider">

                <!-- MEDICAL RECORDS from pet_medical_records table -->
                <div class="medical-card">
                    <div class="card-header-blue">
                        <h3 class="mb-0">
                            <i class="fas fa-file-medical-alt me-3"></i>Medical Visit Records
                            <?php if (!empty($medical_records)): ?>
                                <span class="badge bg-white text-blue ms-2 fs-6"><?php echo count($medical_records); ?> visits</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if (!empty($medical_records)): ?>
                            <div class="row g-4">
                                <?php foreach ($medical_records as $record): ?>
                                <div class="col-lg-6">
                                    <div class="record-item h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="text-pink-darker mb-1"><?php echo htmlspecialchars($record['record_type']); ?></h5>
                                                <?php if (!empty($record['veterinarian'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user-md me-1"></i>Dr. <?php echo htmlspecialchars($record['veterinarian']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-muted d-block"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></strong>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($record['record_date'])); ?></small>
                                            </div>
                                        </div>
                                        <p class="mb-3 fs-6"><?php echo htmlspecialchars($record['description']); ?></p>
                                        <?php if (!empty($record['notes'])): ?>
                                            <div class="bg-white p-3 rounded border">
                                                <small class="text-dark">
                                                    <strong><i class="fas fa-sticky-note me-1"></i>Additional Notes:</strong><br>
                                                    <?php echo htmlspecialchars($record['notes']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-medical"></i>
                                <h4 class="text-muted">No Medical Visit Records</h4>
                                <p class="text-muted">No medical visit records found in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="section-divider">

                <!-- MEDICAL HISTORY from pets table -->
                <div class="medical-card">
                    <div class="card-header-green">
                        <h3 class="mb-0">
                            <i class="fas fa-history me-3"></i>Medical History Summary
                            <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                                <span class="badge bg-white text-green ms-2 fs-6">Complete History</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($pet_data): ?>
                            <div class="row g-4">
                                <!-- Previous Conditions -->
                                <div class="col-xl-6">
                                    <div class="history-item h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="medical-icon" style="background: var(--pink-gradient);">
                                                <i class="fas fa-stethoscope"></i>
                                            </div>
                                            <h4 class="text-dark mb-0">Previous Conditions</h4>
                                        </div>
                                        <?php if (!empty($pet_data['previous_conditions']) && trim($pet_data['previous_conditions']) !== ''): ?>
                                            <div class="medical-content"><?php echo htmlspecialchars($pet_data['previous_conditions']); ?></div>
                                        <?php else: ?>
                                            <div class="empty-state py-4">
                                                <i class="fas fa-stethoscope text-muted"></i>
                                                <p class="text-muted mb-0">No previous conditions recorded</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Vaccination History -->
                                <div class="col-xl-6">
                                    <div class="history-item h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="medical-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                                                <i class="fas fa-syringe"></i>
                                            </div>
                                            <h4 class="text-dark mb-0">Vaccination History</h4>
                                        </div>
                                        <?php if (!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== ''): ?>
                                            <div class="medical-content"><?php echo htmlspecialchars($pet_data['vaccination_history']); ?></div>
                                        <?php else: ?>
                                            <div class="empty-state py-4">
                                                <i class="fas fa-syringe text-muted"></i>
                                                <p class="text-muted mb-0">No vaccination history recorded</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Surgical History -->
                                <div class="col-xl-6">
                                    <div class="history-item h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="medical-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                                <i class="fas fa-scissors"></i>
                                            </div>
                                            <h4 class="text-dark mb-0">Surgical History</h4>
                                        </div>
                                        <?php if (!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== ''): ?>
                                            <div class="medical-content"><?php echo htmlspecialchars($pet_data['surgical_history']); ?></div>
                                        <?php else: ?>
                                            <div class="empty-state py-4">
                                                <i class="fas fa-scissors text-muted"></i>
                                                <p class="text-muted mb-0">No surgical history recorded</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Medication History -->
                                <div class="col-xl-6">
                                    <div class="history-item h-100">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="medical-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                                <i class="fas fa-pills"></i>
                                            </div>
                                            <h4 class="text-dark mb-0">Medication History</h4>
                                        </div>
                                        <?php if (!empty($pet_data['medication_history']) && trim($pet_data['medication_history']) !== ''): ?>
                                            <div class="medical-content"><?php echo htmlspecialchars($pet_data['medication_history']); ?></div>
                                        <?php else: ?>
                                            <div class="empty-state py-4">
                                                <i class="fas fa-pills text-muted"></i>
                                                <p class="text-muted mb-0">No medication history recorded</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Records Location -->
                            <?php if (!empty($pet_data['records_location']) && trim($pet_data['records_location']) !== ''): ?>
                            <div class="history-item mt-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="medical-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                        <i class="fas fa-archive"></i>
                                    </div>
                                    <h4 class="text-dark mb-0">Existing Records Location</h4>
                                </div>
                                <div class="medical-content"><?php echo htmlspecialchars($pet_data['records_location']); ?></div>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h4 class="text-muted">No Medical History Data</h4>
                                <p class="text-muted">No medical history data available for this pet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <?php if ($pet_data && ($pet_data['owner_name'] || $pet_data['vet_contact'])): ?>
                <div class="contact-section">
                    <div class="row">
                        <?php if ($pet_data['owner_name']): ?>
                        <div class="col-lg-6 mb-4 mb-lg-0">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                                <h4 class="text-primary mb-0">Owner Contact</h4>
                            </div>
                            <div class="ps-5">
                                <p class="mb-2 fs-5"><strong><?php echo htmlspecialchars($pet_data['owner_name']); ?></strong></p>
                                <?php if ($pet_data['owner_phone']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($pet_data['owner_phone']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($pet_data['owner_email']): ?>
                                    <p class="mb-0">
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($pet_data['owner_email']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pet_data['vet_contact']): ?>
                        <div class="col-lg-6">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-hospital-user fa-2x text-success me-3"></i>
                                <h4 class="text-success mb-0">Veterinarian Contact</h4>
                            </div>
                            <div class="ps-5">
                                <p class="mb-0 fs-5"><strong><?php echo htmlspecialchars($pet_data['vet_contact']); ?></strong></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <footer class="text-center text-muted mt-5 pt-5 border-top">
                    <div class="mb-3">
                        <i class="fas fa-paw fa-2x text-pink-dark me-2"></i>
                        <strong class="text-pink-darker fs-4">PetMedQR</strong>
                    </div>
                    <p class="mb-2 small">&copy; <?php echo date('Y'); ?> PetMedQR Medical Records System</p>
                    <p class="small text-muted">Secure QR-based pet medical records access for emergency veterinary care</p>
                    <?php if ($pet_data && $pet_data['medical_history_updated_at']): ?>
                        <p class="small text-muted mt-2">
                            <i class="fas fa-sync me-1"></i>
                            Last updated: <?php echo date('F j, Y \a\t g:i A', strtotime($pet_data['medical_history_updated_at'])); ?>
                        </p>
                    <?php endif; ?>
                </footer>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add subtle animations to cards when they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.medical-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>
