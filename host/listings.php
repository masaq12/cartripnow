<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'My Vehicles - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get all vehicles for this owner
    $stmt = $pdo->prepare("
        SELECT v.*,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo,
        (SELECT COUNT(*) FROM trips WHERE vehicle_id = v.vehicle_id) as trip_count
        FROM vehicles v
        WHERE v.owner_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicles = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading vehicles';
    $vehicles = [];
}

include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1><i class="fas fa-car"></i> My Vehicles</h1>
        <a href="add_listing.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Vehicle
        </a>
    </div>
    
    <?php if (empty($vehicles)): ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <i class="fas fa-car" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
            <h3>No vehicles yet</h3>
            <p>Add your first vehicle to start earning!</p>
            <a href="add_listing.php" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Add First Vehicle
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="card">
                    <div class="grid grid-2" style="gap: 20px;">
                        <div>
                            <?php if ($vehicle['primary_photo']): ?>
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($vehicle['primary_photo']); ?>" 
                                     alt="<?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                                     style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;"
                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                            <?php else: ?>
                                <div style="width: 100%; height: 200px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-car" style="font-size: 48px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <h3 style="margin: 0;">
                                    <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                </h3>
                                <?php
                                $status_class = 'badge-success';
                                if ($vehicle['status'] === 'inactive') $status_class = 'badge-warning';
                                if ($vehicle['status'] === 'suspended') $status_class = 'badge-danger';
                                if ($vehicle['status'] === 'maintenance') $status_class = 'badge-secondary';
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($vehicle['status']); ?>
                                </span>
                            </div>
                            
                            <p style="color: #666; margin: 5px 0;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state']); ?>
                            </p>
                            
                            <div style="margin: 15px 0;">
                                <div style="display: flex; gap: 15px; color: #666; font-size: 14px;">
                                    <span><i class="fas fa-users"></i> <?php echo $vehicle['seats']; ?> seats</span>
                                    <span><i class="fas fa-door-closed"></i> <?php echo $vehicle['doors']; ?> doors</span>
                                    <span><i class="fas fa-cog"></i> <?php echo ucfirst($vehicle['transmission']); ?></span>
                                </div>
                            </div>
                            
                            <div style="background-color: var(--light-color); padding: 10px; border-radius: 8px; margin: 15px 0;">
                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-color);">
                                    <?php echo formatCurrency($vehicle['daily_price']); ?> / day
                                </p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                    <?php echo $vehicle['trip_count']; ?> total trips
                                </p>
                            </div>
                            
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="edit_listing.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-primary" style="padding: 8px 15px; font-size: 14px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="manage_photos.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-secondary" style="padding: 8px 15px; font-size: 14px;">
                                    <i class="fas fa-images"></i> Photos
                                </a>
                                <a href="listing_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-outline" style="padding: 8px 15px; font-size: 14px;" target="_blank">
                                    <i class="fas fa-eye"></i> Preview
                                </a>
                                <a href="availability.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-secondary" style="padding: 8px 15px; font-size: 14px;">
                                    <i class="fas fa-calendar-alt"></i> Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
