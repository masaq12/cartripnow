<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Earnings - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get balance information
    $stmt = $pdo->prepare("SELECT * FROM owner_balances WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetch();
    
    // Initialize balance if not exists
    if (!$balance) {
        $balance = [
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earned' => 0,
            'total_paid_out' => 0,
            'platform_fees_paid' => 0,
            'insurance_fees_paid' => 0
        ];
    }
    
    // Get earnings breakdown by month
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(t.created_at, '%Y-%m') as month,
            COUNT(*) as trip_count,
            SUM(t.total_amount) as total_revenue,
            SUM(t.platform_fee) as platform_fees,
            SUM(t.total_amount - t.platform_fee) as net_earnings
        FROM trips t
        WHERE t.owner_id = ? AND t.trip_status IN ('confirmed', 'completed')
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $monthly_earnings = $stmt->fetchAll();
    
    // Get completed trips
    $stmt = $pdo->prepare("
        SELECT t.*, CONCAT(v.year, ' ', v.make, ' ', v.model) as vehicle_title
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        WHERE t.owner_id = ? AND t.trip_status = 'completed'
        ORDER BY t.return_date DESC
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_trips = $stmt->fetchAll();
    
    // Get transaction history
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? AND transaction_type IN ('earning', 'payout', 'fee')
        ORDER BY created_at DESC 
        LIMIT 30
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading earnings data';
    $balance = [
        'available_balance' => 0,
        'pending_balance' => 0,
        'total_earned' => 0,
        'total_paid_out' => 0,
        'platform_fees_paid' => 0,
        'insurance_fees_paid' => 0
    ];
    $monthly_earnings = [];
    $completed_trips = [];
    $transactions = [];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-dollar-sign"></i> Earnings Overview
    </h1>
    
    <!-- Balance Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-wallet" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['available_balance']); ?></div>
            <div class="stat-label">Available Balance</div>
            <a href="payouts.php" class="btn btn-outline" style="margin-top: 15px;">
                Request Payout
            </a>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-clock" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['pending_balance']); ?></div>
            <div class="stat-label">Pending Balance</div>
            <p style="font-size: 11px; margin-top: 10px; opacity: 0.9;">Released after stay completion</p>
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
    
    <!-- Platform Fees -->
    <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin-bottom: 10px;"><i class="fas fa-receipt"></i> Platform Fees Paid</h3>
                <p style="font-size: 36px; font-weight: bold; margin: 0;">
                    <?php echo formatCurrency($balance['platform_fees_paid']); ?>
                </p>
                <p style="opacity: 0.9; margin: 10px 0 0 0; font-size: 14px;">
                    15% service fee on all completed bookings
                </p>
            </div>
            <i class="fas fa-percentage" style="font-size: 64px; opacity: 0.3;"></i>
        </div>
    </div>
    
    <!-- Monthly Earnings Chart -->
    <?php if (!empty($monthly_earnings)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-chart-bar"></i> Monthly Earnings</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Trips</th>
                            <th>Total Revenue</th>
                            <th>Platform Fees</th>
                            <th>Net Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_earnings as $earning): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($earning['month'] . '-01')); ?></td>
                                <td><?php echo $earning['trip_count']; ?></td>
                                <td><?php echo formatCurrency($earning['total_revenue']); ?></td>
                                <td style="color: var(--error-color);">
                                    <?php echo formatCurrency($earning['platform_fees']); ?>
                                </td>
                                <td style="color: var(--success-color); font-weight: bold;">
                                    <?php echo formatCurrency($earning['net_earnings']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Completed Trips -->
    <?php if (!empty($completed_trips)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-check-circle"></i> Completed Trips</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trip ID</th>
                            <th>Vehicle</th>
                            <th>Return Date</th>
                            <th>Days</th>
                            <th>Total Amount</th>
                            <th>Platform Fee</th>
                            <th>Your Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_trips as $trip): ?>
                            <?php
                            $your_earnings = $trip['total_amount'] - $trip['platform_fee'];
                            ?>
                            <tr>
                                <td>#<?php echo $trip['trip_id']; ?></td>
                                <td><?php echo htmlspecialchars($trip['vehicle_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['return_date'])); ?></td>
                                <td><?php echo $trip['trip_duration_days']; ?></td>
                                <td><?php echo formatCurrency($trip['total_amount']); ?></td>
                                <td style="color: var(--error-color);">
                                    -<?php echo formatCurrency($trip['platform_fee']); ?>
                                </td>
                                <td style="color: var(--success-color); font-weight: bold;">
                                    <?php echo formatCurrency($your_earnings); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Transaction History -->
    <?php if (!empty($transactions)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-history"></i> Transaction History</h2>
            </div>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $type_class = 'badge-success';
                                    if ($txn['transaction_type'] === 'payout') $type_class = 'badge-warning';
                                    if ($txn['transaction_type'] === 'fee') $type_class = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $type_class; ?>">
                                        <?php echo ucfirst($txn['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($txn['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $color = $txn['transaction_type'] === 'earning' ? 'green' : 'red';
                                    $prefix = $txn['transaction_type'] === 'earning' ? '+' : '-';
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                        <?php echo $prefix . formatCurrency($txn['amount']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($txn['balance_after']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
