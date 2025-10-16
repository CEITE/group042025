<?php
$hostname = "localhost"; 
$username = "u765170597_group042025"; 
$password = "He=;?Ke2/s"; 
$dbname = "u765170597_group042025"; 

$conn = new mysqli($hostname,$username,$password,$dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
