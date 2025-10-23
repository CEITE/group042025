<?php
// pet-medical-access.php - FIXED TO SHOW ALL MEDICAL HISTORY
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

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch comprehensive pet data from pets table
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
                    
                    // DEBUG: Check what data we're getting
                    error_log("Pet Data for ID $pet_id: " . print_r($pet_data, true));
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Check if pet has medical history
function hasMedicalHistory($pet_data) {
    if (!$pet_data) return false;
    
    $hasHistory = !empty($pet_data['previous_conditions']) || 
                  !empty($pet_data['vaccination_history']) || 
                  !empty($pet_data['surgical_history']) || 
                  !empty($pet_data['medication_history']) ||
                  !empty($pet_data['last_vet_visit']) || 
                  !empty($pet_data['rabies_vaccine_date']) ||
                  !empty($pet_data['dhpp_vaccine_date']) ||
                  $pet_data['is_spayed_neutered'];
    
    error_log("Has medical history check: " . ($hasHistory ? 'YES' : 'NO'));
    if ($hasHistory) {
        error_log("Previous Conditions: " . (!empty($pet_data['previous_conditions']) ? 'YES' : 'NO'));
        error_log("Vaccination History: " . (!empty($pet_data['vaccination_history']) ? 'YES' : 'NO'));
        error_log("Surgical History: " . (!empty($pet_data['surgical_history']) ? 'YES' : 'NO'));
        error_log("Medication History: " . (!empty($pet_data['medication_history']) ? 'YES' : 'NO'));
    }
    
    return $hasHistory;
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
            --pink-light: #fff4f8;
            --blue: #4a6cf7;
            --blue-light: #e8f0fe;
            --green: #2ecc71;
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
        
        .medical-history {
            background: var(--pink-light);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--blue);
            margin-top: 1.5rem;
        }
        
        .medical-item {
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--green);
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }
        
        .medical-item strong {
            color: var(--blue);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .medical-item p {
            margin: 0;
            line-height: 1.6;
            white-space: pre-line;
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
            box-shadow: var(--shadow);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-header">
            <div class="pet-avatar" style="background: <?php echo $pet_data && strtolower($pet_data['species']) == 'dog' ? '#bbdefb' : '#f8bbd0'; ?>">
                <i class="fa-solid <?php echo $pet_data && strtolower($pet_data['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Pet Medical Records</h1>
            <p class="lead mb-0">Emergency Medical Information</p>
            <div class="mt-3">
                <?php if ($pet_data && hasMedicalHistory($pet_data)): ?>
                    <span class="badge bg-success me-2">
                        <i class="fas fa-notes-medical me-1"></i>Medical History Available
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning me-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>No Medical History
                    </span>
                <?php endif; ?>
                <span class="badge bg-primary">
                    <i class="fas fa-shield-alt me-1"></i> QR Code Access
                </span>
            </div>
        </div>

        <!-- Debug Information (Remove this in production) -->
        <?php if ($pet_data): ?>
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Pet ID: <?php echo $pet_data['pet_id']; ?><br>
            Pet Name: <?php echo htmlspecialchars($pet_data['name']); ?><br>
            Has Medical History: <?php echo hasMedicalHistory($pet_data) ? 'YES' : 'NO'; ?><br>
            Previous Conditions: <?php echo !empty($pet_data['previous_conditions']) ? 'EXISTS (' . strlen($pet_data['previous_conditions']) . ' chars)' : 'EMPTY'; ?><br>
            Vaccination History: <?php echo !empty($pet_data['vaccination_history']) ? 'EXISTS (' . strlen($pet_data['vaccination_history']) . ' chars)' : 'EMPTY'; ?><br>
            Surgical History: <?php echo !empty($pet_data['surgical_history']) ? 'EXISTS (' . strlen($pet_data['surgical_history']) . ' chars)' : 'EMPTY'; ?><br>
            Medication History: <?php echo !empty($pet_data['medication_history']) ? 'EXISTS (' . strlen($pet_data['medication_history']) . ' chars)' : 'EMPTY'; ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Pet Information -->
                <div class="card-custom">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-paw me-2"></i>Pet Information
                    </h4>
                    
                    <?php if ($pet_data): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                                <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                <p><strong>Age:</strong> <?php echo $pet_data['age'] ? htmlspecialchars($pet_data['age']) . ' years' : 'Unknown'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                <p><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></p>
                                <p><strong>Weight:</strong> <?php echo $pet_data['weight'] ? htmlspecialchars($pet_data['weight']) . ' kg' : 'Not specified'; ?></p>
                                <p><strong>Birth Date:</strong> <?php echo $pet_data['birth_date'] ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Unknown'; ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($pet_data['medical_notes'])): ?>
                            <div class="alert alert-info mt-3">
                                <h6><i class="fa-solid fa-file-medical me-2"></i>Medical Notes</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['medical_notes'])); ?></p>
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

                <!-- MEDICAL HISTORY SECTION -->
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
                    
                    <!-- Medical History Details - EXACTLY LIKE YOUR USER_PET_PROFILE -->
                    <div class="medical-history">
                        <?php if (!empty($pet_data['previous_conditions'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-file-medical me-1"></i>Previous Conditions</strong>
                                <p><?php echo nl2br(htmlspecialchars($pet_data['previous_conditions'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['vaccination_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-syringe me-1"></i>Vaccination History</strong>
                                <p><?php echo nl2br(htmlspecialchars($pet_data['vaccination_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['surgical_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-procedures me-1"></i>Surgical History</strong>
                                <p><?php echo nl2br(htmlspecialchars($pet_data['surgical_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pet_data['medication_history'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-pills me-1"></i>Medication History</strong>
                                <p><?php echo nl2br(htmlspecialchars($pet_data['medication_history'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pet_data['has_existing_records'] && !empty($pet_data['records_location'])): ?>
                            <div class="medical-item">
                                <strong><i class="fa-solid fa-clipboard-list me-1"></i>Existing Records Location</strong>
                                <p><?php echo htmlspecialchars($pet_data['records_location']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Show message if no medical history -->
                    <?php if ($pet_data): ?>
                    <div class="card-custom">
                        <div class="empty-state">
                            <i class="fa-solid fa-file-medical fa-2x mb-3"></i>
                            <h5>No Medical History Recorded</h5>
                            <p class="text-muted">This pet doesn't have any medical history recorded in the system yet.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Emergency Contact Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-custom">
                            <h5 class="mb-3">
                                <i class="fas fa-phone-alt me-2"></i>Emergency Contacts
                            </h5>
                            <?php if ($pet_data && $pet_data['owner_name']): ?>
                                <p class="mb-1"><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                <?php if ($pet_data['owner_phone']): ?>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($pet_data['owner_email']): ?>
                                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">Owner contact information not available</p>
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
                                <p class="text-muted mb-0">Veterinarian details not available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
