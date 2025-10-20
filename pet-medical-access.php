<?php
// pet-medical-access.php - ENHANCED VERSION WITH DATABASE
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
$recent_records = [];

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch pet data if connection successful
        if ($pet_id > 0 && isset($conn)) {
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email 
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
            
            // Fetch recent records
            $stmt = $conn->prepare("
                SELECT record_type, record_date, description 
                FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY record_date DESC 
                LIMIT 3
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $recent_records = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Pet Medical Records - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .medical-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .pet-info-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .emergency-alert {
            border-left: 4px solid #dc3545;
            background: #f8d7da;
        }
    </style>
</head>
<body style="background: #f8f9fa; min-height: 100vh;">
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-gradient text-white rounded-3 p-4 mb-4 text-center">
            <div class="feature-icon">
                <i class="fas fa-paw fa-2x"></i>
            </div>
            <h1 class="h2 mb-2">PetMedQR Medical Records</h1>
            <p class="mb-0 opacity-75">Professional Pet Healthcare Management System</p>
        </div>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Emergency Alert -->
                <div class="alert emergency-alert rounded-3 mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Emergency Medical Access</h5>
                            <p class="mb-0">This QR code provides access to vital pet medical information.</p>
                        </div>
                    </div>
                </div>

                <!-- Pet Information -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h4 class="mb-0 text-primary">
                            <i class="fas fa-paw me-2"></i>Pet Information
                        </h4>
                    </div>
                    <div class="card-body">
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
                                    <strong>Medical Notes:</strong> <?php echo htmlspecialchars($pet_data['medical_notes']); ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p><strong>Name:</strong> <?php echo $pet_name; ?></p>
                                <p><strong>ID:</strong> <?php echo $pet_id; ?></p>
                                <p class="text-muted">Additional details available in full system</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Medical Records -->
                <?php if (!empty($recent_records)): ?>
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-history me-2"></i>Recent Medical History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recent_records as $record): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                <div>
                                    <strong class="text-primary"><?php echo htmlspecialchars($record['record_type']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($record['description']); ?></div>
                                </div>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Features -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h4 class="mb-0 text-primary">
                            <i class="fas fa-laptop-medical me-2"></i>Our Medical System
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <i class="fas fa-heartbeat fa-2x text-danger mb-2"></i>
                                <h6>Health Tracking</h6>
                                <p class="small text-muted">Complete medical history</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <i class="fas fa-prescription-bottle-alt fa-2x text-primary mb-2"></i>
                                <h6>Medications</h6>
                                <p class="small text-muted">Prescription management</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <i class="fas fa-syringe fa-2x text-success mb-2"></i>
                                <h6>Vaccinations</h6>
                                <p class="small text-muted">Vaccine tracking</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <i class="fas fa-notes-medical fa-2x text-warning mb-2"></i>
                                <h6>Lab Results</h6>
                                <p class="small text-muted">Test results</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-body text-center py-4">
                        <h4 class="mb-3">Access Complete Medical Records</h4>
                        <p class="text-muted mb-4">
                            Login to our secure system for full medical history, treatment plans, and emergency contacts.
                        </p>
                        <a href="<?php echo $base_url; ?>/login.php" class="btn btn-primary btn-lg px-5 me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>System Login
                        </a>
                        <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fas fa-user-plus me-2"></i>Request Access
                        </a>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="alert alert-info rounded-3">
                    <h6 class="mb-2">
                        <i class="fas fa-phone-alt me-2"></i>Emergency Contact
                    </h6>
                    <p class="mb-0 small">
                        For immediate medical emergencies, contact the pet owner directly or seek veterinary assistance.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-4">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <small>Secure pet medical records management system</small>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
