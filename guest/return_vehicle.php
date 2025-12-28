<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter()) {
    redirect(SITE_URL . '/login.php');
}

$trip_id = $_GET['trip_id'] ?? 0;
$pageTitle = 'Return Vehicle - Car Trip Now';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        $trip_id = (int)$_POST['trip_id'];
        
        // Get trip details
        $stmt = $pdo->prepare("
            SELECT t.*, v.owner_id, v.make, v.model, v.year
            FROM trips t
            JOIN vehicles v ON t.vehicle_id = v.vehicle_id
            WHERE t.trip_id = ? AND t.renter_id = ? AND t.trip_status = 'active'
        ");
        $stmt->execute([$trip_id, $_SESSION['user_id']]);
        $trip = $stmt->fetch();
        
        if (!$trip) {
            throw new Exception('Trip not found or not active');
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
        $stmt = $pdo->prepare("
            UPDATE escrow 
            SET status = 'released',
                released_at = NOW(),
                release_notes = 'Trip completed by renter'
            WHERE trip_id = ? AND status = 'held'
        ");
        $stmt->execute([$trip_id]);
        
        // Calculate owner earnings
        $platform_fee = $trip['platform_fee'];
        $owner_earnings = $trip['total_days_cost'] + $trip['insurance_fee'];
        
        // Update renter balance - remove pending hold and release security deposit
        $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
        $stmt->execute([$trip['renter_id']]);
        $renter_balance_before = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            UPDATE renter_balances 
            SET pending_holds = pending_holds - ?,
                current_balance = current_balance + ?
            WHERE user_id = ?
        ");
        $stmt->execute([$trip['total_amount'], $trip['security_deposit'], $trip['renter_id']]);
        
        // Record deposit release transaction for renter
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
        
        // Submit review if provided
        if (!empty($_POST['rating']) && !empty($_POST['comment'])) {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (
                    trip_id, reviewer_id, reviewee_id, review_type,
                    rating, cleanliness_rating, communication_rating, vehicle_condition_rating,
                    comment
                ) VALUES (?, ?, ?, 'renter_to_owner', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $trip_id,
                $_SESSION['user_id'],
                $trip['owner_id'],
                (int)$_POST['rating'],
                (int)$_POST['cleanliness_rating'],
                (int)$_POST['communication_rating'],
                (int)$_POST['vehicle_condition_rating'],
                sanitizeInput($_POST['comment'])
            ]);
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Vehicle returned successfully! Payment has been released to the owner.';
        redirect(SITE_URL . '/guest/trips.php');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get trip details
    $stmt = $pdo->prepare("
        SELECT t.*, v.make, v.model, v.year, v.pickup_city, v.pickup_state,
        o.full_name as owner_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users o ON t.owner_id = o.user_id
        WHERE t.trip_id = ? AND t.renter_id = ? AND t.trip_status = 'active'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        $_SESSION['error_message'] = 'Trip not found or not ready for return';
        redirect(SITE_URL . '/guest/trips.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading trip details';
    redirect(SITE_URL . '/guest/trips.php');
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-sign-out-alt"></i> Return Vehicle & Leave Review
    </h1>
    
    <form method="POST">
        <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
        
        <div class="grid grid-2" style="gap: 30px;">
            <!-- Left Column - Trip Summary -->
            <div>
                <div class="card">
                    <h2><i class="fas fa-info-circle"></i> Trip Summary</h2>
                    
                    <div style="display: flex; gap: 20px; margin-top: 20px;">
                        <div style="flex-shrink: 0;">
                            <?php if ($trip['primary_photo']): ?>
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($trip['primary_photo']); ?>" 
                                     alt="Vehicle" 
                                     style="width: 150px; height: 100px; object-fit: cover; border-radius: 8px;"
                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                            <?php else: ?>
                                <div style="width: 150px; height: 100px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-car" style="font-size: 32px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h3 style="margin: 0 0 10px 0;">
                                <?php echo htmlspecialchars($trip['year'] . ' ' . $trip['make'] . ' ' . $trip['model']); ?>
                            </h3>
                            <p style="margin: 5px 0; color: #666;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($trip['pickup_city'] . ', ' . $trip['pickup_state']); ?>
                            </p>
                            <p style="margin: 5px 0; color: #666;">
                                <i class="fas fa-user"></i> 
                                Owner: <?php echo htmlspecialchars($trip['owner_name']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-top: 20px;">
                        <div class="grid grid-2" style="gap: 20px;">
                            <div>
                                <p style="margin: 0; color: #666; font-size: 14px;">Pickup</p>
                                <p style="margin: 5px 0; font-weight: bold;">
                                    <?php echo date('M d, Y g:i A', strtotime($trip['pickup_date'] . ' ' . $trip['pickup_time'])); ?>
                                </p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666; font-size: 14px;">Return</p>
                                <p style="margin: 5px 0; font-weight: bold;">
                                    <?php echo date('M d, Y g:i A', strtotime($trip['return_date'] . ' ' . $trip['return_time'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-2" style="gap: 20px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                            <div>
                                <p style="margin: 0; color: #666; font-size: 14px;">Duration</p>
                                <p style="margin: 5px 0; font-weight: bold;">
                                    <?php echo $trip['trip_duration_days']; ?> days
                                </p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666; font-size: 14px;">Total Amount</p>
                                <p style="margin: 5px 0; font-weight: bold; color: var(--primary-color);">
                                    <?php echo formatCurrency($trip['total_amount']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center;">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: var(--primary-color); margin-bottom: 15px;"></i>
                        <h3 style="margin: 0 0 10px 0;">Payment Release</h3>
                        <p style="margin: 0; color: #666;">
                            When you confirm the return, the payment of <strong><?php echo formatCurrency($trip['total_amount'] - $trip['security_deposit']); ?></strong> 
                            will be released to the owner and your security deposit of <strong><?php echo formatCurrency($trip['security_deposit']); ?></strong> 
                            will be returned to your account.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Review Form -->
            <div>
                <div class="card">
                    <h2 style="margin-bottom: 20px;">Rate Your Experience</h2>
                    
                    <!-- Overall Rating -->
                    <div class="form-group">
                        <label class="form-label">Overall Rating *</label>
                        <div style="display: flex; gap: 10px; font-size: 32px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; color: #ddd;" class="star-rating" data-rating="<?php echo $i; ?>" data-field="rating">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                    <i class="far fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Cleanliness Rating -->
                    <div class="form-group">
                        <label class="form-label">Vehicle Cleanliness *</label>
                        <div style="display: flex; gap: 10px; font-size: 24px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; color: #ddd;" class="star-rating" data-rating="<?php echo $i; ?>" data-field="cleanliness_rating">
                                    <input type="radio" name="cleanliness_rating" value="<?php echo $i; ?>" required style="display: none;">
                                    <i class="far fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Communication Rating -->
                    <div class="form-group">
                        <label class="form-label">Communication *</label>
                        <div style="display: flex; gap: 10px; font-size: 24px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; color: #ddd;" class="star-rating" data-rating="<?php echo $i; ?>" data-field="communication_rating">
                                    <input type="radio" name="communication_rating" value="<?php echo $i; ?>" required style="display: none;">
                                    <i class="far fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Vehicle Condition Rating -->
                    <div class="form-group">
                        <label class="form-label">Vehicle Condition *</label>
                        <div style="display: flex; gap: 10px; font-size: 24px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; color: #ddd;" class="star-rating" data-rating="<?php echo $i; ?>" data-field="vehicle_condition_rating">
                                    <input type="radio" name="vehicle_condition_rating" value="<?php echo $i; ?>" required style="display: none;">
                                    <i class="far fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Comment -->
                    <div class="form-group">
                        <label class="form-label">Your Review *</label>
                        <textarea name="comment" class="form-control" rows="6" required placeholder="Share your experience..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; padding: 18px; font-size: 18px;" onclick="return confirm('Are you sure you want to return this vehicle? Payment will be released to the owner.');">
                    <i class="fas fa-check-circle"></i> Confirm Return & Submit Review
                </button>
                
                <p style="text-align: center; color: #666; font-size: 13px; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i> This action cannot be undone
                </p>
            </div>
        </div>
    </form>
</div>

<script>
// Star rating functionality
document.querySelectorAll('.star-rating').forEach(label => {
    label.addEventListener('click', function() {
        const rating = this.dataset.rating;
        const field = this.dataset.field;
        const input = this.querySelector('input');
        input.checked = true;
        
        // Update stars for this field
        document.querySelectorAll(`.star-rating[data-field="${field}"]`).forEach((star, index) => {
            const starIcon = star.querySelector('i');
            if (index < rating) {
                starIcon.classList.remove('far');
                starIcon.classList.add('fas');
                star.style.color = '#ffc107';
            } else {
                starIcon.classList.remove('fas');
                starIcon.classList.add('far');
                star.style.color = '#ddd';
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
