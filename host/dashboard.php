<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Host Dashboard - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get owner stats
    $owner_id = $_SESSION['user_id'];
    
    $stats = [];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE owner_id = ?");
    $stmt->execute([$owner_id]);
    $stats['total_vehicles'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE owner_id = ? AND status = 'active'");
    $stmt->execute([$owner_id]);
    $stats['active_vehicles'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ?");
    $stmt->execute([$owner_id]);
    $stats['total_trips'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE owner_id = ? AND trip_status = 'confirmed' AND pickup_date >= CURDATE()");
    $stmt->execute([$owner_id]);
    $stats['upcoming_trips'] = (int)$stmt->fetchColumn();
    
    // Get balance information
    $stmt = $pdo->prepare("SELECT * FROM owner_balances WHERE user_id = ?");
    $stmt->execute([$owner_id]);
    $balance = $stmt->fetch();
    
    // If no balance exists, create one
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO owner_balances (user_id) VALUES (?)");
        $stmt->execute([$owner_id]);
        
        $stmt = $pdo->prepare("SELECT * FROM owner_balances WHERE user_id = ?");
        $stmt->execute([$owner_id]);
        $balance = $stmt->fetch();
    }
    
    // Get verification status
    $stmt = $pdo->prepare("SELECT verification_status FROM owner_verification WHERE user_id = ?");
    $stmt->execute([$owner_id]);
    $verification = $stmt->fetch();
    
    // If no verification exists, create one
    if (!$verification) {
        $stmt = $pdo->prepare("INSERT INTO owner_verification (user_id, verification_status) VALUES (?, 'pending')");
        $stmt->execute([$owner_id]);
        
        $stmt = $pdo->prepare("SELECT verification_status FROM owner_verification WHERE user_id = ?");
        $stmt->execute([$owner_id]);
        $verification = $stmt->fetch();
    }
    
    // Get recent trips
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(v.year, ' ', v.make, ' ', v.model) as vehicle_title, 
               r.full_name as renter_name
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users r ON t.renter_id = r.user_id
        WHERE t.owner_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$owner_id]);
    $recent_trips = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading dashboard: ' . $e->getMessage();
    $stats = [
        'total_vehicles' => 0,
        'active_vehicles' => 0,
        'total_trips' => 0,
        'upcoming_trips' => 0
    ];
    $balance = [
        'available_balance' => 0,
        'pending_balance' => 0,
        'total_earned' => 0,
        'total_paid_out' => 0
    ];
    $verification = ['verification_status' => 'pending'];
    $recent_trips = [];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 10px;">
        <i class="fas fa-tachometer-alt"></i> Vehicle Owner Dashboard
    </h1>
    
    <?php if ($verification['verification_status'] === 'pending'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Verification Pending:</strong> Your vehicle owner account is pending verification. 
            Some features may be limited until verification is complete.
        </div>
    <?php elseif ($verification['verification_status'] === 'verified'): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
            <strong>Verified Host:</strong> Your account is verified and active!
        </div>
    <?php endif; ?>
    
    <!-- Balance Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-wallet" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['available_balance']); ?></div>
            <div class="stat-label">Available Balance</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-clock" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['pending_balance']); ?></div>
            <div class="stat-label">Pending Earnings</div>
        </div>
        
        <div class="stat-card secondary">
            <i class="fas fa-chart-line" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['total_earned']); ?></div>
            <div class="stat-label">Total Earned</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-hand-holding-usd" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['total_paid_out']); ?></div>
            <div class="stat-label">Total Paid Out</div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="grid grid-4" style="margin-bottom: 30px;">
        <div class="card" style="text-align: center;">
            <i class="fas fa-car" style="font-size: 48px; color: var(--primary-color); margin-bottom: 15px;"></i>
            <h3 style="font-size: 32px; margin: 10px 0;"><?php echo $stats['total_vehicles']; ?></h3>
            <p style="color: #666;">Total Vehicles</p>
            <a href="listings.php" class="btn btn-outline" style="margin-top: 10px;">
                <i class="fas fa-eye"></i> View All
            </a>
        </div>
        
        <div class="card" style="text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success-color); margin-bottom: 15px;"></i>
            <h3 style="font-size: 32px; margin: 10px 0;"><?php echo $stats['active_vehicles']; ?></h3>
            <p style="color: #666;">Active Vehicles</p>
            <a href="add_listing.php" class="btn btn-outline" style="margin-top: 10px;">
                <i class="fas fa-plus"></i> Add New
            </a>
        </div>
        
        <div class="card" style="text-align: center;">
            <i class="fas fa-calendar-check" style="font-size: 48px; color: var(--secondary-color); margin-bottom: 15px;"></i>
            <h3 style="font-size: 32px; margin: 10px 0;"><?php echo $stats['total_trips']; ?></h3>
            <p style="color: #666;">Total Trips</p>
            <a href="bookings.php" class="btn btn-outline" style="margin-top: 10px;">
                <i class="fas fa-eye"></i> View All
            </a>
        </div>
        
        <div class="card" style="text-align: center;">
            <i class="fas fa-calendar-alt" style="font-size: 48px; color: var(--warning-color); margin-bottom: 15px;"></i>
            <h3 style="font-size: 32px; margin: 10px 0;"><?php echo $stats['upcoming_trips']; ?></h3>
            <p style="color: #666;">Upcoming Trips</p>
            <a href="bookings.php?filter=upcoming" class="btn btn-outline" style="margin-top: 10px;">
                <i class="fas fa-eye"></i> View
            </a>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card" style="margin-bottom: 30px;">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="grid grid-4">
            <a href="add_listing.php" class="btn btn-primary" style="text-align: center; padding: 20px;">
                <i class="fas fa-plus" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                Add New Listing
            </a>
            <a href="earnings.php" class="btn btn-secondary" style="text-align: center; padding: 20px;">
                <i class="fas fa-dollar-sign" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                View Earnings
            </a>
            <a href="payouts.php" class="btn btn-success" style="text-align: center; padding: 20px;">
                <i class="fas fa-hand-holding-usd" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                Request Payout
            </a>
            <a href="payout_settings.php" class="btn btn-outline" style="text-align: center; padding: 20px;">
                <i class="fas fa-cog" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                Payout Settings
            </a>
        </div>
    </div>
    
    <!-- Recent Trips -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-calendar-check"></i> Recent Trips</h2>
        </div>
        
        <?php if (empty($recent_trips)): ?>
            <p style="text-align: center; color: #666; padding: 40px;">
                No trips yet. Make sure your vehicles are listed and available!
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trip ID</th>
                            <th>Vehicle</th>
                            <th>Renter</th>
                            <th>Pickup</th>
                            <th>Return</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_trips as $trip): ?>
                            <tr>
                                <td>#<?php echo $trip['trip_id']; ?></td>
                                <td><?php echo htmlspecialchars($trip['vehicle_title']); ?></td>
                                <td><?php echo htmlspecialchars($trip['renter_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['return_date'])); ?></td>
                                <td><?php echo formatCurrency($trip['total_amount']); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'badge-info';
                                    if ($trip['trip_status'] === 'confirmed') $status_class = 'badge-success';
                                    if ($trip['trip_status'] === 'completed') $status_class = 'badge-success';
                                    if ($trip['trip_status'] === 'cancelled') $status_class = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($trip['trip_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking_details.php?id=<?php echo $trip['trip_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
