<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'dbconnect.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = $_POST['category_name'];
        $category_desc = $_POST['category_description'];
        
        // Handle image upload
        if ($_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/categories/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imageName = basename($_FILES['category_image']['name']);
            $imagePath = $uploadDir . $imageName;
            move_uploaded_file($_FILES['category_image']['tmp_name'], $imagePath);
        } else {
            $imagePath = null;
        }
        
        $stmt = $conn->prepare("INSERT INTO tbl_categories (category_name, description, image_path) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $category_name, $category_desc, $imagePath);
        $stmt->execute();
    }
    
    // Delete Category
    if (isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];
        $stmt = $conn->prepare("UPDATE tbl_categories SET is_active = FALSE WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF'] . "?active=categories");
        exit();
    }

    // Edit Category
    if (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $category_name = $_POST['category_name'];
        $category_desc = $_POST['category_description'];
        
        // Handle image upload
        if ($_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/categories/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imageName = basename($_FILES['category_image']['name']);
            $imagePath = $uploadDir . $imageName;
            move_uploaded_file($_FILES['category_image']['tmp_name'], $imagePath);
        } else {
            // If no new image is uploaded, keep the existing image path
            $stmt = $conn->prepare("SELECT image_path FROM tbl_categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $imagePath = $row['image_path'];
        }
        
        $stmt = $conn->prepare("UPDATE tbl_categories SET category_name = ?, description = ?, image_path = ? WHERE category_id = ?");
        $stmt->bind_param("sssi", $category_name, $category_desc, $imagePath, $category_id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF'] . "?active=categories");
        exit();
    }
}

// Fetch categories and services
$categories = $conn->query("SELECT * FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name");
$categoriesForFilter = $conn->query("SELECT * FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name");

// Modify services query to handle category filter
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$servicesQuery = "SELECT s.*, c.category_name 
                 FROM tbl_services s 
                 JOIN tbl_categories c ON s.category_id = c.category_id 
                 WHERE s.is_active = TRUE AND c.is_active = TRUE";
if ($categoryFilter > 0) {
    $servicesQuery .= " AND s.category_id = " . $categoryFilter;
}
$servicesQuery .= " ORDER BY c.category_name, s.service_name";
$services = $conn->query($servicesQuery);

// Modify the sub-services query to remove the is_active condition
$subServicesQuery = "SELECT ss.*, s.service_name 
                    FROM tbl_sub_services ss
                    JOIN tbl_services s ON ss.service_id = s.service_id
                    WHERE s.is_active = TRUE
                    ORDER BY s.service_name, ss.sub_service_name";
$subServices = $conn->query($subServicesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - ServiceHive</title>
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

        .sidebar {
            width: 250px;
            background-color: rgb(104, 35, 3);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .table-section {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        
        .table-section.active {
            display: block;
            opacity: 1;
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(104, 35, 3);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }

        .btn-primary {
            background-color: rgb(104, 35, 3);
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .logo-container {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .company-logo {
            width: 100%;
            max-width: 240px;
            height: auto;
            margin: 0 auto;
            display: block;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: white;
            margin-top: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
        }

        .menu-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            user-select: none;
        }

        .menu-item:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .menu-item.active {
            background-color: rgba(255,255,255,0.2);
            pointer-events: none;
        }

        .logout-btn {
            margin-top: auto;
            background-color: rgb(133, 36, 3);
        }

        .filter-section {
            margin-bottom: 20px;
        }
        
        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 200px;
        }

        .btn-edit {
            background-color: #ffc107;
            color: #000;
            margin-right: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }
        img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 4px;
}
/* Add these CSS rules to the existing style section */
.table-section {
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.table-section.active {
    display: block;
    opacity: 1;
}

.menu-item {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    user-select: none;
}

.menu-item:hover {
    background-color: rgba(255,255,255,0.1);
}

.menu-item.active {
    background-color: rgba(255,255,255,0.2);
    pointer-events: none;
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
        <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>
            <div class="sidebar-menu">
                <a href="admin.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="menu-item" data-table="categories" onclick="showTable('categories', event)">
                    <i class="fas fa-folder"></i>
                    <span>Categories</span>
                </a>
                <a href="#" class="menu-item" data-table="services" onclick="showTable('services', event)">
                    <i class="fas fa-tools"></i>
                    <span>Services</span>
                </a>
                <a href="#" class="menu-item" data-table="subServices" onclick="showTable('subServices', event)">
                    <i class="fas fa-list"></i>
                    <span>Sub-Services</span>
                </a>
                <a href="admin.php" class="menu-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Back</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Categories Section -->
            <div id="categoriesTable" class="section table-section <?php echo (isset($_GET['active']) && $_GET['active'] === 'categories') ? 'active' : ''; ?>">
                <h2>Categories</h2>
                <button class="btn btn-primary" onclick="openModal('categoryModal')">
                    <i class="fas fa-plus"></i> Add Category
                </button>
                <table>
    <thead>
        <tr>
            <th>Category Name</th>
            <th>Description</th>
            <th>Image</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($category = $categories->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
            <td><?php echo htmlspecialchars($category['description']); ?></td>
            <td>
                <?php if ($category['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($category['image_path']); ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>" style="width: 50px; height: 50px;">
                <?php else: ?>
                    No Image
                <?php endif; ?>
            </td>
            <td class="action-buttons">
                <button class="btn btn-edit" onclick="editCategory(<?php 
                    echo htmlspecialchars(json_encode($category)); 
                ?>)">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                    <button type="submit" name="delete_category" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
            </div>

            <!-- Services Section (View Only) -->
            <div id="servicesTable" class="section table-section <?php echo (isset($_GET['active']) && $_GET['active'] === 'services') ? 'active' : ''; ?>">
                <h2>Services</h2>
                <div class="filter-section">
                    <select onchange="filterServices(this.value)">
                        <option value="0">All Categories</option>
                        <?php while ($category = $categoriesForFilter->fetch_assoc()): ?>
                        <option value="<?php echo $category['category_id']; ?>" 
                                <?php echo ($categoryFilter == $category['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Service Name</th>
                            <th>Price</th>
                            <th>Add to Cart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($service = $services->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td>₹<?php echo number_format($service['price'], 2); ?></td>
                            <td>
                                <i class="fas <?php echo $service['add_to_cart_option'] ? 'fa-check text-success' : 'fa-times text-danger'; ?>"></i>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sub-Services Section (View Only) -->
            <div id="subServicesTable" class="section table-section">
                <h2>Sub-Services</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Sub-Service Name</th>
                            <th>Description</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($subService = $subServices->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subService['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($subService['sub_service_name']); ?></td>
                            <td><?php echo htmlspecialchars($subService['description']); ?></td>
                            <td>₹<?php echo number_format($subService['price'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <!-- Add Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Add Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" id="category_name" name="category_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="category_description">Description</label>
                <textarea id="category_description" name="category_description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label for="category_image">Category Image</label>
                <input type="file" id="category_image" name="category_image" class="form-control" accept="image/*">
            </div>
            <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
        </form>
    </div>
</div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="form-group">
                <label for="edit_category_name">Category Name</label>
                <input type="text" id="edit_category_name" name="category_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit_category_description">Description</label>
                <textarea id="edit_category_description" name="category_description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label for="edit_category_image">Category Image</label>
                <input type="file" id="edit_category_image" name="category_image" class="form-control" accept="image/*">
            </div>
            <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
        </form>
    </div>
</div>
    <script>
        // Toggle table visibility
        function showTable(tableType, event) {
            // Prevent default link behavior
            if (event) {
                event.preventDefault();
            }
            
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Find and add active class to the correct menu item
            document.querySelector(`.menu-item[data-table="${tableType}"]`).classList.add('active');
            
            // Hide all tables
            document.querySelectorAll('.table-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected table
            document.getElementById(`${tableType}Table`).classList.add('active');
            
            // Update URL without page reload
            const newUrl = `${window.location.pathname}?active=${tableType}`;
            window.history.pushState({ active: tableType }, '', newUrl);
        }

        // Filter services by category
        function filterServices(categoryId) {
            window.location.href = `${window.location.pathname}?active=services&category=${categoryId}`;
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = "block";
        }

        // Close modal when clicking the close button or outside
        document.querySelectorAll('.modal .close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = "none";
            }
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }

        // Edit category function
        function editCategory(category) {
    console.log("Edit category function called");
    console.log("Category data:", category);

    // Populate the form fields with the category data
    document.getElementById('edit_category_id').value = category.category_id;
    document.getElementById('edit_category_name').value = category.category_name;
    document.getElementById('edit_category_description').value = category.description;

    // Open the edit modal
    document.getElementById('editCategoryModal').style.display = 'block';
}      // Show categories table by default
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const active = urlParams.get('active') || 'categories';
            showTable(active);
        }
    </script>
</body>
</html>