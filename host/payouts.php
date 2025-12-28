<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Payouts - Host - Car Trip Now';

$owner_id = $_SESSION['user_id'];

// Handle payout request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    try {
        $pdo = getPDOConnection();
        
        $payout_method_id = $_POST['payout_method_id'] ?? 0;
        $amount = floatval($_POST['amount'] ?? 0);
        
        // Get balance
        $stmt = $pdo->prepare("SELECT available_balance, total_paid_out FROM owner_balances WHERE user_id = ?");
        $stmt->execute([$owner_id]);
        $balance_data = $stmt->fetch();
        
        if (!$balance_data) {
            throw new Exception('Balance not found');
        }
        
        if ($balance_data['available_balance'] < $amount) {
            throw new Exception('Insufficient balance');
        }
        
        if ($amount < MIN_PAYOUT_AMOUNT) {
            throw new Exception('Minimum payout amount is ' . formatCurrency(MIN_PAYOUT_AMOUNT));
        }
        
        // Verify payout method exists
        $stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE payout_method_id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([$payout_method_id, $owner_id]);
        $method = $stmt->fetch();
        
        if (!$method) {
            throw new Exception('Invalid payout method');
        }
        
        $pdo->beginTransaction();
        
        // Create payout request
        $stmt = $pdo->prepare("
            INSERT INTO payouts (user_id, payout_method_id, amount, status, requested_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$owner_id, $payout_method_id, $amount]);
        $payout_id = $pdo->lastInsertId();
        
        // Update balance
        $new_balance = $balance_data['available_balance'] - $amount;
        $new_paid_out = $balance_data['total_paid_out'] + $amount;
        
        $stmt = $pdo->prepare("
            UPDATE owner_balances 
            SET available_balance = ?, total_paid_out = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$new_balance, $new_paid_out, $owner_id]);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, description)
            VALUES (?, 'payout', ?, ?, ?, 'payout', ?, ?)
        ");
        $stmt->execute([
            $owner_id,
            $amount,
            $balance_data['available_balance'],
            $new_balance,
            $payout_id,
            'Payout request #' . $payout_id
        ]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Payout request submitted successfully. It will be processed within 3-5 business days.';
        redirect($_SERVER['PHP_SELF']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get balance
    $stmt = $pdo->prepare("SELECT * FROM owner_balances WHERE user_id = ?");
    $stmt->execute([$owner_id]);
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
    
    // Get payout methods
    $stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ? AND status = 'active' ORDER BY is_default DESC");
    $stmt->execute([$owner_id]);
    $payout_methods = $stmt->fetchAll();
    
    // Get payout history
    $stmt = $pdo->prepare("
        SELECT p.*, pm.method_type, pm.account_holder_name
        FROM payouts p
        JOIN payout_methods pm ON p.payout_method_id = pm.payout_method_id
        WHERE p.user_id = ?
        ORDER BY p.requested_at DESC
    ");
    $stmt->execute([$owner_id]);
    $payouts = $stmt->fetchAll();
    
    // Get stats
    $stats = [
        'total_payouts' => count($payouts),
        'pending_payouts' => count(array_filter($payouts, fn($p) => $p['status'] === 'pending')),
        'completed_payouts' => count(array_filter($payouts, fn($p) => $p['status'] === 'completed')),
    ];
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading payout data';
    $balance = [
        'available_balance' => 0,
        'pending_balance' => 0,
        'total_earned' => 0,
        'total_paid_out' => 0,
        'platform_fees_paid' => 0,
        'insurance_fees_paid' => 0
    ];
    $payout_methods = [];
    $payouts = [];
    $stats = ['total_payouts' => 0, 'pending_payouts' => 0, 'completed_payouts' => 0];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-hand-holding-usd"></i> Payouts
    </h1>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Request Payout -->
        <div>
            <!-- Balance Display -->
            <div class="card">
                <h2><i class="fas fa-wallet"></i> Your Balance</h2>
                <div style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 30px; border-radius: 12px; color: white; margin-top: 20px;">
                    <p style="margin: 0; opacity: 0.9;">Available Balance</p>
                    <h1 style="margin: 10px 0; font-size: 48px;"><?php echo formatCurrency($balance['available_balance']); ?></h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">
                        Pending: <?php echo formatCurrency($balance['pending_balance']); ?>
                    </p>
                </div>
                
                <div style="display: flex; gap: 20px; margin-top: 20px;">
                    <div style="flex: 1; text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <p style="margin: 0; color: #666; font-size: 14px;">Total Earned</p>
                        <p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold;">
                            <?php echo formatCurrency($balance['total_earned']); ?>
                        </p>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <p style="margin: 0; color: #666; font-size: 14px;">Total Paid Out</p>
                        <p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold;">
                            <?php echo formatCurrency($balance['total_paid_out']); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Request Form -->
            <div class="card">
                <h2><i class="fas fa-paper-plane"></i> Request Payout</h2>
                
                <?php if (empty($payout_methods)): ?>
                    <div style="text-align: center; padding: 30px; background-color: #fff3cd; border-radius: 8px; margin-top: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #856404; margin-bottom: 15px;"></i>
                        <p style="color: #856404; margin-bottom: 15px;">You need to add a payout method first</p>
                        <a href="payout_settings.php" class="btn btn-warning">
                            <i class="fas fa-cog"></i> Go to Payout Settings
                        </a>
                    </div>
                <?php elseif ($balance['available_balance'] < MIN_PAYOUT_AMOUNT): ?>
                    <div style="text-align: center; padding: 30px; background-color: #f8d7da; border-radius: 8px; margin-top: 20px;">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: #721c24; margin-bottom: 15px;"></i>
                        <p style="color: #721c24;">Minimum payout amount is <?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?></p>
                        <p style="color: #721c24;">Your current balance: <?php echo formatCurrency($balance['available_balance']); ?></p>
                    </div>
                <?php else: ?>
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="request_payout" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Payout Method *</label>
                            <select name="payout_method_id" class="form-control" required>
                                <option value="">Select payout method...</option>
                                <?php foreach ($payout_methods as $method): ?>
                                    <?php $details = json_decode($method['account_details'], true); ?>
                                    <option value="<?php echo $method['payout_method_id']; ?>" <?php echo $method['is_default'] ? 'selected' : ''; ?>>
                                        <?php
                                        $type_name = ucwords(str_replace('_', ' ', $method['method_type']));
                                        echo $type_name . ' - ' . htmlspecialchars($method['account_holder_name']);
                                        if ($method['is_default']) echo ' (Default)';
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Amount *</label>
                            <input type="number" name="amount" class="form-control" 
                                   min="<?php echo MIN_PAYOUT_AMOUNT; ?>" 
                                   max="<?php echo $balance['available_balance']; ?>" 
                                   step="0.01" 
                                   value="<?php echo $balance['available_balance']; ?>" 
                                   required>
                            <small style="color: #666;">
                                Min: <?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?>, 
                                Max: <?php echo formatCurrency($balance['available_balance']); ?>
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-paper-plane"></i> Request Payout
                        </button>
                    </form>
                <?php endif; ?>
                
                <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                    <ul style="margin: 10px 0; padding-left: 20px; line-height: 1.8;">
                        <li>Payouts are processed within 3-5 business days</li>
                        <li>Minimum payout amount: <?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?></li>
                        <li>You'll receive an email when your payout is processed</li>
                        <li>Make sure your payout method details are correct</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Payout History -->
        <div>
            <div class="card">
                <h2><i class="fas fa-history"></i> Payout History</h2>
                
                <!-- Stats -->
                <div class="grid grid-3" style="gap: 15px; margin: 20px 0;">
                    <div style="text-align: center; padding: 15px; background-color: var(--light-color); border-radius: 8px;">
                        <p style="margin: 0; font-size: 24px; font-weight: bold;"><?php echo $stats['total_payouts']; ?></p>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">Total</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: #fff3cd; border-radius: 8px;">
                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: #856404;"><?php echo $stats['pending_payouts']; ?></p>
                        <p style="margin: 5px 0 0 0; color: #856404; font-size: 12px;">Pending</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background-color: #d4edda; border-radius: 8px;">
                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: #155724;"><?php echo $stats['completed_payouts']; ?></p>
                        <p style="margin: 5px 0 0 0; color: #155724; font-size: 12px;">Completed</p>
                    </div>
                </div>
                
                <?php if (empty($payouts)): ?>
                    <div style="text-align: center; padding: 40px 20px; background-color: var(--light-color); border-radius: 8px;">
                        <i class="fas fa-receipt" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                        <p style="color: #666;">No payout history yet</p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <?php foreach ($payouts as $payout): ?>
                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div>
                                        <p style="margin: 0; font-size: 12px; color: #666;">Payout #<?php echo $payout['payout_id']; ?></p>
                                        <p style="margin: 5px 0; font-size: 24px; font-weight: bold;">
                                            <?php echo formatCurrency($payout['amount']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php
                                        $badge_class = 'badge-info';
                                        $badge_icon = 'fa-clock';
                                        if ($payout['status'] === 'pending') {
                                            $badge_class = 'badge-warning';
                                            $badge_icon = 'fa-clock';
                                        } elseif ($payout['status'] === 'processing') {
                                            $badge_class = 'badge-info';
                                            $badge_icon = 'fa-spinner';
                                        } elseif ($payout['status'] === 'completed') {
                                            $badge_class = 'badge-success';
                                            $badge_icon = 'fa-check';
                                        } elseif ($payout['status'] === 'failed') {
                                            $badge_class = 'badge-danger';
                                            $badge_icon = 'fa-times';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <i class="fas <?php echo $badge_icon; ?>"></i>
                                            <?php echo ucfirst($payout['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <p style="margin: 5px 0; font-size: 14px; color: #666;">
                                    <i class="fas fa-credit-card"></i>
                                    <?php echo ucwords(str_replace('_', ' ', $payout['method_type'])); ?> - 
                                    <?php echo htmlspecialchars($payout['account_holder_name']); ?>
                                </p>
                                
                                <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                    <i class="fas fa-calendar"></i>
                                    Requested: <?php echo date('M d, Y h:i A', strtotime($payout['requested_at'])); ?>
                                </p>
                                
                                <?php if ($payout['processed_at']): ?>
                                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                        <i class="fas fa-check-circle"></i>
                                        Processed: <?php echo date('M d, Y h:i A', strtotime($payout['processed_at'])); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($payout['notes']): ?>
                                    <p style="margin: 10px 0 0 0; padding: 10px; background-color: var(--light-color); border-radius: 4px; font-size: 12px;">
                                        <i class="fas fa-info-circle"></i>
                                        <?php echo htmlspecialchars($payout['notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
