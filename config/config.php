<?php
// Car Trip Now - Site Configuration
define('SITE_NAME', 'Car Trip Now');
define('SITE_URL', 'http://localhost/car_trip_now');
define('BASE_PATH', dirname(__DIR__));

// Upload directories
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('VEHICLE_PHOTOS_PATH', UPLOAD_PATH . 'vehicles/');
define('DAMAGE_PHOTOS_PATH', UPLOAD_PATH . 'damages/');
define('DOCUMENT_PHOTOS_PATH', UPLOAD_PATH . 'documents/');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Platform settings (can be overridden from database)
define('PLATFORM_FEE_PERCENT', 15.00);
define('INSURANCE_FEE_PERCENT', 10.00);
define('MIN_PAYOUT_AMOUNT', 50.00);
define('CURRENCY_SYMBOL', '$');
define('LATE_RETURN_FEE_PER_HOUR', 25.00);
define('DEFAULT_SECURITY_DEPOSIT', 500.00);
define('MAX_TRIP_DURATION_DAYS', 30);
define('MIN_DRIVER_AGE', 25);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to get user type
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Helper function to check if admin
function isAdmin() {
    return isLoggedIn() && getUserType() === 'admin';
}

// Helper function to check if owner (vehicle owner)
function isOwner() {
    return isLoggedIn() && getUserType() === 'owner';
}

// Helper function to check if renter
function isRenter() {
    return isLoggedIn() && getUserType() === 'renter';
}

// Legacy aliases for backward compatibility
function isHost() {
    return isOwner();
}

function isGuest() {
    return isRenter();
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to format currency
function formatCurrency($amount) {
    // Handle null or non-numeric values
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $amount = 0;
    }
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

// Helper function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to generate random string
function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

// Helper function to calculate trip cost
function calculateTripCost($daily_rate, $days, $insurance_fee = 0, $extra_mileage_fee = 0, $late_fee = 0) {
    $subtotal = $daily_rate * $days;
    $platform_fee = ($subtotal * PLATFORM_FEE_PERCENT) / 100;
    $total = $subtotal + $insurance_fee + $platform_fee + $extra_mileage_fee + $late_fee;
    
    return [
        'subtotal' => $subtotal,
        'platform_fee' => $platform_fee,
        'insurance_fee' => $insurance_fee,
        'extra_mileage_fee' => $extra_mileage_fee,
        'late_fee' => $late_fee,
        'total' => $total
    ];
}

// Helper function to format date
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

// Helper function to format time
function formatTime($time, $format = 'g:i A') {
    if (empty($time)) return 'N/A';
    return date($format, strtotime($time));
}

// Helper function to calculate days between dates
function calculateDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $diff = $start->diff($end);
    return max(1, $diff->days); // Minimum 1 day
}

// Helper function to verify driver age
function verifyDriverAge($birthdate, $min_age = MIN_DRIVER_AGE) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $birth->diff($today)->y;
    return $age >= $min_age;
}
?>
