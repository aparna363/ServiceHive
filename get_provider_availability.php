<?php
// get_provider_availability.php
session_start();
include 'dbconnect.php';

// Only process POST requests with JSON content
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!$data || !isset($data['provider_id']) || !isset($data['month']) || !isset($data['year'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $provider_id = intval($data['provider_id']);
    $month = intval($data['month']);
    $year = intval($data['year']);
    
    // Validate input
    if ($provider_id <= 0 || $month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
        exit;
    }
    
    try {
        // Get provider's availability settings
        $query = "SELECT availability FROM service_providers WHERE provider_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Provider not found'
            ]);
            exit;
        }
        
        $row = $result->fetch_assoc();
        $availability_json = $row['availability'];
        
        // Parse JSON availability data (with fallback to default if not set)
        $availability_settings = json_decode($availability_json, true);
        if (!$availability_settings) {
            // Default availability settings
            $availability_settings = [
                'working_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'working_hours' => [
                    'start' => '09:00',
                    'end' => '17:00'
                ],
                'max_bookings_per_day' => 8,
                'unavailable_dates' => [],
                'service_duration' => 60 // Default 60 minutes per service
            ];
        }
        
        // Get the first and last day of the requested month
        $first_day = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // Get existing bookings for this provider in the requested month
        $query = "SELECT booking_date, time_slot, s.estimated_duration 
                 FROM bookings b
                 JOIN tbl_services s ON b.service_id = s.service_id
                 WHERE b.provider_id = ? 
                 AND b.booking_date BETWEEN ? AND ? 
                 AND b.status != 'cancelled'";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $provider_id, $first_day, $last_day);
        $stmt->execute();
        $bookings_result = $stmt->get_result();
        
        // Create a structure to hold booking information by date
        $bookings_by_date = [];
        
        while ($booking = $bookings_result->fetch_assoc()) {
            $date = $booking['booking_date'];
            $time = substr($booking['time_slot'], 0, 5); // HH:MM format
            $duration = intval($booking['estimated_duration'] ?? 60); // Duration in minutes
            
            if (!isset($bookings_by_date[$date])) {
                $bookings_by_date[$date] = [
                    'count' => 0,
                    'booked_slots' => [],
                    'total_minutes' => 0
                ];
            }
            
            $bookings_by_date[$date]['count']++;
            $bookings_by_date[$date]['booked_slots'][] = $time;
            $bookings_by_date[$date]['total_minutes'] += $duration;
        }
        
        // Generate availability data for each day of the month
        $availability_data = [];
        $current_date = new DateTime($first_day);
        $end_date = new DateTime($last_day);
        $end_date->modify('+1 day'); // Include the last day
        
        $daily_limit_minutes = 8 * 60; // Default: 8 hours of work per day
        
        while ($current_date < $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_of_week = intval($current_date->format('N')); // 1 (Monday) to 7 (Sunday)
            
            // Check if this is a working day
            $is_working_day = in_array($day_of_week, $availability_settings['working_days']);
            
            // Check if this date is specifically marked as unavailable
            $is_unavailable_date = in_array($date_str, $availability_settings['unavailable_dates'] ?? []);
            
            // Get booking information for this date
            $bookings = $bookings_by_date[$date_str] ?? [
                'count' => 0,
                'booked_slots' => [],
                'total_minutes' => 0
            ];
            
            // Determine availability status
            if ($is_unavailable_date) {
                $status = 'unavailable';
                $custom_message = 'Provider is unavailable on this date';
            } elseif (!$is_working_day) {
                $status = 'unavailable';
                $custom_message = 'Not a working day';
            } else {
                // Calculate remaining capacity based on booked time vs available time
                $booked_minutes = $bookings['total_minutes'];
                $remaining_minutes = $daily_limit_minutes - $booked_minutes;
                
                if ($remaining_minutes <= 0) {
                    $status = 'unavailable';
                    $custom_message = 'Fully booked';
                } elseif ($remaining_minutes < 120) { // Less than 2 hours remaining
                    $status = 'busy';
                    $custom_message = 'Limited availability';
                } else {
                    $status = 'available';
                    $custom_message = 'Available for booking';
                }
            }
            
            // Add to availability data
            $availability_data[$date_str] = [
                'status' => $status,
                'bookings' => $bookings['count'],
                'booked_slots' => $bookings['booked_slots'],
                'custom_message' => $custom_message,
                'working_hours' => $availability_settings['working_hours'],
                'remaining_minutes' => $remaining_minutes ?? 0
            ];
            
            // Move to next day
            $current_date->modify('+1 day');
        }
        
        // Return the availability data
        echo json_encode([
            'success' => true,
            'availability' => $availability_data
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving availability: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 