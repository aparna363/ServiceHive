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

// Handle service deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $service_id = $_GET['id'];
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM tbl_services WHERE service_id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    
    // Verify the service belongs to this provider before deleting
    $stmt = $conn->prepare("
        SELECT s.service_id 
        FROM tbl_services s
        JOIN service_providers sp ON s.provider_id = sp.provider_id
        WHERE s.service_id = ? AND sp.user_id = ?
    ");
    $stmt->bind_param("ii", $service_id, $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error_msg = "You don't have permission to delete this service.";
    } else {
        // Delete the service
        $stmt = $conn->prepare("DELETE FROM tbl_services WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        
        if ($stmt->execute()) {
            // Delete associated image if it exists
            if (!empty($service['image_path']) && file_exists($service['image_path'])) {
                unlink($service['image_path']);
            }
            $success_msg = "Service deleted successfully!";
        } else {
            $error_msg = "Error deleting service: " . $conn->error;
        }
    }
}

// Process form submission for adding/editing service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add new service
            $category_id = $_POST['category_id'];
            $service_name = $_POST['service_name'];
            $active = isset($_POST['is_active']) ? 1 : 0;
            $cart_option = isset($_POST['add_to_cart_option']) ? 1 : 0;

            // Handle image upload
            $image_path = '';
            if(isset($_FILES['service_image']) && $_FILES['service_image']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['service_image']['name'];
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if(in_array($file_ext, $allowed)) {
                    // Create uploads directory if it doesn't exist
                    $upload_dir = 'uploads/services/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = uniqid('service_', true) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if(move_uploaded_file($_FILES['service_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                    } else {
                        $error_msg = "Error uploading file. Please check directory permissions.";
                    }
                } else {
                    $error_msg = "Invalid file type. Allowed types: jpg, jpeg, png, gif";
                }
            }

            // Insert service with image path
            $stmt = $conn->prepare("
                INSERT INTO tbl_services 
                (category_id, provider_id, service_name, add_to_cart_option, is_active, image_path) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisiis", 
                $category_id, 
                $provider_id_from_table, 
                $service_name, 
                $cart_option, 
                $active, 
                $image_path
            );
            
            if ($stmt->execute()) {
                $success_msg = "Service added successfully!";
            } else {
                $error_msg = "Error adding service: " . $conn->error;
            }
        } elseif ($_POST['action'] === 'edit') {
            // Edit existing service
            $service_id = $_POST['service_id'];
            $category_id = $_POST['category_id'];
            $service_name = $_POST['service_name'];
            $active = isset($_POST['is_active']) ? 1 : 0;
            $cart_option = isset($_POST['add_to_cart_option']) ? 1 : 0;

            // Handle image upload for edit
            $image_path = $_POST['existing_image'] ?? ''; // Keep existing image by default
            if(isset($_FILES['service_image']) && $_FILES['service_image']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['service_image']['name'];
                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if(in_array($file_ext, $allowed)) {
                    $upload_dir = 'uploads/services/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = uniqid('service_', true) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if(move_uploaded_file($_FILES['service_image']['tmp_name'], $upload_path)) {
                        // Delete old image if exists
                        if(!empty($_POST['existing_image']) && file_exists($_POST['existing_image'])) {
                            unlink($_POST['existing_image']);
                        }
                        $image_path = $upload_path;
                    }
                }
            }
            
            // Update service
            $stmt = $conn->prepare("
                UPDATE tbl_services 
                SET category_id = ?, service_name = ?, add_to_cart_option = ?, is_active = ?,
                    image_path = ?
                WHERE service_id = ?
            ");
            $stmt->bind_param("isiisi", 
                $category_id, 
                $service_name, 
                $cart_option, 
                $active, 
                $image_path, 
                $service_id
            );
            
            if ($stmt->execute()) {
                $success_msg = "Service updated successfully!";
            } else {
                $error_msg = "Error updating service: " . $conn->error;
            }
        }
    }
}

// Get service provider's services
$stmt = $conn->prepare("
    SELECT s.*, c.category_name 
    FROM tbl_services s
    JOIN tbl_categories c ON s.category_id = c.category_id
    JOIN service_providers sp ON s.provider_id = sp.provider_id
    WHERE sp.user_id = ?
    ORDER BY s.service_name
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for dropdown
$stmt = $conn->prepare("SELECT category_id, category_name FROM tbl_categories WHERE is_active = 1 ORDER BY category_name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - ServiceHive</title>
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

        .status-active {
            color: #28a745;
        }

        .status-inactive {
            color: #dc3545;
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

        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .checkbox-container input {
            margin-right: 10px;
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
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .current-image {
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f8f9fa;
        }

        .current-image img {
            display: block;
            max-width: 200px;
            height: auto;
        }

        .service-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-calendar"></i> Bookings</a></li>
                <li><a href="service-management.php"><i class="fas fa-tools"></i> Services</a></li>
                <li><a href="subservice-management.php"><i class="fas fa-tools"></i>Sub Services</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Reviews</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>Manage Your Services</h1>
                <button class="add-service-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Service
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

            <div class="services-container">
    <div class="services-list">
        <?php if (empty($services)): ?>
            <p>You haven't added any services yet. Click the "Add New Service" button to get started.</p>
        <?php else: ?>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <?php if (!empty($service['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($service['image_path']); ?>" alt="Service image" class="service-image">
                                <?php else: ?>
                                    <img src="images/default-service.png" alt="Default service image" class="service-image">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($service['category_name']); ?></td>
                            <td>
                                <?php if ($service['is_active']): ?>
                                    <span class="status-active"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-inactive"><i class="fas fa-times-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="action-btn edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $service['service_id']; ?>)">
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

<!-- Add Service Modal -->
<div id="addServiceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addServiceModal')">&times;</span>
        <h2>Add New Service</h2>
        <form action="service-management.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="service_name">Service Name</label>
                <input type="text" name="service_name" id="service_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="service_image">Service Image</label>
                <input type="file" name="service_image" id="service_image" class="form-control" accept="image/*">
                <small class="form-text">Supported formats: JPG, JPEG, PNG, GIF</small>
            </div>
            
            <div class="checkbox-container">
                <input type="checkbox" name="add_to_cart_option" id="add_to_cart_option" checked>
                <label for="add_to_cart_option">Enable "Add to Cart" option</label>
            </div>
            
            <div class="checkbox-container">
                <input type="checkbox" name="is_active" id="is_active" checked>
                <label for="is_active">Active</label>
            </div>
            
            <button type="submit" class="submit-btn">Add Service</button>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="editServiceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editServiceModal')">&times;</span>
        <h2>Edit Service</h2>
        <form action="service-management.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="service_id" id="edit_service_id">
            
            <div class="form-group">
                <label for="edit_category_id">Category</label>
                <select name="category_id" id="edit_category_id" class="form-control" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_service_name">Service Name</label>
                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_service_image">Service Image</label>
                <input type="file" name="service_image" id="edit_service_image" class="form-control" accept="image/*">
                <input type="hidden" name="existing_image" id="edit_existing_image">
                <div class="current-image"></div>
                <small class="form-text">Supported formats: JPG, JPEG, PNG, GIF</small>
            </div>
            
            <div class="checkbox-container">
                <input type="checkbox" name="add_to_cart_option" id="edit_add_to_cart_option">
                <label for="edit_add_to_cart_option">Enable "Add to Cart" option</label>
            </div>
            
            <div class="checkbox-container">
                <input type="checkbox" name="is_active" id="edit_is_active">
                <label for="edit_is_active">Active</label>
            </div>
            
            <button type="submit" class="submit-btn">Update Service</button>
        </form>
    </div>
</div>
    <div id="addSubServiceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addSubServiceModal')">&times;</span>
        <h2>Add New Sub-Service</h2>
        <form action="service-management.php" method="post">
            <input type="hidden" name="action" value="add_sub_service">
            <input type="hidden" name="service_id" id="add_sub_service_id">
            
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
                <input type="file" name="images" id="images" class="form-control">
            </div>
            
            <button type="submit" class="submit-btn">Add Sub-Service</button>
        </form>
    </div>
</div>
<div id="editSubServiceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editSubServiceModal')">&times;</span>
        <h2>Edit Sub-Service</h2>
        <form action="service-management.php" method="post">
            <input type="hidden" name="action" value="edit_sub_service">
            <input type="hidden" name="sub_service_id" id="edit_sub_service_id">
            
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
                <input type="file" name="images" id="edit_images" class="form-control">
            </div>
            
            <button type="submit" class="submit-btn">Update Sub-Service</button>
        </form>
    </div>
</div>
    <script>
        // Open Add Service modal
        function openAddModal() {
            document.getElementById('addServiceModal').style.display = 'block';
        }
        
        // Open Edit Service modal
        function openEditModal(service) {
            // Populate form fields with service data
            document.getElementById('edit_service_id').value = service.service_id;
            document.getElementById('edit_category_id').value = service.category_id;
            document.getElementById('edit_service_name').value = service.service_name;
            document.getElementById('edit_add_to_cart_option').checked = service.add_to_cart_option == 1;
            document.getElementById('edit_is_active').checked = service.is_active == 1;
            
            // Show the modal
            document.getElementById('editServiceModal').style.display = 'block';
            document.getElementById('edit_existing_image').value = service.image_path;
    const currentImageDiv = document.querySelector('#editServiceModal .current-image');
    if (currentImageDiv) {
        if (service.image_path) {
            currentImageDiv.innerHTML = `<img src="${service.image_path}" alt="Current service image" style="max-width: 200px; margin-top: 10px;">`;
        } else {
            currentImageDiv.innerHTML = '<p>No image currently uploaded</p>';
        }
    }
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Delete confirmation
        function confirmDelete(serviceId) {
            if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
                window.location.href = 'service-management.php?action=delete&id=' + serviceId;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
        
    </script>
</body>
</html>