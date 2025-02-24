<?php
session_start();
require_once 'dbconnect.php';
$categories_query = "SELECT * FROM tbl_categories WHERE is_active = TRUE ORDER BY category_name";
$categories = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
</head>
<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
.nav-container {
    background: white;
    position: relative;
    z-index: 1000;
    max-width: 1600px;
    margin: 0 auto;
    height: 70px;
}
nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    background: white;
    position: relative;
    z-index: 1000;
    max-width: 1500px;
    margin: 0 auto;
}

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
    margin-right: 20px;
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

.nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
}

.user-menu {
    position: relative;
    margin-left: 0;
    padding-right: 20px;
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

.about-hero {
    position: relative;
    background: url('images/aboutus.jpg') no-repeat center center fixed;
    background-size: cover;
    height: 80vh; /* Reduced from 100vh */
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin-top: -80px; /* Reduced from -100px to match standard header height */
    padding-top: 80px; /* Reduced from 100px to match the margin-top */
}

.about-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4); /* Slightly lighter overlay */
}

.hero-content {
    position: relative;
    z-index: 2;
    padding: 20px;
    margin-top: 0; /* Removed negative margin */
}

.about-hero h1 {
    color: #ffffff;
    font-size: 4rem;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    margin: 0;
    padding: 20px;
    letter-spacing: 2px;
}

.about-section {
    padding: 80px 0;
}

.about-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    align-items: center;
}

.about-content h2 {
    color: #ee6e06;
    font-size: 2.5rem;
    margin-bottom: 30px;
}

.about-content p {
    color: #666;
    line-height: 1.8;
    margin-bottom: 20px;
}

.about-image {
    position: relative;
}

.about-image img {
    width: 100%;
    border-radius: 10px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-section {
    background-color: #f9f9f9;
    padding: 60px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    text-align: center;
}

.stat-item {
    padding: 20px;
}

.stat-number {
    font-size: 2.5rem;
    color: #ee6e06;
    font-weight: bold;
    margin-bottom: 10px;
}

.stat-label {
    color: #666;
    font-size: 1.1rem;
}

.team-section {
    padding: 80px 0;
    text-align: center;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.team-member {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.team-member:hover {
    transform: translateY(-10px);
}

.team-member img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin-bottom: 20px;
}

.team-member h3 {
    color: #333;
    margin-bottom: 10px;
}

.team-member p {
    color: #666;
    margin-bottom: 15px;
}

.social-links {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.social-links a {
    color: #ee6e06;
    transition: color 0.3s ease;
}

.social-links a:hover {
    color: #bc4f07;
}

@media (max-width: 768px) {
    .about-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.mission-vision-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 50px;
}

.mission-box {
    background: white;
    padding: 40px 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    text-align: left;
}

.mission-box:hover {
    transform: translateY(-10px);
}

.icon-container {
    width: 70px;
    height: 70px;
    background: #ee6e06;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
}

.icon-container i {
    font-size: 30px;
    color: white;
}

.mission-box h2 {
    color: #333;
    font-size: 24px;
    margin-bottom: 20px;
}

.mission-box p {
    color: #666;
    line-height: 1.8;
    margin-bottom: 15px;
}

.mission-box ul {
    list-style: none;
    padding: 0;
}

.mission-box ul li {
    color: #666;
    margin-bottom: 10px;
    font-size: 16px;
}

@media (max-width: 992px) {
    .mission-vision-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .mission-vision-grid {
        grid-template-columns: 1fr;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .about-hero {
        height: 80vh;
        margin-top: -80px; /* Adjust for smaller screens */
        padding-top: 80px;
    }
    
    .about-hero h1 {
        font-size: 2.5rem;
    }
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    background: white;
    position: relative;
    z-index: 1000;
    max-width: 1500px;
    margin: 0 auto;
}

.logo img {
    height: 60px;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
}

.nav-links a {
    color: #333;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s;
}

.nav-links a:hover,
.nav-links a.active {
    color: #ee6e06;
}

.dropdown {
    position: relative;
}

.dropdown-content {
    display: none;
    position: absolute;
    background: white;
    min-width: 200px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px 0;
    z-index: 1;
}

.dropdown:hover .dropdown-content {
    display: block;
}

.dropdown-content a {
    color: #333;
    padding: 12px 20px;
    text-decoration: none;
    display: block;
    transition: background 0.3s;
}

.dropdown-content a:hover {
    background: #f8f9fa;
    color: #ee6e06;
}

.search-container {
    display: flex;
    align-items: center;
    margin-right: 20px;
}

.search-bar {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border-radius: 25px;
    padding: 5px 15px;
}

.search-bar input {
    border: none;
    background: none;
    padding: 8px;
    width: 200px;
    outline: none;
}

.search-bar button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #666;
}

.search-results {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-top: 5px;
}

.user-menu {
    position: relative;
    margin-left: 0;
    padding-right: 20px;
}

.user-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-avatar {
    width: 35px;
    height: 35px;
    background: #ee6e06;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-icon svg {
    width: 24px;
    height: 24px;
    color: #333;
}

.user-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    min-width: 180px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px 0;
    z-index: 1000;
}

.user-dropdown.active {
    display: block !important;
}

.user-dropdown a {
    color: #333;
    padding: 12px 20px;
    text-decoration: none;
    display: block;
    transition: background 0.3s;
}

.user-dropdown a:hover {
    background: #f8f9fa;
    color: #ee6e06;
}

.divider {
    height: 1px;
    background: #eee;
    margin: 8px 0;
}

@media (max-width: 992px) {
    .nav-links {
        gap: 20px;
    }
    
    .search-bar input {
        width: 150px;
    }
}

@media (max-width: 768px) {
    nav {
        flex-wrap: wrap;
    }
    
    .nav-links {
        order: 3;
        width: 100%;
        flex-direction: column;
        gap: 15px;
        margin-top: 15px;
        display: none;
    }
    
    .nav-links.active {
        display: flex;
    }
    
    .dropdown-content {
        position: static;
        box-shadow: none;
        padding-left: 20px;
    }
    
    .search-container {
        width: 100%;
        margin-top: 15px;
    }
    
    .search-bar {
        width: 100%;
    }
    
    .search-bar input {
        width: 100%;
    }
}
</style>
<body>
    
        <nav class="nav-container">
            <div class="logo">
                <a href="index.php"><img src="images/logo2.png" alt="ServiceHive Logo"></a>
            </div>
            <div class="nav-links">
                <a href="index.php" class="dropdown-indicator">Home</a>
                <a href="aboutus.php" class="active">About Us</a>
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
        </nav>
    

    <div class="about-hero">
        <div class="hero-content">
            <h1>About ServiceHive</h1>
        </div>
    </div>

    <section class="about-section">
        <div class="about-container">
            <div class="about-grid">
                <div class="about-content">
                    <h2>Your Trusted Service Partner</h2>
                    <p>ServiceHive was founded with a simple mission: to connect skilled professionals with customers who need quality home services. We believe in making home maintenance and improvement hassle-free and accessible to everyone.</p>
                    <p>Our platform carefully vets all service providers to ensure the highest standards of professionalism and expertise. We're committed to providing transparent pricing, reliable service, and complete customer satisfaction.</p>
                </div>
                <div class="about-image">
                        <img src="images/about.jpg" alt="ServiceHive Team">
                    </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="about-container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">5000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">1000+</div>
                    <div class="stat-label">Service Providers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Service Categories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">4.8</div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
        </div>
    </section>

    <section class="team-section">
        <div class="about-container">
            <div class="mission-vision-grid">
                <div class="mission-box">
                    
                    
                    <h2>Our Mission</h2>
                    <p>To revolutionize the home services industry by providing a seamless, reliable platform that connects skilled professionals with customers, ensuring quality service delivery and customer satisfaction while empowering service providers to grow their businesses.</p>
                </div>
                <div class="mission-box">
                   
                        
                   
                    <h2>Our Vision</h2>
                    <p>To become the most trusted and preferred platform for home services globally, creating positive impacts in communities by setting new standards for service excellence, technological innovation, and professional growth opportunities.</p>
                </div>
                <div class="mission-box">
                  
                    <h2>Our Values</h2>
                    <ul>
                        <li>✓ Trust & Transparency</li>
                        <li>✓ Quality Excellence</li>
                        <li>✓ Customer First</li>
                        <li>✓ Professional Growth</li>
                        <li>✓ Community Impact</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="#">UC reviews</a></li>
                        <li><a href="#">Categories near you</a></li>
                        <li><a href="#">Contact us</a></li>
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
