<?php
// pet-medical-access.php - DISPLAYS ALL MEDICAL RECORDS
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
    error_log("Database error: " . $e->getMessage());
}

// Debug: Check what data we have
error_log("Pet Data: " . print_r($pet_data, true));
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            color: #1f2937;
        }
        
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .medical-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .card-header-custom {
            background: var(--pink-light);
            border-bottom: 2px solid var(--pink);
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--pink-darker);
        }
        
        .record-item {
            background: var(--pink-light);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--pink-dark);
        }
        
        .history-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #6c757d;
        }
        
        .medical-badge {
            background: var(--pink-darker);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-header">
            <h1 class="display-6 fw-bold mb-2">
                <i class="fas fa-paw me-2"></i>
                <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Records
            </h1>
            <p class="lead mb-0">Complete Medical History</p>
            <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                <span class="medical-badge mt-2">
                    <i class="fas fa-check-circle me-1"></i>Has Medical History
                </span>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Pet Information -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Pet Information
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                    <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                                    <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                    <p><strong>Age:</strong> <?php echo htmlspecialchars($pet_data['age']); ?> years</p>
                                    <p><strong>Pet ID:</strong> <?php echo htmlspecialchars($pet_data['pet_id']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                    <p><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></p>
                                    <p><strong>Weight:</strong> <?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></p>
                                    <p><strong>Birth Date:</strong> <?php echo !empty($pet_data['birth_date']) ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Not specified'; ?></p>
                                    <?php if ($pet_data['owner_name']): ?>
                                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($pet_data['medical_notes'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6><i class="fas fa-file-medical me-2"></i>Current Medical Notes</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['medical_notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <h5><?php echo $pet_name; ?></h5>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Records from pet_medical_records table -->
                <?php if (!empty($medical_records)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>Medical Visit Records
                            <span class="badge bg-primary ms-2"><?php echo count($medical_records); ?> records</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($medical_records as $record): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="text-pink-darker mb-0"><?php echo htmlspecialchars($record['record_type']); ?></h6>
                                    <strong class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></strong>
                                </div>
                                <p class="mb-2"><?php echo htmlspecialchars($record['description']); ?></p>
                                <?php if (!empty($record['veterinarian'])): ?>
                                    <p class="mb-1 small"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($record['notes'])): ?>
                                    <p class="mb-0 small"><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Medical History from pets table -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History Summary
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <!-- Debug: Show what data we have -->
                            <?php 
                            $hasMedicalHistory = false;
                            if (!empty($pet_data['previous_conditions'])) $hasMedicalHistory = true;
                            if (!empty($pet_data['vaccination_history'])) $hasMedicalHistory = true;
                            if (!empty($pet_data['surgical_history'])) $hasMedicalHistory = true;
                            if (!empty($pet_data['medication_history'])) $hasMedicalHistory = true;
                            ?>

                            <!-- Previous Conditions -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-stethoscope me-2"></i>Previous Conditions
                                </h5>
                                <?php if (!empty($pet_data['previous_conditions'])): ?>
                                    <div class="bg-white p-3 rounded border">
                                        <?php echo nl2br(htmlspecialchars($pet_data['previous_conditions'])); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted fst-italic">No previous conditions recorded.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Vaccination History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-syringe me-2"></i>Vaccination History
                                </h5>
                                <?php if (!empty($pet_data['vaccination_history'])): ?>
                                    <div class="bg-white p-3 rounded border">
                                        <?php echo nl2br(htmlspecialchars($pet_data['vaccination_history'])); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted fst-italic">No vaccination history recorded.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Surgical History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-scissors me-2"></i>Surgical History
                                </h5>
                                <?php if (!empty($pet_data['surgical_history'])): ?>
                                    <div class="bg-white p-3 rounded border">
                                        <?php echo nl2br(htmlspecialchars($pet_data['surgical_history'])); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted fst-italic">No surgical history recorded.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Medication History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-pills me-2"></i>Medication History
                                </h5>
                                <?php if (!empty($pet_data['medication_history'])): ?>
                                    <div class="bg-white p-3 rounded border">
                                        <?php echo nl2br(htmlspecialchars($pet_data['medication_history'])); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted fst-italic">No medication history recorded.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Additional Medical Information -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-calendar-alt me-2"></i>Important Dates & Information
                                </h5>
                                <div class="row">
                                    <?php if (!empty($pet_data['rabies_vaccine_date'])): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong>Rabies Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['rabies_vaccine_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet_data['dhpp_vaccine_date'])): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong>DHPP Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['dhpp_vaccine_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet_data['last_vet_visit'])): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong>Last Vet Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['last_vet_visit'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet_data['next_vet_visit'])): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong>Next Vet Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['next_vet_visit'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet_data['is_spayed_neutered']): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong>Spayed/Neutered:</strong> Yes
                                        <?php if (!empty($pet_data['spay_neuter_date'])): ?>
                                            (<?php echo date('M j, Y', strtotime($pet_data['spay_neuter_date'])); ?>)
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Records Location -->
                            <?php if (!empty($pet_data['records_location'])): ?>
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-archive me-2"></i>Existing Records Location
                                </h5>
                                <div class="bg-white p-3 rounded border">
                                    <?php echo nl2br(htmlspecialchars($pet_data['records_location'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No medical history data available for this pet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-address-book me-2"></i>Contact Information
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Owner Contact</h6>
                                <?php if ($pet_data && $pet_data['owner_name']): ?>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php if ($pet_data['owner_phone']): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($pet_data['owner_email']): ?>
                                        <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted">Owner information not available</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Veterinarian Contact</h6>
                                <?php if ($pet_data && $pet_data['vet_contact']): ?>
                                    <p class="mb-0"><strong>Contact:</strong> <?php echo htmlspecialchars($pet_data['vet_contact']); ?></p>
                                <?php else: ?>
                                    <p class="text-muted">Veterinarian contact not specified</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR Medical Records</p>
            <p class="small text-muted">Last updated: <?php echo $pet_data && $pet_data['medical_history_updated_at'] ? date('M j, Y g:i A', strtotime($pet_data['medical_history_updated_at'])) : 'Unknown'; ?></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
