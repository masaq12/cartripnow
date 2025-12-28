<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isGuest()) {
    redirect(SITE_URL . '/login.php');
}

$trip_id = $_GET['trip_id'] ?? 0;
$pageTitle = 'Write Review - Car Trip Now';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        
        // Verify booking belongs to guest and is completed
        $stmt = $pdo->prepare("
            SELECT b.*, l.owner_id, l.year, l.make, l.model
            FROM trips b
            JOIN vehicles l ON b.vehicle_id = l.vehicle_id
            WHERE b.trip_id = ? AND b.renter_id = ? AND b.trip_status = 'completed'
        ");
        $stmt->execute([$trip_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            throw new Exception('Invalid booking or not eligible for review');
        }
        
        // Check if already reviewed
        $stmt = $pdo->prepare("
            SELECT review_id FROM reviews 
            WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'renter_to_owner'
        ");
        $stmt->execute([$trip_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            throw new Exception('You have already reviewed this booking');
        }
        
        // Create review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (trip_id, reviewer_id, reviewee_id, review_type, rating, comment, created_at)
            VALUES (?, ?, ?, 'renter_to_owner', ?, ?, NOW())
        ");
        $stmt->execute([
            $trip_id,
            $_SESSION['user_id'],
            $booking['owner_id'],
            $rating,
            $comment
        ]);
        
        $_SESSION['success_message'] = 'Thank you for your review!';
        redirect(SITE_URL . '/guest/bookings.php');
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get booking details
    $stmt = $pdo->prepare("
        SELECT b.*,  l.year, l.make, l.model, l.pickup_city, l.pickup_country,
        h.full_name as host_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = l.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM trips b
        JOIN vehicles l ON b.vehicle_id = l.vehicle_id
        JOIN users h ON b.owner_id = h.user_id
        WHERE b.trip_id = ? AND b.renter_id = ? AND b.trip_status = 'completed'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error_message'] = 'Booking not found or not eligible for review';
        redirect(SITE_URL . '/guest/bookings.php');
    }
    
    // Check if already reviewed
    $stmt = $pdo->prepare("
        SELECT review_id FROM reviews 
        WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'renter_to_owner'
    ");
    $stmt->execute([$trip_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = 'You have already reviewed this booking';
        redirect(SITE_URL . '/guest/bookings.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading booking details';
    redirect(SITE_URL . '/guest/bookings.php');
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
</style>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-star"></i> Write a Review
    </h1>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Booking Info -->
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
                
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <p style="margin: 0; color: #666; font-size: 14px;">Host</p>
                    <p style="margin: 5px 0; font-weight: bold;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['host_name']); ?>
                    </p>
                </div>
            </div>
            
            <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px;">
                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                    <i class="fas fa-info-circle"></i>
                    Your review helps future guests make informed decisions and helps hosts improve their service.
                </p>
            </div>
        </div>
        
        <!-- Review Form -->
        <div class="card">
            <h2><i class="fas fa-edit"></i> Your Review</h2>
            
            <form method="POST" style="margin-top: 20px;">
                <div class="form-group">
                    <label class="form-label">Overall Rating *</label>
                    <div class="star-rating">
                        <input type="radio" name="rating" id="star5" value="5" required>
                        <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" name="rating" id="star4" value="4">
                        <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" name="rating" id="star3" value="3">
                        <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" name="rating" id="star2" value="2">
                        <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" name="rating" id="star1" value="1">
                        <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                    </div>
                    <small style="color: #666;">Click to select your rating</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Your Comments *</label>
                    <textarea name="comment" class="form-control" rows="8" required 
                              placeholder="Tell us about your experience... Was the listing as described? How was the host? Would you recommend this place to others?"></textarea>
                    <small style="color: #666;">Minimum 20 characters</small>
                </div>
                
                <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">
                        <i class="fas fa-exclamation-triangle"></i> Review Guidelines
                    </h4>
                    <ul style="margin: 0; padding-left: 20px; color: #856404; font-size: 14px; line-height: 1.8;">
                        <li>Be honest and fair in your review</li>
                        <li>Focus on your personal experience</li>
                        <li>Avoid profanity and offensive language</li>
                        <li>Don't include personal contact information</li>
                        <li>Reviews are public and cannot be edited once submitted</li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                    <a href="bookings.php" class="btn btn-outline" style="flex: 1; text-align: center;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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

// Form validation
document.querySelector('form').addEventListener('submit', (e) => {
    const comment = document.querySelector('textarea[name="comment"]').value.trim();
    const rating = document.querySelector('input[name="rating"]:checked');
    
    if (!rating) {
        e.preventDefault();
        alert('Please select a rating');
        return false;
    }
    
    if (comment.length < 20) {
        e.preventDefault();
        alert('Please write at least 20 characters in your review');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
