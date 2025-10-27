<?php
// approve_vet_request.php - Handle vet access request approval
session_start();
include("conn.php");

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($request_id > 0 && !empty($token) && in_array($action, ['approve', 'reject'])) {
    
    // Verify the request exists and is pending
    $stmt = $conn->prepare("
        SELECT r.*, p.name as pet_name, u.email as owner_email, u.name as owner_name 
        FROM vet_access_requests r
        JOIN pets p ON r.pet_id = p.pet_id
        JOIN users u ON p.user_id = u.user_id
        WHERE r.request_id = ? AND r.access_key = ? AND r.status = 'pending'
    ");
    $stmt->bind_param("is", $request_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $request = $result->fetch_assoc();
        
        // Update request status
        $status = $action == 'approve' ? 'approved' : 'rejected';
        $update_stmt = $conn->prepare("
            UPDATE vet_access_requests 
            SET status = ?, approved_at = NOW() 
            WHERE request_id = ?
        ");
        $update_stmt->bind_param("si", $status, $request_id);
        
        if ($update_stmt->execute()) {
            // Send notification email to veterinarian
            sendVetNotificationEmail($request, $status);
            
            if ($status == 'approved') {
                // Redirect to medical records with access token
                header("Location: pet-medical-records.php?pet_id=" . $request['pet_id'] . "&token=" . $token);
                exit;
            } else {
                $message = "The access request has been rejected.";
                $title = "Request Rejected";
                $icon = "times-circle";
                $color = "danger";
            }
        }
        $update_stmt->close();
    } else {
        $message = "Invalid or expired request.";
        $title = "Error";
        $icon = "exclamation-triangle";
        $color = "warning";
    }
    $stmt->close();
} else {
    $message = "Invalid parameters.";
    $title = "Error";
    $icon = "exclamation-triangle";
    $color = "warning";
}

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
            <p><em>This access will expire on: " . date('F j, Y \a\t g:i A', strtotime($request['expires_at'])) . "</em></p>
        ";
    } else {
        $body = "
            <p>Your request to access the medical records of <strong>{$request['pet_name']}</strong> has been <strong>rejected</strong>.</p>
            <p>If you believe this is an error, please contact the pet owner directly.</p>
        ";
    }
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f9f9f9;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background-color: #ffffff;
            }
            .header { 
                background: " . ($status == 'approved' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)') . "; 
                color: white; 
                padding: 30px; 
                text-align: center; 
                border-radius: 10px 10px 0 0; 
            }
            .content { 
                padding: 30px; 
                border: 1px solid #e0e0e0;
                border-top: none;
                border-radius: 0 0 10px 10px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
                color: #666;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Access Request " . ucfirst($status) . "</h1>
                <p>For pet: <strong>{$request['pet_name']}</strong></p>
            </div>
            <div class="content">
                {$body}
                <div class="footer">
                    <p>PetMedQR Medical Records System</p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PetMedQR <noreply@petmedqr.com>" . "\r\n";
    $headers .= "Reply-To: noreply@petmedqr.com" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}

// Show result page if not redirected
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="card shadow">
                    <div class="card-body py-5">
                        <i class="fas fa-<?php echo $icon; ?> fa-4x text-<?php echo $color; ?> mb-4"></i>
                        <h2 class="h4 mb-3"><?php echo $title; ?></h2>
                        <p class="text-muted mb-4"><?php echo $message; ?></p>
                        <a href="pet-medical-records.php" class="btn btn-primary">
                            <i class="fas fa-paw me-2"></i>Return to PetMedQR
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>