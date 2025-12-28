<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Preview Listing - Host Dashboard';

try {
    $pdo = getPDOConnection();
    
    // Verify ownership and get listing
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name as host_name, u.email as host_email,
        (SELECT AVG(rating) FROM reviews r JOIN trips b ON r.trip_id = b.trip_id WHERE b.vehicle_id = l.vehicle_id AND r.review_type = 'renter_to_owner') as avg_rating,
        (SELECT COUNT(*) FROM reviews r JOIN trips b ON r.trip_id = b.trip_id WHERE b.vehicle_id = l.vehicle_id AND r.review_type = 'renter_to_owner') as review_count
        FROM trips l
        JOIN users u ON l.host_id = u.user_id
        WHERE l.vehicle_id = ? AND l.host_id = ?
    ");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        $_SESSION['error_message'] = 'Listing not found';
        redirect(SITE_URL . '/host/listings.php');
    }
    
    // Get photos
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, display_order ASC");
    $stmt->execute([$vehicle_id]);
    $photos = $stmt->fetchAll();
    
    // Get reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as reviewer_name, b.pickup_date, b.return_date
        FROM reviews r
        JOIN trips b ON r.trip_id = b.trip_id
        JOIN users u ON r.reviewer_id = u.user_id
        WHERE b.vehicle_id = ? AND r.review_type = 'renter_to_owner'
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$vehicle_id]);
    $reviews = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading listing';
    redirect(SITE_URL . '/host/listings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <!-- Header with Actions -->
    <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0 0 5px 0; color: #856404;"><i class="fas fa-eye"></i> Preview Mode</h3>
                <p style="margin: 0; color: #856404;">This is how guests will see your listing.</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo SITE_URL; ?>/host/edit_listing.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-edit"></i> Edit Listing
                </a>
                <a href="<?php echo SITE_URL; ?>/host/manage_photos.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-images"></i> Manage Photos
                </a>
                <a href="<?php echo SITE_URL; ?>/host/listings.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- Listing Title -->
    <div style="margin-bottom: 20px;">
        <h1 style="margin-bottom: 10px;"><?php echo htmlspecialchars($listing['title']); ?></h1>
        <p style="color: #666; margin-bottom: 10px;">
            <i class="fas fa-map-marker-alt"></i> 
            <?php echo htmlspecialchars($listing['pickup_city'] . ', ' . $listing['pickup_state'] . ', ' . $listing['pickup_country']); ?>
            <?php if ($listing['avg_rating']): ?>
                <span style="margin-left: 20px; color: var(--warning-color);">
                    <i class="fas fa-star"></i> 
                    <?php echo number_format($listing['avg_rating'], 1); ?> 
                    (<?php echo $listing['review_count']; ?> reviews)
                </span>
            <?php endif; ?>
        </p>
        <p>
            <span class="badge badge-<?php echo $listing['status'] === 'active' ? 'success' : 'warning'; ?>">
                Status: <?php echo ucfirst($listing['status']); ?>
            </span>
        </p>
    </div>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Left Column - Photos and Details -->
        <div>
            <!-- Photo Gallery -->
            <div style="margin-bottom: 30px;">
                <?php if (!empty($photos)): ?>
                    <div style="border-radius: 12px; overflow: hidden;">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photos[0]['photo_url']); ?>" 
                             alt="Listing photo" 
                             id="mainPhoto"
                             style="width: 100%; height: 400px; object-fit: cover; cursor: pointer;"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.jpg'">
                    </div>
                    
                    <?php if (count($photos) > 1): ?>
                        <div class="grid grid-4" style="gap: 10px; margin-top: 10px;">
                            <?php foreach ($photos as $index => $photo): ?>
                                <div style="border-radius: 8px; overflow: hidden; border: 2px solid transparent;" class="photo-thumb" data-index="<?php echo $index; ?>">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>" 
                                         alt="Listing photo" 
                                         style="width: 100%; height: 100px; object-fit: cover; cursor: pointer;"
                                         onclick="changeMainPhoto('<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>', <?php echo $index; ?>)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background-color: #f0f0f0; height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fas fa-home" style="font-size: 64px; color: #ccc; margin-bottom: 15px;"></i>
                        <p style="color: #666;">No photos uploaded yet</p>
                        <a href="<?php echo SITE_URL; ?>/host/manage_photos.php?id=<?php echo $vehicle_id; ?>" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Photos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Property Details -->
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Property Details</h2>
                <div class="grid grid-4" style="gap: 15px; margin-top: 20px;">
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-users" style="font-size: 32px; color: var(--primary-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $listing['seats']; ?> Guests</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-bed" style="font-size: 32px; color: var(--secondary-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $listing['doors']; ?> Bedrooms</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-door-open" style="font-size: 32px; color: var(--success-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $listing['beds']; ?> Beds</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-bath" style="font-size: 32px; color: var(--warning-color);"></i>
                        <p style="margin: 10px 0 0 0; font-weight: bold;"><?php echo $listing['bathrooms']; ?> Baths</p>
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="card">
                <h2><i class="fas fa-align-left"></i> Description</h2>
                <p style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($listing['description']); ?></p>
            </div>
            
            <!-- Amenities -->
            <?php if ($listing['amenities']): ?>
                <div class="card">
                    <h2><i class="fas fa-check-circle"></i> Amenities</h2>
                    <div class="grid grid-2" style="gap: 10px;">
                        <?php
                        $amenities = explode(',', $listing['amenities']);
                        foreach ($amenities as $amenity):
                            $amenity = trim($amenity);
                            if ($amenity):
                        ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-check" style="color: var(--success-color);"></i>
                                <span><?php echo htmlspecialchars($amenity); ?></span>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- House Rules -->
            <?php if ($listing['house_rules']): ?>
                <div class="card">
                    <h2><i class="fas fa-list-check"></i> House Rules</h2>
                    <p style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($listing['house_rules']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Host Info -->
            <div class="card">
                <h2><i class="fas fa-user"></i> Hosted by <?php echo htmlspecialchars($listing['host_name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($listing['host_email']); ?></p>
            </div>
            
            <!-- Reviews -->
            <?php if (!empty($reviews)): ?>
                <div class="card">
                    <h2><i class="fas fa-star"></i> Guest Reviews (<?php echo count($reviews); ?>)</h2>
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
        
        <!-- Right Column - Pricing Info -->
        <div>
            <div class="card" style="position: sticky; top: 80px;">
                <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    <div class="listing-price" style="font-size: 32px;">
                        <?php echo formatCurrency($listing['price_per_night']); ?>
                        <span style="font-size: 16px; font-weight: normal;"> / night</span>
                    </div>
                    <p style="color: #666; margin: 5px 0;">+ <?php echo formatCurrency($listing['cleaning_fee']); ?> cleaning fee</p>
                    <p style="color: #666; margin: 5px 0;">Service fee: <?php echo $listing['service_fee_percent']; ?>%</p>
                </div>
                
                <!-- Sample Booking Calculation -->
                <div style="background-color: #f0f0f0; padding: 20px; border-radius: 8px;">
                    <h3 style="font-size: 16px; margin-bottom: 15px;">Sample 3-Night Stay</h3>
                    <?php
                    $nights = 3;
                    $nightsTotal = $listing['price_per_night'] * $nights;
                    $serviceFee = ($nightsTotal + $listing['cleaning_fee']) * ($listing['service_fee_percent'] / 100);
                    $tax = ($nightsTotal + $listing['cleaning_fee'] + $serviceFee) * 0.10;
                    $total = $nightsTotal + $listing['cleaning_fee'] + $serviceFee + $tax;
                    ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span><?php echo $nights; ?> nights × <?php echo formatCurrency($listing['price_per_night']); ?></span>
                        <span><?php echo formatCurrency($nightsTotal); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Cleaning fee</span>
                        <span><?php echo formatCurrency($listing['cleaning_fee']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Service fee</span>
                        <span><?php echo formatCurrency($serviceFee); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Tax</span>
                        <span><?php echo formatCurrency($tax); ?></span>
                    </div>
                    <div style="border-top: 2px solid #ddd; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; font-size: 18px; font-weight: bold;">
                        <span>Total</span>
                        <span><?php echo formatCurrency($total); ?></span>
                    </div>
                </div>
                
                <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <h3 style="font-size: 14px; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Your Earnings</h3>
                    <p style="margin: 5px 0; font-size: 13px;">
                        For this sample booking, you would earn approximately:
                    </p>
                    <?php
                    $platformFee = ($nightsTotal + $listing['cleaning_fee']) * (PLATFORM_FEE_PERCENT / 100);
                    $hostEarnings = $nightsTotal + $listing['cleaning_fee'] - $platformFee;
                    ?>
                    <p style="margin: 10px 0 0 0; font-size: 24px; font-weight: bold; color: var(--success-color);">
                        <?php echo formatCurrency($hostEarnings); ?>
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                        After <?php echo PLATFORM_FEE_PERCENT; ?>% platform fee
                    </p>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="<?php echo SITE_URL; ?>/host/bookings.php" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-calendar-check"></i> View Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeMainPhoto(photoUrl, index) {
    document.getElementById('mainPhoto').src = photoUrl;
    
    // Update active thumbnail
    document.querySelectorAll('.photo-thumb').forEach((thumb, i) => {
        if (i === index) {
            thumb.style.borderColor = 'var(--primary-color)';
        } else {
            thumb.style.borderColor = 'transparent';
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
