<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Car Trip Now - Marketplace'; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="<?php echo SITE_URL; ?>">
                    <i class="fas fa-home"></i>
                    <?php echo SITE_NAME; ?>
                </a>
            </div>
            <div class="nav-menu">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Dashboard</a>
                        <a href="<?php echo SITE_URL; ?>/admin/users.php">Users</a>
                        <a href="<?php echo SITE_URL; ?>/admin/listings.php">Listings</a>
                        <a href="<?php echo SITE_URL; ?>/admin/bookings.php">Bookings</a>
                        <a href="<?php echo SITE_URL; ?>/admin/payouts.php">Payouts</a>
                        <a href="<?php echo SITE_URL; ?>/admin/transactions.php">Transactions</a>
                    <?php elseif (isHost()): ?>
                        <a href="<?php echo SITE_URL; ?>/host/dashboard.php">Dashboard</a>
                        <a href="<?php echo SITE_URL; ?>/host/listings.php">My Listings</a>
                        <a href="<?php echo SITE_URL; ?>/host/bookings.php">Bookings</a>
                        <a href="<?php echo SITE_URL; ?>/host/earnings.php">Earnings</a>
                        <a href="<?php echo SITE_URL; ?>/host/payouts.php">Payouts</a>
                    <?php elseif (isGuest()): ?>
                        <a href="<?php echo SITE_URL; ?>/guest/browse.php">Browse</a>
                        <a href="<?php echo SITE_URL; ?>/guest/wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                        <a href="<?php echo SITE_URL; ?>/guest/bookings.php">My Bookings</a>
                        <a href="<?php echo SITE_URL; ?>/guest/checkout.php"><i class="fas fa-sign-out-alt"></i> Check Out</a>
                        <a href="<?php echo SITE_URL; ?>/guest/balance.php">Balance</a>
                    <?php endif; ?>
                    <div class="user-menu">
                        <span class="user-name">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                        </span>
                        <a href="<?php echo SITE_URL; ?>/logout.php" class="btn-logout">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php">Login</a>
                    <a href="<?php echo SITE_URL; ?>/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
