<?php
// medical-records.php

// Set headers first
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once 'conn.php';

// Check if database connection is established
if (!$pdo) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            handleGet();
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            handlePost($input);
            break;
            
        case 'PUT':
            if (isset($_GET['id'])) {
                $input = json_decode(file_get_contents("php://input"), true);
                handlePut($_GET['id'], $input);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Record ID required"]);
            }
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                handleDelete($_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Record ID required"]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(["success" => false, "error" => "Method not allowed"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}

function handleGet() {
    global $pdo;
    
    try {
        if (isset($_GET['id'])) {
            // Get single record
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Invalid ID"]);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM pet_medical_records WHERE record_id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            
            if ($record) {
                echo json_encode(["success" => true, "data" => $record]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Record not found"]);
            }
        } else {
            // Get all records
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if ($search) {
                $stmt = $pdo->prepare("
                    SELECT * FROM pet_medical_records 
                    WHERE pet_name LIKE ? OR owner_name LIKE ? OR species LIKE ?
                    ORDER BY record_id DESC
                ");
                $searchTerm = "%$search%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM pet_medical_records ORDER BY record_id DESC");
                $stmt->execute();
            }
            
            $records = $stmt->fetchAll();
            echo json_encode(["success" => true, "data" => $records]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
}

function handlePost($data) {
    global $pdo;
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "No data provided"]);
        return;
    }
    
    try {
        $sql = "INSERT INTO pet_medical_records (
            owner_id, owner_name, pet_id, pet_name, species, breed, color, sex, 
            dob, age, weight, status, tag, microchip, weight_date, reminder_description,
            reminder_due_date, service_date, service_time, service_type, service_description,
            veterinarian, notes, generated_date, clinic_name, clinic_address, clinic_contact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['owner_id'] ?? null,
            $data['owner_name'] ?? null,
            $data['pet_id'] ?? null,
            $data['pet_name'] ?? null,
            $data['species'] ?? null,
            $data['breed'] ?? null,
            $data['color'] ?? null,
            $data['sex'] ?? null,
            $data['dob'] ?? null,
            $data['age'] ?? null,
            $data['weight'] ?? null,
            $data['status'] ?? 'Active',
            $data['tag'] ?? null,
            $data['microchip'] ?? null,
            $data['weight_date'] ?? null,
            $data['reminder_description'] ?? null,
            $data['reminder_due_date'] ?? null,
            $data['service_date'] ?? date('Y-m-d'),
            $data['service_time'] ?? null,
            $data['service_type'] ?? 'Checkup',
            $data['service_description'] ?? null,
            $data['veterinarian'] ?? null,
            $data['notes'] ?? null,
            $data['generated_date'] ?? date('Y-m-d'),
            $data['clinic_name'] ?? null,
            $data['clinic_address'] ?? null,
            $data['clinic_contact'] ?? null
        ]);
        
        echo json_encode([
            "success" => true,
            "id" => $pdo->lastInsertId(),
            "message" => "Record created successfully"
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
}

function handlePut($id, $data) {
    global $pdo;
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "No data provided"]);
        return;
    }
    
    try {
        // Check if record exists
        $checkStmt = $pdo->prepare("SELECT record_id FROM pet_medical_records WHERE record_id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Record not found"]);
            return;
        }
        
        $sql = "UPDATE pet_medical_records SET 
            owner_id = ?, owner_name = ?, pet_id = ?, pet_name = ?, species = ?, breed = ?, 
            color = ?, sex = ?, dob = ?, age = ?, weight = ?, status = ?, tag = ?, microchip = ?, 
            weight_date = ?, reminder_description = ?, reminder_due_date = ?, service_date = ?, 
            service_time = ?, service_type = ?, service_description = ?, veterinarian = ?, 
            notes = ?, clinic_name = ?, clinic_address = ?, clinic_contact = ? 
            WHERE record_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['owner_id'] ?? null,
            $data['owner_name'] ?? null,
            $data['pet_id'] ?? null,
            $data['pet_name'] ?? null,
            $data['species'] ?? null,
            $data['breed'] ?? null,
            $data['color'] ?? null,
            $data['sex'] ?? null,
            $data['dob'] ?? null,
            $data['age'] ?? null,
            $data['weight'] ?? null,
            $data['status'] ?? 'Active',
            $data['tag'] ?? null,
            $data['microchip'] ?? null,
            $data['weight_date'] ?? null,
            $data['reminder_description'] ?? null,
            $data['reminder_due_date'] ?? null,
            $data['service_date'] ?? null,
            $data['service_time'] ?? null,
            $data['service_type'] ?? null,
            $data['service_description'] ?? null,
            $data['veterinarian'] ?? null,
            $data['notes'] ?? null,
            $data['clinic_name'] ?? null,
            $data['clinic_address'] ?? null,
            $data['clinic_contact'] ?? null,
            $id
        ]);
        
        echo json_encode(["success" => true, "message" => "Record updated successfully"]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
}

function handleDelete($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pet_medical_records WHERE record_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Record deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Record not found"]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
}
?>