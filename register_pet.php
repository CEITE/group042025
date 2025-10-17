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
    $age = !empty($_POST['age']) ? floatval($_POST['age']) : 0;
    $color = trim($_POST['color'] ?? '');
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $birthDate = !empty($_POST['birthDate']) ? $_POST['birthDate'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $medicalNotes = trim($_POST['medicalNotes'] ?? '');
    $vetContact = trim($_POST['vetContact'] ?? '');
    
    // Validate required fields
    if (empty($petName) || empty($species)) {
        $_SESSION['error'] = "❌ Pet name and species are required fields.";
    } else {
        try {
            // Generate unique QR code filename
            $qrCodeFilename = 'qr_' . uniqid() . '_' . time() . '.svg';
            
            // Insert pet into database
            $stmt = $conn->prepare("
                INSERT INTO pets (user_id, name, species, breed, age, color, weight, birth_date, gender, medical_notes, vet_contact, qr_code, date_registered) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("isssisdsssss", 
                $user_id, 
                $petName, 
                $species, 
                $breed, 
                $age, 
                $color, 
                $weight, 
                $birthDate, 
                $gender, 
                $medicalNotes, 
                $vetContact, 
                $qrCodeFilename
            );
            
            if ($stmt->execute()) {
                $pet_id = $stmt->insert_id;
                
                    // ✅ Generate direct link to view this pet's medical record
                    $qrURL = "https://group042025.ceitesystems.com/view_pet_record.php?pet_id=" . $pet_id;

                    // ✅ Generate the actual QR code image
                    require_once 'phpqrcode/qrlib.php'; // make sure you have phpqrcode library

                    $qrDir = 'qrcodes/';
                    if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

                    $qrPath = $qrDir . 'qr_' . $pet_id . '.png';
                    QRcode::png($qrURL, $qrPath, QR_ECLEVEL_L, 4);

                    // ✅ Update the pet record in DB with QR code file and link
                    $updateStmt = $conn->prepare("UPDATE pets SET qr_code = ?, qr_code_data = ? WHERE pet_id = ?");
                    $updateStmt->bind_param("ssi", $qrPath, $qrURL, $pet_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                
                // Update the pet record with the actual QR data
                $updateStmt = $conn->prepare("UPDATE pets SET qr_code_data = ? WHERE pet_id = ?");
                $updateStmt->bind_param("si", $qrData, $pet_id);
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
                throw new Exception("Failed to register pet. Please try again.");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

// Function to generate QR code data
function generateQRData($user_id, $pet_id, $petName, $species, $breed, $age, $color, $weight, $birthDate, $gender, $medicalNotes, $vetContact, $ownerName = '', $ownerEmail = '') {
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
    
    $data .= "MEDICAL INFORMATION:\n";
    $data .= "--------------------\n";
    $data .= "Medical Notes: " . ($medicalNotes ?: 'None') . "\n";
    $data .= "Veterinarian: " . ($vetContact ?: 'Not specified') . "\n\n";
    
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

// Function to save QR code as file
function saveQRCode($qrData, $filename) {
    $qrDir = 'qrcodes/';
    
    // Create directory if it doesn't exist
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0755, true);
    }
    
    $filePath = $qrDir . $filename;
    return file_put_contents($filePath, $qrData) !== false;
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
            max-width: 1000px;
            display: flex;
            min-height: 700px;
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
            max-height: 700px;
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
                            <i class="fas fa-bell"></i>
                            <span>Vaccination Reminders</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt"></i>
                            <span>Emergency Contact Info</span>
                        </li>
                        <li>
                            <i class="fas fa-mobile-alt"></i>
                            <span>Mobile Access Anywhere</span>
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
                <form id="petRegistrationForm" method="POST" novalidate>
                    <input type="hidden" name="register_pet" value="1">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-section active" id="step1">
                        <div class="form-header">
                            <h1><i class="fas fa-paw me-2"></i>Basic Information</h1>
                            <p>Let's start with the essential details about your pet</p>
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
                                <select class="form-select" id="species" name="species" required>
                                    <option value="">Select Species</option>
                                    <option value="Dog" <?php echo ($_POST['species'] ?? '') == 'Dog' ? 'selected' : ''; ?>>🐕 Dog</option>
                                    <option value="Cat" <?php echo ($_POST['species'] ?? '') == 'Cat' ? 'selected' : ''; ?>>🐈 Cat</option>
                                </select>
                                <div class="form-text">What type of pet do you have?</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="breed" class="form-label">
                                <i class="fas fa-dna"></i>Breed
                            </label>
                            <input type="text" class="form-control" id="breed" name="breed" 
                                   placeholder="e.g., Golden Retriever, Siamese, etc." maxlength="100" value="<?php echo $_POST['breed'] ?? ''; ?>">
                            <div class="form-text">If known, specify your pet's breed</div>
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
                                <label for="age" class="form-label">
                                    <i class="fas fa-birthday-cake"></i>Age (years)
                                </label>
                                <input type="number" class="form-control" id="age" name="age" 
                                       min="0" max="50" step="0.1" placeholder="e.g., 2.5" value="<?php echo $_POST['age'] ?? ''; ?>">
                                <div class="form-text">Your pet's approximate age in years</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender" class="form-label">
                                    <i class="fas fa-venus-mars"></i>Gender
                                </label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($_POST['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="color" class="form-label">
                                    <i class="fas fa-paint-brush"></i>Color/Markings
                                </label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       placeholder="e.g., Brown with white spots" maxlength="50" value="<?php echo $_POST['color'] ?? ''; ?>">
                                <div class="form-text">Describe your pet's color and distinctive markings</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="weight" class="form-label">
                                    <i class="fas fa-weight"></i>Weight (kg)
                                </label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       min="0" step="0.1" placeholder="e.g., 5.2" value="<?php echo $_POST['weight'] ?? ''; ?>">
                                <div class="form-text">Current weight in kilograms</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="birthDate" class="form-label">
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
                    
                    <!-- Step 3: Medical Information -->
                    <div class="form-section" id="step3">
                        <div class="form-header">
                            <h1><i class="fas fa-heartbeat me-2"></i>Medical Information</h1>
                            <p>Help us keep track of your pet's health needs</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="medicalNotes" class="form-label">
                                <i class="fas fa-notes-medical"></i>Medical Notes & Allergies
                            </label>
                            <textarea class="form-control" id="medicalNotes" name="medicalNotes" 
                                      rows="4" placeholder="Any known medical conditions, allergies, medications, or special needs..." 
                                      maxlength="500"><?php echo $_POST['medicalNotes'] ?? ''; ?></textarea>
                            <div class="form-text">This information will be available in the QR code for emergency situations</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vetContact" class="form-label">
                                <i class="fas fa-user-md"></i>Veterinarian Contact
                            </label>
                            <input type="text" class="form-control" id="vetContact" name="vetContact" 
                                   placeholder="e.g., Dr. Smith - City Vet Clinic (555-0123)" maxlength="100" value="<?php echo $_POST['vetContact'] ?? ''; ?>">
                            <div class="form-text">Your preferred veterinarian's name and contact information</div>
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
                                    <p><strong>Medical Notes:</strong> <span id="reviewMedicalNotes">-</span></p>
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
                        <a href="pet_profile.php" class="btn btn-submit">
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
                
                if (!petName) {
                    errorMessage = 'Please enter your pet\'s name';
                    document.getElementById('petName').focus();
                    isValid = false;
                } else if (!species) {
                    errorMessage = 'Please select your pet\'s species';
                    document.getElementById('species').focus();
                    isValid = false;
                }
            }
            
            if (!isValid && errorMessage) {
                alert('❌ ' + errorMessage);
            }
            
            return isValid;
        }
        
        function updateReviewSection() {
            document.getElementById('reviewName').textContent = document.getElementById('petName').value || 'Not specified';
            document.getElementById('reviewSpecies').textContent = document.getElementById('species').value || 'Not specified';
            document.getElementById('reviewBreed').textContent = document.getElementById('breed').value || 'Not specified';
            document.getElementById('reviewAge').textContent = document.getElementById('age').value ? document.getElementById('age').value + ' years' : 'Not specified';
            document.getElementById('reviewGender').textContent = document.getElementById('gender').value || 'Not specified';
            document.getElementById('reviewColor').textContent = document.getElementById('color').value || 'Not specified';
            document.getElementById('reviewWeight').textContent = document.getElementById('weight').value ? document.getElementById('weight').value + ' kg' : 'Not specified';
            document.getElementById('reviewBirthDate').textContent = document.getElementById('birthDate').value || 'Not specified';
            document.getElementById('reviewMedicalNotes').textContent = document.getElementById('medicalNotes').value || 'None';
            document.getElementById('reviewVetContact').textContent = document.getElementById('vetContact').value || 'Not specified';
        }
        
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
    </script>
</body>
</html>
