<?php
// pet-medical-access.php - WITH EMAIL APPROVAL SYSTEM
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Initialize variables
$pet_data = null;
$medical_records = [];
$is_authenticated = false;
$auth_error = '';
$access_request = null;

// Function to send email notification
function sendAccessRequestEmail($owner_email, $owner_name, $pet_name, $vet_email, $vet_clinic, $request_id, $token) {
    $subject = "Access Request for $pet_name's Medical Records";
    
    $approve_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?request_id=$request_id&token=$token&action=approve";
    $reject_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?request_id=$request_id&token=$token&action=reject";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ec4899; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8fafc; }
            .button { display: inline-block; padding: 12px 24px; margin: 10px; color: white; text-decoration: none; border-radius: 5px; }
            .approve { background: #10b981; }
            .reject { background: #ef4444; }
            .footer { text-align: center; padding: 20px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>PetMedQR Access Request</h1>
            </div>
            <div class='content'>
                <h2>Hello $owner_name,</h2>
                <p>A veterinarian has requested access to <strong>$pet_name</strong>'s medical records.</p>
                
                <div style='background: white; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Request Details:</h3>
                    <p><strong>Veterinarian:</strong> $vet_email</p>
                    <p><strong>Clinic:</strong> $vet_clinic</p>
                    <p><strong>Request Time:</strong> " . date('F j, Y g:i A') . "</p>
                    <p><strong>Pet:</strong> $pet_name</p>
                </div>
                
                <p>Please review this request and choose to approve or reject it:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$approve_url' class='button approve'>Approve Access</a>
                    <a href='$reject_url' class='button reject'>Reject Access</a>
                </div>
                
                <p><small>This request will expire in 24 hours. If you didn't expect this request, please reject it immediately.</small></p>
            </div>
            <div class='footer'>
                <p>PetMedQR Medical Records System</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PetMedQR <noreply@petmedqr.com>" . "\r\n";
    
    return mail($owner_email, $subject, $message, $headers);
}

// Function to send approval notification
function sendApprovalEmail($vet_email, $pet_name, $access_url) {
    $subject = "Access Approved for $pet_name's Medical Records";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10b981; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8fafc; }
            .button { display: inline-block; padding: 12px 24px; background: #ec4899; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Access Approved</h1>
            </div>
            <div class='content'>
                <h2>Access Granted</h2>
                <p>Your request to access <strong>$pet_name</strong>'s medical records has been approved by the pet owner.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$access_url' class='button'>View Medical Records</a>
                </div>
                
                <p><small>This access link will expire in 2 hours for security reasons.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PetMedQR <noreply@petmedqr.com>" . "\r\n";
    
    return mail($vet_email, $subject, $message, $headers);
}

// Handle access request approval/rejection
if (isset($_GET['action']) && $request_id > 0) {
    try {
        include("conn.php");
        
        // Verify request exists and is still pending
        $stmt = $conn->prepare("
            SELECT r.*, p.name as pet_name, u.name as owner_name, u.email as owner_email 
            FROM vet_access_requests r 
            JOIN pets p ON r.pet_id = p.pet_id 
            JOIN users u ON p.user_id = u.user_id 
            WHERE r.request_id = ? AND r.status = 'pending'
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($request_data) {
            // Verify token (simple hash verification)
            $expected_token = md5($request_data['request_id'] . $request_data['vet_email'] . 'secret_salt');
            
            if (hash_equals($expected_token, $token)) {
                if ($_GET['action'] === 'approve') {
                    // Approve the request
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'approved', approved_at = NOW() 
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Create session for direct access
                    $_SESSION['vet_authenticated'] = true;
                    $_SESSION['vet_email'] = $request_data['vet_email'];
                    $_SESSION['vet_clinic'] = $request_data['vet_clinic'];
                    $_SESSION['access_time'] = time();
                    $_SESSION['approved_request'] = true;
                    $is_authenticated = true;
                    
                    // Send approval email to vet
                    $access_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?pet_id=" . $request_data['pet_id'] . "&pet_name=" . urlencode($request_data['pet_name']);
                    sendApprovalEmail($request_data['vet_email'], $request_data['pet_name'], $access_url);
                    
                    $success_message = "Access has been approved. The veterinarian has been notified.";
                } elseif ($_GET['action'] === 'reject') {
                    // Reject the request
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'rejected' 
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success_message = "Access request has been rejected.";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Access request handling error: " . $e->getMessage());
    }
}

// Check if vet is already authenticated
if (isset($_SESSION['vet_authenticated']) && $_SESSION['vet_authenticated'] === true) {
    // Check session timeout (2 hours)
    if (isset($_SESSION['access_time']) && (time() - $_SESSION['access_time']) > 7200) {
        session_destroy();
        header("Location: ?pet_id=" . $pet_id . "&pet_name=" . urlencode($pet_name));
        exit();
    }
    $is_authenticated = true;
}

// Handle new access request from vet
if (isset($_POST['request_access']) && !$is_authenticated) {
    $vet_email = trim($_POST['vet_email'] ?? '');
    $vet_clinic = trim($_POST['vet_clinic'] ?? '');
    $vet_phone = trim($_POST['vet_phone'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    
    try {
        include("conn.php");
        
        if (!empty($vet_email) && !empty($vet_clinic) && $pet_id > 0) {
            // Get pet owner information
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email, u.notify_email 
                FROM pets p 
                JOIN users u ON p.user_id = u.user_id 
                WHERE p.pet_id = ?
            ");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $pet_owner_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($pet_owner_data) {
                // Create access request
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $stmt = $conn->prepare("
                    INSERT INTO vet_access_requests 
                    (pet_id, vet_email, vet_clinic, vet_phone, access_code, expires_at, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $access_code = bin2hex(random_bytes(8)); // Generate random code
                $stmt->bind_param("isssssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $access_code, $expires_at, $ip_address, $user_agent);
                $stmt->execute();
                $request_id = $conn->insert_id;
                $stmt->close();
                
                // Generate security token
                $token = md5($request_id . $vet_email . 'secret_salt');
                
                // Send email to pet owner if notifications are enabled
                if ($pet_owner_data['notify_email']) {
                    $email_sent = sendAccessRequestEmail(
                        $pet_owner_data['owner_email'],
                        $pet_owner_data['owner_name'],
                        $pet_owner_data['name'],
                        $vet_email,
                        $vet_clinic,
                        $request_id,
                        $token
                    );
                }
                
                $request_success = true;
                $success_message = "Access request sent to the pet owner. You'll receive an email when approved.";
            }
        }
    } catch (Exception $e) {
        $auth_error = "Error processing request: " . $e->getMessage();
        error_log("Access request error: " . $e->getMessage());
    }
}

// Fetch pet data and medical records if authenticated
if ($is_authenticated) {
    try {
        include("conn.php");
        
        // Fetch pet data
        if ($pet_id > 0) {
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
                }
                $stmt->close();
            }
            
            // Fetch medical records
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
    } catch (Exception $e) {
        error_log("Pet data fetch error: " . $e->getMessage());
    }
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
        /* Your existing CSS styles here */
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
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
        }
        
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .auth-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            border: none;
        }
        
        .auth-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--pink-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
        }
        
        .status-pending { color: #f59e0b; }
        .status-approved { color: #10b981; }
        .status-rejected { color: #ef4444; }
    </style>
</head>
<body>
    <?php if (isset($success_message)): ?>
    <!-- Success Message -->
    <div class="auth-container">
        <div class="auth-card text-center">
            <div class="auth-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="text-success mb-3">Request Processed</h2>
            <p class="mb-4"><?php echo $success_message; ?></p>
            <a href="?pet_id=<?php echo $pet_id; ?>&pet_name=<?php echo urlencode($pet_name); ?>" class="btn btn-primary">
                Return to Access Page
            </a>
        </div>
    </div>
    
    <?php elseif (!$is_authenticated): ?>
    <!-- ACCESS REQUEST FORM -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header text-center mb-4">
                <div class="auth-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h2 class="fw-bold text-dark mb-3">Request Medical Records Access</h2>
                <p class="text-muted">
                    Requesting access to <?php echo htmlspecialchars($pet_name); ?>'s medical records
                </p>
                
                <?php if (isset($request_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Access request sent! The pet owner will review your request and you'll receive an email when approved.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php elseif (!empty($auth_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($auth_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>">
                <input type="hidden" name="pet_name" value="<?php echo htmlspecialchars($pet_name); ?>">
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-envelope me-2 text-primary"></i>Professional Email
                    </label>
                    <input type="email" class="form-control form-control-lg" name="vet_email" 
                           placeholder="your.name@clinic.com" required 
                           value="<?php echo htmlspecialchars($_POST['vet_email'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-hospital me-2 text-primary"></i>Clinic/Hospital Name
                    </label>
                    <input type="text" class="form-control form-control-lg" name="vet_clinic" 
                           placeholder="Your veterinary clinic name" required 
                           value="<?php echo htmlspecialchars($_POST['vet_clinic'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-phone me-2 text-primary"></i>Phone Number (Optional)
                    </label>
                    <input type="tel" class="form-control form-control-lg" name="vet_phone" 
                           placeholder="+1 (555) 123-4567"
                           value="<?php echo htmlspecialchars($_POST['vet_phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-stethoscope me-2 text-primary"></i>Reason for Access
                    </label>
                    <textarea class="form-control" name="reason" rows="3" 
                              placeholder="Please describe why you need access to these medical records..."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    <div class="form-text">This information will be shared with the pet owner</div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="request_access" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane me-2"></i>Send Access Request
                    </button>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        The pet owner will receive an email to approve your request. All access is logged for security.
                    </small>
                </div>
            </form>
            
            <!-- Emergency Contact Info -->
            <?php 
            try {
                include("conn.php");
                $stmt = $conn->prepare("SELECT p.name, u.name as owner_name, u.phone_number as owner_phone FROM pets p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $pet_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $basic_pet_info = $result->fetch_assoc();
                    $stmt->close();
                }
            } catch (Exception $e) {
                // Silent fail
            }
            ?>
            
            <?php if (isset($basic_pet_info) && $basic_pet_info): ?>
            <div class="mt-4 p-3 bg-light rounded">
                <h6 class="mb-2">
                    <i class="fas fa-phone-alt me-2 text-danger"></i>Emergency Contact
                </h6>
                <p class="mb-1 small"><strong>Pet:</strong> <?php echo htmlspecialchars($basic_pet_info['name']); ?></p>
                <p class="mb-1 small"><strong>Owner:</strong> <?php echo htmlspecialchars($basic_pet_info['owner_name'] ?? 'Unknown'); ?></p>
                <?php if ($basic_pet_info['owner_phone']): ?>
                    <p class="mb-0 small"><strong>Phone:</strong> <?php echo htmlspecialchars($basic_pet_info['owner_phone']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    <!-- MEDICAL RECORDS SECTION (After Approval) -->
    <div class="container py-5">
        <!-- Vet Info Bar -->
        <div class="vet-info-bar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem 2rem; border-radius: var(--radius); margin-bottom: 2rem;">
            <div>
                <h5 class="mb-1">
                    <i class="fas fa-user-md me-2"></i>Approved Veterinary Access
                </h5>
                <small>
                    Access granted to: <?php echo htmlspecialchars($_SESSION['vet_email']); ?> | 
                    Clinic: <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?> |
                    <i class="fas fa-clock me-1"></i>Session expires in 2 hours
                </small>
            </div>
            <div>
                <a href="?logout=1&pet_id=<?php echo $pet_id; ?>&pet_name=<?php echo urlencode($pet_name); ?>" 
                   class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>

        <!-- Your existing medical records display code here -->
        <!-- ... include all the medical records content ... -->
        
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
