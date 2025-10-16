<?php
// qr_landing.php - WITH ERROR HANDLING
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include files with error handling
if (!file_exists('config.php')) {
    die('❌ config.php not found. Please check the file exists.');
}
require_once 'config.php';

if (!file_exists('conn.php')) {
    die('❌ conn.php not found. Please check the file exists.');
}
require_once 'conn.php';

// Get pet ID from URL
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

// Debug information
$debug_info = "
<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;'>
<strong>Debug Info:</strong><br>
Pet ID: $pet_id<br>
Base URL: " . SITE_URL . "<br>
Current URL: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "
</div>
";

if ($pet_id > 0) {
    // Fetch pet information
    $stmt = $conn->prepare("
        SELECT p.*, u.name as owner_name, u.phone as owner_phone, u.email as owner_email
        FROM pets p 
        LEFT JOIN users u ON p.user_id = u.user_id 
        WHERE p.pet_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $pet_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pet = $result->fetch_assoc();
        $stmt->close();
    } else {
        $pet = null;
        $error = "Database query failed: " . $conn->error;
    }
} else {
    $pet = null;
    $error = "No pet ID provided in URL";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pet ? htmlspecialchars($pet['name']) . ' - Medical Info' : 'Pet Not Found'; ?> - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .pet-card {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(184, 71, 129, 0.15);
        }
        .pet-header {
            background: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        .pet-avatar {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f1f1;
        }
        .emergency-section {
            background: #ff6b6b;
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
        }
        .error-section {
            background: #ffc107;
            color: #856404;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php if (isset($error)): ?>
        <div class="pet-card">
            <div class="error-section">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h3>Error Loading Pet Information</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php echo $debug_info; ?>
        </div>
    <?php elseif ($pet): ?>
        <div class="pet-card">
            <div class="pet-header">
                <div class="pet-avatar">
                    <i class="fas fa-<?php echo strtolower($pet['species']) == 'dog' ? 'dog' : 'cat'; ?>"></i>
                </div>
                <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($pet['name']); ?></h1>
                <p class="mb-0"><?php echo htmlspecialchars($pet['species']); ?> • <?php echo htmlspecialchars($pet['breed']); ?></p>
            </div>
            <div class="p-4">
                <div class="info-item">
                    <strong>Age:</strong>
                    <span><?php echo htmlspecialchars($pet['age']); ?> years</span>
                </div>
                <div class="info-item">
                    <strong>Gender:</strong>
                    <span><?php echo htmlspecialchars($pet['gender']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Color:</strong>
                    <span><?php echo htmlspecialchars($pet['color']); ?></span>
                </div>
                <?php if ($pet['medical_notes']): ?>
                <div class="info-item">
                    <strong>Medical Notes:</strong>
                    <span><?php echo htmlspecialchars($pet['medical_notes']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="emergency-section">
                    <h4><i class="fas fa-phone me-2"></i>Emergency Contact</h4>
                    <p class="mb-1">If found, please contact:</p>
                    <h5 class="mb-1"><?php echo htmlspecialchars($pet['owner_name']); ?></h5>
                    <?php if ($pet['owner_phone']): ?>
                        <p class="mb-1"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($pet['owner_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($pet['owner_email']): ?>
                        <p class="mb-0"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($pet['owner_email']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="text-center text-muted mt-4">
                    <small>
                        <i class="fas fa-qrcode me-1"></i>
                        VetCareQR Medical ID • Scanned on <?php echo date('M j, Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="pet-card">
            <div class="p-5 text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h3 class="text-danger">Pet Not Found</h3>
                <p class="text-muted">This QR code is not associated with any pet in our system.</p>
                <p class="text-muted">Pet ID: <?php echo $pet_id; ?> was requested but not found.</p>
            </div>
            <?php echo $debug_info; ?>
        </div>
    <?php endif; ?>
</body>
</html>