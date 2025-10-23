<?php
// pet-medical-access.php - FIXED TO SHOW PETS TABLE MEDICAL HISTORY
error_reporting(E_ALL);
ini_set('display_errors', 1);

@session_start();

// Get basic parameters safely
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$pet_name = isset($_GET['pet_name']) ? htmlspecialchars($_GET['pet_name']) : 'Unknown Pet';

// Simple base URL
$base_url = 'https://group042025.ceitesystems.com';

// Initialize variables
$pet_data = null;
$medical_records = [];

// Try to connect to database safely
try {
    if (file_exists("conn.php")) {
        include("conn.php");
        
        // Fetch pet data with all medical history fields
        if ($pet_id > 0 && isset($conn)) {
            // Fetch comprehensive pet data from pets table
            $stmt = $conn->prepare("
                SELECT 
                    p.*, 
                    u.name as owner_name, 
                    u.email as owner_email, 
                    u.phone as owner_phone
                FROM pets p 
                LEFT JOIN users u ON p.user_id = u.user_id 
                WHERE p.pet_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $pet_data = $result->fetch_assoc();
                }
                $stmt->close();
            }
            
            // Fetch individual medical records from pet_medical_records table
            $stmt = $conn->prepare("
                SELECT 
                    record_id,
                    service_date,
                    service_type,
                    service_description,
                    veterinarian,
                    notes,
                    reminder_description,
                    reminder_due_date,
                    clinic_name,
                    clinic_address,
                    clinic_contact,
                    generated_date
                FROM pet_medical_records 
                WHERE pet_id = ? 
                ORDER BY service_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param("i", $pet_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    $medical_records = $result->fetch_all(MYSQLI_ASSOC);
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    // Silent fail - we'll use the basic data
}

// Calculate age from birth date if available
function calculateAge($birth_date) {
    if (!$birth_date) return 'Unknown';
    
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    
    if ($age->y > 0) {
        return $age->y . ' year' . ($age->y > 1 ? 's' : '');
    } elseif ($age->m > 0) {
        return $age->m . ' month' . ($age->m > 1 ? 's' : '');
    } else {
        return $age->d . ' day' . ($age->d > 1 ? 's' : '');
    }
}

// Format text fields with line breaks
function formatMedicalText($text) {
    if (!$text || trim($text) === '') return '<span class="text-muted">No information available</span>';
    return nl2br(htmlspecialchars(trim($text)));
}

// Check if pet has medical history in pets table
function hasMedicalHistory($pet_data) {
    if (!$pet_data) return false;
    
    return (!empty($pet_data['medical_notes']) && trim($pet_data['medical_notes']) !== '') ||
           (!empty($pet_data['previous_conditions']) && trim($pet_data['previous_conditions']) !== '') ||
           (!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== '') ||
           (!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== '') ||
           (!empty($pet_data['medication_history']) && trim($pet_data['medication_history']) !== '') ||
           !empty($pet_data['last_vet_visit']) ||
           !empty($pet_data['rabies_vaccine_date']) ||
           !empty($pet_data['dhpp_vaccine_date']) ||
           $pet_data['is_spayed_neutered'] == 1;
}

// Get service type icon
function getServiceIcon($service_type) {
    if (!$service_type) return 'fas fa-file-medical';
    
    switch(strtolower($service_type)) {
        case 'vaccination': return 'fas fa-syringe';
        case 'surgery': return 'fas fa-procedures';
        case 'checkup': return 'fas fa-stethoscope';
        case 'examination': return 'fas fa-search';
        case 'medication': return 'fas fa-pills';
        case 'dental': return 'fas fa-tooth';
        case 'grooming': return 'fas fa-spa';
        default: return 'fas fa-file-medical';
    }
}

// Get service type color
function getServiceColor($service_type) {
    if (!$service_type) return '#6b7280';
    
    switch(strtolower($service_type)) {
        case 'vaccination': return '#22c55e';
        case 'surgery': return '#ea580c';
        case 'checkup': return '#06b6d4';
        case 'examination': return '#8b5cf6';
        case 'medication': return '#3b82f6';
        case 'dental': return '#ec4899';
        case 'grooming': return '#f59e0b';
        default: return '#6b7280';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Medical Records - PetMedQR</title>
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
            --radius: 12px;
            --radius-lg: 16px;
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
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
            min-height: 100vh;
            color: #1f2937;
            line-height: 1.6;
        }
        
        .medical-header {
            background: var(--pink-gradient);
            color: white;
            padding: 3rem 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }
        
        .medical-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: rotate(45deg);
        }
        
        .pet-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }
        
        .medical-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .medical-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--pink-gradient);
        }
        
        .card-header-custom {
            background: var(--pink-gradient-light);
            border-bottom: 1px solid var(--pink);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--pink-darker);
        }
        
        /* Medical History Styles */
        .medical-section {
            background: var(--pink-light);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--pink-dark);
        }
        
        .history-card {
            background: white;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            box-shadow: var(--shadow);
        }
        
        .vaccine-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #22c55e;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .surgery-card {
            background: linear-gradient(135deg, #fef7ed 0%, #fed7aa 100%);
            border: 1px solid #ea580c;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .medication-card {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #3b82f6;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .condition-card {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            border: 1px solid #dc2626;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .medical-badge {
            background: var(--pink-gradient);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .service-badge {
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .medical-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .medical-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border: 1px solid #e5e7eb;
            box-shadow: var(--shadow);
        }
        
        .medical-stat .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--pink-darker);
            display: block;
        }
        
        .medical-stat .label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .medical-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .medical-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--pink);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border-left: 4px solid var(--pink-dark);
            box-shadow: var(--shadow);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.9rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--pink-dark);
            border: 3px solid white;
            box-shadow: var(--shadow);
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .medical-text-content {
            background: white;
            border-radius: var(--radius);
            padding: 1rem;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .reminder-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: var(--radius);
            padding: 0.75rem;
            margin-top: 0.5rem;
            border-left: 4px solid #f59e0b;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="medical-header">
            <div class="pet-avatar floating">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Pet Medical Records</h1>
            <p class="lead mb-0 opacity-90">Complete Medical History & Health Information</p>
            <div class="mt-3">
                <?php if ($pet_data && (hasMedicalHistory($pet_data) || !empty($medical_records))): ?>
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-notes-medical me-1"></i>Medical History Available
                    </span>
                <?php endif; ?>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-shield-alt me-1"></i> Secure QR Access
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Debug Info (remove in production) -->
                <?php if ($pet_data): ?>
                <div class="alert alert-info d-none">
                    <strong>Debug Info:</strong><br>
                    Medical Notes: <?php echo !empty($pet_data['medical_notes']) ? 'Yes' : 'No'; ?><br>
                    Previous Conditions: <?php echo !empty($pet_data['previous_conditions']) ? 'Yes' : 'No'; ?><br>
                    Vaccination History: <?php echo !empty($pet_data['vaccination_history']) ? 'Yes' : 'No'; ?><br>
                    Surgical History: <?php echo !empty($pet_data['surgical_history']) ? 'Yes' : 'No'; ?><br>
                    Medication History: <?php echo !empty($pet_data['medication_history']) ? 'Yes' : 'No'; ?>
                </div>
                <?php endif; ?>

                <!-- Medical Statistics -->
                <?php if ($pet_data && (hasMedicalHistory($pet_data) || !empty($medical_records))): ?>
                <div class="medical-stats">
                    <div class="medical-stat">
                        <span class="number"><?php echo count($medical_records); ?></span>
                        <span class="label">Medical Visits</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number">
                            <?php 
                                $vaccine_count = 0;
                                if ($pet_data && $pet_data['rabies_vaccine_date']) $vaccine_count++;
                                if ($pet_data && $pet_data['dhpp_vaccine_date']) $vaccine_count++;
                                echo $vaccine_count;
                            ?>
                        </span>
                        <span class="label">Vaccinations</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number"><?php echo $pet_data && $pet_data['is_spayed_neutered'] ? 1 : 0; ?></span>
                        <span class="label">Surgeries</span>
                    </div>
                    <div class="medical-stat">
                        <span class="number"><?php echo $pet_data && $pet_data['last_vet_visit'] ? 1 : 0; ?></span>
                        <span class="label">Recent Visit</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pet Information Card -->
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-paw me-2"></i>Pet Information
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($pet_data): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="text-pink-darker mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Basic Details
                                    </h5>
                                    <div class="row">
                                        <div class="col-6 mb-2"><strong>Name:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['name']); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Species:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['species']); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Breed:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['breed'] ?: 'Mixed'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Age:</strong></div>
                                        <div class="col-6 mb-2"><?php echo calculateAge($pet_data['birth_date']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="text-pink-darker mb-3">
                                        <i class="fas fa-venus-mars me-2"></i>Health Details
                                    </h5>
                                    <div class="row">
                                        <div class="col-6 mb-2"><strong>Gender:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['gender'] ?: 'Unknown'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Color:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['color'] ?: 'Not specified'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Weight:</strong></div>
                                        <div class="col-6 mb-2"><?php echo htmlspecialchars($pet_data['weight'] ? $pet_data['weight'] . ' kg' : 'Not specified'); ?></div>
                                        
                                        <div class="col-6 mb-2"><strong>Birth Date:</strong></div>
                                        <div class="col-6 mb-2"><?php echo $pet_data['birth_date'] ? date('M j, Y', strtotime($pet_data['birth_date'])) : 'Unknown'; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Medical Notes -->
                            <?php if (!empty($pet_data['medical_notes']) && trim($pet_data['medical_notes']) !== ''): ?>
                                <div class="medical-section mt-4">
                                    <h6 class="text-pink-darker mb-3">
                                        <i class="fas fa-file-medical me-2"></i>Medical Notes
                                    </h6>
                                    <div class="medical-text-content">
                                        <?php echo formatMedicalText($pet_data['medical_notes']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-paw fa-3x text-pink-darker mb-3"></i>
                                <h5 class="text-pink-darker mb-2"><?php echo $pet_name; ?></h5>
                                <p class="text-muted">Pet ID: <?php echo $pet_id; ?></p>
                                <p class="text-muted">Complete medical details available in full system</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical History from Pets Table -->
                <?php if ($pet_data && hasMedicalHistory($pet_data)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Medical History Summary
                            <span class="badge bg-primary ms-2">From Pet Profile</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <!-- Previous Conditions -->
                        <?php if (!empty($pet_data['previous_conditions']) && trim($pet_data['previous_conditions']) !== ''): ?>
                            <div class="condition-card">
                                <h6 class="text-pink-darker mb-2">
                                    <i class="fas fa-heartbeat me-2"></i>Previous Medical Conditions
                                </h6>
                                <div class="medical-text-content">
                                    <?php echo formatMedicalText($pet_data['previous_conditions']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Vaccination History -->
                        <?php if ((!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== '') || $pet_data['rabies_vaccine_date'] || $pet_data['dhpp_vaccine_date']): ?>
                            <div class="vaccine-card">
                                <h6 class="text-pink-darker mb-2">
                                    <i class="fas fa-syringe me-2"></i>Vaccination History
                                </h6>
                                <div class="medical-text-content">
                                    <?php if (!empty($pet_data['vaccination_history']) && trim($pet_data['vaccination_history']) !== ''): ?>
                                        <?php echo formatMedicalText($pet_data['vaccination_history']); ?>
                                        <?php if ($pet_data['rabies_vaccine_date'] || $pet_data['dhpp_vaccine_date']): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet_data['rabies_vaccine_date']): ?>
                                        <p class="mb-1"><strong>Rabies Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['rabies_vaccine_date'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet_data['dhpp_vaccine_date']): ?>
                                        <p class="mb-0"><strong>DHPP Vaccine:</strong> <?php echo date('M j, Y', strtotime($pet_data['dhpp_vaccine_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Surgical History -->
                        <?php if ((!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== '') || $pet_data['is_spayed_neutered']): ?>
                            <div class="surgery-card">
                                <h6 class="text-pink-darker mb-2">
                                    <i class="fas fa-procedures me-2"></i>Surgical History
                                </h6>
                                <div class="medical-text-content">
                                    <?php if (!empty($pet_data['surgical_history']) && trim($pet_data['surgical_history']) !== ''): ?>
                                        <?php echo formatMedicalText($pet_data['surgical_history']); ?>
                                        <?php if ($pet_data['is_spayed_neutered']): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet_data['is_spayed_neutered']): ?>
                                        <p class="mb-1"><strong>Spayed/Neutered:</strong> Yes</p>
                                        <?php if ($pet_data['spay_neuter_date']): ?>
                                            <p class="mb-0"><strong>Date:</strong> <?php echo date('M j, Y', strtotime($pet_data['spay_neuter_date'])); ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Medication History -->
                        <?php if (!empty($pet_data['medication_history']) && trim($pet_data['medication_history']) !== ''): ?>
                            <div class="medication-card">
                                <h6 class="text-pink-darker mb-2">
                                    <i class="fas fa-pills me-2"></i>Medication History
                                </h6>
                                <div class="medical-text-content">
                                    <?php echo formatMedicalText($pet_data['medication_history']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Vet Visit Schedule -->
                        <?php if ($pet_data['last_vet_visit'] || $pet_data['next_vet_visit']): ?>
                            <div class="history-card">
                                <h6 class="text-pink-darker mb-2">
                                    <i class="fas fa-calendar-alt me-2"></i>Veterinary Visit Schedule
                                </h6>
                                <div class="row">
                                    <?php if ($pet_data['last_vet_visit']): ?>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Last Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['last_vet_visit'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($pet_data['next_vet_visit']): ?>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Next Visit:</strong> <?php echo date('M j, Y', strtotime($pet_data['next_vet_visit'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Show message if no medical history in pets table -->
                    <?php if ($pet_data): ?>
                    <div class="medical-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-file-medical"></i>
                                <h6 class="text-pink-darker mb-2">No Medical History in Pet Profile</h6>
                                <p class="text-muted mb-0">No detailed medical history found in the pet's main profile.</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Individual Medical Records Timeline -->
                <?php if (!empty($medical_records)): ?>
                <div class="medical-card">
                    <div class="card-header-custom">
                        <h4 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>Medical Visits & Services
                            <span class="badge bg-primary ms-2"><?php echo count($medical_records); ?> records</span>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="medical-timeline">
                            <?php foreach ($medical_records as $record): 
                                $service_color = getServiceColor($record['service_type']);
                                $service_icon = getServiceIcon($record['service_type']);
                            ?>
                                <div class="timeline-item">
                                    <span class="service-badge" style="background: <?php echo $service_color; ?>">
                                        <i class="<?php echo $service_icon; ?> me-1"></i>
                                        <?php echo htmlspecialchars($record['service_type'] ?: 'Medical Service'); ?>
                                    </span>
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($record['service_description']); ?></h6>
                                        <small class="text-muted"><?php echo $record['service_date'] ? date('M j, Y', strtotime($record['service_date'])) : 'Date not specified'; ?></small>
                                    </div>
                                    
                                    <?php if (!empty($record['veterinarian'])): ?>
                                        <p class="mb-1 small"><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['veterinarian']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($record['clinic_name'])): ?>
                                        <p class="mb-1 small"><strong>Clinic:</strong> <?php echo htmlspecialchars($record['clinic_name']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($record['notes'])): ?>
                                        <div class="medical-text-content mt-2">
                                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Reminder Alert -->
                                    <?php if (!empty($record['reminder_description']) && !empty($record['reminder_due_date'])): ?>
                                        <div class="reminder-alert mt-2">
                                            <i class="fas fa-bell me-2"></i>
                                            <strong>Reminder:</strong> <?php echo htmlspecialchars($record['reminder_description']); ?>
                                            <br><small>Due: <?php echo date('M j, Y', strtotime($record['reminder_due_date'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Show message if no medical history at all -->
                <?php if ((!$pet_data || !hasMedicalHistory($pet_data)) && empty($medical_records)): ?>
                <div class="medical-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                        <h5 class="text-pink-darker mb-2">No Medical Records Found</h5>
                        <p class="text-muted">This pet doesn't have any medical records in the system yet.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emergency & Contact Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="medical-card">
                            <div class="card-header-custom">
                                <h5 class="mb-0">
                                    <i class="fas fa-phone-alt me-2"></i>Emergency Contacts
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pet_data && $pet_data['owner_name']): ?>
                                    <h6 class="text-pink-darker mb-3">Pet Owner</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($pet_data['owner_name']); ?></p>
                                    <?php if ($pet_data['owner_phone']): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($pet_data['owner_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($pet_data['owner_email']): ?>
                                        <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($pet_data['owner_email']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Owner contact information available in full system</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="medical-card">
                            <div class="card-header-custom">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-md me-2"></i>Veterinary Contact
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pet_data && $pet_data['vet_contact']): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet_data['vet_contact'])); ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Veterinarian details available in full system</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="medical-card text-center">
                    <div class="card-body py-5">
                        <h3 class="text-pink-darker mb-3">Access Complete Medical System</h3>
                        <p class="text-muted mb-4 lead">
                            Login for full medical history, treatment plans, prescriptions, lab results, and comprehensive health tracking.
                        </p>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                            <a href="<?php echo $base_url; ?>/login.php" class="btn btn-primary btn-lg px-4" style="background: var(--pink-gradient); border: none;">
                                <i class="fas fa-sign-in-alt me-2"></i>System Login
                            </a>
                            <a href="<?php echo $base_url; ?>/register.php" class="btn btn-outline-primary btn-lg px-4" style="border-color: var(--pink-dark); color: var(--pink-dark);">
                                <i class="fas fa-user-plus me-2"></i>Request Access
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-muted mt-5 pt-4 border-top">
            <div class="mb-2">
                <i class="fas fa-paw text-pink-dark me-2"></i>
                <strong class="text-pink-darker">PetMedQR Medical Records</strong>
            </div>
            <p class="mb-1 small">&copy; <?php echo date('Y'); ?> PetMedQR. All rights reserved.</p>
            <p class="small text-muted">Secure pet medical records management system</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
