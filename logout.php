<?php
session_start();
session_unset();    // Clear variables
session_destroy();  // Destroy the session
header("Location: login.php"); // Go back to login
exit();
?>