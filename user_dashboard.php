<?php
session_start();
$_SESSION['user_name'] = 'Pet Owner Aira';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Dashboard - VetCareQR</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f0f2f5;
      margin: 0;
    }
    .wrapper { display: flex; height: 100vh; }
    .sidebar {
      width: 250px; background: #198754; color: white; padding: 1.5rem 1rem;
      flex-shrink: 0; transition: transform 0.3s ease-in-out;
    }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar h2 { font-weight: bold; font-size: 1.5rem; margin-bottom: 2rem; text-align: center; }
    .sidebar a {
      color: white; text-decoration: none; display: block; padding: 0.8rem 1rem;
      border-radius: 8px; margin-bottom: 0.5rem;
    }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); }
    .main-content { flex: 1; padding: 1.5rem; overflow-y: auto; }
    .topbar {
      background: white; padding: 1rem 1.5rem; border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 1.5rem;
      display: flex; justify-content: space-between; align-items: center;
    }
    .menu-btn {
      background: none; border: none; font-size: 1.5rem; color: #198754; display: none;
    }
    @media(max-width: 768px){ .menu-btn { display: block; } }
    .user-menu { position: relative; }
    .user-menu img { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }
    .user-dropdown {
      position: absolute; right: 0; top: 50px; background: white;
      border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.15);
      display: none; min-width: 150px; z-index: 1000;
    }
    .user-dropdown a { display: block; padding: 0.5rem 1rem; color: #333; text-decoration: none; }
    .user-dropdown a:hover { background: #f0f0f0; }
    .cards-row .card { border-radius: 1rem; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    .table thead { background: #198754; color: white; }
    .pet-card img { width:100px;height:100px;object-fit:cover;border-radius:8px; }
  </style>
</head>
<body>

<div class="wrapper">
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <h2><i class="fa-solid fa-paw"></i> VetCareQR</h2>
    <a href="#" class="active" id="link-dashboard"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="#" id="link-mypets"><i class="fa-solid fa-dog"></i> My Pets</a>
    <a href="#"><i class="fa-solid fa-qrcode"></i> QR Codes</a>
    <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
    <a href="#"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
  </div>

  <!-- Main content -->
  <div class="main-content">
    <!-- Top bar -->
    <div class="topbar">
      <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
      <div>
        <h5 class="mb-0">Welcome, <?= htmlspecialchars($_SESSION['user_name']); ?></h5>
        <p class="text-muted mb-0">Your pet health and prediction summary</p>
      </div>
      <div class="user-menu">
        <img src="https://i.pravatar.cc/40?img=3" alt="Profile" onclick="toggleDropdown()">
        <div class="user-dropdown" id="userDropdown">
          <a href="#">Settings</a>
          <a href="#">Logout</a>
        </div>
      </div>
    </div>

    <!-- DASHBOARD SECTION -->
    <div id="dashboard-section">
      <div class="row text-center mb-4 cards-row">
        <div class="col-md-4 mb-3">
          <div class="card p-3">
            <i class="fa-solid fa-dog fa-2x mb-2 text-primary"></i>
            <h6>Registered Pets</h6>
            <h4>3</h4>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card p-3">
            <i class="fa-solid fa-syringe fa-2x mb-2 text-success"></i>
            <h6>Vaccinated Pets</h6>
            <h4>2</h4>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card p-3">
            <i class="fa-solid fa-calendar-check fa-2x mb-2 text-warning"></i>
            <h6>Upcoming Vaccines</h6>
            <h4>1</h4>
          </div>
        </div>
      </div>

      <!-- Chart -->
      <div class="card mb-4 p-3">
        <h5 class="text-center mt-2">Prediction Results</h5>
        <canvas id="predictionChart" height="100"></canvas>
      </div>

      <!-- Pet table -->
      <div class="card mb-4 p-3">
        <h5><i class="fa-solid fa-paw text-success"></i> My Pets and Risk Level</h5>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Pet Name</th>
                <th>Breed</th>
                <th>Age</th>
                <th>Predicted Illness</th>
                <th>Risk Level</th>
              </tr>
            </thead>
            <tbody id="petTable"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MY PETS SECTION -->
    <div id="mypets-section" style="display:none;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fa-solid fa-dog text-success"></i> My Pets</h4>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPetModal">
          <i class="fa-solid fa-plus"></i> Add New Pet
        </button>
      </div>
      <div class="row" id="myPetsGrid"></div>
    </div>

  </div>
</div>

<!-- Modal for Adding Pet -->
<div class="modal fade" id="addPetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addPetForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Add New Pet</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Pet Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Breed</label>
            <input type="text" class="form-control" name="breed" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Age</label>
            <input type="text" class="form-control" name="age" placeholder="2 yrs">
          </div>
          <div class="mb-3">
            <label class="form-label">Photo</label>
            <input type="file" class="form-control" name="photo" accept="image/*" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Pet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('hidden'); }
function toggleDropdown(){
  const d=document.getElementById('userDropdown');
  d.style.display=d.style.display==='block'?'none':'block';
}

// Sidebar navigation
document.getElementById('link-dashboard').addEventListener('click',(e)=>{
  e.preventDefault();
  document.getElementById('dashboard-section').style.display='block';
  document.getElementById('mypets-section').style.display='none';
});
document.getElementById('link-mypets').addEventListener('click',(e)=>{
  e.preventDefault();
  document.getElementById('dashboard-section').style.display='none';
  document.getElementById('mypets-section').style.display='block';
});

// Chart
new Chart(document.getElementById('predictionChart'), {
  type: 'bar',
  data: {
    labels: ['High Risk','Medium Risk','Low Risk'],
    datasets:[{
      label:'Predicted Pet Risk Levels',
      data:[1,1,1],
      backgroundColor:['#dc3545','#ffc107','#198754'],
      borderRadius:8
    }]
  },
  options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

// Load pets dynamically
fetch('get_pets.php')
  .then(res=>res.json())
  .then(pets=>{
    const table=document.getElementById('petTable');
    const grid=document.getElementById('myPetsGrid');

    pets.forEach((pet,i)=>{
      // table row
      table.innerHTML += `
  <tr>
    <td><a href="pet_profile.php?pet_id=${pet.id}">${pet.name}</a></td>
    <td>${pet.breed}</td>
    <td>${pet.age}</td>
    <td>${pet.illness}</td>
    <td><span class="badge ${pet.risk==='High'?'bg-danger':pet.risk==='Medium'?'bg-warning text-dark':'bg-success'}">${pet.risk}</span></td>
  </tr>`;


      // pet card
     const col=document.createElement('div');
col.className='col-md-4 mb-3';
col.innerHTML=`
  <div class="card p-3 text-center pet-card">
    <img src="${pet.image}" class="mb-2">
    <h5>${pet.name}</h5>
    <p>${pet.breed} • ${pet.age}</p>
    <span class="badge ${pet.risk==='High'?'bg-danger':pet.risk==='Medium'?'bg-warning text-dark':'bg-success'}">${pet.risk}</span>
    <div id="mypet-qrcode-${i}" class="d-flex justify-content-center mt-2"></div>
    <div class="mt-2">
      <a href="pet_profile.php?pet_id=${pet.id}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-eye"></i> View Profile
      </a>
      <button class="btn btn-outline-primary btn-sm mt-1">
        <i class="fa-solid fa-pen"></i> Edit
      </button>
      <button class="btn btn-outline-secondary btn-sm download-qr mt-2" data-qr="mypet-qrcode-${i}" data-name="${pet.name}">
        <i class="fa-solid fa-download"></i> QR
      </button>
    </div>
  </div>`;
grid.appendChild(col);


      // generate QR
      new QRCode(document.getElementById(`mypet-qrcode-${i}`), {
        text:`Pet:${pet.name}|Breed:${pet.breed}|Age:${pet.age}|Risk:${pet.risk}`,
        width:80,height:80
      });
    });
  });

// QR download button
document.addEventListener('click',function(e){
  if(e.target.closest('.download-qr')){
    const btn=e.target.closest('.download-qr');
    const qrCanvas=btn.parentElement.parentElement.querySelector('canvas');
    const link=document.createElement('a');
    link.href=qrCanvas.toDataURL('image/png');
    link.download=`${btn.dataset.name}_QR.png`;
    link.click();
  }
});

// Add Pet form
document.getElementById('addPetForm').addEventListener('submit',function(e){
  e.preventDefault();
  alert('New pet form submitted! (connect this to PHP backend)');
  bootstrap.Modal.getInstance(document.getElementById('addPetModal')).hide();
});
</script>
</body>
</html>
