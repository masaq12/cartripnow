<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/trips.php');
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $trip_id = (int)$_POST['trip_id'];
    
    // Get trip details
    $stmt = $pdo->prepare("
        SELECT t.*, v.owner_id, e.status as escrow_status, e.total_amount as escrow_amount
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN escrow e ON t.trip_id = e.trip_id
        WHERE t.trip_id = ? AND t.renter_id = ?
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        throw new Exception('Trip not found');
    }
    
    if ($trip['trip_status'] === 'completed') {
        throw new Exception('Trip already completed');
    }
    
    if ($trip['trip_status'] !== 'active') {
        throw new Exception('Trip must be active to complete');
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
    if ($trip['escrow_status'] === 'held') {
        $stmt = $pdo->prepare("
            UPDATE escrow 
            SET status = 'released',
                released_at = NOW(),
                release_notes = 'Trip completed - automatic release'
            WHERE trip_id = ?
        ");
        $stmt->execute([$trip_id]);
        
        // Calculate owner earnings (subtract platform fee)
        $platform_fee = $trip['platform_fee'];
        $owner_earnings = $trip['total_days_cost'] + $trip['insurance_fee'];
        
        // Update renter balance - remove pending hold
        $stmt = $pdo->prepare("
            UPDATE renter_balances 
            SET pending_holds = pending_holds - ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$trip['escrow_amount'], $trip['renter_id']]);
        
        // Release security deposit back to renter
        $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
        $stmt->execute([$trip['renter_id']]);
        $renter_balance_before = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            UPDATE renter_balances 
            SET current_balance = current_balance + ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$trip['security_deposit'], $trip['renter_id']]);
        
        // Record deposit release transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, transaction_type, amount, balance_before, balance_after,
                reference_type, reference_id, description
            ) VALUES (?, 'deposit_release', ?, ?, ?, 'trip', ?, ?)
        ");
        $stmt->execute([
            $trip['renter_id'],
            $trip['security_deposit'],
            $renter_balance_before,
            $renter_balance_before + $trip['security_deposit'],
            $trip_id,
            'Security deposit released for trip #' . $trip_id
        ]);
        
        // Update owner balance - move from pending to available
        $stmt = $pdo->prepare("SELECT available_balance FROM owner_balances WHERE user_id = ?");
        $stmt->execute([$trip['owner_id']]);
        $owner_balance_before = $stmt->fetchColumn();
        
        if ($owner_balance_before === false) {
            // Create owner balance if doesn't exist
            $stmt = $pdo->prepare("INSERT INTO owner_balances (user_id, available_balance) VALUES (?, 0.00)");
            $stmt->execute([$trip['owner_id']]);
            $owner_balance_before = 0;
        }
        
        $stmt = $pdo->prepare("
            UPDATE owner_balances 
            SET available_balance = available_balance + ?,
                pending_balance = pending_balance - ?,
                total_earned = total_earned + ?,
                platform_fees_paid = platform_fees_paid + ?,
                insurance_fees_paid = insurance_fees_paid + ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $owner_earnings,
            $owner_earnings,
            $owner_earnings,
            $platform_fee,
            $trip['insurance_fee'],
            $trip['owner_id']
        ]);
        
        // Record earning transaction for owner
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, transaction_type, amount, balance_before, balance_after,
                reference_type, reference_id, description
            ) VALUES (?, 'earning', ?, ?, ?, 'trip', ?, ?)
        ");
        $stmt->execute([
            $trip['owner_id'],
            $owner_earnings,
            $owner_balance_before,
            $owner_balance_before + $owner_earnings,
            $trip_id,
            'Earning from completed trip #' . $trip_id
        ]);
    }
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Trip completed! Your security deposit has been released.';
    redirect(SITE_URL . '/guest/trips.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    redirect(SITE_URL . '/guest/trips.php');
}
?>
