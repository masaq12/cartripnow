<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'User Management - Admin';

// Initialize users array to avoid undefined variable error
$users = [];

try {
    $pdo = getPDOConnection();
    
    // Handle user status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'suspend') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = 'User suspended successfully';
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = 'User activated successfully';
        } elseif ($action === 'freeze') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'frozen' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = 'User account frozen';
        }
        
        redirect(SITE_URL . '/admin/users.php');
    }
    
    // Get filter parameters
    $filter_type = $_GET['type'] ?? 'all';
    $filter_status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Build query - Note: The schema uses renter/owner, not guest/host
    $query = "SELECT u.*, 
              COALESCE(rb.current_balance, 0) as renter_balance,
              COALESCE(ob.available_balance, 0) as owner_balance
              FROM users u
              LEFT JOIN renter_balances rb ON u.user_id = rb.user_id
              LEFT JOIN owner_balances ob ON u.user_id = ob.user_id
              WHERE u.user_type != 'admin'";
    
    $params = [];
    
    if ($filter_type !== 'all') {
        $query .= " AND u.user_type = ?";
        $params[] = $filter_type;
    }
    
    if ($filter_status !== 'all') {
        $query .= " AND u.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading users: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-users"></i> User Management
    </h1>
    
    <!-- Filters -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="">
            <div class="grid grid-4">
                <div class="form-group">
                    <label class="form-label">User Type</label>
                    <select name="type" class="form-control">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="renter" <?php echo $filter_type === 'renter' ? 'selected' : ''; ?>>Renters</option>
                        <option value="owner" <?php echo $filter_type === 'owner' ? 'selected' : ''; ?>>Owners</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="frozen" <?php echo $filter_status === 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Users (<?php echo count($users); ?>)</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                <?php 
                                // Handle both renter/owner and legacy guest/host types
                                $balance = 0;
                                if (in_array($user['user_type'], ['renter', 'guest'])) {
                                        $balance = $user['renter_balance'];
                    } elseif (in_array($user['user_type'], ['owner', 'host'])) {
                        $balance = $user['owner_balance'];
                    }
                    echo formatCurrency($balance);
                    ?>
                </td>
                                <td>
                                    <?php
                                    $status_class = 'badge-success';
                                    if ($user['status'] === 'suspended') $status_class = 'badge-warning';
                                    if ($user['status'] === 'frozen') $status_class = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($user['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Suspend this user?')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="freeze">
                                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Freeze this user account?')">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Activate this user?')">
                                                    <i class="fas fa-check"></i>
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
