<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isGuest()) {
    redirect(SITE_URL . '/login.php');
}

$trip_id = $_GET['trip_id'] ?? 0;
$pageTitle = 'Check Out & Review - Car Trip Now';

// Handle checkout and review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        $trip_id = (int)$_POST['trip_id'];
        $renter_id = $_SESSION['user_id'];
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        
        // Verify booking belongs to guest and is eligible for checkout
        $stmt = $pdo->prepare("
            SELECT b.*, l.owner_id, l.year, l.make, l.model, l.pickup_city, l.pickup_country,
            FROM trips b
            JOIN vehicles l ON b.vehicle_id = l.vehicle_id
            WHERE b.trip_id = ? 
            AND b.renter_id = ? 
            AND b.trip_status = 'checked_in'
            AND b.return_date <= CURDATE()
        ");
        $stmt->execute([$trip_id, $renter_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            throw new Exception('Invalid booking or checkout not available yet');
        }
        
        // Update booking status to completed
        $stmt = $pdo->prepare("
            UPDATE trips 
            SET trip_status = 'completed', 
                checked_out_at = NOW(),
                updated_at = NOW()
            WHERE trip_id = ?
        ");
        $stmt->execute([$trip_id]);
        
        // Release payment from escrow
        $stmt = $pdo->prepare("
            UPDATE escrow 
            SET status = 'released', 
                released_at = NOW(),
                release_notes = 'Guest checkout with review'
            WHERE trip_id = ? AND status = 'held'
        ");
        $stmt->execute([$trip_id]);
        
        // Calculate host earnings (total - service fee)
        $platform_fee = $booking['service_fee'];
        $host_earnings = $booking['total_amount'] - $platform_fee;
        
        // Update guest balance - remove pending hold
        $stmt = $pdo->prepare("
            UPDATE renter_balances 
            SET pending_holds = pending_holds - ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$booking['total_amount'], $renter_id]);
        
        // Update host balance - move from pending to available
        $stmt = $pdo->prepare("
            UPDATE host_balances 
            SET available_balance = available_balance + ?,
                pending_balance = pending_balance - ?,
                total_earned = total_earned + ?,
                platform_fees_paid = platform_fees_paid + ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $host_earnings,
            $host_earnings,
            $host_earnings,
            $platform_fee,
            $booking['owner_id']
        ]);
        
        // Get host balance for transaction
        $stmt = $pdo->prepare("SELECT available_balance FROM host_balances WHERE user_id = ?");
        $stmt->execute([$booking['owner_id']]);
        $host_new_balance = $stmt->fetchColumn();
        
        // Record transaction for host
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, transaction_type, amount, balance_before, balance_after, 
                reference_type, reference_id, description
            ) VALUES (?, 'earning', ?, ?, ?, 'booking', ?, ?)
        ");
        $stmt->execute([
            $booking['owner_id'],
            $host_earnings,
            $host_new_balance - $host_earnings,
            $host_new_balance,
            $trip_id,
            'Earning from booking #' . $trip_id . ' (guest checkout)'
        ]);
        
        // If rating and review provided, save it
        if ($rating > 0 && !empty($comment)) {
            // Check if already reviewed
            $stmt = $pdo->prepare("
                SELECT review_id FROM reviews 
                WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'renter_to_owner'
            ");
            $stmt->execute([$trip_id, $renter_id]);
            
            if (!$stmt->fetch()) {
                // Create review
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (trip_id, reviewer_id, reviewee_id, review_type, rating, comment, created_at)
                    VALUES (?, ?, ?, 'renter_to_owner', ?, ?, NOW())
                ");
                $stmt->execute([
                    $trip_id,
                    $renter_id,
                    $booking['owner_id'],
                    $rating,
                    $comment
                ]);
            }
        }
        
        $pdo->commit();
        
        if ($rating > 0 && !empty($comment)) {
            $_SESSION['success_message'] = 'Checkout successful! Thank you for your review. Payment has been released to the host.';
        } else {
            $_SESSION['success_message'] = 'Checkout successful! Payment has been released to the host.';
        }
        
        redirect(SITE_URL . '/guest/bookings.php');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
        redirect(SITE_URL . '/guest/checkout.php');
    }
}

// Get booking details
try {
    $pdo = getPDOConnection();
    
    $stmt = $pdo->prepare("
        SELECT b.*, l.year, l.make, l.model, l.pickup_city, l.pickup_country, l.pickup_city, l.pickup_country, l.owner_id,
        h.full_name as host_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = l.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips b
        JOIN vehicles l ON b.vehicle_id = l.vehicle_id
        JOIN users h ON b.owner_id = h.user_id
        WHERE b.trip_id = ? 
        AND b.renter_id = ? 
        AND b.trip_status = 'checked_in'
        AND b.return_date <= CURDATE()
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error_message'] = 'Booking not found or not eligible for checkout';
        redirect(SITE_URL . '/guest/checkout.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading booking details';
    redirect(SITE_URL . '/guest/checkout.php');
}

include '../includes/header.php';
?>

<style>
.star-rating {
    display: flex;
    gap: 10px;
    font-size: 40px;
    margin: 20px 0;
}
.star-rating input[type="radio"] {
    display: none;
}
.star-rating label {
    cursor: pointer;
    color: #ddd;
    transition: color 0.2s;
}
.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input[type="radio"]:checked ~ label {
    color: #ffc107;
}

.checkout-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.checkout-section h2 {
    color: white;
    margin-bottom: 10px;
}

.review-optional {
    background-color: #e3f2fd;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
}
</style>

<div class="container">
    <div style="max-width: 1000px; margin: 0 auto;">
        <h1 style="margin-bottom: 10px;">
            <i class="fas fa-sign-out-alt"></i> Check Out & Review
        </h1>
        <p style="color: #666; margin-bottom: 30px;">Complete your stay and share your experience</p>
        
        <!-- Checkout Summary -->
        <div class="checkout-section">
            <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h2>Ready to Check Out</h2>
            <p style="margin: 0; opacity: 0.9;">
                You're checking out of <strong><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></strong>
            </p>
            <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.8;">
                Payment of <?php echo formatCurrency($booking['total_amount']); ?> will be released to the host
            </p>
        </div>
        
        <div class="grid grid-2" style="gap: 30px;">
            <!-- Booking Details -->
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Your Stay</h2>
                
                <?php if ($booking['primary_photo']): ?>
                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($booking['primary_photo']); ?>" 
                         alt="<?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?>" 
                         style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-top: 15px;"
                         onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.jpg'">
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></h3>
                    <p style="color: #666; margin: 0;">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($booking['pickup_city'] . ', ' . $booking['pickup_country']); ?>
                    </p>
                </div>
                
                <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <div class="grid grid-2" style="gap: 15px;">
                        <div>
                            <p style="margin: 0; color: #666; font-size: 14px;">Check-in</p>
                            <p style="margin: 5px 0; font-weight: bold;">
                                <?php echo date('M d, Y', strtotime($booking['pickup_date'])); ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0; color: #666; font-size: 14px;">Check-out</p>
                            <p style="margin: 5px 0; font-weight: bold;">
                                <?php echo date('M d, Y', strtotime($booking['return_date'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-3" style="gap: 15px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                        <div>
                            <p style="margin: 0; color: #666; font-size: 14px;">Nights</p>
                            <p style="margin: 5px 0; font-weight: bold;">
                                <?php echo $booking['trip_duration_days']; ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0; color: #666; font-size: 14px;">Guests</p>
                            <p style="margin: 5px 0; font-weight: bold;">
                                <?php echo $booking['num_guests']; ?>
                            </p>
                        </div>
                        <div>
                            <p style="margin: 0; color: #666; font-size: 14px;">Host</p>
                            <p style="margin: 5px 0; font-weight: bold;">
                                <?php echo htmlspecialchars($booking['host_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div style="background-color: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #2e7d32;">
                        <i class="fas fa-money-bill-wave"></i> Payment Details
                    </h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="color: #666;">Total Amount:</span>
                        <strong style="color: #2e7d32; font-size: 18px;"><?php echo formatCurrency($booking['total_amount']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <span style="color: #666;">Status:</span>
                        <span style="color: #ff9800; font-weight: bold;"><i class="fas fa-lock"></i> In Escrow</span>
                    </div>
                </div>
            </div>
            
            <!-- Review Form -->
            <div class="card">
                <h2><i class="fas fa-star"></i> Leave a Review (Optional)</h2>
                
                <div class="review-optional">
                    <p style="margin: 0; color: #1976d2; font-size: 14px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>You can check out without leaving a review</strong>, but we encourage you to share your experience to help future guests and the host improve.
                    </p>
                </div>
                
                <form method="POST" id="checkoutForm">
                    <input type="hidden" name="trip_id" value="<?php echo $booking['trip_id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Rate Your Stay</label>
                        <div class="star-rating">
                            <input type="radio" name="rating" id="star5" value="5">
                            <label for="star5" title="5 stars - Excellent"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star4" value="4">
                            <label for="star4" title="4 stars - Very Good"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star3" value="3">
                            <label for="star3" title="3 stars - Good"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star2" value="2">
                            <label for="star2" title="2 stars - Fair"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star1" value="1">
                            <label for="star1" title="1 star - Poor"><i class="fas fa-star"></i></label>
                        </div>
                        <small style="color: #666;">Click to select your rating (optional)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Your Comments</label>
                        <textarea name="comment" id="reviewComment" class="form-control" rows="6" 
                                  placeholder="Share your experience... Was the listing as described? How was the host? Would you recommend this place to others? (optional)"></textarea>
                        <small style="color: #666;" id="charCount">Minimum 20 characters for review</small>
                    </div>
                    
                    <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <h4 style="margin: 0 0 10px 0; color: #856404;">
                            <i class="fas fa-exclamation-triangle"></i> Important
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; color: #856404; font-size: 14px; line-height: 1.8;">
                            <li>Checking out will complete your stay</li>
                            <li>Payment will be released to the host</li>
                            <li>You can skip the review and add it later from My Bookings</li>
                            <li>Reviews are public and help the community</li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-success" style="flex: 2;">
                            <i class="fas fa-check"></i> Complete Checkout
                        </button>
                        <a href="checkout.php" class="btn btn-outline" style="flex: 1; text-align: center;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Star rating interaction - FIXED
const starLabels = document.querySelectorAll('.star-rating label');
const starInputs = document.querySelectorAll('.star-rating input');

// Update star colors based on selection
function updateStars() {
    const checked = document.querySelector('.star-rating input:checked');
    starLabels.forEach((label, index) => {
        const input = starInputs[starInputs.length - 1 - index];
        if (checked) {
            const checkedValue = parseInt(checked.value);
            const inputValue = parseInt(input.value);
            label.style.color = inputValue <= checkedValue ? '#ffc107' : '#ddd';
        } else {
            label.style.color = '#ddd';
        }
    });
}

// Hover effect
starLabels.forEach((label, index) => {
    label.addEventListener('mouseover', () => {
        starLabels.forEach((l, i) => {
            const input = starInputs[starInputs.length - 1 - i];
            const inputValue = parseInt(input.value);
            const hoverValue = parseInt(starInputs[starInputs.length - 1 - index].value);
            l.style.color = inputValue <= hoverValue ? '#ffc107' : '#ddd';
        });
    });
    
    label.addEventListener('mouseout', () => {
        updateStars();
    });
    
    label.addEventListener('click', () => {
        setTimeout(updateStars, 10);
    });
});

// Initialize
updateStars();

// Character count for review
const reviewComment = document.getElementById('reviewComment');
const charCount = document.getElementById('charCount');

reviewComment.addEventListener('input', () => {
    const length = reviewComment.value.trim().length;
    if (length === 0) {
        charCount.textContent = 'Minimum 20 characters for review';
        charCount.style.color = '#666';
    } else if (length < 20) {
        charCount.textContent = `${length}/20 characters (${20 - length} more needed)`;
        charCount.style.color = '#f44336';
    } else {
        charCount.textContent = `${length} characters - looks great!`;
        charCount.style.color = '#4caf50';
    }
});

// Form validation
document.getElementById('checkoutForm').addEventListener('submit', (e) => {
    const comment = reviewComment.value.trim();
    const rating = document.querySelector('input[name="rating"]:checked');
    
    // If user entered a comment or rating, validate both
    if (comment.length > 0 || rating) {
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating if you want to leave a review, or leave both rating and comment empty to checkout without a review.');
            return false;
        }
        
        if (comment.length > 0 && comment.length < 20) {
            e.preventDefault();
            alert('Please write at least 20 characters in your review, or leave it empty to checkout without a review.');
            return false;
        }
    }
    
    // Confirm checkout
    const hasReview = rating && comment.length >= 20;
    const message = hasReview 
        ? 'Complete checkout with your review? Payment will be released to the host.'
        : 'Complete checkout without a review? You can add a review later from My Bookings. Payment will be released to the host.';
    
    if (!confirm(message)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
