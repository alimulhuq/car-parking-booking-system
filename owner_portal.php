<?php require_once 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner with ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .partner-container {
            max-width: 700px;
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
        }
        .btn-primary {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 48px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
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
    </style>
</head>
<body>
<header class="main-header">
    <div class="container header-flex">
        <div class="logo-area">
            <i class="fas fa-parking"></i>
            <span>Park<span class="logo-bold">Ease</span></span>
        </div>
        <div class="header-cta">
            <a href="index.php" class="btn-outline-light"><i class="fas fa-home"></i> Home</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="btn-outline-light"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php" class="btn-outline-light"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-outline-light"><i class="fas fa-sign-in-alt"></i> Login</a>
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
    
    <?php if($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div style="background: #dcfce7; color: #16a34a; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> Thank you! Our team will contact you within 24 hours.
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="fullname" required>
        </div>
        
        <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" name="phone" required>
        </div>
        
        <div class="form-group">
            <label>Parking Location / Address *</label>
            <input type="text" name="address" required placeholder="Street, city, zip code">
        </div>
        
        <div class="form-group">
            <label>Number of Parking Spots *</label>
            <input type="number" name="spots" required min="1">
        </div>
        
        <div class="form-group">
            <label>Parking Type</label>
            <select name="type">
                <option value="garage">Indoor Garage</option>
                <option value="outdoor">Outdoor Lot</option>
                <option value="covered">Covered Parking</option>
                <option value="street">Street Parking</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Additional Information</label>
            <textarea name="info" rows="4" placeholder="Any special features or notes..."></textarea>
        </div>
        
        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
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
</div>
</body>
</html>