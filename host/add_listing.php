<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Add New Vehicle - Car Trip Now';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPDOConnection();
        
        // Vehicle details
        $make = sanitizeInput($_POST['make']);
        $model = sanitizeInput($_POST['model']);
        $year = (int)$_POST['year'];
        $vehicle_type = sanitizeInput($_POST['vehicle_type']);
        $transmission = sanitizeInput($_POST['transmission']);
        $fuel_type = sanitizeInput($_POST['fuel_type']);
        $seats = (int)$_POST['seats'];
        $doors = (int)$_POST['doors'];
        $description = sanitizeInput($_POST['description']);
        $vehicle_features = sanitizeInput($_POST['vehicle_features']);
        $vehicle_rules = sanitizeInput($_POST['vehicle_rules']);
        
        // Location
        $pickup_city = sanitizeInput($_POST['pickup_city']);
        $pickup_state = sanitizeInput($_POST['pickup_state']);
        $pickup_country = sanitizeInput($_POST['pickup_country']);
        $pickup_zipcode = sanitizeInput($_POST['pickup_zipcode']);
        $pickup_location_address = sanitizeInput($_POST['pickup_location_address']);
        
        // Pricing
        $daily_price = (float)$_POST['daily_price'];
        $weekly_discount = (float)($_POST['weekly_discount'] ?? 0);
        $monthly_discount = (float)($_POST['monthly_discount'] ?? 0);
        $security_deposit = (float)$_POST['security_deposit'];
        $mileage_limit_per_day = (int)$_POST['mileage_limit_per_day'];
        $extra_mileage_fee = (float)$_POST['extra_mileage_fee'];
        
        // Validate
        if (empty($make) || empty($model) || empty($year) || empty($pickup_city) || empty($pickup_country) || $daily_price <= 0) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Insert vehicle listing
        $stmt = $pdo->prepare("
            INSERT INTO vehicles (
                owner_id, make, model, year, vehicle_type, transmission, fuel_type,
                seats, doors, description, vehicle_features, vehicle_rules,
                pickup_city, pickup_state, pickup_country, pickup_zipcode, pickup_location_address,
                daily_price, weekly_discount_percent, monthly_discount_percent,
                security_deposit, mileage_limit_per_day, extra_mileage_fee,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->execute([
            $_SESSION['user_id'], $make, $model, $year, $vehicle_type, $transmission, $fuel_type,
            $seats, $doors, $description, $vehicle_features, $vehicle_rules,
            $pickup_city, $pickup_state, $pickup_country, $pickup_zipcode, $pickup_location_address,
            $daily_price, $weekly_discount, $monthly_discount,
            $security_deposit, $mileage_limit_per_day, $extra_mileage_fee
        ]);
        
        $vehicle_id = $pdo->lastInsertId();
        
        $_SESSION['success_message'] = 'Vehicle listing created successfully! Now add some photos.';
        redirect(SITE_URL . '/host/manage_photos.php?id=' . $vehicle_id);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-plus"></i> Add New Vehicle
    </h1>
    
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <form method="POST" action="">
            
            <!-- Basic Vehicle Information -->
            <h2 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                <i class="fas fa-car"></i> Vehicle Information
            </h2>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="make">Make *</label>
                    <input type="text" id="make" name="make" class="form-control" required 
                           placeholder="Toyota, Honda, BMW...">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="model">Model *</label>
                    <input type="text" id="model" name="model" class="form-control" required 
                           placeholder="Camry, Accord, X5...">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="year">Year *</label>
                    <input type="number" id="year" name="year" class="form-control" required 
                           min="1990" max="2025" placeholder="2020">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="vehicle_type">Vehicle Type *</label>
                    <select id="vehicle_type" name="vehicle_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="sedan">Sedan</option>
                        <option value="suv">SUV</option>
                        <option value="truck">Truck</option>
                        <option value="van">Van</option>
                        <option value="sports">Sports Car</option>
                        <option value="luxury">Luxury</option>
                        <option value="electric">Electric</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="transmission">Transmission *</label>
                    <select id="transmission" name="transmission" class="form-control" required>
                        <option value="">Select Transmission</option>
                        <option value="automatic" selected>Automatic</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="fuel_type">Fuel Type *</label>
                    <select id="fuel_type" name="fuel_type" class="form-control" required>
                        <option value="">Select Fuel Type</option>
                        <option value="gasoline" selected>Gasoline</option>
                        <option value="diesel">Diesel</option>
                        <option value="electric">Electric</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="seats">Seats *</label>
                    <input type="number" id="seats" name="seats" class="form-control" 
                           min="2" max="15" required value="5">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="doors">Doors *</label>
                    <input type="number" id="doors" name="doors" class="form-control" 
                           min="2" max="6" required value="4">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="5" 
                          placeholder="Describe your vehicle, its condition, special features, etc."></textarea>
            </div>
            
            <!-- Pickup Location -->
            <h2 style="margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                <i class="fas fa-map-marker-alt"></i> Pickup Location
            </h2>
            
            <div class="form-group">
                <label class="form-label" for="pickup_location_address">Street Address</label>
                <input type="text" id="pickup_location_address" name="pickup_location_address" class="form-control" 
                       placeholder="123 Main Street">
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="pickup_city">City *</label>
                    <input type="text" id="pickup_city" name="pickup_city" class="form-control" required 
                           placeholder="New York">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pickup_state">State / Province</label>
                    <input type="text" id="pickup_state" name="pickup_state" class="form-control" 
                           placeholder="NY">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="pickup_country">Country *</label>
                    <input type="text" id="pickup_country" name="pickup_country" class="form-control" required 
                           placeholder="USA">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pickup_zipcode">Zip / Postal Code</label>
                    <input type="text" id="pickup_zipcode" name="pickup_zipcode" class="form-control" 
                           placeholder="10001">
                </div>
            </div>
            
            <!-- Pricing -->
            <h2 style="margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                <i class="fas fa-dollar-sign"></i> Pricing & Fees
            </h2>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="daily_price">Daily Price * ($)</label>
                    <input type="number" id="daily_price" name="daily_price" class="form-control" 
                           min="10" step="0.01" required data-currency placeholder="50.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="security_deposit">Security Deposit ($)</label>
                    <input type="number" id="security_deposit" name="security_deposit" class="form-control" 
                           min="0" step="0.01" value="200" data-currency>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="weekly_discount">Weekly Discount (%)</label>
                    <input type="number" id="weekly_discount" name="weekly_discount" class="form-control" 
                           min="0" max="50" step="1" value="0" placeholder="10">
                    <small style="color: #666;">For 7+ day rentals</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="monthly_discount">Monthly Discount (%)</label>
                    <input type="number" id="monthly_discount" name="monthly_discount" class="form-control" 
                           min="0" max="50" step="1" value="0" placeholder="20">
                    <small style="color: #666;">For 28+ day rentals</small>
                </div>
            </div>
            
            <!-- Mileage -->
            <h2 style="margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                <i class="fas fa-tachometer-alt"></i> Mileage Limits
            </h2>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="mileage_limit_per_day">Daily Mileage Limit (miles) *</label>
                    <input type="number" id="mileage_limit_per_day" name="mileage_limit_per_day" class="form-control" 
                           min="50" max="1000" required value="200">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="extra_mileage_fee">Extra Mileage Fee ($/mile) *</label>
                    <input type="number" id="extra_mileage_fee" name="extra_mileage_fee" class="form-control" 
                           min="0.10" max="5.00" step="0.01" required value="0.50" data-currency>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Platform service fee (15%) and insurance fees will be calculated automatically at checkout.
            </div>
            
            <!-- Additional Information -->
            <h2 style="margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color);">
                <i class="fas fa-list"></i> Additional Information
            </h2>
            
            <div class="form-group">
                <label class="form-label" for="vehicle_features">Vehicle Features</label>
                <textarea id="vehicle_features" name="vehicle_features" class="form-control" rows="3" 
                          placeholder="GPS Navigation, Bluetooth, Backup Camera, Sunroof, etc."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="vehicle_rules">Vehicle Rules</label>
                <textarea id="vehicle_rules" name="vehicle_rules" class="form-control" rows="4" 
                          placeholder="No smoking, No pets, Clean return required, etc."></textarea>
            </div>
            
            <!-- Submit -->
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                <a href="listings.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Vehicle Listing
                </button>
            </div>
            
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
