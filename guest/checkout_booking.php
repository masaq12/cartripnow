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
    
    // Verify booking belongs to guest and is eligible for checkout
    $stmt = $pdo->prepare("
        SELECT b.*, l.owner_id 
        FROM trips b
        JOIN vehicles l ON b.vehicle_id = l.vehicle_id
        WHERE b.trip_id = ? 
        AND b.renter_id = ? 
        AND b.trip_status = 'checked_in'
        AND b.return_date <= CURDATE()
    ");
    $stmt->execute([$trip_id, $renter_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception('Invalid booking or checkout not available yet');
    }
    
    // Update booking status
    $stmt = $pdo->prepare("
        UPDATE trips 
        SET trip_status = 'completed', 
            checked_out_at = NOW(),
            updated_at = NOW()
        WHERE trip_id = ?
    ");
    $stmt->execute([$trip_id]);
    
    // Release payment from escrow
    $stmt = $pdo->prepare("
        UPDATE escrow 
        SET status = 'released', 
            released_at = NOW(),
            release_notes = 'Guest checkout'
        WHERE trip_id = ? AND status = 'held'
    ");
    $stmt->execute([$trip_id]);
    
    // Calculate host earnings (total - service fee)
    $platform_fee = $booking['service_fee'];
    $host_earnings = $booking['total_amount'] - $platform_fee;
    
    // Update guest balance - remove pending hold
    $stmt = $pdo->prepare("
        UPDATE renter_balances 
        SET pending_holds = pending_holds - ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$booking['total_amount'], $renter_id]);
    
    // Update host balance - move from pending to available
    $stmt = $pdo->prepare("
        UPDATE host_balances 
        SET available_balance = available_balance + ?,
            pending_balance = pending_balance - ?,
            total_earned = total_earned + ?,
            platform_fees_paid = platform_fees_paid + ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        $host_earnings,
        $host_earnings,
        $host_earnings,
        $platform_fee,
        $booking['owner_id']
    ]);
    
    // Get host balance for transaction
    $stmt = $pdo->prepare("SELECT available_balance FROM host_balances WHERE user_id = ?");
    $stmt->execute([$booking['owner_id']]);
    $host_new_balance = $stmt->fetchColumn();
    
    // Record transaction for host
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id, transaction_type, amount, balance_before, balance_after, 
            reference_type, reference_id, description
        ) VALUES (?, 'earning', ?, ?, ?, 'booking', ?, ?)
    ");
    $stmt->execute([
        $booking['owner_id'],
        $host_earnings,
        $host_new_balance - $host_earnings,
        $host_new_balance,
        $trip_id,
        'Earning from booking #' . $trip_id . ' (guest checkout)'
    ]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Checkout successful! Thank you for staying with us. Payment has been released to the host.';
    redirect(SITE_URL . '/guest/bookings.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    redirect(SITE_URL . '/guest/bookings.php');
}
?>
