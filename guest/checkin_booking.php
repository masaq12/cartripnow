<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isGuest() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/bookings.php');
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $trip_id = (int)$_POST['trip_id'];
    $renter_id = $_SESSION['user_id'];
    
    // Verify booking belongs to guest and is eligible for check-in
    $stmt = $pdo->prepare("
        SELECT b.* 
        FROM bookings b
        WHERE b.trip_id = ? 
        AND b.renter_id = ? 
        AND b.trip_status = 'confirmed'
        AND b.pickup_date <= CURDATE()
    ");
    $stmt->execute([$trip_id, $renter_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception('Invalid booking or check-in not available yet');
    }
    
    // Update booking status to checked_in
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET trip_status = 'checked_in', 
            updated_at = NOW()
        WHERE trip_id = ?
    ");
    $stmt->execute([$trip_id]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Check-in successful! Enjoy your stay.';
    redirect(SITE_URL . '/guest/bookings.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    redirect(SITE_URL . '/guest/bookings.php');
}
?>
