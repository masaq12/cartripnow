<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'My Trips - Host - Car Trip Now';

try {
    $pdo = getPDOConnection();
    $owner_id = $_SESSION['user_id'];
    
    // Filter
    $filter = $_GET['filter'] ?? 'all';
    
    // Build query based on filter
    $query = "
        SELECT t.*, 
        v.make, v.model, v.year, v.daily_price,
        r.full_name as renter_name, r.email as renter_email, r.phone as renter_phone
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users r ON t.renter_id = r.user_id
        WHERE t.owner_id = ?
    ";
    
    $params = [$owner_id];
    
    switch($filter) {
        case 'upcoming':
            $query .= " AND t.trip_status = 'confirmed' AND t.pickup_date >= CURDATE()";
            break;
        case 'current':
            $query .= " AND t.trip_status IN ('confirmed', 'active') AND t.pickup_date <= CURDATE() AND t.return_date >= CURDATE()";
            break;
        case 'past':
            $query .= " AND t.trip_status = 'completed'";
            break;
        case 'cancelled':
            $query .= " AND t.trip_status IN ('cancelled')";
            break;
    }
    
    $query .= " ORDER BY t.pickup_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();
    
    // Get stats
    $stats = [
        'total' => $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ?"),
        'upcoming' => $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ? AND trip_status = 'confirmed' AND pickup_date >= CURDATE()"),
        'current' => $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ? AND trip_status IN ('confirmed', 'active') AND pickup_date <= CURDATE() AND return_date >= CURDATE()"),
        'completed' => $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ? AND trip_status = 'completed'"),
    ];
    
    foreach ($stats as $key => $stmt) {
        $stmt->execute([$owner_id]);
        $stats[$key] = $stmt->fetchColumn();
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading trips: ' . $e->getMessage();
    $trips = [];
    $stats = ['total' => 0, 'upcoming' => 0, 'current' => 0, 'completed' => 0];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-calendar-check"></i> My Trips
    </h1>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-calendar"></i>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Trips</div>
        </div>
        <div class="stat-card warning">
            <i class="fas fa-calendar-plus"></i>
            <div class="stat-value"><?php echo number_format($stats['upcoming']); ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
        <div class="stat-card secondary">
            <i class="fas fa-car"></i>
            <div class="stat-value"><?php echo number_format($stats['current']); ?></div>
            <div class="stat-label">Current Trips</div>
        </div>
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="?filter=upcoming" class="btn <?php echo $filter === 'upcoming' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-calendar-plus"></i> Upcoming
            </a>
            <a href="?filter=current" class="btn <?php echo $filter === 'current' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-car"></i> Current Trips
            </a>
            <a href="?filter=past" class="btn <?php echo $filter === 'past' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-history"></i> Past
            </a>
            <a href="?filter=cancelled" class="btn <?php echo $filter === 'cancelled' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-times-circle"></i> Cancelled
            </a>
        </div>
    </div>
    
    <!-- Trips List -->
    <?php if (empty($trips)): ?>
        <div class="card" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-calendar-times" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
            <h2>No trips found</h2>
            <p style="color: #666; margin-bottom: 20px;">
                <?php if ($filter === 'all'): ?>
                    You don't have any trips yet. Make sure your vehicles are active!
                <?php else: ?>
                    No <?php echo $filter; ?> trips at the moment.
                <?php endif; ?>
            </p>
            <a href="<?php echo SITE_URL; ?>/host/listings.php" class="btn btn-primary">
                <i class="fas fa-car"></i> View My Vehicles
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-1" style="gap: 20px;">
            <?php foreach ($trips as $trip): ?>
                <div class="card">
                    <div class="grid grid-2" style="gap: 20px;">
                        <!-- Trip Info -->
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                <div>
                                    <h3 style="margin: 0 0 5px 0;">
                                        <?php echo htmlspecialchars($trip['year'] . ' ' . $trip['make'] . ' ' . $trip['model']); ?>
                                    </h3>
                                    <p style="color: #666; margin: 0;">
                                        Trip #<?php echo $trip['trip_id']; ?>
                                    </p>
                                </div>
                                <div>
                                    <?php
                                    $badge_class = 'badge-info';
                                    if ($trip['trip_status'] === 'confirmed') $badge_class = 'badge-success';
                                    if ($trip['trip_status'] === 'completed') $badge_class = 'badge-success';
                                    if ($trip['trip_status'] === 'cancelled') $badge_class = 'badge-danger';
                                    if ($trip['trip_status'] === 'active') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $trip['trip_status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-2" style="gap: 20px; margin-bottom: 15px;">
                                <div>
                                    <p style="margin: 0; font-size: 12px; color: #666;">PICKUP</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar-check"></i>
                                        <?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px;">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('g:i A', strtotime($trip['pickup_time'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <p style="margin: 0; font-size: 12px; color: #666;">RETURN</p>
                                    <p style="margin: 5px 0; font-weight: bold;">
                                        <i class="fas fa-calendar-times"></i>
                                        <?php echo date('M d, Y', strtotime($trip['return_date'])); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px;">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('g:i A', strtotime($trip['return_time'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span><?php echo $trip['trip_duration_days']; ?> days × <?php echo formatCurrency($trip['daily_rate']); ?></span>
                                    <strong><?php echo formatCurrency($trip['total_days_cost']); ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span>Insurance Fee</span>
                                    <span><?php echo formatCurrency($trip['insurance_fee']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <span>Platform Fee</span>
                                    <span><?php echo formatCurrency($trip['platform_fee']); ?></span>
                                </div>
                                <div style="border-top: 2px solid #ddd; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; font-size: 18px;">
                                    <strong>Total</strong>
                                    <strong><?php echo formatCurrency($trip['total_amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Renter Info -->
                        <div>
                            <h4><i class="fas fa-user"></i> Renter Information</h4>
                            <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px;">
                                <p style="margin: 0 0 10px 0;">
                                    <strong><?php echo htmlspecialchars($trip['renter_name']); ?></strong>
                                </p>
                                <p style="margin: 0 0 10px 0; color: #666;">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($trip['renter_email']); ?>
                                </p>
                                <?php if ($trip['renter_phone']): ?>
                                    <p style="margin: 0 0 10px 0; color: #666;">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($trip['renter_phone']); ?>
                                    </p>
                                <?php endif; ?>
                                <p style="margin: 10px 0 0 0; color: #666;">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Mileage Limit: <?php echo number_format($trip['mileage_limit']); ?> mi
                                </p>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <a href="booking_details.php?id=<?php echo $trip['trip_id']; ?>" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-eye"></i> View Full Details
                                </a>
                            </div>
                            
                            <?php if ($trip['trip_status'] === 'completed'): ?>
                                <?php
                                // Check if owner already reviewed
                                $check_review = $pdo->prepare("
                                    SELECT review_id FROM reviews 
                                    WHERE trip_id = ? AND reviewer_id = ? AND review_type = 'owner_to_renter'
                                ");
                                $check_review->execute([$trip['trip_id'], $_SESSION['user_id']]);
                                $has_reviewed = $check_review->fetch();
                                ?>
                                <div style="margin-top: 10px;">
                                    <?php if (!$has_reviewed): ?>
                                        <a href="review_renter.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-secondary" style="width: 100%;">
                                            <i class="fas fa-star"></i> Leave Review
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline" style="width: 100%; cursor: default;" disabled>
                                            <i class="fas fa-check"></i> Review Submitted
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
