<?php
// DB_CONNECT.PHP
// This file handles the connection to the MySQL database.
// I include this file on every page that needs to talk to the database.

// $servername = "localhost";
// $username = "ameer";     // Default 
// $password = "0556";         // Default 
// $dbname = "galadawa_textile_db";

$servername = "sql8.freesqldatabase.com";
$username = "sql8828251";
$password = "HSarGF9BxC";
$dbname = "sql8828251";

// I using MySQLi Object-Oriented style to create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if connection worked
if ($conn->connect_error) {
    // If it failed, kill the page and show error
    die("Connection failed: " . $conn->connect_error);
}

// If the script continues past here, the database is connected!
?>