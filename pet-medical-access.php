<?php
session_start();
include("conn.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle approve/reject actions
if (isset($_GET['action']) && isset($_GET['request_id']) && isset($_GET['token'])) {
    $request_id = intval($_GET['request_id']);
    $action = $_GET['action'];
    $token = $_GET['token'];
    
    // Verify token
    $expected_token = md5($request_id . 'secret_salt');
    if ($token === $expected_token) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $update_stmt = $conn->prepare("
            UPDATE vet_access_requests 
            SET status = ?, approved_at = NOW(), approved_by = ? 
            WHERE request_id = ? AND status = 'pending'
        ");
        $update_stmt->bind_param("sii", $status, $user_id, $request_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Request " . $status . " successfully.";
            
            // Send notification email to veterinarian
            if ($status === 'approved') {
                $req_stmt = $conn->prepare("
                    SELECT r.*, p.name as pet_name 
                    FROM vet_access_requests r 
                    JOIN pets p ON r.pet_id = p.pet_id 
                    WHERE r.request_id = ?
                ");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request_data = $req_stmt->get_result()->fetch_assoc();
                
                if ($request_data) {
                    sendStatusUpdateEmail($request_data, 'approved');
                }
            }
        }
        $update_stmt->close();
    }
    
    header("Location: manage_access_requests.php");
    exit();
}

// Fetch pending access requests for user's pets
$requests_stmt = $conn->prepare("
    SELECT r.*, p.name as pet_name 
    FROM vet_access_requests r 
    JOIN pets p ON r.pet_id = p.pet_id 
    WHERE p.user_id = ? AND r.status = 'pending' 
    ORDER BY r.request_time DESC
");
$requests_stmt->bind_param("i", $user_id);
$requests_stmt->execute();
$pending_requests = $requests_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent access history
$history_stmt = $conn->prepare("
    SELECT r.*, p.name as pet_name 
    FROM vet_access_requests r 
    JOIN pets p ON r.pet_id = p.pet_id 
    WHERE p.user_id = ? AND r.status != 'pending' 
    ORDER BY r.request_time DESC 
    LIMIT 50
");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$access_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to send status update email
function sendStatusUpdateEmail($request, $status) {
    $to = $request['vet_email'];
    $subject = "Medical Records Access Request " . ucfirst($status);
    
    $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $access_url = $current_domain . "/pet-medical-records.php?pet_id=" . $request['pet_id'] . "&request_id=" . $request['request_id'] . "&token=" . $request['access_key'];
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: " . ($status == 'approved' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)') . "; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 24px; background: #ec4899; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Access Request " . ucfirst($status) . "</h1>
                <p>For pet: <strong>{$request['pet_name']}</strong></p>
            </div>
            <div class='content'>
                <p>Your request to access the medical records of <strong>{$request['pet_name']}</strong> has been <strong>{$status}</strong>.</p>
                
                " . ($status == 'approved' ? "
                <p>You can now access the complete medical records using the link below:</p>
                <p style='text-align: center;'>
                    <a href='{$access_url}' class='button'>Access Medical Records</a>
                </p>
                <p><em>This access will expire on: " . date('F j, Y', strtotime($request['expires_at'])) . "</em></p>
                " : "
                <p>If you believe this is an error, please contact the pet owner directly.</p>
                ") . "
                
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
    
    mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Access Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #f0f9ff 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
            margin-bottom: 2rem;
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-pink-darker">
                <i class="fas fa-shield-alt me-2"></i>Medical Records Access Requests
            </h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <!-- Pending Requests -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-clock me-2"></i>Pending Requests
                <?php if (!empty($pending_requests)): ?>
                    <span class="badge bg-dark ms-2"><?php echo count($pending_requests); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($pending_requests)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">No pending access requests.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="text-primary"><?php echo htmlspecialchars($request['pet_name']); ?></h5>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong><i class="fas fa-user-md me-2"></i>Veterinarian:</strong> <?php echo htmlspecialchars($request['vet_email']); ?></p>
                                        <p class="mb-1"><strong><i class="fas fa-hospital me-2"></i>Clinic:</strong> <?php echo htmlspecialchars($request['vet_clinic']); ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong><i class="fas fa-phone me-2"></i>Phone:</strong> <?php echo htmlspecialchars($request['vet_phone'] ?: 'Not provided'); ?></p>
                                        <p class="mb-1"><strong><i class="fas fa-calendar me-2"></i>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($request['request_time'])); ?></p>
                                    </div>
                                </div>
                                <?php if ($request['ip_address']): ?>
                                    <p class="mb-0 text-muted small">
                                        <i class="fas fa-globe me-1"></i>Request from: <?php echo htmlspecialchars($request['ip_address']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php
                                $token = md5($request['request_id'] . 'secret_salt');
                                $approve_url = "manage_access_requests.php?request_id=" . $request['request_id'] . "&token=" . $token . "&action=approve";
                                $reject_url = "manage_access_requests.php?request_id=" . $request['request_id'] . "&token=" . $token . "&action=reject";
                                ?>
                                <a href="<?php echo $approve_url; ?>" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-check me-1"></i>Approve
                                </a>
                                <a href="<?php echo $reject_url; ?>" class="btn btn-danger btn-lg">
                                    <i class="fas fa-times me-1"></i>Reject
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Access History -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-history me-2"></i>Access History
            </div>
            <div class="card-body">
                <?php if (empty($access_history)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No access history available.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Pet</th>
                                    <th>Veterinarian</th>
                                    <th>Clinic</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($access_history as $history): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($history['pet_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($history['vet_email']); ?></td>
                                    <td><?php echo htmlspecialchars($history['vet_clinic']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $history['status'] == 'approved' ? 'success' : 
                                                 ($history['status'] == 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $history['status'] == 'approved' ? 'check' : 
                                                     ($history['status'] == 'rejected' ? 'times' : 'clock'); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($history['request_time'])); ?></td>
                                    <td>
                                        <?php if ($history['status'] == 'approved' && $history['access_granted']): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-eye me-1"></i>Viewed
                                            </span>
                                        <?php elseif ($history['status'] == 'approved'): ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-clock me-1"></i>Not Viewed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
