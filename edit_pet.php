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

// ✅ Get pet_id from URL
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

if ($pet_id === 0) {
    $_SESSION['error'] = "❌ Invalid pet ID.";
    header("Location: user_pet_profile.php");
    exit();
}

// ✅ Check if pet belongs to the logged-in user and fetch pet data
$stmt = $conn->prepare("
    SELECT * FROM pets 
    WHERE pet_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $pet_id, $user_id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pet) {
    $_SESSION['error'] = "❌ Pet not found or you don't have permission to edit this pet.";
    header("Location: user_pet_profile.php");
    exit();
}

// ✅ Handle form submission for updating pet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pet'])) {
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
    $profilePicture = $pet['profile_picture']; // Keep existing picture by default
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pet_profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($fileExtension), $allowedExtensions)) {
            // Generate unique filename
            $newProfilePicture = 'pet_' . uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newProfilePicture;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // Delete old profile picture if it exists
                if (!empty($pet['profile_picture']) && file_exists($uploadDir . $pet['profile_picture'])) {
                    unlink($uploadDir . $pet['profile_picture']);
                }
                $profilePicture = $newProfilePicture;
            } else {
                $_SESSION['error'] = "❌ Failed to upload profile picture.";
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
            // Convert species to lowercase to match ENUM('dog', 'cat')
            $species_lower = strtolower($species);
            
            // ✅ CORRECTED UPDATE statement - Fixed column names to match database
            $stmt = $conn->prepare("
                UPDATE pets SET
                    name = ?, species = ?, breed = ?, age = ?, color = ?, weight = ?, 
                    birth_date = ?, gender = ?, medical_notes = ?, vet_contact = ?, 
                    previous_conditions = ?, vaccination_history = ?, surgical_history = ?, 
                    medication_history = ?, has_existing_records = ?, records_location = ?,
                    last_vet_visit = ?, next_vet_visit = ?, rabies_vaccine_date = ?,
                    dhpp_vaccine_date = ?, is_spayed_neutered = ?, spay_neuter_date = ?,
                    profile_picture = ?
                WHERE pet_id = ? AND user_id = ?
            ");

            // ✅ CORRECTED bind_param parameters
            $bind_result = $stmt->bind_param("sssisdssssssssisssssissii", 
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
                $profilePicture,
                $pet_id,
                $user_id
            );
            
            if (!$bind_result) {
                throw new Exception("Bind failed: " . $stmt->error);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "✅ Pet '$petName' has been successfully updated!";
                header("Location: user_pet_profile.php");
                exit();
            } else {
                throw new Exception("Failed to update pet: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            error_log("Edit Pet Error: " . $e->getMessage());
        }
    }
    
    // Refresh pet data after update attempt
    $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pet_id, $user_id);
    $stmt->execute();
    $pet = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pet - VetCareQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #ffd6e7;
            --pink-2: #f7c5e0;
            --pink-light: #fff4f8;
            --blue: #4a6cf7;
            --blue-light: #e8f0fe;
            --green: #2ecc71;
            --green-light: #eafaf1;
            --orange: #f39c12;
            --orange-light: #fef5e7;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fb;
            margin: 0;
            color: #333;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--pink-2);
            padding: 2rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid rgba(0,0,0,0.1);
            object-fit: cover;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            margin: .3rem 0;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: .2s;
        }
        
        .sidebar a .icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.6);
            margin-right: 10px;
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: var(--pink);
            color: #000;
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: #fff;
            background: #dc3545;
            text-align: center;
            padding: 10px;
            border-radius: 10px;
        }
        
        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--pink-light);
            border-radius: var(--radius);
            border-left: 4px solid var(--blue);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--blue);
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
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }
        
        .form-text {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .profile-picture-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: white;
            border-radius: var(--radius);
            border: 2px dashed #e5e7eb;
        }
        
        .profile-picture-preview {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }
        
        .no-profile-picture {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pink-light), var(--blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 4px solid white;
            box-shadow: var(--shadow);
        }
        
        .no-profile-picture i {
            font-size: 4rem;
            color: #6c757d;
        }
        
        .btn-custom {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--blue);
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a5bd9;
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            background: transparent;
            border: 2px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .medical-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--green);
        }
        
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-paw"></i> VetCareQR</div>
        <div class="profile">
            <img src="https://i.pravatar.cc/100?u=<?php echo urlencode($user['name']); ?>" alt="User">
            <h6><?php echo htmlspecialchars($user['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($user['role']); ?></small>
        </div>
        <a href="user_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="user_pet_profile.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> My Pets
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> QR Codes
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> Register Pet
        </a>
        <a href="user_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout">
            <div class="icon"><i class="fa-solid fa-right-from-bracket"></i></div> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Edit Pet Profile</h5>
                <small class="text-muted">Update your pet's information and medical records</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="user_pet_profile.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to My Pets
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-custom">
            <form id="editPetForm" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="update_pet" value="1">
                
                <!-- Profile Picture Section -->
                <div class="profile-picture-section">
                    <h6><i class="fa-solid fa-camera me-2"></i>Profile Picture</h6>
                    <div class="mb-3">
                        <?php if (!empty($pet['profile_picture'])): ?>
                            <img id="profilePicturePreview" 
                                 src="uploads/pet_profile_pictures/<?php echo htmlspecialchars($pet['profile_picture']); ?>" 
                                 alt="<?php echo htmlspecialchars($pet['name']); ?>" 
                                 class="profile-picture-preview"
                                 onerror="this.style.display='none'; document.getElementById('noPicture').style.display='flex';">
                            <div id="noPicture" class="no-profile-picture" style="display: none;">
                                <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                            </div>
                        <?php else: ?>
                            <div id="noPicture" class="no-profile-picture">
                                <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                           accept="image/*" onchange="previewProfilePicture(this)">
                    <div class="form-text">Upload a new profile picture (JPG, JPEG, PNG, GIF, WebP - max 5MB)</div>
                    <?php if (!empty($pet['profile_picture'])): ?>
                        <div class="form-text">
                            <small class="text-muted">Current picture: <?php echo htmlspecialchars($pet['profile_picture']); ?></small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Basic Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-paw"></i> Basic Information
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="petName" class="form-label required">
                                <i class="fa-solid fa-tag"></i> Pet Name
                            </label>
                            <input type="text" class="form-control" id="petName" name="petName" required 
                                   value="<?php echo htmlspecialchars($pet['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="species" class="form-label required">
                                <i class="fa-solid fa-paw"></i> Species
                            </label>
                            <select class="form-select" id="species" name="species" required>
                                <option value="">Select Species</option>
                                <option value="dog" <?php echo strtolower($pet['species']) == 'dog' ? 'selected' : ''; ?>>🐕 Dog</option>
                                <option value="cat" <?php echo strtolower($pet['species']) == 'cat' ? 'selected' : ''; ?>>🐈 Cat</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="breed" class="form-label">
                            <i class="fa-solid fa-dna"></i> Breed
                        </label>
                        <input type="text" class="form-control" id="breed" name="breed" 
                               value="<?php echo htmlspecialchars($pet['breed']); ?>">
                    </div>
                </div>

                <!-- Physical Details -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-palette"></i> Physical Details
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="age" class="form-label">
                                <i class="fa-solid fa-birthday-cake"></i> Age (years)
                            </label>
                            <input type="number" class="form-control" id="age" name="age" 
                                   min="0" max="50" step="1" value="<?php echo htmlspecialchars($pet['age']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender" class="form-label">
                                <i class="fa-solid fa-venus-mars"></i> Gender
                            </label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo $pet['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $pet['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="color" class="form-label">
                                <i class="fa-solid fa-paint-brush"></i> Color/Markings
                            </label>
                            <input type="text" class="form-control" id="color" name="color" 
                                   value="<?php echo htmlspecialchars($pet['color']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="weight" class="form-label">
                                <i class="fa-solid fa-weight-scale"></i> Weight (kg)
                            </label>
                            <input type="number" class="form-control" id="weight" name="weight" 
                                   min="0" step="0.1" value="<?php echo htmlspecialchars($pet['weight']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="birthDate" class="form-label">
                            <i class="fa-solid fa-calendar"></i> Birth Date
                        </label>
                        <input type="date" class="form-control" id="birthDate" name="birthDate" 
                               value="<?php echo htmlspecialchars($pet['birth_date']); ?>">
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-heartbeat"></i> Medical Information
                    </div>
                    
                    <div class="form-group">
                        <label for="medicalNotes" class="form-label">
                            <i class="fa-solid fa-notes-medical"></i> Current Medical Notes & Allergies
                        </label>
                        <textarea class="form-control" id="medicalNotes" name="medicalNotes" 
                                  rows="3" placeholder="Any current medical conditions, allergies, medications, or special needs..."><?php echo htmlspecialchars($pet['medical_notes']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="vetContact" class="form-label">
                            <i class="fa-solid fa-user-doctor"></i> Current Veterinarian Contact
                        </label>
                        <input type="text" class="form-control" id="vetContact" name="vetContact" 
                               value="<?php echo htmlspecialchars($pet['vet_contact']); ?>">
                    </div>
                </div>

                <!-- Medical Dates -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-calendar-check"></i> Important Medical Dates
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="last_vet_visit" class="form-label">
                                <i class="fa-solid fa-calendar-alt"></i> Last Vet Visit
                            </label>
                            <input type="date" class="form-control" id="last_vet_visit" name="last_vet_visit" 
                                   value="<?php echo htmlspecialchars($pet['last_vet_visit']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="next_vet_visit" class="form-label">
                                <i class="fa-solid fa-calendar-plus"></i> Next Vet Visit
                            </label>
                            <input type="date" class="form-control" id="next_vet_visit" name="next_vet_visit" 
                                   value="<?php echo htmlspecialchars($pet['next_vet_visit']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="rabies_vaccine_date" class="form-label">
                                <i class="fa-solid fa-syringe"></i> Rabies Vaccine Date
                            </label>
                            <input type="date" class="form-control" id="rabies_vaccine_date" name="rabies_vaccine_date" 
                                   value="<?php echo htmlspecialchars($pet['rabies_vaccine_date']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="dhpp_vaccine_date" class="form-label">
                                <i class="fa-solid fa-syringe"></i> DHPP/FVRCP Vaccine Date
                            </label>
                            <input type="date" class="form-control" id="dhpp_vaccine_date" name="dhpp_vaccine_date" 
                                   value="<?php echo htmlspecialchars($pet['dhpp_vaccine_date']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_spayed_neutered" name="is_spayed_neutered" value="1" 
                               <?php echo $pet['is_spayed_neutered'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_spayed_neutered">
                            <i class="fa-solid fa-stethoscope"></i> My pet is spayed/neutered
                        </label>
                    </div>
                    
                    <div class="form-group" id="spayNeuterDate" style="display: <?php echo $pet['is_spayed_neutered'] ? 'block' : 'none'; ?>;">
                        <label for="spay_neuter_date" class="form-label">
                            <i class="fa-solid fa-calendar-day"></i> Spay/Neuter Date
                        </label>
                        <input type="date" class="form-control" id="spay_neuter_date" name="spay_neuter_date" 
                               value="<?php echo htmlspecialchars($pet['spay_neuter_date']); ?>">
                    </div>
                </div>

                <!-- Medical History -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-history"></i> Medical History
                    </div>
                    
                    <div class="form-group">
                        <label for="previousConditions" class="form-label">
                            <i class="fa-solid fa-file-medical"></i> Previous Medical Conditions
                        </label>
                        <textarea class="form-control" id="previousConditions" name="previousConditions" 
                                  rows="3" placeholder="e.g., Past surgeries, illnesses, chronic conditions that are now resolved..."><?php echo htmlspecialchars($pet['previous_conditions']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="vaccinationHistory" class="form-label">
                            <i class="fa-solid fa-syringe"></i> Vaccination History
                        </label>
                        <textarea class="form-control" id="vaccinationHistory" name="vaccinationHistory" 
                                  rows="3" placeholder="e.g., Rabies (2023), DHPP (2024), Last flea/tick treatment..."><?php echo htmlspecialchars($pet['vaccination_history']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="surgicalHistory" class="form-label">
                            <i class="fa-solid fa-procedures"></i> Surgical History
                        </label>
                        <textarea class="form-control" id="surgicalHistory" name="surgicalHistory" 
                                  rows="2" placeholder="e.g., Spayed/neutered date, other surgeries..."><?php echo htmlspecialchars($pet['surgical_history']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="medicationHistory" class="form-label">
                            <i class="fa-solid fa-pills"></i> Previous Medications
                        </label>
                        <textarea class="form-control" id="medicationHistory" name="medicationHistory" 
                                  rows="2" placeholder="e.g., Previous long-term medications, treatments..."><?php echo htmlspecialchars($pet['medication_history']); ?></textarea>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hasExistingRecords" name="hasExistingRecords" value="1" 
                               <?php echo $pet['has_existing_records'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hasExistingRecords">
                            <i class="fa-solid fa-clipboard-list"></i> My pet has existing medical records at a veterinary clinic
                        </label>
                    </div>

                    <div class="form-group" id="existingRecordsDetails" style="display: <?php echo $pet['has_existing_records'] ? 'block' : 'none'; ?>;">
                        <label for="recordsLocation" class="form-label">
                            <i class="fa-solid fa-clinic-medical"></i> Records Location
                        </label>
                        <input type="text" class="form-control" id="recordsLocation" name="recordsLocation" 
                               placeholder="e.g., City Veterinary Hospital, Main Street Clinic..." 
                               value="<?php echo htmlspecialchars($pet['records_location']); ?>">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="user_pet_profile.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i> Update Pet Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Profile picture preview
    function previewProfilePicture(input) {
        const preview = document.getElementById('profilePicturePreview');
        const noPicture = document.getElementById('noPicture');
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (!preview) {
                    // Create preview element if it doesn't exist
                    const newPreview = document.createElement('img');
                    newPreview.id = 'profilePicturePreview';
                    newPreview.className = 'profile-picture-preview';
                    newPreview.src = e.target.result;
                    noPicture.parentNode.insertBefore(newPreview, noPicture);
                    noPicture.style.display = 'none';
                } else {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    noPicture.style.display = 'none';
                }
            }
            
            reader.readAsDataURL(file);
            
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                alert('❌ File size too large. Please choose an image smaller than 5MB.');
                input.value = '';
                if (preview) {
                    preview.style.display = 'none';
                }
                noPicture.style.display = 'flex';
            }
        } else {
            if (preview) {
                preview.style.display = 'none';
            }
            noPicture.style.display = 'flex';
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
        document.getElementById('existingRecordsDetails').style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            document.getElementById('recordsLocation').value = '';
        }
    });

    // Form validation
    document.getElementById('editPetForm').addEventListener('submit', function(e) {
        const petName = document.getElementById('petName').value.trim();
        const species = document.getElementById('species').value;
        
        if (!petName) {
            e.preventDefault();
            alert('❌ Please enter your pet\'s name.');
            document.getElementById('petName').focus();
            return;
        }
        
        if (!species) {
            e.preventDefault();
            alert('❌ Please select your pet\'s species.');
            document.getElementById('species').focus();
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Updating...';
        submitBtn.disabled = true;
    });

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
</body>
</html>
