<?php
// get_medical_record.php
session_start();
include("conn.php");

// Check if vet is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vet') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['record_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Record ID required']);
    exit();
}

$record_id = intval($_GET['record_id']);

// Fetch medical record
$stmt = $conn->prepare("
    SELECT 
        record_id,
        pet_id,
        service_date,
        service_type,
        service_description,
        weight,
        weight_date,
        reminder_description,
        reminder_due_date,
        notes,
        veterinarian
    FROM pet_medical_records 
    WHERE record_id = ?
");
$stmt->bind_param("i", $record_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Record not found']);
    exit();
}

$record = $result->fetch_assoc();

// Format dates for form inputs
if ($record['service_date'] && $record['service_date'] !== '0000-00-00') {
    $record['service_date'] = date('Y-m-d', strtotime($record['service_date']));
} else {
    $record['service_date'] = '';
}

if ($record['weight_date'] && $record['weight_date'] !== '0000-00-00') {
    $record['weight_date'] = date('Y-m-d', strtotime($record['weight_date']));
} else {
    $record['weight_date'] = '';
}

if ($record['reminder_due_date'] && $record['reminder_due_date'] !== '0000-00-00') {
    $record['reminder_due_date'] = date('Y-m-d', strtotime($record['reminder_due_date']));
} else {
    $record['reminder_due_date'] = '';
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'record' => $record]);
?>