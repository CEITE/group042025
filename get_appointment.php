<?php
// get_appointment.php
session_start();
include("conn.php");

// Check if vet is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vet') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['appointment_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Appointment ID required']);
    exit();
}

$appointment_id = intval($_GET['appointment_id']);

// Fetch appointment with pet and owner details
$stmt = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.pet_id,
        a.user_id,
        a.appointment_date,
        a.appointment_time,
        a.service_type,
        a.reason,
        a.status,
        a.notes,
        p.name AS pet_name,
        u.name AS owner_name
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Appointment not found']);
    exit();
}

$appointment = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'appointment' => $appointment]);
?>