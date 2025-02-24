<?php
session_start();
require_once 'dbconnect.php';

// Check if service provider is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'service_provider') {
    header('Location: login.php');
    exit();
}

// Get provider details
$provider_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT provider_id, user_id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$provider_result = $stmt->get_result();
$provider = $provider_result->fetch_assoc();

if (!$provider) {
    die("Error: Provider profile not found");
}

$provider_id_from_table = $provider['provider_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent form resubmission
    if (!isset($_SESSION['last_submit_time']) || time() - $_SESSION['last_submit_time'] > 1) {
        $_SESSION['last_submit_time'] = time();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Add new sub-service
                $service_id = $_POST['service_id'];
                $sub_service_name = $_POST['sub_service_name'];
                $price = $_POST['price'];
                $description = $_POST['description'];
                
                // Handle file upload
                $images = '';
                if(isset($_FILES['images']) && $_FILES['images']['error'] === 0) {
                    $target_dir = "uploads/";
                    $target_file = $target_dir . basename($_FILES["images"]["name"]);
                    if (move_uploaded_file($_FILES["images"]["tmp_name"], $target_file)) {
                        $images = $target_file;
                    }
                }

                $stmt = $conn->prepare("
                    INSERT INTO tbl_sub_services 
                    (service_id, sub_service_name, price, description, images) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issds", $service_id, $sub_service_name, $price, $description, $images);
                
if ($stmt->execute()) {
    error_log("Sub-service added successfully: " . $sub_service_name);
    $_SESSION['success_msg'] = "Sub-service added successfully!";
} else {
    error_log("Error adding sub-service: " . $conn->error);
    $_SESSION['error_msg'] = "Error adding sub-service: " . $conn->error;
}

                
                header('Location: subservice-management.php');
                exit();
            } elseif ($_POST['action'] === 'edit') {
                // Edit existing sub-service
                $sub_service_id = $_POST['sub_service_id'];
                $sub_service_name = $_POST['sub_service_name'];
                $price = $_POST['price'];
                $description = $_POST['description'];
                
                // Handle file upload for edit
                $images = $_POST['current_image']; // Keep existing image if no new one uploaded
                if(isset($_FILES['images']) && $_FILES['images']['error'] === 0) {
                    $target_dir = "uploads/";
                    $target_file = $target_dir . basename($_FILES["images"]["name"]);
                    if (move_uploaded_file($_FILES["images"]["tmp_name"], $target_file)) {
                        $images = $target_file;
                    }
                }

                $stmt = $conn->prepare("
                    UPDATE tbl_sub_services 
                    SET sub_service_name = ?, price = ?, description = ?, images = ?
                    WHERE sub_service_id = ?
                ");
                $stmt->bind_param("sdssi", $sub_service_name, $price, $description, $images, $sub_service_id);
                
if ($stmt->execute()) {
    error_log("Sub-service updated successfully: ID " . $sub_service_id);
    $_SESSION['success_msg'] = "Sub-service updated successfully!";
} else {
    error_log("Error updating sub-service: " . $conn->error);
    $_SESSION['error_msg'] = "Error updating sub-service: " . $conn->error;
}

                
                header('Location: subservice-management.php');
                exit();
            }
        }
    }
}

// Handle deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $sub_service_id = $_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM tbl_sub_services WHERE sub_service_id = ?");
    $stmt->bind_param("i", $sub_service_id);
    
if ($stmt->execute()) {
    error_log("Sub-service deleted successfully: ID " . $sub_service_id);
    $_SESSION['success_msg'] = "Sub-service deleted successfully!";
} else {
    error_log("Error deleting sub-service: " . $conn->error);
    $_SESSION['error_msg'] = "Error deleting sub-service: " . $conn->error;
}

    
    header('Location: subservice-management.php');
    exit();
}

// Handle AJAX request for getting sub-service details
if (isset($_GET['get_subservice']) && isset($_GET['id'])) {
    $sub_service_id = $_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT ss.* 
        FROM tbl_sub_services ss
        JOIN tbl_services s ON ss.service_id = s.service_id
        JOIN service_providers sp ON s.provider_id = sp.provider_id
        WHERE ss.sub_service_id = ? AND sp.user_id = ?
    ");
    $stmt->bind_param("ii", $sub_service_id, $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
if ($sub_service = $result->fetch_assoc()) {
    error_log("Fetched sub-service details for ID: " . $sub_service_id);
    header('Content-Type: application/json');
    echo json_encode($sub_service);
    exit();
} else {
    error_log("Failed to fetch sub-service details for ID: " . $sub_service_id);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sub-service not found']);
    exit();
}

}

// Display messages
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Get sub-services for the provider
$stmt = $conn->prepare("
    SELECT ss.*, s.service_name 
    FROM tbl_sub_services ss
    JOIN tbl_services s ON ss.service_id = s.service_id
    JOIN service_providers sp ON s.provider_id = sp.provider_id
    WHERE sp.user_id = ?
    ORDER BY ss.sub_service_name
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$sub_services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get services for dropdown
$stmt = $conn->prepare("
    SELECT s.service_id, s.service_name 
    FROM tbl_services s
    JOIN service_providers sp ON s.provider_id = sp.provider_id
    WHERE sp.user_id = ?
    ORDER BY s.service_name
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sub-Services - ServiceHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: rgb(104, 35, 3);
            color: white;
            padding: 20px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .company-logo {
            width: 260px;
            height: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 15px;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            transition: 0.3s;
        }

        .sidebar-menu a:hover {
            background-color: rgb(171, 46, 8);
            border-radius: 5px;
        }

        .sidebar-menu i {
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .services-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .add-service-btn {
            background-color: #ff5722;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 16px;
        }

        .add-service-btn i {
            margin-right: 10px;
        }

        .services-list {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .service-table {
            width: 100%;
            border-collapse: collapse;
        }

        .service-table th, .service-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .service-table th {
            background-color: #f8f8f8;
            color: #333;
        }

        .action-btn {
            background-color: transparent;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            color: #666;
            transition: 0.3s;
        }

        .edit-btn:hover {
            color: #2196F3;
        }

        .delete-btn:hover {
            color: #F44336;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 60%;
            max-width: 700px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #555;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        textarea.form-control {
            min-height: 100px;
        }

        .submit-btn {
            background-color: #ff5722;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group1 {
            margin-bottom: 20px;
            width: 250px;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .modal-content {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Bookings</a></li>
                <li><a href="service-management.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="sub-service-management.php"><i class="fas fa-tools"></i> Sub-Services</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>Manage Your Sub-Services</h1>
                <button class="add-service-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Sub-Service
                </button>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success">
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Services Dropdown for Filtering -->
            <div class="form-group1">
                <label for="filter_service">Filter by Service:</label>
                <select name="filter_service" id="filter_service" class="form-control" onchange="filterSubServices()">
                    <option value="">-- All Services --</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['service_id']; ?>">
                            <?php echo htmlspecialchars($service['service_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="services-container">
                <div class="services-list">
                    <?php if (empty($sub_services)): ?>
                        <p>You haven't added any sub-services yet. Click the "Add New Sub-Service" button to get started.</p>
                    <?php else: ?>
                        <table class="service-table">
                            <thead>
                                <tr>
                                    <th>Sub-Service Name</th>
                                    <th>Parent Service</th>
                                    <th>Price</th>
                                    <th>Description</th>
                                    <th>Images</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sub_services as $sub_service): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub_service['sub_service_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub_service['service_name']); ?></td>
                                        <td>₹<?php echo number_format($sub_service['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($sub_service['description']); ?></td>
                                        <td>
                                            <?php if (!empty($sub_service['images'])): ?>
                                                <img src="<?php echo htmlspecialchars($sub_service['images']); ?>" alt="Sub-Service Image" style="width: 50px; height: 50px;">
                                            <?php else: ?>
                                                No Image
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="action-btn edit-btn" onclick="openEditModal(<?php echo $sub_service['sub_service_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $sub_service['sub_service_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sub-Service Modal -->
    <div id="addServiceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addServiceModal')">&times;</span>
            <h2>Add New Sub-Service</h2>
            <form action="subservice-management.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="service_id">Parent Service</label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">-- Select Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sub_service_name">Sub-Service Name</label>
                    <input type="text" name="sub_service_name" id="sub_service_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (₹)</label>
                    <input type="number" name="price" id="price" step="0.01" min="0" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="images">Images</label>
                    <input type="file" name="images" id="images" class="form-control" accept="image/*">
                </div>
                
                <button type="submit" class="submit-btn">Add Sub-Service</button>
            </form>
        </div>
    </div>

    <!-- Edit Sub-Service Modal -->
    <div id="editServiceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editServiceModal')">&times;</span>
            <h2>Edit Sub-Service</h2>
            <form action="subservice-management.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="sub_service_id" id="edit_sub_service_id">
                <input type="hidden" name="current_image" id="current_image">
                
                <div class="form-group">
                    <label for="edit_sub_service_name">Sub-Service Name</label>
                    <input type="text" name="sub_service_name" id="edit_sub_service_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_price">Price (₹)</label>
                    <input type="number" name="price" id="edit_price" step="0.01" min="0" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea name="description" id="edit_description" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_images">Images</label>
                    <input type="file" name="images" id="edit_images" class="form-control" accept="image/*">
                    <small>Leave empty to keep current image</small>
                </div>
                
                <button type="submit" class="submit-btn">Update Sub-Service</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addServiceModal').style.display = 'block';
        }
        
        function openEditModal(subServiceId) {
            // Fetch sub-service details via AJAX
            fetch(`subservice-management.php?get_subservice=1&id=${subServiceId}`)
                .then(response => response.json())
                .then(subService => {
                    document.getElementById('edit_sub_service_id').value = subService.sub_service_id;
                    document.getElementById('edit_sub_service_name').value = subService.sub_service_name;
                    document.getElementById('edit_price').value = subService.price;
                    document.getElementById('edit_description').value = subService.description;
                    document.getElementById('current_image').value = subService.images || '';
                    document.getElementById('editServiceModal').style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function confirmDelete(subServiceId) {
            if (confirm('Are you sure you want to delete this sub-service? This action cannot be undone.')) {
                window.location.href = `subservice-management.php?action=delete&id=${subServiceId}`;
            }
        }

        function filterSubServices() {
            const serviceId = document.getElementById('filter_service').value;
            window.location.href = `subservice-management.php?filter_service=${serviceId}`;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
