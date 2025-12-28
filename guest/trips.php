<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'My Trips - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get all trips for this renter
    $stmt = $pdo->prepare("
        SELECT t.*, v.make, v.model, v.year,
        v.pickup_location_address, v.pickup_city, v.pickup_state,
        o.full_name as owner_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users o ON t.owner_id = o.user_id
        WHERE t.renter_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $trips = $stmt->fetchAll();
    
    // Count trips ready for return
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM trips 
        WHERE renter_id = ? 
        AND trip_status = 'active' 
        AND return_date <= CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $return_count = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading trips';
    $trips = [];
    $return_count = 0;
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-car"></i> My Trips
    </h1>
    
    <?php if ($return_count > 0): ?>
        <div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px 20px; border-radius: 8px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="margin: 0 0 5px 0; color: #856404;">
                    <i class="fas fa-exclamation-triangle"></i> You have <?php echo $return_count; ?> trip(s) ready for return!
                </h4>
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    Complete your return to release payment to the owner and share your experience.
                </p>
            </div>
            <a href="return.php" class="btn" style="background-color: #ff9800; color: white; border: none; white-space: nowrap;">
                <i class="fas fa-sign-out-alt"></i> Return Vehicle
            </a>
        </div>
    <?php endif; ?>
    
    <?php if (empty($trips)): ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <i class="fas fa-car" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
            <h3>No trips yet</h3>
            <p>Start exploring amazing vehicles!</p>
            <a href="browse.php" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-search"></i> Browse Vehicles
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($trips as $trip): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="grid grid-2" style="gap: 30px;">
                    <div>
                        <?php if ($trip['primary_photo']): ?>
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($trip['primary_photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($trip['make'] . ' ' . $trip['model']); ?>" 
                                 style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;"
                                 onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                        <?php else: ?>
                            <div style="width: 100%; height: 200px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-car" style="font-size: 48px; color: #ccc;"></i>
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
                            <div>
                                <?php
                                $status_class = 'badge-info';
                                $status_text = ucfirst($trip['trip_status']);
                                if ($trip['trip_status'] === 'confirmed') $status_class = 'badge-success';
                                if ($trip['trip_status'] === 'completed') $status_class = 'badge-success';
                                if ($trip['trip_status'] === 'cancelled') $status_class = 'badge-danger';
                                
                                // Check if ready for pickup
                                $ready_pickup = ($trip['trip_status'] === 'confirmed' && $trip['pickup_date'] <= date('Y-m-d'));
                                if ($ready_pickup) {
                                    $status_class = 'badge-info';
                                    $status_text = 'Ready for Pickup';
                                }
                                
                                // Check if ready for return
                                $ready_return = ($trip['trip_status'] === 'active' && $trip['return_date'] <= date('Y-m-d'));
                                if ($ready_return) {
                                    $status_class = 'badge-warning';
                                    $status_text = 'Ready for Return';
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>" style="font-size: 14px;">
                                    <?php if ($ready_pickup || $ready_return): ?><i class="fas fa-exclamation-circle"></i> <?php endif; ?>
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
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
                            
                            <div class="grid grid-2" style="gap: 20px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Duration</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar-day"></i> <?php echo $trip['trip_duration_days']; ?> days
                                    </p>
                                </div>
                                <div>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Mileage Limit</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-tachometer-alt"></i> <?php echo number_format($trip['mileage_limit']); ?> miles
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="margin: 0; color: #666; font-size: 14px;">Total Amount</p>
                                <p style="margin: 5px 0; font-size: 24px; font-weight: bold; color: var(--primary-color);">
                                    <?php echo formatCurrency($trip['total_amount']); ?>
                                </p>
                                <p style="margin: 5px 0; color: #666; font-size: 12px;">
                                    Trip #<?php echo $trip['trip_id']; ?> • 
                                    <?php echo date('M d, Y', strtotime($trip['created_at'])); ?>
                                </p>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <a href="listing_details.php?id=<?php echo $trip['vehicle_id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> View Vehicle
                                </a>
                                
                                <?php 
                                // Check if renter can start trip (confirmed and pickup date arrived)
                                $can_start = ($trip['trip_status'] === 'confirmed' && $trip['pickup_date'] <= date('Y-m-d'));
                                
                                // Check if renter can end trip (on or after return date and status is active)
                                $can_end = ($trip['trip_status'] === 'active' && $trip['return_date'] <= date('Y-m-d'));
                                
                                // Check if renter can review (trip completed and no review yet)
                                $can_review = false;
                                if ($trip['trip_status'] === 'completed') {
                                    $check_review = $pdo->prepare("
                                        SELECT review_id FROM reviews 
                                        WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'renter_to_owner'
                                    ");
                                    $check_review->execute([$trip['trip_id'], $_SESSION['user_id']]);
                                    $can_review = !$check_review->fetch();
                                }
                                ?>
                                
                                <?php if ($can_start): ?>
                                    <form method="POST" action="start_trip.php" style="margin: 0;" onsubmit="return confirm('Start this trip?');">
                                        <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                                            <i class="fas fa-play"></i> Start Trip
                                        </button>
                                    </form>
                                    <p style="margin: 5px 0; text-align: center; color: #666; font-size: 12px;">
                                        <i class="fas fa-info-circle"></i> Pick up the vehicle
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($can_end): ?>
                                    <a href="return_vehicle.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-success" style="width: 100%;">
                                        <i class="fas fa-sign-out-alt"></i> Return Vehicle & Review
                                    </a>
                                    <p style="margin: 5px 0; text-align: center; color: #666; font-size: 12px;">
                                        <i class="fas fa-exclamation-circle"></i> Payment will be released to owner
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($can_review): ?>
                                    <a href="review.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-secondary" style="width: 100%;">
                                        <i class="fas fa-star"></i> Write Review
                                    </a>
                                <?php elseif ($trip['trip_status'] === 'completed'): ?>
                                    <button class="btn btn-outline" style="width: 100%; cursor: default;" disabled>
                                        <i class="fas fa-check"></i> Review Submitted
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
