<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    require_once 'config/db.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $success_msg = '';
    $error_msg = '';

    if (isset($_GET['cancel_id'])) {
        $cancel_id = intval($_GET['cancel_id']);
        
        $checkStmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$cancel_id, $user_id]);
        $booking = $checkStmt->fetch();
        
        if ($booking) {
            if ($booking['status'] == 'cancelled') {
                $error_msg = "This booking is already cancelled.";
            } 
            elseif ($booking['status'] == 'completed') {
                $error_msg = "Cannot cancel a completed booking.";
            }
            else {
                $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                if ($updateStmt->execute([$cancel_id])) {
                    try {
                        $restoreStmt = $pdo->prepare("UPDATE parking_locations SET available_spots = available_spots + 1 WHERE id = ?");
                        $restoreStmt->execute([$booking['parking_location_id']]);
                    } catch (Exception $e) {
                        // Ignore if column doesn't exist
                    }
                    
                    $success_msg = "Booking #{$booking['booking_ref']} has been cancelled successfully.";
                    
                    header("Location: my_bookings.php?success=1");
                    exit();
                } else {
                    $error_msg = "Failed to cancel booking. Please try again.";
                }
            }
        } else {
            $error_msg = "Booking not found or you don't have permission to cancel it.";
        }
    }

    if (isset($_GET['success'])) {
        $success_msg = "Booking cancelled successfully!";
    }

    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            v.vehicle_number,
            v.vehicle_model,
            v.vehicle_color
        FROM bookings b 
        LEFT JOIN vehicles v ON b.vehicle_id = v.id 
        WHERE b.user_id = ? AND b.status NOT IN ('cancelled', 'completed')
        ORDER BY 
            CASE 
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'confirmed' THEN 2
                ELSE 3
            END,
            b.booking_date DESC,
            b.start_time ASC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();

    $total = count($bookings);
    $confirmed = 0;
    $pending = 0;
    $total_spent = 0;

    foreach ($bookings as $booking) {
        if ($booking['status'] == 'confirmed' && $booking['payment_status'] == 'paid') {
            $confirmed++;
        }
        if ($booking['status'] == 'pending') {
            $pending++;
        }
        if ($booking['payment_status'] == 'paid' && $booking['status'] != 'cancelled') {
            $total_spent += $booking['amount'];
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | ParkEase</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
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
            transition: 0.2s;
        }

        .main-nav a:hover {
            color: #2563eb;
        }

        .main-nav a.active {
            color: #2563eb;
            font-weight: 600;
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
            background: white;
        }

        .btn-outline-light:hover {
            background: #2563eb;
            color: white;
        }

        .bookings-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .bookings-container > h1 {
            color: white;
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .booking-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .booking-card:hover {
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .booking-ref {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2563eb;
        }

        .booking-status {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #dcfce7; color: #16a34a; }

        .payment-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .payment-paid { background: #dcfce7; color: #16a34a; }
        .payment-pending { background: #fef3c7; color: #d97706; }

        .location-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin: 10px 0;
        }

        .spot-badge {
            background: #2563eb10;
            border: 1px solid #2563eb20;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #2563eb;
            display: inline-block;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eef2ff;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 0.95rem;
        }

        .detail-item i {
            color: #2563eb;
            width: 20px;
        }

        .vehicle-info {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 12px;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .booking-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-pay {
            background: #16a34a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-pay:hover {
            background: #15803d;
            transform: scale(0.98);
        }

        .btn-cancel {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-cancel:hover {
            background: #fecaca;
            transform: scale(0.98);
        }

        .btn-invoice {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-invoice:hover {
            background: #e2e8f0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 32px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #1e293b;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #64748b;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cancel-confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .cancel-modal-content {
            background: white;
            border-radius: 24px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cancel-modal-content i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #dc2626;
        }

        .cancel-modal-content h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #1e293b;
        }

        .cancel-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .btn-confirm-cancel {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-confirm-cancel:hover {
            background: #b91c1c;
            transform: scale(0.98);
        }

        .btn-cancel-modal {
            background: #e2e8f0;
            color: #475569;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-cancel-modal:hover {
            background: #cbd5e1;
        }

        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .booking-details {
                grid-template-columns: 1fr;
            }
            .booking-actions {
                flex-direction: column;
            }
            .booking-actions a,
            .booking-actions button {
                width: 100%;
                justify-content: center;
            }
            .header-flex {
                flex-direction: column;
                text-align: center;
            }
            .main-nav {
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <a href="my_bookings.php" class="active"><i class="fas fa-history"></i> My Bookings</a>
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

    <div class="bookings-container">
        <h1><i class="fas fa-history"></i> My Parking Bookings</h1>
        
        <?php if($success_msg): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_msg): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar-alt"></i>
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Active Bookings</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="stat-number"><?php echo $confirmed; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Payment</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div class="stat-number">$<?php echo number_format($total_spent, 0); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
        
        <?php if(empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No active bookings</h3>
                <p>You don't have any active parking reservations.</p>
                <a href="index.php" class="btn-outline-light" style="margin-top: 20px; display: inline-block;">
                    <i class="fas fa-search"></i> Find Parking Now
                </a>
            </div>
        <?php else: ?>
            <?php foreach($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div>
                            <span class="booking-ref">
                                <i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($booking['booking_ref']); ?>
                            </span>
                            <span class="spot-badge" style="margin-left: 12px;">
                                <i class="fas fa-parking"></i> Spot <?php echo htmlspecialchars($booking['spot_number']); ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <span class="booking-status status-<?php echo $booking['status']; ?>">
                                <i class="fas <?php echo $booking['status'] == 'confirmed' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                            <span class="payment-status payment-<?php echo $booking['payment_status']; ?>">
                                <i class="fas <?php echo $booking['payment_status'] == 'paid' ? 'fa-credit-card' : 'fa-hourglass-half'; ?>"></i>
                                <?php echo ucfirst($booking['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="location-name">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($booking['location_name']); ?>
                    </div>
                    
                    <div class="booking-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-hourglass-half"></i>
                            <span><?php echo $booking['duration_hours']; ?> hours</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-dollar-sign"></i>
                            <span style="font-weight: 700; color: #16a34a;">$<?php echo number_format($booking['amount'], 2); ?></span>
                        </div>
                    </div>
                    
                    <?php if($booking['vehicle_number']): ?>
                        <div class="vehicle-info">
                            <i class="fas fa-car"></i> 
                            <strong><?php echo htmlspecialchars($booking['vehicle_number']); ?></strong>
                            <?php if($booking['vehicle_model'] || $booking['vehicle_color']): ?>
                                (<?php echo htmlspecialchars($booking['vehicle_model']); ?> <?php echo htmlspecialchars($booking['vehicle_color']); ?>)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="booking-actions">
                        <?php if($booking['payment_status'] == 'pending' && $booking['status'] != 'cancelled'): ?>
                            <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn-pay">
                                <i class="fas fa-credit-card"></i> Complete Payment
                            </a>
                        <?php endif; ?>
                        
                        <?php if($booking['payment_status'] == 'paid' && $booking['status'] == 'confirmed'): ?>
                            <a href="invoice.php?id=<?php echo $booking['id']; ?>" class="btn-invoice">
                                <i class="fas fa-download"></i> Download Invoice
                            </a>
                        <?php endif; ?>
                        
                        <!-- Cancel button for all active bookings -->
                        <button class="btn-cancel" onclick="confirmCancel(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_ref']); ?>')">
                            <i class="fas fa-times"></i> Cancel Booking
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="cancel-confirm-modal">
        <div class="cancel-modal-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Cancel Booking</h3>
            <p id="cancelMessage">Are you sure you want to cancel this booking?</p>
            <p style="font-size: 0.85rem; color: #64748b; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> This action cannot be undone. The spot will become available for others.
            </p>
            <div class="cancel-modal-buttons">
                <button class="btn-cancel-modal" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i> No, Keep It
                </button>
                <button class="btn-confirm-cancel" id="confirmCancelBtn">
                    <i class="fas fa-check"></i> Yes, Cancel Booking
                </button>
            </div>
        </div>
    </div>

    <script>
        let cancelBookingId = null;

        function confirmCancel(bookingId, bookingRef) {
            cancelBookingId = bookingId;
            document.getElementById('cancelMessage').innerHTML = `Are you sure you want to cancel booking <strong>${bookingRef}</strong>?`;
            document.getElementById('cancelModal').style.display = 'flex';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            cancelBookingId = null;
        }

        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            if (cancelBookingId) {
                window.location.href = `my_bookings.php?cancel_id=${cancelBookingId}`;
            }
        });

        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeCancelModal();
            }
        }

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>