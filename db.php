
<?php
$servername = "localhost";
$username = "naqibullah_shopuser";
$password = "@Ahmadzai123";
$dbname = "naqibullah_shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");



/*
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

*/
?>



