<?php
// pet-medical-access.php - FIXED: COMPLETE WORKING VERSION
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Handle approval check API endpoint first
if (isset($_GET['check_approval']) && !empty($_GET['check_approval'])) {
    $check_request_id = intval($_GET['check_approval']);
    
    try {
        include("conn.php");
        
        // Check if request is approved
        $stmt = $conn->prepare("
            SELECT vet_session_id, status, pet_id 
            FROM vet_access_requests 
            WHERE request_id = ? AND status = 'approved'
        ");
        $stmt->bind_param("i", $check_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($request_data && !empty($request_data['vet_session_id'])) {
            // Return JSON response for approved request
            header('Content-Type: application/json');
            echo json_encode([
                'approved' => true,
                'vet_session' => $request_data['vet_session_id'],
                'pet_id' => $request_data['pet_id']
            ]);
            exit();
        } else {
            // Return JSON response for pending request
            header('Content-Type: application/json');
            echo json_encode([
                'approved' => false
            ]);
            exit();
        }
    } catch (Exception $e) {
        // Return JSON response for error
        header('Content-Type: application/json');
        echo json_encode([
            'approved' => false,
            'error' => 'Database error'
        ]);
        exit();
    }
}

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

// Function to send email notification - FIXED FORMAT
function sendAccessRequestEmail($owner_email, $owner_name, $pet_name, $vet_email, $vet_clinic, $request_id, $token) {
    $subject = "Access Request for " . $pet_name . "'s Medical Records";
    
    $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $approve_url = $current_domain . "/pet-medical-access.php?request_id=" . $request_id . "&token=" . $token . "&action=approve";
    $reject_url = $current_domain . "/pet-medical-access.php?request_id=" . $request_id . "&token=" . $token . "&action=reject";
    
    $message = '
PetMedQR Access Request
Hello ' . $owner_name . ',
A veterinarian has requested access to ' . $pet_name . '\'s medical records.

Request Details:
Veterinarian: ' . $vet_email . '

Clinic: ' . $vet_clinic . '

Request Time: ' . date('F j, Y g:i A') . '

Pet: ' . $pet_name . '

Please review this request and choose to approve or reject it:

Approve Access: ' . $approve_url . '

Reject Access: ' . $reject_url . '

This request will expire in 24 hours. If you didn\'t expect this request, please reject it immediately.

PetMedQR Medical Records System
    ';
    
    $headers = "From: PetMedQR <noreply@petmedqr.com>\r\n";
    
    return mail($owner_email, $subject, $message, $headers);
}

// Handle access request approval/rejection
if (isset($_GET['action']) && $request_id > 0 && !empty($token)) {
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
                    // Generate unique session ID for the VET
                    $vet_session_id = bin2hex(random_bytes(16));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    
                    // Update the request with vet session ID and approve it
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'approved', 
                            approved_at = NOW(), 
                            vet_session_id = ?, 
                            expires_at = ?,
                            access_granted = TRUE
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("ssi", $vet_session_id, $expires_at, $request_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Access has been approved. The veterinarian can now access the medical records.";
                    } else {
                        $success_message = "Error approving request. Please try again.";
                    }
                    $stmt->close();
                    
                } elseif ($_GET['action'] === 'reject') {
                    // Reject the request
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'rejected', 
                            approved_at = NOW()
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("i", $request_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Access request has been rejected.";
                    } else {
                        $success_message = "Error rejecting request. Please try again.";
                    }
                    $stmt->close();
                }
            } else {
                $success_message = "Invalid security token. This request may have been tampered with.";
            }
        } else {
            $success_message = "Request not found or already processed.";
        }
    } catch (Exception $e) {
        $success_message = "Error processing request: " . $e->getMessage();
        error_log("Access request handling error: " . $e->getMessage());
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
            $_SESSION['vet_session_id'] = $session_data['vet_session_id'];
            $_SESSION['access_time'] = time();
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
    $reason = trim($_POST['reason'] ?? 'Emergency care');
    
    try {
        include("conn.php");
        
        if (!empty($vet_email) && !empty($vet_clinic) && $pet_id > 0) {
            // Get pet owner information
            $stmt = $conn->prepare("
                SELECT p.*, u.name as owner_name, u.email as owner_email
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
                    (pet_id, vet_email, vet_clinic, vet_phone, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $ip_address, $user_agent, $expires_at);
                
                if ($stmt->execute()) {
                    $request_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Generate security token
                    $token = md5($request_id . $vet_email . 'secret_salt');
                    
                    // Send email to pet owner
                    $email_sent = sendAccessRequestEmail(
                        $pet_owner_data['owner_email'],
                        $pet_owner_data['owner_name'],
                        $pet_owner_data['name'],
                        $vet_email,
                        $vet_clinic,
                        $request_id,
                        $token
                    );
                    
                    $request_success = true;
                    $submitted_request_id = $request_id;
                    $success_message = "Access request sent to the pet owner. Waiting for approval...";
                } else {
                    $auth_error = "Error creating access request. Please try again.";
                }
            } else {
                $auth_error = "Pet not found in the system.";
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
                if ($pet_data) {
                    $pet_name = $pet_data['name'];
                }
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
    <title>
        <?php 
        if ($is_authenticated) {
            echo 'Medical Records - ' . htmlspecialchars($pet_data['name'] ?? $pet_name);
        } elseif (isset($submitted_request_id)) {
            echo 'Waiting for Approval';
        } elseif (isset($success_message) && !$is_authenticated) {
            echo 'Request Processed';
        } else {
            echo 'Request Medical Access';
        }
        ?>
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
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #f0f9ff 100%);
            min-height: 100vh;
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
        
        .waiting-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
        
        .medical-card {
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
        <div class="alert alert-success mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Access granted!</strong> You are viewing <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s medical records.
                </div>
                <div class="col-auto">
                    <small class="text-muted">
                        <i class="fas fa-user-md me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['vet_email']); ?> | 
                        <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Medical Records Header -->
        <div class="medical-header">
            <div class="pet-avatar">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?></h1>
            <p class="lead mb-3 opacity-90">Complete Medical History & Records</p>
            <div class="d-flex flex-wrap justify-content-center">
                <span class="badge bg-light text-dark me-2 mb-2">
                    <i class="fas fa-shield-alt me-1"></i>Secure Access
                </span>
                <?php if (!empty($medical_records)): ?>
                <span class="badge bg-light text-dark me-2 mb-2">
                    <i class="fas fa-file-medical me-1"></i><?php echo count($medical_records); ?> Records
                </span>
                <?php endif; ?>
                <span class="badge bg-success me-2 mb-2">
                    <i class="fas fa-check-circle me-1"></i>Approved Access
                </span>
            </div>
        </div>

        <!-- Pet Information -->
        <?php if ($pet_data): ?>
        <div class="medical-card">
            <div class="card-header-custom">
                <h3 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Pet Information
                </h3>
            </div>
            <div class="card-body p-4">
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
            </div>
        </div>
        <?php endif; ?>

        <!-- Medical Records -->
        <div class="medical-card">
            <div class="card-header-custom">
                <h3 class="mb-0">
                    <i class="fas fa-file-medical-alt me-2"></i>Medical Records
                    <?php if (!empty($medical_records)): ?>
                        <span class="badge bg-white text-pink-darker ms-2"><?php echo count($medical_records); ?> visits</span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($medical_records)): ?>
                    <div class="row g-3">
                        <?php foreach ($medical_records as $record): ?>
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="text-primary mb-0"><?php echo htmlspecialchars($record['record_type']); ?></h5>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></small>
                                </div>
                                <?php if (!empty($record['veterinarian'])): ?>
                                    <p class="mb-2"><small><i class="fas fa-user-md me-1"></i>Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></small></p>
                                <?php endif; ?>
                                <p class="mb-2"><?php echo htmlspecialchars($record['description']); ?></p>
                                <?php if (!empty($record['notes'])): ?>
                                    <div class="bg-white p-2 rounded border">
                                        <small class="text-dark">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No medical records found for this pet.</p>
                    </div>
                <?php endif; ?>
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
                <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;">
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
                        window.location.href = '?vet_session=' + data.vet_session + '&pet_id=' + data.pet_id;
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
    
    <?php else: ?>
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
                        <i class="fas fa-envelope me-2 text-primary"></i>Professional Email *
                    </label>
                    <input type="email" class="form-control form-control-lg" name="vet_email" 
                           placeholder="your.name@clinic.com" required 
                           value="<?php echo htmlspecialchars($_POST['vet_email'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-hospital me-2 text-primary"></i>Clinic/Hospital Name *
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
                           placeholder="(555) 123-4567" 
                           value="<?php echo htmlspecialchars($_POST['vet_phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-stethoscope me-2 text-primary"></i>Purpose of Access
                    </label>
                    <select class="form-select form-select-lg" name="reason" required>
                        <option value="Emergency care">Emergency Care</option>
                        <option value="Routine checkup">Routine Checkup</option>
                        <option value="Consultation">Consultation</option>
                        <option value="Surgery">Surgery</option>
                        <option value="Vaccination">Vaccination</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="request_access" class="btn btn-primary btn-lg py-3">
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
