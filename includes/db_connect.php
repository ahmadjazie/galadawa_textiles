<?php
// DB_CONNECT.PHP

$servername = "sql8.freesqldatabase.com";
$username = "sql8828251";
$password = "HSarGF9BxC";
$dbname = "sql8828251";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>