<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Payout Management - Admin Dashboard';

// Handle payout actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        $payout_id = (int)$_POST['payout_id'];
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            // Get payout details
            $stmt = $pdo->prepare("
                SELECT p.*, pm.method_type, pm.account_details, u.full_name 
                FROM payouts p
                JOIN payout_methods pm ON p.payout_method_id = pm.payout_method_id
                JOIN users u ON p.user_id = u.user_id
                WHERE p.payout_id = ? AND p.status = 'pending'
            ");
            $stmt->execute([$payout_id]);
            $payout = $stmt->fetch();
            
            if (!$payout) {
                throw new Exception('Payout not found or already processed');
            }
            
            // Check owner balance
            $stmt = $pdo->prepare("SELECT available_balance FROM owner_balances WHERE user_id = ?");
            $stmt->execute([$payout['user_id']]);
            $available_balance = $stmt->fetchColumn();
            
            if ($available_balance < $payout['amount']) {
                throw new Exception('Insufficient balance for payout');
            }
            
            // Update payout status
            $stmt = $pdo->prepare("
                UPDATE payouts 
                SET status = 'processing', 
                    processed_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '
Admin approved: ', ?)
                WHERE payout_id = ?
            ");
            $stmt->execute([$_POST['notes'] ?? 'Approved', $payout_id]);
            
            // Deduct from owner balance
            $new_balance = $available_balance - $payout['amount'];
            $stmt = $pdo->prepare("
                UPDATE owner_balances 
                SET available_balance = ?,
                    total_paid_out = total_paid_out + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$new_balance, $payout['amount'], $payout['user_id']]);
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id, transaction_type, amount, balance_before, balance_after,
                    reference_type, reference_id, description
                ) VALUES (?, 'payout', ?, ?, ?, 'payout', ?, ?)
            ");
            $stmt->execute([
                $payout['user_id'],
                $payout['amount'],
                $available_balance,
                $new_balance,
                $payout_id,
                "Payout to {$payout['method_type']}"
            ]);
            
            // Mark as completed (in real system, this would be done after actual payment)
            $stmt = $pdo->prepare("UPDATE payouts SET status = 'completed' WHERE payout_id = ?");
            $stmt->execute([$payout_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Payout approved and processed successfully';
            
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("
                UPDATE payouts 
                SET status = 'cancelled',
                    processed_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '
Admin rejected: ', ?)
                WHERE payout_id = ? AND status = 'pending'
            ");
            $stmt->execute([$_POST['notes'] ?? 'Rejected', $payout_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Payout rejected';
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    redirect(SITE_URL . '/admin/payouts.php');
}

try {
    $pdo = getPDOConnection();
    
    // Get filter
    $status_filter = $_GET['status'] ?? 'all';
    
    // Build query
    $query = "
        SELECT p.*, u.full_name, u.email, pm.method_type, pm.account_details, pm.account_holder_name,
        ob.available_balance
        FROM payouts p
        JOIN users u ON p.user_id = u.user_id
        JOIN payout_methods pm ON p.payout_method_id = pm.payout_method_id
        LEFT JOIN owner_balances ob ON p.user_id = ob.user_id
        WHERE 1=1
    ";
    
    $params = [];
    if ($status_filter !== 'all') {
        $query .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY 
        CASE p.status 
            WHEN 'pending' THEN 1
            WHEN 'processing' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
        END,
        p.requested_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payouts = $stmt->fetchAll();
    
    // Get statistics
    $stats = [
        'pending' => (int)$pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'pending'")->fetchColumn(),
        'processing' => (int)$pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'processing'")->fetchColumn(),
        'completed' => (int)$pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'completed'")->fetchColumn(),
        'total_amount' => (float)($pdo->query("SELECT SUM(amount) FROM payouts WHERE status = 'completed'")->fetchColumn() ?? 0),
    ];
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading payout data: ' . $e->getMessage();
    $payouts = [];
    $stats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'total_amount' => 0
    ];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-money-check-alt"></i> Payout Management
    </h1>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card warning">
            <i class="fas fa-clock" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-spinner" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo $stats['processing']; ?></div>
            <div class="stat-label">Processing</div>
        </div>
        
        <div class="stat-card success">
            <i class="fas fa-check-circle" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        
        <div class="stat-card secondary">
            <i class="fas fa-dollar-sign" style="font-size: 24px;"></i>
            <div class="stat-value"><?php echo formatCurrency($stats['total_amount']); ?></div>
            <div class="stat-label">Total Paid Out</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: end;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">Filter by Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filter
            </button>
        </form>
    </div>
    
    <!-- Payouts Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-list"></i> Payout Requests</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Host</th>
                        <th>Amount</th>
                        <th>Available Balance</th>
                        <th>Method</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payouts)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No payout requests found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payouts as $payout): ?>
                            <tr>
                                <td>#<?php echo $payout['payout_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payout['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($payout['email']); ?></small>
                                </td>
                                <td><strong><?php echo formatCurrency($payout['amount']); ?></strong></td>
                                <td><?php echo formatCurrency($payout['available_balance'] ?? 0); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucwords(str_replace('_', ' ', $payout['method_type'])); ?>
                                    </span>
                                    <br>
                                    <button onclick="showBankDetails(<?php echo $payout['payout_id']; ?>, '<?php echo htmlspecialchars(addslashes($payout['method_type'])); ?>', '<?php echo htmlspecialchars(addslashes($payout['account_holder_name'])); ?>', <?php echo htmlspecialchars(json_encode($payout['account_details'])); ?>)" 
                                            class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; margin-top: 5px;">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($payout['requested_at'])); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-warning';
                                    if ($payout['status'] === 'completed') $badge_class = 'badge-success';
                                    if ($payout['status'] === 'failed' || $payout['status'] === 'cancelled') $badge_class = 'badge-danger';
                                    if ($payout['status'] === 'processing') $badge_class = 'badge-info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($payout['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php if ($payout['status'] === 'pending'): ?>
                                            <button onclick="showPayoutModal(<?php echo $payout['payout_id']; ?>, 'approve', '<?php echo htmlspecialchars($payout['full_name']); ?>', '<?php echo formatCurrency($payout['amount']); ?>')" 
                                                    class="btn btn-success" style="padding: 5px 10px; font-size: 12px; width: 100%;">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="showPayoutModal(<?php echo $payout['payout_id']; ?>, 'reject', '<?php echo htmlspecialchars($payout['full_name']); ?>', '<?php echo formatCurrency($payout['amount']); ?>')" 
                                                    class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; width: 100%;">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #999;">No actions</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($payout['notes']): ?>
                                <tr>
                                    <td colspan="8" style="background-color: #f5f5f5; padding: 10px;">
                                        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($payout['notes'])); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bank Details Modal -->
<div id="bankDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="bankDetailsTitle" style="margin: 0;"></h3>
            <button onclick="hideBankDetailsModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        <div id="bankDetailsContent" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <!-- Details will be populated here -->
        </div>
        <button onclick="hideBankDetailsModal()" class="btn btn-outline" style="width: 100%; margin-top: 20px;">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
</div>

<!-- Payout Action Modal -->
<div id="payoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
        <h3 id="modalTitle" style="margin-bottom: 20px;"></h3>
        <p id="modalMessage" style="margin-bottom: 20px;"></p>
        
        <form method="POST" style="margin-bottom: 15px;">
            <input type="hidden" name="payout_id" id="modalPayoutId">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Add notes for the host..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button type="button" onclick="hidePayoutModal()" class="btn btn-outline" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showBankDetails(payoutId, methodType, accountHolder, accountDetails) {
    const modal = document.getElementById('bankDetailsModal');
    const title = document.getElementById('bankDetailsTitle');
    const content = document.getElementById('bankDetailsContent');
    
    let details = {};
    try {
        details = typeof accountDetails === 'string' ? JSON.parse(accountDetails) : accountDetails;
    } catch (e) {
        details = {};
    }
    
    // Set title
    const methodName = methodType.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    title.innerHTML = `<i class="fas fa-university"></i> ${methodName} Details`;
    
    // Build content based on method type
    let html = `
        <div style="margin-bottom: 15px;">
            <strong style="color: #666;">Account Holder:</strong><br>
            <span style="font-size: 18px;">${accountHolder}</span>
        </div>
    `;
    
    if (methodType === 'bank_account') {
        html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Bank Name:</strong><br>
                <span style="font-size: 16px;">${details.bank_name || 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Account Number:</strong><br>
                <span style="font-size: 16px; font-family: monospace;">${details.account_number || 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Routing Number:</strong><br>
                <span style="font-size: 16px; font-family: monospace;">${details.routing_number || 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Account Type:</strong><br>
                <span style="font-size: 16px;">${details.account_type ? details.account_type.charAt(0).toUpperCase() + details.account_type.slice(1) : 'N/A'}</span>
            </div>
        `;
    } else if (methodType === 'crypto_wallet') {
        html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Cryptocurrency:</strong><br>
                <span style="font-size: 16px;">${details.crypto_type ? details.crypto_type.charAt(0).toUpperCase() + details.crypto_type.slice(1) : 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Wallet Address:</strong><br>
                <span style="font-size: 14px; font-family: monospace; word-break: break-all;">${details.wallet_address || 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Network:</strong><br>
                <span style="font-size: 16px;">${details.network ? details.network.charAt(0).toUpperCase() + details.network.slice(1) : 'N/A'}</span>
            </div>
        `;
    } else if (methodType === 'business_account') {
        html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Business Name:</strong><br>
                <span style="font-size: 16px;">${details.business_name || 'N/A'}</span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #666;">Account Number:</strong><br>
                <span style="font-size: 16px; font-family: monospace;">${details.account_number || 'N/A'}</span>
            </div>
        `;
    }
    
    html += `
        <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-top: 20px;">
            <i class="fas fa-lock" style="color: #856404;"></i>
            <span style="color: #856404; font-size: 13px;"><strong>Confidential Information</strong> - Handle with care</span>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'flex';
}

function hideBankDetailsModal() {
    document.getElementById('bankDetailsModal').style.display = 'none';
}

function showPayoutModal(payoutId, action, hostName, amount) {
    const modal = document.getElementById('payoutModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    
    document.getElementById('modalPayoutId').value = payoutId;
    document.getElementById('modalAction').value = action;
    
    if (action === 'approve') {
        title.innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Approve Payout';
        message.textContent = `Approve payout of ${amount} to ${hostName}?`;
    } else {
        title.innerHTML = '<i class="fas fa-times-circle" style="color: red;"></i> Reject Payout';
        message.textContent = `Reject payout request of ${amount} from ${hostName}?`;
    }
    
    modal.style.display = 'flex';
}

function hidePayoutModal() {
    document.getElementById('payoutModal').style.display = 'none';
}

// Close modals on outside click
document.getElementById('payoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hidePayoutModal();
    }
});

document.getElementById('bankDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideBankDetailsModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
