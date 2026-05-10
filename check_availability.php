<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['available' => true]);
    exit();
}

$location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
$spot_number = isset($_POST['spot_number']) ? $_POST['spot_number'] : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';

if (!$location_id || !$spot_number || !$date) {
    echo json_encode(['available' => true]);
    exit();
}

try {
    // Simple check - is there any confirmed booking for this spot on this date?
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
        echo json_encode(['available' => false, 'reason' => 'already_booked']);
        exit();
    }
    
    // Check if locked by another user (15 min lock)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM spot_locks 
        WHERE parking_location_id = ? 
        AND spot_number = ?
        AND booking_date = ?
        AND is_active = 1 
        AND expires_at > NOW()
        AND locked_by != ?
    ");
    
    $stmt->execute([$location_id, $spot_number, $date, $_SESSION['user_id']]);
    $locked = $stmt->fetch();
    
    echo json_encode(['available' => $locked['count'] == 0]);
    
} catch (Exception $e) {
    echo json_encode(['available' => true]);
}
?>