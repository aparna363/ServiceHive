<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
 header("Location: login.php");
 exit();
}

// Database connection
require_once 'dbconnect.php';

// Fetch categories with their subcategories and services
$query = "SELECT
 c.category_id, c.category_name, c.description AS cat_description,
 sc.subcategory_id, sc.subcategory_name, sc.description AS subcat_description,
 s.service_id, s.service_name, s.price, s.description AS service_description, s.add_to_cart_option
 FROM
 tbl_categories c
 LEFT JOIN
 tbl_subcategories sc ON c.category_id = sc.category_id
 LEFT JOIN
 tbl_services s ON sc.subcategory_id = s.subcategory_id
 ORDER BY
 c.category_name, sc.subcategory_name, s.service_name";

$result = $conn->query($query);

// Organize data into a nested structure
$categories = array();
if ($result) {
 while ($row = $result->fetch_assoc()) {
 $category_id = $row['category_id'];

 // Add category if not already in array
 if (!isset($categories[$category_id])) {
 $categories[$category_id] = array(
 'id' => $category_id,
 'name' => $row['category_name'],
 'description' => $row['cat_description'],
 'subcategories' => array()
 );
 }

 // If subcategory exists
 if ($row['subcategory_id']) {
 $subcategory_id = $row['subcategory_id'];

 // Add subcategory if not already in this category
 if (!isset($categories[$category_id]['subcategories'][$subcategory_id])) {
 $categories[$category_id]['subcategories'][$subcategory_id] = array(
 'id' => $subcategory_id,
 'name' => $row['subcategory_name'],
 'description' => $row['subcat_description'],
 'services' => array()
 );
 }

 // If service exists
 if ($row['service_id']) {
 $service_id = $row['service_id'];

 // Add service to subcategory
 $categories[$category_id]['subcategories'][$subcategory_id]['services'][$service_id] = array(
 'id' => $service_id,
 'name' => $row['service_name'],
 'price' => $row['price'],
 'description' => $row['service_description'],
 'add_to_cart_option' => $row['add_to_cart_option']
 );
 }
 }
 }
}

// Get all categories for dropdowns
$all_categories = $conn->query("SELECT * FROM tbl_categories ORDER BY category_name");
$all_subcategories = $conn->query("SELECT * FROM tbl_subcategories ORDER BY subcategory_name");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 if (isset($_POST['add_category'])) {
 $category_name = $_POST['category_name'];
 $description = $_POST['description'];
 $query = "INSERT INTO tbl_categories (category_name, description) VALUES (?, ?)";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("ss", $category_name, $description);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Category added successfully!";
 } else {
 $_SESSION['error'] = "Failed to add category.";
 }
 } elseif (isset($_POST['add_subcategory'])) {
 $category_id = $_POST['category_id'];
 $subcategory_name = $_POST['subcategory_name'];
 $description = $_POST['description'];
 $query = "INSERT INTO tbl_subcategories (category_id, subcategory_name, description) VALUES (?, ?, ?)";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("iss", $category_id, $subcategory_name, $description);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Subcategory added successfully!";
 } else {
 $_SESSION['error'] = "Failed to add subcategory.";
 }
 } elseif (isset($_POST['add_service'])) {
 $subcategory_id = $_POST['subcategory_id'];
 $service_name = $_POST['service_name'];
 $price = $_POST['price'];
 $description = $_POST['description'];
 $add_to_cart_option = isset($_POST['add_to_cart_option']) ? 1 : 0;
 $query = "INSERT INTO tbl_services (subcategory_id, service_name, price, description, add_to_cart_option)
 VALUES (?, ?, ?, ?, ?)";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("isdsi", $subcategory_id, $service_name, $price, $description, $add_to_cart_option);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Service added successfully!";
 } else {
 $_SESSION['error'] = "Failed to add service.";
 }
 } elseif (isset($_POST['delete_category'])) {
 $category_id = $_POST['category_id'];
 $query = "DELETE FROM tbl_categories WHERE category_id = ?";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $category_id);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Category deleted successfully!";
 } else {
 $_SESSION['error'] = "Failed to delete category.";
 }
 } elseif (isset($_POST['delete_subcategory'])) {
 $subcategory_id = $_POST['subcategory_id'];
 $query = "DELETE FROM tbl_subcategories WHERE subcategory_id = ?";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $subcategory_id);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Subcategory deleted successfully!";
 } else {
 $_SESSION['error'] = "Failed to delete subcategory.";
 }
 } elseif (isset($_POST['delete_service'])) {
 $service_id = $_POST['service_id'];
 $query = "DELETE FROM tbl_services WHERE service_id = ?";
 $stmt = $conn->prepare($query);
 $stmt->bind_param("i", $service_id);
 if ($stmt->execute()) {
 $_SESSION['message'] = "Service deleted successfully!";
 } else {
 $_SESSION['error'] = "Failed to delete service.";
 }
 }

 header("Location: service-management.php");
 exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Service Management - Admin</title>
 <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
 <style>
 * {
 margin: 0;
 padding: 0;
 box-sizing: border-box;
 font-family: 'Arial', sans-serif;
 }

 body {
 background-color: #f4f6f9;
 min-height: 100vh;
 }

 .container {
 display: flex;
 min-height: 100vh;
 }

 /* Top Header Styles */
 .top-header {
 position: fixed;
 top: 0;
 right: 0;
 width: calc(100% - 250px);
 height: 70px;
 background-color: white;
 box-shadow: 0 2px 4px rgba(0,0,0,0.1);
 display: flex;
 justify-content: flex-end;
 align-items: center;
 padding: 0 30px;
 z-index: 100;
 }

 .profile-container {
 display: flex;
 align-items: center;
 gap: 15px;
 cursor: pointer;
 padding: 8px 15px;
 border-radius: 25px;
 transition: background-color 0.3s;
 }

 .profile-container:hover {
 background-color: #f5f5f5;
 }

 .profile-info {
 text-align: right;
 }

 .profile-name {
 font-weight: 600;
 color: #333;
 font-size: 14px;
 }

 .profile-role {
 color: #666;
 font-size: 12px;
 }

 .profile-image {
 width: 40px;
 height: 40px;
 border-radius: 50%;
 object-fit: cover;
 border: 2px solid rgb(104, 35, 3);
 }

 /* Sidebar Styles */
 .sidebar {
 width: 250px;
 background-color: rgb(104, 35, 3);
 color: white;
 position: fixed;
 height: 100vh;
 left: 0;
 top: 0;
 }

 .logo-container {
 padding: 20px;
 text-align: center;
 border-bottom: 1px solid rgba(255,255,255,0.1);
 }

 .company-logo {
 width: 200px;
 height: auto;
 margin-bottom: 10px;
 }

 .company-name {
 font-size: 20px;
 font-weight: bold;
 color: white;
 margin-bottom: 5px;
 }

 .sidebar-menu {
 padding: 20px 0;
 height: calc(100vh - 200px);
 display: flex;
 flex-direction: column;
 }

 .menu-item {
 padding: 15px 20px;
 display: flex;
 align-items: center;
 color: white;
 text-decoration: none;
 transition: 0.3s;
 }

 .menu-item:hover {
 background-color: rgba(255,255,255,0.1);
 }

 .menu-item i {
 margin-right: 12px;
 width: 20px;
 text-align: center;
 }

 .menu-item.active {
 background-color: rgba(255,255,255,0.1);
 border-left: 4px solid #4CAF50;
 }

 .logout-btn {
 margin-top: auto;
 background-color: rgb(133, 36, 3);
 }

 /* Main Content Styles */
 .main-content {
 flex: 1;
 margin-left: 250px;
 padding: 100px 30px 30px 30px;
 }

 /* Stats Grid */
 .stats-grid {
 display: grid;
 grid-template-columns: repeat(3, 1fr);
 gap: 40px;
 margin: 40px 0;
 padding: 0 20px;
 }

 .stat-card {
 background: white;
 padding: 30px;
 border-radius: 15px;
 box-shadow: 0 4px 15px rgba(0,0,0,0.1);
 position: relative;
 overflow: hidden;
 transition: transform 0.3s ease;
 }

 .stat-card:hover {
 transform: translateY(-5px);
 }

 .stat-value {
 font-size: 32px;
 font-weight: bold;
 color: #333;
 margin-bottom: 12px;
 position: relative;
 z-index: 1;
 }

 .stat-label {
 color: #666;
 font-size: 16px;
 position: relative;
 z-index: 1;
 font-weight: 500;
 }

 .stat-icon {
 position: absolute;
 right: 25px;
 top: 50%;
 transform: translateY(-50%);
 font-size: 54px;
 color: rgba(104, 35, 3, 0.1);
 }

 /* Action Buttons */
 .action-buttons {
 display: flex;
 justify-content: flex-end;
 margin-bottom: 20px;
 }

 .action-buttons .btn {
 margin-left: 10px;
 }

 /* Category Management Section */
 .category-container {
 background: white;
 padding: 25px;
 border-radius: 10px;
 margin-bottom: 30px;
 box-shadow: 0 2px 10px rgba(0,0,0,0.08);
 }

 .category-header {
 color: #333;
 margin-bottom: 20px;
 padding-bottom: 15px;
 border-bottom: 2px solid rgb(104, 35, 3);
 display: flex;
 justify-content: space-between;
 align-items: center;
 }

 .category-header h2 {
 display: flex;
 align-items: center;
 }

 .category-header h2 i {
 margin-right: 10px;
 color: rgb(104, 35, 3);
 }

 .category-table {
 width: 100%;
 border-collapse: collapse;
 margin-bottom: 20px;
 }

 .category-table th, .category-table td {
 padding: 15px;
 text-align: left;
 border-bottom: 1px solid #eee;
 }

 .category-table th {
 background-color: #f8f9fa;
 color: #333;
 font-weight: 600;
 }

 .category-table tr:hover {
 background-color: #f8f9fa;
 }

 /* Subcategory Styles */
 .subcategory-container {
 background: #f9f9f9;
 padding: 20px;
 border-radius: 8px;
 margin: 10px 0 15px 30px;
 border-left: 3px solid rgb(104, 35, 3);
 }

 .subcategory-header {
 display: flex;
 justify-content: space-between;
 align-items: center;
 padding-bottom: 10px;
 border-bottom: 1px solid #e0e0e0;
 margin-bottom: 15px;
 }

 .subcategory-header h3 {
 color: #444;
 font-size: 18px;
 display: flex;
 align-items: center;
 }

 .subcategory-header h3 i {
 margin-right: 8px;
 color: rgb(104, 35, 3);
 }

 /* Service Styles */
 .service-container {
 background: #fff;
 padding: 15px;
 border-radius: 6px;
 margin: 10px 0 10px 30px;
 border-left: 2px solid #4CAF50;
 box-shadow: 0 1px 3px rgba(0,0,0,0.05);
 }

 .service-header {
 display: flex;
 justify-content: space-between;
 align-items: center;
 padding-bottom: 8px;
 border-bottom: 1px solid #f0f0f0;
 margin-bottom: 10px;
 }

 .service-header h4 {
 color: #555;
 font-size: 16px;
 display: flex;
 align-items: center;
 }

 .service-header h4 i {
 margin-right: 8px;
 color: #4CAF50;
 }

 .service-details {
 display: grid;
 grid-template-columns: repeat(3, 1fr);
 gap: 10px;
 font-size: 14px;
 }

 .service-detail-item {
 padding: 5px 0;
 }

 .service-detail-label {
 font-weight: 600;
 color: #666;
 }

 /* Button Styles */
 .btn {
 padding: 8px 16px;
 border: none;
 border-radius: 5px;
 cursor: pointer;
 font-size: 14px;
 transition: 0.3s;
 margin-right: 5px;
 }

 .btn:hover {
 opacity: 0.9;
 }

 .btn-primary {
 background-color: #4CAF50;
 color: white;
 }

 .btn-danger {
 background-color: #f44336;
 color: white;
 }

 .btn-warning {
 background-color: #ff9800;
 color: white;
 }

 /* Modal Styles for Add Forms */
 .modal {
 display: none;
 position: fixed;
 z-index: 1000;
 left: 0;
 top: 0;
 width: 100%;
 height: 100%;
 overflow: auto;
 background-color: rgba(0,0,0,0.4);
 }

 .modal-content {
 background-color: #fefefe;
 margin: 10% auto;
 padding: 20px;
 border: 1px solid #888;
 width: 50%;
 border-radius: 8px;
 box-shadow: 0 4px 8px rgba(0,0,0,0.1);
 }

 .close {
 color: #aaa;
 float: right;
 font-size: 28px;
 font-weight: bold;
 cursor: pointer;
 }

 .close:hover {
 color: black;
 }

 .form-group {
 margin-bottom: 15px;
 }

 .form-group label {
 display: block;
 margin-bottom: 5px;
 font-weight: 600;
 }

 .form-group input[type="text"],
 .form-group input[type="number"],
 .form-group select,
 .form-group textarea {
 width: 100%;
 padding: 8px;
 border: 1px solid #ddd;
 border-radius: 4px;
 }

 .form-group textarea {
 height: 100px;
 }

 .form-actions {
 margin-top: 20px;
 text-align: right;
 }

 /* Messages */
 .message {
 padding: 15px;
 margin-bottom: 20px;
 border-radius: 5px;
 }

 .message-success {
 background-color: #d4edda;
 color: #155724;
 border: 1px solid #c3e6cb;
 }

 .message-error {
 background-color: #f8d7da;
 color: #721c24;
 border: 1px solid #f5c6cb;
 }

 /* Empty state */
 .empty-state {
 text-align: center;
 padding: 30px;
 color: #666;
 }

 .empty-state i {
 font-size: 60px;
 margin-bottom: 15px;
 color: #ddd;
 }

 /* Toggle */
 .toggle-container {
 display: flex;
 align-items: center;
 cursor: pointer;
 }

 .toggle-icon {
 margin-right: 5px;
 transition: transform 0.3s;
 }

 .toggle-icon.collapsed {
 transform: rotate(-90deg);
 }

 /* Badge */
 .badge {
 display: inline-block;
 padding: 3px 7px;
 font-size: 12px;
 font-weight: 600;
 border-radius: 50px;
 background-color: #e9ecef;
 color: #495057;
 }

 .badge-primary {
 background-color: #4CAF50;
 color: white;
 }
 </style>
</head>
<body>
 <div class="container">
 <!-- Sidebar (same as the dashboard) -->
 <div class="sidebar">
 <!-- Logo Section -->
 <div class="logo-container">
 <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
 </div>

 <div class="sidebar-menu">
 <a href="admin-dashboard.php" class="menu-item">
 <i class="fas fa-home"></i>
 <span>Dashboard</span>
 </a>
 <a href="service-management.php" class="menu-item active">
 <i class="fas fa-list"></i>
 <span>Service Management</span>
 </a>
 <a href="logout.php" class="menu-item logout-btn">
 <i class="fas fa-sign-out-alt"></i>
 <span>Logout</span>
 </a>
 </div>
 </div>

 <!-- Top Header (same as the dashboard) -->
 <div class="top-header">
 <div class="profile-container">
 <div class="profile-info">
 <div class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
 <div class="profile-role">Administrator</div>
 </div>
 <img src="./images/admin.png" alt="Profile" class="profile-image">
 </div>
 </div>

 <!-- Main Content -->
 <div class="main-content">
 <div class="category-container">
 <div class="category-header">
 <h2><i class="fas fa-list"></i> Service Management</h2>
 <div class="action-buttons">
 <button class="btn btn-primary" onclick="showModal('categoryModal')">
 <i class="fas fa-plus"></i> Add Category
 </button>
 <button class="btn btn-primary" onclick="showModal('subcategoryModal')">
 <i class="fas fa-plus"></i> Add Subcategory
 </button>
 <button class="btn btn-primary" onclick="showModal('serviceModal')">
 <i class="fas fa-plus"></i> Add Service
 </button>
 </div>
 </div>

 <?php if (isset($_SESSION['message'])): ?>
 <div class="message message-success">
 <?php
 echo $_SESSION['message'];
 unset($_SESSION['message']);
 ?>
 </div>
 <?php endif; ?>

 <?php if (isset($_SESSION['error'])): ?>
 <div class="message message-error">
 <?php
 echo $_SESSION['error'];
 unset($_SESSION['error']);
 ?>
 </div>
 <?php endif; ?>

 <!-- Display Categories, Subcategories, and Services in a nested hierarchy -->
 <?php if (count($categories) > 0): ?>
 <?php foreach ($categories as $category): ?>
 <div class="category-section">
 <div class="category-header">
 <div class="toggle-container" onclick="toggleCategory(<?php echo $category['id']; ?>)">
 <i id="category-icon-<?php echo $category['id']; ?>" class="fas fa-chevron-down toggle-icon"></i>
 <h3><?php echo htmlspecialchars($category['name']); ?></h3>
 </div>
 <div>
 <span class="badge badge-primary">
 <?php echo count($category['subcategories']); ?> Subcategories
 </span>
 <form method="POST" style="display:inline;">
 <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
 <button type="submit" name="delete_category" class="btn btn-danger"
 onclick="return confirm('Are you sure you want to delete this category and all its subcategories and services?')">
 <i class="fas fa-trash"></i> Delete
 </button>
 </form>
 </div>
 </div>

 <div id="category-content-<?php echo $category['id']; ?>" class="category-content">
 <p><strong>Description:</strong> <?php echo htmlspecialchars($category['description']); ?></p>

 <?php if (count($category['subcategories']) > 0): ?>
 <?php foreach ($category['subcategories'] as $subcategory): ?>
 <div class="subcategory-container">
 <div class="subcategory-header">
 <div class="toggle-container" onclick="toggleSubcategory(<?php echo $subcategory['id']; ?>)">
 <i id="subcategory-icon-<?php echo $subcategory['id']; ?>" class="fas fa-chevron-down toggle-icon"></i>
 <h3><i class="fas fa-list-alt"></i> <?php echo htmlspecialchars($subcategory['name']); ?></h3>
 </div>
 <div>
 <span class="badge badge-primary">
 <?php echo count($subcategory['services']); ?> Services
 </span>
 <form method="POST" style="display:inline;">
 <input type="hidden" name="subcategory_id" value="<?php echo $subcategory['id']; ?>">
 <button type="submit" name="delete_subcategory" class="btn btn-danger"
 onclick="return confirm('Are you sure you want to delete this subcategory and all its services?')">
 <i class="fas fa-trash"></i> Delete
 </button>
 </form>
 </div>
 </div>

 <div id="subcategory-content-<?php echo $subcategory['id']; ?>" class="subcategory-content">
 <p><strong>Description:</strong> <?php echo htmlspecialchars($subcategory['description']); ?></p>

 <?php if (count($subcategory['services']) > 0): ?>
 <?php foreach ($subcategory['services'] as $service): ?>
 <div class="service-container">
 <div class="service-header">
 <h4><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($service['name']); ?></h4>
 <div>
 <form method="POST" style="display:inline;">
 <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
 <button type="submit" name="delete_service" class="btn btn-danger"
 onclick="return confirm('Are you sure you want to delete this service?')">
 <i class="fas fa-trash"></i> Delete
 </button>
 </form>
 </div>
 </div>
 <div class="service-details">
 <div class="service-detail-item">
 <span class="service-detail-label">Price:</span>
 <span><?php echo '$' . number_format($service['price'], 2); ?></span>
 </div>
 <div class="service-detail-item">
 <span class="service-detail-label">Add to Cart:</span>
 <span><?php echo $service['add_to_cart_option'] ? 'Yes' : 'No'; ?></span>
 </div>
 <div class="service-detail-item">
 <span class="service-detail-label">Description:</span>
 <p><?php echo htmlspecialchars($service['description']); ?></p>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 <?php else: ?>
 <div class="empty-state">
 <i class="fas fa-info-circle"></i>
 <p>No services found in this subcategory.</p>
 </div>
 <?php endif; ?>
 </div>
 </div>
 <?php endforeach; ?>
 <?php else: ?>
 <div class="empty-state">
 <i class="fas fa-info-circle"></i>
 <p>No subcategories found in this category.</p>
 </div>
 <?php endif; ?>
 </div>
 </div>
 <?php endforeach; ?>
 <?php else: ?>
 <div class="empty-state">
 <i class="fas fa-info-circle"></i>
 <p>No categories found. Click "Add Category" to get started.</p>
 </div>
 <?php endif; ?>
 </div>
 </div>
 </div>

 <!-- Add Category Modal -->
 <div id="categoryModal" class="modal">
 <div class="modal-content">
 <span class="close" onclick="hideModal('categoryModal')">&times;</span>
 <h2>Add New Category</h2>
 <form method="POST" action="">
 <div class="form-group">
 <label for="category_name">Category Name</label>
 <input type="text" id="category_name" name="category_name" required>
 </div>
 <div class="form-group">
 <label for="description">Description</label>
 <textarea id="description" name="description"></textarea>
 </div>
 <div class="form-actions">
 <button type="button" class="btn" onclick="hideModal('categoryModal')">Cancel</button>
 <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
 </div>
 </form>
 </div>
 </div>
 <!-- Add Subcategory Modal -->
<div id="subcategoryModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('subcategoryModal')">&times;</span>
        <h2>Add New Subcategory</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a Category</option>
                    <?php while ($row = $all_categories->fetch_assoc()): ?>
                        <option value="<?php echo $row['category_id']; ?>"><?php echo htmlspecialchars($row['category_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subcategory_name">Subcategory Name</label>
                <input type="text" id="subcategory_name" name="subcategory_name" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="hideModal('subcategoryModal')">Cancel</button>
                <button type="submit" name="add_subcategory" class="btn btn-primary">Add Subcategory</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Service Modal -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('serviceModal')">&times;</span>
        <h2>Add New Service</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="subcategory_id">Subcategory</label>
                <select id="subcategory_id" name="subcategory_id" required>
                    <option value="">Select a Subcategory</option>
                    <?php while ($row = $all_subcategories->fetch_assoc()): ?>
                        <option value="<?php echo $row['subcategory_id']; ?>"><?php echo htmlspecialchars($row['subcategory_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="service_name">Service Name</label>
                <input type="text" id="service_name" name="service_name" required>
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="add_to_cart_option">
                    <input type="checkbox" id="add_to_cart_option" name="add_to_cart_option" value="1">
                    Add to Cart Option
                </label>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" onclick="hideModal('serviceModal')">Cancel</button>
                <button type="submit" name="add_service" class="btn btn-primary">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Modal and Toggle Functionality -->
<script>
    // Function to show a modal
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    // Function to hide a modal
    function hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Function to toggle category content
    function toggleCategory(categoryId) {
        const content = document.getElementById(`category-content-${categoryId}`);
        const icon = document.getElementById(`category-icon-${categoryId}`);
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.remove('collapsed');
        } else {
            content.style.display = 'none';
            icon.classList.add('collapsed');
        }
    }

    // Function to toggle subcategory content
    function toggleSubcategory(subcategoryId) {
        const content = document.getElementById(`subcategory-content-${subcategoryId}`);
        const icon = document.getElementById(`subcategory-icon-${subcategoryId}`);
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.remove('collapsed');
        } else {
            content.style.display = 'none';
            icon.classList.add('collapsed');
        }
    }

    // Close modals when clicking outside of them
    window.onclick = function(event) {
        const modals = document.getElementsByClassName('modal');
        for (let modal of modals) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }
</script>

</body>
</html>