<?php
// check_approval.php - Handles AJAX approval checks
session_start();
include("conn.php");

$request_id = isset($_GET['check_approval']) ? intval($_GET['check_approval']) : 0;

if ($request_id > 0) {
    try {
        // Check if the request has been approved
        $stmt = $conn->prepare("
            SELECT r.*, p.name as pet_name 
            FROM vet_access_requests r 
            JOIN pets p ON r.pet_id = p.pet_id 
            WHERE r.request_id = ? AND r.status = 'approved' AND r.access_granted = TRUE
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $approved_request = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($approved_request) {
            // Return approval data as JSON
            header('Content-Type: application/json');
            echo json_encode([
                'approved' => true,
                'vet_session' => $approved_request['vet_session_id'],
                'pet_id' => $approved_request['pet_id']
            ]);
            
            // Mark as accessed to prevent multiple redirects
            $stmt = $conn->prepare("UPDATE vet_access_requests SET access_granted = FALSE WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['approved' => false]);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['approved' => false, 'error' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['approved' => false]);
}
?>