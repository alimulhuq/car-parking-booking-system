<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$error = '';
$success = false;
$booking = null;

// If no booking_id, try to get the most recent pending booking
if ($booking_id == 0) {
    $stmt = $pdo->prepare("
        SELECT id FROM bookings 
        WHERE user_id = ? AND payment_status = 'pending' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $recent = $stmt->fetch();
    if ($recent) {
        $booking_id = $recent['id'];
    }
}

// Get booking details
if ($booking_id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, v.vehicle_number, v.vehicle_model 
        FROM bookings b 
        LEFT JOIN vehicles v ON b.vehicle_id = v.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $error = "Booking not found.";
    } elseif ($booking['payment_status'] == 'paid') {
        $error = "This booking has already been paid for.";
        header("Location: my_bookings.php");
        exit();
    }
} else {
    $error = "No booking selected. Please make a booking first.";
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'credit_card';
    
    // Map payment method to valid values
    $payment_method_map = [
        'card' => 'credit_card',
        'paypal' => 'paypal',
        'wallet' => 'wallet'
    ];
    $db_payment_method = isset($payment_method_map[$payment_method]) ? $payment_method_map[$payment_method] : 'credit_card';
    
    // Simple validation
    if ($payment_method == 'card') {
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $card_name = $_POST['card_name'] ?? '';
        $card_expiry = $_POST['card_expiry'] ?? '';
        $card_cvv = $_POST['card_cvv'] ?? '';
        
        if (empty($card_number) || strlen($card_number) < 15) {
            $error = 'Please enter a valid card number';
        } elseif (empty($card_name)) {
            $error = 'Please enter the name on card';
        } elseif (empty($card_expiry)) {
            $error = 'Please enter card expiry date';
        } elseif (empty($card_cvv)) {
            $error = 'Please enter CVV';
        }
    }
    
    if (empty($error)) {
        // Generate transaction ID
        $transaction_id = 'TXN' . time() . rand(1000, 9999);
        $card_last_four = ($payment_method == 'card' && isset($card_number)) ? substr(preg_replace('/\s+/', '', $card_number), -4) : null;
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update booking as paid
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET payment_status = 'paid', 
                    payment_method = ?,
                    payment_method_type = ?,
                    transaction_id = ?,
                    card_last_four = ?,
                    status = 'confirmed'
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$db_payment_method, $payment_method, $transaction_id, $card_last_four, $booking_id, $user_id]);
            
            // Add payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (booking_id, user_id, amount, payment_method, card_last_four, transaction_id, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'successful')
            ");
            $stmt->execute([$booking_id, $user_id, $booking['amount'], $db_payment_method, $card_last_four, $transaction_id]);
            
            // Add notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read) 
                VALUES (?, ?, ?, 'payment', 0)
            ");
            $message = "Payment of $" . number_format($booking['amount'], 2) . " for booking {$booking['booking_ref']} was successful.";
            $stmt->execute([$user_id, 'Payment Successful', $message]);
            
            // Commit transaction
            $pdo->commit();
            $success = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Payment processing failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | ParkEase</title>
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
            padding: 40px 20px;
        }
        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
        }
        .payment-summary {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .payment-form {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .summary-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #eef2ff;
            margin-bottom: 20px;
        }
        .summary-header h2 {
            color: #1e293b;
            margin-top: 10px;
        }
        .booking-details {
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eef2ff;
        }
        .detail-label {
            color: #64748b;
            font-weight: 500;
        }
        .detail-value {
            font-weight: 600;
            color: #1e293b;
        }
        .amount-total {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .total-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: #16a34a;
        }
        .payment-methods {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .payment-method {
            flex: 1;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-method:hover {
            border-color: #2563eb;
            background: #f8fafc;
        }
        .payment-method.selected {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .payment-method i {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #334155;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .card-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 48px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 20px;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37,99,235,0.3);
        }
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .success-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .success-icon {
            font-size: 5rem;
            color: #16a34a;
            margin-bottom: 20px;
        }
        .btn-back {
            display: inline-block;
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 40px;
            margin-top: 20px;
        }
        .spot-badge {
            background: #2563eb;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .payment-container {
                grid-template-columns: 1fr;
            }
            .card-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php if($success): ?>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>Payment Successful!</h2>
        <p style="color: #64748b; margin: 15px 0;">Your booking has been confirmed.</p>
        <div style="background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0;">
            <p><strong>Booking Reference:</strong> <?php echo htmlspecialchars($booking['booking_ref']); ?></p>
            <p><strong>Spot Number:</strong> <?php echo htmlspecialchars($booking['spot_number']); ?></p>
            <p><strong>Amount Paid:</strong> $<?php echo number_format($booking['amount'], 2); ?></p>
            <p><strong>Transaction ID:</strong> <?php echo $transaction_id; ?></p>
        </div>
        <a href="my_bookings.php" class="btn-back">View My Bookings</a>
        <a href="index.php" style="display: block; margin-top: 15px; color: #2563eb; text-decoration: none;">← Back to Home</a>
    </div>
<?php elseif($error && !$booking): ?>
    <div class="success-container" style="background: white;">
        <div class="success-icon" style="color: #dc2626;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2>Error</h2>
        <p style="color: #64748b; margin: 15px 0;"><?php echo htmlspecialchars($error); ?></p>
        <a href="index.php" class="btn-back">Back to Home</a>
        <a href="my_bookings.php" style="display: block; margin-top: 15px; color: #2563eb; text-decoration: none;">View My Bookings</a>
    </div>
<?php elseif($booking): ?>
<div class="payment-container">
    <!-- Payment Summary -->
    <div class="payment-summary">
        <div class="summary-header">
            <i class="fas fa-receipt" style="font-size: 2rem; color: #2563eb;"></i>
            <h2>Payment Summary</h2>
        </div>
        
        <div class="booking-details">
            <div class="detail-row">
                <span class="detail-label">Booking ID</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['booking_ref']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['location_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Spot Number</span>
                <span class="detail-value"><span class="spot-badge"><?php echo htmlspecialchars($booking['spot_number']); ?></span></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time</span>
                <span class="detail-value">
                    <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?><br>
                    <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duration</span>
                <span class="detail-value"><?php echo $booking['duration_hours']; ?> hours</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Vehicle</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['vehicle_number']); ?></span>
            </div>
        </div>
        
        <div class="amount-total">
            <div class="detail-row" style="border-bottom: none;">
                <span class="detail-label" style="font-size: 1.1rem; font-weight: 700;">Total Amount</span>
                <span class="total-price">$<?php echo number_format($booking['amount'], 2); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Payment Form -->
    <div class="payment-form">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-credit-card"></i> Select Payment Method</h2>
        
        <?php if($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="paymentForm">
            <div class="payment-methods">
                <div class="payment-method" onclick="selectPaymentMethod('card')" id="method-card">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit/Debit Card</span>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod('paypal')" id="method-paypal">
                    <i class="fab fa-paypal"></i>
                    <span>PayPal</span>
                </div>
                <div class="payment-method" onclick="selectPaymentMethod('wallet')" id="method-wallet">
                    <i class="fas fa-wallet"></i>
                    <span>Digital Wallet</span>
                </div>
            </div>
            
            <input type="hidden" name="payment_method" id="payment_method" value="card">
            
            <div id="card-fields">
                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" onkeyup="formatCardNumber(this)">
                </div>
                
                <div class="form-group">
                    <label>Name on Card</label>
                    <input type="text" name="card_name" placeholder="JOHN DOE">
                </div>
                
                <div class="card-row">
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5" onkeyup="formatExpiry(this)">
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="password" name="card_cvv" placeholder="123" maxlength="4">
                    </div>
                </div>
            </div>
            
            <div id="paypal-fields" style="display: none;">
                <div class="form-group">
                    <label>PayPal Email</label>
                    <input type="email" name="paypal_email" placeholder="your@email.com">
                </div>
                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; text-align: center;">
                    <i class="fab fa-paypal" style="font-size: 2rem; color: #003087;"></i>
                    <p style="margin-top: 10px;">You will be redirected to PayPal to complete payment</p>
                </div>
            </div>
            
            <div id="wallet-fields" style="display: none;">
                <div class="form-group">
                    <label>Wallet Number/ID</label>
                    <input type="text" name="wallet_id" placeholder="Enter your wallet ID">
                </div>
                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-wallet" style="font-size: 2rem; color: #2563eb;"></i>
                    <p style="margin-top: 10px;">Google Pay, Apple Pay, or other digital wallets accepted</p>
                </div>
            </div>
            
            <button type="submit" class="btn-pay">
                <i class="fas fa-lock"></i> Pay $<?php echo number_format($booking['amount'], 2); ?>
            </button>
        </form>
        
        <p style="text-align: center; margin-top: 20px; font-size: 0.85rem; color: #64748b;">
            <i class="fas fa-shield-alt"></i> Secure payment processing. Your information is protected.
        </p>
    </div>
</div>

<script>
function selectPaymentMethod(method) {
    document.getElementById('payment_method').value = method;
    
    const methods = ['card', 'paypal', 'wallet'];
    methods.forEach(m => {
        const element = document.getElementById(`method-${m}`);
        if (element) {
            if (m === method) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }
    });
    
    const cardFields = document.getElementById('card-fields');
    const paypalFields = document.getElementById('paypal-fields');
    const walletFields = document.getElementById('wallet-fields');
    
    if (cardFields) cardFields.style.display = method === 'card' ? 'block' : 'none';
    if (paypalFields) paypalFields.style.display = method === 'paypal' ? 'block' : 'none';
    if (walletFields) walletFields.style.display = method === 'wallet' ? 'block' : 'none';
}

function formatCardNumber(input) {
    let value = input.value.replace(/\D/g, '');
    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) formatted += ' ';
        formatted += value[i];
    }
    input.value = formatted.substring(0, 19);
}

function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
    } else {
        input.value = value;
    }
}

// Set default selected method
selectPaymentMethod('card');
</script>
<?php endif; ?>
</body>
</html>