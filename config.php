<?php
// Database Configuration
$host = 'localhost';
$dbname = 'project';  // Change this to your database name
$username = 'root';         // Default XAMPP username
$password = '';             // Default XAMPP password (empty)

// Create connection using MySQLi
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
else {
    // Connection successful
    // You can uncomment the line below for debugging purposes
    // echo "Connected successfully";
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

if (!defined('PASSWORD_ARGON2ID')) {
    die('Server does not support Argon2id password hashing.');
}

function hashPasswordArgon2id($plainPassword) {
    return password_hash($plainPassword, PASSWORD_ARGON2ID);
}

function verifyPasswordArgon2id($plainPassword, $storedHash) {
    if (!is_string($storedHash) || $storedHash === '') {
        return false;
    }

    $info = password_get_info($storedHash);
    if (($info['algo'] ?? 0) !== PASSWORD_ARGON2ID) {
        return false;
    }

    return password_verify($plainPassword, $storedHash);
}

// Optional: Function to safely close connection
function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Optional: Function for prepared statements (prevents SQL injection)
function prepareAndExecute($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt;
}
?>
