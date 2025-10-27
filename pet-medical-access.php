<?php
// pet-medical-access.php - UPDATED FOR YOUR DATABASE STRUCTURE
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Handle approval check
if (isset($_GET['check_approval']) && !empty($_GET['check_approval'])) {
    include("conn.php");
    $check_request_id = intval($_GET['check_approval']);
    
    $stmt = $conn->prepare("SELECT vet_session_id, status, pet_id FROM vet_access_requests WHERE request_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $check_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request_data = $result->fetch_assoc();
    $stmt->close();
    
    header('Content-Type: application/json');
    if ($request_data && !empty($request_data['vet_session_id'])) {
        echo json_encode(['approved' => true, 'vet_session' => $request_data['vet_session_id'], 'pet_id' => $request_data['pet_id']]);
    } else {
        echo json_encode(['approved' => false]);
    }
    exit();
}

// Get parameters
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';
$vet_session = isset($_GET['vet_session']) ? $_GET['vet_session'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Initialize variables
$pet_data = null;
$medical_records = [];
$is_authenticated = false;
$auth_error = '';
$request_success = false;
$submitted_request_id = null;
$success_message = '';

// Handle approval/rejection from email
if ($request_id > 0 && !empty($token) && !empty($action)) {
    include("conn.php");
    
    $stmt = $conn->prepare("SELECT r.*, p.name as pet_name FROM vet_access_requests r JOIN pets p ON r.pet_id = p.pet_id WHERE r.request_id = ? AND r.status = 'pending'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($request_data) {
        $expected_token = md5($request_data['request_id'] . $request_data['vet_email'] . 'secret_salt');
        
        if (hash_equals($expected_token, $token)) {
            if ($action === 'approve') {
                $vet_session_id = bin2hex(random_bytes(16));
                $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
                
                $stmt = $conn->prepare("UPDATE vet_access_requests SET status = 'approved', approved_at = NOW(), vet_session_id = ?, expires_at = ? WHERE request_id = ?");
                $stmt->bind_param("ssi", $vet_session_id, $expires_at, $request_id);
                $stmt->execute();
                $stmt->close();
                
                $success_message = "Access approved! The veterinarian can now view the medical records.";
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE vet_access_requests SET status = 'rejected', approved_at = NOW() WHERE request_id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $stmt->close();
                
                $success_message = "Access request rejected.";
            }
        }
    }
}

// Check vet session
if (!empty($vet_session)) {
    include("conn.php");
    
    $stmt = $conn->prepare("SELECT r.*, p.name as pet_name, p.pet_id FROM vet_access_requests r JOIN pets p ON r.pet_id = p.pet_id WHERE r.vet_session_id = ? AND r.status = 'approved' AND r.expires_at > NOW()");
    $stmt->bind_param("s", $vet_session);
    $stmt->execute();
    $session_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($session_data) {
        $_SESSION['vet_authenticated'] = true;
        $_SESSION['vet_email'] = $session_data['vet_email'];
        $_SESSION['vet_clinic'] = $session_data['vet_clinic'];
        $_SESSION['access_time'] = time();
        $is_authenticated = true;
        $pet_id = $session_data['pet_id'];
        $pet_name = $session_data['pet_name'];
    }
}

// Check existing session
if (isset($_SESSION['vet_authenticated']) && $_SESSION['vet_authenticated'] === true) {
    if (isset($_SESSION['access_time']) && (time() - $_SESSION['access_time']) > 7200) {
        session_destroy();
    } else {
        $is_authenticated = true;
    }
}

// Handle new access request
if (isset($_POST['request_access']) && !$is_authenticated) {
    $vet_email = trim($_POST['vet_email'] ?? '');
    $vet_clinic = trim($_POST['vet_clinic'] ?? '');
    $vet_phone = trim($_POST['vet_phone'] ?? '');
    
    if (!empty($vet_email) && !empty($vet_clinic) && $pet_id > 0) {
        include("conn.php");
        
        // Get pet owner info
        $stmt = $conn->prepare("SELECT p.*, u.name as owner_name, u.email as owner_email FROM pets p JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
        $stmt->bind_param("i", $pet_id);
        $stmt->execute();
        $pet_owner_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($pet_owner_data) {
            // Create request - USING YOUR ACTUAL COLUMN NAMES
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Use access_key instead of access_code since that's what your table has
            $access_key = bin2hex(random_bytes(16));
            
            $stmt = $conn->prepare("INSERT INTO vet_access_requests (pet_id, vet_email, vet_clinic, vet_phone, access_key, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $pet_id, $vet_email, $vet_clinic, $vet_phone, $access_key, $ip_address, $user_agent, $expires_at);
            
            if ($stmt->execute()) {
                $request_id = $conn->insert_id;
                $stmt->close();
                
                // Generate token and send email
                $token = md5($request_id . $vet_email . 'secret_salt');
                $email_sent = sendAccessRequestEmail($pet_owner_data, $vet_email, $vet_clinic, $request_id, $token);
                
                $request_success = true;
                $submitted_request_id = $request_id;
                $success_message = "Request sent! Waiting for owner approval...";
                
                if (!$email_sent) {
                    $success_message .= " (Note: Email notification failed to send)";
                }
            } else {
                $auth_error = "Database error: Could not create access request.";
            }
        } else {
            $auth_error = "Pet not found or no owner information available.";
        }
    } else {
        $auth_error = "Please fill in all required fields.";
    }
}

// Fetch data if authenticated
if ($is_authenticated && $pet_id > 0) {
    include("conn.php");
    
    // Get pet data
    $stmt = $conn->prepare("SELECT p.*, u.name as owner_name FROM pets p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
    $stmt->bind_param("i", $pet_id);
    $stmt->execute();
    $pet_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get medical records
    $stmt = $conn->prepare("SELECT * FROM pet_medical_records WHERE pet_id = ? ORDER BY record_date DESC");
    $stmt->bind_param("i", $pet_id);
    $stmt->execute();
    $medical_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Email function
function sendAccessRequestEmail($pet_owner_data, $vet_email, $vet_clinic, $request_id, $token) {
    $to = $pet_owner_data['owner_email'];
    $subject = "Access Request for " . $pet_owner_data['name'] . "'s Medical Records";
    
    $domain = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $approve_url = $domain . "/pet-medical-access.php?request_id=" . $request_id . "&token=" . $token . "&action=approve";
    $reject_url = $domain . "/pet-medical-access.php?request_id=" . $request_id . "&token=" . $token . "&action=reject";
    
    $message = "
PetMedQR Access Request
Hello " . $pet_owner_data['owner_name'] . ",
A veterinarian has requested access to " . $pet_owner_data['name'] . "'s medical records.

Request Details:
Veterinarian: " . $vet_email . "

Clinic: " . $vet_clinic . "

Request Time: " . date('F j, Y g:i A') . "

Pet: " . $pet_owner_data['name'] . "

Please review this request and choose to approve or reject it:

Approve Access: " . $approve_url . "

Reject Access: " . $reject_url . "

This request will expire in 24 hours.

PetMedQR Medical Records System
    ";
    
    $headers = "From: PetMedQR <noreply@petmedqr.com>\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Medical Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; min-height: 100vh; }
        .auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .auth-card { background: white; border-radius: 10px; padding: 2rem; max-width: 500px; width: 100%; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php if (isset($success_message) && !$is_authenticated && !isset($submitted_request_id)): ?>
        <!-- Owner sees this after approving/rejecting -->
        <div class="auth-container">
            <div class="auth-card text-center">
                <h3 class="text-success mb-3">✅ Request Processed</h3>
                <p><?php echo $success_message; ?></p>
                <a href="javascript:window.close()" class="btn btn-secondary">Close Window</a>
            </div>
        </div>
    
    <?php elseif ($is_authenticated): ?>
        <!-- Vet sees medical records after approval -->
        <div class="container py-4">
            <div class="alert alert-success">
                <strong>✅ Access granted!</strong> You are viewing <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s medical records.
                <br><small>Veterinarian: <?php echo htmlspecialchars($_SESSION['vet_email']); ?> | Clinic: <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?></small>
            </div>
            
            <?php if ($pet_data): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4>Pet Information</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                            <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                            <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Age:</strong> <?php echo htmlspecialchars($pet_data['age']); ?> years</p>
                            <p><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                            <p><strong>Weight:</strong> <?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Medical Records</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($medical_records)): ?>
                        <?php foreach ($medical_records as $record): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="text-primary"><?php echo htmlspecialchars($record['record_type']); ?></h5>
                                <span class="badge bg-secondary"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></span>
                            </div>
                            <?php if (!empty($record['veterinarian'])): ?>
                                <p class="mb-2"><small><strong>Veterinarian:</strong> Dr. <?php echo htmlspecialchars($record['veterinarian']); ?></small></p>
                            <?php endif; ?>
                            <p class="mb-2"><?php echo htmlspecialchars($record['description']); ?></p>
                            <?php if (!empty($record['notes'])): ?>
                                <div class="bg-light p-2 rounded">
                                    <small><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No medical records found for this pet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    
    <?php elseif (isset($submitted_request_id)): ?>
        <!-- Vet sees waiting page -->
        <div class="auth-container">
            <div class="auth-card text-center">
                <h3 class="text-warning mb-3">⏳ Waiting for Approval</h3>
                <p>Your request has been sent to the pet owner. Please wait for approval...</p>
                <div class="spinner-border text-warning mb-3" style="width: 3rem; height: 3rem;"></div>
                <p class="small text-muted">Auto-checking every 3 seconds...</p>
                <div class="alert alert-info mt-3">
                    <small>Request ID: <?php echo $submitted_request_id; ?></small>
                </div>
            </div>
        </div>
        
        <script>
            function checkApproval() {
                fetch('?check_approval=<?php echo $submitted_request_id; ?>')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network error');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Check result:', data);
                        if (data.approved) {
                            window.location.href = '?vet_session=' + data.vet_session + '&pet_id=' + data.pet_id;
                        } else {
                            setTimeout(checkApproval, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        setTimeout(checkApproval, 3000);
                    });
            }
            
            // Start checking after 3 seconds
            setTimeout(checkApproval, 3000);
        </script>
    
    <?php else: ?>
        <!-- Initial request form -->
        <div class="auth-container">
            <div class="auth-card">
                <h3 class="text-center mb-4">🔒 Request Medical Records Access</h3>
                <p class="text-center text-muted mb-4">For: <strong><?php echo htmlspecialchars($pet_name); ?></strong></p>
                
                <?php if (!empty($auth_error)): ?>
                    <div class="alert alert-danger"><?php echo $auth_error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>">
                    <input type="hidden" name="pet_name" value="<?php echo htmlspecialchars($pet_name); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Veterinarian Email *</label>
                        <input type="email" class="form-control" name="vet_email" required placeholder="your.email@clinic.com">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Clinic/Hospital Name *</label>
                        <input type="text" class="form-control" name="vet_clinic" required placeholder="Animal Care Clinic">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" name="vet_phone" placeholder="(555) 123-4567">
                    </div>
                    
                    <button type="submit" name="request_access" class="btn btn-primary w-100 py-2">
                        📧 Send Access Request
                    </button>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            The pet owner will receive an email to approve your request.
                        </small>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
