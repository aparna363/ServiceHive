<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Attempt to start session with error handling
if (!isset($_SESSION)) {
    // If session failed to start, clear session file and retry
    @session_destroy();
    session_start();
    session_regenerate_id(true);
}

require_once 'dbconnect.php';
$categories_query = "SELECT * FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
$categories = $conn->query($categories_query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean & Clear - Professional Cleaning Services</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    <!-- Add Razorpay JS -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<style>

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
    font-weight: bold;
    transition: transform 0.3s ease;
    margin-right: auto; 
}

.logo:hover {
    transform: scale(1.05);
}

.logo img {
    height: 60px;
    width: auto;
}

.search-container {
    position: relative;
}

    .search-bar {
        display: flex;
        align-items: center;
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 20px;
        padding: 5px 15px;
        transition: all 0.3s ease;
    }

    .search-bar:focus-within {
        border-color: #ee6e06;
        box-shadow: 0 0 5px #ee6e06;
    }

    .search-bar input {
        border: none;
        background: none;
        padding: 5px;
        width: 200px;
        outline: none;
        font-size: 14px;
    }

    .search-bar button {
        background: none;
        border: none;
        cursor: pointer;
        padding: 5px;
        color: #666;
        transition: color 0.3s ease;
    }

    .search-bar button:hover {
        color: #007bff;
    }

    /* Adjust existing nav-links to accommodate search bar */
.nav-links {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-right: 0;
        margin-left: 40px;
}
.user-menu {
    position: relative;
    margin-left: 0;
    padding-right: 20px;
    display: flex;
    align-items: center;
}

.user-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333;
    font-size: 16px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #ee6e06;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    text-transform: uppercase;
    transition: transform 0.2s ease;
}

.user-avatar:hover {
    transform: scale(1.05);
}

.user-icon svg {
    width: 24px;
    height: 24px;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    display: none;
    z-index: 1000;
}

.user-dropdown.active {
    display: block;
    animation: dropdownFade 0.2s ease-out;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.user-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    transition: background-color 0.2s;
}

.user-dropdown a:hover {
    background-color: #f5f5f5;
    color: #ee6e06;
}

.user-dropdown .divider {
    height: 1px;
    background-color: #ddd;
    margin: 8px 0;
}

/* ------------------------- */

.container3 {
    background-color: #ffffff;
    padding: 50px 20px;
}

.container3 h2 {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    color: #bc4f07;
    text-align: center;
    position: relative;
}

.container3 h2::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 3px;
    background-color:  #bc4f07;
    transition: width 0.3s ease;
}

.container3:hover h2::after {
    width: 100px;
}

.features3 {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
}

.feature3 {
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 150px;
    padding: 20px;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.feature3::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(14, 15, 14, 0.05);
    border-radius: 8px;
    transform: scale(0.8);
    opacity: 0;
    transition: all 0.3s ease;
}

.feature3:hover {
    transform: translateY(-5px);
}

.feature3:hover::before {
    transform: scale(1);
    opacity: 1;
}

.feature3 i {
    font-size: 40px;
    color: #099409;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.feature3:hover i {
    transform: scale(1.1) rotate(5deg);
    animation: iconPulse 1s ease infinite;
}

.feature3 p {
    font-size: 14px;
    font-weight: bold;
    color: #848b84;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.feature3:hover p {
    transform: scale(1.05);
}

/* Animation for icon pulse */
@keyframes iconPulse {
    0%, 100% {
        transform: scale(1.1) rotate(5deg);
    }
    50% {
        transform: scale(1.2) rotate(-5deg);
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .container3 {
        padding: 30px 15px;
    }
    
    .features3 {
        gap: 20px;
    }
    
    .feature3 {
        max-width: 130px;
        padding: 15px;
    }
    
    .feature3 i {
        font-size: 32px;
    }
    
    .feature3 p {
        font-size: 12px;
    }
}

/* Add to your style.css */
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-top: 5px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    border: 1px solid #ddd;
}

.search-result-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.search-result-card:hover {
    background-color: #f9f9f9;
}

.service-info h4 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.service-meta {
    display: flex;
    gap: 12px;
    margin-top: 4px;
    font-size: 14px;
    color: #666;
}

.rating {
    color: #ff9800;
}

.book-now {
    background: #ee6e06;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    font-size: 14px;
}

.book-now:hover {
    background: #d66000;
}

.no-results {
    padding: 20px;
    text-align: center;
    color: #666;
}

.popular-searches {
    margin-top: 15px;
}

.popular-searches h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #666;
}

.popular-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
}

.popular-tag {
    background: #f0f0f0;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    color: #555;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.3s;
}

.popular-tag:hover {
    background: #ee6e06;
    color: white;
}

.view-all-results {
    text-align: center;
    padding: 12px;
    background: #f5f5f5;
    border-top: 1px solid #eee;
}

.view-all-results a {
    color: #ee6e06;
    text-decoration: none;
    font-weight: 500;
}

.view-all-results a:hover {
    text-decoration: underline;
}

.loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.loading:after {
    content: "";
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #ee6e06;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    display: inline-block;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Floating Action Buttons */
.floating-action-buttons {
    position: fixed;
    bottom: 30px;
    right: 30px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    z-index: 999;
}

.floating-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.floating-btn:hover {
    transform: scale(1.1);
}

.floating-btn i {
    font-size: 24px;
    color: white;
}

.floating-btn.visit {
    background-color: #28a745;
}

.floating-btn.emergency {
    background-color: #e53e3e;
}

.btn-label {
    position: absolute;
    right: 70px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 14px;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.floating-btn:hover .btn-label {
    opacity: 1;
}

/* Visit Booking Modal */
.visit-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1001;
    overflow-y: auto; /* Add this to allow scrolling */
    padding: 20px; /* Add padding for better mobile experience */
}

.visit-modal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
    max-height: 90vh; /* Limit height */
    overflow-y: auto; /* Enable scrolling within modal */
    margin: auto; /* Center the modal */
}

.visit-modal .close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 24px;
    cursor: pointer;
}

.visit-modal h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #2d3748;
    font-size: 22px;
}

.visit-fee-notice {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.visit-fee-notice p {
    margin: 0;
    font-size: 15px;
}

.visit-fee-notice .fee {
    font-weight: 600;
    color: #28a745;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
}

.book-button {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}

.book-button:hover {
    background: #218838;
}

/* Emergency Booking Modal */
.emergency-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1001;
    overflow-y: auto; /* Add this to allow scrolling */
    padding: 20px; /* Add padding for better mobile experience */
}

.emergency-modal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
    max-height: 90vh; /* Limit height */
    overflow-y: auto; /* Enable scrolling within modal */
    margin: auto; /* Center the modal */
}

.emergency-modal .close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 24px;
    cursor: pointer;
    z-index: 1002;
    background: #fff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.emergency-modal .modal-header {
    position: sticky;
    top: 0;
    background: white;
    padding-bottom: 15px;
    margin-bottom: 15px;
    border-bottom: 1px solid #eee;
    z-index: 1;
}

.emergency-fee-notice {
    background: #fff5f5;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #e53e3e;
}

.emergency-fee-notice p {
    margin: 0;
    font-size: 15px;
}

.emergency-fee-notice .fee {
    font-weight: 600;
    color: #e53e3e;
}

/* Success Modals */
.visit-success-modal,
.emergency-success-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1001;
}

.visit-success-modal .modal-content,
.emergency-success-modal .modal-content {
    background: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    max-width: 500px;
    width: 90%;
    position: relative;
    transform: scale(0.7);
    transition: transform 0.3s ease-out;
}

.visit-success-modal.show .modal-content,
.emergency-success-modal.show .modal-content {
    transform: scale(1);
}

.success-icon {
    color: #28a745;
    font-size: 64px;
    margin-bottom: 20px;
}

.emergency-icon {
    color: #e53e3e;
    font-size: 64px;
    margin-bottom: 20px;
}

.modal-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.modal-buttons button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.modal-buttons button:first-child {
    background: #f1f1f1;
    color: #333;
}

.modal-buttons button:last-child {
    background: #28a745;
    color: white;
}

@media (max-width: 768px) {
    .floating-action-buttons {
        bottom: 20px;
        right: 20px;
    }
    
    .floating-btn {
        width: 50px;
        height: 50px;
    }
    
    .floating-btn i {
        font-size: 20px;
    }
    
    .modal-buttons {
        flex-direction: column;
    }
    
    .modal-buttons button {
        width: 100%;
    }
}

/* Review Popup Modal */
.review-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1500;
    overflow-y: auto; /* Add this to allow scrolling */
    padding: 20px; /* Add padding for better mobile experience */
}

.review-modal.active {
    display: flex !important;
}

.review-modal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.review-modal .close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 24px;
    cursor: pointer;
}

.review-modal h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #2d3748;
    font-size: 22px;
}

.completed-service-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #f39c12;
}

.service-details h4 {
    margin-top: 0;
    color: #333;
}

.service-details p {
    margin: 5px 0;
    color: #666;
}

.rating-container {
    margin-bottom: 20px;
}

.rating-label {
    font-weight: 500;
    margin-bottom: 10px;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.star-rating input {
    display: none;
}

.star-rating label {
    font-size: 30px;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.star-rating label:before {
    content: "★";
}

.star-rating input:checked ~ label {
    color: #f39c12;
}

.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #f39c12;
}

.review-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.book-button {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}

.book-button:hover {
    background: #218838;
}

/* Add this to your existing styles */
.provider-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 5px;
}

.provider-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.provider-card:hover {
    border-color: #ee6e06;
    background-color: #fff9f5;
}

.provider-card.selected {
    border-color: #ee6e06;
    background-color: #fff9f5;
    box-shadow: 0 0 0 2px rgba(238, 110, 6, 0.2);
}

.provider-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-weight: bold;
    color: #666;
    font-size: 18px;
}

.provider-info {
    flex: 1;
}

.provider-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.provider-meta {
    display: flex;
    gap: 15px;
    font-size: 14px;
    color: #666;
}

.provider-rating {
    color: #f39c12;
}

.provider-jobs {
    color: #3498db;
}

.no-providers {
    padding: 20px;
    text-align: center;
    color: #666;
    background: #f9f9f9;
    border-radius: 8px;
}

.loading-providers {
    padding: 20px;
    text-align: center;
    color: #666;
}

/* Add this to your existing CSS */
.search-suggestion {
    margin: 10px 0;
    font-style: italic;
    color: #555;
}

.search-suggestion a {
    color: #007bff;
    text-decoration: underline;
    font-weight: bold;
}

.search-suggestion-header {
    padding: 8px 10px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
    font-style: italic;
    color: #555;
}

.search-suggestion-header a {
    color: #007bff;
    text-decoration: underline;
    font-weight: bold;
}
</style>
<body>
    <div class="container">
        <nav>
        <div class="logo">
                <a href="index.php"><img src="images/logo2.png" alt="ServiceHive Logo"></a>
                
            </div>
            <div class="nav-links">
                <a href="index.php" class="dropdown-indicator">Home</a>
                <a href="aboutus.php">About Us</a>
                <div class="dropdown">
                   <a href="#services" class="dropdown-indicator">Services</a>
                    <div class="dropdown-content">
                        <?php
                        // Fetch categories from database
                        $query = "SELECT * FROM tbl_categories WHERE is_active = TRUE";
                        $result = $conn->query($query);
                        
                        while($row = $result->fetch_assoc()) {
                            echo '<a href="services.php?category_id=' . $row['category_id'] . '">' . 
                                htmlspecialchars($row['category_name']) . '</a>';
                        }
                        ?>
                    </div>
                </div>
                <a href="contact.php">Contact Us</a>
                <div class="search-container">
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search for services..." aria-label="Search">
        <button type="button" id="searchButton">
            <i class="fas fa-search"></i>
        </button>
    </div>
    <div id="searchResults" class="search-results" style="display: none;"></div>
</div>
                
                        


            </div>
            <div class="user-menu">
    <?php if(isset($_SESSION['username'])): ?>
        <button id="userMenuButton" class="user-icon">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
        </button>
    <?php else: ?>
        <button id="userMenuButton" class="user-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </button>
    <?php endif; ?>
    
    <div id="userDropdown" class="user-dropdown">
        <?php if(isset($_SESSION['username'])): ?>
            <a href="profile.php">Profile</a>
            <a href="booking.php">My Bookings</a>
            <a href="reviews.php">Reviews</a>
            <div class="divider"></div>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="select-type.php">Sign Up</a>
        <?php endif; ?>
    </div>
</div>
    
             <!-- <a href="login.php" class="get-in-touch">Login</a> -->
        </nav>
        <section class="hero">             
            <div class="slider">                 
                <div class="slide active">                     
                    <img src="images/main.webp" alt="images" />                 
                </div>                 
                <div class="slide">                     
                    <img src="images/main1.jpeg" alt="images" />                 
                </div>                 
                <div class="slide">                     
                    <img src="images/main3.jpeg" alt="images" />                 
                </div>             
            </div>             
            <div class="slider-nav"></div>             
            <div class="slider-arrow slider-prev">‹</div>             
            <div class="slider-arrow slider-next">›</div>
            <h1>Your Trusted Partner for Home Services</h1>
    <p>Connect with skilled professionals for all your household needs</p>
    <div class="cta-buttons">
        <a href="aboutus.php" class="cta-button primary-button">ABOUT US</a>
        <a href="contact.php" class="cta-button secondary-button">GET IN TOUCH</a>
    </div>         
        </section>
    </div>

     
    <div class="services-sections1">
        <div class="services-contents1">
            <div class="services-images1">
                <div class="slidess active2">
                    <img src="images/image1.jpeg" alt="Service 1">
                </div>
                <!-- <div class="slide2">
                    <img src="image2.jpg" alt="Service 2">
                </div>
                <div class="slide2">
                    <img src="image3.jpg" alt="Service 3">
                </div>
                <div class="slide2">
                    <img src="plumbing1.webp" alt="Service 4">
                </div> -->
    
               
                <!-- <div class="experience-badge">
                    <div class="icon">★</div>
                    <div>23 Years Experience</div>
                </div> -->
    
                <div class="carousel-nav"></div>
            </div>
        </div>
    
        <div class="services-text">
            <h2 class="services-title">Your Trusted Partner for Household Solutions</h2>
            <p>Simplify your life with ServiceHive, connecting you to skilled professionals for all your household needs. From seamless booking to secure payments, we prioritize quality, reliability, 
            and your peace of mind. Choose ServiceHive for hassle-free, dependable service every time.</p>
            <div class="services-list">
                <div class="service-item">✓ House</div>
                <div class="service-item">✓ Warehouses</div>
                <div class="service-item">✓ Restaurant</div>
                <div class="service-item">✓ Showrooms</div>
                <div class="service-item">✓ Workship Place</div>
                <div class="service-item">✓ Office</div>
                <div class="service-item">✓ Hotel</div>
                <div class="service-item">✓ Hospital</div>
            </div>
        </div>
    </div>
        
   


    
       
    
    <div class="services">
        <h1>SERVICES</h1>
        <h3>What we offer</h3>
    </div>
    
    <section class="services-section">
    <div class="carousel">
        <div class="carousel-track-container">
            <ul class="carousel-track">
                <?php while ($category = $categories->fetch_assoc()): ?>
                <li class="carousel-slide">
                    <div class="card">
                        <?php 
                        $imagePath = "/api/placeholder/400/300";
                        if (isset($category['image_path']) && !empty($category['image_path'])) {
                            $imagePath = htmlspecialchars($category['image_path']);
                        }
                        ?>
                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>" class="card-image">
                        <h3 class="card-title"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                        <p class="card-description"><?php echo htmlspecialchars($category['description']); ?></p>
                        <a href="services.php?category_id=<?php echo $category['category_id']; ?>" style="display: flex; justify-content: center; text-decoration: none;">
                            <button style="background-color:rgb(18, 136, 171); color: white; border:none; border-radius:5px; padding: 10px 20px; cursor:pointer;">
                                View More
                            </button>
                        </a>
                    </div>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
</section>
    
    <div class="indicators">
        <span class="indicator active" data-slide1="0"></span>
        <span class="indicator" data-slide1="1"></span>
        
    </div>

    <div class="container3">
        <h2>WHY SERVICEHIVE?</h2>
        <div class="features3">
            <div class="feature3">
                <i class="fa-solid fa-calendar-check"></i>
                <p>ON DEMAND / SCHEDULED</p>
            </div>
            <div class="feature3">
                <i class="fa-solid fa-user-check"></i>
                <p>VERIFIED PARTNERS</p>
            </div>
            <!-- <div class="feature">
                <i class="fa-solid fa-shield-check"></i>
                <p>SERVICE WARRANTY</p>
            </div> -->
            <div class="feature3">
                <i class="fa-solid fa-tag"></i>
                <p>TRANSPARENT PRICING</p>
            </div>
            <div class="feature3">
                <i class="fa-solid fa-credit-card"></i>
                <p>ONLINE PAYMENTS</p>
            </div>
            <div class="feature3">
                <i class="fa-solid fa-headset"></i>
                <p>SUPPORT</p>
            </div>
        </div>
    </div>
    </div>

    <!-- Floating Action Buttons -->
    <div class="floating-action-buttons">
        <div class="floating-btn visit" onclick="showVisitModal()">
            <i class="fas fa-calendar-check"></i>
            <span class="btn-label">Book a Visit</span>
        </div>
        <div class="floating-btn emergency" onclick="showEmergencyModal()">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="btn-label">Emergency Service</span>
        </div>
    </div>

    <!-- Visit Booking Modal -->
    <div id="visitModal" class="visit-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeVisitModal()">&times;</span>
            <h3>Book a Technical Visit</h3>
            
            <div class="visit-fee-notice">
                <p>A technician will visit your location to assess your requirements.</p>
                <p>Visit fee: <span class="fee">₹199</span> (payable on visit)</p>
            </div>
            
            <form id="visitForm">
                <input type="hidden" name="action" value="book_visit">
                
                <div class="form-group">
                    <label>Service Category</label>
                    <select name="category_id" id="visit_category_id" required onchange="loadProviders('visit')">
                        <option value="">Select a category</option>
                        <?php
                        // Reset the categories result pointer
                        $categories->data_seek(0);
                        while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group" id="visit_provider_container" style="display: none;">
                    <label>Select Provider</label>
                    <div class="provider-list" id="visit_provider_list">
                        <!-- Providers will be loaded here -->
                    </div>
                </div>
                
                <input type="hidden" name="provider_id" id="visit_provider_id" value="">
                
                <div class="form-group">
                    <label>Visit Date</label>
                    <input type="date" name="visit_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Visit Time</label>
                    <select name="visit_time" required>
                        <?php for($i = 9; $i <= 17; $i++): ?>
                            <option value="<?php echo sprintf('%02d:00', $i); ?>">
                                <?php echo date('h:i A', strtotime(sprintf('%02d:00', $i))); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="visit_address" required rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Additional Notes (optional)</label>
                    <textarea name="visit_notes" rows="3" placeholder="Describe your requirements or issues"></textarea>
                </div>
                
                <button type="button" class="book-button" onclick="bookVisit()">Confirm Visit</button>
            </form>
        </div>
    </div>
    
    <!-- Visit Success Modal -->
    <div id="visitSuccessModal" class="visit-success-modal">
        <div class="modal-content">
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Visit Scheduled!</h2>
            <p>Your technical visit has been successfully scheduled.</p>
            <p>Visit Reference: <strong id="visitReference"></strong></p>
            <p>A confirmation email has been sent to your registered email address.</p>
            <div class="modal-buttons">
                <button onclick="window.location.href='visits.php'">View My Visits</button>
                <button onclick="window.location.reload()">Continue Shopping</button>
            </div>
        </div>
    </div>

    <!-- Emergency Booking Modal -->
    <div id="emergencyModal" class="emergency-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEmergencyModal()">&times;</span>
            
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Emergency Service Request</h3>
                
                <div class="emergency-fee-notice">
                    <p><strong>Need urgent help?</strong> Our technicians will prioritize your request.</p>
                    <p>Emergency service fee: <span class="fee">₹299</span> (additional to service charges)</p>
                    <p>Expected response time: <strong>Within 2 hours</strong></p>
                </div>
            </div>
            
            <form id="emergencyForm">
                <input type="hidden" name="action" value="book_emergency">
                
                <div class="form-group">
                    <label>Service Category</label>
                    <select name="category_id" id="emergency_category_id" required onchange="loadProviders('emergency')">
                        <option value="">Select a category</option>
                        <?php
                        // Reset the categories result pointer
                        $categories->data_seek(0);
                        while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group" id="emergency_provider_container" style="display: none;">
                    <label>Select Provider</label>
                    <div class="provider-list" id="emergency_provider_list">
                        <!-- Providers will be loaded here -->
                    </div>
                </div>
                
                <input type="hidden" name="provider_id" id="emergency_provider_id" value="">
                
                <div class="form-group">
                    <label>Your Name*</label>
                    <input type="text" name="emergency_name" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number*</label>
                    <input type="tel" name="emergency_phone" required pattern="[0-9]{10}">
                </div>
                
                <div class="form-group">
                    <label>Email Address*</label>
                    <input type="email" name="emergency_email" required>
                </div>
                
                <div class="form-group">
                    <label>Address*</label>
                    <textarea name="emergency_address" required rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Describe your emergency*</label>
                    <textarea name="emergency_issue" required rows="3" placeholder="Please provide details about your emergency"></textarea>
                </div>
                
                <button type="button" class="book-button" style="background-color: #e53e3e;" onclick="bookEmergency()">Request Emergency Service</button>
            </form>
        </div>
    </div>
    
    <!-- Emergency Success Modal -->
    <div id="emergencySuccessModal" class="emergency-success-modal">
        <div class="modal-content">
            <i class="fas fa-exclamation-circle emergency-icon"></i>
            <h2>Emergency Request Received!</h2>
            <p>Your emergency service request has been prioritized.</p>
            <p>Emergency Reference: <strong id="emergencyReference"></strong></p>
            <p>A technician will contact you shortly.</p>
            <div class="modal-buttons">
                <button onclick="window.location.reload()">Close</button>
            </div>
        </div>
    </div>

    <!-- Review Popup Modal -->
    <div id="reviewModal" class="review-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeReviewModal()">&times;</span>
            <h3><i class="fas fa-star"></i> Rate Your Service</h3>
            
            <div class="completed-service-info" id="serviceInfo">
                <!-- Service details will be loaded here -->
            </div>
            
            <form id="reviewForm">
                <input type="hidden" name="booking_id" id="review_booking_id">
                <input type="hidden" name="provider_id" id="review_provider_id">
                <input type="hidden" name="service_id" id="review_service_id">
                
                <div class="rating-container">
                    <div class="rating-label">Your Rating:</div>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" required><label for="star5"></label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4"></label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3"></label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2"></label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1"></label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review_text">Your Review:</label>
                    <textarea id="review_text" name="review_text" rows="4" placeholder="Share your experience with this service..." required></textarea>
                </div>
                
                <button type="button" class="book-button" onclick="submitReview()">Submit Review</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="container9">
            <div class="logo3">
                <img src="images/logo1.png" alt=" Logo">
                
            </div>
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="aboutus.php">About us</a></li>
                        <li><a href="#">Terms & conditions</a></li>
                        <li><a href="#">Privacy policy</a></li>
                        
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>For customers</h3>
                    <ul>
                        <li><a href="#">Our Services</a></li>
                       
                        <li><a href="contact.php">Contact us</a></li>
                        
                        <li><a href="support.php">Customer Support</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>For Partners</h3>
                    <ul>
                        <li><a href="signup.php">Register as a professional</a></li>
                    </ul>
                </div>

                <div class="footer-section contact-info">
                <h3>Contact Info</h3>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> 201 Stokes Isle Apt. 896, New York 10010, US</li>
                    <li><i class="fas fa-phone-alt"></i> (+01) 123 456 7890</li>
                    <li><i class="fas fa-envelope"></i> servicehive.com</li>
                </ul>
                <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
            </div>
        </div>

                
                    
                    <!-- <div class="app-download">
                        <img src="path/to/app-store-icon.png" alt="Download on App Store">
                        <img src="path/to/google-play-icon.png" alt="Get it on Google Play">
                    </div> -->
                </div>
            </div>

            <div class="copyright">
                © Copyright 2025 ServiceHive. All rights reserved. 
            </div>
        </div>
    </footer>
    
   
    




</body>
</html>

<!-- Add this just before </body> -->
<!-- <script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all dropdown elements
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(dropdown => {
        const dropdownIndicator = dropdown.querySelector('.dropdown-indicator');
        const dropdownContent = dropdown.querySelector('.dropdown-content');

        // Toggle dropdown on click
        dropdownIndicator.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Close all other dropdowns
            dropdowns.forEach(otherDropdown => {
                if (otherDropdown !== dropdown) {
                    otherDropdown.querySelector('.dropdown-content').style.display = 'none';
                }
            });

            // Toggle current dropdown
            const currentDisplay = dropdownContent.style.display;
            dropdownContent.style.display = currentDisplay === 'block' ? 'none' : 'block';
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.querySelector('.dropdown-content').style.display = 'none';
            });
        }
    });
});
</script> -->

<script>
// Visit booking functions
function showVisitModal() {
    const modal = document.getElementById('visitModal');
    modal.style.display = 'flex';
    
    // Reset form if needed
    document.getElementById('visitForm').reset();
}

function closeVisitModal() {
    document.getElementById('visitModal').style.display = 'none';
}

// Close modal if user clicks outside the modal content
document.getElementById('visitModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeVisitModal();
    }
});

function bookVisit() {
    const formData = new FormData(document.getElementById('visitForm'));
    
    // Check if provider is selected
    const providerId = document.getElementById('visit_provider_id').value;
    if (!providerId && document.getElementById('visit_provider_container').style.display !== 'none') {
        alert('Please select a provider or we will assign one for you.');
        // Set provider_id to 0 to indicate system should assign a provider
        document.getElementById('visit_provider_id').value = '0';
    }
    
    // Convert FormData to an object for JSON serialization
    const formDataObj = {};
    formData.forEach((value, key) => {
        formDataObj[key] = value;
    });
    
    // Debug - Log form data
    console.log('Form data being sent:', formDataObj);
    
    // First, create a server-side order
    fetch('create_order.php', {
        method: 'POST',
        body: JSON.stringify({
            amount: 199 * 100, // Convert to paisa (₹199)
            type: 'visit',
            formData: formDataObj
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // First check if the response is ok
        if (!response.ok) {
            return response.json().then(error => {
                throw new Error(`Server Error: ${error.message || 'Unknown error'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Initialize Razorpay payment
            const options = {
                key: data.key_id,
                amount: data.amount,
                currency: "INR",
                name: "ServiceHive",
                description: "Technical Visit Fee",
                order_id: data.order_id,
                handler: function (response) {
                    verifyPayment(response, 'visit', data.booking_id);
                },
                prefill: {
                    name: data.user_name || '',
                    email: data.user_email || '',
                    contact: data.user_phone || ''
                },
                notes: {
                    booking_id: data.booking_id,
                    type: 'visit'
                },
                theme: {
                    color: "#ee6e06"
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
            
            closeVisitModal();
        } else {
            // Improved error handling for server errors
            const errorMessage = data.message || 'Unknown server error';
            console.error(`Server error: ${errorMessage}`);
            alert(`Error: ${errorMessage}`);
        }
    })
    .catch(error => {
        console.error(error.message);
        alert(error.message);
    });
}

// Emergency booking functions
function showEmergencyModal() {
    const modal = document.getElementById('emergencyModal');
    modal.style.display = 'flex';
    
    // Reset form if needed
    document.getElementById('emergencyForm').reset();
}

function closeEmergencyModal() {
    document.getElementById('emergencyModal').style.display = 'none';
}

// Close modal if user clicks outside the modal content
document.getElementById('emergencyModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeEmergencyModal();
    }
});

function bookEmergency() {
    const formData = new FormData(document.getElementById('emergencyForm'));
    
    // Check if provider is selected
    const providerId = document.getElementById('emergency_provider_id').value;
    if (!providerId && document.getElementById('emergency_provider_container').style.display !== 'none') {
        alert('Please select a provider or we will assign one for you.');
        // Set provider_id to 0 to indicate system should assign a provider
        document.getElementById('emergency_provider_id').value = '0';
    }
    
    // Convert FormData to an object for JSON serialization
    const formDataObj = {};
    formData.forEach((value, key) => {
        formDataObj[key] = value;
    });
    
    // Debug - Log form data
    console.log('Emergency form data being sent:', formDataObj);
    
    // First, create a server-side order
    fetch('create_order.php', {
        method: 'POST',
        body: JSON.stringify({
            amount: 299 * 100, // Convert to paisa (₹299)
            type: 'emergency',
            formData: formDataObj
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // First check if the response is ok
        if (!response.ok) {
            return response.json().then(error => {
                throw new Error(`Server Error: ${error.message || 'Unknown error'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Initialize Razorpay payment
            const options = {
                key: data.key_id,
                amount: data.amount,
                currency: "INR",
                name: "ServiceHive",
                description: "Emergency Service Fee",
                order_id: data.order_id,
                handler: function (response) {
                    verifyPayment(response, 'emergency', data.booking_id);
                },
                prefill: {
                    name: data.user_name || '',
                    email: data.user_email || '',
                    contact: data.user_phone || ''
                },
                notes: {
                    booking_id: data.booking_id,
                    type: 'emergency'
                },
                theme: {
                    color: "#e53e3e"
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
            
            closeEmergencyModal();
        } else {
            // Improved error handling for server errors
            const errorMessage = data.message || 'Unknown server error';
            console.error(`Server error: ${errorMessage}`);
            alert(`Error: ${errorMessage}`);
        }
    })
    .catch(error => {
        console.error(error.message);
        alert(error.message);
    });
}

// Function to verify payment after completion
function verifyPayment(response, type, booking_id) {
    fetch('verify_payment.php', {
        method: 'POST',
        body: JSON.stringify({
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_signature: response.razorpay_signature,
            booking_id: booking_id,
            type: type
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(error => {
                throw new Error(`Verification Error: ${error.message || 'Unknown error'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success modal based on type
            if (type === 'visit') {
                document.getElementById('visitReference').textContent = data.reference;
                document.getElementById('visitSuccessModal').style.display = 'flex';
                setTimeout(() => {
                    document.getElementById('visitSuccessModal').classList.add('show');
                }, 10);
            } else {
                document.getElementById('emergencyReference').textContent = data.reference;
                document.getElementById('emergencySuccessModal').style.display = 'flex';
                setTimeout(() => {
                    document.getElementById('emergencySuccessModal').classList.add('show');
                }, 10);
            }
        } else {
            const errorMessage = data.message || 'Payment verification failed';
            console.error(`Verification error: ${errorMessage}`);
            alert(`Verification Error: ${errorMessage}`);
        }
    })
    .catch(error => {
        console.error(error.message);
        alert(error.message);
    });
}

// Add before your existing JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking for completed services...');
    checkCompletedServices();
    
    // Debug button to manually show the review modal (for testing)
    // You can remove this after confirming the modal works
    window.showDebugModal = function() {
        console.log('Debug: Showing review modal');
        document.getElementById('serviceInfo').innerHTML = `
            <div class="service-details">
                <h4>Test Service</h4>
                <p>Provider: Test Provider</p>
                <p>Date: Today</p>
            </div>
        `;
        showReviewModal();
    }
});

function checkCompletedServices() {
    <?php if(isset($_SESSION['user_id'])): ?>
    console.log('User logged in, fetching completed services...');
    fetch('check_completed_services.php')
        .then(response => {
            console.log('Response received:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.success && data.needsReview) {
                console.log('Service needs review, showing modal');
                // Set service details
                document.getElementById('serviceInfo').innerHTML = `
                    <div class="service-details">
                        <h4>${data.service_name}</h4>
                        <p>Provider: ${data.provider_name}</p>
                        <p>Date: ${data.booking_date}</p>
                    </div>
                `;
                
                // Set hidden form values
                document.getElementById('review_booking_id').value = data.booking_id;
                document.getElementById('review_provider_id').value = data.provider_id;
                document.getElementById('review_service_id').value = data.service_id;
                
                // Show the review modal
                showReviewModal();
            } else {
                console.log('No services need review or request failed');
            }
        })
        .catch(error => {
            console.error('Error checking completed services:', error);
        });
    <?php else: ?>
        console.log('User not logged in, skipping service check');
    <?php endif; ?>
}

function showReviewModal() {
    console.log('Showing review modal');
    const modal = document.getElementById('reviewModal');
    modal.classList.add('active');
    modal.style.display = 'flex';
}

function closeReviewModal() {
    console.log('Closing review modal');
    const modal = document.getElementById('reviewModal');
    modal.classList.remove('active');
    modal.style.display = 'none';
}

function submitReview() {
    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);
    
    console.log('Submitting review...');
    
    fetch('submit_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Review submission response:', data);
        if (data.success) {
            alert('Thank you for your review!');
            closeReviewModal();
        } else {
            alert('Failed to submit review: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error submitting review:', error);
        alert('An error occurred while submitting your review');
    });
}

// Debug function to test if JavaScript is working
console.log('Review modal script loaded');
</script>

<!-- Add this at the bottom of your page for testing -->
<div style="margin-top: 20px; text-align: center; padding: 10px; background: #f5f5f5; border-radius: 5px;">
    <button onclick="showDebugModal()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
        Test Review Modal
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    function displayResults(data) {
        if (!data.success) {
            searchResults.innerHTML = `
                <div class="error">
                    <p>${data.error || 'An error occurred while searching. Please try again.'}</p>
                </div>`;
            return;
        }

        if (data.results.length === 0) {
            searchResults.innerHTML = `
                <div class="no-results">
                    <p>No results found</p>
                    <div class="popular-searches">
                        <h3>Popular Searches</h3>
                        <div class="popular-tags">
                            <a href="#" onclick="event.preventDefault(); document.getElementById('searchInput').value='Cleaning'; performSearch('Cleaning')">Cleaning</a>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('searchInput').value='Plumbing'; performSearch('Plumbing')">Plumbing</a>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('searchInput').value='Electrical'; performSearch('Electrical')">Electrical</a>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('searchInput').value='Painting'; performSearch('Painting')">Painting</a>
                        </div>
                    </div>
                </div>`;
            return;
        }

        let resultsHtml = '<div class="search-results-container">';
        data.results.forEach(service => {
            resultsHtml += `
                <div class="search-result-card">
                    <div class="service-info">
                        <h4>${service.service_name}</h4>
                        <div class="service-meta">
                            <span class="category">${service.category_name}</span>
                            ${service.avg_rating > 0 ? 
                                `<span class="rating">★ ${service.avg_rating}</span>` : 
                                ''}
                            <span class="price">₹${service.price}</span>
                        </div>
                    </div>
                    <a href="service-details.php?service_id=${service.service_id}" class="book-now">Book Now</a>
                </div>`;
        });
        resultsHtml += '</div>';
        searchResults.innerHTML = resultsHtml;
    }

    function performSearch(query) {
        // Show loading state
        searchResults.innerHTML = '<div class="loading">Searching...</div>';
        searchResults.style.display = 'block';

        // Make the API call
        fetch(`search.php?query=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse response:', text);
                        throw new Error('Invalid response from server');
                    }
                });
            })
            .then(data => {
                displayResults(data);
            })
            .catch(error => {
                console.error('Search error:', error);
                searchResults.innerHTML = `
                    <div class="error">
                        <p>An error occurred while searching. Please try again.</p>
                        <p class="error-details">${error.message}</p>
                    </div>`;
            });
    }

    // Search on input with debounce
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length === 0) {
            searchResults.style.display = 'none';
            return;
        }

        if (query.length < 2) {
            return;
        }

        searchTimeout = setTimeout(() => performSearch(query), 300);
    });

    // Search on button click
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        if (query.length >= 2) {
            performSearch(query);
        }
    });

    // Search on Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length >= 2) {
                e.preventDefault();
                performSearch(query);
            }
        }
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            searchResults.style.display = 'none';
        }
    });

    // Make performSearch available globally for the suggestion links
    window.performSearch = performSearch;
});
</script>

<script>
// Add this to your existing JavaScript

// Function to load providers based on selected category
function loadProviders(type) {
    const categoryId = document.getElementById(`${type}_category_id`).value;
    const providerContainer = document.getElementById(`${type}_provider_container`);
    const providerList = document.getElementById(`${type}_provider_list`);
    
    if (!categoryId) {
        providerContainer.style.display = 'none';
        return;
    }
    
    // Show loading
    providerContainer.style.display = 'block';
    providerList.innerHTML = '<div class="loading-providers">Loading providers...</div>';
    
    // Fetch providers for the selected category
    fetch(`get_providers.php?category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.providers && data.providers.length > 0) {
                let providersHtml = '';
                
                data.providers.forEach(provider => {
                    const initials = provider.name.split(' ').map(n => n[0]).join('').toUpperCase();
                    providersHtml += `
                        <div class="provider-card" data-provider-id="${provider.provider_id}" onclick="selectProvider('${type}', ${provider.provider_id})">
                            <div class="provider-avatar">${initials}</div>
                            <div class="provider-info">
                                <div class="provider-name">${provider.name}</div>
                                <div class="provider-meta">
                                    ${provider.rating ? `<span class="provider-rating">★ ${provider.rating}</span>` : ''}
                                    <span class="provider-jobs">${provider.completed_jobs || 0} jobs completed</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                providerList.innerHTML = providersHtml;
            } else {
                providerList.innerHTML = `
                    <div class="no-providers">
                        <p>No providers available for this category.</p>
                        <p>We'll assign the best available provider for you.</p>
                    </div>
                `;
                // Set provider_id to 0 to indicate system should assign a provider
                document.getElementById(`${type}_provider_id`).value = '0';
            }
        })
        .catch(error => {
            console.error('Error loading providers:', error);
            providerList.innerHTML = `
                <div class="no-providers">
                    <p>Error loading providers. We'll assign the best available provider for you.</p>
                </div>
            `;
            // Set provider_id to 0 to indicate system should assign a provider
            document.getElementById(`${type}_provider_id`).value = '0';
        });
}

// Function to select a provider
function selectProvider(type, providerId) {
    // Remove selected class from all provider cards
    const providerCards = document.querySelectorAll(`#${type}_provider_list .provider-card`);
    providerCards.forEach(card => card.classList.remove('selected'));
    
    // Add selected class to the clicked card
    const selectedCard = document.querySelector(`#${type}_provider_list .provider-card[data-provider-id="${providerId}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Set the provider ID in the hidden input
    document.getElementById(`${type}_provider_id`).value = providerId;
}

// Update the existing bookVisit and bookEmergency functions to include provider_id
function bookVisit() {
    const formData = new FormData(document.getElementById('visitForm'));
    
    // Check if provider is selected
    const providerId = document.getElementById('visit_provider_id').value;
    if (!providerId && document.getElementById('visit_provider_container').style.display !== 'none') {
        alert('Please select a provider or we will assign one for you.');
        // Set provider_id to 0 to indicate system should assign a provider
        document.getElementById('visit_provider_id').value = '0';
    }
    
    // Convert FormData to an object for JSON serialization
    const formDataObj = {};
    formData.forEach((value, key) => {
        formDataObj[key] = value;
    });
    
    // Debug - Log form data
    console.log('Form data being sent:', formDataObj);
    
    // First, create a server-side order
    fetch('create_order.php', {
        method: 'POST',
        body: JSON.stringify({
            amount: 199 * 100, // Convert to paisa (₹199)
            type: 'visit',
            formData: formDataObj
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // First check if the response is ok
        if (!response.ok) {
            return response.json().then(error => {
                throw new Error(`Server Error: ${error.message || 'Unknown error'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Initialize Razorpay payment
            const options = {
                key: data.key_id,
                amount: data.amount,
                currency: "INR",
                name: "ServiceHive",
                description: "Technical Visit Fee",
                order_id: data.order_id,
                handler: function (response) {
                    verifyPayment(response, 'visit', data.booking_id);
                },
                prefill: {
                    name: data.user_name || '',
                    email: data.user_email || '',
                    contact: data.user_phone || ''
                },
                notes: {
                    booking_id: data.booking_id,
                    type: 'visit'
                },
                theme: {
                    color: "#ee6e06"
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
            
            closeVisitModal();
        } else {
            // Improved error handling for server errors
            const errorMessage = data.message || 'Unknown server error';
            console.error(`Server error: ${errorMessage}`);
            alert(`Error: ${errorMessage}`);
        }
    })
    .catch(error => {
        console.error(error.message);
        alert(error.message);
    });
}

function bookEmergency() {
    const formData = new FormData(document.getElementById('emergencyForm'));
    
    // Check if provider is selected
    const providerId = document.getElementById('emergency_provider_id').value;
    if (!providerId && document.getElementById('emergency_provider_container').style.display !== 'none') {
        alert('Please select a provider or we will assign one for you.');
        // Set provider_id to 0 to indicate system should assign a provider
        document.getElementById('emergency_provider_id').value = '0';
    }
    
    // Convert FormData to an object for JSON serialization
    const formDataObj = {};
    formData.forEach((value, key) => {
        formDataObj[key] = value;
    });
    
    // Debug - Log form data
    console.log('Emergency form data being sent:', formDataObj);
    
    // First, create a server-side order
    fetch('create_order.php', {
        method: 'POST',
        body: JSON.stringify({
            amount: 299 * 100, // Convert to paisa (₹299)
            type: 'emergency',
            formData: formDataObj
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // First check if the response is ok
        if (!response.ok) {
            return response.json().then(error => {
                throw new Error(`Server Error: ${error.message || 'Unknown error'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Initialize Razorpay payment
            const options = {
                key: data.key_id,
                amount: data.amount,
                currency: "INR",
                name: "ServiceHive",
                description: "Emergency Service Fee",
                order_id: data.order_id,
                handler: function (response) {
                    verifyPayment(response, 'emergency', data.booking_id);
                },
                prefill: {
                    name: data.user_name || '',
                    email: data.user_email || '',
                    contact: data.user_phone || ''
                },
                notes: {
                    booking_id: data.booking_id,
                    type: 'emergency'
                },
                theme: {
                    color: "#e53e3e"
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
            
            closeEmergencyModal();
        } else {
            // Improved error handling for server errors
            const errorMessage = data.message || 'Unknown server error';
            console.error(`Server error: ${errorMessage}`);
            alert(`Error: ${errorMessage}`);
        }
    })
    .catch(error => {
        console.error(error.message);
        alert(error.message);
    });
}
</script>
</script>