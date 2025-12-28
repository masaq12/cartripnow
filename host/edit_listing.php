<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Edit Vehicle - Host Dashboard';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
        $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized');
        }
        
        // Update vehicle
        $stmt = $pdo->prepare("
            UPDATE vehicles SET
                make = ?,
                model = ?,
                year = ?,
                color = ?,
                vehicle_type = ?,
                transmission = ?,
                fuel_type = ?,
                seats = ?,
                doors = ?,
                description = ?,
                vehicle_features = ?,
                vehicle_rules = ?,
                daily_price = ?,
                weekly_discount_percent = ?,
                monthly_discount_percent = ?,
                mileage_limit_per_day = ?,
                extra_mileage_fee = ?,
                security_deposit = ?,
                min_driver_age = ?,
                pickup_location_address = ?,
                pickup_city = ?,
                pickup_state = ?,
                pickup_country = ?,
                pickup_zipcode = ?,
                status = ?,
                instant_book = ?
            WHERE vehicle_id = ?
        ");
        
        $stmt->execute([
            sanitizeInput($_POST['make']),
            sanitizeInput($_POST['model']),
            intval($_POST['year']),
            sanitizeInput($_POST['color']),
            sanitizeInput($_POST['vehicle_type']),
            sanitizeInput($_POST['transmission']),
            sanitizeInput($_POST['fuel_type']),
            intval($_POST['seats']),
            intval($_POST['doors']),
            sanitizeInput($_POST['description']),
            sanitizeInput($_POST['vehicle_features']),
            sanitizeInput($_POST['vehicle_rules']),
            floatval($_POST['daily_price']),
            floatval($_POST['weekly_discount_percent']),
            floatval($_POST['monthly_discount_percent']),
            intval($_POST['mileage_limit_per_day']),
            floatval($_POST['extra_mileage_fee']),
            floatval($_POST['security_deposit']),
            intval($_POST['min_driver_age']),
            sanitizeInput($_POST['pickup_location_address']),
            sanitizeInput($_POST['pickup_city']),
            sanitizeInput($_POST['pickup_state']),
            sanitizeInput($_POST['pickup_country']),
            sanitizeInput($_POST['pickup_zipcode']),
            sanitizeInput($_POST['status']),
            isset($_POST['instant_book']) ? 1 : 0,
            $vehicle_id
        ]);
        
        $_SESSION['success_message'] = 'Vehicle updated successfully!';
        redirect(SITE_URL . '/host/listings.php');
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating vehicle: ' . $e->getMessage();
    }
}

try {
    $pdo = getPDOConnection();
    
    // Get vehicle details
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error_message'] = 'Vehicle not found';
        redirect(SITE_URL . '/host/listings.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading vehicle';
    redirect(SITE_URL . '/host/listings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1><i class="fas fa-edit"></i> Edit Vehicle</h1>
        <div style="display: flex; gap: 10px;">
            <a href="listing_details.php?id=<?php echo $vehicle_id; ?>" class="btn btn-outline" target="_blank">
                <i class="fas fa-eye"></i> Preview
            </a>
            <a href="manage_photos.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary">
                <i class="fas fa-images"></i> Manage Photos
            </a>
            <a href="listings.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <form method="POST" class="card">
        <h2 style="margin-bottom: 20px;">Vehicle Information</h2>
        
        <!-- Basic Info -->
        <div class="grid grid-3">
            <div class="form-group">
                <label class="form-label">Make *</label>
                <input type="text" name="make" class="form-control" required value="<?php echo htmlspecialchars($vehicle['make']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Model *</label>
                <input type="text" name="model" class="form-control" required value="<?php echo htmlspecialchars($vehicle['model']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Year *</label>
                <input type="number" name="year" class="form-control" required min="1900" max="<?php echo date('Y') + 1; ?>" value="<?php echo $vehicle['year']; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Description *</label>
            <textarea name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($vehicle['description']); ?></textarea>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Vehicle Type *</label>
                <select name="vehicle_type" class="form-control" required>
                    <option value="">Select type</option>
                    <option value="sedan" <?php echo $vehicle['vehicle_type'] === 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                    <option value="suv" <?php echo $vehicle['vehicle_type'] === 'suv' ? 'selected' : ''; ?>>SUV</option>
                    <option value="truck" <?php echo $vehicle['vehicle_type'] === 'truck' ? 'selected' : ''; ?>>Truck</option>
                    <option value="van" <?php echo $vehicle['vehicle_type'] === 'van' ? 'selected' : ''; ?>>Van</option>
                    <option value="sports" <?php echo $vehicle['vehicle_type'] === 'sports' ? 'selected' : ''; ?>>Sports Car</option>
                    <option value="luxury" <?php echo $vehicle['vehicle_type'] === 'luxury' ? 'selected' : ''; ?>>Luxury</option>
                    <option value="electric" <?php echo $vehicle['vehicle_type'] === 'electric' ? 'selected' : ''; ?>>Electric</option>
                    <option value="hybrid" <?php echo $vehicle['vehicle_type'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                    <option value="other" <?php echo $vehicle['vehicle_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Color</label>
                <input type="text" name="color" class="form-control" value="<?php echo htmlspecialchars($vehicle['color']); ?>">
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Transmission *</label>
                <select name="transmission" class="form-control" required>
                    <option value="automatic" <?php echo $vehicle['transmission'] === 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                    <option value="manual" <?php echo $vehicle['transmission'] === 'manual' ? 'selected' : ''; ?>>Manual</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Fuel Type *</label>
                <select name="fuel_type" class="form-control" required>
                    <option value="gasoline" <?php echo $vehicle['fuel_type'] === 'gasoline' ? 'selected' : ''; ?>>Gasoline</option>
                    <option value="diesel" <?php echo $vehicle['fuel_type'] === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                    <option value="electric" <?php echo $vehicle['fuel_type'] === 'electric' ? 'selected' : ''; ?>>Electric</option>
                    <option value="hybrid" <?php echo $vehicle['fuel_type'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                </select>
            </div>
        </div>
        
        <!-- Location -->
        <h3 style="margin-top: 30px; margin-bottom: 20px;">Pickup Location</h3>
        
        <div class="form-group">
            <label class="form-label">Address *</label>
            <input type="text" name="pickup_location_address" class="form-control" required value="<?php echo htmlspecialchars($vehicle['pickup_location_address']); ?>">
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">City *</label>
                <input type="text" name="pickup_city" class="form-control" required value="<?php echo htmlspecialchars($vehicle['pickup_city']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">State/Province *</label>
                <input type="text" name="pickup_state" class="form-control" required value="<?php echo htmlspecialchars($vehicle['pickup_state']); ?>">
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Country *</label>
                <input type="text" name="pickup_country" class="form-control" required value="<?php echo htmlspecialchars($vehicle['pickup_country']); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Zipcode</label>
                <input type="text" name="pickup_zipcode" class="form-control" value="<?php echo htmlspecialchars($vehicle['pickup_zipcode'] ?? ''); ?>">
            </div>
        </div>
        
        <!-- Pricing -->
        <h3 style="margin-top: 30px; margin-bottom: 20px;">Pricing</h3>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Daily Price ($) *</label>
                <input type="number" name="daily_price" class="form-control" step="0.01" min="0" required value="<?php echo $vehicle['daily_price']; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Security Deposit ($)</label>
                <input type="number" name="security_deposit" class="form-control" step="0.01" min="0" value="<?php echo $vehicle['security_deposit']; ?>">
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Weekly Discount (%)</label>
                <input type="number" name="weekly_discount_percent" class="form-control" step="0.01" min="0" max="100" value="<?php echo $vehicle['weekly_discount_percent']; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Monthly Discount (%)</label>
                <input type="number" name="monthly_discount_percent" class="form-control" step="0.01" min="0" max="100" value="<?php echo $vehicle['monthly_discount_percent']; ?>">
            </div>
        </div>
        
        <!-- Vehicle Details -->
        <h3 style="margin-top: 30px; margin-bottom: 20px;">Vehicle Details</h3>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Seats *</label>
                <input type="number" name="seats" class="form-control" min="1" max="20" required value="<?php echo $vehicle['seats']; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Doors *</label>
                <input type="number" name="doors" class="form-control" min="2" max="6" required value="<?php echo $vehicle['doors']; ?>">
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Mileage Limit (per day) *</label>
                <input type="number" name="mileage_limit_per_day" class="form-control" min="0" required value="<?php echo $vehicle['mileage_limit_per_day']; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Extra Mileage Fee ($ per mile)</label>
                <input type="number" name="extra_mileage_fee" class="form-control" step="0.01" min="0" value="<?php echo $vehicle['extra_mileage_fee']; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Minimum Driver Age *</label>
            <input type="number" name="min_driver_age" class="form-control" min="18" max="100" required value="<?php echo $vehicle['min_driver_age']; ?>">
        </div>
        
        <!-- Features -->
        <div class="form-group">
            <label class="form-label">Vehicle Features</label>
            <textarea name="vehicle_features" class="form-control" rows="4" placeholder="Air Conditioning, Bluetooth, GPS, Backup Camera, Heated Seats, etc."><?php echo htmlspecialchars($vehicle['vehicle_features'] ?? ''); ?></textarea>
            <small class="form-text">List the features of your vehicle</small>
        </div>
        
        <!-- Vehicle Rules -->
        <div class="form-group">
            <label class="form-label">Vehicle Rules</label>
            <textarea name="vehicle_rules" class="form-control" rows="4" placeholder="No smoking, No pets, Return with full tank, etc."><?php echo htmlspecialchars($vehicle['vehicle_rules'] ?? ''); ?></textarea>
        </div>
        
        <!-- Status & Settings -->
        <h3 style="margin-top: 30px; margin-bottom: 20px;">Status & Settings</h3>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label class="form-label">Status *</label>
                <select name="status" class="form-control" required>
                    <option value="active" <?php echo $vehicle['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $vehicle['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="maintenance" <?php echo $vehicle['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="instant_book" value="1" <?php echo $vehicle['instant_book'] ? 'checked' : ''; ?>>
                    Enable Instant Book
                </label>
                <small class="form-text">Allow renters to book instantly without approval</small>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="listings.php" class="btn btn-outline">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
