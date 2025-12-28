<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Browse Vehicles - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get filter parameters
    $filter_type = $_GET['filter'] ?? 'all';
    $location = $_GET['location'] ?? '';
    $pickup_date = $_GET['pickup_date'] ?? '';
    $return_date = $_GET['return_date'] ?? '';
    $vehicle_type = $_GET['vehicle_type'] ?? '';
    $make = $_GET['make'] ?? '';
    $min_price = $_GET['min_price'] ?? '';
    $max_price = $_GET['max_price'] ?? '';
    $state = $_GET['state'] ?? '';
    $city = $_GET['city'] ?? '';
    $delivery = $_GET['delivery'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';
    
    // Build base query
    $query = "SELECT v.*, u.full_name as owner_name,
              (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo,
              (SELECT COUNT(*) FROM vehicle_photos WHERE vehicle_id = v.vehicle_id) as photo_count,
              (SELECT AVG(rating) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as avg_rating,
              (SELECT COUNT(*) FROM reviews r JOIN trips t ON r.trip_id = t.trip_id WHERE t.vehicle_id = v.vehicle_id AND r.review_type = 'renter_to_owner') as review_count
              FROM vehicles v
              JOIN users u ON v.owner_id = u.user_id
              WHERE v.status = 'active'";
    
    $params = [];
    
    // Apply filters
    if ($filter_type === 'monthly') {
        $query .= " AND v.monthly_discount > 0";
    } elseif ($filter_type === 'airport') {
        $query .= " AND v.airport_delivery = 1";
    } elseif ($filter_type === 'nearby') {
        $query .= " AND v.pickup_city IS NOT NULL";
    } elseif ($filter_type === 'delivered') {
        $query .= " AND v.delivery_available = 1";
    }
    
    if (!empty($location)) {
        $query .= " AND (v.pickup_city LIKE ? OR v.pickup_state LIKE ? OR v.pickup_zip LIKE ?)";
        $params[] = "%$location%";
        $params[] = "%$location%";
        $params[] = "%$location%";
    }
    
    if (!empty($state)) {
        $query .= " AND v.pickup_state = ?";
        $params[] = $state;
    }
    
    if (!empty($city)) {
        $query .= " AND v.pickup_city = ?";
        $params[] = $city;
    }
    
    if (!empty($vehicle_type)) {
        $query .= " AND v.vehicle_type = ?";
        $params[] = $vehicle_type;
    }
    
    if (!empty($make)) {
        $query .= " AND v.make = ?";
        $params[] = $make;
    }
    
    if (!empty($min_price)) {
        $query .= " AND v.daily_price >= ?";
        $params[] = $min_price;
    }
    
    if (!empty($max_price)) {
        $query .= " AND v.daily_price <= ?";
        $params[] = $max_price;
    }
    
    if (!empty($delivery)) {
        $query .= " AND v.delivery_available = 1";
    }
    
    // Check availability if dates provided
    if (!empty($pickup_date) && !empty($return_date)) {
        $query .= " AND v.vehicle_id NOT IN (
            SELECT DISTINCT vehicle_id FROM trips 
            WHERE trip_status IN ('confirmed', 'active')
            AND (
                (pickup_date <= ? AND return_date >= ?)
                OR (pickup_date <= ? AND return_date >= ?)
                OR (pickup_date >= ? AND return_date <= ?)
            )
        )";
        $params[] = $pickup_date;
        $params[] = $pickup_date;
        $params[] = $return_date;
        $params[] = $return_date;
        $params[] = $pickup_date;
        $params[] = $return_date;
    }
    
    // Sorting
    switch ($sort) {
        case 'price_low':
            $query .= " ORDER BY v.daily_price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY v.daily_price DESC";
            break;
        case 'rating':
            $query .= " ORDER BY avg_rating DESC NULLS LAST";
            break;
        default:
            $query .= " ORDER BY v.created_at DESC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
    
    // Get filter options
    $states_stmt = $pdo->query("SELECT DISTINCT pickup_state FROM vehicles WHERE status = 'active' AND pickup_state IS NOT NULL ORDER BY pickup_state");
    $states = $states_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $cities_stmt = $pdo->query("SELECT DISTINCT pickup_city FROM vehicles WHERE status = 'active' AND pickup_city IS NOT NULL ORDER BY pickup_city");
    $cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $makes_stmt = $pdo->query("SELECT DISTINCT make FROM vehicles WHERE status = 'active' ORDER BY make");
    $makes = $makes_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get renter balance
    $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetchColumn() ?? 0;
    
    // Get wishlist
    $stmt = $pdo->prepare("SELECT vehicle_id FROM wishlists WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading vehicles';
    $vehicles = [];
    $states = [];
    $cities = [];
    $makes = [];
}

include '../includes/header.php';
?>

<style>
.turo-hero {
    background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=1600');
    background-size: cover;
    background-position: center;
    color: white;
    padding: 60px 0 40px;
    text-align: center;
    margin-bottom: 30px;
}

.search-hero {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding-bottom: 5px;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 24px;
    background: white;
    border: 1px solid #ddd;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.3s;
    text-decoration: none;
    color: #222;
}

.filter-tab:hover {
    border-color: #222;
}

.filter-tab.active {
    background: #222;
    color: white;
    border-color: #222;
}

.advanced-filters {
    background: #f7f7f7;
    padding: 20px;
    border-radius: 12px;
    margin-top: 20px;
}

.filter-section {
    margin-bottom: 15px;
}

.filter-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    text-transform: uppercase;
    color: #666;
}

.vehicle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
    margin-top: 30px;
}

.vehicle-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}

.vehicle-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.vehicle-image {
    width: 100%;
    height: 240px;
    object-fit: cover;
}

.vehicle-content {
    padding: 16px;
}

.vehicle-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #222;
}

.vehicle-location {
    color: #717171;
    font-size: 14px;
    margin-bottom: 12px;
}

.vehicle-features {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 13px;
    color: #717171;
}

.vehicle-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 12px;
}

.vehicle-price {
    font-size: 22px;
    font-weight: 600;
    color: #222;
}

.vehicle-price span {
    font-size: 14px;
    font-weight: normal;
    color: #717171;
}

.wishlist-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    background: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.3s;
    z-index: 10;
}

.wishlist-btn:hover {
    transform: scale(1.1);
}

.badge-tag {
    position: absolute;
    top: 12px;
    left: 12px;
    background: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.balance-bar {
    background: #E8F5E9;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

@media (max-width: 768px) {
    .vehicle-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="turo-hero">
    <div class="container">
        <h1 style="font-size: 48px; font-weight: 700; margin-bottom: 12px;">
            Browse available cars
        </h1>
        <p style="font-size: 18px;">
            Find your perfect ride for any occasion
        </p>
    </div>
</div>

<div class="container">
    <!-- Balance Display -->
    <div class="balance-bar">
        <div>
            <span style="color: #666; font-size: 14px;">Your Balance</span>
            <h3 style="margin: 4px 0 0 0; color: #222; font-size: 24px;">
                <?php
                $stmt = $pdo->prepare("SELECT current_balance FROM renter_balances WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $balance = $stmt->fetchColumn() ?? 0;
                 echo formatCurrency($balance); ?>
            </h3>
        </div>
        <a href="balance.php" class="btn btn-outline" style="padding: 8px 16px;">
            <i class="fas fa-wallet"></i> Manage
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-th"></i> All
        </a>
       <!--  <a href="?filter=monthly" class="filter-tab <?php echo $filter_type === 'monthly' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Monthly
        </a>
        <a href="?filter=airport" class="filter-tab <?php echo $filter_type === 'airport' ? 'active' : ''; ?>">
            <i class="fas fa-plane"></i> Airport
        </a> -->
        <a href="?filter=nearby" class="filter-tab <?php echo $filter_type === 'nearby' ? 'active' : ''; ?>">
            <i class="fas fa-map-marker-alt"></i> Nearby
        </a>
        <a href="?filter=delivered" class="filter-tab <?php echo $filter_type === 'delivered' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i> Delivered
        </a>
    </div>

    <!-- Search & Filters -->
    <div class="search-hero">
        <form method="GET" action="">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter_type); ?>">
            
            <div class="grid grid-3" style="margin-bottom: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="location" class="form-control" 
                           placeholder="Where?" 
                           value="<?php echo htmlspecialchars($location); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="date" name="pickup_date" class="form-control" 
                           placeholder="Pickup" 
                           value="<?php echo htmlspecialchars($pickup_date); ?>" 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="date" name="return_date" class="form-control" 
                           placeholder="Return" 
                           value="<?php echo htmlspecialchars($return_date); ?>" 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <button type="button" onclick="toggleFilters()" style="background: none; border: none; color: #222; cursor: pointer; font-weight: 600; margin-bottom: 15px;">
                <i class="fas fa-sliders-h"></i> More filters
            </button>
            
            <div id="advancedFilters" class="advanced-filters" style="display: none;">
                <div class="grid grid-3" style="margin-bottom: 15px;">
                    <div class="filter-section">
                        <h4>Vehicle Type</h4>
                        <select name="vehicle_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="sedan" <?php echo $vehicle_type === 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                            <option value="suv" <?php echo $vehicle_type === 'suv' ? 'selected' : ''; ?>>SUV</option>
                            <option value="truck" <?php echo $vehicle_type === 'truck' ? 'selected' : ''; ?>>Truck</option>
                            <option value="van" <?php echo $vehicle_type === 'van' ? 'selected' : ''; ?>>Van</option>
                            <option value="sports" <?php echo $vehicle_type === 'sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="luxury" <?php echo $vehicle_type === 'luxury' ? 'selected' : ''; ?>>Luxury</option>
                            <option value="electric" <?php echo $vehicle_type === 'electric' ? 'selected' : ''; ?>>Electric</option>
                            <option value="hybrid" <?php echo $vehicle_type === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <h4>Make</h4>
                        <select name="make" class="form-control">
                            <option value="">All Makes</option>
                            <?php foreach ($makes as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $make === $m ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <h4>State</h4>
                        <select name="state" class="form-control">
                            <option value="">All States</option>
                            <?php foreach ($states as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $state === $s ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-3" style="margin-bottom: 15px;">
                    <div class="filter-section">
                        <h4>City</h4>
                        <select name="city" class="form-control">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $city === $c ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-section">
                        <h4>Min Price/Day</h4>
                        <input type="number" name="min_price" class="form-control" 
                               placeholder="$0" 
                               value="<?php echo htmlspecialchars($min_price); ?>">
                    </div>
                    
                    <div class="filter-section">
                        <h4>Max Price/Day</h4>
                        <input type="number" name="max_price" class="form-control" 
                               placeholder="Any" 
                               value="<?php echo htmlspecialchars($max_price); ?>">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="delivery" value="1" <?php echo $delivery ? 'checked' : ''; ?> style="margin-right: 8px;">
                        Delivery available
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>

    <!-- Results Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; margin-bottom: 20px;">
        <h2 style="font-size: 24px; font-weight: 600;">
            <?php echo count($vehicles); ?> cars available
        </h2>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <select name="sort" onchange="window.location.href='?filter=<?php echo htmlspecialchars($filter_type); ?>&sort=' + this.value" class="form-control" style="width: auto;">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
            </select>
            
            <a href="wishlist.php" class="btn btn-outline" style="padding: 8px 16px;">
                <i class="fas fa-heart"></i> Wishlist
            </a>
        </div>
    </div>

    <!-- Vehicles Grid -->
    <?php if (empty($vehicles)): ?>
        <div class="card" style="text-align: center; padding: 80px 20px;">
            <i class="fas fa-car" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">No cars found</h3>
            <p style="color: #717171;">Try adjusting your search or filters</p>
        </div>
    <?php else: ?>
        <div class="vehicle-grid">
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="vehicle-card" onclick="location.href='listing_details.php?id=<?php echo $vehicle['vehicle_id']; ?>'">
                    <?php $in_wishlist = in_array($vehicle['vehicle_id'], $wishlist_items); ?>
                    <button class="wishlist-btn" onclick="toggleWishlist(event, <?php echo $vehicle['vehicle_id']; ?>, this)">
                        <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart" style="color: #FF385C; font-size: 16px;"></i>
                    </button>
                    
                 
                    
                    <?php if ($vehicle['primary_photo']): ?>
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($vehicle['primary_photo']); ?>" 
                             alt="<?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>" 
                             class="vehicle-image"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                    <?php else: ?>
                        <div class="vehicle-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-car" style="font-size: 48px; color: #ddd;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="vehicle-content">
                        <h3 class="vehicle-title">
                            <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                        </h3>
                        
                        <p class="vehicle-location">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state']); ?>
                        </p>
                        
                        <div class="vehicle-features">
                            <span><i class="fas fa-users"></i> <?php echo $vehicle['seats']; ?></span>
                            <span><i class="fas fa-cog"></i> <?php echo ucfirst($vehicle['transmission']); ?></span>
                            <span><i class="fas fa-gas-pump"></i> <?php echo ucfirst($vehicle['fuel_type']); ?></span>
                        </div>
                        
                        <?php if ($vehicle['avg_rating']): ?>
                            <div class="vehicle-rating">
                                <i class="fas fa-star" style="color: #FFB400;"></i>
                                <span style="font-weight: 600;"><?php echo number_format($vehicle['avg_rating'], 1); ?></span>
                                <span style="color: #717171;">(<?php echo $vehicle['review_count']; ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="vehicle-price">
                            $<?php echo number_format($vehicle['daily_price'], 0); ?> <span>/ day</span>
                        </div>
                        
                        <?php if ($vehicle['security_deposit'] > 0): ?>
                            <p style="color: #717171; font-size: 12px; margin-top: 4px;">
                                + <?php echo formatCurrency($vehicle['security_deposit']); ?> deposit
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFilters() {
    const filters = document.getElementById('advancedFilters');
    filters.style.display = filters.style.display === 'none' ? 'block' : 'none';
}

function toggleWishlist(event, vehicleId, btn) {
    event.preventDefault();
    event.stopPropagation();
    
    fetch('wishlist_toggle.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'vehicle_id=' + vehicleId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<?php include '../includes/footer.php'; ?>
