<?php
session_start();
include("conn.php");

// Get pet ID from URL
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? urldecode($_GET['pet_name']) : 'Unknown Pet';

// Fetch pet data from database
$pet_data = null;
if ($pet_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
        FROM pets p 
        LEFT JOIN users u ON p.user_id = u.user_id 
        WHERE p.pet_id = ?
    ");
    $stmt->bind_param("i", $pet_id);
    $stmt->execute();
    $pet_data = $stmt->get_result()->fetch_assoc();
}

// Get recent medical records
$recent_records = [];
if ($pet_id > 0) {
    $stmt = $conn->prepare("
        SELECT record_type, record_date, description 
        FROM pet_medical_records 
        WHERE pet_id = ? 
        ORDER BY record_date DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $pet_id);
    $stmt->execute();
    $recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ✅ FIXED: Use your actual domain directly
$base_url = 'https://group042025.ceitesystems.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Medical Records Access - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #ffd6e7;
            --pink-dark: #ec4899;
            --pink-darker: #db2777;
            --pink-gradient: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            --pink-light: #fdf2f8;
        }
        body {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .medical-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 2rem auto;
            max-width: 1200px;
            border: 1px solid rgba(255,255,255,0.8);
        }
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
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
        .medical-body {
            padding: 3rem;
        }
        .system-preview {
            background: var(--pink-light);
            border-radius: 15px;
            padding: 2.5rem;
            margin: 2.5rem 0;
            border: 2px dashed var(--pink-dark);
            position: relative;
        }
        .login-btn {
            background: var(--pink-gradient);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.4);
        }
        .pet-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .feature-list li:before {
            content: "✓";
            color: var(--pink-darker);
            font-weight: bold;
            margin-right: 12px;
            font-size: 1.2rem;
        }
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--pink-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .record-item {
            background: var(--pink-light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid var(--pink-dark);
        }
        .security-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .medical-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .contact-info {
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="medical-card">
        <div class="medical-header">
            <div class="pet-avatar">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="display-5 fw-bold">Welcome to PetMedQR</h1>
            <p class="lead mb-0 opacity-90">Professional Pet Healthcare Management System</p>
        </div>
        
        <div class="medical-body">
            <!-- Emergency Alert -->
            <div class="medical-alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle text-warning fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Emergency Medical Access</h5>
                        <p class="mb-0">This QR code provides access to vital pet medical information for veterinary professionals.</p>
                    </div>
                </div>
            </div>

            <!-- Pet Information Section -->
            <?php if ($pet_data): ?>
            <div class="row mb-5">
                <div class="col-md-6">
                    <div class="info-card">
                        <h4><i class="fas fa-paw me-2 text-primary"></i>Pet Information</h4>
                        <div class="row mt-3">
                            <div class="col-5"><strong>Name:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['name']); ?></div>
                            
                            <div class="col-5"><strong>Species:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['species']); ?></div>
                            
                            <div class="col-5"><strong>Breed:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></div>
                            
                            <div class="col-5"><strong>Age:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['age']); ?> years</div>
                            
                            <div class="col-5"><strong>Gender:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></div>
                            
                            <div class="col-5"><strong>Color:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></div>
                            
                            <div class="col-5"><strong>Weight:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h4><i class="fas fa-user me-2 text-primary"></i>Owner Information</h4>
                        <div class="row mt-3">
                            <div class="col-5"><strong>Owner:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['owner_name']); ?></div>
                            
                            <?php if ($pet_data['owner_email']): ?>
                            <div class="col-5"><strong>Email:</strong></div>
                            <div class="col-7">
                                <a href="mailto:<?php echo htmlspecialchars($pet_data['owner_email']); ?>">
                                    <?php echo htmlspecialchars($pet_data['owner_email']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($pet_data['owner_phone']): ?>
                            <div class="col-5"><strong>Phone:</strong></div>
                            <div class="col-7">
                                <a href="tel:<?php echo htmlspecialchars($pet_data['owner_phone']); ?>">
                                    <?php echo htmlspecialchars($pet_data['owner_phone']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-5"><strong>Veterinarian:</strong></div>
                            <div class="col-7"><?php echo htmlspecialchars($pet_data['vet_contact'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>

                    <!-- Medical Notes -->
                    <?php if ($pet_data['medical_notes']): ?>
                    <div class="info-card mt-3">
                        <h4><i class="fas fa-file-medical me-2 text-primary"></i>Medical Notes</h4>
                        <p class="mt-2"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Medical Records -->
            <?php if (!empty($recent_records)): ?>
            <div class="info-card">
                <h4><i class="fas fa-history me-2 text-primary"></i>Recent Medical History</h4>
                <div class="mt-3">
                    <?php foreach ($recent_records as $record): ?>
                    <div class="record-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="text-primary"><?php echo htmlspecialchars($record['record_type']); ?></strong>
                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($record['description']); ?></div>
                            </div>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Pet information not found or access denied.
                <br><small>Please check the pet ID or contact system administrator.</small>
            </div>
            <?php endif; ?>

            <!-- Contact Information for Emergencies -->
            <div class="contact-info">
                <h4><i class="fas fa-phone-alt me-2 text-primary"></i>Emergency Contact</h4>
                <p class="mb-2">If this is a medical emergency, please contact the pet owner immediately using the information above.</p>
                <p class="mb-0"><strong>For urgent veterinary assistance, contact the pet's veterinarian directly.</strong></p>
            </div>

            <!-- System Design Preview -->
            <div class="system-preview text-center">
                <span class="security-badge">
                    <i class="fas fa-shield-alt"></i> Secure Access
                </span>
                <h3 class="mt-3"><i class="fas fa-star me-2 text-warning"></i>Our Medical Record System</h3>
                <p class="lead">Professional interface for efficient pet healthcare management</p>
                
                <!-- System Preview -->
                <div style="background: white; padding: 2rem; border-radius: 12px; border: 2px solid var(--pink); margin: 2rem 0;">
                    <div class="row text-start">
                        <div class="col-md-6">
                            <h5><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Overview</h5>
                            <p class="text-muted">Quick access to vital pet information and medical history</p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-file-medical me-2 text-success"></i>Medical Records</h5>
                            <p class="text-muted">Comprehensive health tracking and treatment plans</p>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded">
                        <i class="fas fa-laptop-medical fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Professional Medical Record System Interface</p>
                        <div class="mt-3">
                            <span class="badge bg-primary me-2">Patient Profiles</span>
                            <span class="badge bg-success me-2">Treatment History</span>
                            <span class="badge bg-info me-2">Lab Results</span>
                            <span class="badge bg-warning me-2">Prescriptions</span>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-3 mb-3">
                        <div class="p-3">
                            <i class="fas fa-heartbeat fa-2x text-danger mb-2"></i>
                            <h6>Health Tracking</h6>
                            <p class="small text-muted">Comprehensive medical history</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3">
                            <i class="fas fa-prescription-bottle-alt fa-2x text-primary mb-2"></i>
                            <h6>Medications</h6>
                            <p class="small text-muted">Prescription management</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3">
                            <i class="fas fa-syringe fa-2x text-success mb-2"></i>
                            <h6>Vaccinations</h6>
                            <p class="small text-muted">Vaccine schedule tracking</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3">
                            <i class="fas fa-notes-medical fa-2x text-warning mb-2"></i>
                            <h6>Lab Results</h6>
                            <p class="small text-muted">Test results and analysis</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-5">
                <h4>Ready to Access the Full System?</h4>
                <p class="text-muted mb-4">Click below to log in to our complete medical records system for full access to all features</p>
                <a href="<?php echo $base_url; ?>/login.php" class="login-btn me-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Access System Login
                </a>
                <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary">
                    <i class="fas fa-user-plus me-2"></i>Request Access
                </a>
                <p class="text-muted mt-3 small">
                    For emergency access or technical support, contact system administrator
                </p>
            </div>

            <!-- Features List -->
            <div class="row mt-5">
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-list-check me-2 text-primary"></i>System Features</h5>
                        <ul class="feature-list mt-3">
                            <li>Complete medical history tracking</li>
                            <li>Vaccination and medication records</li>
                            <li>Lab results and diagnostic imaging</li>
                            <li>Appointment scheduling</li>
                            <li>Multi-veterinarian access</li>
                            <li>Emergency contact information</li>
                            <li>Prescription management</li>
                            <li>Treatment plan tracking</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-benefits me-2 text-success"></i>Benefits</h5>
                        <ul class="feature-list mt-3">
                            <li>24/7 secure access to records</li>
                            <li>Seamless clinic-to-clinic transfers</li>
                            <li>Emergency situation readiness</li>
                            <li>Mobile-friendly interface</li>
                            <li>Automated reminder system</li>
                            <li>HIPAA compliant data protection</li>
                            <li>Real-time updates</li>
                            <li>Comprehensive audit trails</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center text-muted mt-4 pb-4">
        <p>&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
        <p class="small">Secure pet medical records management system | Professional Veterinary Care</p>
        <p class="small">System: <?php echo $base_url; ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>