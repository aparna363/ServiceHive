// -------------------------header------------------------------------------------------------------

window.addEventListener('scroll', () => {
    const nav = document.querySelector('nav');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}


//------------------------------------------------------------------------------------------------------------


// <!-- ------dot----- -->

    document.addEventListener('DOMContentLoaded', function() {
        const track = document.querySelector('.carousel-track');
        const slides = Array.from(document.querySelectorAll('.carousel-slide'));
        const indicators = document.querySelectorAll('.indicator');
        const slideWidth = slides[0].getBoundingClientRect().width;
    
        // Update slide position when indicator is clicked
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                // Calculate the new position
                const targetPosition = -slideWidth * index * 3; // Show 3 slides at a time
                
                // Move the track
                track.style.transform = `translateX(${targetPosition}px)`;
                
                // Update active indicator
                document.querySelector('.indicator.active').classList.remove('active');
                indicator.classList.add('active');
            });
        });
    });


   



//------------------------------------------------------------------------------------------------------


// -----icon---------


    document.addEventListener('DOMContentLoaded', function() {
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');

        // Toggle dropdown when clicking the user icon
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target) && !userMenuButton.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    });
  
//------------------image (services)animation ------------------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', function() {
    const track = document.querySelector('.carousel-track');
    const slides = Array.from(document.querySelectorAll('.carousel-slide'));
    
    // Clone first few slides and append to end for seamless loop
    const slidesToClone = slides.slice(0, 3);
    slidesToClone.forEach(slide => {
        const clone = slide.cloneNode(true);
        track.appendChild(clone);
    });

    let currentIndex = 0;
    const slideWidth = slides[0].getBoundingClientRect().width + 16; // Including gap
    const totalSlides = slides.length;
    
    function moveCarousel() {
        currentIndex++;
        const position = -slideWidth * currentIndex;
        track.style.transform = `translateX(${position}px)`;
        
        // Reset to start when reaching the cloned slides
        if (currentIndex >= totalSlides) {
            setTimeout(() => {
                // Remove transition for instant reset
                track.style.transition = 'none';
                currentIndex = 0;
                track.style.transform = `translateX(0)`;
                // Restore transition after reset
                setTimeout(() => {
                    track.style.transition = 'transform 0.8s ease-in-out';
                }, 50);
            }, 800); // Wait for transition to complete
        }
    }
    
    // Start the automatic movement
    setInterval(moveCarousel, 3000);
});


//-------------------------------------------------------------------------------------------------------------


document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide2');
    const dotsContainer = document.querySelector('.slider-dots');
    let currentSlide = 0;
    
    // Create dots
    slides.forEach((_, index) => {
      const dot = document.createElement('div');
      dot.classList.add('dot');
      if (index === 0) dot.classList.add('active');
      dot.addEventListener('click', () => goToSlide(index));
      dotsContainer.appendChild(dot);
    });

    const dots = document.querySelectorAll('.dot');

    function goToSlide(n) {
      // Check if slides exist before trying to access them
      const slides = document.getElementsByClassName("hero-slide");
      if (!slides || slides.length === 0) {
          return; // Exit if no slides found
      }
      
      slides[currentSlide].classList.remove('active2');
      dots[currentSlide].classList.remove('active');
      
      currentSlide = n;
      
      if (currentSlide >= slides.length) currentSlide = 0;
      if (currentSlide < 0) currentSlide = slides.length - 1;
      
      slides[currentSlide].classList.add('active2');
      dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
      // Check if slides exist before proceeding
      const slides = document.getElementsByClassName("hero-slide");
      if (!slides || slides.length === 0) {
          return; // Exit if no slides found
      }
      
      goToSlide(currentSlide + 1);
    }

    // Auto advance slides every 3 seconds
    setInterval(nextSlide, 3000);
  });


  

//----------------------main image----------------------------------------------------------------------------------

// First, ensure your HTML structure is circular by cloning the first slide
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide');
    const sliderNav = document.querySelector('.slider-nav');
    const prevButton = document.querySelector('.slider-prev');
    const nextButton = document.querySelector('.slider-next');
    let currentSlide = 0;
    let slideInterval;
    let isTransitioning = false;  // Add this flag to prevent multiple transitions
    
    // Create navigation dots
    slides.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.classList.add('slider-dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(index));
        sliderNav.appendChild(dot);
    });
    
    function updateSlides() {
        if (isTransitioning) return;  // Prevent multiple transitions
        isTransitioning = true;
        
        slides.forEach((slide, index) => {
            slide.classList.remove('active');
            document.querySelectorAll('.slider-dot')[index].classList.remove('active');
        });
        
        slides[currentSlide].classList.add('active');
        document.querySelectorAll('.slider-dot')[currentSlide].classList.add('active');
        
        // Reset transition flag after animation completes
        setTimeout(() => {
            isTransitioning = false;
        }, 500); // Match this with your CSS transition duration
    }
    
    function nextSlide() {
        // Check if slides exist before proceeding
        const slides = document.getElementsByClassName("hero-slide");
        if (!slides || slides.length === 0) {
            return; // Exit if no slides found
        }
        
        if (isTransitioning) return;
        currentSlide = (currentSlide + 1) % slides.length;
        if (currentSlide === slides.length - 1) {
            // If we're on the last slide, queue up return to first slide
            setTimeout(() => {
                currentSlide = 0;
                updateSlides();
            }, 2000); // Match your slide duration
        }
        updateSlides();
    }
    
    function prevSlide() {
        if (isTransitioning) return;
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
        updateSlides();
    }
    
    function goToSlide(index) {
        if (isTransitioning) return;
        currentSlide = index;
        updateSlides();
        resetInterval();
    }
    
    function resetInterval() {
        clearInterval(slideInterval);
        slideInterval = setInterval(nextSlide, 2000);
    }
    
    // Event listeners
    prevButton.addEventListener('click', () => {
        if (!isTransitioning) {
            prevSlide();
            resetInterval();
        }
    });
    
    nextButton.addEventListener('click', () => {
        if (!isTransitioning) {
            nextSlide();
            resetInterval();
        }
    });
    
    // Start automatic slideshow
    slideInterval = setInterval(nextSlide, 2000);
    
    // Pause on hover
    const slider = document.querySelector('.slider');
    slider.addEventListener('mouseenter', () => clearInterval(slideInterval));
    slider.addEventListener('mouseleave', () => {
        slideInterval = setInterval(nextSlide, 2000);
    });
});

//---------------------------search functionality--------------------------------------------------------------------------------

// Function to create a service card
function createResultCard(service) {
    return `
        <div class="search-result-card">
            <div class="service-info">
                <h4>${service.service_name}</h4>
                <div class="service-meta">
                    <span>${service.category_name}</span>
                    ${service.avg_rating > 0 ? 
                        `<span class="rating">★ ${parseFloat(service.avg_rating).toFixed(1)}</span>` 
                        : ''}
                </div>
            </div>
            <a href="service-details.php?service_id=${service.service_id}" class="book-now">Book</a>
        </div>
    `;
}

// Function to show popular searches when no results are found
function showPopularSearches(query) {
    return `
        <div class="no-results">
            <p>No results found for "${query}"</p>
            <div class="popular-searches">
                <h3>Popular Searches</h3>
                <div class="popular-tags">
                    <a href="#" onclick="performSearch('Cleaning'); return false;">Cleaning</a>
                    <a href="#" onclick="performSearch('Plumbing'); return false;">Plumbing</a>
                    <a href="#" onclick="performSearch('Electrical'); return false;">Electrical</a>
                    <a href="#" onclick="performSearch('Painting'); return false;">Painting</a>
                </div>
            </div>
        </div>
    `;
}

// Function to perform search
function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    
    // Show loading indicator
    searchResults.innerHTML = '<div class="loading">Searching...</div>';
    searchResults.style.display = 'block';

    // Make AJAX request to search.php (not search_handler.php)
    fetch(`search.php?query=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    // Log the raw response for debugging
                    console.log('Raw server response:', text);
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse server response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Search failed');
            }

            let resultsHtml = '';

            // Handle services
            if (data.results && data.results.services && data.results.services.length > 0) {
                resultsHtml += '<div class="service-results">';
                resultsHtml += '<h3>Services</h3>';
                data.results.services.forEach(service => {
                    resultsHtml += createResultCard(service);
                });
                resultsHtml += '</div>';
            } else {
                resultsHtml = showPopularSearches(query);
            }

            searchResults.innerHTML = resultsHtml;
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = `
                <div class="error">
                    <p>An error occurred while searching. Please try again.</p>
                    <p class="error-details">${error.message}</p>
                </div>
            `;
        });
}

// Add event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const searchResults = document.getElementById('searchResults');

    // Handle search button click
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        performSearch(query);
    });

    // Search on input after delay (debounce)
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(() => performSearch(query), 500);
    });

    // Search on Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            performSearch(query);
        }
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            searchResults.style.display = 'none';
        }
    });
});



document.addEventListener('DOMContentLoaded', function() {
    // Existing dropdown code
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const dropdownIndicator = dropdown.querySelector('.dropdown-indicator');
        const dropdownContent = dropdown.querySelector('.dropdown-content');
        
        // Toggle dropdown on click
        dropdownIndicator.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
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
                const dropdownContent = dropdown.querySelector('.dropdown-content');
                if (dropdownContent) {
                    dropdownContent.style.display = 'none';
                }
            });
        }
    });
    
    // Add active class to current page link code...
    
    // New code: Add hover bottom line animation to nav links
    const navLinks = document.querySelectorAll('.nav-links a');
    
    navLinks.forEach(link => {
        // Create and append the line element
        const line = document.createElement('span');
        line.classList.add('hover-line');
        link.appendChild(line);
        
        // Add event listeners for hover animation
        link.addEventListener('mouseenter', function() {
            line.style.width = '100%';
        });
        
        link.addEventListener('mouseleave', function() {
            line.style.width = '0';
        });
    });
});

let selectedMethod = null;

function toggleSection(sectionId) {
    const sections = document.querySelectorAll('.payment-options');
    const chevrons = document.querySelectorAll('.chevron');
    
    // Close all other sections first
    sections.forEach(section => {
        if (section.id !== sectionId && !section.classList.contains('collapsed')) {
            section.classList.add('collapsed');
            section.previousElementSibling.querySelector('.chevron').classList.remove('up');
        }
    });

    // Toggle the clicked section
    const section = document.getElementById(sectionId);
    const chevron = section.previousElementSibling.querySelector('.chevron');
    section.classList.toggle('collapsed');
    chevron.classList.toggle('up');
}

function selectPayment(method, element) {
    selectedMethod = method;
    
    // Remove selection from all options
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
        option.querySelector('.choose-text').textContent = 'Choose';
    });
    
    // Add selection to clicked option
    element.classList.add('selected');
    element.querySelector('.choose-text').textContent = 'Selected';
    
    // Enable continue button
    document.querySelector('.continue-btn').removeAttribute('disabled');
}

function processPayment() {
    if (!selectedMethod) {
        alert('Please select a payment method');
        return;
    }
    initializeRazorpay(selectedMethod);
}

// Slider functionality with robust error handling
let slideIndex = 0;
let slideInterval;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSlider();
});

function initializeSlider() {
    const slidesContainer = document.querySelector('.hero-slider');
    
    // Only initialize if slider exists on the page
    if (!slidesContainer) {
        console.log('No slider found on this page');
        return;
    }
    
    const slides = document.querySelectorAll('.hero-slide');
    
    // Check if slides exist
    if (!slides || slides.length === 0) {
        console.error('No slides found in the slider');
        return;
    }
    
    console.log(`Slider initialized with ${slides.length} slides`);
    
    // Set initial slide
    goToSlide(slideIndex);
    
    // Set up auto-rotation if more than one slide
    if (slides.length > 1) {
        // Clear any existing intervals first
        if (slideInterval) {
            clearInterval(slideInterval);
        }
        slideInterval = setInterval(nextSlide, 5000);
    }
    
    // Set up manual navigation if it exists
    const prevButton = document.querySelector('.prev-slide');
    const nextButton = document.querySelector('.next-slide');
    
    if (prevButton) {
        prevButton.addEventListener('click', function() {
            prevSlide();
        });
    }
    
    if (nextButton) {
        nextButton.addEventListener('click', function() {
            nextSlide();
        });
    }
    
    // Set up dot navigation if it exists
    const dots = document.querySelectorAll('.dot');
    if (dots && dots.length > 0) {
        dots.forEach((dot, index) => {
            dot.addEventListener('click', function() {
                goToSlide(index);
            });
        });
    }
}

function goToSlide(n) {
    // Get all slides and dots safely
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.dot');
    
    // Safety check - if no slides, exit function
    if (!slides || slides.length === 0) {
        console.error('No slides found when trying to go to slide');
        return;
    }
    
    // Reset slideIndex if out of bounds
    if (n >= slides.length) {
        slideIndex = 0;
    } else if (n < 0) {
        slideIndex = slides.length - 1;
    } else {
        slideIndex = n;
    }
    
    console.log(`Going to slide ${slideIndex} of ${slides.length}`);
    
    // Hide all slides first
    slides.forEach(slide => {
        if (slide && slide.classList) {
            slide.classList.remove('active');
        }
    });
    
    // Remove active from all dots
    if (dots && dots.length > 0) {
        dots.forEach(dot => {
            if (dot && dot.classList) {
                dot.classList.remove('active');
            }
        });
    }
    
    // Safety checks before accessing elements
    if (slides[slideIndex] && slides[slideIndex].classList) {
        slides[slideIndex].classList.add('active');
    } else {
        console.error(`Cannot access slide at index ${slideIndex}`);
    }
    
    if (dots && dots.length > 0 && dots[slideIndex] && dots[slideIndex].classList) {
        dots[slideIndex].classList.add('active');
    }
}

function nextSlide() {
    const slides = document.querySelectorAll('.hero-slide');
    if (!slides || slides.length === 0) {
        console.error('No slides found when trying to go to next slide');
        return;
    }
    
    goToSlide(slideIndex + 1);
}

function prevSlide() {
    const slides = document.querySelectorAll('.hero-slide');
    if (!slides || slides.length === 0) {
        console.error('No slides found when trying to go to previous slide');
        return;
    }
    
    goToSlide(slideIndex - 1);
}

// Add this to your script.js file to restore toggle button functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });
    }
    
    // Any other toggle buttons on the page
    const toggleButtons = document.querySelectorAll('[data-toggle]');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-toggle');
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                if (targetElement.style.display === 'none' || targetElement.style.display === '') {
                    targetElement.style.display = 'block';
                } else {
                    targetElement.style.display = 'none';
                }
                
                // Toggle active class on the button
                this.classList.toggle('active');
            }
        });
    });
    
    // Floating action buttons toggle
    const fabToggle = document.querySelector('.fab-toggle');
    const fabActions = document.querySelector('.fab-actions');
    
    if (fabToggle && fabActions) {
        fabToggle.addEventListener('click', function() {
            fabActions.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
});