<?php
// Sample data (replace with DB query results)
$pet_name = "Lucky";
$breed = "Aspin";
$age = "3";
$gender = "Male";
$owner = "Juan Dela Cruz";
$last_vaccine = "2024-11-12";
$risk_level = "High";
$wellness_score = 72;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VetCareQR | Pet Profile</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body { background-color: #f8f9fa; }
    .card {
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        background: #fff;
    }
    .profile-header {
        background: linear-gradient(135deg, #0d6efd, #4dabf7);
        color: white;
        border-radius: 15px;
        padding: 20px;
        display: flex;
        align-items: center;
    }
    .profile-header img {
        border-radius: 50%;
        border: 3px solid white;
        margin-right: 20px;
        width: 100px;
        height: 100px;
        object-fit: cover;
    }
    .badge-high { background-color: #dc3545; }
    .badge-medium { background-color: #ffc107; color: #000; }
    .badge-low { background-color: #198754; }
    .timeline {
        position: relative;
        border-left: 3px solid #0d6efd;
        padding-left: 20px;
    }
    .timeline-item {
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 5px;
        height: 12px;
        width: 12px;
        background-color: #0d6efd;
        border-radius: 50%;
    }
    table th {
        background-color: #0d6efd;
        color: white;
    }
</style>
</head>
<body>

<div class="container my-4">

    <!-- Pet Header -->
    <div class="profile-header mb-4">
        <img src="assets/pet_placeholder.jpg" alt="Pet Photo">
        <div>
            <h3 class="mb-0"><?= $pet_name ?> 🐾</h3>
            <small><?= $breed ?> | <?= $age ?> years | <?= $gender ?></small><br>
            <span class="badge <?= $risk_level == 'High' ? 'badge-high' : ($risk_level == 'Medium' ? 'badge-medium' : 'badge-low') ?>">
                <?= $risk_level ?> Risk
            </span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Basic Information -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="card-title mb-3">📋 Owner & Medical Info</h5>
                <p><strong>Owner:</strong> <?= $owner ?></p>
                <p><strong>Last Vaccination:</strong> <?= $last_vaccine ?></p>
                <p><strong>Microchip ID:</strong> 12345-ABCDE</p>
                <p><strong>Registered:</strong> 2023-06-14</p>
            </div>
        </div>

        <!-- QR Code & Wellness -->
        <div class="col-md-6">
            <div class="card p-3 text-center">
                <h5 class="card-title">📱 QR Code Access</h5>
                <img src="assets/qr_placeholder.png" alt="QR Code" width="150" class="mb-2">
                <p class="text-muted">Scan to access this pet’s full medical record</p>
            </div>

            <div class="card mt-4 p-3 text-center">
                <h5 class="card-title">💙 Wellness Score</h5>
                <div style="position: relative; width: 180px; margin: auto;">
                    <canvas id="wellnessChart"></canvas>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                        font-size: 1.4rem; font-weight: bold;"><?= $wellness_score ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vaccination Timeline -->
    <div class="card mt-5 p-4">
        <h5 class="mb-3">💉 Vaccination Timeline</h5>
        <div class="timeline">
            <div class="timeline-item">
                <strong>Rabies</strong> - <span class="text-primary">Next Due: 2025-11-12</span>
            </div>
            <div class="timeline-item">
                <strong>Parvo</strong> - <span class="text-danger">Overdue</span>
            </div>
            <div class="timeline-item">
                <strong>Deworming</strong> - <span class="text-warning">Due in 5 days</span>
            </div>
        </div>
    </div>

    <!-- Medical History -->
    <div class="card mt-4 p-3">
        <h5 class="mb-3">📜 Medical History</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Treatment</th>
                        <th>Veterinarian</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2024-11-12</td>
                        <td>Rabies Vaccine</td>
                        <td>Dr. Santos</td>
                        <td>Next due in 1 year</td>
                    </tr>
                    <tr>
                        <td>2024-06-10</td>
                        <td>Deworming</td>
                        <td>Dr. Reyes</td>
                        <td>Given orally</td>
                    </tr>
                    <tr>
                        <td>2024-05-02</td>
                        <td>Parvo Vaccine</td>
                        <td>Dr. Cruz</td>
                        <td>Booster needed</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Chart Script -->
<script>
const ctx = document.getElementById('wellnessChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Score', 'Remaining'],
        datasets: [{
            data: [<?= $wellness_score ?>, 100 - <?= $wellness_score ?>],
            backgroundColor: ['#0d6efd', '#dee2e6'],
            borderWidth: 1
        }]
    },
    options: {
        animation: { animateRotate: true, duration: 1200 },
        cutout: '75%',
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

</body>
</html>
