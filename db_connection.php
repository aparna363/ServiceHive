<?php
// db_connection.php - PostgreSQL connection for Render.com

// Database configuration
$config = [
    'host' => 'dpg-cvq3rg15pdvs739tugog-a.oregon-postgres.render.com', // External host
    'port' => '5432',
    'database' => 'serviceshive',
    'username' => 'serviceshive_user',
    'password' => 'YOUR_NEW_PASSWORD' // Change this to your new password
];

// Create a function to get database connection
function getDbConnection() {
    global $config;
    
    // Create connection string
    $conn_string = sprintf(
        "host=%s port=%s dbname=%s user=%s password=%s",
        $config['host'],
        $config['port'],
        $config['database'],
        $config['username'],
        $config['password']
    );
    
    try {
        // Establish connection
        $conn = pg_connect($conn_string);
        
        if (!$conn) {
            throw new Exception("Failed to connect to PostgreSQL: " . pg_last_error());
        }
        
        // Set timezone to match your application needs
        pg_query($conn, "SET timezone = 'Asia/Kolkata'");
        
        return $conn;
    } catch (Exception $e) {
        die("Connection error: " . $e->getMessage());
    }
}

// Function to safely execute queries
function executeQuery($conn, $query, $params = []) {
    if (empty($params)) {
        $result = pg_query($conn, $query);
    } else {
        $result = pg_query_params($conn, $query, $params);
    }
    
    if (!$result) {
        error_log("Query error: " . pg_last_error($conn));
        return false;
    }
    
    return $result;
}

// Helper function to fetch results as associative array
function fetchRows($result) {
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Adaptation of your getCategoryList function for PostgreSQL
function getCategoryList($conn) {
    $query = "SELECT category_id, category_name FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
    $result = executeQuery($conn, $query);
    
    if ($result) {
        return fetchRows($result);
    }
    return [];
}
