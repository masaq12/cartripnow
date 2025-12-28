<?php
require_once 'config/config.php';
require_once 'config/database.php';

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Vehicle Details - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get vehicle details
    $stmt = $pdo->prepare("
        SELECT v.*, u.full_name as owner_name, u.email as owner_email, u.user_id as owner_user_id,
        (SELECT AVG(rating) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as avg_rating,
        (SELECT COUNT(*) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as review_count
        FROM vehicles v
        JOIN users u ON v.owner_id = u.user_id
        WHERE v.vehicle_id = ? AND v.status = 'active'
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error_message'] = 'Vehicle not found';
        redirect(SITE_URL . '/index.php');
    }
    
    // Generate vehicle title
    $vehicle_title = $vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'];
    
    // Get vehicle photos
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, display_order ASC");
    $stmt->execute([$vehicle_id]);
    $photos = $stmt->fetchAll();
    
    // Get renter balance and payment credentials if logged in as renter
    $balance_info = null;
    $payment_credentials = [];
    $is_in_wishlist = false;
    
    if (isRenter()) {
        $stmt = $pdo->prepare("SELECT current_balance, pending_holds FROM renter_balances WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $balance_info = $stmt->fetch();
        
        if (!$balance_info) {
            $stmt = $pdo->prepare("INSERT INTO renter_balances (user_id, current_balance) VALUES (?, 0.00)");
            $stmt->execute([$_SESSION['user_id']]);
            $balance_info = ['current_balance' => 0, 'pending_holds' => 0];
        }
        
        $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $payment_credentials = $stmt->fetchAll();
        
        // Check if in wishlist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ? AND vehicle_id = ?");
        $stmt->execute([$_SESSION['user_id'], $vehicle_id]);
        $is_in_wishlist = $stmt->fetchColumn() > 0;
    }
    
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
    $_SESSION['error_message'] = 'Error loading vehicle details';
    redirect(SITE_URL . '/index.php');
}

include 'includes/header.php';
?>

<div class="container">
    <!-- Vehicle Title -->
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
        <div>
            <h1 style="margin-bottom: 10px;"><?php echo htmlspecialchars($vehicle_title); ?></h1>
            <p style="color: #666; margin-bottom: 10px;">
                <i class="fas fa-map-marker-alt"></i> 
                <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state'] . ', ' . $vehicle['pickup_country']); ?>
                <?php if ($vehicle['avg_rating']): ?>
                    <span style="margin-left: 20px; color: var(--warning-color);">
                        <i class="fas fa-star"></i> 
                        <?php echo number_format($vehicle['avg_rating'], 1); ?> 
                        (<?php echo $vehicle['review_count']; ?> reviews)
                    </span>
                <?php endif; ?>
            </p>
            <p style="color: #666; margin-bottom: 10px;">
                <i class="fas fa-car"></i> 
                <?php echo htmlspecialchars(ucfirst($vehicle['vehicle_type']) . ' • ' . ucfirst($vehicle['transmission']) . ' • ' . ucfirst($vehicle['fuel_type'])); ?>
            </p>
        </div>
        <?php if (isRenter()): ?>
            <button class="btn btn-outline" onclick="toggleWishlist(<?php echo $vehicle_id; ?>)" id="wishlistBtn">
                <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                <?php echo $is_in_wishlist ? 'Saved' : 'Save'; ?>
            </button>
        <?php endif; ?>
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
                             id="mainPhoto"
                             style="width: 100%; height: 400px; object-fit: cover; cursor: pointer;"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                    </div>
                    
                    <?php if (count($photos) > 1): ?>
                        <div class="grid grid-4" style="gap: 10px; margin-top: 10px;">
                            <?php foreach ($photos as $index => $photo): ?>
                                <div style="border-radius: 8px; overflow: hidden; border: 2px solid transparent;" class="photo-thumb" data-index="<?php echo $index; ?>">
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>" 
                                         alt="Vehicle photo" 
                                         style="width: 100%; height: 100px; object-fit: cover; cursor: pointer;"
                                         onclick="changeMainPhoto('<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>', <?php echo $index; ?>)">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background-color: #f0f0f0; height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-car" style="font-size: 64px; color: #ccc;"></i>
                    </div>
                <?php endif; ?>
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
                    <p style="margin: 0;"><strong>Mileage Limit:</strong> <?php echo $vehicle['mileage_limit_per_day']; ?> miles/day</p>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                        Extra miles: <?php echo formatCurrency($vehicle['extra_mileage_fee']); ?> per mile
                    </p>
                </div>
            </div>
            
            <!-- Description -->
            <div class="card">
                <h2><i class="fas fa-align-left"></i> Description</h2>
                <p style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($vehicle['description']); ?></p>
            </div>
            
            <!-- Features -->
            <?php if ($vehicle['vehicle_features']): ?>
                <div class="card">
                    <h2><i class="fas fa-check-circle"></i> Features</h2>
                    <p style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($vehicle['vehicle_features']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Vehicle Rules -->
            <?php if ($vehicle['vehicle_rules']): ?>
                <div class="card">
                    <h2><i class="fas fa-list-check"></i> Vehicle Rules</h2>
                    <p style="line-height: 1.8; white-space: pre-wrap;"><?php echo htmlspecialchars($vehicle['vehicle_rules']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Owner Info -->
            <div class="card">
                <h2><i class="fas fa-user"></i> Hosted by <?php echo htmlspecialchars($vehicle['owner_name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($vehicle['owner_email']); ?></p>
            </div>
            
            <!-- Reviews -->
            <?php if (!empty($reviews)): ?>
                <div class="card">
                    <h2><i class="fas fa-star"></i> Renter Reviews (<?php echo count($reviews); ?>)</h2>
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
        
        <!-- Right Column - Booking Card -->
        <div>
            <div class="card" style="position: sticky; top: 80px;">
                <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    <div class="listing-price" style="font-size: 32px;">
                        <?php echo formatCurrency($vehicle['daily_price']); ?>
                        <span style="font-size: 16px; font-weight: normal;"> / day</span>
                    </div>
                    <p style="color: #666; margin: 5px 0;">Security Deposit: <?php echo formatCurrency($vehicle['security_deposit']); ?></p>
                </div>
                
                <?php if (!isLoggedIn()): ?>
                    <!-- Non-logged in users -->
                    <div style="background-color: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                        <p style="margin-bottom: 15px; font-weight: bold;">Sign in to rent this vehicle</p>
                        <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="<?php echo SITE_URL; ?>/register.php?type=renter" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-user-plus"></i> Create Account
                        </a>
                    </div>
                <?php elseif (isRenter()): ?>
                    <!-- Balance Display -->
                    <div style="background-color: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 14px;"><strong>Your Balance:</strong></p>
                        <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: var(--success-color);">
                            <?php echo formatCurrency($balance_info['current_balance']); ?>
                        </p>
                        <?php if ($balance_info['pending_holds'] > 0): ?>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                Pending: <?php echo formatCurrency($balance_info['pending_holds']); ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/guest/balance.php" style="font-size: 12px; color: var(--primary-color);">Manage Balance</a>
                    </div>
                    
                    <!-- Booking Form -->
                    <form method="POST" action="<?php echo SITE_URL; ?>/guest/trip_checkout.php" id="bookingForm">
                        <input type="hidden" name="vehicle_id" value="<?php echo $vehicle_id; ?>">
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-check"></i> Pickup Date</label>
                            <input type="date" name="pickup_date" id="pickup_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-times"></i> Return Date</label>
                            <input type="date" name="return_date" id="return_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-credit-card"></i> Payment Method</label>
                            <select name="payment_credential_id" class="form-control" required>
                                <option value="">Select payment method</option>
                                <?php foreach ($payment_credentials as $cred): ?>
                                    <option value="<?php echo $cred['credential_id']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $cred['credential_type'])); ?> - 
                                        <?php echo htmlspecialchars($cred['credential_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 18px;">
                            <i class="fas fa-calendar-check"></i> Proceed to Checkout
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Logged in as owner or admin -->
                    <div style="background-color: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center;">
                        <p>To rent this vehicle, please login as a renter.</p>
                    </div>
                <?php endif; ?>
                
                <p style="text-align: center; color: #666; font-size: 12px; margin-top: 15px;">
                    <i class="fas fa-shield-alt"></i> Secure payment with escrow protection
                </p>
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

<?php if (isRenter()): ?>
function toggleWishlist(listingId) {
    fetch('<?php echo SITE_URL; ?>/guest/wishlist_toggle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'vehicle_id=' + listingId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('wishlistBtn');
            const icon = btn.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas');
                btn.innerHTML = '<i class="fas fa-heart"></i> Saved';
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                btn.innerHTML = '<i class="far fa-heart"></i> Save';
            }
        } else {
            alert(data.message || 'Error updating wishlist');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating wishlist');
    });
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
