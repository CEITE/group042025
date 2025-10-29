<?php
session_start();
include("conn.php");

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch logged-in user info
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Define breed options
$dog_breeds = [
    "Labrador Retriever", "German Shepherd", "Golden Retriever", "French Bulldog", "Bulldog",
    "Poodle", "Beagle", "Rottweiler", "Yorkshire Terrier", "Boxer", "Dachshund", "Siberian Husky",
    "Great Dane", "Doberman Pinscher", "Australian Shepherd", "Cavalier King Charles Spaniel",
    "Shih Tzu", "Boston Terrier", "Pomeranian", "Havanese", "Shetland Sheepdog", "Brittany",
    "English Springer Spaniel", "Cocker Spaniel", "Border Collie", "Chihuahua", "Pembroke Welsh Corgi",
    "Miniature Schnauzer", "Maltese", "Mixed Breed", "Other"
];

$cat_breeds = [
    "Domestic Shorthair", "Domestic Longhair", "Siamese", "Persian", "Maine Coon", "Ragdoll",
    "Bengal", "British Shorthair", "Abyssinian", "Scottish Fold", "Sphynx", "Russian Blue",
    "American Shorthair", "Exotic Shorthair", "Birman", "Oriental Shorthair", "Burmese",
    "Tonkinese", "Himalayan", "Norwegian Forest Cat", "Devon Rex", "Cornish Rex", "Turkish Angora",
    "Mixed Breed", "Other"
];

// ✅ Handle pet registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_pet'])) {
    // Collect and sanitize form data
    $petName = trim($_POST['petName'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $age = !empty($_POST['age']) ? intval($_POST['age']) : 0;
    $color = trim($_POST['color'] ?? '');
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $birthDate = !empty($_POST['birthDate']) ? $_POST['birthDate'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $medicalNotes = trim($_POST['medicalNotes'] ?? '');
    $vetContact = trim($_POST['vetContact'] ?? '');
    
    // Medical history fields
    $previousConditions = trim($_POST['previousConditions'] ?? '');
    $vaccinationHistory = trim($_POST['vaccinationHistory'] ?? '');
    $surgicalHistory = trim($_POST['surgicalHistory'] ?? '');
    $medicationHistory = trim($_POST['medicationHistory'] ?? '');
    $hasExistingRecords = isset($_POST['hasExistingRecords']) ? 1 : 0;
    $recordsLocation = trim($_POST['recordsLocation'] ?? '');
    
    // NEW: Structured medical fields
    $last_vet_visit = !empty($_POST['last_vet_visit']) ? $_POST['last_vet_visit'] : null;
    $next_vet_visit = !empty($_POST['next_vet_visit']) ? $_POST['next_vet_visit'] : null;
    $rabies_vaccine_date = !empty($_POST['rabies_vaccine_date']) ? $_POST['rabies_vaccine_date'] : null;
    $dhpp_vaccine_date = !empty($_POST['dhpp_vaccine_date']) ? $_POST['dhpp_vaccine_date'] : null;
    $is_spayed_neutered = isset($_POST['is_spayed_neutered']) ? 1 : 0;
    $spay_neuter_date = !empty($_POST['spay_neuter_date']) ? $_POST['spay_neuter_date'] : null;
    
    // Profile picture handling
    $profilePicture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pet_profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($fileExtension), $allowedExtensions)) {
            // Generate unique filename
            $profilePicture = 'pet_' . uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $profilePicture;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // File uploaded successfully
            } else {
                $_SESSION['error'] = "❌ Failed to upload profile picture.";
                $profilePicture = null;
            }
        } else {
            $_SESSION['error'] = "❌ Invalid file type. Please upload JPG, JPEG, PNG, GIF, or WebP images only.";
        }
    }

    // Validate required fields
    if (empty($petName) || empty($species)) {
        $_SESSION['error'] = "❌ Pet name and species are required fields.";
    } else {
        try {
            // Generate unique QR code filename
            $qrCodeFilename = 'qr_' . uniqid() . '_' . time() . '.svg';
            
            // Convert species to lowercase to match ENUM('dog', 'cat')
            $species_lower = strtolower($species);
            
            // ✅ UPDATED: INSERT statement - ADDED profile_picture column
            $stmt = $conn->prepare("
                INSERT INTO pets (
                    user_id, name, species, breed, age, color, weight, 
                    birth_date, gender, medical_notes, vet_contact, 
                    previous_conditions, vaccination_history, surgical_history, 
                    medication_history, has_existing_records, records_location,
                    last_vet_visit, next_vet_visit, rabies_vaccine_date,
                    dhpp_vaccine_date, is_spayed_neutered, spay_neuter_date,
                    qr_code, profile_picture
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // ✅ UPDATED: 25 parameters in bind_param (added profile_picture)
            $bind_result = $stmt->bind_param("isssisdssssssssisssssisss", 
                $user_id, 
                $petName, 
                $species_lower,
                $breed, 
                $age, 
                $color, 
                $weight, 
                $birthDate, 
                $gender, 
                $medicalNotes, 
                $vetContact,
                $previousConditions,
                $vaccinationHistory,
                $surgicalHistory,
                $medicationHistory,
                $hasExistingRecords,
                $recordsLocation,
                $last_vet_visit,
                $next_vet_visit,
                $rabies_vaccine_date,
                $dhpp_vaccine_date,
                $is_spayed_neutered,
                $spay_neuter_date,
                $qrCodeFilename,
                $profilePicture
            );
            
            if (!$bind_result) {
                throw new Exception("Bind failed: " . $stmt->error);
            }
            
            if ($stmt->execute()) {
                $pet_id = $stmt->insert_id;
                
                // ✅ Generate direct link to view this pet's medical record
                $qrURL = "https://group042025.ceitesystems.com/view_pet_record.php?pet_id=" . $pet_id;

                // ✅ Generate the actual QR code image
                require_once 'phpqrcode/qrlib.php';

                $qrDir = 'qrcodes/';
                if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

                $qrPath = $qrDir . 'qr_' . $pet_id . '.png';
                QRcode::png($qrURL, $qrPath, QR_ECLEVEL_L, 4);

                // Generate QR data with enhanced medical history
                $qrData = generateQRData(
                    $user_id, $pet_id, $petName, $species, $breed, $age, 
                    $color, $weight, $birthDate, $gender, $medicalNotes, 
                    $vetContact, $previousConditions, $vaccinationHistory, 
                    $surgicalHistory, $medicationHistory, 
                    $last_vet_visit, $next_vet_visit, $rabies_vaccine_date,
                    $dhpp_vaccine_date, $is_spayed_neutered, $spay_neuter_date,
                    $user['name'], $user['email']
                );
                
                // Update the pet record with the QR code path and data
                $updateStmt = $conn->prepare("UPDATE pets SET qr_code = ?, qr_code_data = ? WHERE pet_id = ?");
                $updateStmt->bind_param("ssi", $qrPath, $qrData, $pet_id);
                $updateStmt->execute();
                $updateStmt->close();
                
                $_SESSION['success'] = "🎉 Pet '$petName' has been successfully registered! QR code has been generated.";
                $_SESSION['new_pet_id'] = $pet_id;
                $_SESSION['new_pet_data'] = $qrData;
                $_SESSION['new_pet_name'] = $petName;
                
                // Redirect to success page
                header("Location: register_pet.php?success=1");
                exit();
                
            } else {
                throw new Exception("Failed to register pet: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
}
// Enhanced function to generate QR code data with medical history
function generateQRData($user_id, $pet_id, $petName, $species, $breed, $age, $color, $weight, $birthDate, $gender, $medicalNotes, $vetContact, $previousConditions = '', $vaccinationHistory = '', $surgicalHistory = '', $medicationHistory = '', $last_vet_visit = '', $next_vet_visit = '', $rabies_vaccine_date = '', $dhpp_vaccine_date = '', $is_spayed_neutered = '', $spay_neuter_date = '', $ownerName = '', $ownerEmail = '') {
    $data = "PET MEDICAL RECORD - PETMEDQR\n";
    $data .= "================================\n\n";
    
    $data .= "BASIC INFORMATION:\n";
    $data .= "------------------\n";
    $data .= "Pet ID: PMQ-" . str_pad($pet_id, 6, '0', STR_PAD_LEFT) . "\n";
    $data .= "Name: " . ($petName ?: 'Not specified') . "\n";
    $data .= "Species: " . ($species ?: 'Not specified') . "\n";
    $data .= "Breed: " . ($breed ?: 'Unknown') . "\n";
    $data .= "Age: " . ($age ? $age . ' years' : 'Unknown') . "\n";
    $data .= "Color: " . ($color ?: 'Not specified') . "\n";
    $data .= "Weight: " . ($weight ? $weight . ' kg' : 'Not specified') . "\n";
    $data .= "Birth Date: " . ($birthDate ? date('M j, Y', strtotime($birthDate)) : 'Unknown') . "\n";
    $data .= "Gender: " . ($gender ?: 'Not specified') . "\n\n";
    
    $data .= "IMPORTANT MEDICAL DATES:\n";
    $data .= "------------------------\n";
    if ($last_vet_visit) $data .= "Last Vet Visit: " . date('M j, Y', strtotime($last_vet_visit)) . "\n";
    if ($next_vet_visit) $data .= "Next Vet Visit: " . date('M j, Y', strtotime($next_vet_visit)) . "\n";
    if ($rabies_vaccine_date) $data .= "Rabies Vaccine: " . date('M j, Y', strtotime($rabies_vaccine_date)) . "\n";
    if ($dhpp_vaccine_date) $data .= "DHPP/FVRCP Vaccine: " . date('M j, Y', strtotime($dhpp_vaccine_date)) . "\n";
    if ($is_spayed_neutered) $data .= "Spayed/Neutered: Yes" . ($spay_neuter_date ? " (" . date('M j, Y', strtotime($spay_neuter_date)) . ")" : "") . "\n";
    $data .= "\n";
    
    $data .= "CURRENT MEDICAL INFORMATION:\n";
    $data .= "----------------------------\n";
    $data .= "Medical Notes: " . ($medicalNotes ?: 'None') . "\n\n";
    
    $data .= "MEDICAL HISTORY:\n";
    $data .= "----------------\n";
    $data .= "Previous Conditions: " . ($previousConditions ?: 'None') . "\n";
    $data .= "Vaccination History: " . ($vaccinationHistory ?: 'None') . "\n";
    $data .= "Surgical History: " . ($surgicalHistory ?: 'None') . "\n";
    $data .= "Medication History: " . ($medicationHistory ?: 'None') . "\n\n";
    
    $data .= "VETERINARIAN CONTACT:\n";
    $data .= "---------------------\n";
    $data .= "Current Vet: " . ($vetContact ?: 'Not specified') . "\n\n";
    
    $data .= "OWNER INFORMATION:\n";
    $data .= "------------------\n";
    $data .= "Owner: " . ($ownerName ?: 'Not specified') . "\n";
    $data .= "Contact: " . ($ownerEmail ?: 'Not specified') . "\n\n";
    
    $data .= "REGISTRATION INFO:\n";
    $data .= "------------------\n";
    $data .= "Registered: " . date('M j, Y \a\t g:i A') . "\n";
    $data .= "Pet ID: " . $pet_id . "\n\n";
    
    $data .= "EMERGENCY CONTACT:\n";
    $data .= "==================\n";
    $data .= "If found, please contact owner immediately.\n";
    $data .= "Scan this QR code or visit PetMedQR system.\n\n";
    
    $data .= "Generated by PetMedQR - Your Pet's Health Companion";
    
    return $data;
}

// Check for success redirect
$showSuccess = isset($_GET['success']) && $_GET['success'] == '1' && isset($_SESSION['new_pet_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Pet - PetMedQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --radius: 16px;
            --radius-lg: 20px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #e0f2fe 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .registration-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .registration-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            width: 100%;
            max-width: 1100px;
            display: flex;
            min-height: 750px;
        }
        
        .registration-sidebar {
            width: 40%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        .registration-sidebar::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }
        
        .sidebar-content {
            position: relative;
            z-index: 2;
        }
        
        .sidebar-content h2 {
            font-weight: 800;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        
        .sidebar-content p {
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
        }
        
        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .features-list li i {
            background: rgba(255, 255, 255, 0.2);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.9rem;
        }
        
        .sidebar-footer {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        
        .registration-form {
            flex: 1;
            padding: 3rem;
            overflow-y: auto;
            max-height: 750px;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h1 {
            color: var(--primary-dark);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: var(--gray);
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gray-light);
            z-index: 1;
        }
        
        .progress-bar {
            position: absolute;
            top: 15px;
            left: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            z-index: 2;
            transition: width 0.3s ease;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 3;
        }
        
        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: #9ca3af;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .step.active .step-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
            color: white;
        }
        
        .step.completed .step-icon {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .step-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #9ca3af;
        }
        
        .step.active .step-label {
            color: var(--primary-dark);
        }
        
        .form-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .form-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .required::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--gray-light);
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .form-text {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-light);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-prev {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-next, .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-prev:hover {
            background: #e5e7eb;
        }
        
        .btn-next:hover, .btn-submit:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #0369a1 100%);
        }
        
        .success-state {
            text-align: center;
            padding: 3rem 2rem;
            display: none;
        }
        
        .success-state.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
            color: white;
        }
        
        .qr-preview {
            margin: 2rem auto;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: inline-block;
        }
        
        .alert {
            border-radius: var(--radius);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #7f1d1d;
            border-left: 4px solid var(--danger);
        }
        
        .bg-primary-light {
            background: var(--primary-light);
        }
        
        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .card-header {
            background: var(--primary-light);
            border-bottom: 1px solid var(--primary);
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .registration-card {
                flex-direction: column;
                min-height: auto;
            }
            
            .registration-sidebar {
                width: 100%;
                padding: 2rem 1.5rem;
            }
            
            .registration-form {
                padding: 2rem 1.5rem;
                max-height: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 1rem;
            }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Profile picture preview styles */
        .profile-picture-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid var(--gray-light);
            object-fit: cover;
        }
        
        .profile-preview-container {
            text-align: center;
            margin-bottom: 1rem;
        }

        /* Debug info styles */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.875rem;
        }
        
        .debug-info h6 {
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="registration-card">
            <!-- Sidebar -->
            <div class="registration-sidebar">
                <div class="sidebar-content">
                    <h2>Register Your Pet</h2>
                    <p>Welcome to the PetMedQR family! Register your pet to create their digital medical profile and generate a unique QR code for emergency situations.</p>
                    
                    <ul class="features-list">
                        <li>
                            <i class="fas fa-qrcode"></i>
                            <span>Generate Medical QR Code</span>
                        </li>
                        <li>
                            <i class="fas fa-heartbeat"></i>
                            <span>Digital Medical Records</span>
                        </li>
                        <li>
                            <i class="fas fa-history"></i>
                            <span>Complete Medical History</span>
                        </li>
                        <li>
                            <i class="fas fa-bell"></i>
                            <span>Vaccination Reminders</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt"></i>
                            <span>Emergency Contact Info</span>
                        </li>
                        <li>
                            <i class="fas fa-camera"></i>
                            <span>Profile Picture Upload</span>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-footer">
                    <div class="floating">
                        <i class="fas fa-paw fa-3x" style="opacity: 0.3;"></i>
                    </div>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">
                        Already have pets? <a href="user_pet_profile.php" style="color: white; text-decoration: underline;">View your pets</a>
                    </p>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="registration-form">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error']; ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Debug Information (visible during development) -->
                <?php if (isset($_POST['register_pet']) && isset($_SESSION['error'])): ?>
                <div class="debug-info">
                    <h6>Debug Information:</h6>
                    <?php
                    // Show debug info from error log
                    if (isset($actual_column_count)) {
                        echo "<div><strong>Table Columns:</strong> $actual_column_count</div>";
                    }
                    if (isset($insert_column_count)) {
                        echo "<div><strong>Insert Columns:</strong> $insert_column_count</div>";
                    }
                    if (isset($missing_columns) && !empty($missing_columns)) {
                        echo "<div><strong>Missing Columns:</strong> " . implode(', ', $missing_columns) . "</div>";
                    }
                    ?>
                    <div><small>Check your server error logs for complete debug details.</small></div>
                </div>
                <?php endif; ?>
                
                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
                    <div class="step active" data-step="1">
                        <div class="step-icon">1</div>
                        <div class="step-label">Basic Info</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-icon">2</div>
                        <div class="step-label">Details</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-icon">3</div>
                        <div class="step-label">Medical</div>
                    </div>
                    <div class="step" data-step="4">
                        <div class="step-icon">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>
                
                <?php if (!$showSuccess): ?>
                <form id="petRegistrationForm" method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="register_pet" value="1">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-section active" id="step1">
                        <div class="form-header">
                            <h1><i class="fas fa-paw me-2"></i>Basic Information</h1>
                            <p>Let's start with the essential details about your pet</p>
                        </div>
                        
                        <!-- Profile Picture Upload -->
                        <div class="form-group">
                            <label for="profile_picture" class="form-label">
                                <i class="fas fa-camera"></i>Profile Picture
                            </label>
                            <div class="profile-preview-container">
                                <img id="profilePicturePreview" src="" alt="Profile Picture Preview" 
                                     class="profile-picture-preview" style="display: none;">
                            </div>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                   accept="image/*" onchange="previewProfilePicture(this)">
                            <div class="form-text">Upload a clear photo of your pet (JPG, JPEG, PNG, GIF, WebP - max 5MB)</div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="petName" class="form-label required">
                                    <i class="fas fa-tag"></i>Pet Name
                                </label>
                                <input type="text" class="form-control" id="petName" name="petName" required 
                                       placeholder="Enter your pet's name" maxlength="100" value="<?php echo $_POST['petName'] ?? ''; ?>">
                                <div class="form-text">What's your pet's name? This will be displayed on their QR code.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="species" class="form-label required">
                                    <i class="fas fa-paw"></i>Species
                                </label>
                                <select class="form-select" id="species" name="species" required onchange="updateBreedOptions()">
                                    <option value="">Select Species</option>
                                    <option value="dog" <?php echo ($_POST['species'] ?? '') == 'dog' ? 'selected' : ''; ?>>🐕 Dog</option>
                                    <option value="cat" <?php echo ($_POST['species'] ?? '') == 'cat' ? 'selected' : ''; ?>>🐈 Cat</option>
                                </select>
                                <div class="form-text">What type of pet do you have?</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="breed" class="form-label required">
                                <i class="fas fa-dna"></i>Breed
                            </label>
                            <select class="form-select" id="breed" name="breed" required>
                                <option value="">Select Breed</option>
                                <!-- Breed options will be populated by JavaScript based on species selection -->
                            </select>
                            <div class="form-text">Select your pet's breed from the list</div>
                        </div>
                        
                        <div class="form-actions">
                            <div></div> <!-- Spacer -->
                            <button type="button" class="btn btn-next" onclick="nextStep(2)">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Physical Details -->
                    <div class="form-section" id="step2">
                        <div class="form-header">
                            <h1><i class="fas fa-palette me-2"></i>Physical Details</h1>
                            <p>Tell us more about your pet's appearance and characteristics</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="age" class="form-label required">
                                    <i class="fas fa-birthday-cake"></i>Age (years)
                                </label>
                                <input type="number" class="form-control" id="age" name="age" 
                                       min="0" max="50" step="1" placeholder="e.g., 2" value="<?php echo $_POST['age'] ?? ''; ?>">
                                <div class="form-text">Your pet's approximate age in years</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender" class="form-label required">
                                    <i class="fas fa-venus-mars"></i>Gender
                                </label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="color" class="form-label required">
                                    <i class="fas fa-paint-brush"></i>Color/Markings
                                </label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       placeholder="e.g., Brown with white spots" maxlength="50" value="<?php echo $_POST['color'] ?? ''; ?>">
                                <div class="form-text">Describe your pet's color and distinctive markings</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="weight" class="form-label required">
                                    <i class="fas fa-weight"></i>Weight (kg)
                                </label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       min="0" step="0.1" placeholder="e.g., 5.2" value="<?php echo $_POST['weight'] ?? ''; ?>">
                                <div class="form-text">Current weight in kilograms</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="birthDate" class="form-label required">
                                <i class="fas fa-calendar"></i>Birth Date
                            </label>
                            <input type="date" class="form-control" id="birthDate" name="birthDate" value="<?php echo $_POST['birthDate'] ?? ''; ?>">
                            <div class="form-text">If known, your pet's birth date</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-prev" onclick="prevStep(1)">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-next" onclick="nextStep(3)">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Medical Information & History -->
                    <div class="form-section" id="step3">
                        <div class="form-header">
                            <h1><i class="fas fa-heartbeat me-2"></i>Medical Information & History</h1>
                            <p>Help us keep track of your pet's health needs and medical background</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="medicalNotes" class="form-label">
                                <i class="fas fa-notes-medical"></i>Current Medical Notes & Allergies
                            </label>
                            <textarea class="form-control" id="medicalNotes" name="medicalNotes" 
                                      rows="3" placeholder="Any current medical conditions, allergies, medications, or special needs..." 
                                      maxlength="500"><?php echo $_POST['medicalNotes'] ?? ''; ?></textarea>
                            <div class="form-text">Current health issues that need immediate attention</div>
                        </div>

                        <!-- NEW: Structured Medical Dates Section -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>Important Medical Dates & Reminders
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="last_vet_visit" class="form-label required">
                                            <i class="fas fa-calendar-alt"></i>Last Vet Visit
                                        </label>
                                        <input type="date" class="form-control" id="last_vet_visit" name="last_vet_visit" 
                                               value="<?php echo $_POST['last_vet_visit'] ?? ''; ?>">
                                        <div class="form-text">Most recent veterinary appointment</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="next_vet_visit" class="form-label">
                                            <i class="fas fa-calendar-plus"></i>Next Vet Visit
                                        </label>
                                        <input type="date" class="form-control" id="next_vet_visit" name="next_vet_visit" 
                                               value="<?php echo $_POST['next_vet_visit'] ?? ''; ?>">
                                        <div class="form-text">Upcoming scheduled appointment</div>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="rabies_vaccine_date" class="form-label required">
                                            <i class="fas fa-syringe"></i>Rabies Vaccine Date
                                        </label>
                                        <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date" 
                                               value="<?php echo $_POST['rabies_vaccine_date'] ?? ''; ?>">
                                        <div class="form-text">Date of last rabies vaccination</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dhpp_vaccine_date" class="form-label required">
                                            <i class="fas fa-syringe"></i>DHPP/FVRCP Vaccine Date
                                        </label>
                                        <input type="date" class="form-control" id="dhpp_vaccine_date" name="dhpp_vaccine_date" 
                                               value="<?php echo $_POST['dhpp_vaccine_date'] ?? ''; ?>">
                                        <div class="form-text">Core vaccine for dogs (DHPP) or cats (FVRCP)</div>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_spayed_neutered" name="is_spayed_neutered" value="1" 
                                           <?php echo isset($_POST['is_spayed_neutered']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_spayed_neutered">
                                        <i class="fas fa-stethoscope"></i> My pet is spayed/neutered
                                    </label>
                                </div>
                                
                                <div class="form-group" id="spayNeuterDate" style="display: none;">
                                    <label for="spay_neuter_date" class="form-label">
                                        <i class="fas fa-calendar-day"></i>Spay/Neuter Date
                                    </label>
                                    <input type="date" class="form-control" id="spay_neuter_date" name="spay_neuter_date" 
                                           value="<?php echo $_POST['spay_neuter_date'] ?? ''; ?>">
                                    <div class="form-text">Date when the procedure was performed</div>
                                </div>
                            </div>
                        </div>

                        <!-- KEEP EXISTING: Medical History Section -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Medical History & Previous Records
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="previousConditions" class="form-label">
                                        <i class="fas fa-file-medical"></i>Previous Medical Conditions
                                    </label>
                                    <textarea class="form-control" id="previousConditions" name="previousConditions" 
                                              rows="3" placeholder="e.g., Past surgeries, illnesses, chronic conditions that are now resolved..." 
                                              maxlength="500"><?php echo $_POST['previousConditions'] ?? ''; ?></textarea>
                                    <div class="form-text">Any medical issues your pet has had in the past</div>
                                </div>

                                <div class="form-group">
                                    <label for="vaccinationHistory" class="form-label">
                                        <i class="fas fa-syringe"></i>Vaccination History
                                    </label>
                                    <textarea class="form-control" id="vaccinationHistory" name="vaccinationHistory" 
                                              rows="3" placeholder="e.g., Rabies (2023), DHPP (2024), Last flea/tick treatment..." 
                                              maxlength="500"><?php echo $_POST['vaccinationHistory'] ?? ''; ?></textarea>
                                    <div class="form-text">List vaccinations and their dates if known</div>
                                </div>

                                <div class="form-group">
                                    <label for="surgicalHistory" class="form-label">
                                        <i class="fas fa-procedures"></i>Surgical History
                                    </label>
                                    <textarea class="form-control" id="surgicalHistory" name="surgicalHistory" 
                                              rows="2" placeholder="e.g., Spayed/neutered date, other surgeries..." 
                                              maxlength="300"><?php echo $_POST['surgicalHistory'] ?? ''; ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="medicationHistory" class="form-label">
                                        <i class="fas fa-pills"></i>Previous Medications
                                    </label>
                                    <textarea class="form-control" id="medicationHistory" name="medicationHistory" 
                                              rows="2" placeholder="e.g., Previous long-term medications, treatments..." 
                                              maxlength="300"><?php echo $_POST['medicationHistory'] ?? ''; ?></textarea>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="hasExistingRecords" name="hasExistingRecords" value="1" 
                                           <?php echo isset($_POST['hasExistingRecords']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hasExistingRecords">
                                        <i class="fas fa-clipboard-list"></i> My pet has existing medical records at a veterinary clinic
                                    </label>
                                </div>

                                <div class="form-group" id="existingRecordsDetails" style="display: none;">
                                    <label for="recordsLocation" class="form-label">
                                        <i class="fas fa-clinic-medical"></i>Records Location
                                    </label>
                                    <input type="text" class="form-control" id="recordsLocation" name="recordsLocation" 
                                           placeholder="e.g., City Veterinary Hospital, Main Street Clinic..." 
                                           value="<?php echo $_POST['recordsLocation'] ?? ''; ?>">
                                    <div class="form-text">Where are your pet's previous records kept?</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vetContact" class="form-label">
                                <i class="fas fa-user-md"></i>Current Veterinarian Contact
                            </label>
                            <input type="text" class="form-control" id="vetContact" name="vetContact" 
                                   placeholder="e.g., Dr. Smith - City Vet Clinic (555-0123)" maxlength="100" value="<?php echo $_POST['vetContact'] ?? ''; ?>">
                            <div class="form-text">Your current veterinarian's name and contact information</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-prev" onclick="prevStep(2)">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-next" onclick="nextStep(4)">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Review & Submit -->
                    <div class="form-section" id="step4">
                        <div class="form-header">
                            <h1><i class="fas fa-check-circle me-2"></i>Review & Submit</h1>
                            <p>Please review all information before registering your pet</p>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Pet Information Summary</h6>
                                
                                <!-- Profile Picture in Review -->
                                <div class="text-center mb-4">
                                    <img id="reviewProfilePicture" src="" alt="Profile Picture" 
                                         class="profile-picture-preview" style="display: none;">
                                    <div id="noProfilePicture" class="text-muted">
                                        <i class="fas fa-camera fa-2x mb-2"></i><br>
                                        No profile picture uploaded
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <span id="reviewName">-</span></p>
                                        <p><strong>Species:</strong> <span id="reviewSpecies">-</span></p>
                                        <p><strong>Breed:</strong> <span id="reviewBreed">-</span></p>
                                        <p><strong>Age:</strong> <span id="reviewAge">-</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Gender:</strong> <span id="reviewGender">-</span></p>
                                        <p><strong>Color:</strong> <span id="reviewColor">-</span></p>
                                        <p><strong>Weight:</strong> <span id="reviewWeight">-</span></p>
                                        <p><strong>Birth Date:</strong> <span id="reviewBirthDate">-</span></p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h6 class="mb-2">Medical Dates:</h6>
                                    <p><strong>Last Vet Visit:</strong> <span id="reviewLastVetVisit">-</span></p>
                                    <p><strong>Next Vet Visit:</strong> <span id="reviewNextVetVisit">-</span></p>
                                    <p><strong>Rabies Vaccine:</strong> <span id="reviewRabiesVaccine">-</span></p>
                                    <p><strong>DHPP Vaccine:</strong> <span id="reviewDhppVaccine">-</span></p>
                                    <p><strong>Spayed/Neutered:</strong> <span id="reviewSpayedNeutered">-</span></p>
                                    <p><strong>Spay/Neuter Date:</strong> <span id="reviewSpayNeuterDate">-</span></p>
                                </div>
                                
                                <div class="mt-3">
                                    <h6 class="mb-2">Medical History:</h6>
                                    <p><strong>Current Medical Notes:</strong> <span id="reviewMedicalNotes">-</span></p>
                                    <p><strong>Previous Conditions:</strong> <span id="reviewPreviousConditions">-</span></p>
                                    <p><strong>Vaccination History:</strong> <span id="reviewVaccinationHistory">-</span></p>
                                    <p><strong>Surgical History:</strong> <span id="reviewSurgicalHistory">-</span></p>
                                    <p><strong>Medication History:</strong> <span id="reviewMedicationHistory">-</span></p>
                                    <p><strong>Has Existing Records:</strong> <span id="reviewHasExistingRecords">-</span></p>
                                    <p><strong>Records Location:</strong> <span id="reviewRecordsLocation">-</span></p>
                                    <p><strong>Veterinarian:</strong> <span id="reviewVetContact">-</span></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="confirmInfo" required>
                            <label class="form-check-label" for="confirmInfo">
                                I confirm that all information provided is accurate and complete
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-prev" onclick="prevStep(3)">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" class="btn btn-submit" id="submitBtn">
                                <i class="fas fa-paw"></i> Register Pet
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <!-- Success State -->
                <div class="success-state active" id="successState">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Registration Successful! 🎉</h2>
                    <p class="text-muted mb-4">
                        <strong><?php echo htmlspecialchars($_SESSION['new_pet_name'] ?? 'Your pet'); ?></strong> 
                        has been registered successfully and a medical QR code has been generated.
                    </p>
                    
                    <div class="qr-preview" id="successQrCode"></div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Pet ID:</strong> PMQ-<?php echo str_pad($_SESSION['new_pet_id'] ?? '000000', 6, '0', STR_PAD_LEFT); ?> | 
                        <strong>Registered:</strong> <?php echo date('M j, Y \a\t g:i A'); ?>
                    </div>
                    
                    <div class="d-grid gap-2 col-md-8 mx-auto mt-4">
                        <a href="user_pet_profile.php" class="btn btn-submit">
                            <i class="fas fa-dog"></i> View My Pets
                        </a>
                        <a href="register_pet.php" class="btn btn-prev">
                            <i class="fas fa-plus"></i> Register Another Pet
                        </a>
                    </div>
                </div>
                <?php 
                // Clear success session data
                unset($_SESSION['new_pet_id'], $_SESSION['new_pet_data'], $_SESSION['new_pet_name']);
                endif; 
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script>
        // Breed data
        const dogBreeds = <?php echo json_encode($dog_breeds); ?>;
        const catBreeds = <?php echo json_encode($cat_breeds); ?>;
        
        let currentStep = 1;
        const totalSteps = 4;
        
        function updateProgress() {
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;
            
            // Update step indicators
            document.querySelectorAll('.step').forEach((step, index) => {
                const stepNumber = index + 1;
                if (stepNumber < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (stepNumber === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
        }
        
        function showStep(stepNumber) {
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(`step${stepNumber}`).classList.add('active');
            currentStep = stepNumber;
            updateProgress();
            
            // Scroll to top of form
            document.querySelector('.registration-form').scrollTo(0, 0);
        }
        
        function nextStep(next) {
            if (validateStep(currentStep)) {
                if (next === 4) {
                    updateReviewSection();
                }
                showStep(next);
            }
        }
        
        function prevStep(prev) {
            showStep(prev);
        }
        
        function validateStep(step) {
            let isValid = true;
            let errorMessage = '';
            
            if (step === 1) {
                const petName = document.getElementById('petName').value.trim();
                const species = document.getElementById('species').value;
                const breed = document.getElementById('breed').value;
                
                if (!petName) {
                    errorMessage = 'Please enter your pet\'s name';
                    document.getElementById('petName').focus();
                    isValid = false;
                } else if (!species) {
                    errorMessage = 'Please select your pet\'s species';
                    document.getElementById('species').focus();
                    isValid = false;
                } else if (!breed) {
                    errorMessage = 'Please select your pet\'s breed';
                    document.getElementById('breed').focus();
                    isValid = false;
                }
            }
            
            if (!isValid && errorMessage) {
                alert('❌ ' + errorMessage);
            }
            
            return isValid;
        }
        
        function updateBreedOptions() {
            const species = document.getElementById('species').value;
            const breedSelect = document.getElementById('breed');
            
            // Clear existing options except the first one
            breedSelect.innerHTML = '<option value="">Select Breed</option>';
            
            if (species === 'dog') {
                dogBreeds.forEach(breed => {
                    const option = document.createElement('option');
                    option.value = breed;
                    option.textContent = breed;
                    breedSelect.appendChild(option);
                });
            } else if (species === 'cat') {
                catBreeds.forEach(breed => {
                    const option = document.createElement('option');
                    option.value = breed;
                    option.textContent = breed;
                    breedSelect.appendChild(option);
                });
            }
            
            // Set the selected breed if it exists in POST data
            const selectedBreed = "<?php echo $_POST['breed'] ?? ''; ?>";
            if (selectedBreed) {
                breedSelect.value = selectedBreed;
            }
        }
        
        function updateReviewSection() {
            // Profile picture preview in review
            const profilePictureInput = document.getElementById('profile_picture');
            const reviewProfilePicture = document.getElementById('reviewProfilePicture');
            const noProfilePicture = document.getElementById('noProfilePicture');
            
            if (profilePictureInput.files && profilePictureInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    reviewProfilePicture.src = e.target.result;
                    reviewProfilePicture.style.display = 'block';
                    noProfilePicture.style.display = 'none';
                }
                reader.readAsDataURL(profilePictureInput.files[0]);
            } else {
                reviewProfilePicture.style.display = 'none';
                noProfilePicture.style.display = 'block';
            }
            
            // Basic information
            document.getElementById('reviewName').textContent = document.getElementById('petName').value || 'Not specified';
            document.getElementById('reviewSpecies').textContent = document.getElementById('species').value || 'Not specified';
            document.getElementById('reviewBreed').textContent = document.getElementById('breed').value || 'Not specified';
            document.getElementById('reviewAge').textContent = document.getElementById('age').value ? document.getElementById('age').value + ' years' : 'Not specified';
            document.getElementById('reviewGender').textContent = document.getElementById('gender').value || 'Not specified';
            document.getElementById('reviewColor').textContent = document.getElementById('color').value || 'Not specified';
            document.getElementById('reviewWeight').textContent = document.getElementById('weight').value ? document.getElementById('weight').value + ' kg' : 'Not specified';
            document.getElementById('reviewBirthDate').textContent = document.getElementById('birthDate').value || 'Not specified';
            
            // New medical dates
            document.getElementById('reviewLastVetVisit').textContent = document.getElementById('last_vet_visit').value || 'Not specified';
            document.getElementById('reviewNextVetVisit').textContent = document.getElementById('next_vet_visit').value || 'Not specified';
            document.getElementById('reviewRabiesVaccine').textContent = document.getElementById('rabies_vaccine_date').value || 'Not specified';
            document.getElementById('reviewDhppVaccine').textContent = document.getElementById('dhpp_vaccine_date').value || 'Not specified';
            document.getElementById('reviewSpayedNeutered').textContent = document.getElementById('is_spayed_neutered').checked ? 'Yes' : 'No';
            document.getElementById('reviewSpayNeuterDate').textContent = document.getElementById('spay_neuter_date').value || 'Not applicable';
            
            // Medical information
            document.getElementById('reviewMedicalNotes').textContent = document.getElementById('medicalNotes').value || 'None';
            document.getElementById('reviewPreviousConditions').textContent = document.getElementById('previousConditions').value || 'None';
            document.getElementById('reviewVaccinationHistory').textContent = document.getElementById('vaccinationHistory').value || 'None';
            document.getElementById('reviewSurgicalHistory').textContent = document.getElementById('surgicalHistory').value || 'None';
            document.getElementById('reviewMedicationHistory').textContent = document.getElementById('medicationHistory').value || 'None';
            document.getElementById('reviewHasExistingRecords').textContent = document.getElementById('hasExistingRecords').checked ? 'Yes' : 'No';
            document.getElementById('reviewRecordsLocation').textContent = document.getElementById('recordsLocation').value || 'Not specified';
            document.getElementById('reviewVetContact').textContent = document.getElementById('vetContact').value || 'Not specified';
        }
        
        // Profile picture preview function
        function previewProfilePicture(input) {
            const preview = document.getElementById('profilePicturePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
                
                // Validate file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ File size too large. Please choose an image smaller than 5MB.');
                    input.value = '';
                    preview.style.display = 'none';
                }
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Toggle spay/neuter date
        document.getElementById('is_spayed_neutered').addEventListener('change', function() {
            document.getElementById('spayNeuterDate').style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                document.getElementById('spay_neuter_date').value = '';
            }
        });

        // Toggle existing records details
        document.getElementById('hasExistingRecords').addEventListener('change', function() {
            const detailsDiv = document.getElementById('existingRecordsDetails');
            detailsDiv.style.display = this.checked ? 'block' : 'none';
            
            // Clear the field if unchecked
            if (!this.checked) {
                document.getElementById('recordsLocation').value = '';
            }
        });
        
        // Initialize the existing records and spay/neuter displays
        document.addEventListener('DOMContentLoaded', function() {
            // Existing records
            const hasExistingRecords = document.getElementById('hasExistingRecords');
            const recordsDetailsDiv = document.getElementById('existingRecordsDetails');
            if (hasExistingRecords.checked) {
                recordsDetailsDiv.style.display = 'block';
            }
            
            // Spay/neuter
            const spayNeuterCheckbox = document.getElementById('is_spayed_neutered');
            const spayNeuterDateDiv = document.getElementById('spayNeuterDate');
            if (spayNeuterCheckbox.checked) {
                spayNeuterDateDiv.style.display = 'block';
            }
            
            // Initialize breed options based on selected species
            updateBreedOptions();
        });
        
        // Form submission
        document.getElementById('petRegistrationForm').addEventListener('submit', function(e) {
            const confirmCheckbox = document.getElementById('confirmInfo');
            
            if (!confirmCheckbox.checked) {
                e.preventDefault();
                alert('❌ Please confirm that all information is accurate by checking the box.');
                confirmCheckbox.focus();
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
            submitBtn.disabled = true;
            
            // Add loading class to form
            document.getElementById('petRegistrationForm').classList.add('loading');
            
            // Form will submit naturally to PHP
        });
        
        function generateSuccessQRCode() {
            const petName = "<?php echo $_SESSION['new_pet_name'] ?? 'Your Pet'; ?>";
            const petId = "<?php echo $_SESSION['new_pet_id'] ?? '000000'; ?>";
            const qrData = `PET: ${petName}\nID: PMQ-${String(petId).padStart(6, '0')}\nRegistered: ${new Date().toLocaleDateString()}\nStatus: Active 🐾`;
            
            const qr = qrcode(0, 'M');
            qr.addData(qrData);
            qr.make();
            
            document.getElementById('successQrCode').innerHTML = qr.createSvgTag({
                scalable: true,
                margin: 2,
                color: '#000',
                background: '#fff'
            });
        }
        
        // Initialize
        updateProgress();
        
        // Generate QR code for success state if needed
        <?php if ($showSuccess): ?>
            generateSuccessQRCode();
        <?php endif; ?>
        
        // Add real-time validation
        document.getElementById('petName').addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
        
        document.getElementById('species').addEventListener('change', function() {
            if (!this.value) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
        
        document.getElementById('breed').addEventListener('change', function() {
            if (!this.value) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
    </script>
</body>
</html>
