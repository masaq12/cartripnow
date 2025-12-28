<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isHost() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    $vehicle_id = (int)$_POST['vehicle_id'];
    $date = $_POST['date'];
    $status = $_POST['status']; // 'blocked' or 'available'
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Listing not found or access denied');
    }
    
    // Check if date is in the past
    if ($date < date('Y-m-d')) {
        throw new Exception('Cannot modify past dates');
    }
    
    // Check if date is already booked
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM trips 
        WHERE vehicle_id = ? 
        AND trip_status IN ('confirmed', 'checked_in')
        AND ? BETWEEN pickup_date AND DATE_SUB(return_date, INTERVAL 1 DAY)
    ");
    $stmt->execute([$vehicle_id, $date]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Cannot modify booked dates');
    }
    
    // Update or insert availability
    if ($status === 'available') {
        // Remove block (delete record or set to available)
        $stmt = $pdo->prepare("
            DELETE FROM vehicle_availability 
            WHERE vehicle_id = ? AND date = ? AND status = 'blocked'
        ");
        $stmt->execute([$vehicle_id, $date]);
    } else {
        // Block date
        $stmt = $pdo->prepare("
            INSERT INTO vehicle_availability (vehicle_id, date, status) 
            VALUES (?, ?, 'blocked')
            ON DUPLICATE KEY UPDATE status = 'blocked'
        ");
        $stmt->execute([$vehicle_id, $date]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Availability updated successfully',
        'status' => $status
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
