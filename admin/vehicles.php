<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'All Vehicles - Admin - Car Trip Now';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getPDOConnection();
        $vehicle_id = $_POST['vehicle_id'] ?? 0;
        
        if ($_POST['action'] === 'suspend') {
            $stmt = $pdo->prepare("UPDATE vehicles SET status = 'suspended' WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_id]);
            $_SESSION['success_message'] = 'Vehicle suspended successfully';
        } elseif ($_POST['action'] === 'activate') {
            $stmt = $pdo->prepare("UPDATE vehicles SET status = 'active' WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_id]);
            $_SESSION['success_message'] = 'Vehicle activated successfully';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_id]);
            $_SESSION['success_message'] = 'Vehicle deleted successfully';
        }
        
        redirect($_SERVER['PHP_SELF']);
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating vehicle: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Filter options
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query
    $query = "
        SELECT v.*, u.full_name as owner_name, u.email as owner_email,
        (SELECT COUNT(*) FROM trips WHERE vehicle_id = v.vehicle_id) as total_trips
        FROM vehicles v
        JOIN users u ON v.owner_id = u.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter) {
        $query .= " AND v.status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $query .= " AND (v.make LIKE ? OR v.model LIKE ? OR v.pickup_city LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY v.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
    
    // Get stats
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")->fetchColumn(),
        'inactive' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'inactive'")->fetchColumn(),
        'suspended' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'suspended'")->fetchColumn(),
    ];
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading vehicles';
    $vehicles = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0];
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-car"></i> All Vehicles
    </h1>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-car"></i>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Vehicles</div>
        </div>
        <div class="stat-card success">
            <i class="fas fa-check-circle"></i>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card warning">
            <i class="fas fa-pause-circle"></i>
            <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
            <div class="stat-label">Inactive</div>
        </div>
        <div class="stat-card secondary">
            <i class="fas fa-ban"></i>
            <div class="stat-value"><?php echo number_format($stats['suspended']); ?></div>
            <div class="stat-label">Suspended</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <form method="GET" class="grid grid-3" style="gap: 15px;">
            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Make, model, city, or owner..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Vehicles Table -->
    <div class="card">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vehicle</th>
                        <th>Owner</th>
                        <th>Location</th>
                        <th>Price/Day</th>
                        <th>Trips</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #666; padding: 40px;">
                                No vehicles found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td><?php echo $vehicle['vehicle_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($vehicle['owner_name']); ?>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($vehicle['owner_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state']); ?></td>
                                <td><?php echo formatCurrency($vehicle['daily_price']); ?></td>
                                <td><?php echo $vehicle['total_trips']; ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-info';
                                    if ($vehicle['status'] === 'active') $badge_class = 'badge-success';
                                    if ($vehicle['status'] === 'suspended') $badge_class = 'badge-danger';
                                    if ($vehicle['status'] === 'inactive') $badge_class = 'badge-warning';
                                    if ($vehicle['status'] === 'maintenance') $badge_class = 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($vehicle['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <a href="<?php echo SITE_URL; ?>/listing_public.php?id=<?php echo $vehicle['vehicle_id']; ?>" 
                                           class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($vehicle['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;"
                                                        onclick="return confirm('Suspend this vehicle?')">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;"
                                                        onclick="return confirm('Activate this vehicle?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;"
                                                    onclick="return confirm('Delete this vehicle? This cannot be undone!')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
