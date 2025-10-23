<?php
// pet-medical-access.php - DISPLAYS ALL RECORDS WITHOUT LOGIN
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';

// Initialize variables
$pet_data = null;
$recent_records = [];
$medical_history = [];
$all_medical_records = [];

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch pet data if connection successful
        if ($pet_id > 0 && isset($conn)) {
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
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
            
            // Fetch ALL records from pet_medical_records table (not just recent)
            $stmt = $conn->prepare("
                SELECT record_type, record_date, description, veterinarian, notes
                FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY record_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $all_medical_records = $result->fetch_all(MYSQLI_ASSOC);
                }
                $stmt->close();
            }
            
            // Fetch medical history from pets table (the historical records)
            if ($pet_data) {
                // Build medical history array from pets table fields
                $medical_history = [];
                
                // Previous Conditions
                if (!empty($pet_data['previous_conditions'])) {
                    $medical_history[] = [
                        'type' => 'Previous Medical Conditions',
                        'details' => $pet_data['previous_conditions'],
                        'date' => $pet_data['medical_history_updated_at'] ?? 'Historical Record'
                    ];
                }
                
                // Vaccination History
                if (!empty($pet_data['vaccination_history'])) {
                    $medical_history[] = [
                        'type' => 'Vaccination History',
                        'details' => $pet_data['vaccination_history'],
                        'date' => $pet_data['medical_history_updated_at'] ?? 'Historical Record'
                    ];
                }
                
                // Surgical History
                if (!empty($pet_data['surgical_history'])) {
                    $medical_history[] = [
                        'type' => 'Surgical History',
                        'details' => $pet_data['surgical_history'],
                        'date' => $pet_data['medical_history_updated_at'] ?? 'Historical Record'
                    ];
                }
                
                // Medication History
                if (!empty($pet_data['medication_history'])) {
                    $medical_history[] = [
                        'type' => 'Medication History',
                        'details' => $pet_data['medication_history'],
                        'date' => $pet_data['medical_history_updated_at'] ?? 'Historical Record'
                    ];
                }
                
                // Specific vaccine dates
                if (!empty($pet_data['rabies_vaccine_date'])) {
                    $medical_history[] = [
                        'type' => 'Rabies Vaccine',
                        'details' => 'Rabies vaccination administered',
                        'date' => $pet_data['rabies_vaccine_date']
                    ];
                }
                
                if (!empty($pet_data['dhpp_vaccine_date'])) {
                    $medical_history[] = [
                        'type' => 'DHPP Vaccine',
                        'details' => 'DHPP vaccination administered',
                        'date' => $pet_data['dhpp_vaccine_date']
                    ];
                }
                
                // Spay/Neuter history
                if (!empty($pet_data['is_spayed_neutered']) && !empty($pet_data['spay_neuter_date'])) {
                    $medical_history[] = [
                        'type' => 'Surgical Procedure',
                        'details' => ($pet_data['gender'] == 'Male' ? 'Neutered' : 'Spayed'),
                        'date' => $pet_data['spay_neuter_date']
                    ];
                }
                
                // Vet visit history
                if (!empty($pet_data['last_vet_visit'])) {
                    $medical_history[] = [
                        'type' => 'Last Veterinary Visit',
                        'details' => 'Routine checkup or consultation',
                        'date' => $pet_data['last_vet_visit']
                    ];
                }
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
            padding: 2.5rem 2rem;
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
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
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
        
        .record-item {
            background: var(--pink-light);
            border-radius: var(--radius);
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--pink-dark);
            transition: all 0.3s ease;
        }
        
        .record-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }
        
        .history-item {
            background: #f8f9fa;
            border-radius: var(--radius);
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid #6c757d;
            transition: all 0.3s ease;
        }
        
        .history-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }
        
        .emergency-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 4px solid #f59e0b;
        }
        
        .contact-info {
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 4px solid #3b82f6;
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .medical-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
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
            <h1 class="display-6 fw-bold mb-2"><?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Records</h1>
            <p class="lead mb-0 opacity-90">Complete Medical History - No Login Required</p>
            <div class="mt-3">
                <span class="stats-badge">
                    <i class="fas fa-shield-alt"></i> Public Access
                </span>
                <?php if (!empty($all_medical_records)): ?>
                <span class="stats-badge">
                    <i class="fas fa-file-medical"></i> <?php echo count($all_medical_records); ?> Medical Records
                </span>
                <?php endif; ?>
                <?php if (!empty($medical_history)): ?>
                <span class="stats-badge">
                    <i class="fas fa-history"></i> <?php echo count($medical_history); ?> History Items
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Emergency Alert -->
                <div class="emergency-alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-warning fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Complete Medical Access</h5>
                            <p class="mb-0">All medical records are publicly accessible for emergency veterinary care.</p>
                        </div>
                    </div>
                </div>

                <!-- Pet Information -->
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
                                    <div class="mb-3">
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
                                            <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['age']); ?> years</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h5 class="text-pink-darker mb-3">
                                            <i class="fas fa-venus-mars me-2"></i>Additional Info
                                        </h5>
                                        <div class="row">
                                            <div class="col-6 mb-2"><strong>Gender:</strong></div>
                                            <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></div>
                                            
                                            <div class="col-6 mb-2"><strong>Color:</strong></div>
                                            <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></div>
                                            
                                            <div class="col-6 mb-2"><strong>Weight:</strong></div>
                                            <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></div>
                                            
                                            <?php if ($pet_data['owner_name']): ?>
                                                <div class="col-6 mb-2"><strong>Owner:</strong></div>
                                                <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['owner_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($pet_data['medical_notes']): ?>
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6 class="text-pink-darker mb-2">
                                        <i class="fas fa-file-medical me-2"></i>Medical Notes
                                    </h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-paw fa-3x text-muted mb-3"></i>
                                <h5 class="text-pink-darker mb-2"><?php echo $pet_name; ?></h5>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Medical Records -->
                <?php if (!empty($all_medical_records)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>Medical Records
                            <span class="medical-badge ms-2"><?php echo count($all_medical_records); ?> records</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($all_medical_records as $record): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong class="text-pink-darker h6"><?php echo htmlspecialchars($record['record_type']); ?></strong>
                                        <?php if (!empty($record['veterinarian'])): ?>
                                            <span class="badge bg-primary ms-2">Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <strong class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></strong>
                                </div>
                                <div class="text-muted mb-2"><?php echo htmlspecialchars($record['description']); ?></div>
                                <?php if (!empty($record['notes'])): ?>
                                    <div class="bg-white p-2 rounded border">
                                        <small class="text-dark"><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>Medical Records
                        </h4>
                    </div>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h5 class="text-muted">No Medical Records Found</h5>
                        <p class="text-muted">No medical visit records have been added yet.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Medical History from Pets Table -->
                <?php if (!empty($medical_history)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History
                            <span class="medical-badge ms-2"><?php echo count($medical_history); ?> items</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($medical_history as $history): ?>
                            <div class="history-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-dark h6"><?php echo htmlspecialchars($history['type']); ?></strong>
                                        <div class="text-muted mt-1"><?php echo htmlspecialchars($history['details']); ?></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php 
                                        if ($history['date'] && $history['date'] != 'Historical Record') {
                                            echo date('M j, Y', strtotime($history['date']));
                                        } else {
                                            echo 'Historical';
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Important Medical Dates -->
                <?php if ($pet_data && (!empty($pet_data['next_vet_visit']) || !empty($pet_data['rabies_vaccine_date']) || !empty($pet_data['dhpp_vaccine_date']))): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Important Dates
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($pet_data['next_vet_visit'])): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-pink-darker">Next Veterinary Visit</strong>
                                        <div class="text-muted small mt-1">Scheduled appointment</div>
                                    </div>
                                    <strong class="text-primary"><?php echo date('M j, Y', strtotime($pet_data['next_vet_visit'])); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['rabies_vaccine_date'])): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-pink-darker">Rabies Vaccine</strong>
                                        <div class="text-muted small mt-1">Last administered</div>
                                    </div>
                                    <strong class="text-success"><?php echo date('M j, Y', strtotime($pet_data['rabies_vaccine_date'])); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['dhpp_vaccine_date'])): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-pink-darker">DHPP Vaccine</strong>
                                        <div class="text-muted small mt-1">Last administered</div>
                                    </div>
                                    <strong class="text-success"><?php echo date('M j, Y', strtotime($pet_data['dhpp_vaccine_date'])); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact & Emergency Info -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="contact-info">
                            <h6 class="mb-2">
                                <i class="fas fa-phone-alt me-2"></i>Emergency Contact
                            </h6>
                            <p class="mb-2 small">For immediate medical emergencies, contact:</p>
                            <?php if ($pet_data && $pet_data['owner_name']): ?>
                                <p class="mb-1"><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                <?php if ($pet_data['owner_phone']): ?>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($pet_data['owner_email']): ?>
                                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-0 small">Contact information not available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="emergency-alert">
                            <h6 class="mb-2">
                                <i class="fas fa-first-aid me-2"></i>Veterinary Contact
                            </h6>
                            <p class="mb-2 small">Primary veterinarian:</p>
                            <?php if ($pet_data && $pet_data['vet_contact']): ?>
                                <p class="mb-0"><strong><?php echo htmlspecialchars($pet_data['vet_contact']); ?></strong></p>
                            <?php else: ?>
                                <p class="mb-0 small">Veterinarian details not specified</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Public Access Notice -->
                <div class="medical-card mt-4">
                    <div class="card-body text-center py-3">
                        <p class="text-muted mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            This is a public medical access page. All information is openly available for emergency veterinary care.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw text-pink-dark me-2"></i>
                <strong class="text-pink-darker">PetMedQR Public Access</strong>
            </div>
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <p class="small text-muted">Public medical records access for emergency veterinary care</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
