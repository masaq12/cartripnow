<?php
require_once 'config/config.php';
require_once 'config/database.php';

$pageTitle = 'Register - Car Trip Now';

if (isLoggedIn()) {
    $userType = getUserType();
    if ($userType === 'admin') {
        redirect(SITE_URL . '/admin/dashboard.php');
    } elseif ($userType === 'owner') {
        redirect(SITE_URL . '/host/dashboard.php');
    } else {
        redirect(SITE_URL . '/guest/browse.php');
    }
}

$error = '';
$success = '';
$user_type = $_GET['type'] ?? 'renter';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'renter';
    
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            $pdo = getPDOConnection();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                $pdo->beginTransaction();
                
                // Insert user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, user_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $password_hash, $full_name, $phone, $user_type]);
                $user_id = $pdo->lastInsertId();
                
                // Create balance record based on user type
                if ($user_type === 'renter') {
                    // Create renter balance with starting balance
                    $starting_balance = 1000.00;
                    $stmt = $pdo->prepare("INSERT INTO renter_balances (user_id, current_balance) VALUES (?, ?)");
                    $stmt->execute([$user_id, $starting_balance]);
                    
                    // Create a default payment credential
                    $credential_number = 'PC-' . strtoupper(generateRandomString(12));
                    $stmt = $pdo->prepare("INSERT INTO payment_credentials (user_id, credential_type, credential_number, credential_name, status, expiry_date) VALUES (?, 'platform_card', ?, ?, 'active', DATE_ADD(CURDATE(), INTERVAL 3 YEAR))");
                    $stmt->execute([$user_id, $credential_number, $full_name]);
                    
                    // Record the starting balance transaction
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, description) VALUES (?, 'deposit', ?, 0.00, ?, 'Welcome bonus - Starting balance')");
                    $stmt->execute([$user_id, $starting_balance, $starting_balance]);
                    
                    $_SESSION['success_message'] = 'Registration successful! You have been credited with $1,000.00 and a payment credential has been issued.';
                } elseif ($user_type === 'owner') {
                    // Create owner balance
                    $stmt = $pdo->prepare("INSERT INTO owner_balances (user_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                    
                    // Create verification record
                    $stmt = $pdo->prepare("INSERT INTO owner_verification (user_id, verification_status) VALUES (?, 'pending')");
                    $stmt->execute([$user_id]);
                    
                    $_SESSION['success_message'] = 'Registration successful! Your vehicle owner account is pending verification.';
                }
                
                $pdo->commit();
                redirect(SITE_URL . '/login.php');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'An error occurred during registration. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container" style="max-width: 550px;">
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
            <p>Join Car Trip Now and start your journey</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Account Type</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <label style="cursor: pointer; padding: 20px; border: 2px solid #ddd; border-radius: 8px; text-align: center; transition: all 0.3s;" 
                           class="account-type-option" data-type="renter">
                        <input type="radio" name="user_type" value="renter" <?php echo $user_type === 'renter' ? 'checked' : ''; ?> required style="display: none;">
                        <i class="fas fa-car" style="font-size: 32px; color: var(--primary-color); display: block; margin-bottom: 10px;"></i>
                        <strong>Renter</strong>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Rent vehicles for trips</p>
                    </label>
                    <label style="cursor: pointer; padding: 20px; border: 2px solid #ddd; border-radius: 8px; text-align: center; transition: all 0.3s;" 
                           class="account-type-option" data-type="owner">
                        <input type="radio" name="user_type" value="owner" <?php echo $user_type === 'owner' ? 'checked' : ''; ?> required style="display: none;">
                        <i class="fas fa-key" style="font-size: 32px; color: var(--secondary-color); display: block; margin-bottom: 10px;"></i>
                        <strong>Vehicle Owner</strong>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">List your vehicles</p>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" required 
                       placeholder="Enter your full name"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       placeholder="your.email@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" 
                       placeholder="+1 (555) 123-4567"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password * (minimum 6 characters)</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6"
                       placeholder="Create a secure password">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6"
                       placeholder="Re-enter your password">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 16px; padding: 12px;">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>
        
        <div class="auth-link">
            <p>Already have an account? <a href="<?php echo SITE_URL; ?>/login.php">Login here</a></p>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-radius: 8px; border-left: 4px solid var(--primary-color);">
            <p style="margin: 0; font-size: 14px; font-weight: bold;"><i class="fas fa-gift" style="color: var(--primary-color);"></i> Renter Benefits:</p>
            <p style="margin: 5px 0; font-size: 13px;">✓ $1,000 starting balance</p>
            <p style="margin: 5px 0; font-size: 13px;">✓ Platform payment credential issued automatically</p>
            <p style="margin: 5px 0; font-size: 13px;">✓ Instant booking capability</p>
            <p style="margin: 5px 0; font-size: 13px;">✓ Secure escrow system</p>
        </div>
    </div>
</div>

<style>
.account-type-option:has(input:checked) {
    border-color: var(--primary-color) !important;
    background-color: rgba(66, 133, 244, 0.05);
}

.account-type-option:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<script>
// Handle account type selection visual feedback
document.querySelectorAll('.account-type-option').forEach(label => {
    label.addEventListener('click', function() {
        document.querySelectorAll('.account-type-option').forEach(l => {
            l.style.borderColor = '#ddd';
            l.style.backgroundColor = 'transparent';
        });
        this.style.borderColor = 'var(--primary-color)';
        this.style.backgroundColor = 'rgba(66, 133, 244, 0.05)';
    });
});

// Set initial state
document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('input[name="user_type"]:checked');
    if (checkedRadio) {
        const label = checkedRadio.closest('.account-type-option');
        label.style.borderColor = 'var(--primary-color)';
        label.style.backgroundColor = 'rgba(66, 133, 244, 0.05)';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
