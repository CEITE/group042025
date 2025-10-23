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

// ✅ Fetch user's pets with medical history from pets table
$query = "
SELECT 
    p.pet_id,
    p.name AS pet_name,
    p.species,
    p.breed,
    p.age,
    p.color,
    p.weight,
    p.birth_date,
    p.gender,
    p.medical_notes,
    p.vet_contact,
    p.date_registered,
    p.qr_code,
    p.qr_code_data,
    p.previous_conditions,
    p.vaccination_history,
    p.surgical_history,
    p.medication_history,
    p.has_existing_records,
    p.records_location,
    p.last_vet_visit,
    p.next_vet_visit,
    p.rabies_vaccine_date,
    p.dhpp_vaccine_date,
    p.is_spayed_neutered,
    p.spay_neuter_date
FROM pets p
WHERE p.user_id = ?
ORDER BY p.date_registered DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pets - VetCareQR</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
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
        
        .pet-card {
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
            border: none;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
        }
        
        .pet-card-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--pink-light), var(--blue-light));
        }
        
        .pet-card-body {
            padding: 1.5rem;
        }
        
        .pet-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: white;
            border: 4px solid white;
            box-shadow: var(--shadow);
        }
        
        .pet-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .detail-item {
            background: var(--pink-light);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .detail-item i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--blue);
        }
        
        .medical-history {
            background: var(--pink-light);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--blue);
            margin-top: 1.5rem;
        }
        
        .medical-item {
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            border-left: 3px solid var(--green);
            margin-bottom: 0.75rem;
        }
        
        .medical-item strong {
            color: var(--blue);
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .medical-item p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .medical-dates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .medical-date-item {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .medical-date-item small {
            color: #6c757d;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .medical-dates-grid {
                grid-template-columns: 1fr;
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
        <a href="user_pet_profile.php" class="active">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> My Pets
        </a>
        <a href="qr_code.php">
            <div class="icon"><i class="fa-solid fa-qrcode"></i></div> QR Codes
        </a>
        <a href="register_pet.php">
            <div class="icon"><i class="fa-solid fa-plus-circle"></i></div> Register Pet
        </a>
        <a href="user_setting.php">
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
                <h5 class="mb-0">My Pets</h5>
                <small class="text-muted">Manage your pets and view their medical records</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="register_pet.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus-circle me-1"></i> Add New Pet
                </a>
            </div>
        </div>

        <?php if (empty($pets)): ?>
            <div class="card-custom text-center">
                <div class="empty-state">
                    <i class="fa-solid fa-paw"></i>
                    <h5>No Pets Registered</h5>
                    <p class="text-muted">You haven't added any pets yet. Register your first pet to get started!</p>
                    <a href="register_pet.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add Your First Pet
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($pets as $pet): ?>
                <div class="card-custom">
                    <div class="pet-card">
                        <div class="pet-card-header">
                            <div class="d-flex align-items-center">
                                <div class="pet-avatar me-3" style="background: <?php echo strtolower($pet['species']) == 'dog' ? '#bbdefb' : '#f8bbd0'; ?>">
                                    <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                                    <p class="mb-0 text-muted">
                                        <?php echo htmlspecialchars($pet['species']); ?> • 
                                        <?php echo htmlspecialchars($pet['breed']); ?> • 
                                        <?php echo htmlspecialchars($pet['age']); ?> years old
                                    </p>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary">ID: <?php echo htmlspecialchars($pet['pet_id']); ?></span>
                                <?php 
                                $hasMedicalHistory = !empty($pet['previous_conditions']) || !empty($pet['vaccination_history']) || 
                                                     !empty($pet['surgical_history']) || !empty($pet['medication_history']) ||
                                                     !empty($pet['last_vet_visit']) || !empty($pet['rabies_vaccine_date']);
                                ?>
                                <span class="badge <?php echo $hasMedicalHistory ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $hasMedicalHistory ? 'Has Medical History' : 'No Medical History'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="pet-card-body">
                            <div class="pet-details-grid">
                                <div class="detail-item">
                                    <i class="fa-solid fa-venus-mars"></i>
                                    <div>
                                        <strong>Gender</strong><br>
                                        <?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fa-solid fa-weight-scale"></i>
                                    <div>
                                        <strong>Weight</strong><br>
                                        <?php echo $pet['weight'] ? htmlspecialchars($pet['weight']) . ' kg' : 'Not specified'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fa-solid fa-palette"></i>
                                    <div>
                                        <strong>Color</strong><br>
                                        <?php echo htmlspecialchars($pet['color']) ?: 'Not specified'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fa-solid fa-cake-candles"></i>
                                    <div>
                                        <strong>Birth Date</strong><br>
                                        <?php echo $pet['birth_date'] ? date('M j, Y', strtotime($pet['birth_date'])) : 'Unknown'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($pet['medical_notes'])): ?>
                                <div class="medical-notes mt-3">
                                    <h6><i class="fa-solid fa-file-medical me-2"></i>Current Medical Notes</h6>
                                    <div class="alert alert-info">
                                        <?php echo nl2br(htmlspecialchars($pet['medical_notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($pet['vet_contact'])): ?>
                                <div class="vet-contact mt-3">
                                    <h6><i class="fa-solid fa-user-doctor me-2"></i>Veterinarian Contact</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($pet['vet_contact']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- 🆕 Medical History Section -->
                            <div class="medical-history">
                                <h6><i class="fa-solid fa-history me-2"></i>Medical History Summary</h6>
                                
                                <!-- Medical Dates -->
                                <?php 
                                $hasMedicalDates = !empty($pet['last_vet_visit']) || !empty($pet['next_vet_visit']) || 
                                                  !empty($pet['rabies_vaccine_date']) || !empty($pet['dhpp_vaccine_date']) ||
                                                  $pet['is_spayed_neutered'];
                                ?>
                                
                                <?php if ($hasMedicalDates): ?>
                                <div class="medical-dates-grid mb-3">
                                    <?php if (!empty($pet['last_vet_visit'])): ?>
                                        <div class="medical-date-item">
                                            <small>Last Vet Visit</small>
                                            <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet['last_vet_visit'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['next_vet_visit'])): ?>
                                        <div class="medical-date-item">
                                            <small>Next Vet Visit</small>
                                            <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet['next_vet_visit'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['rabies_vaccine_date'])): ?>
                                        <div class="medical-date-item">
                                            <small>Rabies Vaccine</small>
                                            <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet['rabies_vaccine_date'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['dhpp_vaccine_date'])): ?>
                                        <div class="medical-date-item">
                                            <small>DHPP Vaccine</small>
                                            <div class="fw-bold"><?php echo date('M j, Y', strtotime($pet['dhpp_vaccine_date'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet['is_spayed_neutered']): ?>
                                        <div class="medical-date-item">
                                            <small>Spayed/Neutered</small>
                                            <div class="fw-bold">
                                                Yes <?php echo !empty($pet['spay_neuter_date']) ? '<br><small>(' . date('M j, Y', strtotime($pet['spay_neuter_date'])) . ')</small>' : ''; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Medical History Details -->
                                <div class="medical-details">
                                    <?php if (!empty($pet['previous_conditions'])): ?>
                                        <div class="medical-item">
                                            <strong><i class="fa-solid fa-file-medical me-1"></i>Previous Conditions</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet['previous_conditions'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['vaccination_history'])): ?>
                                        <div class="medical-item">
                                            <strong><i class="fa-solid fa-syringe me-1"></i>Vaccination History</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet['vaccination_history'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['surgical_history'])): ?>
                                        <div class="medical-item">
                                            <strong><i class="fa-solid fa-procedures me-1"></i>Surgical History</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet['surgical_history'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pet['medication_history'])): ?>
                                        <div class="medical-item">
                                            <strong><i class="fa-solid fa-pills me-1"></i>Medication History</strong>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($pet['medication_history'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($pet['has_existing_records'] && !empty($pet['records_location'])): ?>
                                        <div class="medical-item">
                                            <strong><i class="fa-solid fa-clipboard-list me-1"></i>Existing Records Location</strong>
                                            <p class="mb-0"><?php echo htmlspecialchars($pet['records_location']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$hasMedicalHistory): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fa-solid fa-file-medical fa-2x mb-2"></i>
                                            <p>No medical history recorded yet.</p>
                                            <a href="edit_pet.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-plus me-1"></i> Add Medical History
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="edit_pet.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Pet & Medical Info
                                </a>
                                <button class="btn btn-outline-info" onclick="generatePetQR(<?php echo $pet['pet_id']; ?>, '<?php echo addslashes($pet['pet_name']); ?>')">
                                    <i class="fa-solid fa-qrcode me-1"></i> Generate QR
                                </button>
                                <a href="pet-medical-records.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-outline-success">
                                    <i class="fa-solid fa-eye me-1"></i> View Full Record
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="modalQrContainer"></div>
                <p class="text-muted mt-3">Scan this QR code to view medical records</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadQRCode()">
                    <i class="fas fa-download me-1"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function generatePetQR(petId, petName) {
        const qrData = `PET MEDICAL RECORD\nPet ID: ${petId}\nPet Name: ${petName}\nOwner: <?php echo htmlspecialchars($user['name']); ?>\nGenerated: ${new Date().toLocaleDateString()}`;
        
        const container = document.getElementById('modalQrContainer');
        container.innerHTML = '';
        
        const qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        container.innerHTML = qr.createSvgTag({
            scalable: true,
            margin: 2,
            color: '#000',
            background: '#fff'
        });
        
        document.getElementById('qrModalTitle').textContent = `QR Code - ${petName}`;
        
        // Store data for download
        container.setAttribute('data-pet-name', petName);
        container.setAttribute('data-pet-id', petId);
        
        const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
        qrModal.show();
    }
    
    function downloadQRCode() {
        const container = document.getElementById('modalQrContainer');
        const svgElement = container.querySelector('svg');
        const petName = container.getAttribute('data-pet-name');
        const petId = container.getAttribute('data-pet-id');
        
        if (!svgElement) return;
        
        const serializer = new XMLSerializer();
        let source = serializer.serializeToString(svgElement);
        
        if (!source.match(/^<svg[^>]+xmlns="http\:\/\/www\.w3\.org\/2000\/svg"/)) {
            source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
        }
        
        const blob = new Blob([source], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        
        const downloadLink = document.createElement('a');
        downloadLink.href = url;
        downloadLink.download = `petmedqr-${petName.toLowerCase().replace(/\s+/g, '-')}-${petId}.svg`;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        URL.revokeObjectURL(url);
    }
</script>
</body>
</html>



