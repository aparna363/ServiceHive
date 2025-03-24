/**
 * Razorpay integration helper functions
 */

// Initialize Razorpay checkout
function initRazorpay(options) {
    var rzp = new Razorpay(options);
    
    // Handle payment failures
    rzp.on('payment.failed', function (response) {
        console.error('Payment failed:', response.error);
        
        // Redirect to payment failed page
        window.location.href = 'payment_failed.php?booking_id=' + options.booking_id + 
                              '&error=' + encodeURIComponent(response.error.description);
    });
    
    return rzp;
}

// Open Razorpay checkout modal
function openRazorpayCheckout(rzp) {
    rzp.open();
}

// Format price for display
function formatPrice(price) {
    return '₹' + parseFloat(price).toFixed(2);
}

// Convert price to paise (for Razorpay)
function toPaise(price) {
    return Math.round(parseFloat(price) * 100);
} 