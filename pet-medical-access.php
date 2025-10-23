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
$medical_history = [];

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
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #6c757d;
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
                                    <?php if ($pet_data['owner_name']): ?>
                                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($pet_data['medical_notes']): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6><i class="fas fa-file-medical me-2"></i>Medical Notes</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></p>
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
                            <i class="fas fa-file-medical me-2"></i>Medical Records
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
                <?php else: ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>Medical Records
                        </h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Medical Records Found</h5>
                        <p class="text-muted">No medical visit records have been added yet.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Medical History from pets table -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <!-- Previous Conditions -->
                            <?php if (!empty($pet_data['previous_conditions'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Previous Medical Conditions</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['previous_conditions']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Vaccination History -->
                            <?php if (!empty($pet_data['vaccination_history'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Vaccination History</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['vaccination_history']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Surgical History -->
                            <?php if (!empty($pet_data['surgical_history'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Surgical History</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['surgical_history']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Medication History -->
                            <?php if (!empty($pet_data['medication_history'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Medication History</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet_data['medication_history']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Vaccine Dates -->
                            <?php if (!empty($pet_data['rabies_vaccine_date']) || !empty($pet_data['dhpp_vaccine_date'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Vaccine Dates</h6>
                                    <?php if (!empty($pet_data['rabies_vaccine_date'])): ?>
                                        <p class="mb-1"><strong>Rabies Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['rabies_vaccine_date'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pet_data['dhpp_vaccine_date'])): ?>
                                        <p class="mb-0"><strong>DHPP Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['dhpp_vaccine_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Spay/Neuter Info -->
                            <?php if ($pet_data['is_spayed_neutered']): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Spay/Neuter Information</h6>
                                    <p class="mb-1">
                                        <strong>Status:</strong> 
                                        <?php echo ($pet_data['gender'] == 'Male' ? 'Neutered' : 'Spayed'); ?>
                                    </p>
                                    <?php if (!empty($pet_data['spay_neuter_date'])): ?>
                                        <p class="mb-0"><strong>Date:</strong> <?php echo date('M j, Y', strtotime($pet_data['spay_neuter_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Vet Visits -->
                            <?php if (!empty($pet_data['last_vet_visit']) || !empty($pet_data['next_vet_visit'])): ?>
                                <div class="history-item">
                                    <h6 class="text-dark">Veterinary Visits</h6>
                                    <?php if (!empty($pet_data['last_vet_visit'])): ?>
                                        <p class="mb-1"><strong>Last Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['last_vet_visit'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pet_data['next_vet_visit'])): ?>
                                        <p class="mb-0"><strong>Next Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['next_vet_visit'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No medical history data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <?php if ($pet_data && ($pet_data['owner_name'] || $pet_data['vet_contact'])): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-address-book me-2"></i>Contact Information
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <?php if ($pet_data['owner_name']): ?>
                            <div class="col-md-6">
                                <h6>Owner Contact</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                <?php if ($pet_data['owner_phone']): ?>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($pet_data['owner_email']): ?>
                                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($pet_data['vet_contact']): ?>
                            <div class="col-md-6">
                                <h6>Veterinarian</h6>
                                <p class="mb-0"><strong>Clinic:</strong> <?php echo htmlspecialchars($pet_data['vet_contact']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR Medical Records</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
