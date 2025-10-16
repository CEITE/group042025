<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VetCareQR — Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --pink: #ffd6e7;
      --pink-2: #f7c5e0;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f9fbfd;
    }
    /* Sidebar */
    #sidebar {
      width: 250px;
      min-height: 100vh;
      background: var(--pink-2);
      padding: 20px;
    }
    #sidebar h2 {
      font-size: 1.3rem;
      font-weight: bold;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    #sidebar .nav-link {
      font-weight: 500;
      color: #333;
      padding: 12px 16px;
      border-radius: 10px;
      margin: 4px 0;
      transition: 0.3s;
    }
    #sidebar .nav-link:hover {
      background-color: var(--pink);
      color: #000;
    }
    #sidebar .nav-link.active {
      background-color: #0d6efd;
      color: white;
    }
    /* Content */
    .content {
      flex-grow: 1;
      padding: 2rem;
    }
    .card {
      border-radius: 1rem;
      box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    }
    .btn {
      border-radius: 8px;
    }
  </style>
</head>
<body>
<div class="d-flex">
  <!-- Sidebar -->
  <div id="sidebar">
    <h2><i class="fa-solid fa-paw"></i> VetCareQR</h2>
    <a href="user_dashboard.php" class="nav-link"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a>
    <a href="pet_profile.php" class="nav-link"><i class="fa-solid fa-dog me-2"></i> My Pets</a>
    <a href="user_settings.php" class="nav-link active"><i class="fa-solid fa-gear me-2"></i> Settings</a>
    <a href="logout.php" class="btn btn-danger w-100 mt-3"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
  </div>

  <!-- Content -->
  <div class="content">
    <h2 class="mb-4">⚙️ Account Settings</h2>

    <!-- Profile Update -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Update Profile</h5>
        <form>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="John Doe">
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" value="johndoe@email.com">
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-2"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Password Update -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</h5>
        <form>
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control">
          </div>
          <button type="submit" class="btn btn-warning"><i class="fa-solid fa-lock me-2"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
