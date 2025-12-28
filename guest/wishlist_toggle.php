<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isGuest()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$vehicle_id = $_POST['vehicle_id'] ?? 0;

if (!$vehicle_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Check if already in wishlist
    $stmt = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE user_id = ? AND vehicle_id = ?");
    $stmt->execute([$_SESSION['user_id'], $vehicle_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Remove from wishlist
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE wishlist_id = ?");
        $stmt->execute([$existing['wishlist_id']]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        // Add to wishlist
        $stmt = $pdo->prepare("INSERT INTO wishlists (user_id, vehicle_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $vehicle_id]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating wishlist']);
}
?>
