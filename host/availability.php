<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Manage Availability - Host Dashboard';

try {
    $pdo = getPDOConnection();
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        $_SESSION['error_message'] = 'Listing not found';
        redirect(SITE_URL . '/host/listings.php');
    }
    
    // Get current month or specified month
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    
    // Get existing availability data for this month
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT date, status 
        FROM vehicle_availability 
        WHERE vehicle_id = ? 
        AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$vehicle_id, $start_date, $end_date]);
    $availability_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get bookings for this month
    $stmt = $pdo->prepare("
        SELECT pickup_date, return_date, trip_status, trip_id, 
               u.full_name as guest_name
        FROM trips b
        JOIN users u ON b.renter_id = u.user_id
        WHERE vehicle_id = ? 
        AND trip_status IN ('confirmed', 'checked_in')
        AND (
            (pickup_date BETWEEN ? AND ?)
            OR (return_date BETWEEN ? AND ?)
            OR (pickup_date <= ? AND return_date >= ?)
        )
    ");
    $stmt->execute([
        $vehicle_id, 
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date
    ]);
    $bookings = $stmt->fetchAll();
    
    // Build booked dates array
    $booked_dates = [];
    foreach ($bookings as $booking) {
        $current = strtotime($booking['pickup_date']);
        $end = strtotime($booking['return_date']);
        while ($current < $end) {
            $date = date('Y-m-d', $current);
            $booked_dates[$date] = [
                'trip_id' => $booking['trip_id'],
                'guest_name' => $booking['guest_name'],
                'status' => $booking['trip_status']
            ];
            $current = strtotime('+1 day', $current);
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading availability data';
    redirect(SITE_URL . '/host/listings.php');
}

include '../includes/header.php';
?>

<style>
.calendar {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}
.calendar-day-header {
    text-align: center;
    font-weight: bold;
    padding: 10px;
    color: #666;
    font-size: 14px;
}
.calendar-day {
    aspect-ratio: 1;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    min-height: 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
.calendar-day:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.calendar-day.empty {
    border: none;
    cursor: default;
}
.calendar-day.empty:hover {
    transform: none;
    box-shadow: none;
}
.calendar-day.available {
    background-color: #e8f5e9;
    border-color: #4caf50;
}
.calendar-day.booked {
    background-color: #ffebee;
    border-color: #f44336;
    cursor: not-allowed;
}
.calendar-day.blocked {
    background-color: #fff3e0;
    border-color: #ff9800;
}
.calendar-day.past {
    background-color: #f5f5f5;
    color: #999;
    cursor: not-allowed;
}
.day-number {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 3px;
}
.day-status {
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 600;
}
.legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding: 15px;
    background-color: #f5f5f5;
    border-radius: 8px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #ddd;
}
</style>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1><i class="fas fa-calendar-alt"></i> Availability Calendar</h1>
            <p style="color: #666; margin: 5px 0 0 0;">
                <strong><?php echo htmlspecialchars($listing['year'] . ' ' . $listing['make'] . ' ' . $listing['model']); ?></strong>
            </p>
        </div>
        <a href="listings.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Listings
        </a>
    </div>
    
    <div class="calendar">
        <div class="calendar-header">
            <a href="?id=<?php echo $vehicle_id; ?>&year=<?php echo date('Y', strtotime("$year-$month-01 -1 month")); ?>&month=<?php echo date('m', strtotime("$year-$month-01 -1 month")); ?>" 
               class="btn btn-outline">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <h2><?php echo date('F Y', strtotime("$year-$month-01")); ?></h2>
            <a href="?id=<?php echo $vehicle_id; ?>&year=<?php echo date('Y', strtotime("$year-$month-01 +1 month")); ?>&month=<?php echo date('m', strtotime("$year-$month-01 +1 month")); ?>" 
               class="btn btn-outline">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <div class="calendar-grid">
            <?php
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($days as $day) {
                echo "<div class='calendar-day-header'>$day</div>";
            }
            
            $first_day = date('w', strtotime("$year-$month-01"));
            $days_in_month = date('t', strtotime("$year-$month-01"));
            $today = date('Y-m-d');
            
            // Empty cells before month starts
            for ($i = 0; $i < $first_day; $i++) {
                echo "<div class='calendar-day empty'></div>";
            }
            
            // Days of the month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $is_past = $date < $today;
                
                // Check if booked
                $is_booked = isset($booked_dates[$date]);
                $booking_info = $booked_dates[$date] ?? null;
                
                // Check if blocked by host
                $is_blocked = isset($availability_data[$date]) && $availability_data[$date] === 'blocked';
                
                $classes = ['calendar-day'];
                $status_text = '';
                $clickable = !$is_past && !$is_booked;
                
                if ($is_past) {
                    $classes[] = 'past';
                    $status_text = 'Past';
                } elseif ($is_booked) {
                    $classes[] = 'booked';
                    $status_text = 'Booked';
                } elseif ($is_blocked) {
                    $classes[] = 'blocked';
                    $status_text = 'Blocked';
                } else {
                    $classes[] = 'available';
                    $status_text = 'Available';
                }
                
                $onclick = $clickable ? "onclick=\"toggleAvailability('$date', '$vehicle_id', '" . ($is_blocked ? 'available' : 'blocked') . "')\"" : '';
                $title = $is_booked ? "Booked by {$booking_info['guest_name']}" : '';
                
                echo "<div class='" . implode(' ', $classes) . "' $onclick title='$title'>";
                echo "<div class='day-number'>$day</div>";
                echo "<div class='day-status'>$status_text</div>";
                echo "</div>";
            }
            ?>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #e8f5e9; border-color: #4caf50;"></div>
                <span>Available (Click to block)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #fff3e0; border-color: #ff9800;"></div>
                <span>Blocked by you (Click to unblock)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ffebee; border-color: #f44336;"></div>
                <span>Booked (Cannot change)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #f5f5f5;"></div>
                <span>Past dates</span>
            </div>
        </div>
    </div>
    
    <div class="card" style="margin-top: 30px;">
        <h3><i class="fas fa-info-circle"></i> How It Works</h3>
        <ul style="line-height: 1.8;">
            <li><strong>Click available dates</strong> to block them from guest bookings</li>
            <li><strong>Click blocked dates</strong> to make them available again</li>
            <li><strong>Booked dates</strong> cannot be changed (shown in red)</li>
            <li><strong>Past dates</strong> are greyed out and cannot be modified</li>
            <li>Changes take effect immediately</li>
        </ul>
    </div>
</div>

<script>
function toggleAvailability(date, listingId, newStatus) {
    if (!confirm(`Are you sure you want to ${newStatus === 'blocked' ? 'block' : 'unblock'} this date?`)) {
        return;
    }
    
    fetch('update_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `vehicle_id=${listingId}&date=${date}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error updating availability');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating availability');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
