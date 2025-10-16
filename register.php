<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("conn.php");

// Check if PHPMailer files exist
if (!file_exists('PHPMailer/src/PHPMailer.php')) {
    die("PHPMailer not found. Please download PHPMailer.");
}

// PHPMailer includes
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";
$show_otp_form = false;

// Check if OTP columns exist, add them if missing
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
if ($checkColumns->num_rows == 0) {
    $conn->query("ALTER TABLE users 
        ADD COLUMN is_verified TINYINT(1) DEFAULT 0,
        ADD COLUMN otp_code VARCHAR(6),
        ADD COLUMN otp_expiry DATETIME");
}

// Function to generate OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Function to send OTP using PHPMailer
function sendOTP($email, $name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alimoromaira13@gmail.com';
        $mail->Password   = 'mxzbmhpuuruyrffn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30;
        $mail->SMTPDebug  = 0;

        // Recipients
        $mail->setFrom('alimoromaira13@gmail.com', 'VetCareQR');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'VetCareQR - Email Verification Code';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .header { text-align: center; color: #bf3b78; }
                .otp-code { font-size: 32px; font-weight: bold; text-align: center; color: #bf3b78; margin: 20px 0; padding: 15px; background: #fff8fc; border-radius: 10px; letter-spacing: 5px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐾 VetCareQR</h2>
                    <h3>Email Verification</h3>
                </div>
                <p>Hello <strong>$name</strong>,</p>
                <p>Thank you for registering with VetCareQR. Use the following OTP code to verify your email address:</p>
                <div class='otp-code'>$otp</div>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
                <div class='footer'>
                    <p>© 2025 VetCareQR - Santa Rosa Municipal Veterinary Services</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nYour VetCareQR verification code is: $otp\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $entered_otp = implode('', $_POST['otp'] ?? []);
    $email = $_SESSION['pending_user']['email'] ?? '';
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP code";
    } elseif (empty($email)) {
        $error = "Session expired. Please register again.";
    } else {
        // Check OTP from database
        $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stored_otp = $row['otp_code'];
                $otp_expiry = $row['otp_expiry'];
                
                if (time() > strtotime($otp_expiry)) {
                    $error = "OTP has expired. Please request a new one.";
                } elseif ($entered_otp !== $stored_otp) {
                    $error = "Invalid OTP code. Please try again.";
                } else {
                    // OTP verified successfully, activate the user account
                    $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("s", $email);
                        
                        if ($update_stmt->execute()) {
                            $success = "Registration successful! Your account has been verified. You can now login.";
                            unset($_SESSION['pending_user']);
                            $show_otp_form = false;
                        } else {
                            $error = "Error activating account: " . $conn->error;
                        }
                        $update_stmt->close();
                    }
                }
            } else {
                $error = "OTP not found or account already verified. Please register again.";
            }
            $stmt->close();
        }
    }
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['verify_otp']) && !isset($_POST['resend_otp'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Role is always 'user'
    $role = "user";

    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $password)) {
        $error = "Password must contain both letters and numbers";
    } elseif (!empty($phone_number) && !preg_match('/^\+?[0-9]{10,15}$/', $phone_number)) {
        $error = "Please enter a valid phone number (10-15 digits, optional + prefix)";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if email already exists and is verified
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND is_verified = 1");
        if (!$stmt) {
            $error = "SQL Error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email already exists!";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate OTP
                $otp = generateOTP();
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Check if unverified user exists, update or insert
                $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND is_verified = 0");
                if ($check_stmt) {
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    $user_saved = false;
                    if ($check_result->num_rows > 0) {
                        // Update existing unverified user
                        $update_stmt = $conn->prepare("UPDATE users SET name = ?, password = ?, phone_number = ?, address = ?, otp_code = ?, otp_expiry = ? WHERE email = ? AND is_verified = 0");
                        if ($update_stmt) {
                            $update_stmt->bind_param("sssssss", $name, $hashed_password, $phone_number, $address, $otp, $otp_expiry, $email);
                            $user_saved = $update_stmt->execute();
                            $update_stmt->close();
                        }
                    } else {
                        // Insert new user
                        $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone_number, address, otp_code, otp_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($insert_stmt) {
                            $insert_stmt->bind_param("ssssssss", $name, $email, $hashed_password, $role, $phone_number, $address, $otp, $otp_expiry);
                            $user_saved = $insert_stmt->execute();
                            $insert_stmt->close();
                        }
                    }
                    $check_stmt->close();
                    
                    if ($user_saved) {
                        // Send OTP via email
                        if (sendOTP($email, $name, $otp)) {
                            $_SESSION['pending_user'] = ['name' => $name, 'email' => $email];
                            $show_otp_form = true;
                            $success = "OTP sent to your email! Please check your inbox (and spam folder).";
                        } else {
                            // Fallback: show OTP on screen if email fails
                            $_SESSION['pending_user'] = ['name' => $name, 'email' => $email];
                            $show_otp_form = true;
                            $success = "Email sending failed. Your OTP code is: <strong>$otp</strong> - Enter this code to verify.";
                        }
                    } else {
                        $error = "Error saving user data. Please try again.";
                    }
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
}

// Handle OTP resend
if (isset($_POST['resend_otp'])) {
    $pending_user = $_SESSION['pending_user'] ?? null;
    if ($pending_user) {
        $email = $pending_user['email'];
        $name = $pending_user['name'];
        
        // Generate new OTP
        $otp = generateOTP();
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Update OTP in database
        $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ? AND is_verified = 0");
        if ($update_stmt) {
            $update_stmt->bind_param("sss", $otp, $otp_expiry, $email);
            
            if ($update_stmt->execute()) {
                // Send new OTP
                if (sendOTP($email, $name, $otp)) {
                    $success = "New OTP sent to your email! Please check your inbox.";
                } else {
                    $success = "New OTP: <strong>$otp</strong> - Enter this code to verify.";
                }
            } else {
                $error = "Error updating OTP. Please try again.";
            }
            $update_stmt->close();
        }
    } else {
        $error = "Session expired. Please register again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
      --pink: #ffd6e7; --pink-dark: #bf3b78; --pink-darker: #9c2c60; --ink: #2a2e34;
    }
    body { font-family: system-ui, sans-serif; color: var(--ink); background: #fff; display: flex; flex-direction: column; min-height: 100vh; }
    .register-section { flex: 1; display: flex; align-items: center; justify-content: center; background: radial-gradient(1200px 600px at 15% 20%, #ffe7f2 0%, #ffd8ec 40%, var(--pink) 100%); padding: 40px 20px; }
    .register-card { background: #fff; border: 1px solid #f0ddea; border-radius: 24px; box-shadow: 0 15px 40px rgba(184, 71, 129, 0.12); padding: 40px 35px; max-width: 500px; width: 100%; }
    .logo-container { text-align: center; margin-bottom: 25px; }
    .logo-icon { background: linear-gradient(135deg, var(--pink-dark), var(--pink-darker)); color: white; width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 28px; }
    .welcome-title { font-weight: 800; margin-bottom: 5px; color: var(--pink-darker); font-size: 28px; }
    .form-label { font-weight: 600; margin-bottom: 8px; color: var(--ink); font-size: 14px; }
    .input-group { position: relative; }
    .input-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; z-index: 5; }
    .form-control { border-radius: 12px; padding: 0.85rem 1rem 0.85rem 3rem; }
    .btn-pink { background: var(--pink-dark); color: #fff; border: none; border-radius: 12px; padding: 0.9rem 1.2rem; font-weight: 700; }
    .otp-inputs { display: flex; gap: 10px; justify-content: center; margin: 20px 0; }
    .otp-input { width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid #e9ecef; border-radius: 12px; }
    
    /* Password Strength Styles */
    .password-strength { height: 5px; margin-top: 5px; border-radius: 5px; background: #e9ecef; overflow: hidden; }
    .password-strength-bar { height: 100%; width: 0; transition: width 0.3s ease; border-radius: 5px; }
    .password-requirements { font-size: 12px; color: #6c757d; margin-top: 5px; }
    .password-toggle { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; cursor: pointer; z-index: 5; }
  </style>
</head>
<body>

  <section class="register-section">
    <div class="register-card">
      <div class="logo-container">
        <div class="logo-icon"><i class="fas fa-paw"></i></div>
        <h1 class="welcome-title"><?php echo $show_otp_form ? 'Verify Email' : 'Create Account'; ?></h1>
        <p class="text-muted">
          <?php echo $show_otp_form ? 'Enter the 6-digit verification code' : 'Join VetCareQR to manage pet health'; ?>
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center">
          <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center">
          <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        </div>
      <?php endif; ?>

      <?php if ($show_otp_form): ?>
        <!-- OTP Form -->
        <form method="POST">
          <div class="otp-inputs">
            <?php for ($i = 0; $i < 6; $i++): ?>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="<?php echo $i; ?>" required>
            <?php endfor; ?>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" name="verify_otp" class="btn btn-pink">Verify Account</button>
            <button type="submit" name="resend_otp" class="btn btn-outline-secondary">Resend OTP</button>
          </div>
        </form>
      <?php else: ?>
        <!-- Registration Form -->
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-user"></i></span>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Email</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-envelope"></i></span>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-phone"></i></span>
              <input type="tel" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($phone_number ?? ''); ?>">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Address</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-home"></i></span>
              <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($address ?? ''); ?>">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-lock"></i></span>
              <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required>
              <button type="button" class="password-toggle" id="passwordToggle">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="password-strength mt-2">
              <div class="password-strength-bar" id="passwordStrengthBar"></div>
            </div>
            <div class="password-requirements">
              Must be at least 8 characters with letters and numbers
            </div>
          </div>
          
          <div class="mb-4">
            <label class="form-label">Confirm Password</label>
            <div class="input-group">
              <span class="input-icon"><i class="fas fa-lock"></i></span>
              <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm your password" required>
              <button type="button" class="password-toggle" id="confirmPasswordToggle">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="passwordMatch" class="mt-2 small"></div>
          </div>
          
          <button type="submit" class="btn btn-pink w-100">Create Account</button>
        </form>
      <?php endif; ?>

      <div class="text-center mt-3">
        <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Sign In</a></p>
      </div>
    </div>
  </section>

  <script>
    // Password visibility toggle
    document.getElementById('passwordToggle')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Confirm password visibility toggle
    document.getElementById('confirmPasswordToggle')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('confirmPassword');
      const icon = this.querySelector('i');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Password strength indicator
    document.getElementById('password')?.addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('passwordStrengthBar');
      let strength = 0;
      
      if (password.length >= 8) strength += 25;
      if (/[A-Z]/.test(password)) strength += 25;
      if (/[0-9]/.test(password)) strength += 25;
      if (/[^A-Za-z0-9]/.test(password)) strength += 25;
      
      // Update strength bar
      strengthBar.style.width = strength + '%';
      
      // Update color
      if (strength < 50) {
        strengthBar.style.backgroundColor = '#dc3545';
      } else if (strength < 75) {
        strengthBar.style.backgroundColor = '#fd7e14';
      } else {
        strengthBar.style.backgroundColor = '#28a745';
      }
    });

    // Password match checking
    document.getElementById('confirmPassword')?.addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirmPassword = this.value;
      const matchElement = document.getElementById('passwordMatch');
      
      if (confirmPassword === '') {
        matchElement.textContent = '';
        matchElement.style.color = '';
      } else if (password === confirmPassword) {
        matchElement.textContent = 'Passwords match';
        matchElement.style.color = '#28a745';
      } else {
        matchElement.textContent = 'Passwords do not match';
        matchElement.style.color = '#dc3545';
      }
    });

    // OTP Input handling
    const otpInputs = document.querySelectorAll('.otp-input');
    if (otpInputs.length > 0) {
      otpInputs.forEach(input => {
        input.addEventListener('input', function(e) {
          const index = parseInt(this.getAttribute('data-index'));
          if (this.value && index < 5) otpInputs[index + 1].focus();
        });
        input.addEventListener('keydown', function(e) {
          const index = parseInt(this.getAttribute('data-index'));
          if (e.key === 'Backspace' && !this.value && index > 0) otpInputs[index - 1].focus();
        });
      });
      otpInputs[0].focus();
    }
  </script>
</body>
</html>
