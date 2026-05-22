<?php
include 'config.php';
$res = $conn->query("SHOW TABLES LIKE 'activity_logs'");
if ($res && $res->num_rows > 0) {
    echo "TABLE_EXISTS\n";
    $res2 = $conn->query("SELECT COUNT(*) FROM activity_logs");
    if ($res2) {
        $row = $res2->fetch_row();
        echo "LOG_COUNT: " . $row[0] . "\n";
    } else {
        echo "SELECT_ERROR: " . $conn->error . "\n";
    }
} else {
    echo "TABLE_NOT_FOUND: " . ($conn->error ?: "No error, table just doesn't exist.") . "\n";
}
?>
