<?php
// admin_stats.php – LGU analytics and community dashboard
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LGU Analytics | VetCareQR</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 0.75rem; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
  </style>
</head>
<body>
<div class="container mt-4">
  <h3 class="mb-4">📊 Municipal Dashboard – LGU Overview</h3>

  <div class="row g-4">
    <!-- Illness Pie Chart -->
    <div class="col-md-6">
      <div class="card p-4">
        <h5 class="mb-3">Top Reported Illnesses</h5>
        <canvas id="illnessPie"></canvas>
      </div>
    </div>

    <!-- Pet Registrations by Barangay -->
    <div class="col-md-6">
      <div class="card p-4">
        <h5 class="mb-3">Pet Registrations by Barangay</h5>
        <canvas id="barangayChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Vaccination Compliance Trends -->
  <div class="row g-4 mt-4">
    <div class="col-md-12">
      <div class="card p-4">
        <h5 class="mb-3">Vaccination Compliance (Last 6 Months)</h5>
        <canvas id="vaccineTrend"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
// Pie Chart - Illness Breakdown
new Chart(document.getElementById('illnessPie'), {
  type: 'pie',
  data: {
    labels: ['Parvo', 'Rabies', 'Worms', 'Skin Disease'],
    datasets: [{
      data: [34, 20, 18, 10],
      backgroundColor: ['#dc3545', '#0d6efd', '#ffc107', '#20c997']
    }]
  }
});

// Bar Chart - Registrations per Barangay
new Chart(document.getElementById('barangayChart'), {
  type: 'bar',
  data: {
    labels: ['Brgy San Pedro', 'Brgy Malusak', 'Brgy Market Area', 'Brgy Labas'],
    datasets: [{
      label: 'Registered Pets',
      data: [120, 95, 80, 60],
      backgroundColor: '#0d6efd'
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true } }
  }
});

// Line Chart - Vaccine Compliance
new Chart(document.getElementById('vaccineTrend'), {
  type: 'line',
  data: {
    labels: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
    datasets: [{
      label: 'Vaccines Given',
      data: [58, 72, 90, 104, 97, 110],
      borderColor: '#198754',
      backgroundColor: 'rgba(25,135,84,0.2)',
      tension: 0.3,
      fill: true
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'top' } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>

</body>
</html>
