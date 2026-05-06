<?php 
    require_once 'config/db.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>ParkEase | Smart Parking Booking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-wrapper">
        <header class="main-header">
            <div class="container header-flex">
                <div class="logo-area">
                    <i class="fas fa-parking"></i>
                    <span>Park<span class="logo-bold">Ease</span></span>
                </div>
                <nav class="main-nav">
                    <a href="index.php" class="active"><i class="fas fa-map-marker-alt"></i> Find Parking</a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="my_bookings.php"><i class="fas fa-history"></i> My Bookings</a>
                        <a href="profile.php" class="profile-link">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                    <?php endif; ?>
                </nav>
                <div class="header-cta">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="logout.php" class="btn-outline-light"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn-outline-light"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="btn-outline-light" style="margin-left: 10px;"><i class="fas fa-user-plus"></i> Sign Up</a>
                    <?php endif; ?>
                    <a href="owner_portal.php" class="btn-outline-light" style="margin-left: 10px;"><i class="fas fa-building"></i> List Your Parking</a>
                </div>
            </div>
        </header>

        <section class="hero-section">
            <div class="container hero-content">
                <h1>Find & secure your perfect spot</h1>
                <p class="hero-sub">From indoor garages to secure street parking — instant booking, no stress.</p>
                <div class="search-card">
                    <form id="searchForm" action="search_results.php" method="GET" class="search-form">
                        <div class="search-group">
                            <i class="fas fa-location-dot"></i>
                            <input type="text" id="locationInput" name="location" placeholder="City, airport, or landmark..." value="Downtown" required>
                        </div>
                        <div class="search-group small">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="checkin" name="checkin" required>
                        </div>
                        <div class="search-group small">
                            <i class="fas fa-clock"></i>
                            <input type="time" id="startTime" name="start_time" value="09:00" required>
                        </div>
                        <div class="search-group small">
                            <i class="fas fa-hourglass-end"></i>
                            <input type="time" id="endTime" name="end_time" value="18:00" required>
                        </div>
                        <button type="submit" class="btn-primary btn-search"><i class="fas fa-search"></i> Search spots</button>
                    </form>
                </div>
                <div class="trust-badge">
                    <span><i class="fas fa-check-circle"></i> 90+ countries</span>
                    <span><i class="fas fa-map"></i> 20,000+ cities</span>
                    <span><i class="fas fa-parking"></i> 89M+ parking spots</span>
                </div>
            </div>
        </section>

        <section class="featured-section">
            <div class="container">
                <div class="section-head">
                    <h2>Popular parking near you</h2>
                    <p>Real-time availability & best rates guaranteed</p>
                </div>
                <div class="parking-grid" id="parkingGrid">
                    <?php include_once 'includes/featured_spots.php'; ?>
                </div>
            </div>
        </section>

        <section class="how-it-works">
            <div class="container">
                <div class="section-head center">
                    <h2>Book in three simple steps</h2>
                    <p>Transparent, fast & reliable — drive straight to your spot</p>
                </div>
                <div class="steps">
                    <div class="step-card">
                        <div class="step-icon"><i class="fas fa-search-location"></i></div>
                        <h3>1. Search</h3>
                        <p>Enter location, date & time — see live options.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-icon"><i class="fas fa-credit-card"></i></div>
                        <h3>2. Reserve & pay</h3>
                        <p>Secure your slot with safe online payment.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-icon"><i class="fas fa-qrcode"></i></div>
                        <h3>3. Park & relax</h3>
                        <p>Access QR code / PIN to enter garage.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="partner-cta">
            <div class="container partner-flex">
                <div class="partner-text">
                    <h3>Own a parking facility? <span>Increase revenue</span></h3>
                    <p>Join the largest parking network & attract thousands of drivers daily.</p>
                    <a href="owner_portal.php" class="btn-light">Become a partner →</a>
                </div>
                <div class="partner-stats">
                    <div><span>90+</span> Countries</div>
                    <div><span>2.5M+</span> Monthly bookings</div>
                </div>
            </div>
        </section>

        <footer class="main-footer">
            <div class="container footer-inner">
                <div class="footer-copyright">
                    <i class="fas fa-parking"></i> ParkEase © 2025 — smart parking reinvented.
                </div>
                <div class="footer-links">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="#">Support</a>
                </div>
            </div>
        </footer>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>