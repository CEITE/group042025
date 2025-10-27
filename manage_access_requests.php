<?php
session_start();
include("conn.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Access Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Medical Records Access Requests</h2>
        
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
                                <?php
                                $token = md5($request['request_id'] . $request['vet_email'] . 'secret_salt');
                                $approve_url = "pet-medical-access.php?request_id=" . $request['request_id'] . "&token=" . $token . "&action=approve";
                                $reject_url = "pet-medical-access.php?request_id=" . $request['request_id'] . "&token=" . $token . "&action=reject";
                                ?>
                                <a href="<?php echo $approve_url; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i>Approve
                                </a>
                                <a href="<?php echo $reject_url; ?>" class="btn btn-danger btn-sm">
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
</body>
</html>
