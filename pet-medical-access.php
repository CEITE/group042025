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
            .header { background: #0ea5e9; color: white; padding: 20px; text-align: center; }
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
        if (file_exists("conn.php")) {
            include("conn.php");
            
            // Fetch pet data if connection successful
            if ($pet_id > 0 && isset($conn)) {
                // Get ALL pet data including medical fields
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
        }
    } catch (Exception $e) {
        // Silent fail - we'll use the basic data
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
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #e0f2fe 50%, #f0f9ff 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.7;
        }
        
        .medical-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 4rem 2rem;
            border-radius: var(--radius);
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-lg);
        }
        
        .medical-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 6s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
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
            margin: 0 auto 2rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .medical-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .medical-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .medical-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        
        .card-header-custom {
            background: var(--primary-light);
            border-bottom: 2px solid var(--primary);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }
        
        .card-header-blue {
            background: var(--primary-light);
            border-bottom: 2px solid var(--primary);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }
        
        .card-header-green {
            background: var(--success-light);
            border-bottom: 2px solid var(--success);
            padding: 1.5rem 2rem;
            font-weight: 700;
            color: var(--success);
            font-size: 1.2rem;
        }
        
        .record-item {
            background: linear-gradient(135deg, var(--primary-light) 0%, #fff 100%);
            border-radius: var(--radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border-left: 5px solid var(--primary);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.1);
        }
        
        .record-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
        }
        
        .history-item {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            border-radius: var(--radius-sm);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid var(--gray);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.08);
        }
        
        .history-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.12);
        }
        
        .medical-content {
            background: rgba(255, 255, 255, 0.7);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(0, 0, 0, 0.08);
            white-space: pre-line;
            line-height: 1.8;
            font-size: 1.05rem;
            backdrop-filter: blur(10px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--gray-light);
            opacity: 0.7;
        }
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            margin: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary);
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.08);
        }
        
        .info-card i {
            font-size: 2rem;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }
        
        .contact-section {
            background: linear-gradient(135deg, var(--light) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            padding: 2rem;
            margin: 2rem 0;
            border: 2px solid var(--primary-light);
        }
        
        .emergency-banner {
            background: linear-gradient(135deg, var(--warning-light) 0%, #fde68a 100%);
            border: 2px solid var(--warning);
            border-radius: var(--radius);
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }
        
        .floating-action {
            animation: float 4s ease-in-out infinite;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .medical-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            margin: 3rem 0;
            border: none;
        }

        .auth-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .auth-card { background: white; border-radius: 16px; box-shadow: var(--shadow-lg); padding: 3rem; max-width: 500px; width: 100%; }
        .auth-icon { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; margin: 0 auto 1.5rem; }
        .waiting-animation { animation: pulse 2s infinite; }
    </style>
</head>
<body>

<?php if (isset($success_message) && !$is_authenticated && !isset($submitted_request_id)): ?>
<!-- Success Message for Owner (After Approval/Rejection) -->
<div class="auth-container">
    <div class="auth-card text-center">
        <div class="auth-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
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
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-check-circle me-2"></i>
            Access granted! You can now view <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s medical records.
        </div>
        <div class="text-muted small">
            <i class="fas fa-clock me-1"></i>
            Session expires: <?php echo date('g:i A', strtotime('+2 hours')); ?>
        </div>
    </div>

    <!-- Header -->
    <div class="medical-header">
        <div class="pet-avatar floating">
            <i class="fas fa-paw"></i>
        </div>
        <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Profile</h1>
        <p class="lead mb-4 opacity-90">Complete Medical History & Healthcare Records</p>
        <div class="d-flex flex-wrap justify-content-center">
            <span class="stats-badge">
                <i class="fas fa-shield-alt"></i> Secure QR Access
            </span>
            <?php if ($pet_data && $pet_data['has_existing_records']): ?>
            <span class="stats-badge">
                <i class="fas fa-history"></i> Medical History Available
            </span>
            <?php endif; ?>
            <?php if (!empty($medical_records)): ?>
            <span class="stats-badge">
                <i class="fas fa-file-medical"></i> <?php echo count($medical_records); ?> Visit Records
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xxl-10 col-xl-12">
            <!-- Emergency Banner -->
            <div class="emergency-banner pulse">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                    </div>
                    <div class="col">
                        <h4 class="text-warning mb-2">Emergency Medical Access</h4>
                        <p class="mb-0">This QR code provides instant access to vital medical information for emergency veterinary care.</p>
                    </div>
                </div>
            </div>

            <!-- Pet Information -->
            <div class="medical-card">
                <div class="card-header-custom">
                    <h3 class="mb-0">
                        <i class="fas fa-paw me-3"></i>Pet Information
                    </h3>
                </div>
                <div class="card-body p-5">
                    <?php if ($pet_data): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <i class="fas fa-id-card"></i>
                                <h6>Basic Info</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                <p class="mb-1"><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species']); ?></p>
                                <p class="mb-1"><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></p>
                                <p class="mb-0"><strong>Age:</strong> <?php echo htmlspecialchars($pet_data['age']); ?> years</p>
                            </div>
                            
                            <div class="info-card">
                                <i class="fas fa-venus-mars"></i>
                                <h6>Physical Details</h6>
                                <p class="mb-1"><strong>Gender:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></p>
                                <p class="mb-1"><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></p>
                                <p class="mb-1"><strong>Weight:</strong> <?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></p>
                                <p class="mb-0"><strong>Birth Date:</strong> <?php echo !empty($pet_data['birth_date']) ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Not specified'; ?></p>
                            </div>
                            
                            <div class="info-card">
                                <i class="fas fa-id-badge"></i>
                                <h6>Identification</h6>
                                <p class="mb-1"><strong>Pet ID:</strong> #<?php echo htmlspecialchars($pet_data['pet_id']); ?></p>
                                <p class="mb-1"><strong>Registered:</strong> <?php echo !empty($pet_data['date_registered']) ? date('M j, Y', strtotime($pet_data['date_registered'])) : 'Unknown'; ?></p>
                                <?php if ($pet_data['owner_name']): ?>
                                    <p class="mb-0"><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($pet_data['medical_notes'])): ?>
                            <div class="mt-4 p-4 bg-light rounded-3">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="medical-icon">
                                        <i class="fas fa-file-medical"></i>
                                    </div>
                                    <h4 class="text-primary-dark mb-0">Current Medical Notes</h4>
                                </div>
                                <div class="medical-content"><?php echo htmlspecialchars($pet_data['medical_notes']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-paw"></i>
                            <h4 class="text-muted"><?php echo $pet_name; ?></h4>
                            <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                            <p class="text-muted">Basic information available in full system</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="section-divider">

            <!-- MEDICAL RECORDS from pet_medical_records table -->
            <div class="medical-card">
                <div class="card-header-blue">
                    <h3 class="mb-0">
                        <i class="fas fa-file-medical-alt me-3"></i>Medical Visit Records
                        <?php if (!empty($medical_records)): ?>
                            <span class="badge bg-white text-primary ms-2 fs-6"><?php echo count($medical_records); ?> visits</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body p-5">
                    <?php if (!empty($medical_records)): ?>
                        <div class="row g-4">
                            <?php foreach ($medical_records as $record): ?>
                            <div class="col-lg-6">
                                <div class="record-item h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="text-primary-dark mb-1"><?php echo htmlspecialchars($record['record_type']); ?></h5>
                                            <?php if (!empty($record['veterinarian'])): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-md me-1"></i>Dr. <?php echo htmlspecialchars($record['veterinarian']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-muted d-block"><?php echo date('M j, Y', strtotime($record['record_date'])); ?></strong>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($record['record_date'])); ?></small>
                                        </div>
                                    </div>
                                    <p class="mb-3 fs-6"><?php echo htmlspecialchars($record['description']); ?></p>
                                    <?php if (!empty($record['notes'])): ?>
                                        <div class="bg-white p-3 rounded border">
                                            <small class="text-dark">
                                                <strong><i class="fas fa-sticky-note me-1"></i>Additional Notes:</strong><br>
                                                <?php echo htmlspecialchars($record['notes']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical"></i>
                            <h4 class="text-muted">No Medical Visit Records</h4>
                            <p class="text-muted">No medical visit records found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="section-divider">

            <!-- MEDICAL HISTORY from pets table -->
            <div class="medical-card">
                <div class="card-header-green">
                    <h3 class="mb-0">
                        <i class="fas fa-history me-3"></i>Medical History Summary
                        <?php if ($pet_data && $pet_data['has_existing_records']): ?>
                            <span class="badge bg-white text-success ms-2 fs-6">Complete History</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body p-5">
                    <?php if ($pet_data): ?>
                        <div class="row g-4">
                            <!-- Previous Conditions -->
                            <div class="col-xl-6">
                                <div class="history-item h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="medical-icon" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                            <i class="fas fa-stethoscope"></i>
                                        </div>
                                        <h4 class="text-dark mb-0">Previous Conditions</h4>
                                    </div>
                                    <?php if (!empty($pet_data['previous_conditions']) && trim($pet_data['previous_conditions']) !== ''): ?>
                                        <div class="medical-content"><?php echo htmlspecialchars($pet_data['previous_conditions']); ?></div>
                                    <?php else: ?>
                                        <div class="empty-state py-4">
                                            <i class="fas fa-stethoscope text-muted"></i>
                                            <p class="text-muted mb-0">No previous conditions recorded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Vaccination History -->
                            <div class="col-xl-6">
                                <div class="history-item h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="medical-icon" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                            <i class="fas fa-syringe"></i>
                                        </div>
                                        <h4 class="text-dark mb-0">Vaccination History</h4>
                                    </div>
                                    <?php if (!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== ''): ?>
                                        <div class="medical-content"><?php echo htmlspecialchars($pet_data['vaccination_history']); ?></div>
                                    <?php else: ?>
                                        <div class="empty-state py-4">
                                            <i class="fas fa-syringe text-muted"></i>
                                            <p class="text-muted mb-0">No vaccination history recorded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Surgical History -->
                            <div class="col-xl-6">
                                <div class="history-item h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="medical-icon" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                                            <i class="fas fa-scissors"></i>
                                        </div>
                                        <h4 class="text-dark mb-0">Surgical History</h4>
                                    </div>
                                    <?php if (!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== ''): ?>
                                        <div class="medical-content"><?php echo htmlspecialchars($pet_data['surgical_history']); ?></div>
                                    <?php else: ?>
                                        <div class="empty-state py-4">
                                            <i class="fas fa-scissors text-muted"></i>
                                            <p class="text-muted mb-0">No surgical history recorded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Medication History -->
                            <div class="col-xl-6">
                                <div class="history-item h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="medical-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                                            <i class="fas fa-pills"></i>
                                        </div>
                                        <h4 class="text-dark mb-0">Medication History</h4>
                                    </div>
                                    <?php if (!empty($pet_data['medication_history']) && trim($pet_data['medication_history']) !== ''): ?>
                                        <div class="medical-content"><?php echo htmlspecialchars($pet_data['medication_history']); ?></div>
                                    <?php else: ?>
                                        <div class="empty-state py-4">
                                            <i class="fas fa-pills text-muted"></i>
                                            <p class="text-muted mb-0">No medication history recorded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Records Location -->
                        <?php if (!empty($pet_data['records_location']) && trim($pet_data['records_location']) !== ''): ?>
                        <div class="history-item mt-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="medical-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                                    <i class="fas fa-archive"></i>
                                </div>
                                <h4 class="text-dark mb-0">Existing Records Location</h4>
                            </div>
                            <div class="medical-content"><?php echo htmlspecialchars($pet_data['records_location']); ?></div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4 class="text-muted">No Medical History Data</h4>
                            <p class="text-muted">No medical history data available for this pet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Information -->
            <?php if ($pet_data && ($pet_data['owner_name'] || $pet_data['vet_contact'])): ?>
            <div class="contact-section">
                <div class="row">
                    <?php if ($pet_data['owner_name']): ?>
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                            <h4 class="text-primary mb-0">Owner Contact</h4>
                        </div>
                        <div class="ps-5">
                            <p class="mb-2 fs-5"><strong><?php echo htmlspecialchars($pet_data['owner_name']); ?></strong></p>
                            <?php if ($pet_data['owner_phone']): ?>
                                <p class="mb-2">
                                    <i class="fas fa-phone me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($pet_data['owner_phone']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($pet_data['owner_email']): ?>
                                <p class="mb-0">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($pet_data['owner_email']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($pet_data['vet_contact']): ?>
                    <div class="col-lg-6">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-hospital-user fa-2x text-success me-3"></i>
                            <h4 class="text-success mb-0">Veterinarian Contact</h4>
                        </div>
                        <div class="ps-5">
                            <p class="mb-0 fs-5"><strong><?php echo htmlspecialchars($pet_data['vet_contact']); ?></strong></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <footer class="text-center text-muted mt-5 pt-5 border-top">
                <div class="mb-3">
                    <i class="fas fa-paw fa-2x text-primary me-2"></i>
                    <strong class="text-primary-dark fs-4">PetMedQR</strong>
                </div>
                <p class="mb-2 small">&copy; <?php echo date('Y'); ?> PetMedQR Medical Records System</p>
                <p class="small text-muted">Secure QR-based pet medical records access for emergency veterinary care</p>
                <?php if ($pet_data && $pet_data['medical_history_updated_at']): ?>
                    <p class="small text-muted mt-2">
                        <i class="fas fa-sync me-1"></i>
                        Last updated: <?php echo date('F j, Y \a\t g:i A', strtotime($pet_data['medical_history_updated_at'])); ?>
                    </p>
                <?php endif; ?>
            </footer>
        </div>
    </div>
</div>

<?php elseif (isset($submitted_request_id)): ?>
<!-- WAITING FOR APPROVAL PAGE (Vet sees this after submitting request) -->
<div class="auth-container">
    <div class="auth-card text-center">
        <div class="auth-icon waiting-animation" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
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
<script>
    // Add subtle animations to cards when they come into view
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.medical-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 200);
        });
    });
</script>
</body>
</html>
