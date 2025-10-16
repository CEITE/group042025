<?php
include "connect.php"; // DB connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs safely
    $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
    $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // Combine first and last name
    $name = $fname . " " . $lname;

    // Validate passwords
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.location='register.php';</script>";
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare SQL (only name, email, password since your table has no role column)
    $stmt = $conn->prepare("INSERT INTO users_1 (name, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        die("SQL error: " . $conn->error);
    }

    $stmt->bind_param("sss", $name, $email, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Please login.'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Error: Could not register. Email might already exist.'); window.location='register.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>
