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
      slides[currentSlide].classList.remove('active2');
      dots[currentSlide].classList.remove('active');
      
      currentSlide = n;
      
      if (currentSlide >= slides.length) currentSlide = 0;
      if (currentSlide < 0) currentSlide = slides.length - 1;
      
      slides[currentSlide].classList.add('active2');
      dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
      goToSlide(currentSlide + 1);
    }

    // Auto advance slides every 3 seconds
    setInterval(nextSlide, 3000);
  });


  //-----------------------------------------------------------------------------------------------

 
//   const slides = document.querySelectorAll('.slide');
//   let currentIndex = 0;

//   function showNextSlide() {
//       slides[currentIndex].classList.remove('active');
//       currentIndex = (currentIndex + 1) % slides.length;
//       slides[currentIndex].classList.add('active');
//   }

//   setInterval(showNextSlide, 2000);

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



// Add this to your script.js
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    function createResultCard(service) {
        return `
            <div class="search-result-card">
                <div class="service-info">
                    <h4>${service.name}</h4>
                    <div class="service-meta">
                        <span class="category">${service.category}</span>
                        <span class="rating">★ ${service.rating}</span>
                        <span class="price">${service.price}</span>
                    </div>
                </div>
                <button class="book-now">Book Now</button>
            </div>
        `;
    }

    function showPopularSearches(searches) {
        return `
            <div class="popular-searches">
                <h3>Popular Searches</h3>
                <div class="popular-tags">
                    ${searches.map(search => `
                        <span class="popular-tag">${search}</span>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function performSearch() {
        const term = searchInput.value;
        
        if (term.length < 2) {
            fetch('search_handler.php')
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = showPopularSearches(data.popular_searches);
                    searchResults.style.display = 'block';
                });
            return;
        }

        fetch(`search_handler.php?term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                if (data.results.length > 0) {
                    html = data.results.map(service => createResultCard(service)).join('');
                } else {
                    html = `<div class="no-results">No services found for "${term}"</div>`;
                }
                searchResults.innerHTML = html;
                searchResults.style.display = 'block';
            });
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchResults.contains(e.target) && !searchInput.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
});



document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        if (searchTerm.length > 0) {
            // Using the PHP class through AJAX
            fetch(`search.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.noResults) {
                        searchResults.innerHTML = '<div class="no-results">No services found</div>';
                    } else {
                        const resultsHtml = data.results.map(result => `
                            <div class="search-result-card">
                                <div class="service-info">
                                    <h4>${result}</h4>
                                </div>
                                <button class="book-now">Book Now</button>
                            </div>
                        `).join('');
                        searchResults.innerHTML = resultsHtml;
                    }
                    searchResults.style.display = 'block';
                });
        } else {
            searchResults.style.display = 'none';
        }
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
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