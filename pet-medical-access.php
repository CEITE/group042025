<?php
// pet-medical-access.php - ENHANCED WITH PET_MEDICAL_RECORDS TABLE
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';

// Simple base URL
$base_url = 'https://group042025.ceitesystems.com';

// Initialize variables
$pet_data = null;
$medical_records = [];
$vaccinations = [];
$medications = [];
$procedures = [];
$checkups = [];

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch pet data if connection successful
        if ($pet_id > 0 && isset($conn)) {
            // Fetch basic pet info
            $stmt = $conn->prepare("
                SELECT 
                    p.*, 
                    u.name as owner_name, 
                    u.email as owner_email, 
                    u.phone as owner_phone
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
                SELECT 
                    record_id,
                    record_type,
                    record_date,
                    description,
                    veterinarian,
                    notes,
                    medication_name,
                    dosage,
                    frequency,
                    vaccination_name,
                    vaccination_date,
                    next_vaccination_date,
                    procedure_type,
                    cost,
                    follow_up_required,
                    follow_up_date,
                    created_at
                FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY record_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $medical_records = $result->fetch_all(MYSQLI_ASSOC);
                    
                    // Categorize records by type
                    foreach ($medical_records as $record) {
                        switch($record['record_type']) {
                            case 'Vaccination':
                                $vaccinations[] = $record;
                                break;
                            case 'Medication':
                                $medications[] = $record;
                                break;
                            case 'Surgery':
                            case 'Procedure':
                                $procedures[] = $record;
                                break;
                            case 'Checkup':
                            case 'Examination':
                                $checkups[] = $record;
                                break;
                            default:
                                // Keep in general medical records
                                break;
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    // Silent fail - we'll use the basic data
}

// Calculate age from birth date if available
function calculateAge($birth_date) {
    if (!$birth_date) return 'Unknown';
    
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    
    if ($age->y > 0) {
        return $age->y . ' year' . ($age->y > 1 ? 's' : '');
    } elseif ($age->m > 0) {
        return $age->m . ' month' . ($age->m > 1 ? 's' : '');
    } else {
        return $age->d . ' day' . ($age->d > 1 ? 's' : '');
    }
}

// Get record type icon
function getRecordIcon($record_type) {
    switch($record_type) {
        case 'Vaccination': return 'fas fa-syringe';
        case 'Medication': return 'fas fa-pills';
        case 'Surgery': return 'fas fa-procedures';
        case 'Procedure': return 'fas fa-tools';
        case 'Checkup': return 'fas fa-stethoscope';
        case 'Examination': return 'fas fa-search';
        default: return 'fas fa-file-medical';
    }
}

// Get record type color
function getRecordColor($record_type) {
    switch($record_type) {
        case 'Vaccination': return '#22c55e';
        case 'Medication': return '#3b82f6';
        case 'Surgery': return '#ea580c';
        case 'Procedure': return '#8b5cf6';
        case 'Checkup': return '#06b6d4';
        default: return '#ec4899';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Medical Records - PetMedQR</title>
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
            color: #1f2937;
            line-height: 1.6;
        }
        
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 3rem 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }
        
        .medical-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: rotate(45deg);
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
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }
        
        .medical-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .medical-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--pink-gradient);
        }
        
        .card-header-custom {
            background: var(--pink-gradient-light);
            border-bottom: 1px solid var(--pink);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--pink-darker);
        }
        
        /* Medical History Specific Styles */
        .medical-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .medical-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--pink);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border-left: 4px solid var(--pink-dark);
            box-shadow: var(--shadow);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.9rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--pink-dark);
            border: 3px solid white;
            box-shadow: var(--shadow);
        }
        
        .record-badge {
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .medical-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .medical-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border: 1px solid #e5e7eb;
            box-shadow: var(--shadow);
        }
        
        .medical-stat .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--pink-darker);
            display: block;
        }
        
        .medical-stat .label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .record-details {
            background: var(--pink-light);
            border-radius: var(--radius);
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .follow-up-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: var(--radius);
            padding: 0.75rem;
            margin-top: 0.5rem;
            border-left: 4px solid #f59e0b;
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-header">
            <div class="pet-avatar floating">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Pet Medical Records</h1>
            <p class="lead mb-0 opacity-90">Complete Medical History & Health Information</p>
            <div class="mt-3">
                <?php if (!empty($medical_records)): ?>
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-notes-medical me-1"></i><?php echo count($medical_records); ?> Medical Records
                    </span>
                <?php endif; ?>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-shield-alt me-1"></i> Secure QR Access
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Medical Statistics -->
                <?php if (!empty($medical_records)): ?>
                <div class="medical-stats">
                    <div class="medical-stat">
                        <span class="number"><?php echo count($medical_records); ?></span>
                        <span class="label">Total Records</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number"><?php echo count($vaccinations); ?></span>
                        <span class="label">Vaccinations</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number"><?php echo count($medications); ?></span>
                        <span class="label">Medications</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number"><?php echo count($procedures); ?></span>
                        <span class="label">Procedures</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pet Information Card -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-paw me-2"></i>Pet Information
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="text-pink-darker mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Basic Details
                                    </h5>
                                    <div class="row">
                                        <div class="col-6 mb-2"><strong>Name:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['name']); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Species:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['species']); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Breed:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Age:</strong></div>
                                        <div class="col-6 mb-2"><?php echo calculateAge($pet_data['birth_date']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="text-pink-darker mb-3">
                                        <i class="fas fa-venus-mars me-2"></i>Health Details
                                    </h5>
                                    <div class="row">
                                        <div class="col-6 mb-2"><strong>Gender:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Color:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Weight:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Birth Date:</strong></div>
                                        <div class="col-6 mb-2"><?php echo $pet_data['birth_date'] ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Unknown'; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($pet_data['medical_notes'])): ?>
                                <div class="record-details mt-3">
                                    <h6 class="text-pink-darker mb-2">
                                        <i class="fas fa-file-medical me-2"></i>Medical Notes
                                    </h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['medical_notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-paw fa-3x text-pink-darker mb-3"></i>
                                <h5 class="text-pink-darker mb-2"><?php echo $pet_name; ?></h5>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                                <p class="text-muted">Complete medical details available in full system</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical History Timeline -->
                <?php if (!empty($medical_records)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History Timeline
                            <span class="badge bg-primary ms-2"><?php echo count($medical_records); ?> records</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="medical-timeline">
                            <?php foreach ($medical_records as $record): 
                                $record_color = getRecordColor($record['record_type']);
                                $record_icon = getRecordIcon($record['record_type']);
                            ?>
                                <div class="timeline-item">
                                    <span class="record-badge" style="background: <?php echo $record_color; ?>">
                                        <i class="<?php echo $record_icon; ?> me-1"></i>
                                        <?php echo htmlspecialchars($record['record_type']); ?>
                                    </span>
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($record['description']); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></small>
                                    </div>
                                    
                                    <?php if (!empty($record['veterinarian'])): ?>
                                        <p class="mb-1 small"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                                    <?php endif; ?>
                                    
                                    <!-- Vaccination Details -->
                                    <?php if (!empty($record['vaccination_name'])): ?>
                                        <div class="record-details">
                                            <strong><i class="fas fa-syringe me-1"></i>Vaccination:</strong> 
                                            <?php echo htmlspecialchars($record['vaccination_name']); ?>
                                            <?php if (!empty($record['next_vaccination_date'])): ?>
                                                <br><small><strong>Next due:</strong> <?php echo date('M j, Y', strtotime($record['next_vaccination_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Medication Details -->
                                    <?php if (!empty($record['medication_name'])): ?>
                                        <div class="record-details">
                                            <strong><i class="fas fa-pills me-1"></i>Medication:</strong> 
                                            <?php echo htmlspecialchars($record['medication_name']); ?>
                                            <?php if (!empty($record['dosage'])): ?>
                                                <span class="text-muted"> - <?php echo htmlspecialchars($record['dosage']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($record['frequency'])): ?>
                                                <span class="text-muted"> (<?php echo htmlspecialchars($record['frequency']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Procedure Details -->
                                    <?php if (!empty($record['procedure_type'])): ?>
                                        <div class="record-details">
                                            <strong><i class="fas fa-procedures me-1"></i>Procedure:</strong> 
                                            <?php echo htmlspecialchars($record['procedure_type']); ?>
                                            <?php if (!empty($record['cost'])): ?>
                                                <span class="text-muted"> - $<?php echo number_format($record['cost'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($record['notes'])): ?>
                                        <div class="record-details mt-2">
                                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Follow-up Alert -->
                                    <?php if ($record['follow_up_required'] == 1 && !empty($record['follow_up_date'])): ?>
                                        <div class="follow-up-alert">
                                            <i class="fas fa-calendar-check me-2"></i>
                                            <strong>Follow-up required:</strong> <?php echo date('M j, Y', strtotime($record['follow_up_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="medical-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5 class="text-pink-darker mb-2">No Medical Records Found</h5>
                        <p class="text-muted">This pet doesn't have any medical records in the system yet.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emergency & Contact Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="medical-card">
                            <div class="card-header-custom">
                                <h5 class="mb-0">
                                    <i class="fas fa-phone-alt me-2"></i>Emergency Contacts
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pet_data && $pet_data['owner_name']): ?>
                                    <h6 class="text-pink-darker mb-3">Pet Owner</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php if ($pet_data['owner_phone']): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($pet_data['owner_email']): ?>
                                        <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Owner contact information available in full system</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="medical-card">
                            <div class="card-header-custom">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-md me-2"></i>Veterinary Contact
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pet_data && $pet_data['vet_contact']): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['vet_contact'])); ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Veterinarian details available in full system</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="medical-card text-center">
                    <div class="card-body py-5">
                        <h3 class="text-pink-darker mb-3">Access Complete Medical System</h3>
                        <p class="text-muted mb-4 lead">
                            Login for full medical history, treatment plans, prescriptions, lab results, and comprehensive health tracking.
                        </p>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                            <a href="<?php echo $base_url; ?>/login.php" class="btn btn-primary btn-lg px-4" style="background: var(--pink-gradient); border: none;">
                                <i class="fas fa-sign-in-alt me-2"></i>System Login
                            </a>
                            <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary btn-lg px-4" style="border-color: var(--pink-dark); color: var(--pink-dark);">
                                <i class="fas fa-user-plus me-2"></i>Request Access
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw text-pink-dark me-2"></i>
                <strong class="text-pink-darker">PetMedQR Medical Records</strong>
            </div>
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <p class="small text-muted">Secure pet medical records management system</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
