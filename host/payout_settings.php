<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Payout Settings - Host - Car Trip Now';

$owner_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        
        if (isset($_POST['add_method'])) {
            $method_type = $_POST['method_type'] ?? '';
            $account_holder_name = $_POST['account_holder_name'] ?? '';
            
            $account_details = [];
            
            if ($method_type === 'bank_account') {
                $account_details = [
                    'account_number' => $_POST['account_number'] ?? '',
                    'routing_number' => $_POST['routing_number'] ?? '',
                    'bank_name' => $_POST['bank_name'] ?? '',
                    'account_type' => $_POST['account_type'] ?? 'checking'
                ];
            } elseif ($method_type === 'crypto_wallet') {
                $account_details = [
                    'wallet_address' => $_POST['wallet_address'] ?? '',
                    'crypto_type' => $_POST['crypto_type'] ?? 'bitcoin',
                    'network' => $_POST['network'] ?? 'mainnet'
                ];
            } elseif ($method_type === 'business_account') {
                $account_details = [
                    'account_number' => $_POST['business_account_number'] ?? '',
                    'business_name' => $_POST['business_name'] ?? ''
                ];
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO payout_methods (user_id, method_type, account_details, account_holder_name, is_default, status)
                VALUES (?, ?, ?, ?, 0, 'active')
            ");
            
            $stmt->execute([
                $owner_id,
                $method_type,
                json_encode($account_details),
                $account_holder_name
            ]);
            
            $_SESSION['success_message'] = 'Payout method added successfully';
            redirect($_SERVER['PHP_SELF']);
            
        } elseif (isset($_POST['set_default'])) {
            $payout_method_id = $_POST['payout_method_id'] ?? 0;
            
            $pdo->beginTransaction();
            
            // Unset all defaults
            $stmt = $pdo->prepare("UPDATE payout_methods SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$owner_id]);
            
            // Set new default
            $stmt = $pdo->prepare("UPDATE payout_methods SET is_default = 1 WHERE payout_method_id = ? AND user_id = ?");
            $stmt->execute([$payout_method_id, $owner_id]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Default payout method updated';
            redirect($_SERVER['PHP_SELF']);
            
        } elseif (isset($_POST['delete_method'])) {
            $payout_method_id = $_POST['payout_method_id'] ?? 0;
            
            $stmt = $pdo->prepare("DELETE FROM payout_methods WHERE payout_method_id = ? AND user_id = ?");
            $stmt->execute([$payout_method_id, $owner_id]);
            
            $_SESSION['success_message'] = 'Payout method deleted';
            redirect($_SERVER['PHP_SELF']);
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get all payout methods
    $stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$owner_id]);
    $payout_methods = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading payout methods';
    $payout_methods = [];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-cog"></i> Payout Settings
    </h1>
    
    <div class="grid grid-2" style="gap: 30px;">
        <!-- Existing Payout Methods -->
        <div>
            <div class="card">
                <h2><i class="fas fa-credit-card"></i> Your Payout Methods</h2>
                
                <?php if (empty($payout_methods)): ?>
                    <div style="text-align: center; padding: 40px 20px; background-color: var(--light-color); border-radius: 8px; margin-top: 20px;">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                        <p style="color: #666;">No payout methods configured yet.</p>
                        <p style="color: #666;">Add a payout method to receive your earnings.</p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <?php foreach ($payout_methods as $method): ?>
                            <?php $details = json_decode($method['account_details'], true); ?>
                            <div style="border: 2px solid <?php echo $method['is_default'] ? 'var(--success-color)' : '#ddd'; ?>; padding: 20px; border-radius: 8px; margin-bottom: 15px; position: relative;">
                                <?php if ($method['is_default']): ?>
                                    <span class="badge badge-success" style="position: absolute; top: 10px; right: 10px;">
                                        <i class="fas fa-check"></i> Default
                                    </span>
                                <?php endif; ?>
                                
                                <h3 style="margin: 0 0 15px 0;">
                                    <?php
                                    $icon = 'fa-university';
                                    $type_name = 'Bank Account';
                                    if ($method['method_type'] === 'crypto_wallet') {
                                        $icon = 'fa-bitcoin';
                                        $type_name = 'Crypto Wallet';
                                    } elseif ($method['method_type'] === 'business_account') {
                                        $icon = 'fa-building';
                                        $type_name = 'Business Account';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <?php echo $type_name; ?>
                                </h3>
                                
                                <p style="margin: 5px 0;">
                                    <strong>Account Holder:</strong> <?php echo htmlspecialchars($method['account_holder_name']); ?>
                                </p>
                                
                                <?php if ($method['method_type'] === 'bank_account'): ?>
                                    <p style="margin: 5px 0;">
                                        <strong>Bank:</strong> <?php echo htmlspecialchars($details['bank_name']); ?>
                                    </p>
                                    <p style="margin: 5px 0;">
                                        <strong>Account:</strong> ****<?php echo substr($details['account_number'], -4); ?>
                                    </p>
                                    <p style="margin: 5px 0;">
                                        <strong>Type:</strong> <?php echo ucfirst($details['account_type']); ?>
                                    </p>
                                <?php elseif ($method['method_type'] === 'crypto_wallet'): ?>
                                    <p style="margin: 5px 0;">
                                        <strong>Crypto:</strong> <?php echo ucfirst($details['crypto_type']); ?>
                                    </p>
                                    <p style="margin: 5px 0; word-break: break-all;">
                                        <strong>Address:</strong> <?php echo htmlspecialchars(substr($details['wallet_address'], 0, 20) . '...' . substr($details['wallet_address'], -10)); ?>
                                    </p>
                                <?php elseif ($method['method_type'] === 'business_account'): ?>
                                    <p style="margin: 5px 0;">
                                        <strong>Business:</strong> <?php echo htmlspecialchars($details['business_name']); ?>
                                    </p>
                                    <p style="margin: 5px 0;">
                                        <strong>Account:</strong> ****<?php echo substr($details['account_number'], -4); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <?php if (!$method['is_default']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="payout_method_id" value="<?php echo $method['payout_method_id']; ?>">
                                            <button type="submit" name="set_default" class="btn btn-success" style="padding: 8px 15px; font-size: 14px;">
                                                <i class="fas fa-check"></i> Set as Default
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payout_method_id" value="<?php echo $method['payout_method_id']; ?>">
                                        <button type="submit" name="delete_method" class="btn btn-danger" style="padding: 8px 15px; font-size: 14px;"
                                                onclick="return confirm('Delete this payout method?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add New Payout Method -->
        <div>
            <div class="card">
                <h2><i class="fas fa-plus"></i> Add Payout Method</h2>
                
                <form method="POST" id="payoutForm">
                    <input type="hidden" name="add_method" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Method Type *</label>
                        <select name="method_type" id="method_type" class="form-control" required>
                            <option value="">Select method type...</option>
                            <option value="bank_account">Bank Account</option>
                            <option value="crypto_wallet">Crypto Wallet</option>
                            <option value="business_account">Business Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control" required>
                    </div>
                    
                    <!-- Bank Account Fields -->
                    <div id="bank_fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Bank Name *</label>
                            <input type="text" name="bank_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number *</label>
                            <input type="text" name="account_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Routing Number *</label>
                            <input type="text" name="routing_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Type *</label>
                            <select name="account_type" class="form-control">
                                <option value="checking">Checking</option>
                                <option value="savings">Savings</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Crypto Wallet Fields -->
                    <div id="crypto_fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Cryptocurrency *</label>
                            <select name="crypto_type" class="form-control">
                                <option value="bitcoin">Bitcoin (BTC)</option>
                                <option value="ethereum">Ethereum (ETH)</option>
                                <option value="usdt">Tether (USDT)</option>
                                <option value="usdc">USD Coin (USDC)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Wallet Address *</label>
                            <input type="text" name="wallet_address" class="form-control" placeholder="Enter your wallet address">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Network *</label>
                            <select name="network" class="form-control">
                                <option value="mainnet">Mainnet</option>
                                <option value="testnet">Testnet</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Business Account Fields -->
                    <div id="business_fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Business Name *</label>
                            <input type="text" name="business_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number *</label>
                            <input type="text" name="business_account_number" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Add Payout Method
                    </button>
                </form>
            </div>
            
            <!-- Info Card -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Payout Information</h3>
                <ul style="line-height: 2;">
                    <li>Minimum payout amount: <strong><?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?></strong></li>
                    <li>Payouts are processed within 3-5 business days</li>
                    <li>You can set up multiple payout methods</li>
                    <li>Mark one method as default for automatic payouts</li>
                    <li>All payment information is encrypted and secure</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('method_type').addEventListener('change', function() {
    const bankFields = document.getElementById('bank_fields');
    const cryptoFields = document.getElementById('crypto_fields');
    const businessFields = document.getElementById('business_fields');
    
    // Hide all
    bankFields.style.display = 'none';
    cryptoFields.style.display = 'none';
    businessFields.style.display = 'none';
    
    // Show relevant fields
    if (this.value === 'bank_account') {
        bankFields.style.display = 'block';
        bankFields.querySelectorAll('input, select').forEach(el => el.required = true);
    } else if (this.value === 'crypto_wallet') {
        cryptoFields.style.display = 'block';
        cryptoFields.querySelectorAll('input, select').forEach(el => el.required = true);
    } else if (this.value === 'business_account') {
        businessFields.style.display = 'block';
        businessFields.querySelectorAll('input, select').forEach(el => el.required = true);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
