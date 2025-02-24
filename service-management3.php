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
        $stmt = $conn->prepare("INSERT INTO tbl_categories (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category_name, $category_desc);
        $stmt->execute();
        // No need for header redirects
    }
    
    // Add Service
    if (isset($_POST['add_service'])) {
        $category_id = $_POST['category_id'];
        $service_name = $_POST['service_name'];
        $price = $_POST['price'];
        $service_desc = $_POST['service_description'];
        $add_to_cart = isset($_POST['add_to_cart']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO tbl_services (category_id, service_name, price, description, add_to_cart_option) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsi", $category_id, $service_name, $price, $service_desc, $add_to_cart);
        $stmt->execute();
        // No need for header redirects
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
    
    // Delete Service
    if (isset($_POST['delete_service'])) {
        $service_id = $_POST['service_id'];
        $stmt = $conn->prepare("UPDATE tbl_services SET is_active = FALSE WHERE service_id = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF'] . "?active=services");
        exit();
    }

    // Edit Category
    if (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $category_name = $_POST['category_name'];
        $category_desc = $_POST['category_description'];
        $stmt = $conn->prepare("UPDATE tbl_categories SET category_name = ?, description = ? WHERE category_id = ?");
        $stmt->bind_param("ssi", $category_name, $category_desc, $category_id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF'] . "?active=categories");
        exit();
    }

    // Edit Service
    if (isset($_POST['edit_service'])) {
        $service_id = $_POST['service_id'];
        $category_id = $_POST['category_id'];
        $service_name = $_POST['service_name'];
        $price = $_POST['price'];
        $service_desc = $_POST['service_description'];
        $add_to_cart = isset($_POST['add_to_cart']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE tbl_services SET category_id = ?, service_name = ?, price = ?, description = ?, add_to_cart_option = ? WHERE service_id = ?");
        $stmt->bind_param("isdsii", $category_id, $service_name, $price, $service_desc, $add_to_cart, $service_id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF'] . "?active=services");
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
        }
        
        .table-section.active {
            display: block;
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
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .company-logo {
            width: 200px;
            height: auto;
            margin-bottom: 10px;
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
            background-color: rgba(255,255,255,0.2);
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
                <a href="#" class="menu-item" onclick="showTable('categories'); window.location.href = '<?php echo $_SERVER['PHP_SELF'] . '?active=categories'; ?>'">
                    <i class="fas fa-folder"></i>
                    <span>Categories</span>
                </a>
                <a href="#" class="menu-item" onclick="showTable('services'); window.location.href = '<?php echo $_SERVER['PHP_SELF'] . '?active=services'; ?>'">
                    <i class="fas fa-tools"></i>
                    <span>Services</span>
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

                <button class="btn btn-primary" onclick="openModal('categoryModal')">
                    <i class="fas fa-plus"></i> Add Category
                </button>
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description']); ?></td>
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

            <!-- Services Section -->
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
                <button class="btn btn-primary" onclick="openModal('serviceModal')">
                    <i class="fas fa-plus"></i> Add Service
                </button>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Service Name</th>
                            <th>Price</th>
                            <th>Add to Cart</th>
                            <th>Actions</th>
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
                            <td class="action-buttons">
                                <button class="btn btn-edit" onclick="editService(<?php 
                                    echo htmlspecialchars(json_encode($service)); 
                                ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                    <button type="submit" name="delete_service" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this service?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Category</h2>
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?active=categories'; ?>">
                <div class="form-group">
                    <label for="category_name">Category Name</label>
                    <input type="text" id="category_name" name="category_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="category_description">Description</label>
                    <textarea id="category_description" name="category_description" class="form-control"></textarea>
                </div>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </form>
        </div>
    </div>

    <!-- Service Modal -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Service</h2>
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?active=services'; ?>">
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php
                        $categories->data_seek(0);
                        while ($category = $categories->fetch_assoc()):
                        ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="service_name">Service Name</label>
                    <input type="text" id="service_name" name="service_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (₹)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="service_description">Description</label>
                    <textarea id="service_description" name="service_description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="add_to_cart" checked>
                        Allow Add to Cart
                    </label>
                </div>
                <button type="submit" name="add_service" class="btn btn-primary">Add Service</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Category</h2>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="form-group">
                    <label for="edit_category_name">Category Name</label>
                    <input type="text" id="edit_category_name" name="category_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_category_description">Description</label>
                    <textarea id="edit_category_description" name="category_description" class="form-control"></textarea>
                </div>
                <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
            </form>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Service</h2>
            <form method="POST">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div class="form-group">
                    <label for="edit_service_category">Category</label>
                    <select id="edit_service_category" name="category_id" class="form-control" required>
                        <?php
                        $categories->data_seek(0);
                        while ($category = $categories->fetch_assoc()):
                        ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_service_name">Service Name</label>
                    <input type="text" id="edit_service_name" name="service_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_service_price">Price (₹)</label>
                    <input type="number" id="edit_service_price" name="price" step="0.01" min="0" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_service_description">Description</label>
                    <textarea id="edit_service_description" name="service_description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="add_to_cart" id="edit_service_cart">
                        Allow Add to Cart
                    </label>
                </div>
                <button type="submit" name="edit_service" class="btn btn-primary">Update Service</button>
            </form>
        </div>
    </div>

    <script>
        // Toggle table visibility
        function showTable(tableType) {
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked menu item
            event.currentTarget.classList.add('active');
            
            // Hide all tables
            document.querySelectorAll('.table-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected table
            if (tableType === 'categories') {
                document.getElementById('categoriesTable').classList.add('active');
            } else if (tableType === 'services') {
                document.getElementById('servicesTable').classList.add('active');
            }
        }

        // Filter services by category
        function filterServices(categoryId) {
            window.location.href = `${window.location.pathname}?category=${categoryId}`;
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
            document.getElementById('edit_category_id').value = category.category_id;
            document.getElementById('edit_category_name').value = category.category_name;
            document.getElementById('edit_category_description').value = category.description;
            
            document.getElementById('editCategoryModal').style.display = 'block';
        }

        // Edit service function
        function editService(service) {
            document.getElementById('edit_service_id').value = service.service_id;
            document.getElementById('edit_service_category').value = service.category_id;
            document.getElementById('edit_service_name').value = service.service_name;
            document.getElementById('edit_service_price').value = service.price;
            document.getElementById('edit_service_description').value = service.description;
            document.getElementById('edit_service_cart').checked = service.add_to_cart_option === 1;
            
            document.getElementById('editServiceModal').style.display = 'block';
        }

        // Show categories table by default
       // Replace the existing window.onload function with this:
       window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const active = urlParams.get('active');
    
    if (active === 'services') {
        showTable('services');
    } else if (active === 'categories') {
        showTable('categories');
    } else {
        showTable('categories'); // default view
    }
}
    </script>
</body>
</html>