<?php
require_once 'config/db.php';

$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $spots = intval($_POST['spots'] ?? 0);
    $parking_type = $_POST['type'] ?? 'garage';
    $additional_info = trim($_POST['info'] ?? '');
    $price_per_hour = floatval($_POST['price_per_hour'] ?? 3.50);
    $location_name = trim($_POST['location_name'] ?? '');
    
    // Validate inputs
    if (empty($fullname) || empty($email) || empty($phone) || empty($address) || $spots <= 0) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            // Generate a unique parking location name if not provided
            if (empty($location_name)) {
                $location_name = $fullname . "'s Parking";
            }
            
            // Insert into parking_locations table
            $stmt = $pdo->prepare("
                INSERT INTO parking_locations (
                    name, 
                    address, 
                    total_spots, 
                    available_spots, 
                    price_per_hour, 
                    features, 
                    is_active,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $features = ucfirst($parking_type) . " parking";
            if (!empty($additional_info)) {
                $features .= " · " . substr($additional_info, 0, 100);
            }
            
            $stmt->execute([
                $location_name,
                $address,
                $spots,
                $spots, // available_spots starts equal to total_spots
                $price_per_hour,
                $features
            ]);
            
            $location_id = $pdo->lastInsertId();
            
            // Insert parking spots for this location
            $spotStmt = $pdo->prepare("
                INSERT INTO parking_spots (parking_location_id, spot_number, spot_type, is_available)
                VALUES (?, ?, 'standard', 1)
            ");
            
            // Generate spots (A1, A2, B1, B2, etc.)
            $prefixes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            $spots_per_prefix = 4;
            $spot_count = 0;
            
            for ($p = 0; $p < count($prefixes) && $spot_count < $spots; $p++) {
                for ($s = 1; $s <= $spots_per_prefix && $spot_count < $spots; $s++) {
                    $spot_number = $prefixes[$p] . $s;
                    $spotStmt->execute([$location_id, $spot_number]);
                    $spot_count++;
                }
            }
            
            // If user is logged in, also store as their owned parking
            if (isset($_SESSION['user_id'])) {
                // You could create a owner_parking table here
                // For now, just add a notification
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read) 
                    VALUES (?, ?, ?, 'system', 0)
                ");
                $notifStmt->execute([
                    $_SESSION['user_id'],
                    'Parking Location Added',
                    "Your parking location '{$location_name}' has been successfully listed on ParkEase!"
                ]);
            }
            
            $success_msg = "Thank you! Your parking location '{$location_name}' has been successfully listed. We will review it within 24 hours.";
            
            // Clear form data
            $_POST = array();
            
        } catch (Exception $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch existing parking locations to show as examples
$existing_locations = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM parking_locations ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $existing_locations = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner with ParkEase | List Your Parking Space</title>
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

        .partner-container {
            max-width: 800px;
            margin: 60px auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);
        }

        .partner-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .partner-header h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-top: 10px;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 48px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37,99,235,0.3);
        }

        .benefits {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            margin-top: 30px;
        }

        .benefits ul {
            list-style: none;
            padding: 0;
        }

        .benefits li {
            padding: 8px 0;
            color: #475569;
        }

        .benefits i {
            color: #16a34a;
            margin-right: 10px;
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
        }

        .price-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-input-group input {
            flex: 1;
        }

        .price-input-group span {
            color: #64748b;
        }

        .recent-listings {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eef2ff;
        }

        .recent-listings h3 {
            margin-bottom: 20px;
            color: #1e293b;
        }

        .listing-item {
            padding: 10px 0;
            border-bottom: 1px solid #eef2ff;
            color: #475569;
        }

        .listing-item strong {
            color: #2563eb;
        }

        @media (max-width: 768px) {
            .partner-container {
                margin: 20px;
                padding: 20px;
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
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="profile-link">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </a>
            <?php endif; ?>
        </nav>
        <div class="header-cta">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="logout.php" class="btn-outline-light"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-outline-light"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="register.php" class="btn-outline-light"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="partner-container">
    <div class="partner-header">
        <i class="fas fa-handshake" style="font-size: 3rem; color: #2563eb;"></i>
        <h1>List Your Parking Space</h1>
        <p style="color: #64748b;">Join thousands of owners earning extra revenue</p>
    </div>
    
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
    
    <form method="POST">
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="fullname" required value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Parking Location Name *</label>
            <input type="text" name="location_name" placeholder="e.g., Downtown Smart Parking" value="<?php echo htmlspecialchars($_POST['location_name'] ?? ''); ?>">
            <small style="color: #64748b;">Leave blank to use your name</small>
        </div>
        
        <div class="form-group">
            <label>Parking Location / Address *</label>
            <input type="text" name="address" required placeholder="Street, city, zip code" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Number of Parking Spots *</label>
            <input type="number" name="spots" required min="1" max="500" value="<?php echo htmlspecialchars($_POST['spots'] ?? 10); ?>">
            <small style="color: #64748b;">Minimum 1, Maximum 500 spots</small>
        </div>
        
        <div class="form-group">
            <label>Price per Hour ($) *</label>
            <div class="price-input-group">
                <input type="number" name="price_per_hour" step="0.50" min="1" max="50" required value="<?php echo htmlspecialchars($_POST['price_per_hour'] ?? 3.50); ?>">
                <span>/ hour</span>
            </div>
        </div>
        
        <div class="form-group">
            <label>Parking Type</label>
            <select name="type">
                <option value="garage">Indoor Garage</option>
                <option value="outdoor">Outdoor Lot</option>
                <option value="covered">Covered Parking</option>
                <option value="street">Street Parking</option>
                <option value="basement">Basement Parking</option>
                <option value="rooftop">Rooftop Parking</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Additional Information</label>
            <textarea name="info" rows="4" placeholder="Any special features or notes... (e.g., 24/7 security, EV charging, CCTV, etc.)"><?php echo htmlspecialchars($_POST['info'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" name="submit_request" class="btn-primary">
            <i class="fas fa-paper-plane"></i> Submit & List Parking
        </button>
    </form>
    
    <div class="benefits">
        <h3><i class="fas fa-gem"></i> Why partner with us?</h3>
        <ul>
            <li><i class="fas fa-check-circle"></i> Free listing - pay only when booked</li>
            <li><i class="fas fa-chart-line"></i> Reach thousands of daily drivers</li>
            <li><i class="fas fa-shield-alt"></i> Secure payment processing</li>
            <li><i class="fas fa-headset"></i> 24/7 dedicated support</li>
            <li><i class="fas fa-chart-simple"></i> Real-time analytics dashboard</li>
        </ul>
    </div>
    
    <?php if(!empty($existing_locations)): ?>
    <div class="recent-listings">
        <h3><i class="fas fa-building"></i> Recently Added Parking Locations</h3>
        <?php foreach($existing_locations as $loc): ?>
            <div class="listing-item">
                <strong><?php echo htmlspecialchars($loc['name']); ?></strong><br>
                📍 <?php echo htmlspecialchars(substr($loc['address'], 0, 60)); ?>...<br>
                🅿️ <?php echo $loc['total_spots']; ?> spots | 💰 $<?php echo number_format($loc['price_per_hour'], 2); ?>/hour
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>