<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/trips.php');
}

try {
    $pdo = getPDOConnection();
    $trip_id = (int)$_POST['trip_id'];
    
    // Verify trip belongs to user and is confirmed
    $stmt = $pdo->prepare("
        SELECT trip_id FROM trips 
        WHERE trip_id = ? AND renter_id = ? AND trip_status = 'confirmed'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Trip not found or cannot be started');
    }
    
    // Update trip status to active
    $stmt = $pdo->prepare("
        UPDATE trips 
        SET trip_status = 'active', 
            updated_at = NOW() 
        WHERE trip_id = ?
    ");
    $stmt->execute([$trip_id]);
    
    $_SESSION['success_message'] = 'Trip started! Have a great ride!';
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

redirect(SITE_URL . '/guest/trips.php');
?>
