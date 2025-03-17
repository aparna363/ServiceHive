<?php
// Attempt to start session with error handling
session_start();
if (!isset($_SESSION)) {
    // If session failed to start, clear session file and retry
    @session_destroy();
    session_start();
    session_regenerate_id(true);
}

require_once 'dbconnect.php';
$categories_query = "SELECT * FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
$categories = $conn->query($categories_query);
// class SearchBar {
//     private $sampleData = [
//         'Cleaning',
//         'Plumber',
//         'Electrician',
//         'Carpenter',
//         'HouseKeeper',
//         'Painting'
//     ];

//     public function handleSearch($searchTerm) {
//         if (empty(trim($searchTerm))) {
//             return ['results' => [], 'noResults' => false];
//         }

//         $filteredResults = array_filter($this->sampleData, function($item) use ($searchTerm) {
//             return stripos($item, $searchTerm) !== false;
//         });

//         return [
//             'results' => array_values($filteredResults),
//             'noResults' => empty($filteredResults)
//         ];
//     }
// }
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
    display: flex;
    align-items: center;
    margin-right: 15px;
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-top: 8px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
}

.search-result-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
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
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}

.popular-searches {
    padding: 16px;
}

.popular-searches h3 {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #666;
}

.popular-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.popular-tag {
    background: #f5f5f5;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
}

.no-results {
    padding: 24px;
    text-align: center;
    color: #666;
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
}

.visit-modal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
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
    overflow-y: auto;
    padding: 20px;
}

.emergency-modal .modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    margin: auto;
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
        <button type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
            </svg>
        </button>
    </div>
    <div id="searchResults" class="search-results"></div>
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
            <a href="settings.php">Settings</a>
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
                    <select name="category_id" required>
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
                    <select name="category_id" required>
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
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Reset form if needed
    document.getElementById('visitForm').reset();
}

function closeVisitModal() {
    document.getElementById('visitModal').style.display = 'none';
    
    // Re-enable body scrolling
    document.body.style.overflow = '';
}

// Close modal if user clicks outside the modal content
document.getElementById('visitModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeVisitModal();
    }
});

function bookVisit() {
    const formData = new FormData(document.getElementById('visitForm'));
    
    fetch('services.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close visit modal
            closeVisitModal();
            
            // Show success modal
            document.getElementById('visitReference').textContent = data.visit_reference;
            document.getElementById('visitSuccessModal').style.display = 'flex';
            
            // Add animation class
            setTimeout(() => {
                document.getElementById('visitSuccessModal').classList.add('show');
            }, 10);
        } else {
            alert(data.message || 'Error booking visit');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error booking visit');
    });
}

// Emergency booking functions
function showEmergencyModal() {
    const modal = document.getElementById('emergencyModal');
    modal.style.display = 'flex';
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
    
    // Reset form if needed
    document.getElementById('emergencyForm').reset();
}

function closeEmergencyModal() {
    document.getElementById('emergencyModal').style.display = 'none';
    
    // Re-enable body scrolling
    document.body.style.overflow = '';
}

// Close modal if user clicks outside the modal content
document.getElementById('emergencyModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeEmergencyModal();
    }
});

function bookEmergency() {
    const formData = new FormData(document.getElementById('emergencyForm'));
    
    fetch('services.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close emergency modal
            closeEmergencyModal();
            
            // Show success modal
            document.getElementById('emergencyReference').textContent = data.emergency_reference;
            document.getElementById('emergencySuccessModal').style.display = 'flex';
            
            // Add animation class
            setTimeout(() => {
                document.getElementById('emergencySuccessModal').classList.add('show');
            }, 10);
        } else {
            alert(data.message || 'Error requesting emergency service');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error requesting emergency service');
    });
}
</script>