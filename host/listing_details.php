<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Vehicle Details - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get vehicle details
    $stmt = $pdo->prepare("
        SELECT v.*, u.full_name as owner_name, u.email as owner_email,
        (SELECT AVG(rating) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as avg_rating,
        (SELECT COUNT(*) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as review_count
        FROM vehicles v
        JOIN users u ON v.owner_id = u.user_id
        WHERE v.vehicle_id = ? AND v.owner_id = ?
    ");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error_message'] = 'Vehicle not found';
        redirect(SITE_URL . '/host/listings.php');
    }
    
    // Generate vehicle title
    $vehicle_title = $vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'];
    
    // Get vehicle photos
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, display_order ASC");
    $stmt->execute([$vehicle_id]);
    $photos = $stmt->fetchAll();
    
    // Get reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as reviewer_name, t.pickup_date, t.return_date
        FROM reviews r
        JOIN trips t ON r.trip_id = t.trip_id
        JOIN users u ON r.reviewer_id = u.user_id
        WHERE t.vehicle_id = ? AND r.review_type = 'renter_to_owner'
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$vehicle_id]);
    $reviews = $stmt->fetchAll();
    
    $pageTitle = htmlspecialchars($vehicle_title) . ' - Car Trip Now';
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading vehicle details: ' . $e->getMessage();
    redirect(SITE_URL . '/host/listings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <!-- Vehicle Title -->
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0;"><?php echo htmlspecialchars($vehicle_title); ?></h1>
            <p style="color: #666; margin: 10px 0 5px 0;">
                <i class="fas fa-map-marker-alt"></i> 
                <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state'] . ', ' . $vehicle['pickup_country']); ?>
            </p>
            <p style="color: #666; margin: 5px 0;">
                <i class="fas fa-car"></i> 
                <?php echo htmlspecialchars(ucfirst($vehicle['vehicle_type']) . ' • ' . ucfirst($vehicle['transmission']) . ' • ' . ucfirst($vehicle['fuel_type'])); ?>
                <?php if ($vehicle['avg_rating']): ?>
                    <span style="margin-left: 20px; color: var(--warning-color);">
                        <i class="fas fa-star"></i> 
                        <?php echo number_format($vehicle['avg_rating'], 1); ?> 
                        (<?php echo $vehicle['review_count']; ?> reviews)
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="edit_listing.php?id=<?php echo $vehicle_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Left Column - Photos and Details -->
        <div>
            <!-- Photo Gallery -->
            <div style="margin-bottom: 30px;">
                <?php if (!empty($photos)): ?>
                    <div style="border-radius: 12px; overflow: hidden;">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photos[0]['photo_url']); ?>" 
                             alt="Vehicle photo" 
                             style="width: 100%; height: 400px; object-fit: cover;"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                    </div>
                    
                    <?php if (count($photos) > 1): ?>
                        <div class="grid grid-4" style="gap: 10px; margin-top: 10px;">
                            <?php foreach (array_slice($photos, 1, 4) as $photo): ?>
                                <div style="border-radius: 8px; overflow: hidden;">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>" 
                                         alt="Vehicle photo" 
                                         style="width: 100%; height: 100px; object-fit: cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background-color: #f0f0f0; height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-car" style="font-size: 64px; color: #ccc;"></i>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <a href="manage_photos.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-images"></i> Manage Photos
                    </a>
                </div>
            </div>
            
            <!-- Vehicle Details -->
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Vehicle Details</h2>
                <div class="grid grid-3" style="gap: 20px; margin-top: 20px;">
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-users" style="font-size: 32px; color: var(--primary-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $vehicle['seats']; ?> Seats</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-door-closed" style="font-size: 32px; color: var(--secondary-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $vehicle['doors']; ?> Doors</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-tachometer-alt" style="font-size: 32px; color: var(--warning-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo number_format($vehicle['odometer_reading']); ?> mi</p>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                    <p style="margin: 0;"><strong>Color:</strong> <?php echo htmlspecialchars($vehicle['color']); ?></p>
                    <p style="margin: 5px 0 0 0;"><strong>License Plate:</strong> <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                </div>
            </div>
            
            <!-- Description -->
            <div class="card">
                <h2><i class="fas fa-align-left"></i> Description</h2>
                <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['description'])); ?></p>
            </div>
            
            <!-- Features -->
            <?php if ($vehicle['vehicle_features']): ?>
                <div class="card">
                    <h2><i class="fas fa-check-circle"></i> Features</h2>
                    <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['vehicle_features'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Vehicle Rules -->
            <?php if ($vehicle['vehicle_rules']): ?>
                <div class="card">
                    <h2><i class="fas fa-list-check"></i> Vehicle Rules</h2>
                    <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['vehicle_rules'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Reviews -->
            <?php if (!empty($reviews)): ?>
                <div class="card">
                    <h2><i class="fas fa-star"></i> Renter Reviews</h2>
                    <?php foreach ($reviews as $review): ?>
                        <div style="border-bottom: 1px solid #eee; padding: 15px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                <div style="color: var(--warning-color);">
                                    <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p><?php echo htmlspecialchars($review['comment']); ?></p>
                            <p style="color: #666; font-size: 12px; margin-top: 5px;">
                                <?php echo date('F Y', strtotime($review['created_at'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column - Pricing and Status -->
        <div>
            <div class="card">
                <h2><i class="fas fa-dollar-sign"></i> Pricing</h2>
                <div style="margin-top: 20px;">
                    <div style="background-color: var(--light-color); padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 15px;">
                        <p style="margin: 0; font-size: 14px; color: #666;">Daily Rate</p>
                        <p style="margin: 5px 0 0 0; font-size: 36px; font-weight: bold; color: var(--primary-color);">
                            <?php echo formatCurrency($vehicle['daily_price']); ?>
                        </p>
                    </div>
                    
                    <div class="grid grid-2" style="gap: 15px;">
                        <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px;">
                            <p style="margin: 0; font-size: 12px; color: #666;">Security Deposit</p>
                            <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: bold;">
                                <?php echo formatCurrency($vehicle['security_deposit']); ?>
                            </p>
                        </div>
                        <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px;">
                            <p style="margin: 0; font-size: 12px; color: #666;">Mileage Limit</p>
                            <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: bold;">
                                <?php echo $vehicle['mileage_limit_per_day']; ?> mi/day
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($vehicle['weekly_discount_percent'] > 0 || $vehicle['monthly_discount_percent'] > 0): ?>
                        <div style="margin-top: 15px; padding: 15px; background-color: #e8f5e9; border-radius: 8px;">
                            <p style="margin: 0; font-weight: bold; color: var(--success-color);">Discounts</p>
                            <?php if ($vehicle['weekly_discount_percent'] > 0): ?>
                                <p style="margin: 5px 0;">Weekly: <?php echo $vehicle['weekly_discount_percent']; ?>% off</p>
                            <?php endif; ?>
                            <?php if ($vehicle['monthly_discount_percent'] > 0): ?>
                                <p style="margin: 5px 0;">Monthly: <?php echo $vehicle['monthly_discount_percent']; ?>% off</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-cog"></i> Status & Settings</h2>
                <div style="margin-top: 20px;">
                    <p style="margin: 10px 0;">
                        <strong>Status:</strong>
                        <?php
                        $status_class = 'badge-success';
                        if ($vehicle['status'] === 'inactive') $status_class = 'badge-warning';
                        if ($vehicle['status'] === 'suspended') $status_class = 'badge-danger';
                        if ($vehicle['status'] === 'maintenance') $status_class = 'badge-secondary';
                        ?>
                        <span class="badge <?php echo $status_class; ?>">
                            <?php echo ucfirst($vehicle['status']); ?>
                        </span>
                    </p>
                    <p style="margin: 10px 0;">
                        <strong>Instant Book:</strong> 
                        <?php echo $vehicle['instant_book'] ? 'Enabled' : 'Disabled'; ?>
                    </p>
                    <p style="margin: 10px 0;">
                        <strong>Min Driver Age:</strong> <?php echo $vehicle['min_driver_age']; ?> years
                    </p>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i> Quick Actions</h2>
                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <a href="edit_listing.php?id=<?php echo $vehicle_id; ?>" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-edit"></i> Edit Vehicle
                    </a>
                    <a href="manage_photos.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-images"></i> Manage Photos
                    </a>
                    <a href="availability.php?id=<?php echo $vehicle_id; ?>" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-calendar"></i> Manage Availability
                    </a>
                    <a href="listings.php" class="btn btn-outline" style="width: 100%;">
                        <i class="fas fa-arrow-left"></i> Back to Vehicles
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
