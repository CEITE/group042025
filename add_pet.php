<?php
session_start();
include("conn.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get form data
    $name = trim($_POST['name']);
    $species = trim($_POST['species']);
    $breed = trim($_POST['breed']);
    $age = floatval($_POST['age']);
    $gender = trim($_POST['gender']);
    $color = trim($_POST['color'] ?? '');
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $microchip = trim($_POST['microchip'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $medical_notes = trim($_POST['medical_notes'] ?? '');
    $dietary_restrictions = trim($_POST['dietary_restrictions'] ?? '');
    $vet_name = trim($_POST['vet_name'] ?? '');
    $vet_contact = trim($_POST['vet_contact'] ?? '');
    $behavior_notes = trim($_POST['behavior_notes'] ?? '');
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($species) || empty($breed) || empty($gender)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: dashboard.php");
        exit();
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Insert pet into database
        $stmt = $conn->prepare("
            INSERT INTO pets (
                user_id, name, species, breed, age, gender, color, weight, 
                birth_date, microchip, registration_number, medical_notes, 
                dietary_restrictions, vet_name, vet_contact, behavior_notes, 
                special_instructions, date_registered
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "isssdssdsssssssss", 
            $user_id, $name, $species, $breed, $age, $gender, $color, $weight,
            $birth_date, $microchip, $registration_number, $medical_notes,
            $dietary_restrictions, $vet_name, $vet_contact, $behavior_notes,
            $special_instructions
        );
        
        if ($stmt->execute()) {
            $pet_id = $conn->insert_id;
            
            // Generate QR code data
            $qr_data = generateQRData($pet_id, $name, $species, $breed, $age, $user_id);
            
            // Update pet with QR code
            $update_stmt = $conn->prepare("UPDATE pets SET qr_code = ? WHERE pet_id = ?");
            $update_stmt->bind_param("si", $qr_data, $pet_id);
            $update_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Pet registered successfully!";
        } else {
            throw new Exception("Failed to register pet: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error registering pet: " . $e->getMessage();
    }
    
    header("Location: dashboard.php");
    exit();
}

function generateQRData($pet_id, $name, $species, $breed, $age, $user_id) {
    $data = [
        'pet_id' => $pet_id,
        'name' => $name,
        'species' => $species,
        'breed' => $breed,
        'age' => $age,
        'user_id' => $user_id,
        'timestamp' => time()
    ];
    
    return json_encode($data);
}
?>