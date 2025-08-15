<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VetCareQR Login</title>
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
    .login-container {
      background: white;
      padding: 3rem;
      border-radius: 1.5rem;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 450px;
    }
    .login-container h2 {
      color: #198754;
      font-weight: bold;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .form-control {
      border-radius: 0.75rem;
    }
    .btn-login {
      background-color: #198754;
      color: white;
      border-radius: 0.75rem;
    }
    .btn-login:hover {
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
  <div class="login-container">
    <img src="assets/logo.png" alt="VetCareQR Logo" class="logo">
    <h2>Login to VetCareQR</h2>
    <form action="login.php" method="POST">
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-login">Login</button>
      </div>
      <div class="mt-3 text-center text-muted">
        Don’t have an account? <a href="register.php">Register here</a>
      </div>
    </form>
  </div>
</body>
</html>
