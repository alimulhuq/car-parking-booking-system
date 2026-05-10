<?php
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

if ($booking_id > 0) {
    // Only cancel if booking belongs to user and is confirmed
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'confirmed'");
    $stmt->execute([$booking_id, $user_id]);
}

header('Location: my_bookings.php');
exit();
?>