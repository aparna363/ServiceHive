<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'dbconnect.php';

// Get counts for dashboard stats
$userCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'];
$providerCount = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'service_provider'")->fetch_assoc()['count'];
$bookingCount = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];

// Functions for database operations
function getServiceProviders($db) {
    $query = "SELECT u.id, u.username, u.email, u.status, sp.verified_status, sp.rating, sp.total_reviews,
              sp.business_name
              FROM users u 
              LEFT JOIN service_providers sp ON u.id = sp.user_id 
              WHERE u.role = 'service_provider'";
    return $db->query($query);
}

function getUsers($db) {
    $query = "SELECT id, username, email, role, status, is_active, mobile, city, state 
              FROM users 
              WHERE role != 'admin'";
    return $db->query($query);
}

function getBookings($db) {
    $query = "SELECT b.booking_id, b.booking_date, b.time_slot, b.status, b.total_price,
              b.priority, b.notes, b.payment_status,
              u.username as client_name, 
              sp.business_name as provider_name,
              s.service_name
              FROM bookings b 
              JOIN users u ON b.user_id = u.id 
              JOIN service_providers sp ON b.provider_id = sp.provider_id
              JOIN tbl_services s ON b.service_id = s.service_id
              ORDER BY b.booking_date DESC, b.time_slot DESC";
    return $db->query($query);
}

function approveServiceProvider($db, $id) {
    $db->begin_transaction();
    try {
        // Update user status
        $query1 = "UPDATE users SET status = 'approved' WHERE id = ? AND role = 'service_provider'";
        $stmt1 = $db->prepare($query1);
        $stmt1->bind_param("i", $id);
        $stmt1->execute();

        // Update service_provider verified_status
        $query2 = "UPDATE service_providers SET verified_status = TRUE WHERE user_id = ?";
        $stmt2 = $db->prepare($query2);
        $stmt2->bind_param("i", $id);
        $stmt2->execute();

        // Create notification
        $query3 = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (?, 'Account Approved', 'Your service provider account has been approved.', 'system')";
        $stmt3 = $db->prepare($query3);
        $stmt3->bind_param("i", $id);
        $stmt3->execute();

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

function rejectServiceProvider($db, $id) {
    $db->begin_transaction();
    try {
        // Update user status
        $query1 = "UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'service_provider'";
        $stmt1 = $db->prepare($query1);
        $stmt1->bind_param("i", $id);
        $stmt1->execute();

        // Update service_provider verified_status
        $query2 = "UPDATE service_providers SET verified_status = FALSE WHERE user_id = ?";
        $stmt2 = $db->prepare($query2);
        $stmt2->bind_param("i", $id);
        $stmt2->execute();

        // Create notification
        $query3 = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (?, 'Account Rejected', 'Your service provider application has been rejected.', 'system')";
        $stmt3 = $db->prepare($query3);
        $stmt3->bind_param("i", $id);
        $stmt3->execute();

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

function deleteUser($db, $id) {
    $query = "DELETE FROM users WHERE id = ? AND role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}



// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_provider'])) {
        if (approveServiceProvider($conn, $_POST['provider_id'])) {
            $_SESSION['message'] = "Service provider approved successfully";
        } else {
            $_SESSION['error'] = "Failed to approve service provider";
        }
    } elseif (isset($_POST['reject_provider'])) {
        if (rejectServiceProvider($conn, $_POST['provider_id'])) {
            $_SESSION['message'] = "Service provider rejected successfully";
        } else {
            $_SESSION['error'] = "Failed to reject service provider";
        }
    } elseif (isset($_POST['delete_user'])) {
        if (deleteUser($conn, $_POST['user_id'])) {
            $_SESSION['message'] = "User deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete user";
        }
    } elseif (isset($_POST['toggle_status'])) {
        $userId = $_POST['user_id'];
        $is_active = $_POST['action'] === 'activate' ? 1 : 0;
        
        $query = "UPDATE users SET is_active = ? WHERE id = ? AND role != 'admin'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $is_active, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User status updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update user status";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch data
$serviceProviders = getServiceProviders($conn);
$users = getUsers($conn);
$bookings = getBookings($conn);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ServiceHive</title>
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
            margin-top: 320px;
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

        /* Table Sections */
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(5, 7, 37);
            display: flex;
            align-items: center;
        }

        .section h2 i {
            margin-right: 10px;
            color: rgb(10, 14, 50);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
        }

        table tr:hover {
            background-color: #f8f9fa;
        }

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

        .btn-approve {
            background-color: #4CAF50;
            color: white;
        }

        .btn-reject {
            background-color: #f44336;
            color: white;
        }

        .btn-delete {
            background-color: #ff9800;
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn-activate {
            background-color: #4CAF50;
            color: white;
        }

        .btn-deactivate {
            background-color: #f44336;
            color: white;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Logo Section -->
            <div class="logo-container">
                <img src="images/logo2.png" alt="ServiceHive Logo" class="company-logo">
            </div>

            <div class="sidebar-menu">
                <a href="#dashboard" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#providers" class="menu-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Service Providers</span>
                </a>
                <a href="category-management.php" class="menu-item">
                    <i class="fas fa-cogs"></i>
                    <span>Service Management</span>
                </a>
                <a href="#bookings" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Bookings</span>
                </a>
                <a href="#users" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                
                <a href="logout.php" class="menu-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Top Header -->
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
            <!-- Stats Grid -->
            <div class="stats-grid" id="dashboard">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $userCount; ?></div>
                    <div class="stat-label">Total Users</div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $providerCount; ?></div>
                    <div class="stat-label">Service Providers</div>
                    <i class="fas fa-user-tie stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $bookingCount; ?></div>
                    <div class="stat-label">Total Bookings</div>
                    <i class="fas fa-calendar-check stat-icon"></i>
                </div>
            </div>


            <!-- Service Providers Section -->
            <div class="section" id="providers">
                <h2><i class="fas fa-user-tie"></i> Service Providers</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($serviceProviders && $serviceProviders->num_rows > 0): ?>
                            <?php while ($provider = $serviceProviders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($provider['id']); ?></td>
                                    <td><?php echo htmlspecialchars($provider['username']); ?></td>
                                    <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($provider['status'] ?? 'pending'); ?>">
                                            <?php echo ucfirst($provider['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                            <button type="submit" name="approve_provider" class="btn btn-approve">Approve</button>
                                            <button type="submit" name="reject_provider" class="btn btn-reject">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No service providers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bookings Section -->
            <div class="section" id="bookings">
                <h2><i class="fas fa-calendar-check"></i> Bookings</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings && $bookings->num_rows > 0): ?>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No bookings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Users Section -->
            <div class="section" id="users">
                <h2><i class="fas fa-users"></i> Users</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['role'] === 'service_provider' ? 'approved' : 'pending'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-approved' : 'status-rejected'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                    <form method="POST" style="display:inline;">
    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
    <input type="hidden" name="action" value="<?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?>">
    <button type="submit" name="toggle_status" class="btn <?php echo $user['is_active'] ? 'btn-reject' : 'btn-approve'; ?>">
        <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
    </button>
    <button type="submit" name="delete_user" class="btn btn-delete" 
            onclick="return confirm('Are you sure you want to delete this user?')">
        <i class="fas fa-trash"></i> Delete
    </button>
</form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Subservices Section -->
            <div class="section" id="subservices">
                <h2><i class="fas fa-list"></i> Subservices</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subservice Name</th>
                            <th>Main Service</th>
                            <th>Price</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($subservices && $subservices->num_rows > 0): ?>
                            <?php while ($subservice = $subservices->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subservice['sub_service_id']); ?></td>
                                    <td><?php echo htmlspecialchars($subservice['sub_service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subservice['main_service']); ?></td>
                                    <td>₹<?php echo htmlspecialchars($subservice['price']); ?></td>
                                    <td><?php echo htmlspecialchars($subservice['description']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($subservice['status']); ?>">
                                            <?php echo ucfirst($subservice['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No subservices found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add active class to current menu item
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.menu-item');
            const sections = document.querySelectorAll('.section');
            
            // Handle menu item clicks
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        e.preventDefault();
                        
                        // Remove active class from all items
                        menuItems.forEach(i => i.classList.remove('active'));
                        
                        // Add active class to clicked item
                        this.classList.add('active');
                        
                        // Scroll to section
                        const targetId = this.getAttribute('href').substring(1);
                        const targetSection = document.getElementById(targetId);
                        if (targetSection) {
                            targetSection.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });

            // Handle scroll to update active menu item
            window.addEventListener('scroll', function() {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if (pageYOffset >= sectionTop - 200) {
                        current = section.getAttribute('id');
                    }
                });

                menuItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === `#${current}`) {
                        item.classList.add('active');
                    }
                });
            });
        });

        // Confirm delete actions
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>