<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Admin Dashboard - Car Trip Now';

// Initialize stats array to avoid undefined variable error
$stats = [];
$recent_trips = [];
$recent_users = [];

try {
    $pdo = getPDOConnection();
    
    // Get statistics
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type != 'admin'")->fetchColumn() ?? 0,
        'total_renters' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'renter'")->fetchColumn() ?? 0,
        'total_owners' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'owner'")->fetchColumn() ?? 0,
        'total_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn() ?? 0,
        'active_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")->fetchColumn() ?? 0,
        'total_trips' => $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn() ?? 0,
        'confirmed_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE trip_status = 'confirmed'")->fetchColumn() ?? 0,
        'total_renter_balance' => $pdo->query("SELECT SUM(current_balance) FROM renter_balances")->fetchColumn() ?? 0,
        'total_owner_balance' => $pdo->query("SELECT SUM(available_balance) FROM owner_balances")->fetchColumn() ?? 0,
        'total_platform_fees' => $pdo->query("SELECT SUM(platform_fees_paid) FROM owner_balances")->fetchColumn() ?? 0,
        'today_trips' => $pdo->query("SELECT COUNT(*) FROM trips WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0,
        'total_revenue' => $pdo->query("SELECT SUM(total_amount) FROM trips WHERE trip_status IN ('confirmed', 'completed')")->fetchColumn() ?? 0,
    ];
    
    // Recent trips
    $stmt = $pdo->query("
        SELECT t.*, 
               v.make, v.model, v.year,
               r.full_name as renter_name, 
               o.full_name as owner_name
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users r ON t.renter_id = r.user_id
        JOIN users o ON t.owner_id = o.user_id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $recent_trips = $stmt->fetchAll();
    
    // Recent users
    $stmt = $pdo->query("
        SELECT * FROM users 
        WHERE user_type != 'admin'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading dashboard data: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
    </h1>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        
        <div class="stat-card secondary">
            <i class="fas fa-car" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo number_format($stats['total_vehicles']); ?></div>
            <div class="stat-label">Total Vehicles</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-calendar-check" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo number_format($stats['total_trips']); ?></div>
            <div class="stat-label">Total Trips</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-dollar-sign" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_platform_fees']); ?></div>
            <div class="stat-label">Platform Fees Earned</div>
        </div>
    </div>
    
    <!-- Additional Stats -->
    <div class="grid grid-3" style="margin-bottom: 30px;">
        <div class="card">
            <h3><i class="fas fa-user"></i> Renters</h3>
            <p style="font-size: 24px; font-weight: bold; color: var(--primary-color);">
                <?php echo number_format($stats['total_renters']); ?>
            </p>
            <p>Total Balance: <?php echo formatCurrency($stats['total_renter_balance']); ?></p>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-building"></i> Vehicle Owners</h3>
            <p style="font-size: 24px; font-weight: bold; color: var(--secondary-color);">
                <?php echo number_format($stats['total_owners']); ?>
            </p>
            <p>Total Balance: <?php echo formatCurrency($stats['total_owner_balance']); ?></p>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Today's Activity</h3>
            <p style="font-size: 24px; font-weight: bold; color: var(--success-color);">
                <?php echo number_format($stats['today_trips']); ?>
            </p>
            <p>New trips today</p>
        </div>
    </div>
    
    <!-- Recent Trips -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-calendar-check"></i> Recent Trips</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Vehicle</th>
                        <th>Renter</th>
                        <th>Owner</th>
                        <th>Pickup</th>
                        <th>Return</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_trips)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">No trips yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_trips as $trip): ?>
                            <tr>
                                <td>#<?php echo $trip['trip_id']; ?></td>
                                <td><?php echo htmlspecialchars($trip['year'] . ' ' . $trip['make'] . ' ' . $trip['model']); ?></td>
                                <td><?php echo htmlspecialchars($trip['renter_name']); ?></td>
                                <td><?php echo htmlspecialchars($trip['owner_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['return_date'])); ?></td>
                                <td><?php echo formatCurrency($trip['total_amount']); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'badge-info';
                                    if ($trip['trip_status'] === 'confirmed') $status_class = 'badge-success';
                                    if ($trip['trip_status'] === 'cancelled') $status_class = 'badge-danger';
                                    if ($trip['trip_status'] === 'completed') $status_class = 'badge-success';
                                    if ($trip['trip_status'] === 'active') $status_class = 'badge-primary';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($trip['trip_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="trips.php?id=<?php echo $trip['trip_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-users"></i> Recent Users</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No users yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = $user['status'] === 'active' ? 'badge-success' : 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
