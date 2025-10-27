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

// ===============================
// FUNCTION: Send Email Notification
// ===============================
function sendAccessRequestEmail($owner_email, $owner_name, $pet_name, $vet_email, $vet_clinic, $request_id, $token)
{
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
                </div>
                <p>Please review this request and choose to approve or reject it:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$approve_url' class='button approve'>Approve Access</a>
                    <a href='$reject_url' class='button reject'>Reject Access</a>
                </div>
                <p><small>This request will expire in 24 hours.</small></p>
            </div>
            <div class='footer'>
                <p>PetMedQR Medical Records System</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: PetMedQR <noreply@petmedqr.com>\r\n";

    return mail($owner_email, $subject, $message, $headers);
}

// ===============================
// FIXED SECTION: AJAX CHECK HANDLER
// ===============================
if (isset($_GET['check_approval'])) {
    include("conn.php");
    $check_request_id = intval($_GET['check_approval']);

    $stmt = $conn->prepare("SELECT status, vet_session_id FROM vet_access_requests WHERE request_id = ? AND expires_at > NOW()");
    $stmt->bind_param("i", $check_request_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = ['approved' => false, 'vet_session' => null];

    if ($result && $result['status'] === 'approved' && !empty($result['vet_session_id'])) {
        $response = [
            'approved' => true,
            'vet_session' => $result['vet_session_id']
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// ===============================
// HANDLE APPROVE/REJECT ACTIONS
// ===============================
if (isset($_GET['action']) && $request_id > 0) {
    try {
        include("conn.php");
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
            $expected_token = md5($request_data['request_id'] . $request_data['vet_email'] . 'secret_salt');
            if (hash_equals($expected_token, $token)) {
                if ($_GET['action'] === 'approve') {
                    $vet_session_id = bin2hex(random_bytes(16));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    $stmt = $conn->prepare("
                        UPDATE vet_access_requests 
                        SET status = 'approved', approved_at = NOW(), vet_session_id = ?, expires_at = ?, access_granted = TRUE 
                        WHERE request_id = ?
                    ");
                    $stmt->bind_param("ssi", $vet_session_id, $expires_at, $request_id);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = "Access has been approved. The veterinarian can now access the medical records.";
                } elseif ($_GET['action'] === 'reject') {
                    $stmt = $conn->prepare("UPDATE vet_access_requests SET status = 'rejected' WHERE request_id = ?");
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

// ===============================
// CHECK VALID SESSION FOR VET
// ===============================
if (!empty($vet_session)) {
    try {
        include("conn.php");
        $stmt = $conn->prepare("
            SELECT r.*, p.name as pet_name 
            FROM vet_access_requests r 
            JOIN pets p ON r.pet_id = p.pet_id 
            WHERE r.vet_session_id = ? AND r.status = 'approved' AND r.expires_at > NOW()
        ");
        $stmt->bind_param("s", $vet_session);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($session_data) {
            $_SESSION['vet_authenticated'] = true;
            $_SESSION['vet_email'] = $session_data['vet_email'];
            $_SESSION['vet_clinic'] = $session_data['vet_clinic'];
            $_SESSION['access_time'] = time();
            $_SESSION['approved_request'] = true;
            $is_authenticated = true;

            $pet_id = $session_data['pet_id'];
            $pet_name = $session_data['pet_name'];

            $stmt = $conn->prepare("UPDATE vet_access_requests SET access_granted = FALSE WHERE request_id = ?");
            $stmt->bind_param("i", $session_data['request_id']);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Vet session verification error: " . $e->getMessage());
    }
}

// ===============================
// SESSION VALIDATION
// ===============================
if (isset($_SESSION['vet_authenticated']) && $_SESSION['vet_authenticated'] === true) {
    if (isset($_SESSION['access_time']) && (time() - $_SESSION['access_time']) > 7200) {
        session_destroy();
        header("Location: ?pet_id=" . $pet_id . "&pet_name=" . urlencode($pet_name));
        exit();
    }
    $is_authenticated = true;
}

// ===============================
// VET REQUEST ACCESS HANDLER
// ===============================
if (isset($_POST['request_access']) && !$is_authenticated) {
    $vet_email = trim($_POST['vet_email'] ?? '');
    $vet_clinic = trim($_POST['vet_clinic'] ?? '');
    $vet_phone = trim($_POST['vet_phone'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    try {
        include("conn.php");
        if (!empty($vet_email) && !empty($vet_clinic) && $pet_id > 0) {
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
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $stmt = $conn->prepare("
                    INSERT INTO vet_access_requests 
                    (pet_id, vet_email, vet_clinic, vet_phone, access_code, expires_at, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $access_code = bin2hex(random_bytes(8));
                $stmt->bind_param("isssssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $access_code, $expires_at, $ip_address, $user_agent);
                $stmt->execute();
                $request_id = $conn->insert_id;
                $stmt->close();

                $token = md5($request_id . $vet_email . 'secret_salt');

                if ($pet_owner_data['notify_email']) {
                    sendAccessRequestEmail(
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
            }
        }
    } catch (Exception $e) {
        $auth_error = "Error processing request: " . $e->getMessage();
        error_log("Access request error: " . $e->getMessage());
    }
}

// ===============================
// FETCH PET DATA & MEDICAL RECORDS
// ===============================
if ($is_authenticated) {
    try {
        include("conn.php");
        if ($pet_id > 0) {
            $stmt = $conn->prepare("SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone FROM pets p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pet_data = $result->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare("SELECT * FROM pet_medical_records WHERE pet_id = ? ORDER BY record_date DESC");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $medical_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
            --pink-gradient: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
        }
        .auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .auth-card { background: white; border-radius: 16px; box-shadow: var(--shadow-lg); padding: 3rem; max-width: 500px; width: 100%; }
        .auth-icon { width: 80px; height: 80px; border-radius: 50%; background: var(--pink-gradient); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; margin: 0 auto 1.5rem; }
        .waiting-animation { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
    </style>
</head>
<body>
<?php
// KEEP YOUR FRONTEND SECTIONS BELOW (unchanged)
?>

    <?php if (isset($success_message) && !$is_authenticated && !isset($submitted_request_id)): ?>
    <!-- Success Message for Owner (After Approval/Rejection) -->
    <div class="auth-container">
        <div class="auth-card text-center">
            <div class="auth-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="text-success mb-3">Request Processed</h2>
            <p class="mb-4"><?php echo $success_message; ?></p>
            <p class="text-muted small">The veterinarian will now have access to the medical records.</p>
        </div>
    </div>
    
    <?php elseif ($is_authenticated): ?>
    <!-- MEDICAL RECORDS SECTION (Veterinarian Access After Approval) -->
    <div class="container py-5">
        <!-- Vet Info Bar -->
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Access granted! You can now view <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s medical records.
        </div>
        
        <!-- Your medical records display code here -->
        <h1>Medical Records for <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?></h1>
        <p>Veterinarian: <?php echo htmlspecialchars($_SESSION['vet_email']); ?></p>
        <p>Clinic: <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?></p>
        
        <!-- Add your full medical records display sections here -->
        
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
            fetch('pet-medical-access.php?check_approval=<?php echo $submitted_request_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.approved && data.vet_session) {
                        // Redirect to medical records with vet session
                        window.location.href = 'pet-medical-access.php?vet_session=' + data.vet_session + '&pet_id=<?php echo $pet_id; ?>';
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

