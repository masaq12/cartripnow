<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isRenter() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/guest/browse.php');
}

$vehicle_id = $_POST['vehicle_id'] ?? 0;
$pickup_date = $_POST['pickup_date'] ?? '';
$return_date = $_POST['return_date'] ?? '';
$payment_credential_id = $_POST['payment_credential_id'] ?? 0;

// Validate required fields
if (empty($pickup_date) || empty($return_date)) {
    $_SESSION['error_message'] = 'Please select pickup and return dates';
    redirect(SITE_URL . '/guest/listing_details.php?id=' . $vehicle_id);
}

$pageTitle = 'Checkout - Car Trip Now';

try {
    $pdo = getPDOConnection();
    
    // Get vehicle details
    $stmt = $pdo->prepare("
        SELECT v.*, u.full_name as owner_name,
        (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.vehicle_id AND is_primary = 1 LIMIT 1) as primary_photo
        FROM vehicles v
        JOIN users u ON v.owner_id = u.user_id
        WHERE v.vehicle_id = ? AND v.status = 'active'
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error_message'] = 'Vehicle not found or not available';
        redirect(SITE_URL . '/guest/browse.php');
    }
    
    // Calculate trip duration
    $pickup_datetime = new DateTime($pickup_date);
    $return_datetime = new DateTime($return_date);
    
    // Check if same-day booking
    $is_same_day = $pickup_date === $return_date;
    
    if ($is_same_day) {
        // For same-day, calculate hours instead of days
        $trip_days = 1;
    } else {
        $duration = $pickup_datetime->diff($return_datetime);
        $trip_days = max(1, $duration->days);
    }
    
    // Calculate pricing
    $daily_rate = $vehicle['daily_price'];
    $total_days_cost = $daily_rate * $trip_days;
    
    // Apply discounts
    $discount = 0;
    if ($trip_days >= 28 && $vehicle['monthly_discount_percent'] > 0) {
        $discount = $total_days_cost * ($vehicle['monthly_discount_percent'] / 100);
    } elseif ($trip_days >= 7 && $vehicle['weekly_discount_percent'] > 0) {
        $discount = $total_days_cost * ($vehicle['weekly_discount_percent'] / 100);
    }
    $total_days_cost_after_discount = $total_days_cost - $discount;
    
    // Insurance
    $stmt = $pdo->prepare("SELECT * FROM insurance_plans WHERE status = 'active' ORDER BY daily_fee ASC");
    $stmt->execute();
    $insurance_plans = $stmt->fetchAll();
    
    // Mileage
    $mileage_limit = $vehicle['mileage_limit_per_day'] * $trip_days;
    
    // Platform fee (15% default)
    $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_percent'");
    $stmt->execute();
    $platform_fee_percent = $stmt->fetchColumn() ?: 15.00;
    $platform_fee = $total_days_cost_after_discount * ($platform_fee_percent / 100);
    
    // Security deposit
    $security_deposit = $vehicle['security_deposit'];
    
    // Get renter balance
    $stmt = $pdo->prepare("SELECT current_balance, pending_holds FROM renter_balances WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance_info = $stmt->fetch();
    
    if (!$balance_info) {
        // Create balance if doesn't exist
        $stmt = $pdo->prepare("INSERT INTO renter_balances (user_id, current_balance) VALUES (?, 0.00)");
        $stmt->execute([$_SESSION['user_id']]);
        $balance_info = ['current_balance' => 0, 'pending_holds' => 0];
    }
    
    // Get payment credentials
    $stmt = $pdo->prepare("SELECT * FROM payment_credentials WHERE user_id = ? AND status = 'active' ORDER BY credential_id DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $payment_credentials = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading checkout: ' . $e->getMessage();
    redirect(SITE_URL . '/guest/listing_details.php?id=' . $vehicle_id);
}

include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-shopping-cart"></i> Confirm & Pay
    </h1>
    
    <form method="POST" action="process_trip_booking.php" id="checkoutForm">
        <input type="hidden" name="vehicle_id" value="<?php echo $vehicle_id; ?>">
        <input type="hidden" name="pickup_date" value="<?php echo htmlspecialchars($pickup_date); ?>">
        <input type="hidden" name="return_date" value="<?php echo htmlspecialchars($return_date); ?>">
        
        <div class="grid grid-2" style="gap: 30px;">
            <!-- Left Column - Trip Details -->
            <div>
                <!-- Vehicle Info -->
                <div class="card">
                    <h2><i class="fas fa-car"></i> Your Trip</h2>
                    <div class="grid grid-2" style="gap: 20px; margin-top: 20px;">
                        <div>
                            <?php if ($vehicle['primary_photo']): ?>
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($vehicle['primary_photo']); ?>" 
                                     alt="Vehicle" 
                                     style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px;"
                                     onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.svg'">
                            <?php else: ?>
                                <div style="width: 100%; height: 150px; background-color: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-car" style="font-size: 48px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 style="margin: 0 0 10px 0;">
                                <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                            </h3>
                            <p style="color: #666; margin: 5px 0;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($vehicle['pickup_city'] . ', ' . $vehicle['pickup_state']); ?>
                            </p>
                            <p style="color: #666; margin: 5px 0;">
                                <i class="fas fa-user"></i> 
                                Hosted by <?php echo htmlspecialchars($vehicle['owner_name']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Trip Dates & Times -->
                <div class="card">
                    <h2><i class="fas fa-calendar-alt"></i> Trip Details</h2>
                    
                    <?php if ($is_same_day): ?>
                        <div class="alert alert-info" style="margin-bottom: 15px;">
                            <i class="fas fa-info-circle"></i> <strong>Same-day trip:</strong> Return time must be at least 2 hours after pickup time.
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-2" style="gap: 20px; margin-top: 20px;">
                        <div>
                            <label class="form-label">Pickup Date</label>
                            <input type="date" value="<?php echo htmlspecialchars($pickup_date); ?>" class="form-control" disabled>
                        </div>
                        <div>
                            <label class="form-label">Pickup Time *</label>
                            <select name="pickup_time" id="pickup_time" class="form-control" required>
                                <option value="">Select time</option>
                                <?php for ($h = 6; $h <= 22; $h++): ?>
                                    <?php for ($m = 0; $m < 60; $m += 30): ?>
                                        <option value="<?php echo sprintf('%02d:%02d:00', $h, $m); ?>">
                                            <?php echo date('g:i A', strtotime(sprintf('%02d:%02d', $h, $m))); ?>
                                        </option>
                                    <?php endfor; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-2" style="gap: 20px; margin-top: 15px;">
                        <div>
                            <label class="form-label">Return Date</label>
                            <input type="date" value="<?php echo htmlspecialchars($return_date); ?>" class="form-control" disabled>
                        </div>
                        <div>
                            <label class="form-label">Return Time *</label>
                            <select name="return_time" id="return_time" class="form-control" required>
                                <option value="">Select time</option>
                                <?php for ($h = 6; $h <= 22; $h++): ?>
                                    <?php for ($m = 0; $m < 60; $m += 30): ?>
                                        <option value="<?php echo sprintf('%02d:%02d:00', $h, $m); ?>">
                                            <?php echo date('g:i A', strtotime(sprintf('%02d:%02d', $h, $m))); ?>
                                        </option>
                                    <?php endfor; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="time-error" class="alert alert-danger" style="margin-top: 15px; display: none;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="time-error-message"></span>
                    </div>
                    
                    <div style="background-color: var(--light-color); padding: 15px; border-radius: 8px; margin-top: 20px;">
                        <p style="margin: 0; font-weight: bold;">
                            <i class="fas fa-calendar-day"></i> Trip Duration: <?php echo $trip_days; ?> day<?php echo $trip_days > 1 ? 's' : ''; ?>
                        </p>
                        <p style="margin: 10px 0 0 0; color: #666;">
                            <i class="fas fa-tachometer-alt"></i> Mileage Limit: <?php echo number_format($mileage_limit); ?> miles
                        </p>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                            Extra mileage: <?php echo formatCurrency($vehicle['extra_mileage_fee']); ?> per mile
                        </p>
                    </div>
                </div>
                
                <!-- Insurance Selection -->
                <div class="card">
                    <h2><i class="fas fa-shield-alt"></i> Insurance Protection</h2>
                    <?php foreach ($insurance_plans as $plan): ?>
                        <div style="border: 2px solid #ddd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <label style="display: flex; align-items: start; cursor: pointer;">
                                <input type="radio" name="insurance_plan_id" value="<?php echo $plan['plan_id']; ?>" 
                                       data-daily-fee="<?php echo $plan['daily_fee']; ?>" 
                                       style="margin-top: 5px; margin-right: 15px;" required>
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong>
                                        <strong><?php echo formatCurrency($plan['daily_fee'] * $trip_days); ?></strong>
                                    </div>
                                    <p style="margin: 5px 0; color: #666; font-size: 14px;">
                                        <?php echo htmlspecialchars($plan['plan_description']); ?>
                                    </p>
                                    <p style="margin: 5px 0; color: #666; font-size: 13px;">
                                        Coverage: <?php echo formatCurrency($plan['coverage_amount']); ?> • 
                                        Deductible: <?php echo formatCurrency($plan['deductible']); ?>
                                    </p>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Right Column - Payment -->
            <div>
                <!-- Balance Display -->
                <div class="card">
                    <h2><i class="fas fa-wallet"></i> Your Balance</h2>
                    <div style="background-color: #e8f5e9; padding: 20px; border-radius: 8px; margin-top: 15px;">
                        <p style="margin: 0; font-size: 14px;">Available Balance</p>
                        <p id="availableBalance" style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold; color: var(--success-color);">
                            <?php echo formatCurrency($balance_info['current_balance']); ?>
                        </p>
                        <?php if ($balance_info['pending_holds'] > 0): ?>
                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                                Pending Holds: <?php echo formatCurrency($balance_info['pending_holds']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card">
                    <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                    <div class="form-group" style="margin-top: 15px;">
                        <label class="form-label">Select Payment Credential</label>
                        <select name="payment_credential_id" class="form-control" required>
                            <option value="">Choose payment method</option>
                            <?php foreach ($payment_credentials as $cred): ?>
                                <option value="<?php echo $cred['credential_id']; ?>" <?php echo $cred['credential_id'] == $payment_credential_id ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $cred['credential_type'])); ?> - 
                                    <?php echo htmlspecialchars($cred['credential_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Price Breakdown -->
                <div class="card">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Price Details</h2>
                    <div style="margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span><?php echo $trip_days; ?> day<?php echo $trip_days > 1 ? 's' : ''; ?> × <?php echo formatCurrency($daily_rate); ?></span>
                            <span id="daysTotal"><?php echo formatCurrency($total_days_cost); ?></span>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--success-color);">
                                <span>
                                    <?php 
                                    if ($trip_days >= 28) echo 'Monthly discount';
                                    elseif ($trip_days >= 7) echo 'Weekly discount';
                                    ?>
                                </span>
                                <span>-<?php echo formatCurrency($discount); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Insurance</span>
                            <span id="insuranceFee">Select plan</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Platform Fee (<?php echo number_format($platform_fee_percent, 1); ?>%)</span>
                            <span><?php echo formatCurrency($platform_fee); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                            <span>Security Deposit (refundable)</span>
                            <span><?php echo formatCurrency($security_deposit); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; font-size: 20px; font-weight: bold; margin-top: 15px;">
                            <span>Total Amount</span>
                            <span id="totalAmount"><?php echo formatCurrency($total_days_cost_after_discount + $platform_fee + $security_deposit); ?></span>
                        </div>
                        
                        <div id="insufficientFunds" style="display: none; background-color: #ffebee; padding: 15px; border-radius: 8px; margin-top: 15px; color: #c62828;">
                            <i class="fas fa-exclamation-triangle"></i> Insufficient balance. Please add funds to your account.
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" id="confirmButton" class="btn btn-primary" style="width: 100%; padding: 18px; font-size: 18px;">
                    <i class="fas fa-check-circle"></i> Confirm & Book Trip
                </button>
                
                <p style="text-align: center; color: #666; font-size: 13px; margin-top: 15px;">
                    <i class="fas fa-shield-alt"></i> Your payment will be held in escrow until trip completion
                </p>
            </div>
        </div>
    </form>
</div>

<script>
const tripDays = <?php echo $trip_days; ?>;
const totalDaysCost = <?php echo $total_days_cost_after_discount; ?>;
const platformFee = <?php echo $platform_fee; ?>;
const securityDeposit = <?php echo $security_deposit; ?>;
const availableBalance = <?php echo $balance_info['current_balance']; ?>;
const isSameDay = <?php echo $is_same_day ? 'true' : 'false'; ?>;

// Time validation
document.getElementById('pickup_time').addEventListener('change', validateTimes);
document.getElementById('return_time').addEventListener('change', validateTimes);

function validateTimes() {
    const pickupTime = document.getElementById('pickup_time').value;
    const returnTime = document.getElementById('return_time').value;
    const errorDiv = document.getElementById('time-error');
    const errorMessage = document.getElementById('time-error-message');
    const confirmButton = document.getElementById('confirmButton');
    
    if (!pickupTime || !returnTime) {
        errorDiv.style.display = 'none';
        return;
    }
    
    if (isSameDay) {
        // Parse times
        const [pickupHour, pickupMinute] = pickupTime.split(':').map(Number);
        const [returnHour, returnMinute] = returnTime.split(':').map(Number);
        
        const pickupMinutes = pickupHour * 60 + pickupMinute;
        const returnMinutes = returnHour * 60 + returnMinute;
        
        const diffMinutes = returnMinutes - pickupMinutes;
        
        if (diffMinutes < 120) {
            errorMessage.textContent = 'For same-day trips, return time must be at least 2 hours after pickup time.';
            errorDiv.style.display = 'block';
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
            return;
        }
    } else {
        // For multi-day trips, just check that return time is not before pickup time on different days
        if (returnTime < pickupTime) {
            errorMessage.textContent = 'Return time should typically be after pickup time.';
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    }
    
    errorDiv.style.display = 'none';
    confirmButton.disabled = false;
    confirmButton.style.opacity = '1';
    confirmButton.style.cursor = 'pointer';
}

document.querySelectorAll('input[name="insurance_plan_id"]').forEach(radio => {
    radio.addEventListener('change', updateTotal);
});

function updateTotal() {
    const selectedInsurance = document.querySelector('input[name="insurance_plan_id"]:checked');
    if (!selectedInsurance) return;
    
    const insuranceDailyFee = parseFloat(selectedInsurance.dataset.dailyFee);
    const insuranceTotalFee = insuranceDailyFee * tripDays;
    
    document.getElementById('insuranceFee').textContent = formatCurrency(insuranceTotalFee);
    
    const totalAmount = totalDaysCost + insuranceTotalFee + platformFee + securityDeposit;
    document.getElementById('totalAmount').textContent = formatCurrency(totalAmount);
    
    // Check if sufficient balance
    const confirmButton = document.getElementById('confirmButton');
    const insufficientDiv = document.getElementById('insufficientFunds');
    
    if (availableBalance < totalAmount) {
        confirmButton.disabled = true;
        confirmButton.style.opacity = '0.5';
        confirmButton.style.cursor = 'not-allowed';
        insufficientDiv.style.display = 'block';
    } else {
        confirmButton.disabled = false;
        confirmButton.style.opacity = '1';
        confirmButton.style.cursor = 'pointer';
        insufficientDiv.style.display = 'none';
    }
}

function formatCurrency(amount) {
    return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Trigger initial calculation
document.addEventListener('DOMContentLoaded', function() {
    const firstInsurance = document.querySelector('input[name="insurance_plan_id"]');
    if (firstInsurance) {
        firstInsurance.checked = true;
        updateTotal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
