<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VetCareQR Registration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #0d6efd, #198754);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .register-container {
      background: white;
      padding: 3rem;
      border-radius: 1.5rem;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 550px;
    }
    .register-container h2 {
      color: #198754;
      font-weight: bold;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .form-control {
      border-radius: 0.75rem;
    }
    .btn-register {
      background-color: #198754;
      color: white;
      border-radius: 0.75rem;
    }
    .btn-register:hover {
      background-color: #157347;
    }
    .logo {
      width: 60px;
      display: block;
      margin: 0 auto 1rem;
    }
    .text-muted {
      font-size: 0.9rem;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <img src="assets/logo.png" alt="VetCareQR Logo" class="logo">
    <h2>Create Account</h2>
    <form action="register.php" method="POST">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="fname" class="form-label">First Name</label>
          <input type="text" class="form-control" id="fname" name="fname" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="lname" class="form-label">Last Name</label>
          <input type="text" class="form-control" id="lname" name="lname" required>
        </div>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Role</label>
        <select class="form-select" id="role" name="role" required>
          <option value="owner">Pet Owner</option>
          <option value="vet">Veterinarian</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="terms" required>
        <label class="form-check-label" for="terms">
          I agree to the <a href="#">Terms and Conditions</a>
        </label>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-register">Register</button>
      </div>
      <div class="mt-3 text-center text-muted">
        Already have an account? <a href="login.php">Login here</a>
      </div>
    </form>
  </div>
</body>
</html>
