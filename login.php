<?php
require_once 'config/config.php';
require_once 'config/database.php';

$pageTitle = 'Login - Car Trip Now';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/dashboard.php');
    } elseif (isOwner()) {
        redirect(SITE_URL . '/host/dashboard.php');
    } else {
        redirect(SITE_URL . '/guest/browse.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $pdo = getPDOConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    redirect(SITE_URL . '/admin/dashboard.php');
                } elseif ($user['user_type'] === 'owner') {
                    redirect(SITE_URL . '/host/dashboard.php');
                } else {
                    redirect(SITE_URL . '/guest/browse.php');
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fas fa-sign-in-alt"></i> Login to Car Trip Now</h2>
            <p>Access your account and manage your trips</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="auth-link">
            <p>Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Register here</a></p>
        </div>
        
        <!-- <div style="margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 8px;">
            <p style="margin: 0; font-size: 14px;"><strong>Demo Accounts:</strong></p>
            <p style="margin: 5px 0; font-size: 13px;">Admin: admin@cartripnow.com / admin123</p>
            <p style="margin: 5px 0; font-size: 13px;">Create Renter or Owner account to test</p>
        </div> -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>
