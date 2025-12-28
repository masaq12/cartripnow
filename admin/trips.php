<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'All Trips - Admin - Car Trip Now';

// Handle trip actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getPDOConnection();
        $trip_id = $_POST['trip_id'] ?? 0;
        
        if ($_POST['action'] === 'cancel') {
            $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'cancelled' WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            $_SESSION['success_message'] = 'Trip cancelled successfully';
        } elseif ($_POST['action'] === 'refund') {
            // Process refund
            $pdo->beginTransaction();
            
            // Get trip details
            $stmt = $pdo->prepare("SELECT * FROM trips WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            $trip = $stmt->fetch();
            
            if ($trip) {
                // Update trip status
                $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'cancelled', payment_status = 'refunded' WHERE trip_id = ?");
                $stmt->execute([$trip_id]);
                
                // Refund to renter balance
                $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
                $stmt->execute([$trip['renter_id']]);
                $renter_balance = $stmt->fetch();
                
                $old_balance = $renter_balance['current_balance'] ?? 0;
                $new_balance = $old_balance + $trip['total_amount'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO renter_balances (user_id, current_balance) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE current_balance = ?
                ");
                $stmt->execute([$trip['renter_id'], $new_balance, $new_balance]);
                
                // Record transaction
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, description)
                    VALUES (?, 'refund', ?, ?, ?, 'trip', ?, ?)
                ");
                $stmt->execute([
                    $trip['renter_id'],
                    $trip['total_amount'],
                    $old_balance,
                    $new_balance,
                    $trip_id,
                    'Refund for trip #' . $trip_id
                ]);
                
                // Update escrow
                $stmt = $pdo->prepare("UPDATE escrow SET status = 'refunded' WHERE trip_id = ?");
                $stmt->execute([$trip_id]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Trip refunded successfully';
        }
        
        redirect($_SERVER['PHP_SELF']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error processing action: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Filter options
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query
    $query = "
        SELECT t.*, 
        CONCAT(v.year, ' ', v.make, ' ', v.model) as vehicle_title,
        r.full_name as renter_name, r.email as renter_email,
        o.full_name as owner_name, o.email as owner_email
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN users r ON t.renter_id = r.user_id
        JOIN users o ON t.owner_id = o.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter) {
        $query .= " AND t.trip_status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $query .= " AND (v.make LIKE ? OR v.model LIKE ? OR r.full_name LIKE ? OR o.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();
    
    // Get stats
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
        'confirmed' => $pdo->query("SELECT COUNT(*) FROM trips WHERE trip_status = 'confirmed'")->fetchColumn(),
        'completed' => $pdo->query("SELECT COUNT(*) FROM trips WHERE trip_status = 'completed'")->fetchColumn(),
        'cancelled' => $pdo->query("SELECT COUNT(*) FROM trips WHERE trip_status = 'cancelled'")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT SUM(total_amount) FROM trips WHERE trip_status IN ('confirmed', 'active', 'completed')")->fetchColumn() ?? 0,
    ];
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading trips';
    $trips = [];
    $stats = ['total' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0, 'total_revenue' => 0];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-car"></i> All Trips
    </h1>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-calendar"></i>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Trips</div>
        </div>
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-value"><?php echo number_format($stats['confirmed']); ?></div>
            <div class="stat-label">Confirmed</div>
        </div>
        <div class="stat-card secondary">
            <i class="fas fa-flag-checkered"></i>
            <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card warning">
            <i class="fas fa-dollar-sign"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <form method="GET" class="grid grid-3" style="gap: 15px;">
            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Vehicle, renter, or owner..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="disputed" <?php echo $status_filter === 'disputed' ? 'selected' : ''; ?>>Disputed</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Trips Table -->
    <div class="card">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vehicle</th>
                        <th>Renter</th>
                        <th>Owner</th>
                        <th>Pickup</th>
                        <th>Return</th>
                        <th>Days</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Booked</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trips)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; color: #666; padding: 40px;">
                                No trips found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><strong>#<?php echo $trip['trip_id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($trip['vehicle_title']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($trip['renter_name']); ?>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($trip['renter_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($trip['owner_name']); ?>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($trip['owner_email']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['return_date'])); ?></td>
                                <td><?php echo $trip['trip_duration_days']; ?></td>
                                <td><strong><?php echo formatCurrency($trip['total_amount']); ?></strong></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-info';
                                    if ($trip['trip_status'] === 'confirmed') $badge_class = 'badge-success';
                                    if ($trip['trip_status'] === 'completed') $badge_class = 'badge-success';
                                    if ($trip['trip_status'] === 'cancelled') $badge_class = 'badge-danger';
                                    if ($trip['trip_status'] === 'disputed') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($trip['trip_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $payment_badge = 'badge-info';
                                    if ($trip['payment_status'] === 'completed') $payment_badge = 'badge-success';
                                    if ($trip['payment_status'] === 'refunded') $payment_badge = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $payment_badge; ?>">
                                        <?php echo ucfirst($trip['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($trip['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-direction: column;">
                                        <?php if ($trip['trip_status'] === 'confirmed'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px; width: 100%;"
                                                        onclick="return confirm('Cancel this trip?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                            <form method="POST">
                                                <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
                                                <input type="hidden" name="action" value="refund">
                                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; width: 100%;"
                                                        onclick="return confirm('Refund this trip? Renter will receive full refund.')">
                                                    <i class="fas fa-undo"></i> Refund
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($trip['trip_status'], ['confirmed', 'active']) && $trip['payment_status'] === 'held'): ?>
                                            <form method="POST" action="release_payment.php">
                                                <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
                                                <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px; width: 100%;"
                                                        onclick="return confirm('Release payment to owner? This action cannot be undone.')">
                                                    <i class="fas fa-unlock"></i> Release
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
