<?php
session_start();
include 'config.php';

// Update logout_time if user is logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE user SET logout_time = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>
