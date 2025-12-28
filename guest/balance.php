<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isGuest()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'My Balance - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get balance information
    $stmt = $pdo->prepare("SELECT * FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetch();
    
    // If no balance record exists, create one
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO renter_balances (user_id, current_balance) VALUES (?, 0.00)");
        $stmt->execute([$_SESSION['user_id']]);
        
        $stmt = $pdo->prepare("SELECT * FROM renter_balances WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $balance = $stmt->fetch();
    }
    
    // Get payment credentials
    $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $credentials = $stmt->fetchAll();
    
    // Get transaction history
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading balance information: ' . $e->getMessage();
    // Initialize default balance if error occurs
    $balance = [
        'current_balance' => 0,
        'pending_holds' => 0,
        'total_spent' => 0
    ];
    $credentials = [];
    $transactions = [];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-wallet"></i> My Balance & Payment Credentials
    </h1>
    
    <!-- Balance Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-wallet" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['current_balance']); ?></div>
            <div class="stat-label">Available Balance</div>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-clock" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['pending_holds']); ?></div>
            <div class="stat-label">Pending Holds</div>
        </div>
        
        <div class="stat-card secondary">
            <i class="fas fa-chart-line" style="font-size: 32px;"></i>
            <div class="stat-value"><?php echo formatCurrency($balance['total_spent']); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
    </div>
    
    <!-- Payment Credentials -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-credit-card"></i> Payment Credentials</h2>
        </div>
        
        <?php if (empty($credentials)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No payment credentials found. Contact support to issue credentials.
            </p>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($credentials as $cred): ?>
                    <div style="border: 2px solid var(--border-color); border-radius: 12px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <div>
                                <p style="margin: 0; opacity: 0.9; font-size: 12px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $cred['credential_type'])); ?>
                                </p>
                                <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: bold; letter-spacing: 2px;">
                                    <?php echo htmlspecialchars($cred['credential_number']); ?>
                                </p>
                            </div>
                            <div>
                                <?php
                                $status_color = $cred['status'] === 'active' ? '#4caf50' : '#f44336';
                                ?>
                                <span style="background-color: <?php echo $status_color; ?>; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                                    <?php echo ucfirst($cred['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <p style="margin: 0; opacity: 0.9; font-size: 12px;">Cardholder</p>
                            <p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 500;">
                                <?php echo htmlspecialchars($cred['credential_name'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        
                        <?php if ($cred['expiry_date']): ?>
                            <div style="margin-top: 15px;">
                                <p style="margin: 0; opacity: 0.9; font-size: 12px;">Expires</p>
                                <p style="margin: 5px 0 0 0; font-size: 14px; font-weight: 500;">
                                    <?php echo date('m/Y', strtotime($cred['expiry_date'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Transaction History -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-history"></i> Transaction History</h2>
        </div>
        
        <?php if (empty($transactions)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No transactions yet
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Balance Before</th>
                            <th>Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $type_class = 'badge-info';
                                    if (in_array($txn['transaction_type'], ['deposit', 'refund', 'release'])) {
                                        $type_class = 'badge-success';
                                    } elseif (in_array($txn['transaction_type'], ['deduction', 'fee', 'hold'])) {
                                        $type_class = 'badge-warning';
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_class; ?>">
                                        <?php echo ucfirst($txn['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($txn['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $amount_color = in_array($txn['transaction_type'], ['deposit', 'refund', 'release']) ? 'green' : 'red';
                                    $amount_prefix = in_array($txn['transaction_type'], ['deposit', 'refund', 'release']) ? '+' : '-';
                                    ?>
                                    <span style="color: <?php echo $amount_color; ?>; font-weight: bold;">
                                        <?php echo $amount_prefix . formatCurrency($txn['amount']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($txn['balance_before']); ?></td>
                                <td><?php echo formatCurrency($txn['balance_after']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
