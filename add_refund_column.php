<?php
require_once 'dbconnect.php';

// First check if the column already exists
$check_query = "SHOW COLUMNS FROM bookings LIKE 'refund_amount'";
$result = $conn->query($check_query);

if ($result->num_rows == 0) {
    // Column doesn't exist, so add it
    $sql = "ALTER TABLE bookings 
            ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT NULL 
            AFTER total_amount";

    if ($conn->query($sql) === TRUE) {
        echo "Refund amount column added successfully";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'refund_amount' already exists in the bookings table";
}

// Verify the column structure
echo "<br><br>Current table structure:<br>";
$structure_query = "DESCRIBE bookings";
$structure_result = $conn->query($structure_query);

if ($structure_result) {
    echo "<pre>";
    while ($row = $structure_result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
}

$conn->close();
?> 