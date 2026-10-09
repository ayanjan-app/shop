<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ph";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}
?>
