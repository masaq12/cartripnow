<?php
/**
 * Automated Payment Release Cron Job
 * 
 * This script should be run daily (recommended: 2 AM)
 * Automatically releases payments for completed stays where check-out date has passed
 * 
 * Cron Setup:
 * 0 2 * * * php /path/to/dream_destinations/scripts/release_payments_cron.php
 */

// Set execution time limit for cron
set_time_limit(300); // 5 minutes

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Log file
$log_file = dirname(__DIR__) . '/logs/payment_release_' . date('Y-m-d') . '.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Payment Release Cron Job Started ===");

try {
    $pdo = getPDOConnection();
    
    // Find trips eligible for payment release
    // Criteria: checked_in status, return_date date has passed, payment not yet released
    $stmt = $pdo->prepare("
        SELECT b.*, l.owner_id, e.status as escrow_status
        FROM trips b
        JOIN vehicles l ON b.vehicle_id = l.vehicle_id
        JOIN escrow e ON b.trip_id = e.trip_id
        WHERE b.trip_status IN ('confirmed', 'checked_in')
        AND b.return_date < CURDATE()
        AND b.payment_status = 'held'
        AND e.status = 'held'
    ");
    $stmt->execute();
    $eligible_trips = $stmt->fetchAll();
    
    $total_processed = 0;
    $total_errors = 0;
    $total_amount = 0;
    
    logMessage("Found " . count($eligible_trips) . " trips eligible for payment release");
    
    foreach ($eligible_trips as $booking) {
        try {
            $pdo->beginTransaction();
            
            logMessage("Processing booking #{$booking['trip_id']}");
            
            // Update booking status
            $stmt = $pdo->prepare("
                UPDATE trips 
                SET trip_status = 'completed',
                    payment_status = 'completed',
                    checked_out_at = NOW(),
                    updated_at = NOW()
                WHERE trip_id = ?
            ");
            $stmt->execute([$booking['trip_id']]);
            
            // Release escrow
            $stmt = $pdo->prepare("
                UPDATE escrow 
                SET status = 'released',
                    released_at = NOW(),
                    release_notes = 'Automatic release (cron)'
                WHERE trip_id = ? AND status = 'held'
            ");
            $stmt->execute([$booking['trip_id']]);
            
            // Calculate host earnings
            $platform_fee = $booking['service_fee'];
            $host_earnings = $booking['total_amount'] - $platform_fee;
            
            // Update guest balance - remove pending hold
            $stmt = $pdo->prepare("
                UPDATE renter_balances 
                SET pending_holds = pending_holds - ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$booking['total_amount'], $booking['renter_id']]);
            
            // Update host balance - move from pending to available
            $stmt = $pdo->prepare("
                UPDATE host_balances 
                SET available_balance = available_balance + ?,
                    pending_balance = pending_balance - ?,
                    total_earned = total_earned + ?,
                    platform_fees_paid = platform_fees_paid + ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $host_earnings,
                $host_earnings,
                $host_earnings,
                $platform_fee,
                $booking['owner_id']
            ]);
            
            // Get host balance for transaction
            $stmt = $pdo->prepare("SELECT available_balance FROM host_balances WHERE user_id = ?");
            $stmt->execute([$booking['owner_id']]);
            $host_new_balance = $stmt->fetchColumn();
            
            // Record transaction for host
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id, transaction_type, amount, balance_before, balance_after,
                    reference_type, reference_id, description
                ) VALUES (?, 'earning', ?, ?, ?, 'booking', ?, ?)
            ");
            $stmt->execute([
                $booking['owner_id'],
                $host_earnings,
                $host_new_balance - $host_earnings,
                $host_new_balance,
                $booking['trip_id'],
                'Earning from booking #' . $booking['trip_id'] . ' (auto-released)'
            ]);
            
            $pdo->commit();
            
            $total_processed++;
            $total_amount += $host_earnings;
            
            logMessage("✓ Successfully released payment for booking #{$booking['trip_id']} - Amount: " . formatCurrency($host_earnings));
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $total_errors++;
            logMessage("✗ Error processing booking #{$booking['trip_id']}: " . $e->getMessage());
        }
    }
    
    logMessage("=== Payment Release Summary ===");
    logMessage("Total Processed: $total_processed");
    logMessage("Total Errors: $total_errors");
    logMessage("Total Amount Released: " . formatCurrency($total_amount));
    logMessage("=== Cron Job Completed ===\n");
    
    // If running from command line, output summary
    if (php_sapi_name() === 'cli') {
        echo "\n";
        echo "Payment Release Cron Job Completed\n";
        echo "==================================\n";
        echo "Total Processed: $total_processed\n";
        echo "Total Errors: $total_errors\n";
        echo "Total Amount Released: " . formatCurrency($total_amount) . "\n";
        echo "Log file: $log_file\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "FATAL ERROR: " . $e->getMessage() . "\n";
    }
}
?>
