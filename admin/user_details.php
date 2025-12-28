<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = $_GET['id'] ?? 0;
$pageTitle = 'User Details - Admin';

// Handle manual balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_balance'])) {
    try {
        $pdo = getPDOConnection();
        $adjustment = (float)$_POST['adjustment'];
        $description = sanitizeInput($_POST['description']);
        
        $user_type = $_POST['user_type'];
        
        if (in_array($user_type, ['renter', 'guest'])) {
            $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $balance_before = $stmt->fetchColumn() ?: 0;
            $balance_after = $balance_before + $adjustment;
            
            $stmt = $pdo->prepare("
                INSERT INTO renter_balances (user_id, current_balance) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE current_balance = ?
            ");
            $stmt->execute([$user_id, $balance_after, $balance_after]);
            
            // Log transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, description)
                VALUES (?, 'deposit', ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $adjustment, $balance_before, $balance_after, 'Admin Adjustment: ' . $description]);
            
        } elseif (in_array($user_type, ['owner', 'host'])) {
            $stmt = $pdo->prepare("SELECT available_balance FROM owner_balances WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $balance_before = $stmt->fetchColumn() ?: 0;
            $balance_after = $balance_before + $adjustment;
            
            $stmt = $pdo->prepare("
                INSERT INTO owner_balances (user_id, available_balance) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE available_balance = ?
            ");
            $stmt->execute([$user_id, $balance_after, $balance_after]);
            
            // Log transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, description)
                VALUES (?, 'earning', ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $adjustment, $balance_before, $balance_after, 'Admin Adjustment: ' . $description]);
        }
        
        $_SESSION['success_message'] = 'Balance adjusted successfully';
        redirect($_SERVER['PHP_SELF'] . '?id=' . $user_id);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error adjusting balance: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, 
        COALESCE(rb.current_balance, 0) as renter_balance,
        COALESCE(rb.pending_holds, 0) as renter_pending,
        COALESCE(rb.total_spent, 0) as renter_spent,
        COALESCE(ob.available_balance, 0) as owner_balance,
        COALESCE(ob.pending_balance, 0) as owner_pending,
        COALESCE(ob.total_earned, 0) as owner_earned,
        COALESCE(ob.total_paid_out, 0) as owner_paid_out
        FROM users u
        LEFT JOIN renter_balances rb ON u.user_id = rb.user_id
        LEFT JOIN owner_balances ob ON u.user_id = ob.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'User not found';
        redirect(SITE_URL . '/admin/users.php');
    }
    
    // Get payment credentials for renters
    $credentials = [];
    if (in_array($user['user_type'], ['renter', 'guest'])) {
        $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $credentials = $stmt->fetchAll();
    }
    
    // Get payout methods for owners
    $payout_methods = [];
    if (in_array($user['user_type'], ['owner', 'host'])) {
        $stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $payout_methods = $stmt->fetchAll();
    }
    
    // Get trips
    if (in_array($user['user_type'], ['renter', 'guest'])) {
        $stmt = $pdo->prepare("
            SELECT t.*, CONCAT(v.year, ' ', v.make, ' ', v.model) as vehicle_title 
            FROM trips t 
            JOIN vehicles v ON t.vehicle_id = v.vehicle_id 
            WHERE t.renter_id = ? 
            ORDER BY t.created_at DESC 
            LIMIT 10
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT t.*, CONCAT(v.year, ' ', v.make, ' ', v.model) as vehicle_title 
            FROM trips t 
            JOIN vehicles v ON t.vehicle_id = v.vehicle_id 
            WHERE t.owner_id = ? 
            ORDER BY t.created_at DESC 
            LIMIT 10
        ");
    }
    $stmt->execute([$user_id]);
    $trips = $stmt->fetchAll();
    
    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll();
    
    // Get vehicles for owners
    $vehicles = [];
    if (in_array($user['user_type'], ['owner', 'host'])) {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE owner_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $vehicles = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading user details';
    redirect(SITE_URL . '/admin/users.php');
}

include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1><i class="fas fa-user"></i> User Details</h1>
        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
    
    <div class="grid grid-2" style="gap: 20px;">
        <!-- User Information -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> User Information</h2>
            <div style="margin-top: 20px;">
                <p><strong>User ID:</strong> <?php echo $user['user_id']; ?></p>
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
                <p>
                    <strong>User Type:</strong> 
                    <span class="badge badge-info"><?php echo ucfirst($user['user_type']); ?></span>
                </p>
                <p>
                    <strong>Status:</strong>
                    <?php
                    $status_class = 'badge-success';
                    if ($user['status'] === 'suspended') $status_class = 'badge-warning';
                    if ($user['status'] === 'frozen') $status_class = 'badge-danger';
                    ?>
                    <span class="badge <?php echo $status_class; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </p>
                <p><strong>Registered:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('F d, Y H:i', strtotime($user['updated_at'])); ?></p>
            </div>
        </div>
        
        <!-- Balance Information -->
        <div class="card">
            <h2><i class="fas fa-wallet"></i> Balance Information</h2>
            <div style="margin-top: 20px;">
                <?php if (in_array($user['user_type'], ['renter', 'guest'])): ?>
                    <div style="padding: 15px; background-color: #e8f5e9; border-radius: 8px; margin-bottom: 15px;">
                        <p style="margin: 0; font-size: 14px;">Current Balance</p>
                        <p style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: var(--success-color);">
                            <?php echo formatCurrency($user['renter_balance']); ?>
                        </p>
                    </div>
                    <p><strong>Pending Holds:</strong> <?php echo formatCurrency($user['renter_pending']); ?></p>
                    <p><strong>Total Spent:</strong> <?php echo formatCurrency($user['renter_spent']); ?></p>
                <?php elseif (in_array($user['user_type'], ['owner', 'host'])): ?>
                    <div style="padding: 15px; background-color: #e3f2fd; border-radius: 8px; margin-bottom: 15px;">
                        <p style="margin: 0; font-size: 14px;">Available Balance</p>
                        <p style="margin: 5px 0 0 0; font-size: 28px; font-weight: bold; color: var(--primary-color);">
                            <?php echo formatCurrency($user['owner_balance']); ?>
                        </p>
                    </div>
                    <p><strong>Pending Balance:</strong> <?php echo formatCurrency($user['owner_pending']); ?></p>
                    <p><strong>Total Earned:</strong> <?php echo formatCurrency($user['owner_earned']); ?></p>
                    <p><strong>Total Paid Out:</strong> <?php echo formatCurrency($user['owner_paid_out']); ?></p>
                <?php endif; ?>
                
                <!-- Manual Balance Adjustment -->
                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                    <h3 style="font-size: 16px; margin-bottom: 10px;">Adjust Balance</h3>
                    <form method="POST">
                        <input type="hidden" name="user_type" value="<?php echo $user['user_type']; ?>">
                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" name="adjustment" class="form-control" step="0.01" required placeholder="Use negative for deduction">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" required placeholder="Reason for adjustment">
                        </div>
                        <button type="submit" name="adjust_balance" class="btn btn-warning" style="width: 100%;">
                            <i class="fas fa-edit"></i> Adjust Balance
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Credentials for Renters -->
    <?php if (in_array($user['user_type'], ['renter', 'guest']) && !empty($credentials)): ?>
        <div class="card">
            <h2><i class="fas fa-credit-card"></i> Payment Credentials</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Number</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Issued Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credentials as $cred): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $cred['credential_type'])); ?></td>
                                <td><?php echo htmlspecialchars($cred['credential_number']); ?></td>
                                <td><?php echo htmlspecialchars($cred['credential_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $cred['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($cred['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($cred['issued_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Payout Methods for Owners -->
    <?php if (in_array($user['user_type'], ['owner', 'host']) && !empty($payout_methods)): ?>
        <div class="card">
            <h2><i class="fas fa-money-bill"></i> Payout Methods</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Account Holder</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payout_methods as $method): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $method['method_type'])); ?></td>
                                <td><?php echo htmlspecialchars($method['account_holder_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $method['is_default'] ? '<i class="fas fa-check text-success"></i>' : ''; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $method['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($method['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($method['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Owner Vehicles -->
    <?php if (in_array($user['user_type'], ['owner', 'host']) && !empty($vehicles)): ?>
        <div class="card">
            <h2><i class="fas fa-car"></i> Vehicles (<?php echo count($vehicles); ?>)</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle</th>
                            <th>City</th>
                            <th>Price/Day</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td><?php echo $vehicle['vehicle_id']; ?></td>
                                <td><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['pickup_city']); ?></td>
                                <td><?php echo formatCurrency($vehicle['daily_price']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $vehicle['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($vehicle['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo SITE_URL; ?>/vehicle_public.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Trips -->
    <?php if (!empty($trips)): ?>
        <div class="card">
            <h2><i class="fas fa-car"></i> Recent Trips</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle</th>
                            <th>Pickup</th>
                            <th>Return</th>
                            <th>Days</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?php echo $trip['trip_id']; ?></td>
                                <td><?php echo htmlspecialchars($trip['vehicle_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['pickup_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($trip['return_date'])); ?></td>
                                <td><?php echo $trip['trip_duration_days']; ?></td>
                                <td><?php echo formatCurrency($trip['total_amount']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $trip['trip_status'] === 'confirmed' ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst($trip['trip_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Transactions -->
    <?php if (!empty($transactions)): ?>
        <div class="card">
            <h2><i class="fas fa-exchange-alt"></i> Recent Transactions</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance Before</th>
                            <th>Balance After</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst($txn['transaction_type']); ?>
                                    </span>
                                </td>
                                <td style="color: <?php echo $txn['amount'] >= 0 ? 'green' : 'red'; ?>">
                                    <?php echo formatCurrency($txn['amount']); ?>
                                </td>
                                <td><?php echo formatCurrency($txn['balance_before']); ?></td>
                                <td><?php echo formatCurrency($txn['balance_after']); ?></td>
                                <td><?php echo htmlspecialchars($txn['description'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
