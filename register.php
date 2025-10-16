<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

// Check if users table exists and has the required columns
$checkTable = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkTable->num_rows == 0) {
    // Create users table if it doesn't exist
    $createTable = $conn->query("CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'vet') DEFAULT 'user',
        phone_number VARCHAR(20),
        address TEXT,
        is_verified TINYINT(1) DEFAULT 0,
        otp_code VARCHAR(6),
        otp_expiry DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} else {
    // Check if columns exist
    $checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
    if ($checkColumns->num_rows == 0) {
        // Add OTP columns to existing users table
        $alterTable = $conn->query("ALTER TABLE users 
            ADD COLUMN is_verified TINYINT(1) DEFAULT 0,
            ADD COLUMN otp_code VARCHAR(6),
            ADD COLUMN otp_expiry DATETIME");
    }
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
        $mail->Username   = 'alimoromaira13@gmail.com'; // Your Gmail
        $mail->Password   = 'mxzbmhpuuruyrffn'; // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 30; // Increase timeout
        $mail->SMTPDebug  = 0; // Set to 0 for production

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
    $entered_otp = implode('', $_POST['otp']);
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
                            // Clear session data
                            unset($_SESSION['pending_user']);
                            $show_otp_form = false;
                        } else {
                            $error = "Error activating account: " . $conn->error;
                        }
                        $update_stmt->close();
                    } else {
                        $error = "Database error: " . $conn->error;
                    }
                }
            } else {
                $error = "OTP not found or account already verified. Please register again.";
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['verify_otp']) && !isset($_POST['resend_otp'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    
    // Get the selected role, default to 'user' if not provided or invalid
    $role = "user";
    if (isset($_POST['role']) && in_array($_POST['role'], ['vet', 'user'])) {
        $role = $_POST['role'];
    }

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
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 1");
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
                        $update_stmt = $conn->prepare("UPDATE users SET name = ?, password = ?, role = ?, phone_number = ?, address = ?, otp_code = ?, otp_expiry = ? WHERE email = ? AND is_verified = 0");
                        if ($update_stmt) {
                            $update_stmt->bind_param("ssssssss", $name, $hashed_password, $role, $phone_number, $address, $otp, $otp_expiry, $email);
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
                            // Store user data in session for after OTP verification
                            $_SESSION['pending_user'] = [
                                'name' => $name,
                                'email' => $email
                            ];
                            
                            $show_otp_form = true;
                            $success = "OTP sent to your email! Please check your inbox (and spam folder).";
                        } else {
                            $error = "Failed to send OTP. Please check your email address and try again.";
                            // Remove the unverified user if email failed
                            $delete_stmt = $conn->prepare("DELETE FROM users WHERE email = ? AND is_verified = 0");
                            if ($delete_stmt) {
                                $delete_stmt->bind_param("s", $email);
                                $delete_stmt->execute();
                                $delete_stmt->close();
                            }
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
                    $error = "Failed to resend OTP. Please try again.";
                }
            } else {
                $error = "Error updating OTP. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    } else {
        $error = "Session expired. Please register again.";
    }
}
?>


