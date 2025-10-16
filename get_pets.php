<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "no session user_id"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch pets
$sql = "SELECT id, name, species, breed, age, gender FROM pets WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$pets = [];
while ($row = $result->fetch_assoc()) {
    $pets[] = $row;
}

header('Content-Type: application/json');
echo json_encode($pets);
?>
