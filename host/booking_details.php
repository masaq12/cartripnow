<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$trip_id = $_GET['id'] ?? 0;
$pageTitle = 'Booking Details - Host - Car Trip Now';

try {
    $pdo = getPDOConnection();
    $owner_id = $_SESSION['user_id'];
    
    // Get booking details
    $stmt = $pdo->prepare("
        SELECT b.*, 
        l.year, l.make, l.model, l.pickup_city, l.pickup_country, l.pickup_location_address, l.pickup_zipcode,l.pickup_state, l.seats,
        g.full_name as guest_name, g.email as guest_email, g.phone as guest_phone,
        pc.credential_type, pc.credential_number
        FROM trips b
        JOIN vehicles l ON b.vehicle_id = l.vehicle_id
        JOIN users g ON b.renter_id = g.user_id
        JOIN payment_credentials pc ON b.payment_credential_id = pc.credential_id
        WHERE b.trip_id = ? AND b.owner_id = ?
    ");
    $stmt->execute([$trip_id, $owner_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error_message'] = 'Booking not found';
        redirect(SITE_URL . '/host/bookings.php');
    }
    
    // Get listing photos
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC LIMIT 1");
    $stmt->execute([$booking['vehicle_id']]);
    $photo = $stmt->fetch();
    
    // Get transaction history for this booking
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE reference_type = 'booking' AND reference_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$trip_id]);
    $transactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading booking details';
    redirect(SITE_URL . '/host/bookings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <div style="margin-bottom: 20px;">
        <a href="<?php echo SITE_URL; ?>/host/bookings.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Bookings
        </a>
    </div>
    
    <h1 style="margin-bottom: 10px;">
        <i class="fas fa-file-invoice"></i> Booking #<?php echo $booking['trip_id']; ?>
    </h1>
    
    <div style="margin-bottom: 30px;">
        <?php
        $badge_class = 'badge-info';
        if ($booking['trip_status'] === 'confirmed') $badge_class = 'badge-success';
        if ($booking['trip_status'] === 'completed') $badge_class = 'badge-success';
        if ($booking['trip_status'] === 'cancelled') $badge_class = 'badge-danger';
        if ($booking['trip_status'] === 'checked_in') $badge_class = 'badge-warning';
        ?>
        <span class="badge <?php echo $badge_class; ?>" style="font-size: 16px; padding: 8px 15px;">
            <?php echo ucfirst(str_replace('_', ' ', $booking['trip_status'])); ?>
        </span>
        
        <span style="margin-left: 10px; color: #666;">
            Booked on <?php echo date('F d, Y', strtotime($booking['created_at'])); ?>
        </span>
    </div>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Left Column -->
        <div>
            <!-- Listing Info -->
            <div class="card">
                <h2><i class="fas fa-home"></i> Property Information</h2>
                
                <?php if ($photo): ?>
                    <div style="margin: 20px 0; border-radius: 8px; overflow: hidden;">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>" 
                             alt="Property" 
                             style="width: 100%; height: 250px; object-fit: cover;">
                    </div>
                <?php endif; ?>
                
                <h3 style="margin: 15px 0;"><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></h3>
                
                <p style="color: #666; margin: 5px 0;">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($booking['pickup_location_address'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($booking['pickup_city'] . ', ' . $booking['pickup_state'] . ' ' . $booking['pickup_zipcode']); ?><br>
                    <?php echo htmlspecialchars($booking['pickup_country']); ?>
                </p>
                
                <div style="margin-top: 15px;">
                    <a href="<?php echo SITE_URL; ?>/guest/listing_details.php?id=<?php echo $booking['vehicle_id']; ?>" 
                       class="btn btn-outline" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Listing
                    </a>
                </div>
            </div>
            
            <!-- Guest Info -->
            <div class="card">
                <h2><i class="fas fa-user"></i> Guest Information</h2>
                
                <div style="background-color: var(--light-color); padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <i class="fas fa-user" style="font-size: 30px; color: white;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0;"><?php echo htmlspecialchars($booking['guest_name']); ?></h3>
                            <p style="margin: 5px 0; color: #666;">Guest</p>
                        </div>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <p style="margin: 8px 0;">
                            <i class="fas fa-envelope" style="width: 20px;"></i>
                            <strong>Email:</strong> <?php echo htmlspecialchars($booking['guest_email']); ?>
                        </p>
                        <?php if ($booking['guest_phone']): ?>
                            <p style="margin: 8px 0;">
                                <i class="fas fa-phone" style="width: 20px;"></i>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($booking['guest_phone']); ?>
                            </p>
                        <?php endif; ?>
                        <p style="margin: 8px 0;">
                            <i class="fas fa-users" style="width: 20px;"></i>
                            <strong>Number of Guests:</strong> <?php echo $booking['seats']; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Transaction History -->
            <?php if (!empty($transactions)): ?>
                <div class="card">
                    <h2><i class="fas fa-exchange-alt"></i> Transaction History</h2>
                    
                    <div style="margin-top: 20px;">
                        <?php foreach ($transactions as $txn): ?>
                            <div style="border-bottom: 1px solid #eee; padding: 15px 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <p style="margin: 0; font-weight: bold;">
                                            <?php echo ucfirst($txn['transaction_type']); ?>
                                        </p>
                                        <p style="margin: 5px 0; color: #666; font-size: 14px;">
                                            <?php echo htmlspecialchars($txn['description']); ?>
                                        </p>
                                        <p style="margin: 5px 0; color: #666; font-size: 12px;">
                                            <?php echo date('M d, Y h:i A', strtotime($txn['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <p style="margin: 0; font-size: 18px; font-weight: bold; color: <?php echo in_array($txn['transaction_type'], ['earning', 'refund']) ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                                            <?php echo in_array($txn['transaction_type'], ['earning', 'refund']) ? '+' : '-'; ?>
                                            <?php echo formatCurrency($txn['amount']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column -->
        <div>
            <!-- Booking Dates -->
            <div class="card">
                <h2><i class="fas fa-calendar"></i> Booking Details</h2>
                
                <div class="grid grid-2" style="gap: 20px; margin-top: 20px;">
                    <div style="background-color: var(--light-color); padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-calendar-check" style="font-size: 32px; color: var(--success-color); margin-bottom: 10px;"></i>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">CHECK-IN</p>
                        <p style="margin: 0; font-size: 20px; font-weight: bold;">
                            <?php echo date('M d, Y', strtotime($booking['pickup_date'])); ?>
                        </p>
                        <p style="margin: 5px 0; font-size: 14px; color: #666;">
                            <?php echo date('l', strtotime($booking['pickup_date'])); ?>
                        </p>
                    </div>
                    
                    <div style="background-color: var(--light-color); padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-calendar-times" style="font-size: 32px; color: var(--danger-color); margin-bottom: 10px;"></i>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">CHECK-OUT</p>
                        <p style="margin: 0; font-size: 20px; font-weight: bold;">
                            <?php echo date('M d, Y', strtotime($booking['return_date'])); ?>
                        </p>
                        <p style="margin: 5px 0; font-size: 14px; color: #666;">
                            <?php echo date('l', strtotime($booking['return_date'])); ?>
                        </p>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 20px; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                    <p style="margin: 0; font-size: 16px; color: #666;">Total Stay</p>
                    <p style="margin: 5px 0; font-size: 32px; font-weight: bold;">
                        <?php echo $booking['trip_duration_days']; ?> Night<?php echo $booking['trip_duration_days'] > 1 ? 's' : ''; ?>
                    </p>
                </div>
            </div>
            
            <!-- Payment Breakdown -->
            <div class="card">
                <h2><i class="fas fa-dollar-sign"></i> Payment Breakdown</h2>
                
                <div style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee;">
                        <span><?php echo $booking['trip_duration_days']; ?> nights × <?php echo formatCurrency($booking['daily_rate']); ?></span>
                        <strong><?php echo formatCurrency($booking['trip_duration_days'] * $booking['daily_rate']); ?></strong>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee;">
                        <span>Insurance Fee</span>
                        <span><?php echo formatCurrency($booking['insurance_fee'] ?? 0); ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee;">
                        <span>Platform Fee (<?php echo number_format($booking['service_fee_percent'] ?? 15, 1); ?>%)</span>
                        <span><?php echo formatCurrency($booking['platform_fee'] ?? 0); ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee;">
                        <span>Security Deposit (Refundable)</span>
                        <span><?php echo formatCurrency($booking['security_deposit'] ?? 0); ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; padding: 20px 0; font-size: 20px;">
                        <strong>Total Amount</strong>
                        <strong style="color: var(--primary-color);"><?php echo formatCurrency($booking['total_amount']); ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div class="card">
                <h2><i class="fas fa-credit-card"></i> Payment Information</h2>
                
                <div style="margin-top: 20px;">
                    <p style="margin: 8px 0;">
                        <strong>Payment Method:</strong> 
                        <?php echo ucfirst(str_replace('_', ' ', $booking['credential_type'])); ?>
                    </p>
                    <p style="margin: 8px 0;">
                        <strong>Credential:</strong> 
                        ****<?php echo substr($booking['credential_number'], -4); ?>
                    </p>
                    <p style="margin: 8px 0;">
                        <strong>Payment Status:</strong>
                        <?php
                        $payment_badge = 'badge-info';
                        if ($booking['payment_status'] === 'completed') $payment_badge = 'badge-success';
                        if ($booking['payment_status'] === 'refunded') $payment_badge = 'badge-warning';
                        ?>
                        <span class="badge <?php echo $payment_badge; ?>">
                            <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
