<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter()) {
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
        WHERE v.vehicle_id = ? AND v.status = 'active'
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error_message'] = 'Vehicle not found or not available';
        redirect(SITE_URL . '/guest/browse.php');
    }
    
    // Get vehicle photos
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, display_order ASC");
    $stmt->execute([$vehicle_id]);
    $photos = $stmt->fetchAll();
    
    // Get renter balance and payment credentials
    $stmt = $pdo->prepare("SELECT current_balance, pending_holds FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance_info = $stmt->fetch();
    
    // If no balance exists, create one
    if (!$balance_info) {
        $stmt = $pdo->prepare("INSERT INTO renter_balances (user_id, current_balance) VALUES (?, 0.00)");
        $stmt->execute([$_SESSION['user_id']]);
        $balance_info = ['current_balance' => 0, 'pending_holds' => 0];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE user_id = ? AND status = 'active' ORDER BY credential_id DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $payment_credentials = $stmt->fetchAll();
    
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
    
    $pageTitle = htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']) . ' - Car Trip Now';
    
} catch (Exception $e) {
    echo '<pre>Exception: ' . $e->getMessage() . '</pre>';
    echo '<pre>File: ' . $e->getFile() . ' Line: ' . $e->getLine() . '</pre>';
    echo '<pre>Trace: ' . $e->getTraceAsString() . '</pre>';
    exit;
}

include '../includes/header.php';
?>

<div class="container">
    <!-- Vehicle Title -->
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
        <h1 style="margin: 0;">
            <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
        </h1>
        
    </div>
    <p style="color: #666; margin-bottom: 5px;">
        <i class="fas fa-car"></i> 
        <?php echo htmlspecialchars(ucfirst($vehicle['vehicle_type'])); ?> • 
        <?php echo htmlspecialchars(ucfirst($vehicle['transmission'])); ?> • 
        <?php echo htmlspecialchars(ucfirst($vehicle['fuel_type'])); ?>
    </p>
    <p style="color: #666; margin-bottom: 20px;">
        <i class="fas fa-map-marker-alt"></i> 
        <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state']); ?>
        <?php if ($vehicle['avg_rating']): ?>
            <span style="margin-left: 20px; color: var(--warning-color);">
                <i class="fas fa-star"></i> 
                <?php echo number_format($vehicle['avg_rating'], 1); ?> 
                (<?php echo $vehicle['review_count']; ?> reviews)
            </span>
        <?php endif; ?>
    </p>
    
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
                                         style="width: 100%; height: 100px; object-fit: cover; cursor: pointer;">
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
            </div>
            
            <!-- Description -->
            <div class="card">
                <h2><i class="fas fa-align-left"></i> Description</h2>
                <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['description'])); ?></p>
            </div>
            
            <!-- Features -->
            <?php if ($vehicle['vehicle_features']): ?>
                <div class="card">
                    <h2><i class="fas fa-list-check"></i> Features</h2>
                    <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['vehicle_features'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Vehicle Rules -->
            <?php if ($vehicle['vehicle_rules']): ?>
                <div class="card">
                    <h2><i class="fas fa-exclamation-circle"></i> Vehicle Rules</h2>
                    <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($vehicle['vehicle_rules'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Owner Info -->
            <div class="card">
                <h2><i class="fas fa-user"></i> Hosted by <?php echo htmlspecialchars($vehicle['owner_name']); ?></h2>
                <p>Contact: <?php echo htmlspecialchars($vehicle['owner_email']); ?></p>
            </div>
            
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
        
        <!-- Right Column - Booking Card -->
        <div>
            <div class="card" style="position: sticky; top: 80px;">
                <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    <div class="listing-price" style="font-size: 32px;">
                        <?php echo formatCurrency($vehicle['daily_price']); ?>
                        <span style="font-size: 16px; font-weight: normal;"> / day</span>
                    </div>
                    <p style="color: #666; margin: 5px 0;">Security Deposit: <?php echo formatCurrency($vehicle['security_deposit']); ?></p>
                    <p style="color: #666; margin: 5px 0;">Mileage Limit: <?php echo $vehicle['mileage_limit_per_day']; ?> mi/day</p>
                </div>
                
                <!-- Balance Display -->
                <div style="background-color: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; font-size: 14px;"><strong>Your Balance:</strong></p>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: var(--success-color);">
                        <?php echo formatCurrency($balance_info['current_balance']); ?>
                    </p>
                    <?php if ($balance_info['pending_holds'] > 0): ?>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                            Pending Holds: <?php echo formatCurrency($balance_info['pending_holds']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Booking Form -->
                <form method="POST" action="trip_checkout.php" id="bookingForm">
                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicle_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Pickup Date</label>
                        <input type="date" name="pickup_date" id="pickup_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Return Date</label>
                        <input type="date" name="return_date" id="return_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Credential</label>
                        <select name="payment_credential_id" class="form-control" required>
                            <option value="">Select payment credential</option>
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
                
                <p style="text-align: center; color: #666; font-size: 12px; margin-top: 15px;">
                    <i class="fas fa-shield-alt"></i> Secure payment with escrow protection
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleWishlist(vehicleId, btn) {
    fetch('<?php echo SITE_URL; ?>/guest/wishlist_toggle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'vehicle_id=' + vehicleId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#ff385c';
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                icon.style.color = '#666';
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
</script>

<?php include '../includes/footer.php'; ?>
