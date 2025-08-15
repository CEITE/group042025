<!-- dashboard.php -->
<?php
// Include session check and database connection here
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VetCareQR | Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 1rem; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    .sidebar {
      height: 100vh;
      background-color: #084298;
      padding: 20px;
      color: white;
    }
    .sidebar a {
      color: white;
      display: block;
      padding: 10px;
      text-decoration: none;
      border-radius: 8px;
    }
    .sidebar a:hover {
      background-color: #0d6efd;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-2 sidebar">
      <h4>VetCareQR</h4>
      <a href="#">Dashboard</a>
      <a href="#">Pet Records</a>
      <a href="#">QR Scanner</a>
      <a href="#">Vaccination</a>
      <a href="#">Analytics</a>
      <a href="#">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 p-4">
      <h3>Welcome, Veterinarian</h3>

      <!-- Summary Cards -->
      <div class="row g-4 mt-2">
        <div class="col-md-3">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <h5 class="card-title">Total Pets</h5>
              <p class="card-text fs-4">152</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-white bg-danger">
            <div class="card-body">
              <h5 class="card-title">High-Risk Cases</h5>
              <p class="card-text fs-4">8</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-white bg-warning">
            <div class="card-body">
              <h5 class="card-title">Vaccines Due</h5>
              <p class="card-text fs-4">24</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Illness Risk Chart -->
      <div class="card mt-5 p-4">
        <h5>Community Illness Risk Overview</h5>
        <canvas id="illnessChart" height="100"></canvas>
      </div>

      <!-- Recent Scans / Search -->
      <div class="card mt-4 p-4">
        <h5>Scan Pet QR / Search Record</h5>
        <form class="row g-3 mt-2">
          <div class="col-md-6">
            <input type="text" class="form-control" placeholder="Enter Pet ID or Scan QR">
          </div>
          <div class="col-md-3">
            <button class="btn btn-success">Search</button>
            <button class="btn btn-outline-primary ms-2">📷 Scan QR</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Chart Script -->
<script>
const ctx = document.getElementById('illnessChart').getContext('2d');
const illnessChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Parvo', 'Rabies', 'Deworming', 'Skin Disease'],
    datasets: [{
      label: 'Number of Cases',
      data: [8, 4, 15, 3],
      backgroundColor: ['#dc3545', '#198754', '#ffc107', '#0d6efd']
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: {
        beginAtZero: true
      }
    }
  }
});
</script>

</body>
</html>
