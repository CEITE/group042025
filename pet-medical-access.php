<?php
// pet-medical-access.php - ENHANCED DESIGN WITH YOUR PINK THEME
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
        
        .info-badge {
            background: var(--pink-light);
            border: 1px solid var(--pink);
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--pink-darker);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem;
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: var(--pink-gradient);
            color: white;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow);
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
            box-shadow: var(--shadow);
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }
        
        .record-item {
            background: var(--pink-light);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--pink-dark);
            transition: all 0.3s ease;
        }
        
        .record-item:hover {
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
            background: var(--pink-gradient);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
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
            <h1 class="display-5 fw-bold mb-2">Welcome to PetMedQR</h1>
            <p class="lead mb-0 opacity-90">Professional Pet Healthcare Management System</p>
            <div class="mt-3">
                <span class="stats-badge">
                    <i class="fas fa-shield-alt"></i> Secure Access
                </span>
                <span class="stats-badge">
                    <i class="fas fa-bolt"></i> Instant Access
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Emergency Alert -->
                <div class="emergency-alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-warning fa-2x me-3"></i>
                        <div>
                            <h5 class="mb-1">Emergency Medical Access</h5>
                            <p class="mb-0">This QR code provides access to vital pet medical information for veterinary professionals.</p>
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
                                <div class="feature-icon" style="background: var(--pink-light); color: var(--pink-darker);">
                                    <i class="fas fa-paw"></i>
                                </div>
                                <h5 class="text-pink-darker mb-2"><?php echo $pet_name; ?></h5>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                                <p class="text-muted">Complete details available in full system</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Medical Records -->
                <?php if (!empty($recent_records)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Medical History
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($recent_records as $record): ?>
                            <div class="record-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-pink-darker"><?php echo htmlspecialchars($record['record_type']); ?></strong>
                                        <div class="text-muted small mt-1"><?php echo htmlspecialchars($record['description']); ?></div>
                                    </div>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Features -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-laptop-medical me-2"></i>Our Medical System Features
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row text-center">
                            <div class="col-md-3 mb-4">
                                <div class="feature-icon">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <h6>Health Tracking</h6>
                                <p class="small text-muted">Complete medical history and vital records</p>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="feature-icon">
                                    <i class="fas fa-prescription-bottle-alt"></i>
                                </div>
                                <h6>Medications</h6>
                                <p class="small text-muted">Prescription and treatment management</p>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="feature-icon">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <h6>Vaccinations</h6>
                                <p class="small text-muted">Vaccine schedule and history tracking</p>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="feature-icon">
                                    <i class="fas fa-notes-medical"></i>
                                </div>
                                <h6>Lab Results</h6>
                                <p class="small text-muted">Test results and analysis reports</p>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-4 mb-3">
                                <span class="info-badge">
                                    <i class="fas fa-clock"></i> 24/7 Access
                                </span>
                            </div>
                            <div class="col-md-4 mb-3">
                                <span class="info-badge">
                                    <i class="fas fa-shield-alt"></i> Secure
                                </span>
                            </div>
                            <div class="col-md-4 mb-3">
                                <span class="info-badge">
                                    <i class="fas fa-mobile-alt"></i> Mobile Friendly
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="medical-card">
                    <div class="card-body text-center py-5">
                        <h3 class="text-pink-darker mb-3">Access Complete Medical Records</h3>
                        <p class="text-muted mb-4 lead">
                            Login to our secure system for full medical history, treatment plans, prescriptions, and emergency contacts.
                        </p>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                            <a href="<?php echo $base_url; ?>/login.php" class="login-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>System Login
                            </a>
                            <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary btn-lg px-4" style="border-color: var(--pink-dark); color: var(--pink-dark);">
                                <i class="fas fa-user-plus me-2"></i>Request Access
                            </a>
                        </div>
                        <p class="text-muted mt-3 small">
                            For emergency access or technical support, contact system administrator
                        </p>
                    </div>
                </div>

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
                                <p class="mb-0 small">Contact information available in full system</p>
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
                                <p class="mb-0 small">Veterinarian details available in full system</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw text-pink-dark me-2"></i>
                <strong class="text-pink-darker">PetMedQR</strong>
            </div>
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <p class="small text-muted">Secure pet medical records management system | Professional Veterinary Care</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

