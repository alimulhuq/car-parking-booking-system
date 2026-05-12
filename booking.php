<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    require_once 'config/db.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=booking');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $error = '';
    $booking_id = null;

    $location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
    $spot_number = isset($_GET['spot_number']) ? htmlspecialchars(urldecode($_GET['spot_number'])) : '';
    $location_name = isset($_GET['location_name']) ? htmlspecialchars(urldecode($_GET['location_name'])) : '';
    $price_per_hour = isset($_GET['price_per_hour']) ? floatval($_GET['price_per_hour']) : 3.50;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $from = isset($_GET['from']) ? $_GET['from'] : '09:00';
    $to = isset($_GET['to']) ? $_GET['to'] : '18:00';
    $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 0;
    $amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
    $lock_token = isset($_GET['lock_token']) ? $_GET['lock_token'] : (isset($_POST['lock_token']) ? $_POST['lock_token'] : '');

    if ($location_id == 0) {
        $error = "No parking location selected. Please go back and choose a parking spot.";
    }

    if (empty($spot_number)) {
        $error = "No parking spot selected. Please go back and choose a spot.";
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $from)) {
        $from = '09:00';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $to)) {
        $to = '18:00';
    }

    if ($amount == 0 && $hours > 0 && $price_per_hour > 0) {
        $amount = $hours * $price_per_hour;
    } 
    elseif ($amount == 0 && !empty($from) && !empty($to)) {
        $start = new DateTime($from);
        $end = new DateTime($to);
        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        if ($hours <= 0) $hours = 1;
        $amount = $hours * $price_per_hour;
    }

    if (!empty($lock_token)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM spot_locks 
                WHERE lock_token = ? 
                AND parking_location_id = ? 
                AND spot_number = ?
                AND booking_date = ?
                AND is_active = TRUE 
                AND expires_at > NOW()
                AND locked_by = ?
            ");
            $stmt->execute([$lock_token, $location_id, $spot_number, $date, $user_id]);
            $valid_lock = $stmt->fetch();
            
            if (!$valid_lock) {
                $error = "Your session for this spot has expired or the spot has been booked by someone else. Please go back and select another spot.";
            }
        } catch (Exception $e) {
            $error = "Error verifying spot availability. Please try again.";
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM parking_locations WHERE id = ?");
        $stmt->execute([$location_id]);
        $existing_location = $stmt->fetch();
        
        if (!$existing_location && $location_id > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO parking_locations (id, name, address, total_spots, available_spots, price_per_hour, features, is_active) 
                VALUES (?, ?, ?, 100, 95, ?, '24/7 security, CCTV cameras', 1)
            ");
            $address = $location_name . ", Downtown Area";
            $stmt->execute([$location_id, $location_name, $address, $price_per_hour]);
        }
    } catch (Exception $e) {
        // If insert fails, we'll use the ID as is
    }

    $location = [
        'id' => $location_id,
        'name' => $location_name,
        'address' => $location_name . ', Downtown',
        'price_per_hour' => $price_per_hour,
        'total_spots' => 100,
        'available_spots' => 95
    ];

    try {
        $stmt = $pdo->prepare("SELECT * FROM parking_locations WHERE id = ?");
        $stmt->execute([$location_id]);
        $db_loc = $stmt->fetch();
        if ($db_loc) {
            $location = $db_loc;
        }
    } catch (Exception $e) {
        // Use default location
    }

    $vehicles = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$user_id]);
        $vehicles = $stmt->fetchAll();
    } catch (Exception $e) {
        $vehicles = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
        $vehicle_id = intval($_POST['vehicle_id']);
        $special_requests = trim($_POST['special_requests'] ?? '');
        $submitted_lock_token = $_POST['lock_token'] ?? '';
        $booking_ref = 'PK' . strtoupper(uniqid());
        
        if ($vehicle_id <= 0) {
            $error = "Please select a vehicle";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    SELECT id FROM spot_locks 
                    WHERE lock_token = ? 
                    AND parking_location_id = ? 
                    AND spot_number = ?
                    AND booking_date = ?
                    AND is_active = TRUE 
                    AND expires_at > NOW()
                    AND locked_by = ?
                ");
                $stmt->execute([$submitted_lock_token, $location_id, $spot_number, $date, $user_id]);
                $valid_lock = $stmt->fetch();
                
                if (!$valid_lock) {
                    $pdo->rollBack();
                    $error = "This spot is no longer available. It may have been booked by someone else. Please go back and select another spot.";
                } else {
                    $checkStmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM bookings 
                        WHERE parking_location_id = ? 
                        AND spot_number = ?
                        AND booking_date = ?
                        AND status = 'confirmed'
                        AND (
                            (start_time < ? AND end_time > ?)
                        )
                    ");
                    $checkStmt->execute([$location_id, $spot_number, $date, $to, $from]);
                    $already_booked = $checkStmt->fetch();
                    
                    if ($already_booked['count'] > 0) {
                        $pdo->rollBack();
                        $error = "This spot has already been booked. Please select another spot.";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO bookings (
                                user_id, vehicle_id, parking_location_id, 
                                spot_number, location_name, booking_ref, booking_date, 
                                start_time, end_time, amount, payment_status, status, special_requests
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
                        ");
                        
                        if ($stmt->execute([
                            $user_id, $vehicle_id, $location_id, 
                            $spot_number, $location['name'], $booking_ref, $date, 
                            $from, $to, $amount, $special_requests
                        ])) {
                            $booking_id = $pdo->lastInsertId();
                            
                            $updateStmt = $pdo->prepare("
                                UPDATE parking_locations 
                                SET available_spots = available_spots - 1 
                                WHERE id = ? AND available_spots > 0
                            ");
                            $updateStmt->execute([$location_id]);
                            
                            $lockStmt = $pdo->prepare("
                                UPDATE spot_locks SET is_active = FALSE WHERE lock_token = ?
                            ");
                            $lockStmt->execute([$submitted_lock_token]);
                            
                            $notifStmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, type, is_read) 
                                VALUES (?, ?, ?, 'booking', 0)
                            ");
                            $message = "Your booking at {$location['name']} (Spot $spot_number) has been created. Please complete payment to confirm.";
                            $notifStmt->execute([$user_id, 'Booking Created - Pending Payment', $message]);
                            
                            $pdo->commit();
                            
                            header("Location: payment.php?booking_id=" . $booking_id);
                            exit();
                        } else {
                            $pdo->rollBack();
                            $error = "Booking failed. Please try again.";
                        }
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Booking | ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo-area {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .logo-area i {
            color: #2563eb;
        }

        .logo-bold {
            color: #1e40af;
        }

        .main-nav {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .main-nav a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
        }

        .profile-link {
            background: #2563eb10;
            padding: 8px 16px;
            border-radius: 40px;
            color: #2563eb !important;
        }

        .btn-outline-light {
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 40px;
            border: 1.5px solid #2563eb;
            color: #2563eb;
            font-weight: 500;
            transition: 0.2s;
            display: inline-block;
        }

        .btn-outline-light:hover {
            background: #2563eb;
            color: white;
        }

        .booking-panel {
            max-width: 700px;
            margin: 60px auto;
            background: white;
            border-radius: 32px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eef2ff;
        }

        .booking-header h2 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 48px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }

        .booking-details-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 20px;
            padding: 20px;
            margin: 25px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: #1e293b;
        }

        .spot-badge {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .vehicle-selector {
            background: #f8fafc;
            border-radius: 16px;
            padding: 15px;
            border: 2px solid #e2e8f0;
        }

        .vehicle-option {
            padding: 12px;
            margin: 8px 0;
            border-radius: 12px;
            background: white;
            border: 1px solid #e2e8f0;
            display: block;
            cursor: pointer;
            transition: 0.2s;
        }

        .vehicle-option:hover {
            background: #eff6ff;
            border-color: #2563eb;
        }

        .vehicle-option input {
            width: auto;
            margin-right: 12px;
            transform: scale(1.1);
        }

        .error-box {
            background: #fee2e2;
            color: #dc2626;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }

        .error-box a {
            color: #2563eb;
            text-decoration: none;
        }

        .error-box a:hover {
            text-decoration: underline;
        }

        .amount-highlight {
            font-size: 1.5rem;
            font-weight: 800;
            color: #16a34a;
        }

        .add-vehicle-link {
            display: inline-block;
            margin-top: 12px;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .warning-box {
            background: #fef3c7;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
            font-size: 0.85rem;
        }

        .lock-info {
            background: #e0e7ff;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #4338ca;
        }

        @media (max-width: 768px) {
            .booking-panel {
                margin: 20px;
                padding: 20px;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .header-flex {
                flex-direction: column;
                text-align: center;
            }

            .main-nav {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="container header-flex">
            <div class="logo-area">
                <i class="fas fa-parking"></i>
                <span>Park<span class="logo-bold">Ease</span></span>
            </div>
            <nav class="main-nav">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="my_bookings.php"><i class="fas fa-history"></i> My Bookings</a>
                <a href="profile.php" class="profile-link">
                    <i class="fas fa-user-circle"></i> 
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </a>
            </nav>
            <div class="header-cta">
                <a href="logout.php" class="btn-outline-light"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <div class="booking-panel">
        <div class="booking-header">
            <i class="fas fa-parking" style="font-size: 2.5rem; color: #2563eb;"></i>
            <h2>Complete Your Booking</h2>
            <p style="color: #64748b;">Review details and confirm your reservation</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <div style="margin-top: 10px;">
                    <a href="search_results.php?location=<?php echo urlencode($location_name); ?>&checkin=<?php echo $date; ?>&start_time=<?php echo $from; ?>&end_time=<?php echo $to; ?>">
                        <i class="fas fa-arrow-left"></i> Go back to search and select another spot
                    </a>
                </div>
            </div>
        <?php else: ?>
        <div class="booking-details-card">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Booking Details</h3>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-building"></i> Location:</span>
                <span class="detail-value"><?php echo htmlspecialchars($location['name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-map-pin"></i> Selected Spot:</span>
                <span class="detail-value"><span class="spot-badge"><?php echo htmlspecialchars($spot_number); ?></span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-calendar"></i> Date:</span>
                <span class="detail-value"><?php echo date('l, F j, Y', strtotime($date)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-clock"></i> Time:</span>
                <span class="detail-value"><?php echo date('g:i A', strtotime($from)); ?> - <?php echo date('g:i A', strtotime($to)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-hourglass-half"></i> Duration:</span>
                <span class="detail-value"><?php echo $hours; ?> hour(s)</span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-tag"></i> Rate:</span>
                <span class="detail-value">$<?php echo number_format($location['price_per_hour'], 2); ?> / hour</span>
            </div>
            <div class="detail-row" style="margin-top: 10px; padding-top: 15px; border-top: 2px solid #cbd5e1;">
                <span class="detail-label" style="font-size: 1.1rem; font-weight: 700;">Total Amount:</span>
                <span class="amount-highlight">$<?php echo number_format($amount, 2); ?></span>
            </div>
            <?php if(!empty($lock_token)): ?>
            <div class="lock-info">
                <i class="fas fa-lock"></i> This spot is reserved for you for 15 minutes. Complete your booking to confirm.
            </div>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="lock_token" value="<?php echo htmlspecialchars($lock_token); ?>">
            
            <div class="form-group">
                <label><i class="fas fa-car"></i> Select Vehicle *</label>
                <?php if(empty($vehicles)): ?>
                    <div class="vehicle-selector" style="text-align: center;">
                        <i class="fas fa-car-side" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                        <p style="color: #64748b; margin-bottom: 10px;">No vehicles added to your account yet.</p>
                        <a href="profile.php" class="add-vehicle-link">
                            <i class="fas fa-plus-circle"></i> Add a vehicle first
                        </a>
                    </div>
                <?php else: ?>
                    <div class="vehicle-selector">
                        <?php foreach($vehicles as $vehicle): ?>
                            <label class="vehicle-option">
                                <input type="radio" name="vehicle_id" value="<?php echo $vehicle['id']; ?>" 
                                    <?php echo $vehicle['is_default'] ? 'checked' : ''; ?> required>
                                <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                <?php if($vehicle['vehicle_model'] || $vehicle['vehicle_color']): ?>
                                    (<?php echo htmlspecialchars($vehicle['vehicle_model']); ?> 
                                    <?php echo htmlspecialchars($vehicle['vehicle_color']); ?>)
                                <?php endif; ?>
                                <?php if($vehicle['is_default']): ?>
                                    <span style="color:#16a34a; margin-left: 8px;">
                                        <i class="fas fa-star"></i> Default
                                    </span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        <a href="profile.php" class="add-vehicle-link">
                            <i class="fas fa-plus-circle"></i> Add another vehicle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-pen"></i> Special Requests (Optional)</label>
                <textarea name="special_requests" rows="3" 
                    placeholder="Any special requirements? (e.g., need extra space, near elevator, etc.)"
                    style="resize: vertical;"></textarea>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-info-circle"></i>
                <span>You will be redirected to the payment page after confirming your booking. Your spot is reserved for 15 minutes.</span>
            </div>
            
            <button type="submit" name="confirm_booking" class="btn-primary" <?php echo empty($vehicles) ? 'disabled' : ''; ?>>
                <i class="fas fa-arrow-right"></i> Proceed to Payment - $<?php echo number_format($amount, 2); ?>
            </button>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>