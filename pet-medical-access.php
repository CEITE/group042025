<?php
// pet-medical-access.php - DISPLAYS ALL MEDICAL RECORDS SEPARATELY
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
error_log("Pet Data for ID $pet_id: " . print_r($pet_data, true));
error_log("Medical Records count: " . count($medical_records));
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
        
        .card-header-blue {
            background: var(--blue-light);
            border-bottom: 2px solid var(--blue);
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--blue);
        }
        
        .record-item {
            background: var(--pink-light);
            border-radius: 10px;
            padding: 1.25rem;
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
        
        .records-badge {
            background: var(--blue);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .medical-content {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            white-space: pre-line;
            line-height: 1.6;
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
            <h1 class="display-6 fw-bold mb-2">
                <i class="fas fa-paw me-2"></i>
                <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Records
            </h1>
            <p class="lead mb-0">Complete Medical History & Records</p>
            <div class="mt-2">
                <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                    <span class="medical-badge">
                        <i class="fas fa-history me-1"></i>Has Medical History
                    </span>
                <?php endif; ?>
                <?php if (!empty($medical_records)): ?>
                    <span class="records-badge">
                        <i class="fas fa-file-medical me-1"></i><?php echo count($medical_records); ?> Medical Visits
                    </span>
                <?php endif; ?>
            </div>
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
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                    <p><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></p>
                                    <p><strong>Weight:</strong> <?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></p>
                                    <p><strong>Birth Date:</strong> <?php echo !empty($pet_data['birth_date']) ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Not specified'; ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($pet_data['medical_notes'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6><i class="fas fa-file-medical me-2"></i>Current Medical Notes</h6>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></div>
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

                <!-- MEDICAL RECORDS from pet_medical_records table -->
                <div class="medical-card">
                    <div class="card-header-blue">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical-alt me-2"></i>Medical Visit Records
                            <?php if (!empty($medical_records)): ?>
                                <span class="badge bg-white text-blue ms-2"><?php echo count($medical_records); ?> visits</span>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($medical_records)): ?>
                            <?php foreach ($medical_records as $record): ?>
                                <div class="record-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="text-pink-darker mb-0"><?php echo htmlspecialchars($record['record_type']); ?></h6>
                                            <?php if (!empty($record['veterinarian'])): ?>
                                                <small class="text-muted">by Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <strong class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></strong>
                                    </div>
                                    <p class="mb-2"><?php echo htmlspecialchars($record['description']); ?></p>
                                    <?php if (!empty($record['notes'])): ?>
                                        <div class="bg-white p-2 rounded border mt-2">
                                            <small class="text-dark"><strong>Additional Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-medical"></i>
                                <h5 class="text-muted">No Medical Visit Records</h5>
                                <p class="text-muted">No medical visit records found in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MEDICAL HISTORY from pets table -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History Summary
                            <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                                <span class="badge bg-white text-pink-darker ms-2">Has History</span>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <!-- Previous Conditions -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-stethoscope me-2"></i>Previous Conditions
                                </h5>
                                <?php if (!empty($pet_data['previous_conditions']) && trim($pet_data['previous_conditions']) !== ''): ?>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['previous_conditions']); ?></div>
                                <?php else: ?>
                                    <div class="empty-state py-3">
                                        <i class="fas fa-stethoscope text-muted"></i>
                                        <p class="text-muted mb-0">No previous conditions recorded</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Vaccination History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-syringe me-2"></i>Vaccination History
                                </h5>
                                <?php if (!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== ''): ?>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['vaccination_history']); ?></div>
                                <?php else: ?>
                                    <div class="empty-state py-3">
                                        <i class="fas fa-syringe text-muted"></i>
                                        <p class="text-muted mb-0">No vaccination history recorded</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Surgical History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-scissors me-2"></i>Surgical History
                                </h5>
                                <?php if (!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== ''): ?>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['surgical_history']); ?></div>
                                <?php else: ?>
                                    <div class="empty-state py-3">
                                        <i class="fas fa-scissors text-muted"></i>
                                        <p class="text-muted mb-0">No surgical history recorded</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Medication History -->
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-pills me-2"></i>Medication History
                                </h5>
                                <?php if (!empty($pet_data['medication_history']) && trim($pet_data['medication_history']) !== ''): ?>
                                    <div class="medical-content"><?php echo htmlspecialchars($pet_data['medication_history']); ?></div>
                                <?php else: ?>
                                    <div class="empty-state py-3">
                                        <i class="fas fa-pills text-muted"></i>
                                        <p class="text-muted mb-0">No medication history recorded</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Records Location -->
                            <?php if (!empty($pet_data['records_location']) && trim($pet_data['records_location']) !== ''): ?>
                            <div class="history-item">
                                <h5 class="text-dark mb-3">
                                    <i class="fas fa-archive me-2"></i>Existing Records Location
                                </h5>
                                <div class="medical-content"><?php echo htmlspecialchars($pet_data['records_location']); ?></div>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h5 class="text-muted">No Medical History Data</h5>
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
