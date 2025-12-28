<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isHost()) {
    redirect(SITE_URL . '/login.php');
}

$vehicle_id = $_GET['id'] ?? 0;
$pageTitle = 'Manage Photos - Host Dashboard';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    try {
        $pdo = getPDOConnection();
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
        $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized');
        }
        
        // Ensure upload directory exists
        if (!is_dir(VEHICLE_PHOTOS_PATH)) {
            mkdir(VEHICLE_PHOTOS_PATH, 0755, true);
        }
        
        $uploaded_count = 0;
        $files = $_FILES['photos'];
        
        // Handle multiple file uploads
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // Validate file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $files['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
                    continue;
                }
                
                // Validate file size
                if ($files['size'][$i] > MAX_FILE_SIZE) {
                    continue;
                }
                
                // Generate unique filename
                $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $filename = 'listing_' . $vehicle_id . '_' . time() . '_' . $i . '.' . $extension;
                $filepath = VEHICLE_PHOTOS_PATH . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                    // Get next display order
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM vehicle_photos WHERE vehicle_id = ?");
                    $stmt->execute([$vehicle_id]);
                    $next_order = $stmt->fetchColumn();
                    
                    // Check if this is the first photo (make it primary)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicle_photos WHERE vehicle_id = ?");
                    $stmt->execute([$vehicle_id]);
                    $is_first = $stmt->fetchColumn() == 0;
                    
                    // Insert photo record
                    $stmt = $pdo->prepare("
                        INSERT INTO vehicle_photos (vehicle_id, photo_url, is_primary, display_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $vehicle_id,
                        'uploads/vehicles/' . $filename,
                        $is_first ? 1 : 0,
                        $next_order
                    ]);
                    
                    $uploaded_count++;
                }
            }
        }
        
        $_SESSION['success_message'] = $uploaded_count . ' photo(s) uploaded successfully!';
        redirect($_SERVER['PHP_SELF'] . '?id=' . $vehicle_id);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error uploading photos: ' . $e->getMessage();
    }
}

// Handle set primary photo
if (isset($_POST['set_primary'])) {
    try {
        $pdo = getPDOConnection();
        $photo_id = $_POST['photo_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT lp.photo_id FROM vehicle_photos lp
            JOIN vehicles l ON lp.vehicle_id = l.vehicle_id
            WHERE lp.photo_id = ? AND l.owner_id = ?
        ");
        $stmt->execute([$photo_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Unauthorized');
        }
        
        // Remove primary from all photos of this listing
        $stmt = $pdo->prepare("UPDATE vehicle_photos SET is_primary = 0 WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        
        // Set new primary
        $stmt = $pdo->prepare("UPDATE vehicle_photos SET is_primary = 1 WHERE photo_id = ?");
        $stmt->execute([$photo_id]);
        
        $_SESSION['success_message'] = 'Primary photo updated!';
        redirect($_SERVER['PHP_SELF'] . '?id=' . $vehicle_id);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error setting primary photo';
    }
}

// Handle delete photo
if (isset($_POST['delete_photo'])) {
    try {
        $pdo = getPDOConnection();
        $photo_id = $_POST['photo_id'];
        
        // Get photo info and verify ownership
        $stmt = $pdo->prepare("
            SELECT lp.photo_url FROM vehicle_photos lp
            JOIN vehicles l ON lp.vehicle_id = l.vehicle_id
            WHERE lp.photo_id = ? AND l.owner_id = ?
        ");
        $stmt->execute([$photo_id, $_SESSION['user_id']]);
        $photo = $stmt->fetch();
        
        if (!$photo) {
            throw new Exception('Unauthorized');
        }
        
        // Delete file
        $file_path = BASE_PATH . '/' . $photo['photo_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete record
        $stmt = $pdo->prepare("DELETE FROM vehicle_photos WHERE photo_id = ?");
        $stmt->execute([$photo_id]);
        
        $_SESSION['success_message'] = 'Photo deleted successfully!';
        redirect($_SERVER['PHP_SELF'] . '?id=' . $vehicle_id);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting photo';
    }
}

// Get listing and photos
try {
    $pdo = getPDOConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ? AND owner_id = ?");
    $stmt->execute([$vehicle_id, $_SESSION['user_id']]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        $_SESSION['error_message'] = 'Listing not found';
        redirect(SITE_URL . '/host/listings.php');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, display_order ASC");
    $stmt->execute([$vehicle_id]);
    $photos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading photos';
    redirect(SITE_URL . '/host/listings.php');
}

include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1><i class="fas fa-images"></i> Manage Photos</h1>
        <div style="display: flex; gap: 10px;">
            <a href="preview_listing.php?id=<?php echo $vehicle_id; ?>" class="btn btn-outline" target="_blank">
                <i class="fas fa-eye"></i> Preview
            </a>
            <a href="edit_listing.php?id=<?php echo $vehicle_id; ?>" class="btn btn-secondary">
                <i class="fas fa-edit"></i> Edit Listing
            </a>
            <a href="listings.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="card" style="margin-bottom: 30px;">
        <h2><?php echo htmlspecialchars($listing['year'] . ' ' . $listing['make'] . ' ' . $listing['model']); ?></h2>
        <p style="color: #666;">
            <i class="fas fa-map-marker-alt"></i> 
            <?php echo htmlspecialchars($listing['pickup_city'] . ', ' . $listing['pickup_country']); ?>
        </p>
    </div>
    
    <!-- Upload Photos -->
    <div class="card" style="margin-bottom: 30px;">
        <h2><i class="fas fa-upload"></i> Upload New Photos</h2>
        <p style="color: #666; margin-bottom: 20px;">
            Select one or more photos to upload. Supported formats: JPG, PNG. Max size: 5MB per photo.
        </p>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Select Photos</label>
                <input type="file" name="photos[]" class="form-control" multiple accept="image/jpeg,image/jpg,image/png" required>
                <small class="form-text">You can select multiple photos at once</small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Photos
            </button>
        </form>
    </div>
    
    <!-- Current Photos -->
    <div class="card">
        <h2><i class="fas fa-photo-video"></i> Current Photos (<?php echo count($photos); ?>)</h2>
        
        <?php if (empty($photos)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-images" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                <p>No photos uploaded yet. Upload your first photo above!</p>
            </div>
        <?php else: ?>
            <p style="color: #666; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> The primary photo will be shown as the main image in listings. Drag to reorder (coming soon).
            </p>
            
            <div class="grid grid-3" style="gap: 20px;">
                <?php foreach ($photos as $photo): ?>
                    <div class="card" style="padding: 0; overflow: hidden; position: relative;">
                        <?php if ($photo['is_primary']): ?>
                            <div style="position: absolute; top: 10px; left: 10px; background-color: var(--primary-color); color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; z-index: 10;">
                                <i class="fas fa-star"></i> Primary
                            </div>
                        <?php endif; ?>
                        
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($photo['photo_url']); ?>" 
                             alt="Listing photo" 
                             style="width: 100%; height: 200px; object-fit: cover;"
                             onerror="this.src='<?php echo SITE_URL; ?>/assets/images/placeholder.jpg'">
                        
                        <div style="padding: 15px;">
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <?php if (!$photo['is_primary']): ?>
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="photo_id" value="<?php echo $photo['photo_id']; ?>">
                                        <button type="submit" name="set_primary" class="btn btn-primary" style="width: 100%; padding: 8px; font-size: 12px;">
                                            <i class="fas fa-star"></i> Set Primary
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="photo_id" value="<?php echo $photo['photo_id']; ?>">
                                    <button type="submit" name="delete_photo" class="btn btn-danger" style="width: 100%; padding: 8px; font-size: 12px;"
                                            onclick="return confirm('Delete this photo?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
