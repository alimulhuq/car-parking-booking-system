<?php
    require_once 'config/db.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = $_SESSION['user_id'];

    if ($booking_id > 0) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
            $stmt->execute([$booking_id, $user_id]);
            $booking = $stmt->fetch();
            
            if ($booking && ($booking['status'] == 'confirmed' || $booking['status'] == 'pending') && $booking['payment_status'] != 'paid') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
                $stmt->execute([$booking_id, $user_id]);
                
                $updateStmt = $pdo->prepare("
                    UPDATE parking_locations 
                    SET available_spots = available_spots + 1 
                    WHERE id = ?
                ");
                $updateStmt->execute([$booking['parking_location_id']]);
                
                $lockStmt = $pdo->prepare("
                    UPDATE spot_locks SET is_active = FALSE 
                    WHERE parking_location_id = ? AND spot_number = ? AND locked_by = ?
                ");
                $lockStmt->execute([$booking['parking_location_id'], $booking['spot_number'], $user_id]);
                
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read) 
                    VALUES (?, ?, ?, 'booking', 0)
                ");
                $message = "Your booking {$booking['booking_ref']} has been cancelled successfully.";
                $notifStmt->execute([$user_id, 'Booking Cancelled', $message]);
                
                $pdo->commit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }

    header('Location: my_bookings.php');
    exit();
?>