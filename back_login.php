<?php
session_start();
include "conn.php"; // make sure this file has your DB connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Prepare query
    $stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM users_1 WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $name, $db_email, $db_password, $role);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $db_password)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;

            // Redirect by role
            if ($role == 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($role == 'vet') {
                header("Location: vet_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            echo "<script>alert('Invalid password!'); window.location='login.php';</script>";
        }
    } else {
        echo "<script>alert('No account found with that email!'); window.location='login.php';</script>";
    }
    $stmt->close();
}
$conn->close();
?>
