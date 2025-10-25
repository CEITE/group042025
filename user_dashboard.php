<?php
session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && isset($_POST['pet_id'])) {
    $pet_id = $_POST['pet_id'];
    
    // Check if user owns this pet
    $check_stmt = $conn->prepare("SELECT user_id FROM pets WHERE pet_id = ?");
    $check_stmt->bind_param("i", $pet_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Pet not found']);
        exit();
    }
    
    $pet = $result->fetch_assoc();
    if ($pet['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
        exit();
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 2MB.']);
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/pet_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'pet_' . $pet_id . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $update_stmt = $conn->prepare("UPDATE pets SET profile_picture = ? WHERE pet_id = ?");
        $update_stmt->bind_param("si", $filepath, $pet_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'profile_picture' => $filepath]);
        } else {
            // Delete the uploaded file if database update fails
            unlink($filepath);
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
