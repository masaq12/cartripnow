<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Return Vehicle - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get all trips eligible for return (active and return date reached)
    $stmt = $pdo->prepare("
        SELECT t.*, v.make, v.model, v.year,
        v.pickup_location_address, v.pickup_city, v.pickup_state,
        o.full_name as owner_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users o ON t.owner_id = o.user_id
        WHERE t.renter_id = ?
        AND t.trip_status = 'active'
        AND t.return_date <= CURDATE()
        ORDER BY t.return_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $return_ready = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading return information';
    $return_ready = [];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 10px;">
        <i class="fas fa-sign-out-alt"></i> Return Vehicle
    </h1>
    <p style="color: #666; margin-bottom: 30px;">Complete your trip and leave a review</p>
    
    <?php if (empty($return_ready)): ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <i class="fas fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>
            <h3>No Active Returns</h3>
            <p style="color: #666;">You don't have any trips ready for return at the moment.</p>
            <a href="trips.php" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-calendar-check"></i> View My Trips
            </a>
        </div>
    <?php else: ?>
        <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 30px;">
            <h4 style="margin: 0 0 10px 0; color: #856404;">
                <i class="fas fa-info-circle"></i> Ready to Return
            </h4>
            <p style="margin: 0; color: #856404; font-size: 14px;">
                You have <?php echo count($return_ready); ?> trip(s) ready for return. 
                When you return the vehicle, payment will be released to the owner and you can leave a review.
            </p>
        </div>
        
        <?php foreach ($return_ready as $trip): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="grid grid-2" style="gap: 30px;">
                    <div>
                        <?php if ($trip['primary_photo']): ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($trip['primary_photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($trip['make'] . ' ' . $trip['model']); ?>" 
                                 style="width: 100%; height: 250px; object-fit: cover; border-radius: 8px;"
                                 onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                        <?php else: ?>
                            <div style="width: 100%; height: 250px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-car" style="font-size: 64px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($trip['year'] . ' ' . $trip['make'] . ' ' . $trip['model']); ?></h3>
                                <p style="color: #666; margin: 0;">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($trip['pickup_city'] . ', ' . $trip['pickup_state']); ?>
                                </p>
                            </div>
                            <span class="badge badge-warning" style="font-size: 14px;">
                                <i class="fas fa-clock"></i> Ready to Return
                            </span>
                        </div>
                        
                        <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div class="grid grid-2" style="gap: 20px;">
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Pickup</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px;">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('g:i A', strtotime($trip['pickup_time'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Return</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M d, Y', strtotime($trip['return_date'])); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px;">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('g:i A', strtotime($trip['return_time'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="grid grid-3" style="gap: 20px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Duration</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar-day"></i> <?php echo $trip['trip_duration_days']; ?> days
                                    </p>
                                </div>
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Mileage Limit</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-tachometer-alt"></i> <?php echo number_format($trip['mileage_limit']); ?> mi
                                    </p>
                                </div>
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Owner</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($trip['owner_name']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Total Amount</p>
                                    <p style="margin: 5px 0; font-size: 20px; font-weight: bold; color: var(--primary-color);">
                                        <?php echo formatCurrency($trip['total_amount']); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 12px;">
                                        Trip #<?php echo $trip['trip_id']; ?>
                                    </p>
                                </div>
                                <div style="text-align: right;">
                                    <p style="margin: 0; color: #666; font-size: 14px;">Payment Status</p>
                                    <p style="margin: 5px 0; font-weight: bold; color: #ff9800;">
                                        <i class="fas fa-lock"></i> In Escrow
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 12px;">
                                        Will be released to owner
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <form method="GET" action="return_vehicle_review.php" style="margin: 0;">
                            <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
                            <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px;">
                                <i class="fas fa-sign-out-alt"></i> Return Vehicle & Leave Review
                            </button>
                        </form>
                        
                        <p style="margin: 10px 0 0 0; text-align: center; color: #666; font-size: 13px;">
                            <i class="fas fa-info-circle"></i> Returning the vehicle will release payment to the owner
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
