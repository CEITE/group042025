<?php
// pet-medical-records.php - VET ACCESS REQUEST SYSTEM
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';
$access_token = isset($_GET['token']) ? $_GET['token'] : null;
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

// Initialize variables
$pet_data = null;
$medical_records = [];
$access_granted = false;
$request_sent = false;
$request_message = '';
$access_request = null;

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        if ($pet_id > 0 && isset($conn)) {
            // Check if access is granted via token
            if ($access_token) {
                $stmt = $conn->prepare("
                    SELECT r.*, p.name as pet_name, u.email as owner_email, u.name as owner_name 
                    FROM vet_access_requests r
                    JOIN pets p ON r.pet_id = p.pet_id
                    JOIN users u ON p.user_id = u.user_id
                    WHERE r.access_key = ? AND r.status = 'approved' 
                    AND (r.expires_at IS NULL OR r.expires_at > NOW())
                ");
                if ($stmt) {
                    $stmt->bind_param("s", $access_token);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $access_granted = true;
                        $access_request = $result->fetch_assoc();
                        
                        // Update access granted flag
                        $update_stmt = $conn->prepare("
                            UPDATE vet_access_requests 
                            SET access_granted = 1, viewed_at = NOW() 
                            WHERE request_id = ?
                        ");
                        $update_stmt->bind_param("i", $access_request['request_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    $stmt->close();
                }
            }
            
            // Fetch pet data
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone
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
                    if ($pet_data) {
                        $pet_name = $pet_data['name'];
                    }
                }
                $stmt->close();
            }
            
            // Only fetch medical records if access is granted
            if ($access_granted) {
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
            
            // Handle access request form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_access'])) {
                $vet_email = trim($_POST['vet_email'] ?? '');
                $vet_clinic = trim($_POST['vet_clinic'] ?? '');
                $vet_phone = trim($_POST['vet_phone'] ?? '');
                $purpose = trim($_POST['purpose'] ?? '');
                
                // Validate required fields
                if (!empty($vet_email) && !empty($vet_clinic) && !empty($purpose) && filter_var($vet_email, FILTER_VALIDATE_EMAIL)) {
                    // Check for recent duplicate requests (prevent spam)
                    $check_stmt = $conn->prepare("
                        SELECT request_id FROM vet_access_requests 
                        WHERE pet_id = ? AND vet_email = ? AND status = 'pending' AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ");
                    $check_stmt->bind_param("is", $pet_id, $vet_email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        // Generate access key and set expiration
                        $access_key = bin2hex(random_bytes(16));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        
                        $stmt = $conn->prepare("
                            INSERT INTO vet_access_requests 
                            (pet_id, vet_email, vet_clinic, vet_phone, access_key, status, expires_at, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                        ");
                        
                        if ($stmt) {
                            $stmt->bind_param("isssssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $access_key, $expires_at, $ip_address, $user_agent);
                            if ($stmt->execute()) {
                                $request_id = $conn->insert_id;
                                $request_sent = true;
                                
                                // Send email notification to owner
                                if ($pet_data && !empty($pet_data['owner_email'])) {
                                    sendAccessRequestEmail($pet_data, $vet_email, $vet_clinic, $vet_phone, $purpose, $access_key, $request_id);
                                }
                                
                                $request_message = "Your access request has been sent to the pet owner. You'll receive an email once approved.";
                            } else {
                                $request_message = "Error submitting request. Please try again.";
                            }
                            $stmt->close();
                        }
                    } else {
                        $request_message = "You have a pending request for this pet. Please wait for the owner to respond.";
                    }
                    $check_stmt->close();
                } else {
                    $request_message = "Please fill in all required fields with valid information.";
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $request_message = "System error. Please try again later.";
}

// Function to send access request email to pet owner
function sendAccessRequestEmail($pet_data, $vet_email, $vet_clinic, $vet_phone, $purpose, $access_key, $request_id) {
    $to = $pet_data['owner_email'];
    $subject = "Medical Records Access Request for " . $pet_data['name'];
    
    $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $approve_url = $current_domain . dirname($_SERVER['PHP_SELF']) . "/approve_vet_request.php?request_id=" . $request_id . "&token=" . $access_key . "&action=approve";
    $reject_url = $current_domain . dirname($_SERVER['PHP_SELF']) . "/approve_vet_request.php?request_id=" . $request_id . "&token=" . $access_key . "&action=reject";
    $manage_url = $current_domain . dirname($_SERVER['PHP_SELF']) . "/manage_access_requests.php";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ec4899; }
            .button { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 5px; font-weight: bold; color: white; }
            .approve { background-color: #10b981; }
            .reject { background-color: #ef4444; }
            .manage { background-color: #3b82f6; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Medical Records Access Request</h1>
                <p>For your pet: <strong>{$pet_data['name']}</strong></p>
            </div>
            <div class='content'>
                <p>Hello {$pet_data['owner_name']},</p>
                <p>A veterinarian has requested access to the medical records of your pet, <strong>{$pet_data['name']}</strong>.</p>
                
                <div class='info-box'>
                    <h3 style='margin-top: 0; color: #ec4899;'>Veterinarian Information:</h3>
                    <p><strong>Email:</strong> {$vet_email}</p>
                    <p><strong>Clinic:</strong> {$vet_clinic}</p>
                    " . (!empty($vet_phone) ? "<p><strong>Phone:</strong> {$vet_phone}</p>" : "") . "
                    <p><strong>Purpose:</strong> {$purpose}</p>
                    <p><strong>Request ID:</strong> #{$request_id}</p>
                    <p><strong>Request Time:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                </div>
                
                <h3>Quick Actions:</h3>
                <p>You can quickly approve or reject this request using the links below:</p>
                <p style='text-align: center;'>
                    <a href='{$approve_url}' class='button approve'>✓ Approve Access</a>
                    <a href='{$reject_url}' class='button reject'>✗ Reject Request</a>
                </p>
                
                <p>Or manage all access requests from your dashboard:</p>
                <p style='text-align: center;'>
                    <a href='{$manage_url}' class='button manage'>Manage All Requests</a>
                </p>
                
                <p><em>This request will expire in 30 days if not approved.</em></p>
                
                <hr>
                <p style='color: #666; font-size: 12px;'>
                    This is an automated message from PetMedQR System. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PetMedQR <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    // Send email
    mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $access_granted ? 'Medical Records - ' . htmlspecialchars($pet_data['name']) : 'Access Request - ' . htmlspecialchars($pet_name); ?>
    </title>
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
            --green: #10b981;
            --green-light: #d1fae5;
            --radius: 16px;
            --shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #f0f9ff 100%);
            min-height: 100vh;
            color: #1f2937;
        }
        
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 3rem 2rem;
            border-radius: var(--radius);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
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
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .access-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: 2rem;
        }
        
        .card-header-custom {
            background: var(--pink-light);
            border-bottom: 2px solid var(--pink);
            padding: 1.5rem;
            font-weight: 700;
            color: var(--pink-darker);
            font-size: 1.2rem;
            border-radius: var(--radius) var(--radius) 0 0 !important;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--pink);
            margin-bottom: 1.5rem;
        }
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            margin: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-header">
            <div class="pet-avatar">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?></h1>
            <p class="lead mb-3 opacity-90">Pet Medical Records</p>
            <div class="d-flex flex-wrap justify-content-center">
                <?php if ($access_granted): ?>
                    <span class="stats-badge bg-success">
                        <i class="fas fa-check-circle"></i> Access Granted
                    </span>
                <?php else: ?>
                    <span class="stats-badge">
                        <i class="fas fa-shield-alt"></i> Secure Access
                    </span>
                    <span class="stats-badge bg-warning text-dark">
                        <i class="fas fa-lock"></i> Login Required
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($access_granted): ?>
            <!-- Show medical records when access is granted -->
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Access Granted</strong> - You can now view the complete medical records.
            </div>

            <!-- Medical records content here -->
            <!-- ... (your existing medical records display code) ... -->

        <?php else: ?>
            <!-- Access Request Section -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="access-card">
                        <div class="card-header-custom">
                            <h3 class="mb-0">
                                <i class="fas fa-unlock-alt me-2"></i>Request Medical Records Access
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if ($request_sent): ?>
                                <div class="alert alert-success text-center">
                                    <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                                    <h4 class="text-success">Request Sent Successfully!</h4>
                                    <p class="mb-3"><?php echo $request_message; ?></p>
                                    <div class="badge bg-warning text-dark fs-6 p-2">
                                        <i class="fas fa-clock me-1"></i> Status: Pending Approval
                                    </div>
                                    <div class="mt-3">
                                        <p class="text-muted small">
                                            The pet owner has been notified and will review your request shortly.
                                            You'll receive an email once your access is approved.
                                        </p>
                                    </div>
                                </div>
                            <?php else: ?>
                                
                                <?php if (!empty($request_message)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $request_message; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Basic Pet Info -->
                                <?php if ($pet_data): ?>
                                <div class="info-card">
                                    <div class="row text-center">
                                        <div class="col-md-4 mb-2">
                                            <strong>Pet Name</strong>
                                            <p class="h5 text-primary mb-0"><?php echo htmlspecialchars($pet_data['name']); ?></p>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <strong>Species</strong>
                                            <p class="h5 text-primary mb-0"><?php echo htmlspecialchars($pet_data['species']); ?></p>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <strong>Breed</strong>
                                            <p class="h5 text-primary mb-0"><?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-card h-100">
                                            <i class="fas fa-info-circle text-primary mb-3" style="font-size: 2rem;"></i>
                                            <h5>Secure Access Required</h5>
                                            <p class="mb-0">Medical records contain sensitive information. The pet owner must approve access to protect privacy.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card h-100">
                                            <i class="fas fa-clock text-primary mb-3" style="font-size: 2rem;"></i>
                                            <h5>Quick Approval</h5>
                                            <p class="mb-0">Once submitted, the pet owner will receive an email and can approve access immediately.</p>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">
                                
                                <form method="POST" action="">
                                    <h5 class="mb-3 text-primary"><i class="fas fa-user-md me-2"></i>Veterinarian Information</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="vet_email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="vet_email" name="vet_email" 
                                                   value="<?php echo isset($_POST['vet_email']) ? htmlspecialchars($_POST['vet_email']) : ''; ?>" 
                                                   required placeholder="your.email@clinic.com">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="vet_clinic" class="form-label">Clinic/Hospital Name *</label>
                                            <input type="text" class="form-control" id="vet_clinic" name="vet_clinic" 
                                                   value="<?php echo isset($_POST['vet_clinic']) ? htmlspecialchars($_POST['vet_clinic']) : ''; ?>" 
                                                   required placeholder="Animal Care Clinic">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="vet_phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="vet_phone" name="vet_phone" 
                                                   value="<?php echo isset($_POST['vet_phone']) ? htmlspecialchars($_POST['vet_phone']) : ''; ?>" 
                                                   placeholder="(555) 123-4567">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="purpose" class="form-label">Purpose of Access *</label>
                                            <select class="form-select" id="purpose" name="purpose" required>
                                                <option value="">Select purpose...</option>
                                                <option value="Emergency care" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Emergency care') ? 'selected' : ''; ?>>Emergency care</option>
                                                <option value="Routine checkup" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Routine checkup') ? 'selected' : ''; ?>>Routine checkup</option>
                                                <option value="Consultation" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Consultation') ? 'selected' : ''; ?>>Consultation</option>
                                                <option value="Surgery" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                                <option value="Vaccination" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Vaccination') ? 'selected' : ''; ?>>Vaccination</option>
                                                <option value="Other" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-grid mt-4">
                                        <button type="submit" name="request_access" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Access Request
                                        </button>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            Your information is secure and will only be shared with the pet owner for approval purposes.
                                        </small>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Public Information (Limited) -->
                    <?php if ($pet_data && !$request_sent): ?>
                    <div class="access-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>About This Pet
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Pet ID:</strong> #<?php echo htmlspecialchars($pet_data['pet_id']); ?></p>
                                    <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                                    <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Age:</strong> <?php echo htmlspecialchars($pet_data['age']); ?> years</p>
                                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                    <?php if ($pet_data['owner_name']): ?>
                                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-lock me-2"></i>
                                <strong>Medical records are protected</strong> - Complete medical history, vaccination records, and treatment history require access approval.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw fa-1x text-pink-dark me-2"></i>
                <strong class="text-pink-darker">PetMedQR</strong>
            </div>
            <p class="small mb-1">&copy; <?php echo date('Y'); ?> PetMedQR Medical Records System</p>
            <p class="small text-muted">Secure QR-based pet medical records access</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple form enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>
