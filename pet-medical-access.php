<?php
// pet-medical-access.php - UPDATED TO MATCH USER_PET_PROFILE DISPLAY
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

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch comprehensive pet data from pets table (same as user_pet_profile)
        if ($pet_id > 0 && isset($conn)) {
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
            
            // Also fetch from pet_medical_records table if needed
            $stmt = $conn->prepare("
                SELECT 
                    record_id,
                    service_date,
                    service_type,
                    service_description,
                    veterinarian,
                    notes,
                    reminder_description,
                    reminder_due_date,
                    clinic_name,
                    clinic_address,
                    clinic_contact,
                    generated_date
                FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY service_date DESC
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

// Check if pet has medical history (same logic as user_pet_profile)
function hasMedicalHistory($pet_data) {
    if (!$pet_data) return false;
    
    return !empty($pet_data['previous_conditions']) || 
           !empty($pet_data['vaccination_history']) || 
           !empty($pet_data['surgical_history']) || 
           !empty($pet_data['medication_history']) ||
           !empty($pet_data['last_vet_visit']) || 
           !empty($pet_data['rabies_vaccine_date']);
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
        
        .medical-header {
            background: linear-gradient(135deg, var(--pink-light), var(--blue-light));
            color: #333;
            padding: 3rem 2rem;
            border-radius: var(--radius);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .pet-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            background: white;
            border: 4px solid white;
            box-shadow: var(--shadow);
            margin: 0 auto 1.5rem;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .pet-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .detail-item {
            background: var(--pink-light);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .detail-item i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--blue);
        }
        
        .medical-history {
            background: var(--pink-light);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--blue);
            margin-top: 1.5rem;
        }
        
        .medical-item {
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            border-left: 3px solid var(--green);
            margin-bottom: 0.75rem;
        }
        
        .medical-item strong {
            color: var(--blue);
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .medical-item p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .medical-dates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .medical-date-item {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .medical-date-item small {
            color: #6c757d;
            display: block;
            margin-bottom: 0.25rem;
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
        
        .medical-notes {
            background: var(--blue-light);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--blue);
            margin: 1rem 0;
        }
        
        .vet-contact {
            background: var(--green-light);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--green);
            margin: 1rem 0;
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
            <div class="pet-avatar floating" style="background: <?php echo $pet_data && strtolower($pet_data['species']) == 'dog' ? '#bbdefb' : '#f8bbd0'; ?>">
                <i class="fa-solid <?php echo $pet_data && strtolower($pet_data['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Pet Medical Records</h1>
            <p class="lead mb-0">Complete Medical History & Health Information</p>
            <div class="mt-3">
                <?php if ($pet_data && hasMedicalHistory($pet_data)): ?>
                    <span class="badge bg-success me-2">
                        <i class="fas fa-notes-medical me-1"></i>Medical History Available
                    </span>
                <?php endif; ?>
                <span class="badge bg-primary">
                    <i class="fas fa-shield-alt me-1"></i> Secure QR Access
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Pet Information Card -->
                <div class="card-custom">
                    <div class="d-flex align-items-center mb-4">
                        <h4 class="mb-0">
                            <i class="fa-solid fa-paw me-2"></i>Pet Information
                        </h4>
                        <?php if ($pet_data): ?>
                            <div class="ms-auto">
                                <span class="badge bg-primary">ID: <?php echo htmlspecialchars($pet_data['pet_id']); ?></span>
                                <?php $hasMedicalHistory = hasMedicalHistory($pet_data); ?>
                                <span class="badge <?php echo $hasMedicalHistory ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $hasMedicalHistory ? 'Has Medical History' : 'No Medical History'; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($pet_data): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
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
                                <h5 class="text-primary mb-3">
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
                            <div class="medical-notes mt-4">
                                <h6><i class="fa-solid fa-file-medical me-2"></i>Current Medical Notes</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['medical_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['vet_contact'])): ?>
                            <div class="vet-contact mt-3">
                                <h6><i class="fa-solid fa-user-doctor me-2"></i>Veterinarian Contact</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($pet_data['vet_contact']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-paw fa-3x text-primary mb-3"></i>
                            <h5 class="text-primary mb-2"><?php echo $pet_name; ?></h5>
                            <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                            <p class="text-muted">Complete medical details available in full system</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Medical History Section (Same as user_pet_profile) -->
                <?php if ($pet_data && hasMedicalHistory($pet_data)): ?>
                <div class="card-custom">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-history me-2"></i>Medical History Summary
                    </h4>
                    
                    <!-- Medical Dates -->
                    <?php 
                    $hasMedicalDates = !empty($pet_data['last_vet_visit']) || !empty($pet_data['next_vet_visit']) || 
                                      !empty($pet_data['rabies_vaccine_date']) || !empty($pet_data['dhpp_vaccine_date']) ||
                                      $pet_data['is_spayed_neutered'];
                    ?>
                    
                    <?php if ($hasMedicalDates): ?>
                    <div class="medical-dates-grid mb-4">
                        <?php if (!empty($pet_data['last_vet_visit'])): ?>
                            <div class="medical-date-item">
                                <small>Last Vet Visit</small>
                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet_data['last_vet_visit'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['next_vet_visit'])): ?>
                            <div class="medical-date-item">
                                <small>Next Vet Visit</small>
                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet_data['next_vet_visit'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['rabies_vaccine_date'])): ?>
                            <div class="medical-date-item">
                                <small>Rabies Vaccine</small>
                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet_data['rabies_vaccine_date'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['dhpp_vaccine_date'])): ?>
                            <div class="medical-date-item">
                                <small>DHPP Vaccine</small>
                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet_data['dhpp_vaccine_date'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pet_data['is_spayed_neutered']): ?>
                            <div class="medical-date-item">
                                <small>Spayed/Neutered</small>
                                <div class="fw-bold">
                                    Yes <?php echo !empty($pet_data['spay_neuter_date']) ? '<br><small>(' . date('M j, Y', strtotime($pet_data['spay_neuter_date'])) . ')</small>' : ''; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Medical History Details -->
                    <div class="medical-history">
                        <?php if (!empty($pet_data['previous_conditions'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-file-medical me-1"></i>Previous Conditions</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['previous_conditions'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['vaccination_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-syringe me-1"></i>Vaccination History</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['vaccination_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['surgical_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-procedures me-1"></i>Surgical History</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['surgical_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['medication_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-pills me-1"></i>Medication History</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['medication_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pet_data['has_existing_records'] && !empty($pet_data['records_location'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-clipboard-list me-1"></i>Existing Records Location</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($pet_data['records_location']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Show message if no medical history -->
                    <?php if ($pet_data): ?>
                    <div class="card-custom">
                        <div class="empty-state">
                            <i class="fa-solid fa-file-medical"></i>
                            <h5>No Medical History Recorded</h5>
                            <p class="text-muted">This pet doesn't have any medical history recorded in the system yet.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Individual Medical Records (from pet_medical_records table) -->
                <?php if (!empty($medical_records)): ?>
                <div class="card-custom">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-clipboard-list me-2"></i>Medical Visits & Services
                        <span class="badge bg-primary ms-2"><?php echo count($medical_records); ?> records</span>
                    </h4>
                    
                    <?php foreach ($medical_records as $record): ?>
                        <div class="medical-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong>
                                    <i class="fa-solid fa-calendar me-1"></i>
                                    <?php echo htmlspecialchars($record['service_type'] ?: 'Medical Service'); ?>
                                </strong>
                                <small class="text-muted">
                                    <?php echo $record['service_date'] ? date('M j, Y', strtotime($record['service_date'])) : 'Date not specified'; ?>
                                </small>
                            </div>
                            
                            <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($record['service_description']); ?></p>
                            
                            <?php if (!empty($record['veterinarian'])): ?>
                                <p class="mb-1 small"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['clinic_name'])): ?>
                                <p class="mb-1 small"><strong>Clinic:</strong> <?php echo htmlspecialchars($record['clinic_name']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['notes'])): ?>
                                <div class="alert alert-info mt-2 mb-2">
                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($record['reminder_description']) && !empty($record['reminder_due_date'])): ?>
                                <div class="alert alert-warning mt-2 mb-0">
                                    <i class="fa-solid fa-bell me-2"></i>
                                    <strong>Reminder:</strong> <?php echo htmlspecialchars($record['reminder_description']); ?>
                                    <br><small>Due: <?php echo date('M j, Y', strtotime($record['reminder_due_date'])); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Emergency & Contact Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-custom">
                            <h5 class="mb-3">
                                <i class="fas fa-phone-alt me-2"></i>Emergency Contacts
                            </h5>
                            <?php if ($pet_data && $pet_data['owner_name']): ?>
                                <h6 class="text-primary mb-3">Pet Owner</h6>
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
                    <div class="col-md-6">
                        <div class="card-custom">
                            <h5 class="mb-3">
                                <i class="fas fa-user-md me-2"></i>Veterinary Contact
                            </h5>
                            <?php if ($pet_data && $pet_data['vet_contact']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['vet_contact'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted mb-0">Veterinarian details available in full system</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="card-custom text-center">
                    <h3 class="text-primary mb-3">Access Complete Medical System</h3>
                    <p class="text-muted mb-4 lead">
                        Login for full medical history, treatment plans, prescriptions, lab results, and comprehensive health tracking.
                    </p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="<?php echo $base_url; ?>/login.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-sign-in-alt me-2"></i>System Login
                        </a>
                        <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fas fa-user-plus me-2"></i>Request Access
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw text-primary me-2"></i>
                <strong class="text-primary">PetMedQR Medical Records</strong>
            </div>
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <p class="small text-muted">Secure pet medical records management system</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
