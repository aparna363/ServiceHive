<?php
session_start();
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
                <div class="service-item">✓ Worship Place</div>
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