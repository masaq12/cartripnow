<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/dashboard.php');
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $trip_id = (int)$_POST['trip_id'];
    
    // Get trip details
    $stmt = $pdo->prepare("
        SELECT t.*, v.owner_id, e.status as escrow_status
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN escrow e ON t.trip_id = e.trip_id
        WHERE t.trip_id = ?
    ");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        throw new Exception('Trip not found');
    }
    
    if ($trip['payment_status'] === 'completed') {
        throw new Exception('Payment already released');
    }
    
    if ($trip['escrow_status'] !== 'held') {
        throw new Exception('No funds in escrow for this trip');
    }
    
    // Update trip status
    $stmt = $pdo->prepare("
        UPDATE trips 
        SET trip_status = 'completed',
            payment_status = 'completed',
            updated_at = NOW()
        WHERE trip_id = ?
    ");
    $stmt->execute([$trip_id]);
    
    // Release escrow
    $stmt = $pdo->prepare("
        UPDATE escrow 
        SET status = 'released',
            released_at = NOW(),
            release_notes = 'Manual admin release'
        WHERE trip_id = ? AND status = 'held'
    ");
    $stmt->execute([$trip_id]);
    
    // Calculate owner earnings
    $platform_fee = $trip['platform_fee'];
    $owner_earnings = $trip['total_amount'] - $platform_fee;
    
    // Update renter balance - remove pending hold
    $stmt = $pdo->prepare("
        UPDATE renter_balances 
        SET pending_holds = pending_holds - ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$trip['total_amount'], $trip['renter_id']]);
    
    // Update owner balance - move from pending to available
    $stmt = $pdo->prepare("
        UPDATE owner_balances 
        SET available_balance = available_balance + ?,
            pending_balance = pending_balance - ?,
            total_earned = total_earned + ?,
            platform_fees_paid = platform_fees_paid + ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        $owner_earnings,
        $owner_earnings,
        $owner_earnings,
        $platform_fee,
        $trip['owner_id']
    ]);
    
    // Get owner balance for transaction
    $stmt = $pdo->prepare("SELECT available_balance FROM owner_balances WHERE user_id = ?");
    $stmt->execute([$trip['owner_id']]);
    $owner_new_balance = $stmt->fetchColumn();
    
    // Record transaction for owner
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id, transaction_type, amount, balance_before, balance_after,
            reference_type, reference_id, description
        ) VALUES (?, 'earning', ?, ?, ?, 'trip', ?, ?)
    ");
    $stmt->execute([
        $trip['owner_id'],
        $owner_earnings,
        $owner_new_balance - $owner_earnings,
        $owner_new_balance,
        $trip_id,
        'Earning from trip #' . $trip_id . ' (admin release)'
    ]);
    
    // Log admin action
    $stmt = $pdo->prepare("
        INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, description)
        VALUES (?, 'release_payment', 'trip', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $trip_id,
        'Manually released payment for trip #' . $trip_id
    ]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Payment released successfully. Owner balance has been updated.';
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

redirect($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/admin/trips.php');
?>
