<?php
session_start();
include("conn.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    try {
        $uploadDir = "uploads/user_profile_pictures/";
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $file = $_FILES['profile_picture'];
        $fileName = time() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        
        // Check if image file is actual image
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            throw new Exception("File is not an image.");
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5000000) {
            throw new Exception("File is too large. Maximum size is 5MB.");
        }
        
        // Allow certain file formats
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
        }
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update database
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("si", $targetPath, $user_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Profile picture updated successfully!";
                $response['filePath'] = $targetPath;
                
                // Update session
                $_SESSION['profile_picture'] = $targetPath;
            } else {
                throw new Exception("Database update failed.");
            }
        } else {
            throw new Exception("Sorry, there was an error uploading your file.");
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
