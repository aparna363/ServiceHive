<?php
// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'serviceshive'
];

try {
    // Create connection without database initially
    $conn = new mysqli($config['host'], $config['username'], $config['password']);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS {$config['database']}")) {
        throw new Exception("Error creating database: " . $conn->error);
    }

    // Select the database
    if (!$conn->select_db($config['database'])) {
        throw new Exception("Error selecting database: " . $conn->error);
    }

    // Temporarily disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=0");

    // Array of table creation queries - ordered to handle foreign key dependencies
    $tables = [
        // Tables with no foreign keys first
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user', 'service_provider') DEFAULT 'user',
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            email_verified TINYINT(1) DEFAULT 0,
            verification_token VARCHAR(255),
            token_expiry TIMESTAMP NULL,
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(2),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (email)
        )",

        'tbl_categories' => "CREATE TABLE IF NOT EXISTS tbl_categories (
            category_id INT AUTO_INCREMENT,
            category_name VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (category_id)
        )",

        'service_providers' => "CREATE TABLE IF NOT EXISTS service_providers (
            provider_id INT AUTO_INCREMENT,
            user_id INT NOT NULL,
            category_id INT,
            business_name VARCHAR(100),
            description TEXT,
            verified_status BOOLEAN DEFAULT FALSE,
            certifications TEXT,
            availability JSON,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_reviews INT DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (provider_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id),
            INDEX idx_status (status),
            INDEX idx_verified (verified_status)
        )",

        'tbl_services' => "CREATE TABLE IF NOT EXISTS tbl_services (
            service_id INT AUTO_INCREMENT,
            category_id INT NOT NULL,
            provider_id INT NOT NULL,
            service_name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT,
            pricing_type ENUM('fixed', 'quantity', 'measurement') DEFAULT 'fixed',
            add_to_cart_option BOOLEAN DEFAULT TRUE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (service_id),
            FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES service_providers(provider_id) ON DELETE CASCADE,
            INDEX idx_category (category_id),
            INDEX idx_provider (provider_id),
            INDEX idx_active (is_active)
        )"
    ];

    // Create base tables first
    foreach ($tables as $table_name => $query) {
        if (!$conn->query($query)) {
            throw new Exception("Error creating {$table_name} table: " . $conn->error);
        }
    }

    // Now that we know tbl_services exists, update existing services with appropriate pricing types
    $update_pricing = "UPDATE tbl_services 
                      SET pricing_type = 
                      CASE 
                          WHEN service_name LIKE '%wiring%' THEN 'measurement'
                          WHEN service_name LIKE '%installation%' OR 
                               service_name LIKE '%repair%' OR 
                               service_name LIKE '%fan%' OR 
                               service_name LIKE '%switch%' THEN 'quantity'
                          ELSE 'fixed'
                      END";
            
    if (!$conn->query($update_pricing)) {
        echo "Error updating pricing types: " . $conn->error . "<br>";
    }

    // Create remaining tables
    $remaining_tables = [
        'bookings' => "CREATE TABLE IF NOT EXISTS bookings (
            booking_id INT AUTO_INCREMENT,
            user_id INT NOT NULL,
            provider_id INT NOT NULL,
            service_id INT NOT NULL,
            booking_date DATE NOT NULL,
            time_slot TIME NOT NULL,
            status ENUM('pending', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
            priority ENUM('yes', 'no') DEFAULT 'no',
            total_price DECIMAL(10,2) NOT NULL,
            payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (booking_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES service_providers(provider_id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES tbl_services(service_id) ON DELETE CASCADE,
            INDEX idx_booking_date (booking_date),
            INDEX idx_status (status),
            INDEX idx_payment_status (payment_status)
        )",

        'reviews' => "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            provider_id INT NOT NULL,
            service_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review_text TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (provider_id) REFERENCES service_providers(provider_id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES tbl_services(service_id) ON DELETE CASCADE,
            INDEX idx_rating (rating)
        )",

        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('booking', 'review', 'system', 'payment') NOT NULL,
            reference_id INT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read)
        )",

        'payments' => "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT,
            booking_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_payment_date (payment_date)
        )",

        'tbl_sub_services' => "CREATE TABLE IF NOT EXISTS tbl_sub_services (
            sub_service_id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            sub_service_name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            description TEXT,
            images VARCHAR(255),
            FOREIGN KEY (service_id) REFERENCES tbl_services(service_id) ON DELETE CASCADE
        )"
    ];

    // Create remaining tables
    foreach ($remaining_tables as $table_name => $query) {
        if (!$conn->query($query)) {
            throw new Exception("Error creating {$table_name} table: " . $conn->error);
        }
    }

    // Add missing columns
    $alter_queries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100)",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(2)",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE",
        "ALTER TABLE service_providers ADD COLUMN IF NOT EXISTS category_id INT",
        "ALTER TABLE service_providers ADD FOREIGN KEY IF NOT EXISTS (category_id) REFERENCES tbl_categories(category_id)",
        "ALTER TABLE tbl_categories ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE tbl_services ADD COLUMN IF NOT EXISTS image_path VARCHAR(255)",
        "ALTER TABLE tbl_sub_services ADD COLUMN IF NOT EXISTS images VARCHAR(255)"
    ];

    foreach ($alter_queries as $query) {
        if (!$conn->query($query)) {
            echo "Error executing alter query: " . $conn->error . "<br>";
        }
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");

    // Set timezone
    if (!$conn->query("SET time_zone = '+05:30'")) {
        throw new Exception("Error setting timezone: " . $conn->error);
    }

    return $conn;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

function getCategoryList($conn) {
    $categories = [];
    $query = "SELECT category_id, category_name FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

function generateCategoryDropdown($categories) {
    $html = '<div class="dropdown-content">';
    foreach ($categories as $category) {
        $html .= sprintf(
            '<a href="category.php?id=%d">%s</a>',
            $category['category_id'],
            htmlspecialchars($category['category_name'])
        );
    }
    $html .= '</div>';
    return $html;
}

function getSearchableCategories($conn) {
    $categories = getCategoryList($conn);
    return array_column($categories, 'category_name');
}