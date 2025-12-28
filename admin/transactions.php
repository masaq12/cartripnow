<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'All Transactions - Admin - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Filter options
    $type_filter = $_GET['type'] ?? '';
    $user_search = $_GET['user'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build query
    $query = "
        SELECT t.*, u.full_name as user_name, u.email as user_email, u.user_type
        FROM transactions t
        JOIN users u ON t.user_id = u.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($type_filter) {
        $query .= " AND t.transaction_type = ?";
        $params[] = $type_filter;
    }
    
    if ($user_search) {
        $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$user_search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($date_from) {
        $query .= " AND DATE(t.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND DATE(t.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY t.created_at DESC LIMIT 500";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Get stats
    $stats_query = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN transaction_type = 'deduction' THEN amount ELSE 0 END) as total_deductions,
            SUM(CASE WHEN transaction_type = 'payout' THEN amount ELSE 0 END) as total_payouts,
            SUM(CASE WHEN transaction_type = 'fee' THEN amount ELSE 0 END) as total_fees
        FROM transactions
    ";
    
    if ($date_from) {
        $stats_query .= " WHERE DATE(created_at) >= '$date_from'";
        if ($date_to) {
            $stats_query .= " AND DATE(created_at) <= '$date_to'";
        }
    } elseif ($date_to) {
        $stats_query .= " WHERE DATE(created_at) <= '$date_to'";
    }
    
    $stats = $pdo->query($stats_query)->fetch();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading transactions';
    $transactions = [];
    $stats = [
        'total_transactions' => 0,
        'total_deposits' => 0,
        'total_deductions' => 0,
        'total_payouts' => 0,
        'total_fees' => 0
    ];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-exchange-alt"></i> All Transactions
    </h1>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-list"></i>
            <div class="stat-value"><?php echo number_format($stats['total_transactions']); ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card success">
            <i class="fas fa-arrow-down"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_deposits']); ?></div>
            <div class="stat-label">Total Deposits</div>
        </div>
        <div class="stat-card warning">
            <i class="fas fa-arrow-up"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_payouts']); ?></div>
            <div class="stat-label">Total Payouts</div>
        </div>
        <div class="stat-card secondary">
            <i class="fas fa-dollar-sign"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_fees']); ?></div>
            <div class="stat-label">Platform Fees</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <form method="GET">
            <div class="grid grid-4" style="gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label">Transaction Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                        <option value="deduction" <?php echo $type_filter === 'deduction' ? 'selected' : ''; ?>>Deduction</option>
                        <option value="earning" <?php echo $type_filter === 'earning' ? 'selected' : ''; ?>>Earning</option>
                        <option value="payout" <?php echo $type_filter === 'payout' ? 'selected' : ''; ?>>Payout</option>
                        <option value="refund" <?php echo $type_filter === 'refund' ? 'selected' : ''; ?>>Refund</option>
                        <option value="fee" <?php echo $type_filter === 'fee' ? 'selected' : ''; ?>>Fee</option>
                        <option value="hold" <?php echo $type_filter === 'hold' ? 'selected' : ''; ?>>Hold</option>
                        <option value="release" <?php echo $type_filter === 'release' ? 'selected' : ''; ?>>Release</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">User Search</label>
                    <input type="text" name="user" class="form-control" placeholder="Name or email..." value="<?php echo htmlspecialchars($user_search); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">Clear</a>
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </form>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance Before</th>
                        <th>Balance After</th>
                        <th>Reference</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #666; padding: 40px;">
                                No transactions found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><strong>#<?php echo $txn['transaction_id']; ?></strong></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($txn['created_at'])); ?>
                                    <br>
                                    <small style="color: #666;"><?php echo date('h:i A', strtotime($txn['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($txn['user_name']); ?>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo htmlspecialchars($txn['user_email']); ?>
                                    </small>
                                    <br>
                                    <span class="badge badge-info" style="font-size: 10px;">
                                        <?php echo ucfirst($txn['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $type_badge = 'badge-info';
                                    $type_icon = 'fa-exchange-alt';
                                    
                                    switch($txn['transaction_type']) {
                                        case 'deposit':
                                            $type_badge = 'badge-success';
                                            $type_icon = 'fa-arrow-down';
                                            break;
                                        case 'deduction':
                                            $type_badge = 'badge-danger';
                                            $type_icon = 'fa-minus';
                                            break;
                                        case 'earning':
                                            $type_badge = 'badge-success';
                                            $type_icon = 'fa-plus';
                                            break;
                                        case 'payout':
                                            $type_badge = 'badge-warning';
                                            $type_icon = 'fa-arrow-up';
                                            break;
                                        case 'refund':
                                            $type_badge = 'badge-info';
                                            $type_icon = 'fa-undo';
                                            break;
                                        case 'fee':
                                            $type_badge = 'badge-secondary';
                                            $type_icon = 'fa-percentage';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_badge; ?>">
                                        <i class="fas <?php echo $type_icon; ?>"></i>
                                        <?php echo ucfirst($txn['transaction_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?php echo in_array($txn['transaction_type'], ['deposit', 'earning', 'refund']) ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                                        <?php echo in_array($txn['transaction_type'], ['deposit', 'earning', 'refund']) ? '+' : '-'; ?>
                                        <?php echo formatCurrency($txn['amount']); ?>
                                    </strong>
                                </td>
                                <td><?php echo formatCurrency($txn['balance_before']); ?></td>
                                <td><?php echo formatCurrency($txn['balance_after']); ?></td>
                                <td>
                                    <?php if ($txn['reference_type'] && $txn['reference_id']): ?>
                                        <span class="badge badge-outline">
                                            <?php echo ucfirst($txn['reference_type']); ?> #<?php echo $txn['reference_id']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($txn['description']); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($transactions) >= 500): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                Showing latest 500 transactions. Use filters to narrow results.
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .navbar, .btn, form, .alert { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd; }
}
</style>

<?php include '../includes/footer.php'; ?>
