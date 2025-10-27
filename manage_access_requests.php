<?php
session_start();
include("conn.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle approve/reject actions
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action = $_GET['action'];
    
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
                sendVetNotificationEmail($request_data, 'approved');
            }
        }
    }
    $update_stmt->close();
    
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

// Function to send notification to veterinarian
function sendVetNotificationEmail($request, $status) {
    $to = $request['vet_email'];
    $subject = "Medical Records Access Request " . ucfirst($status);
    
    $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    
    if ($status == 'approved') {
        $access_url = $current_domain . "/pet-medical-records.php?pet_id=" . $request['pet_id'] . "&token=" . $request['access_key'];
        $body = "
            <p>Your request to access the medical records of <strong>{$request['pet_name']}</strong> has been <strong>approved</strong>.</p>
            <p>You can now access the complete medical records using the link below:</p>
            <p style='text-align: center;'>
                <a href='{$access_url}' style='background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                    Access Medical Records
                </a>
            </p>
        ";
    } else {
        $body = "
            <p>Your request to access the medical records of <strong>{$request['pet_name']}</strong> has been <strong>rejected</strong>.</p>
        ";
    }
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Access Request " . ucfirst($status) . "</h2>
            {$body}
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PetMedQR <noreply@petmedqr.com>" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Access Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-shield-alt me-2"></i>Medical Records Access Requests</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <!-- Pending Requests -->
        <div class="card mt-4">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-clock me-2"></i>Pending Requests
            </div>
            <div class="card-body">
                <?php if (empty($pending_requests)): ?>
                    <p class="text-muted">No pending access requests.</p>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="row">
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($request['pet_name']); ?></h5>
                                <p class="mb-1"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($request['vet_email']); ?></p>
                                <p class="mb-1"><strong>Clinic:</strong> <?php echo htmlspecialchars($request['vet_clinic']); ?></p>
                                <p class="mb-1"><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($request['request_time'])); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="manage_access_requests.php?request_id=<?php echo $request['request_id']; ?>&action=approve" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i>Approve
                                </a>
                                <a href="manage_access_requests.php?request_id=<?php echo $request['request_id']; ?>&action=reject" class="btn btn-danger btn-sm">
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
        <div class="card mt-4">
            <div class="card-header">Access History</div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Veterinarian</th>
                            <th>Clinic</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($access_history as $history): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($history['pet_name']); ?></td>
                            <td><?php echo htmlspecialchars($history['vet_email']); ?></td>
                            <td><?php echo htmlspecialchars($history['vet_clinic']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $history['status'] == 'approved' ? 'success' : 
                                         ($history['status'] == 'rejected' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($history['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($history['request_time'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
