<?php
session_start();
include("conn.php");

// PHPMailer includes
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";
$show_otp_form = false;
$show_reset_form = false;

// Function to generate OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Function to send OTP using PHPMailer
function sendPasswordResetOTP($email, $name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alimoromaira13@gmail.com'; // Your Gmail
        $mail->Password   = 'mxzbmhpuuruyrffn'; // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('alimoromaira13@gmail.com', 'PetMedQR');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'VetCare - Password Reset Verification Code';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .header { text-align: center; color: #0ea5e9; }
                .otp-code { font-size: 32px; font-weight: bold; text-align: center; color: #0ea5e9; margin: 20px 0; padding: 15px; background: #f0f9ff; border-radius: 10px; letter-spacing: 5px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐾 PetMedQR</h2>
                    <h3>Password Reset Request</h3>
                </div>
                <p>Hello <strong>$name</strong>,</p>
                <p>We received a request to reset your password. Use the following OTP code to verify your identity:</p>
                <div class='otp-code'>$otp</div>
                <div class='warning'>
                    <strong>Note:</strong> This code will expire in 10 minutes. If you didn't request a password reset, please ignore this email and your account will remain secure.
                </div>
                <div class='footer'>
                    <p>© 2025 PetMedQR - Professional Pet Medical Records System</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $name,\n\nWe received a request to reset your password. Your verification code is: $otp\nThis code will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.\n\n© 2025 PetMedQR";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle email submission for password reset
if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE email = ? AND is_verified = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $name = $user['name'];
            
            // Generate OTP
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session and database
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            $_SESSION['reset_user_id'] = $user_id;
            $_SESSION['reset_email'] = $email;
            
            // Send OTP via email
            if (sendPasswordResetOTP($email, $name, $otp)) {
                $show_otp_form = true;
                $success = "OTP sent to your email! Please check your inbox (and spam folder).";
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        } else {
            $error = "No account found with this email address.";
        }
        $stmt->close();
    }
}

// Handle OTP verification for password reset
if (isset($_POST['verify_reset_otp'])) {
    $entered_otp = implode('', $_POST['otp']);
    $stored_otp = $_SESSION['reset_otp'] ?? '';
    $otp_expiry = $_SESSION['reset_otp_expiry'] ?? '';
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP code";
    } elseif (time() > strtotime($otp_expiry)) {
        $error = "OTP has expired. Please request a new one.";
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry']);
    } elseif ($entered_otp !== $stored_otp) {
        $error = "Invalid OTP code. Please try again.";
    } else {
        // OTP verified successfully, show password reset form
        $show_otp_form = false;
        $show_reset_form = true;
        $success = "OTP verified! You can now reset your password.";
    }
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['reset_user_id'] ?? '';
    
    if (empty($user_id)) {
        $error = "Session expired. Please start over.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in database
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "Password reset successfully! You can now login with your new password.";
            // Clear session data
            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id'], $_SESSION['reset_email']);
            $show_reset_form = false;
        } else {
            $error = "Error resetting password. Please try again.";
        }
        $update_stmt->close();
    }
}

// Handle OTP resend
if (isset($_POST['resend_otp'])) {
    $email = $_SESSION['reset_email'] ?? '';
    $user_id = $_SESSION['reset_user_id'] ?? '';
    
    if ($email && $user_id) {
        // Get user name
        $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $name = $user['name'];
            
            // Generate new OTP
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Update OTP in session
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            
            // Send new OTP
            if (sendPasswordResetOTP($email, $name, $otp)) {
                $success = "New OTP sent to your email! Please check your inbox.";
            } else {
                $error = "Failed to resend OTP. Please try again.";
            }
        }
        $stmt->close();
    } else {
        $error = "Session expired. Please start over.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PetMedQR — Forgot Password</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    :root {
      --primary: #0ea5e9;
      --primary-dark: #0284c7;
      --primary-light: #e0f2fe;
      --secondary: #8b5cf6;
      --light: #f0f9ff;
      --dark: #1f2937;
      --gray: #6b7280;
      --gray-light: #e5e7eb;
    }

    body {
      font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif;
      color: var(--dark);
      background: #fff;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .password-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(
        1200px 600px at 15% 20%,
        #e0f2fe 0%,
        #bae6fd 40%,
        var(--primary-light) 100%
      );
      padding: 40px 20px;
    }

    .password-card {
      background: #fff;
      border: 1px solid var(--primary-light);
      border-radius: 24px;
      box-shadow: 0 15px 40px rgba(14, 165, 233, 0.12);
      padding: 40px 35px;
      max-width: 500px;
      width: 100%;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .password-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 50px rgba(14, 165, 233, 0.15);
    }

    .logo-container {
      text-align: center;
      margin-bottom: 25px;
    }

    .logo-icon {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      width: 70px;
      height: 70px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      font-size: 28px;
    }

    .welcome-title {
      font-weight: 800;
      margin-bottom: 5px;
      color: var(--primary-dark);
      font-size: 28px;
    }

    .welcome-subtitle {
      color: var(--gray);
      margin-bottom: 30px;
      font-size: 15px;
      line-height: 1.5;
    }

    .form-label {
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--dark);
      font-size: 14px;
    }

    .input-group {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
      z-index: 5;
    }

    .form-control {
      border-radius: 12px;
      padding: 0.85rem 1rem 0.85rem 3rem;
      transition: all 0.2s ease;
    }

    .form-control:focus {
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
      border-color: var(--primary);
    }

    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--gray);
      cursor: pointer;
      z-index: 5;
    }

    .btn-primary {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      font-weight: 700;
      transition: all 0.2s ease;
      box-shadow: 0 4px 10px rgba(14, 165, 233, 0.25);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(14, 165, 233, 0.3);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-outline-primary {
      background: transparent;
      color: var(--primary);
      border: 2px solid var(--primary);
      border-radius: 12px;
      padding: 0.9rem 1.2rem;
      font-weight: 700;
      transition: all 0.2s ease;
    }

    .btn-outline-primary:hover {
      background: var(--primary);
      color: #fff;
    }

    .login-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: color 0.2s ease;
    }

    .login-link:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    .alert {
      border-radius: 12px;
      padding: 0.85rem 1rem;
      margin-bottom: 20px;
    }

    .password-strength {
      height: 5px;
      margin-top: 5px;
      border-radius: 5px;
      background: var(--gray-light);
      overflow: hidden;
    }

    .password-strength-bar {
      height: 100%;
      width: 0;
      transition: width 0.3s ease;
      border-radius: 5px;
    }

    .password-requirements {
      font-size: 12px;
      color: var(--gray);
      margin-top: 5px;
    }

    footer {
      background: var(--light);
      border-top: 1px solid var(--primary-light);
      padding: 1.5rem 0;
      margin-top: auto;
    }

    /* OTP Input Styles */
    .otp-container {
      text-align: center;
      margin: 30px 0;
    }

    .otp-inputs {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 20px 0;
    }

    .otp-input {
      width: 50px;
      height: 60px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      border: 2px solid var(--gray-light);
      border-radius: 12px;
      background: var(--light);
      transition: all 0.3s ease;
    }

    .otp-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
      outline: none;
      background: #fff;
    }

    .otp-input.filled {
      border-color: var(--primary);
      background: var(--primary-light);
    }

    .timer {
      color: var(--primary-dark);
      font-weight: 600;
      margin: 10px 0;
    }

    .demo-note {
      background: var(--primary-light);
      padding: 10px;
      border-radius: 8px;
      margin: 15px 0;
      font-size: 14px;
      text-align: center;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      margin-bottom: 20px;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <section class="password-section">
    <div class="password-card">
      <a href="login.php" class="back-link">
        <i class="fas fa-arrow-left me-2"></i>Back to Login
      </a>

      <div class="logo-container">
        <div class="logo-icon">
          <i class="fas fa-key"></i>
        </div>
        <h1 class="welcome-title">
          <?php 
          if ($show_reset_form) {
              echo 'Reset Password';
          } elseif ($show_otp_form) {
              echo 'Verify Identity';
          } else {
              echo 'Forgot Password';
          }
          ?>
        </h1>
        <p class="welcome-subtitle">
          <?php 
          if ($show_reset_form) {
              echo 'Enter your new password below';
          } elseif ($show_otp_form) {
              $reset_email = $_SESSION['reset_email'] ?? '';
              echo "Enter the 6-digit code sent to $reset_email";
          } else {
              echo "Enter your email address and we'll send you an OTP to reset your password";
          }
          ?>
        </p>
      </div>

      <!-- Show error/success messages -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="fas fa-check-circle me-2"></i>
          <div><?php echo htmlspecialchars($success); ?></div>
        </div>
      <?php endif; ?>

      <?php if ($show_reset_form): ?>
        <!-- Password Reset Form -->
        <form method="POST" action="">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <div class="input-group">
              <span class="input-icon">
                <i class="fas fa-lock"></i>
              </span>
              <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter new password" required />
              <button type="button" class="password-toggle" id="newPasswordToggle">
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
            <label class="form-label">Confirm New Password</label>
            <div class="input-group">
              <span class="input-icon">
                <i class="fas fa-lock"></i>
              </span>
              <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm new password" required />
              <button type="button" class="password-toggle" id="confirmPasswordToggle">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="passwordMatch" class="mt-2 small"></div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" name="reset_password" class="btn btn-primary">
              <i class="fas fa-save me-2"></i>Reset Password
            </button>
          </div>
        </form>

      <?php elseif ($show_otp_form): ?>
        <!-- OTP Verification Form -->
        <form method="POST" action="">
          <div class="demo-note">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Email Sent:</strong> Check your email inbox and spam folder for the OTP code.
          </div>

          <div class="otp-container">
            <div class="otp-inputs">
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="0" required>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="1" required>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="2" required>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="3" required>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="4" required>
              <input type="text" name="otp[]" class="otp-input" maxlength="1" data-index="5" required>
            </div>
            
            <div class="timer">
              <i class="fas fa-clock me-2"></i>
              OTP expires in <span id="countdown">10:00</span>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" name="verify_reset_otp" class="btn btn-primary">
              <i class="fas fa-check-circle me-2"></i>Verify OTP
            </button>
            <button type="submit" name="resend_otp" class="btn btn-outline-primary">
              <i class="fas fa-redo me-2"></i>Resend OTP
            </button>
          </div>
        </form>

      <?php else: ?>
        <!-- Email Input Form -->
        <form method="POST" action="">
          <div class="mb-4">
            <label class="form-label">Email Address</label>
            <div class="input-group">
              <span class="input-icon">
                <i class="fas fa-envelope"></i>
              </span>
              <input type="email" name="email" class="form-control" placeholder="Enter your registered email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required />
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" name="send_otp" class="btn btn-primary">
              <i class="fas fa-paper-plane me-2"></i>Send OTP
            </button>
          </div>
        </form>
      <?php endif; ?>

      <div class="text-center mt-3">
        <p class="mb-0">Remember your password?
          <a href="login.php" class="login-link">Sign In</a>
        </p>
      </div>
    </div>
  </section>

  <footer class="py-4 text-center">
    <small>© 2025 PetMedQR — Professional Pet Medical Records System</small>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Password visibility toggle
    document.getElementById('newPasswordToggle')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('newPassword');
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
    document.getElementById('newPassword')?.addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('passwordStrengthBar');
      let strength = 0;
      
      if (password.length >= 8) strength += 25;
      if (/[A-Z]/.test(password)) strength += 25;
      if (/[0-9]/.test(password)) strength += 25;
      if (/[^A-Za-z0-9]/.test(password)) strength += 25;
      
      // Update strength bar
      if (strengthBar) {
        strengthBar.style.width = strength + '%';
        
        // Update color
        if (strength < 50) {
          strengthBar.style.backgroundColor = '#dc3545';
        } else if (strength < 75) {
          strengthBar.style.backgroundColor = '#fd7e14';
        } else {
          strengthBar.style.backgroundColor = '#28a745';
        }
      }
    });

    // Password match checking
    document.getElementById('confirmPassword')?.addEventListener('input', function() {
      const password = document.getElementById('newPassword').value;
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

    // OTP Input Handling
    const otpInputs = document.querySelectorAll('.otp-input');
    if (otpInputs.length > 0) {
      otpInputs.forEach(input => {
        input.addEventListener('input', function(e) {
          const value = e.target.value;
          const index = parseInt(this.getAttribute('data-index'));
          
          // Only allow numbers
          if (!/^\d*$/.test(value)) {
            this.value = '';
            return;
          }
          
          // Update filled class
          if (value) {
            this.classList.add('filled');
          } else {
            this.classList.remove('filled');
          }
          
          // Auto-focus next input
          if (value && index < 5) {
            otpInputs[index + 1].focus();
          }
        });
        
        input.addEventListener('keydown', function(e) {
          const index = parseInt(this.getAttribute('data-index'));
          
          // Handle backspace
          if (e.key === 'Backspace' && !this.value && index > 0) {
            otpInputs[index - 1].focus();
          }
        });
        
        input.addEventListener('paste', function(e) {
          e.preventDefault();
          const pasteData = e.clipboardData.getData('text').slice(0, 6);
          
          if (/^\d+$/.test(pasteData)) {
            for (let i = 0; i < pasteData.length; i++) {
              if (i < 6) {
                otpInputs[i].value = pasteData[i];
                otpInputs[i].classList.add('filled');
              }
            }
            
            if (pasteData.length === 6) {
              otpInputs[5].focus();
            } else {
              otpInputs[pasteData.length].focus();
            }
          }
        });
      });
      
      // Focus first OTP input
      otpInputs[0].focus();
      
      // Countdown timer
      let timeLeft = 600; // 10 minutes in seconds
      const countdownElement = document.getElementById('countdown');
      
      function updateCountdown() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        if (countdownElement) {
          countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
          
          if (timeLeft > 0) {
            timeLeft--;
            setTimeout(updateCountdown, 1000);
          } else {
            countdownElement.textContent = '00:00';
            countdownElement.style.color = '#dc3545';
          }
        }
      }
      
      updateCountdown();
    }
  </script>
</body>
</html>

