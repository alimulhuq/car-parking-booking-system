<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['locked' => false]);
    exit();
}

$location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
$spot_number = isset($_POST['spot_number']) ? $_POST['spot_number'] : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';

if (!$location_id || !$spot_number || !$date) {
    echo json_encode(['locked' => false]);
    exit();
}

try {
    // First, check if already booked
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM bookings 
        WHERE parking_location_id = ? 
        AND spot_number = ? 
        AND booking_date = ?
        AND status = 'confirmed'
    ");
    $stmt->execute([$location_id, $spot_number, $date]);
    $booked = $stmt->fetch();
    
    if ($booked['count'] > 0) {
        echo json_encode(['locked' => false, 'reason' => 'already_booked']);
        exit();
    }
    
    // Clean old locks
    $stmt = $pdo->prepare("UPDATE spot_locks SET is_active = 0 WHERE expires_at < NOW()");
    $stmt->execute();
    
    // Check if locked by another user
    $stmt = $pdo->prepare("
        SELECT id FROM spot_locks 
        WHERE parking_location_id = ? 
        AND spot_number = ? 
        AND booking_date = ?
        AND is_active = 1 
        AND expires_at > NOW()
        AND locked_by != ?
    ");
    $stmt->execute([$location_id, $spot_number, $date, $_SESSION['user_id']]);
    $existing_lock = $stmt->fetch();
    
    if ($existing_lock) {
        echo json_encode(['locked' => false, 'reason' => 'locked_by_other']);
        exit();
    }
    
    // Remove old locks from this user
    $stmt = $pdo->prepare("
        UPDATE spot_locks SET is_active = 0 
        WHERE locked_by = ? AND parking_location_id = ? AND spot_number = ? AND booking_date = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $location_id, $spot_number, $date]);
    
    // Create new lock
    $lock_token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO spot_locks (parking_location_id, spot_number, booking_date, start_time, end_time, locked_by, lock_token, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ");
    $stmt->execute([$location_id, $spot_number, $date, $start_time, $end_time, $_SESSION['user_id'], $lock_token]);
    
    echo json_encode(['locked' => true, 'lock_token' => $lock_token]);
    
} catch (Exception $e) {
    echo json_encode(['locked' => false, 'error' => $e->getMessage()]);
}
?>