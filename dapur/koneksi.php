<?php
$servername = "localhost";  // Or "127.0.0.1"
$username = "root";          // Default XAMPP username
$password = "";              // Default XAMPP password (usually blank)
$database = "restoran";       // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// echo "Connected successfully"; // Optional: For testing the connection

?>