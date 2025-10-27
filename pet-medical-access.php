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
$vaccinations = [];
$allergies = [];
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
            // Fetch basic pet information
            $stmt = $conn->prepare("SELECT p.*, u.name as owner_name, u.email as owner_email, u.phone_number as owner_phone FROM pets p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.pet_id = ?");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pet_data = $result->fetch_assoc();
            $stmt->close();

            // Fetch medical records
            $stmt = $conn->prepare("SELECT * FROM pet_medical_records WHERE pet_id = ? ORDER BY record_date DESC");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $medical_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Fetch vaccinations
            $stmt = $conn->prepare("SELECT * FROM pet_vaccinations WHERE pet_id = ? ORDER BY vaccination_date DESC");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $vaccinations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Fetch allergies
            $stmt = $conn->prepare("SELECT * FROM pet_allergies WHERE pet_id = ?");
            $stmt->bind_param("i", $pet_id);
            $stmt->execute();
            $allergies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        .medical-record-card { border-left: 4px solid #0d6efd; }
        .vaccination-card { border-left: 4px solid #198754; }
        .allergy-card { border-left: 4px solid #dc3545; }
        .info-card { border-left: 4px solid #6f42c1; }
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="fas fa-paw me-2"></i>
                        <?php echo htmlspecialchars($pet_data['name'] ?? $pet_name); ?>'s Medical Records
                    </h1>
                    <div class="text-end">
                        <p class="mb-1"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($_SESSION['vet_email']); ?></p>
                        <p class="mb-0"><strong>Clinic:</strong> <?php echo htmlspecialchars($_SESSION['vet_clinic']); ?></p>
                    </div>
                </div>
                <hr>
            </div>
        </div>

        <!-- Pet Information Card -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card info-card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Pet Information
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($pet_data): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                                <p><strong>Species:</strong> <?php echo htmlspecialchars($pet_data['species'] ?? 'Not specified'); ?></p>
                                <p><strong>Breed:</strong> <?php echo htmlspecialchars($pet_data['breed'] ?? 'Not specified'); ?></p>
                                <p><strong>Color:</strong> <?php echo htmlspecialchars($pet_data['color'] ?? 'Not specified'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date of Birth:</strong> <?php echo $pet_data['date_of_birth'] ? date('F j, Y', strtotime($pet_data['date_of_birth'])) : 'Unknown'; ?></p>
                                <p><strong>Age:</strong> 
                                    <?php 
                                    if ($pet_data['date_of_birth']) {
                                        $birthDate = new DateTime($pet_data['date_of_birth']);
                                        $today = new DateTime();
                                        $age = $today->diff($birthDate);
                                        echo $age->y . ' years, ' . $age->m . ' months';
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </p>
                                <p><strong>Weight:</strong> <?php echo $pet_data['weight'] ? htmlspecialchars($pet_data['weight']) . ' kg' : 'Not specified'; ?></p>
                                <p><strong>Microchip:</strong> <?php echo !empty($pet_data['microchip_number']) ? htmlspecialchars($pet_data['microchip_number']) : 'Not registered'; ?></p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet_data['owner_name'] ?? 'Unknown'); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($pet_data['owner_email'] ?? 'Unknown'); ?> 
                                    <?php if (!empty($pet_data['owner_phone'])): ?>
                                    | <?php echo htmlspecialchars($pet_data['owner_phone']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($pet_data['medical_notes'])): ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Medical Notes:</strong></p>
                                <div class="alert alert-light border">
                                    <?php echo nl2br(htmlspecialchars($pet_data['medical_notes'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No pet information found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Allergies Section -->
        <?php if (!empty($allergies)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card allergy-card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-skull-crossbones me-2"></i>
                            Known Allergies & Sensitivities
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Allergen</th>
                                        <th>Reaction</th>
                                        <th>Severity</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allergies as $allergy): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($allergy['allergen']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($allergy['reaction']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $allergy['severity'] == 'Severe' ? 'bg-danger' : 
                                                         ($allergy['severity'] == 'Moderate' ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo htmlspecialchars($allergy['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($allergy['notes'] ?? 'None'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vaccinations Section -->
        <?php if (!empty($vaccinations)): ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card vaccination-card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-syringe me-2"></i>
                            Vaccination History
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Vaccine</th>
                                        <th>Date Administered</th>
                                        <th>Next Due</th>
                                        <th>Veterinarian</th>
                                        <th>Lot Number</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vaccinations as $vaccination): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($vaccination['vaccination_date'])); ?></td>
                                        <td>
                                            <?php if ($vaccination['next_due_date']): ?>
                                                <?php 
                                                $nextDue = new DateTime($vaccination['next_due_date']);
                                                $today = new DateTime();
                                                if ($nextDue < $today) {
                                                    echo '<span class="badge bg-danger">OVERDUE: ' . $nextDue->format('M j, Y') . '</span>';
                                                } else {
                                                    echo $nextDue->format('M j, Y');
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($vaccination['administered_by'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($vaccination['lot_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($vaccination['notes'] ?? 'None'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Medical Records Section -->
        <div class="row">
            <div class="col-12">
                <div class="card medical-record-card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>
                            Medical History & Visit Records
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($medical_records)): ?>
                            <div class="accordion" id="medicalRecordsAccordion">
                                <?php foreach ($medical_records as $index => $record): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" 
                                                type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $index; ?>" 
                                                aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                                aria-controls="collapse<?php echo $index; ?>">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($record['visit_type']); ?></strong> 
                                                    - <?php echo date('F j, Y', strtotime($record['record_date'])); ?>
                                                </div>
                                                <div>
                                                    <span class="badge 
                                                        <?php echo $record['urgency'] == 'Emergency' ? 'bg-danger' : 
                                                                 ($record['urgency'] == 'Urgent' ? 'bg-warning' : 'bg-info'); ?> me-2">
                                                        <?php echo htmlspecialchars($record['urgency']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $index; ?>" 
                                         class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                         aria-labelledby="heading<?php echo $index; ?>" 
                                         data-bs-parent="#medicalRecordsAccordion">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian_name'] ?? 'Unknown'); ?></p>
                                                    <p><strong>Clinic:</strong> <?php echo htmlspecialchars($record['clinic_name'] ?? 'Unknown'); ?></p>
                                                    <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis'] ?? 'Not specified'); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Temperature:</strong> <?php echo $record['temperature'] ? htmlspecialchars($record['temperature']) . '°C' : 'Not recorded'; ?></p>
                                                    <p><strong>Weight:</strong> <?php echo $record['weight'] ? htmlspecialchars($record['weight']) . ' kg' : 'Not recorded'; ?></p>
                                                    <p><strong>Heart Rate:</strong> <?php echo $record['heart_rate'] ? htmlspecialchars($record['heart_rate']) . ' bpm' : 'Not recorded'; ?></p>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($record['symptoms'])): ?>
                                            <div class="mt-3">
                                                <strong>Symptoms:</strong>
                                                <div class="alert alert-light border">
                                                    <?php echo nl2br(htmlspecialchars($record['symptoms'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['treatment'])): ?>
                                            <div class="mt-3">
                                                <strong>Treatment:</strong>
                                                <div class="alert alert-light border">
                                                    <?php echo nl2br(htmlspecialchars($record['treatment'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['medications'])): ?>
                                            <div class="mt-3">
                                                <strong>Medications Prescribed:</strong>
                                                <div class="alert alert-light border">
                                                    <?php echo nl2br(htmlspecialchars($record['medications'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['notes'])): ?>
                                            <div class="mt-3">
                                                <strong>Additional Notes:</strong>
                                                <div class="alert alert-light border">
                                                    <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['follow_up_date'])): ?>
                                            <div class="mt-3">
                                                <strong>Follow-up Date:</strong> 
                                                <span class="badge bg-info">
                                                    <?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No medical records found for this pet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-secondary text-center">
                    <small>
                        <i class="fas fa-shield-alt me-1"></i>
                        This access is temporary and will expire automatically. All access is logged for security purposes.
                    </small>
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
