<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$trip_id = $_GET['trip_id'] ?? 0;
$pageTitle = 'Review Renter - Car Trip Now';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        $trip_id = (int)$_POST['trip_id'];
        
        // Get trip details and verify ownership
        $stmt = $pdo->prepare("
            SELECT t.*, r.full_name as renter_name, v.make, v.model, v.year
            FROM trips t
            JOIN users r ON t.renter_id = r.user_id
            JOIN vehicles v ON t.vehicle_id = v.vehicle_id
            WHERE t.trip_id = ? AND t.owner_id = ? AND t.trip_status = 'completed'
        ");
        $stmt->execute([$trip_id, $_SESSION['user_id']]);
        $trip = $stmt->fetch();
        
        if (!$trip) {
            throw new Exception('Trip not found or not completed');
        }
        
        // Check if already reviewed
        $stmt = $pdo->prepare("
            SELECT review_id FROM reviews 
            WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'owner_to_renter'
        ");
        $stmt->execute([$trip_id, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception('You have already reviewed this renter');
        }
        
        // Create review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (
                trip_id, reviewer_id, reviewee_id, review_type,
                rating, cleanliness_rating, communication_rating, vehicle_condition_rating,
                comment
            ) VALUES (?, ?, ?, 'owner_to_renter', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $trip_id,
            $_SESSION['user_id'],
            $trip['renter_id'],
            (int)$_POST['rating'],
            (int)$_POST['cleanliness_rating'],
            (int)$_POST['communication_rating'],
            (int)$_POST['vehicle_condition_rating'],
            sanitizeInput($_POST['comment'])
        ]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Review submitted successfully!';
        redirect(SITE_URL . '/host/bookings.php');
        
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
        SELECT t.*, r.full_name as renter_name, v.make, v.model, v.year,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips t
        JOIN users r ON t.renter_id = r.user_id
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        WHERE t.trip_id = ? AND t.owner_id = ? AND t.trip_status = 'completed'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        $_SESSION['error_message'] = 'Trip not found or not completed';
        redirect(SITE_URL . '/host/bookings.php');
    }
    
    // Check if already reviewed
    $stmt = $pdo->prepare("
        SELECT review_id FROM reviews 
        WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'owner_to_renter'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = 'You have already reviewed this renter';
        redirect(SITE_URL . '/host/bookings.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading trip details';
    redirect(SITE_URL . '/host/bookings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-star"></i> Review Renter
    </h1>
    
    <div class="grid grid-2" style="gap: 30px;">
        <div>
            <!-- Trip Info Card -->
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Trip Information</h2>
                
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
                            <strong>Renter:</strong> <?php echo htmlspecialchars($trip['renter_name']); ?>
                        </p>
                        <p style="margin: 5px 0; color: #666;">
                            <strong>Trip:</strong> <?php echo date('M d', strtotime($trip['pickup_date'])); ?> - <?php echo date('M d, Y', strtotime($trip['return_date'])); ?>
                        </p>
                        <p style="margin: 5px 0; color: #666;">
                            <strong>Duration:</strong> <?php echo $trip['trip_duration_days']; ?> days
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div>
            <!-- Review Form -->
            <form method="POST" class="card">
                <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
                
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
                    <label class="form-label">Vehicle Condition on Return *</label>
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
                    <textarea name="comment" class="form-control" rows="6" required placeholder="Share your experience with this renter..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                    <a href="bookings.php" class="btn btn-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
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
