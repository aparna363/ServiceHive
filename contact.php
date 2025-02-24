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
    <title>Contact Us - ServiceHive</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<style>
    .container {
    background: white;
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    height: 100vh;
    display: flex;
    flex-direction: column;
}
.contact-hero {
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/contact.jpeg');
    background-size: cover;
    background-position: center;
    padding: 100px 0;
    text-align: center;
    color: white;
}

.contact-hero h1 {
    font-size: 48px;
    margin-bottom: 20px;
}

.contact-hero p {
    font-size: 18px;
    max-width: 600px;
    margin: 0 auto;
}

.contact-info-section {
    padding: 80px 0;
    background-color: #f9f9f9;
}

.contact-info-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    padding: 0 20px;
}

.contact-info-box {
    background: white;
    padding: 40px 30px;
    text-align: center;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.contact-info-box:hover {
    transform: translateY(-10px);
}

.contact-info-box i {
    font-size: 40px;
    color: #ee6e06;
    margin-bottom: 20px;
}

.contact-info-box h3 {
    font-size: 24px;
    margin-bottom: 15px;
    color: #333;
}

.contact-info-box p {
    color: #666;
    margin-bottom: 15px;
}

.contact-info-box a {
    color: #ee6e06;
    text-decoration: none;
    font-weight: bold;
}

.contact-form-section {
    padding: 80px 0;
}

.contact-form-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 50px;
    padding: 0 20px;
}

.form-content {
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-content h2 {
    font-size: 32px;
    margin-bottom: 30px;
    color: #333;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #ee6e06;
}

.form-group textarea {
    height: 150px;
    resize: vertical;
}

.submit-btn {
    background: #ee6e06;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s ease;
    width: 100%;
}

.submit-btn:hover {
    background: #d66305;
}

.map-container {
    border-radius: 10px;
    overflow: hidden;
    height: 100%;
}

.map-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.faq-section {
    padding: 80px 0;
    background-color: #f9f9f9;
}

.faq-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.faq-container h2 {
    text-align: center;
    font-size: 32px;
    margin-bottom: 50px;
    color: #333;
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
}

.faq-item {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.faq-item h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #333;
}

.faq-item p {
    color: #666;
    line-height: 1.6;
}

@media (max-width: 992px) {
    .contact-info-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .contact-form-container {
        grid-template-columns: 1fr;
    }
    
    .faq-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .contact-info-container {
        grid-template-columns: 1fr;
    }
    
    .contact-hero h1 {
        font-size: 36px;
    }
    
    .form-content {
        padding: 30px;
    }
}

/* Add these header styles from index.php */
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
    margin-right: -455px;
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
    margin-right: 40px;
    margin-left: 80px;
}

.user-menu {
    position: relative;
    margin-left: auto;
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

.user-icon svg {
    width: 24px;
    height: 24px;
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

.nav-links a.active {
    color: #ee6e06;
    position: relative;
}

.nav-links a.active::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 2px;
    background-color: #ee6e06;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: white;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    border-radius: 5px;
    padding: 8px 0;
}

.dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
}

.dropdown-content a:hover {
    background-color: #f1f1f1;
    color: #ee6e06;
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
        </nav>

        <div class="contact-hero">
            <h1>Get in Touch</h1>
            <p>We're here to help and answer any questions you might have</p>
        </div>

        <div class="contact-info-section">
            <div class="contact-info-container">
                <div class="contact-info-box">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Call Us</h3>
                    <p>Speak to our friendly team</p>
                    <a href="tel:+01123456789">(+01) 123 456 789</a>
                </div>
                
                <div class="contact-info-box">
                    <i class="fas fa-envelope"></i>
                    <h3>Email Us</h3>
                    <p>We'll respond within 24 hours</p>
                    <a href="mailto:info@servicehive.com">info@servicehive.com</a>
                </div>
                
                <div class="contact-info-box">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Visit Us</h3>
                    <p>Come say hello at our office</p>
                    <a href="#">Find our location</a>
                </div>
            </div>
        </div>

        <div class="contact-form-section">
            <div class="contact-form-container">
                <div class="form-content">
                    <h2>Send us a Message</h2>
                    <form action="process_contact.php" method="POST">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn">Send Message</button>
                    </form>
                </div>
                
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d387193.30596670663!2d-74.25987368715491!3d40.69714941932609!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c24fa5d33f083b%3A0xc80b8f06e177fe62!2sNew%20York%2C%20NY%2C%20USA!5e0!3m2!1sen!2s!4v1647286140401!5m2!1sen!2s" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
        </div>

        <div class="faq-section">
            <div class="faq-container">
                <h2>Frequently Asked Questions</h2>
                <div class="faq-grid">
                    <div class="faq-item">
                        <h3>How do I book a service?</h3>
                        <p>You can easily book a service through our website by selecting the desired service category and following the booking process. Alternatively, you can contact our customer service team for assistance.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>What are your working hours?</h3>
                        <p>Our customer service team is available Monday through Friday, 9:00 AM to 6:00 PM. However, services can be booked 24/7 through our website.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>How can I become a service provider?</h3>
                        <p>To become a service provider, click on the "Register as a professional" link and fill out the application form. Our team will review your application and get back to you.</p>
                    </div>
                    
                    <div class="faq-item">
                        <h3>What payment methods do you accept?</h3>
                        <p>We accept all major credit cards, debit cards, and digital payment methods. Payment can be made securely through our website or app.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');

        // Toggle dropdown when clicking the user menu button
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });

        // Services dropdown functionality
        const servicesLink = document.querySelector('a[href="#services"]');
        const dropdownContent = servicesLink.nextElementSibling;

        servicesLink.addEventListener('click', function(e) {
            e.preventDefault();
            dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
        });

        // Close services dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!servicesLink.contains(e.target) && !dropdownContent.contains(e.target)) {
                dropdownContent.style.display = 'none';
            }
        });

        // Active link underline
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-links a');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>
</html> 