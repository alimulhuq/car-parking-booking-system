<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT b.*, pl.name as location_name, pl.address, v.vehicle_number, v.vehicle_model 
    FROM bookings b 
    LEFT JOIN parking_locations pl ON b.parking_location_id = pl.id
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: my_bookings.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice #<?php echo $booking['booking_ref']; ?> | ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            padding: 40px;
        }
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.1);
        }
        .invoice-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #eef2ff;
            margin-bottom: 30px;
        }
        .invoice-title {
            font-size: 2rem;
            color: #2563eb;
        }
        .invoice-details {
            margin: 30px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #eef2ff;
        }
        .total {
            font-size: 1.3rem;
            font-weight: 700;
            color: #16a34a;
        }
        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="invoice">
    <div class="invoice-header">
        <i class="fas fa-parking" style="font-size: 3rem; color: #2563eb;"></i>
        <h1 class="invoice-title">ParkEase Invoice</h1>
        <p>Booking Reference: <?php echo $booking['booking_ref']; ?></p>
    </div>
    
    <div class="invoice-details">
        <div class="row">
            <strong>Issue Date:</strong>
            <span><?php echo date('F j, Y'); ?></span>
        </div>
        <div class="row">
            <strong>Customer Name:</strong>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
        <div class="row">
            <strong>Parking Location:</strong>
            <span><?php echo htmlspecialchars($booking['location_name']); ?></span>
        </div>
        <div class="row">
            <strong>Spot Number:</strong>
            <span><?php echo htmlspecialchars($booking['spot_number']); ?></span>
        </div>
        <div class="row">
            <strong>Date:</strong>
            <span><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
        </div>
        <div class="row">
            <strong>Time:</strong>
            <span><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
        </div>
        <div class="row">
            <strong>Duration:</strong>
            <span><?php echo $booking['duration_hours']; ?> hours</span>
        </div>
        <div class="row">
            <strong>Vehicle:</strong>
            <span><?php echo htmlspecialchars($booking['vehicle_number']); ?></span>
        </div>
        <div class="row">
            <strong>Payment Method:</strong>
            <span><?php echo ucfirst($booking['payment_method']); ?></span>
        </div>
        <div class="row">
            <strong>Transaction ID:</strong>
            <span><?php echo htmlspecialchars($booking['transaction_id']); ?></span>
        </div>
        <div class="row total">
            <strong>Total Amount Paid:</strong>
            <span>$<?php echo number_format($booking['amount'], 2); ?></span>
        </div>
    </div>
    
    <button onclick="window.print()" class="btn-print no-print"><i class="fas fa-print"></i> Print Invoice</button>
    <a href="my_bookings.php" class="btn-print no-print" style="background: #64748b; text-decoration: none; margin-left: 10px;">Back to Bookings</a>
</div>
</body>
</html>