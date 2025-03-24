<?php
// Include database connection
require_once 'dbconnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=visits.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's visit bookings
$visits_query = "
    SELECT 
        vb.*,
        c.category_name,
        sp.business_name as provider_name
    FROM visit_bookings vb
    JOIN tbl_categories c ON vb.category_id = c.category_id
    JOIN service_providers sp ON vb.provider_id = sp.provider_id
    WHERE vb.user_id = ?
    ORDER BY vb.visit_date DESC, vb.visit_time DESC
";

$stmt = $conn->prepare($visits_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$visits_result = $stmt->get_result();

$visits = [];
while ($row = $visits_result->fetch_assoc()) {
    $visits[] = $row;
}

// Handle cancel visit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_visit') {
    $visit_id = $_POST['visit_id'];
    
    // Check if visit belongs to user
    $check_query = "SELECT * FROM visit_bookings WHERE visit_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $visit_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $visit = $result->fetch_assoc();
        
        // Only allow cancellation if status is 'scheduled'
        if ($visit['status'] === 'scheduled') {
            $update_query = "UPDATE visit_bookings SET status = 'cancelled' WHERE visit_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("i", $visit_id);
            
            if ($stmt->execute()) {
                header('Location: visits.php?msg=cancelled');
                exit;
            }
        }
    }
    
    // If we get here, something went wrong
    header('Location: visits.php?error=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Technical Visits</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .visits-list {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .visit-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .visit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .visit-id {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .visit-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-scheduled {
            background: #ebf8ff;
            color: #3182ce;
        }
        
        .status-completed {
            background: #f0fff4;
            color: #38a169;
        }
        
        .status-cancelled {
            background: #fff5f5;
            color: #e53e3e;
        }
        
        .visit-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #2d3748;
        }
        
        .visit-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .cancel-btn {
            background: #fff5f5;
            color: #e53e3e;
            border: 1px solid #e53e3e;
        }
        
        .cancel-btn:hover {
            background: #fed7d7;
        }
        
        .reschedule-btn {
            background: #ebf8ff;
            color: #3182ce;
            border: 1px solid #3182ce;
        }
        
        .reschedule-btn:hover {
            background: #bee3f8;
        }
        
        .empty-visits {
            text-align: center;
            padding: 40px 0;
            color: #718096;
        }
        
        .empty-visits i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e0;
        }
        
        .empty-visits p {
            margin-bottom: 20px;
        }
        
        .book-visit-btn {
            background: #7e3af2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .book-visit-btn:hover {
            background: #6c2bd9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #38a169;
            border-left: 4px solid #38a169;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #e53e3e;
            border-left: 4px solid #e53e3e;
        }
        
        @media (max-width: 768px) {
            .visit-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-calendar-check"></i> My Technical Visits</h1>
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
            <div class="alert alert-success">
                Visit has been successfully cancelled.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                An error occurred. Please try again.
            </div>
        <?php endif; ?>
        
        <div class="visits-list">
            <?php if (empty($visits)): ?>
                <div class="empty-visits">
                    <i class="fas fa-calendar-times"></i>
                    <p>You don't have any technical visits scheduled yet.</p>
                    <a href="index.php" class="book-visit-btn">Book a Visit</a>
                </div>
            <?php else: ?>
                <?php foreach ($visits as $visit): ?>
                    <div class="visit-card">
                        <div class="visit-header">
                            <div class="visit-id"><?php echo htmlspecialchars($visit['visit_reference']); ?></div>
                            <div class="visit-status status-<?php echo htmlspecialchars($visit['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($visit['status'])); ?>
                            </div>
                        </div>
                        
                        <div class="visit-details">
                            <div class="detail-item">
                                <div class="detail-label">Service Category</div>
                                <div class="detail-value"><?php echo htmlspecialchars($visit['category_name']); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Service Provider</div>
                                <div class="detail-value"><?php echo htmlspecialchars($visit['provider_name']); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Visit Date & Time</div>
                                <div class="detail-value">
                                    <?php 
                                        echo date('d M Y', strtotime($visit['visit_date'])) . ' at ' . 
                                             date('h:i A', strtotime($visit['visit_time'])); 
                                    ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Visit Fee</div>
                                <div class="detail-value">₹<?php echo number_format($visit['visit_fee'], 2); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Address</div>
                                <div class="detail-value"><?php echo htmlspecialchars($visit['address']); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Payment Method</div>
                                <div class="detail-value"><?php echo htmlspecialchars($visit['payment_method']); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($visit['status'] === 'scheduled'): ?>
                            <div class="visit-actions">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this visit?');">
                                    <input type="hidden" name="action" value="cancel_visit">
                                    <input type="hidden" name="visit_id" value="<?php echo $visit['visit_id']; ?>">
                                    <button type="submit" class="action-btn cancel-btn">Cancel Visit</button>
                                </form>
                                
                                <button class="action-btn reschedule-btn" onclick="alert('Please call customer support to reschedule your visit.')">Reschedule</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 