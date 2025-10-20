<?php
// pet-medical-access.php - MINIMAL WORKING VERSION
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple session start - remove if causing issues
@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';

// Simple base URL
$base_url = 'https://group042025.ceitesystems.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Medical Records - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: #f8f9fa; min-height: 100vh; display: flex; align-items: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <i class="fas fa-paw fa-3x mb-3"></i>
                        <h1 class="h3 mb-0">PetMedQR Medical Records</h1>
                        <p class="mb-0 opacity-75">Professional Pet Healthcare System</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Pet Information -->
                        <div class="alert alert-info">
                            <h4 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>
                                Pet Medical Access
                            </h4>
                            <p class="mb-2"><strong>Pet Name:</strong> <?php echo $pet_name; ?></p>
                            <p class="mb-0"><strong>Pet ID:</strong> <?php echo $pet_id; ?></p>
                        </div>

                        <!-- System Features -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-heartbeat me-2"></i>Features
                                        </h5>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Medical History</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Vaccination Records</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Prescriptions</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Lab Results</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">
                                            <i class="fas fa-shield-alt me-2"></i>Security
                                        </h5>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-lock text-success me-2"></i>Secure Access</li>
                                            <li><i class="fas fa-user-md text-success me-2"></i>Professional Use</li>
                                            <li><i class="fas fa-clock text-success me-2"></i>24/7 Availability</li>
                                            <li><i class="fas fa-mobile-alt text-success me-2"></i>Mobile Friendly</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Call to Action -->
                        <div class="text-center mt-4">
                            <h5 class="mb-3">Access Full Medical Records</h5>
                            <p class="text-muted mb-4">
                                Login to our secure system for complete medical history, treatment plans, and emergency contacts.
                            </p>
                            <a href="<?php echo $base_url; ?>/login.php" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-sign-in-alt me-2"></i>System Login
                            </a>
                            <div class="mt-3">
                                <small class="text-muted">
                                    Don't have access? <a href="<?php echo $base_url; ?>/register.php">Request account</a>
                                </small>
                            </div>
                        </div>

                        <!-- Emergency Notice -->
                        <div class="alert alert-warning mt-4">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>Emergency Notice
                            </h6>
                            <p class="mb-0 small">
                                For immediate medical emergencies, contact the pet owner directly or seek veterinary assistance.
                            </p>
                        </div>
                    </div>
                    
                    <div class="card-footer text-center text-muted py-3">
                        <small>
                            &copy; <?php echo date('Y'); ?> PetMedQR. Secure pet medical records system.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
