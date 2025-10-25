<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Debug logging
error_log("=== PET PICTURE UPLOAD START ===");
error_log("Upload request received - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Files received: " . print_r($_FILES, true));
error_log("POST data: " . print_r($_POST, true));

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Use POST.']);
    exit();
}

// Check if file and pet_id are set
if (!isset($_FILES['profile_picture']) || !isset($_POST['pet_id'])) {
    error_log("Missing required parameters - Files: " . (isset($_FILES['profile_picture']) ? 'Yes' : 'No') . ", Pet ID: " . (isset($_POST['pet_id']) ? $_POST['pet_id'] : 'No'));
    echo json_encode(['success' => false, 'error' => 'Missing required parameters: profile_picture and pet_id are required.']);
    exit();
}

$pet_id = $_POST['pet_id'];
$file = $_FILES['profile_picture'];

error_log("Processing upload for pet ID: " . $pet_id);
error_log("File info - Name: " . $file['name'] . ", Size: " . $file['size'] . ", Type: " . $file['type'] . ", Error: " . $file['error']);

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit exceeded)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit exceeded)',
        UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by PHP extension'
    ];
    $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error (Code: ' . $file['error'] . ')';
    error_log("Upload error: " . $error_msg);
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit();
}

// Check if file was actually uploaded
if (!is_uploaded_file($file['tmp_name'])) {
    error_log("File not properly uploaded: " . $file['tmp_name']);
    echo json_encode(['success' => false, 'error' => 'File upload verification failed.']);
    exit();
}

// Check if user owns this pet
$check_stmt = $conn->prepare("SELECT user_id, name FROM pets WHERE pet_id = ?");
if (!$check_stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

$check_stmt->bind_param("i", $pet_id);
if (!$check_stmt->execute()) {
    error_log("Execute failed: " . $check_stmt->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $check_stmt->error]);
    exit();
}

$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Pet not found: " . $pet_id);
    echo json_encode(['success' => false, 'error' => 'Pet not found']);
    exit();
}

$pet = $result->fetch_assoc();
if ($pet['user_id'] != $user_id) {
    error_log("Unauthorized access attempt for pet: " . $pet_id . " by user: " . $user_id);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - You do not own this pet']);
    exit();
}

error_log("User authorized for pet: " . $pet['name']);

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$file_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

error_log("Detected file type: " . $file_type);

if (!in_array($file_type, $allowed_types)) {
    error_log("Invalid file type: " . $file_type . ". Allowed: " . implode(', ', $allowed_types));
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed. Detected: ' . $file_type]);
    exit();
}

// Validate file size (2MB max)
$max_size = 2 * 1024 * 1024;
if ($file['size'] > $max_size) {
    error_log("File too large: " . $file['size'] . " bytes (Max: " . $max_size . ")");
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 2MB. Your file: ' . round($file['size'] / 1024 / 1024, 2) . 'MB']);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/pet_pictures/';
if (!is_dir($upload_dir)) {
    error_log("Creating upload directory: " . $upload_dir);
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create directory: " . $upload_dir);
        echo json_encode(['success' => false, 'error' => 'Server error: Could not create upload directory']);
        exit();
    }
    error_log("Directory created successfully");
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    error_log("Directory not writable: " . $upload_dir . " - Permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
    echo json_encode(['success' => false, 'error' => 'Server error: Upload directory not writable']);
    exit();
}

// Generate unique filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (empty($file_extension)) {
    // Determine extension from MIME type
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $file_extension = $mime_to_ext[$file_type] ?? 'jpg';
}

// Clean filename and create unique name
$filename = 'pet_' . $pet_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$filepath = $upload_dir . $filename;

error_log("Generated filename: " . $filename);
error_log("Full filepath: " . $filepath);

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    error_log("File successfully moved to: " . $filepath);
    
    // Verify the file was created
    if (!file_exists($filepath)) {
        error_log("ERROR: File doesn't exist after move: " . $filepath);
        echo json_encode(['success' => false, 'error' => 'File upload failed - file not found after move']);
        exit();
    }
    
    // Update database
    $update_stmt = $conn->prepare("UPDATE pets SET profile_picture = ? WHERE pet_id = ?");
    if (!$update_stmt) {
        error_log("Prepare failed for update: " . $conn->error);
        // Delete the uploaded file if database update fails
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $update_stmt->bind_param("si", $filepath, $pet_id);
    
    if ($update_stmt->execute()) {
        error_log("Database updated successfully for pet: " . $pet_id);
        echo json_encode([
            'success' => true, 
            'profile_picture' => $filepath,
            'message' => 'Profile picture uploaded successfully'
        ]);
    } else {
        // Delete the uploaded file if database update fails
        unlink($filepath);
        error_log("Database update failed: " . $update_stmt->error);
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $update_stmt->error]);
    }
} else {
    error_log("File move failed for: " . $file['tmp_name'] . " to: " . $filepath);
    error_log("Upload error: " . $file['error']);
    error_log("Is uploaded file: " . (is_uploaded_file($file['tmp_name']) ? 'Yes' : 'No'));
    echo json_encode(['success' => false, 'error' => 'File upload failed - could not save file to server']);
}

error_log("=== PET PICTURE UPLOAD END ===");
?>
