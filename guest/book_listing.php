<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isGuest() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/browse.php');
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $renter_id = $_SESSION['user_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $pickup_date = $_POST['pickup_date'];
    $return_date = $_POST['return_date'];
    $num_guests = (int)$_POST['num_guests'];
    $payment_credential_id = (int)$_POST['payment_credential_id'];
    $trip_duration_days = (int)$_POST['trip_duration_days'];
    $total_amount = (float)$_POST['total_amount'];
    
    // Get listing details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND status = 'active'");
    $stmt->execute([$vehicle_id]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        throw new Exception('Listing not found or inactive');
    }
    
    // Verify payment credential belongs to user and is active
    $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE credential_id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$payment_credential_id, $renter_id]);
    $credential = $stmt->fetch();
    
    if (!$credential) {
        throw new Exception('Invalid or inactive payment credential');
    }
    
    // Check if dates are available
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM trips 
        WHERE vehicle_id = ? 
        AND trip_status IN ('confirmed', 'checked_in')
        AND (
            (pickup_date <= ? AND return_date >= ?)
            OR (pickup_date <= ? AND return_date >= ?)
            OR (pickup_date >= ? AND return_date <= ?)
        )
    ");
    $stmt->execute([$vehicle_id, $pickup_date, $pickup_date, $return_date, $return_date, $pickup_date, $return_date]);
    $conflicting_bookings = $stmt->fetchColumn();
    
    if ($conflicting_bookings > 0) {
        throw new Exception('Selected dates are not available');
    }
    
    // Get guest balance
    $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$renter_id]);
    $guest_balance = $stmt->fetchColumn();
    
    // Check if guest has sufficient balance
    if ($guest_balance < $total_amount) {
        throw new Exception('Insufficient balance. Please add funds to your account.');
    }
    
    // Calculate pricing
    $daily_rate = $listing['price_per_night'];
    $cleaning_fee = $listing['cleaning_fee'];
    $nights_total = $daily_rate * $trip_duration_days;
    $service_fee = $nights_total * ($listing['service_fee_percent'] / 100);
    $subtotal = $nights_total + $cleaning_fee + $service_fee;
    $tax_amount = $subtotal * 0.10; // 10% tax
    $calculated_total = $subtotal + $tax_amount;
    
    // Verify total amount
    if (abs($calculated_total - $total_amount) > 0.01) {
        throw new Exception('Price mismatch. Please refresh and try again.');
    }
    
    // Create booking
    $stmt = $pdo->prepare("
        INSERT INTO trips (
            vehicle_id, renter_id, owner_id, pickup_date, return_date, num_guests, trip_duration_days,
            daily_rate, cleaning_fee, service_fee, tax_amount, total_amount,
            payment_credential_id, trip_status, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
    ");
    $stmt->execute([
        $vehicle_id, $renter_id, $listing['owner_id'], $pickup_date, $return_date, $num_guests, $trip_duration_days,
        $daily_rate, $cleaning_fee, $service_fee, $tax_amount, $total_amount,
        $payment_credential_id
    ]);
    $trip_id = $pdo->lastInsertId();
    
    // Deduct from guest balance
    $new_balance = $guest_balance - $total_amount;
    $stmt = $pdo->prepare("UPDATE renter_balances SET current_balance = ?, pending_holds = pending_holds + ?, total_spent = total_spent + ? WHERE user_id = ?");
    $stmt->execute([$new_balance, $total_amount, $total_amount, $renter_id]);
    
    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, description)
        VALUES (?, 'deduction', ?, ?, ?, 'booking', ?, ?)
    ");
    $stmt->execute([$renter_id, $total_amount, $guest_balance, $new_balance, $trip_id, 'Payment for booking #' . $trip_id]);
    
    // Place funds in escrow
    $stmt = $pdo->prepare("INSERT INTO escrow (trip_id, amount, status) VALUES (?, ?, 'held')");
    $stmt->execute([$trip_id, $total_amount]);
    
    // Update booking status
    $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'confirmed', payment_status = 'held' WHERE trip_id = ?");
    $stmt->execute([$trip_id]);
    
    // Update host pending balance
    $host_earnings = $total_amount - $service_fee;
    $stmt = $pdo->prepare("UPDATE host_balances SET pending_balance = pending_balance + ? WHERE user_id = ?");
    $stmt->execute([$host_earnings, $listing['owner_id']]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Booking confirmed! Your payment has been secured in escrow.';
    redirect(SITE_URL . '/guest/bookings.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    redirect(SITE_URL . '/guest/listing_details.php?id=' . ($vehicle_id ?? 0));
}
?>
