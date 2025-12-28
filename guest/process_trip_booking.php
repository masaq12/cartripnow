<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/browse.php');
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $renter_id = $_SESSION['user_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $pickup_date = $_POST['pickup_date'];
    $return_date = $_POST['return_date'];
    $pickup_time = $_POST['pickup_time'];
    $return_time = $_POST['return_time'];
    $insurance_plan_id = (int)$_POST['insurance_plan_id'];
    $payment_credential_id = (int)$_POST['payment_credential_id'];
    
    // Validate required fields
    if (empty($pickup_date) || empty($return_date) || empty($pickup_time) || empty($return_time)) {
        throw new Exception('Please select pickup and return dates and times');
    }
    
    // Calculate and validate trip duration
    $pickup_datetime = new DateTime($pickup_date . ' ' . $pickup_time);
    $return_datetime = new DateTime($return_date . ' ' . $return_time);
    $duration = $pickup_datetime->diff($return_datetime);
    $trip_days = max(1, $duration->days);
    
    // Validate minimum trip duration (at least 1 day)
    // if ($trip_days < 1 || $duration->days == 0) {
    //     throw new Exception('Return date must be at least 1 day after pickup date');
    // }
    
    // Validate that return is after pickup
    if ($return_datetime <= $pickup_datetime) {
        throw new Exception('Return date and time must be after pickup date and time');
    }
    
    // Get vehicle details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND status = 'active'");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        throw new Exception('Vehicle not found or inactive');
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
        AND trip_status IN ('confirmed', 'active')
        AND (
            (pickup_date <= ? AND return_date >= ?)
            OR (pickup_date <= ? AND return_date >= ?)
            OR (pickup_date >= ? AND return_date <= ?)
        )
    ");
    $stmt->execute([$vehicle_id, $pickup_date, $pickup_date, $return_date, $return_date, $pickup_date, $return_date]);
    $conflicting_trips = $stmt->fetchColumn();
    
    if ($conflicting_trips > 0) {
        throw new Exception('Selected dates are not available');
    }
    
    // Trip duration already calculated and validated above
    // $trip_days is already set
    
    // Calculate pricing
    $daily_rate = $vehicle['daily_price'];
    $total_days_cost = $daily_rate * $trip_days;
    
    // Apply discounts
    $discount = 0;
    if ($trip_days >= 28 && $vehicle['monthly_discount_percent'] > 0) {
        $discount = $total_days_cost * ($vehicle['monthly_discount_percent'] / 100);
    } elseif ($trip_days >= 7 && $vehicle['weekly_discount_percent'] > 0) {
        $discount = $total_days_cost * ($vehicle['weekly_discount_percent'] / 100);
    }
    $total_days_cost = $total_days_cost - $discount;
    
    // Insurance
    $stmt = $pdo->prepare("SELECT * FROM insurance_plans WHERE plan_id = ? AND status = 'active'");
    $stmt->execute([$insurance_plan_id]);
    $insurance_plan = $stmt->fetch();
    
    if (!$insurance_plan) {
        throw new Exception('Invalid insurance plan');
    }
    
    $insurance_fee = $insurance_plan['daily_fee'] * $trip_days;
    
    // Platform fee
    $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_percent'");
    $stmt->execute();
    $platform_fee_percent = $stmt->fetchColumn() ?: 15.00;
    $platform_fee = $total_days_cost * ($platform_fee_percent / 100);
    
    // Security deposit
    $security_deposit = $vehicle['security_deposit'];
    
    // Mileage
    $mileage_limit = $vehicle['mileage_limit_per_day'] * $trip_days;
    
    // Total amount
    $total_amount = $total_days_cost + $insurance_fee + $platform_fee + $security_deposit;
    
    // Get renter balance
    $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$renter_id]);
    $renter_balance = $stmt->fetchColumn();
    
    // Check if renter has sufficient balance
    if ($renter_balance < $total_amount) {
        throw new Exception('Insufficient balance. Please add funds to your account.');
    }
    
    // Create trip
    $stmt = $pdo->prepare("
        INSERT INTO trips (
            vehicle_id, renter_id, owner_id, 
            pickup_date, return_date, pickup_time, return_time, trip_duration_days,
            daily_rate, total_days_cost, 
            mileage_limit, insurance_plan_id, insurance_fee,
            platform_fee, service_fee_percent, 
            security_deposit, total_amount,
            payment_credential_id, trip_status, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
    ");
    $stmt->execute([
        $vehicle_id, $renter_id, $vehicle['owner_id'], 
        $pickup_date, $return_date, $pickup_time, $return_time, $trip_days,
        $daily_rate, $total_days_cost,
        $mileage_limit, $insurance_plan_id, $insurance_fee,
        $platform_fee, $platform_fee_percent,
        $security_deposit, $total_amount,
        $payment_credential_id
    ]);
    $trip_id = $pdo->lastInsertId();
    
    // Deduct from renter balance
    $new_balance = $renter_balance - $total_amount;
    $stmt = $pdo->prepare("
        UPDATE renter_balances 
        SET current_balance = ?, 
            pending_holds = pending_holds + ?, 
            total_spent = total_spent + ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$new_balance, $total_amount, $total_amount, $renter_id]);
    
    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id, transaction_type, amount, balance_before, balance_after, 
            reference_type, reference_id, description
        ) VALUES (?, 'deduction', ?, ?, ?, 'trip', ?, ?)
    ");
    $stmt->execute([
        $renter_id, $total_amount, $renter_balance, $new_balance, 
        $trip_id, 'Payment for trip #' . $trip_id
    ]);
    
    // Place funds in escrow
    $trip_amount = $total_days_cost + $insurance_fee + $platform_fee;
    $stmt = $pdo->prepare("
        INSERT INTO escrow (trip_id, trip_amount, deposit_amount, total_amount, status) 
        VALUES (?, ?, ?, ?, 'held')
    ");
    $stmt->execute([$trip_id, $trip_amount, $security_deposit, $total_amount]);
    
    // Update trip status
    $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'confirmed', payment_status = 'held' WHERE trip_id = ?");
    $stmt->execute([$trip_id]);
    
    // Update owner pending balance
    $owner_earnings = $total_days_cost + $insurance_fee - $platform_fee;
    
    // Check if owner balance exists, if not create it
    $stmt = $pdo->prepare("SELECT balance_id FROM owner_balances WHERE user_id = ?");
    $stmt->execute([$vehicle['owner_id']]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO owner_balances (user_id, available_balance) VALUES (?, 0.00)");
        $stmt->execute([$vehicle['owner_id']]);
    }
    
    $stmt = $pdo->prepare("UPDATE owner_balances SET pending_balance = pending_balance + ? WHERE user_id = ?");
    $stmt->execute([$owner_earnings, $vehicle['owner_id']]);
    
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Trip confirmed! Your payment has been secured in escrow.';
    redirect(SITE_URL . '/guest/trips.php');
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    redirect(SITE_URL . '/guest/listing_details.php?id=' . ($vehicle_id ?? 0));
}
?>
