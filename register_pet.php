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
    
    // Structured medical fields
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

    // ✅ ENHANCED VALIDATION WITH SPECIFIC ERROR TRACKING
    $missing_fields = [];
    
    // Check required fields
    if (empty($petName)) $missing_fields[] = "Pet Name";
    if (empty($species)) $missing_fields[] = "Species";
    if (empty($breed)) $missing_fields[] = "Breed";
    if (empty($age)) $missing_fields[] = "Age";
    if (empty($gender)) $missing_fields[] = "Gender";
    if (empty($color)) $missing_fields[] = "Color/Markings";
    if (empty($weight)) $missing_fields[] = "Weight";
    if (empty($medicalNotes)) $missing_fields[] = "Current Medical Notes & Allergies";
    if (empty($last_vet_visit)) $missing_fields[] = "Last Vet Visit";
    if (empty($rabies_vaccine_date)) $missing_fields[] = "Rabies Vaccine Date";
    if (empty($dhpp_vaccine_date)) $missing_fields[] = "DHPP/FVRCP Vaccine Date";
    if (empty($vetContact)) $missing_fields[] = "Current Veterinarian Contact";
    
    if (!empty($missing_fields)) {
        $_SESSION['error'] = "❌ Please fill in all required fields. Missing: " . implode(', ', $missing_fields);
    } else {
        try {
            // Generate unique QR code filename
            $qrCodeFilename = 'qr_' . uniqid() . '_' . time() . '.svg';
            
            // Convert species to lowercase to match ENUM('dog', 'cat')
            $species_lower = strtolower($species);
            
            // Generate QR data first (we need it for the insert)
            $qrData = generateQRData(
                $user_id, 0, $petName, $species, $breed, $age, 
                $color, $weight, $birthDate, $gender, $medicalNotes, 
                $vetContact, $previousConditions, $vaccinationHistory, 
                $surgicalHistory, $medicationHistory, 
                $last_vet_visit, $next_vet_visit, $rabies_vaccine_date,
                $dhpp_vaccine_date, $is_spayed_neutered, $spay_neuter_date,
                $user['name'], $user['email']
            );
            
            // ✅ CORRECTED: INSERT statement that matches EXACT table structure
            $sql = "
                INSERT INTO pets (
                    user_id, name, species, breed, age, color, weight, 
                    birth_date, gender, medical_notes, vet_contact, 
                    previous_conditions, vaccination_history, surgical_history, 
                    medication_history, has_existing_records, records_location,
                    last_vet_visit, next_vet_visit, rabies_vaccine_date,
                    dhpp_vaccine_date, is_spayed_neutered, spay_neuter_date,
                    qr_code, qr_code_data, profile_picture
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            // ✅ CORRECTED: 25 parameters in bind_param (matches 25 columns we're inserting)
            $bind_result = $stmt->bind_param("isssisdssssssssisssssissss", 
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
                $qrData,
                $profilePicture
            );
            
            if (!$bind_result) {
                throw new Exception("Bind failed: " . $stmt->error);
            }
            
            if ($stmt->execute()) {
                $pet_id = $stmt->insert_id;
                
                // Generate direct link to view this pet's medical record
                $qrURL = "https://group042025.ceitesystems.com/view_pet_record.php?pet_id=" . $pet_id;

                // Generate the actual QR code image
                require_once 'phpqrcode/qrlib.php';

                $qrDir = 'qrcodes/';
                if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

                $qrPath = $qrDir . 'qr_' . $pet_id . '.png';
                QRcode::png($qrURL, $qrPath, QR_ECLEVEL_L, 4);

                // Update the pet record with the QR code path
                $updateStmt = $conn->prepare("UPDATE pets SET qr_code = ? WHERE pet_id = ?");
                $updateStmt->bind_param("si", $qrPath, $pet_id);
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
            error_log("Registration Error: " . $e->getMessage());
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
        /* Your existing CSS styles remain exactly the same */
        :root {
            --pink: #ffd6e7;
            --pink-2: #f7c5e0;
            --pink-dark: #ec4899;
            --pink-darker: #db2777;
            --pink-light: #fff4f8;
            --pink-gradient: linear-gradient(135deg, #f9a8d4 0%, #ec4899 100%);
            --pink-gradient-light: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
            --blue: #4a6cf7;
            --green: #10b981;
            --orange: #f59e0b;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            color: #1f2937;
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
            background: var(--pink-gradient);
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
            color: var(--pink-darker);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #6b7280;
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
            background: #e5e7eb;
            z-index: 1;
        }
        
        .progress-bar {
            position: absolute;
            top: 15px;
            left: 0;
            height: 2px;
            background: var(--pink-gradient);
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
            border: 2px solid #e5e7eb;
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
            background: var(--pink-gradient);
            border-color: var(--pink-dark);
            color: white;
        }
        
        .step.completed .step-icon {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        
        .step-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #9ca3af;
        }
        
        .step.active .step-label {
            color: var(--pink-darker);
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
            color: var(--pink-darker);
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
            color: #ef4444;
            margin-left: 0.25rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--pink-dark);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        
        .form-text {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
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
            background: var(--pink-gradient);
            color: white;
        }
        
        .btn-prev:hover {
            background: #e5e7eb;
        }
        
        .btn-next:hover, .btn-submit:hover {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
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
            background: var(--green);
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
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #7f1d1d;
            border-left: 4px solid #ef4444;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
            color: #1e3a8a;
            border-left: 4px solid #3b82f6;
        }
        
        .bg-pink-light {
            background: var(--pink-light);
        }
        
        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .card-header {
            background: var(--pink-gradient-light);
            border-bottom: 1px solid var(--pink);
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
            border: 2px solid #e5e7eb;
            object-fit: cover;
        }
        
        .profile-preview-container {
            text-align: center;
            margin-bottom: 1rem;
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

                <?php if ($showSuccess): ?>
                    <!-- Success State -->
                    <div class="success-state active">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 class="mb-3">Registration Successful!</h2>
                        <p class="mb-4">Your pet <strong><?php echo $_SESSION['new_pet_name']; ?></strong> has been successfully registered with PetMedQR.</p>
                        
                        <div class="qr-preview">
                            <img src="qrcodes/qr_<?php echo $_SESSION['new_pet_id']; ?>.png" alt="QR Code" width="200" height="200">
                        </div>
                        
                        <p class="mb-4">A unique QR code has been generated for your pet's medical records.</p>
                        
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <a href="user_pet_profile.php" class="btn btn-prev">
                                <i class="fas fa-paw"></i> View All Pets
                            </a>
                            <a href="register_pet.php" class="btn btn-submit">
                                <i class="fas fa-plus"></i> Register Another Pet
                            </a>
                            <a href="dashboard.php" class="btn" style="background: var(--blue); color: white;">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Multi-step Form -->
                    <div class="form-header">
                        <h1>Register New Pet</h1>
                        <p>Complete all sections to create your pet's medical profile</p>
                    </div>
                    
                    <!-- Progress Steps -->
                    <div class="progress-steps">
                        <div class="progress-bar" id="progressBar" style="width: 25%;"></div>
                        <div class="step active" data-step="1">
                            <div class="step-icon">1</div>
                            <div class="step-label">Basic Info</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-icon">2</div>
                            <div class="step-label">Medical Info</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-icon">3</div>
                            <div class="step-label">History</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-icon">4</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>
                    
                    <form id="petRegistrationForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="register_pet" value="1">
                        
                        <!-- Step 1: Basic Information -->
                        <div class="form-section active" id="step1">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i> Basic Information
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required" for="petName">Pet Name</label>
                                    <input type="text" class="form-control" id="petName" name="petName" required 
                                           placeholder="Enter pet's name" value="<?php echo $_POST['petName'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="species">Species</label>
                                    <select class="form-select" id="species" name="species" required>
                                        <option value="">Select Species</option>
                                        <option value="dog" <?php echo (($_POST['species'] ?? '') == 'dog') ? 'selected' : ''; ?>>Dog</option>
                                        <option value="cat" <?php echo (($_POST['species'] ?? '') == 'cat') ? 'selected' : ''; ?>>Cat</option>
                                        <option value="other" <?php echo (($_POST['species'] ?? '') == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="breed">Breed</label>
                                    <input type="text" class="form-control" id="breed" name="breed" required 
                                           placeholder="Enter breed" value="<?php echo $_POST['breed'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="age">Age (years)</label>
                                    <input type="number" class="form-control" id="age" name="age" min="0" max="30" step="0.5" required 
                                           placeholder="Enter age" value="<?php echo $_POST['age'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="gender">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo (($_POST['gender'] ?? '') == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo (($_POST['gender'] ?? '') == 'female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="color">Color/Markings</label>
                                    <input type="text" class="form-control" id="color" name="color" required 
                                           placeholder="Describe color and markings" value="<?php echo $_POST['color'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="weight">Weight (kg)</label>
                                    <input type="number" class="form-control" id="weight" name="weight" min="0" max="100" step="0.1" required 
                                           placeholder="Enter weight" value="<?php echo $_POST['weight'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="birthDate">Birth Date</label>
                                    <input type="date" class="form-control" id="birthDate" name="birthDate" 
                                           value="<?php echo $_POST['birthDate'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="profile_picture">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">Optional: Upload a clear photo of your pet (JPG, PNG, GIF, WebP)</div>
                                <div class="profile-preview-container mt-2" id="profilePreview" style="display: none;">
                                    <img id="profilePreviewImg" class="profile-picture-preview" src="" alt="Profile Preview">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <div></div> <!-- Empty div for spacing -->
                                <button type="button" class="btn btn-next" onclick="nextStep(2)">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Medical Information -->
                        <div class="form-section" id="step2">
                            <h3 class="section-title">
                                <i class="fas fa-heartbeat"></i> Medical Information
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required" for="last_vet_visit">Last Vet Visit</label>
                                    <input type="date" class="form-control" id="last_vet_visit" name="last_vet_visit" required 
                                           value="<?php echo $_POST['last_vet_visit'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="next_vet_visit">Next Scheduled Visit</label>
                                    <input type="date" class="form-control" id="next_vet_visit" name="next_vet_visit" 
                                           value="<?php echo $_POST['next_vet_visit'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="rabies_vaccine_date">Rabies Vaccine Date</label>
                                    <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date" required 
                                           value="<?php echo $_POST['rabies_vaccine_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required" for="dhpp_vaccine_date">DHPP/FVRCP Vaccine Date</label>
                                    <input type="date" class="form-control" id="dhpp_vaccine_date" name="dhpp_vaccine_date" required 
                                           value="<?php echo $_POST['dhpp_vaccine_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="is_spayed_neutered">
                                        <input type="checkbox" id="is_spayed_neutered" name="is_spayed_neutered" value="1" 
                                               <?php echo (($_POST['is_spayed_neutered'] ?? '') == '1') ? 'checked' : ''; ?>>
                                        Spayed/Neutered
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="spay_neuter_date">Spay/Neuter Date</label>
                                    <input type="date" class="form-control" id="spay_neuter_date" name="spay_neuter_date" 
                                           value="<?php echo $_POST['spay_neuter_date'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="medicalNotes">Current Medical Notes & Allergies</label>
                                <textarea class="form-control" id="medicalNotes" name="medicalNotes" rows="4" required 
                                          placeholder="Describe any current medical conditions, treatments, or allergies"><?php echo $_POST['medicalNotes'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="vetContact">Current Veterinarian Contact</label>
                                <textarea class="form-control" id="vetContact" name="vetContact" rows="3" required 
                                          placeholder="Veterinarian name, clinic, phone number, and address"><?php echo $_POST['vetContact'] ?? ''; ?></textarea>
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
                        
                        <!-- Step 3: Medical History -->
                        <div class="form-section" id="step3">
                            <h3 class="section-title">
                                <i class="fas fa-history"></i> Medical History
                            </h3>
                            
                            <div class="form-group">
                                <label class="form-label" for="previousConditions">Previous Medical Conditions</label>
                                <textarea class="form-control" id="previousConditions" name="previousConditions" rows="3" 
                                          placeholder="Any previous illnesses, surgeries, or chronic conditions"><?php echo $_POST['previousConditions'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="vaccinationHistory">Vaccination History</label>
                                <textarea class="form-control" id="vaccinationHistory" name="vaccinationHistory" rows="3" 
                                          placeholder="Complete vaccination history beyond required vaccines"><?php echo $_POST['vaccinationHistory'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="surgicalHistory">Surgical History</label>
                                <textarea class="form-control" id="surgicalHistory" name="surgicalHistory" rows="3" 
                                          placeholder="Any past surgeries with dates"><?php echo $_POST['surgicalHistory'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="medicationHistory">Medication History</label>
                                <textarea class="form-control" id="medicationHistory" name="medicationHistory" rows="3" 
                                          placeholder="Past and current medications"><?php echo $_POST['medicationHistory'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header bg-pink-light">
                                    Existing Medical Records
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label" for="hasExistingRecords">
                                            <input type="checkbox" id="hasExistingRecords" name="hasExistingRecords" value="1" 
                                                   <?php echo (($_POST['hasExistingRecords'] ?? '') == '1') ? 'checked' : ''; ?>>
                                            I have existing paper/digital medical records for this pet
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="recordsLocation">Location of Existing Records</label>
                                        <input type="text" class="form-control" id="recordsLocation" name="recordsLocation" 
                                               placeholder="Where are your existing records stored?" value="<?php echo $_POST['recordsLocation'] ?? ''; ?>">
                                        <div class="form-text">Optional: Specify if you have records at a specific clinic or in digital format</div>
                                    </div>
                                </div>
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
                            <h3 class="section-title">
                                <i class="fas fa-clipboard-check"></i> Review & Submit
                            </h3>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Please review all information carefully before submitting. This will create your pet's permanent medical record.
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header bg-pink-light">
                                    Basic Information Summary
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Pet Name:</strong> <span id="review_petName"></span></p>
                                            <p><strong>Species:</strong> <span id="review_species"></span></p>
                                            <p><strong>Breed:</strong> <span id="review_breed"></span></p>
                                            <p><strong>Age:</strong> <span id="review_age"></span> years</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Gender:</strong> <span id="review_gender"></span></p>
                                            <p><strong>Color:</strong> <span id="review_color"></span></p>
                                            <p><strong>Weight:</strong> <span id="review_weight"></span> kg</p>
                                            <p><strong>Birth Date:</strong> <span id="review_birthDate"></span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header bg-pink-light">
                                    Medical Information Summary
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Last Vet Visit:</strong> <span id="review_last_vet_visit"></span></p>
                                            <p><strong>Next Vet Visit:</strong> <span id="review_next_vet_visit"></span></p>
                                            <p><strong>Rabies Vaccine:</strong> <span id="review_rabies_vaccine_date"></span></p>
                                            <p><strong>DHPP/FVRCP Vaccine:</strong> <span id="review_dhpp_vaccine_date"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Spayed/Neutered:</strong> <span id="review_is_spayed_neutered"></span></p>
                                            <p><strong>Spay/Neuter Date:</strong> <span id="review_spay_neuter_date"></span></p>
                                            <p><strong>Veterinarian:</strong> <span id="review_vetContact"></span></p>
                                        </div>
                                    </div>
                                    <p><strong>Medical Notes:</strong> <span id="review_medicalNotes"></span></p>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="confirmAccuracy" required>
                                <label class="form-check-label" for="confirmAccuracy">
                                    I confirm that all information provided is accurate to the best of my knowledge
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-prev" onclick="prevStep(3)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="submit" class="btn btn-submit" id="submitButton" disabled>
                                    <i class="fas fa-paw"></i> Register Pet
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form navigation
        function nextStep(step) {
            // Validate current step before proceeding
            if (validateStep(step - 1)) {
                document.querySelectorAll('.form-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById('step' + step).classList.add('active');
                
                document.querySelectorAll('.step').forEach(stepEl => {
                    stepEl.classList.remove('active');
                });
                document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
                
                // Update progress bar
                const progress = (step / 4) * 100;
                document.getElementById('progressBar').style.width = progress + '%';
                
                // If moving to review step, populate review fields
                if (step === 4) {
                    populateReviewFields();
                }
            }
        }
        
        function prevStep(step) {
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById('step' + step).classList.add('active');
            
            document.querySelectorAll('.step').forEach(stepEl => {
                stepEl.classList.remove('active');
            });
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            // Update progress bar
            const progress = (step / 4) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
        }
        
        // Step validation
        function validateStep(step) {
            const currentStep = document.getElementById('step' + step);
            const inputs = currentStep.querySelectorAll('input[required], select[required], textarea[required]');
            
            let isValid = true;
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields before proceeding.');
            }
            
            return isValid;
        }
        
        // Populate review fields
        function populateReviewFields() {
            // Basic Information
            document.getElementById('review_petName').textContent = document.getElementById('petName').value || 'Not provided';
            document.getElementById('review_species').textContent = document.getElementById('species').value || 'Not provided';
            document.getElementById('review_breed').textContent = document.getElementById('breed').value || 'Not provided';
            document.getElementById('review_age').textContent = document.getElementById('age').value || 'Not provided';
            document.getElementById('review_gender').textContent = document.getElementById('gender').value || 'Not provided';
            document.getElementById('review_color').textContent = document.getElementById('color').value || 'Not provided';
            document.getElementById('review_weight').textContent = document.getElementById('weight').value || 'Not provided';
            document.getElementById('review_birthDate').textContent = formatDate(document.getElementById('birthDate').value) || 'Not provided';
            
            // Medical Information
            document.getElementById('review_last_vet_visit').textContent = formatDate(document.getElementById('last_vet_visit').value) || 'Not provided';
            document.getElementById('review_next_vet_visit').textContent = formatDate(document.getElementById('next_vet_visit').value) || 'Not provided';
            document.getElementById('review_rabies_vaccine_date').textContent = formatDate(document.getElementById('rabies_vaccine_date').value) || 'Not provided';
            document.getElementById('review_dhpp_vaccine_date').textContent = formatDate(document.getElementById('dhpp_vaccine_date').value) || 'Not provided';
            document.getElementById('review_is_spayed_neutered').textContent = document.getElementById('is_spayed_neutered').checked ? 'Yes' : 'No';
            document.getElementById('review_spay_neuter_date').textContent = formatDate(document.getElementById('spay_neuter_date').value) || 'Not applicable';
            document.getElementById('review_medicalNotes').textContent = document.getElementById('medicalNotes').value || 'Not provided';
            document.getElementById('review_vetContact').textContent = document.getElementById('vetContact').value ? document.getElementById('vetContact').value.substring(0, 50) + '...' : 'Not provided';
        }
        
        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        
        // Enable submit button when confirmation is checked
        document.getElementById('confirmAccuracy')?.addEventListener('change', function() {
            document.getElementById('submitButton').disabled = !this.checked;
        });
        
        // Profile picture preview
        document.getElementById('profile_picture')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreviewImg').src = e.target.result;
                    document.getElementById('profilePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Real-time validation
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        });
        
        // Toggle spay/neuter date based on checkbox
        document.getElementById('is_spayed_neutered')?.addEventListener('change', function() {
            const dateField = document.getElementById('spay_neuter_date');
            if (this.checked) {
                dateField.removeAttribute('disabled');
            } else {
                dateField.setAttribute('disabled', 'disabled');
                dateField.value = '';
            }
        });
        
        // Initialize spay/neuter date field state
        document.addEventListener('DOMContentLoaded', function() {
            const spayNeuterCheckbox = document.getElementById('is_spayed_neutered');
            const spayNeuterDate = document.getElementById('spay_neuter_date');
            
            if (spayNeuterCheckbox && spayNeuterDate) {
                if (!spayNeuterCheckbox.checked) {
                    spayNeuterDate.setAttribute('disabled', 'disabled');
                }
            }
        });
    </script>
</body>
</html>
