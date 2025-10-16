<?php
$servername = "localhost"; 
$username = "u765170597_group042025"; 
$password = "He=;?Ke2/s"; 
$database = "u765170597_group042025"; 

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
