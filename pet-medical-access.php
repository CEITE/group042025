<?php
// pet-medical-access.php - FIXED: VET GETS ACCESS, NOT OWNER
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';
$vet_session = isset($_GET['vet_session']) ? $_GET['vet_session'] : '';

// Initialize variables
$pet_data = null;
$medical_records = [];
$is_authenticated = false;
$auth_error = '';
$access_request = null;
$request_success = false;
$submitted_request_id = null;
$success_message = '';

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

// Handle AJAX approval check
if (isset($_GET['check_approval']) && !empty($_GET['check_approval'])) {
    $check_request_id = intval($_GET['check_approval']);
    
    try {
        include("conn.php");
        
        $stmt = $conn->prepare("
            SELECT vet_session_id, status 
            FROM vet_access_requests 
            WHERE request_id = ? AND status = 'approved'
        ");
        $stmt->bind_param("i", $check_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($request_data && $request_data['status'] === 'approved') {
            echo json_encode([
                'approved' => true,
                'vet_session' => $request_data['vet_session_id']
            ]);
        } else {
            echo json_encode(['approved' => false]);
        }
        exit();
    } catch (Exception $e) {
        echo json_encode(['approved' => false, 'error' => $e->getMessage()]);
        exit();
    }
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
                    // Generate unique session ID for the VET (not the owner)
                    $vet_session_id = bin2hex(random_bytes(16));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    
                    // Update the request with vet session ID and approve it
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'approved', 
                            approved_at = NOW(), 
                            vet_session_id = ?, 
                            expires_at = ?
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("ssi", $vet_session_id, $expires_at, $request_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Show success message to owner (DO NOT create session for owner)
                    $success_message = "Access has been approved. The veterinarian can now access the medical records.";
                    
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
            } else {
                $success_message = "Invalid security token.";
            }
        } else {
            $success_message = "Request not found or already processed.";
        }
    } catch (Exception $e) {
        error_log("Access request handling error: " . $e->getMessage());
        $success_message = "Error processing request.";
    }
}

// Check if vet is accessing with valid session ID
if (!empty($vet_session)) {
    try {
        include("conn.php");
        
        // Verify vet session is valid and not expired
        $stmt = $conn->prepare("
            SELECT r.*, p.name as pet_name, p.pet_id
            FROM vet_access_requests r 
            JOIN pets p ON r.pet_id = p.pet_id 
            WHERE r.vet_session_id = ? AND r.status = 'approved' AND r.expires_at > NOW()
        ");
        $stmt->bind_param("s", $vet_session);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($session_data) {
            // Valid vet session - create session for VET
            $_SESSION['vet_authenticated'] = true;
            $_SESSION['vet_email'] = $session_data['vet_email'];
            $_SESSION['vet_clinic'] = $session_data['vet_clinic'];
            $_SESSION['access_time'] = time();
            $_SESSION['vet_session_id'] = $vet_session;
            $is_authenticated = true;
            
            // Set pet data
            $pet_id = $session_data['pet_id'];
            $pet_name = $session_data['pet_name'];
        }
    } catch (Exception $e) {
        error_log("Vet session verification error: " . $e->getMessage());
    }
}

// Check if vet is already authenticated via session
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
                    (pet_id, vet_email, vet_clinic, vet_phone, reason, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $reason, $ip_address, $user_agent, $expires_at);
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
                $submitted_request_id = $request_id;
                $success_message = "Access request sent to the pet owner. Waiting for approval...";
            } else {
                $auth_error = "Pet not found or invalid pet ID.";
            }
        } else {
            $auth_error = "Please fill in all required fields.";
        }
    } catch (Exception $e) {
        $auth_error = "Error processing request: " . $e->getMessage();
        error_log("Access request error: " . $e->getMessage());
    }
}

// Fetch pet data and medical records if authenticated
if ($is_authenticated && $pet_id > 0) {
    try {
        include("conn.php");
        
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
        
        .waiting-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .medical-record-card {
            border-left: 4px solid var(--pink-dark);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php if (isset($success_message) && !$is_authenticated && !isset($submitted_request_id)): ?>
    <!-- Success Message for Owner (After Approval/Rejection) -->
    <div class="auth-container">
        <div class="auth-card text-center">
            <div class="auth-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="text-success mb-3">Request Processed</h2>
            <p class="mb-4"><?php echo $success_message; ?></p>
            <p class="text-muted small">You can close this window.</p>
        </div>
    </div>
    
    <?php elseif ($is_authenticated): ?>
    <!-- MEDICAL RECORDS SECTION (Veterinarian Access After Approval) -->
    <div class="container py-5">
        <!-- Vet Info Bar -->
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-check-circle me-2"></i>
                Access granted! You can now view <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s medical records.
            </div>
            <div class="text-muted small">
                <i class="fas fa-user-md me-1"></i>
                <?php echo htmlspecialchars($_SESSION['vet_email']); ?> | 
                <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?>
            </div>
        </div>
        
        <!-- Pet Information -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-paw me-2"></i>
                            Medical Records for <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($pet_data): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Pet Information</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species'] ?? 'Not specified'); ?></p>
                                <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?? 'Not specified'); ?></p>
                                <p><strong>Date of Birth:</strong> <?php echo !empty($pet_data['date_of_birth']) ? date('F j, Y', strtotime($pet_data['date_of_birth'])) : 'Not specified'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Owner Information</h5>
                                <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name'] ?? 'Not specified'); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email'] ?? 'Not specified'); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone'] ?? 'Not specified'); ?></p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Pet information not found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Medical Records -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-file-medical me-2"></i>
                            Medical History
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($medical_records)): ?>
                            <?php foreach ($medical_records as $record): ?>
                            <div class="card medical-record-card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($record['record_type']); ?></h5>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($record['description'])); ?></p>
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span><i class="fas fa-calendar me-1"></i> <?php echo date('F j, Y', strtotime($record['record_date'])); ?></span>
                                        <span><i class="fas fa-user-md me-1"></i> <?php echo htmlspecialchars($record['veterinarian'] ?? 'Unknown Vet'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No medical records found for this pet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif (isset($submitted_request_id)): ?>
    <!-- WAITING FOR APPROVAL PAGE (Vet sees this after submitting request) -->
    <div class="auth-container">
        <div class="auth-card text-center">
            <div class="auth-icon waiting-animation" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-clock"></i>
            </div>
            <h2 class="text-warning mb-3">Waiting for Approval</h2>
            <p class="mb-4">Your access request has been sent to the pet owner. This page will automatically refresh and grant access once approved.</p>
            
            <div class="mb-4">
                <div class="spinner-border text-warning" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            
            <p class="text-muted small mb-4">
                <i class="fas fa-sync-alt me-1"></i>
                Auto-refreshing every 3 seconds...
            </p>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Please keep this page open. It will automatically redirect when approved.
            </div>
        </div>
    </div>
    
    <script>
        // Auto-check for approval every 3 seconds
        function checkApproval() {
            fetch('?check_approval=<?php echo $submitted_request_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.approved && data.vet_session) {
                        // Redirect to medical records with vet session
                        window.location.href = '?vet_session=' + data.vet_session + '&pet_id=<?php echo $pet_id; ?>';
                    } else {
                        // Continue waiting
                        setTimeout(checkApproval, 3000);
                    }
                })
                .catch(error => {
                    console.error('Error checking approval:', error);
                    setTimeout(checkApproval, 3000);
                });
        }
        
        // Start checking for approval
        setTimeout(checkApproval, 3000);
    </script>
    
    <?php elseif (!$is_authenticated): ?>
    <!-- ACCESS REQUEST FORM (Initial QR Code Scan) -->
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
                
                <?php if (!empty($auth_error)): ?>
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
                           placeholder="Your clinic phone number" 
                           value="<?php echo htmlspecialchars($_POST['vet_phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-stethoscope me-2 text-primary"></i>Reason for Access (Optional)
                    </label>
                    <textarea class="form-control" name="reason" rows="3" 
                              placeholder="Brief reason for accessing these records..."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="request_access" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane me-2"></i>Send Access Request
                    </button>
                </div>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        The pet owner will receive an email to approve your request. You'll get immediate access once approved.
                    </small>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
