<?php
    require_once 'config/db.php';

    $location = isset($_GET['location']) ? htmlspecialchars(trim($_GET['location'])) : 'Downtown';
    $checkin = isset($_GET['checkin']) ? $_GET['checkin'] : date('Y-m-d');
    $start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '09:00';
    $end_time = isset($_GET['end_time']) ? $_GET['end_time'] : '18:00';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
        $checkin = date('Y-m-d');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
        $start_time = '09:00';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $end_time = '18:00';
    }

    $parking_locations = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM parking_locations WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        $parking_locations = $stmt->fetchAll();
        
        if (empty($parking_locations)) {
            $demo_locations = [
                ['name' => 'Central Plaza Garage', 'address' => '100 Central Plaza, Downtown, NY', 'total_spots' => 150, 'price_per_hour' => 3.50, 'features' => '24/7 security, EV charging, CCTV', 'is_active' => 1],
                ['name' => 'Harbor View Parking', 'address' => '500 Harbor Drive, CA', 'total_spots' => 80, 'price_per_hour' => 2.90, 'features' => 'Ocean view, Security patrol', 'is_active' => 1],
                ['name' => 'Grand Station Underground', 'address' => '200 Station Road, Chicago, IL', 'total_spots' => 200, 'price_per_hour' => 4.20, 'features' => 'Direct station access, Elevators', 'is_active' => 1],
                ['name' => 'Airport Express Parking', 'address' => '1000 Airport Blvd, Miami, FL', 'total_spots' => 300, 'price_per_hour' => 5.00, 'features' => 'Free shuttle, 24/7 service', 'is_active' => 1]
            ];
            
            foreach ($demo_locations as $loc) {
                $stmt = $pdo->prepare("INSERT INTO parking_locations (name, address, total_spots, available_spots, price_per_hour, features, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $available = $loc['total_spots'];
                $stmt->execute([$loc['name'], $loc['address'], $loc['total_spots'], $available, $loc['price_per_hour'], $loc['features'], $loc['is_active']]);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM parking_locations WHERE is_active = 1 ORDER BY name");
            $stmt->execute();
            $parking_locations = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $parking_locations = [
            ['id' => 1, 'name' => 'Central Plaza Garage', 'address' => '100 Central Plaza, Downtown, NY', 'total_spots' => 150, 'available_spots' => 95, 'price_per_hour' => 3.50, 'features' => '24/7 security, EV charging, CCTV'],
            ['id' => 2, 'name' => 'Harbor View Parking', 'address' => '500 Harbor Drive, CA', 'total_spots' => 80, 'available_spots' => 42, 'price_per_hour' => 2.90, 'features' => 'Ocean view, Security patrol'],
            ['id' => 3, 'name' => 'Grand Station Underground', 'address' => '200 Station Road, Chicago, IL', 'total_spots' => 200, 'available_spots' => 120, 'price_per_hour' => 4.20, 'features' => 'Direct station access, Elevators'],
            ['id' => 4, 'name' => 'Airport Express Parking', 'address' => '1000 Airport Blvd, Miami, FL', 'total_spots' => 300, 'available_spots' => 180, 'price_per_hour' => 5.00, 'features' => 'Free shuttle, 24/7 service']
        ];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results | ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: 
            border-box; 
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
            max-width: 1200px; margin: 0 auto; padding: 0 20px; }
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
            border: 1.5px solid #2563eb; color: #2563eb; 
            font-weight: 500; 
            transition: 0.2s; 
            display: inline-block; 
            background: white; 
        }
        .btn-outline-light:hover { 
            background: #2563eb; 
            color: white; 
        }
        
        .results-card {
            background: white;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .results-card:hover { 
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.15); 
            transform: translateY(-2px); 
        }
        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eef2ff;
        }
        .location-name { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: #1e293b; 
        }
        .price-hour { 
            font-size: 1.8rem; 
            font-weight: 800; 
            color: #2563eb; 
        }
        .location-features { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin: 15px 0; 
            color: #64748b; 
            font-size: 0.9rem; 
        }
        .spots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            max-height: 400px;
            overflow-y: auto;
            min-height: 200px;
        }
        .spot-item {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .spot-item:hover:not(.booked):not(.locked) { 
            border-color: #2563eb; 
            transform: scale(1.02); 
            background: #eff6ff; 
        }
        .spot-item.selected { 
            background: #2563eb; 
            border-color: #2563eb; 
            color: white; 
        }
        .spot-item.booked { 
            opacity: 0.5; 
            cursor: not-allowed; 
            background: #fee2e2;
            border-color: #dc2626;
        }
        .spot-item.booked:after {
            content: "BOOKED";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.65rem;
            font-weight: 700;
            color: #dc2626;
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }
        .spot-item.locked {
            opacity: 0.6;
            cursor: wait;
            background: #fef3c7;
            border-color: #f59e0b;
            position: relative;
        }
        .spot-item.locked:after {
            content: "🔒 LOCKED";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.65rem;
            font-weight: 700;
            color: #d97706;
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }
        .spot-number { 
            font-size: 1.1rem; 
            font-weight: 700; 
            display: 
            block; 
        }
        .spot-type { 
            font-size: 0.7rem; 
            margin-top: 5px; 
            display: block; 
            color: #64748b; 
        }
        .spot-item.selected .spot-type { 
            color: rgba(255,255,255,0.9); 
        }
        .availability-badge { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            margin-left: 10px;
        }
        .availability-high { 
            background: #dcfce7; 
            color: #16a34a; 
        }
        .availability-medium { 
            background: #fef3c7; 
            color: #d97706; 
        }
        .availability-low { 
            background: #fee2e2; 
            color: #dc2626; 
        }
        .refresh-btn { 
            background: #f1f5f9; 
            border: 1px solid #e2e8f0; 
            padding: 8px 16px; 
            border-radius: 40px; 
            cursor: pointer; 
            font-size: 0.85rem; 
        }
        .search-summary { 
            background: white; 
            padding: 20px; 
            border-radius: 16px; 
            margin-bottom: 30px; 
        }
        .selected-info-card {
            background: #dcfce7;
            padding: 15px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid #16a34a;
        }
        .btn-proceed {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
            transition: 0.2s;
        }
        .btn-proceed:hover { 
            background: #1d4ed8; 
            transform: scale(0.98); 
        }
        @media (max-width: 768px) {
            .spots-grid { 
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); 
            }
            .location-header { 
                flex-direction: column; 
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

    <section style="padding: 40px 0;">
        <div class="container">
            <div class="search-summary">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h2>
                            <i class="fas fa-search"></i> Available Parking for "
                            <strong>
                                <?php echo htmlspecialchars($location); ?>
                            </strong>"
                        </h2>
                        <p>
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($checkin)); ?> | <i class="fas fa-clock"></i>
                            <?php echo date('g:i A', strtotime($start_time)); ?> - 
                            <?php echo date('g:i A', strtotime($end_time)); ?>
                        </p>
                    </div>
                    <button class="refresh-btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
            </div>
            
            <?php if(empty($parking_locations)): ?>
                <div style="text-align: center; padding: 60px; background: white; border-radius: 24px;">
                    <i class="fas fa-map-marker-alt" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <h3 style="margin-top: 20px;">No parking locations found</h3>
                    <p style="color: #64748b;">Please try a different search or check back later.</p>
                    <a href="index.php" class="btn-outline-light" style="margin-top: 20px; display: inline-block;">Back to Home</a>
                </div>
            <?php else: ?>
                <div id="parking-results">
                    <?php foreach($parking_locations as $index => $loc): 
                        $available_spots = isset($loc['available_spots']) ? $loc['available_spots'] : $loc['total_spots'];
                        $availability_class = $available_spots > 50 ? 'availability-high' : ($available_spots > 20 ? 'availability-medium' : 'availability-low');
                    ?>
                    <div class="results-card" data-location-id="<?php echo $loc['id']; ?>">
                        <div class="location-header">
                            <div>
                                <div class="location-name"><i class="fas fa-building"></i> <?php echo htmlspecialchars($loc['name']); ?></div>
                                <div class="location-features">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($loc['address']); ?></span>
                                    <?php if(!empty($loc['features'])): ?>
                                    <span><i class="fas fa-star"></i> <?php echo htmlspecialchars($loc['features']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div class="price-hour">$<?php echo number_format($loc['price_per_hour'], 2); ?><span style="font-size: 0.9rem;">/hour</span></div>
                                <span class="availability-badge <?php echo $availability_class; ?>"><i class="fas fa-car"></i> <?php echo $available_spots; ?> spots available</span>
                            </div>
                        </div>
                        
                        <h4 style="margin-bottom: 10px;"><i class="fas fa-parking"></i> Select a Parking Spot:</h4>
                        <div class="spots-grid" id="spots-grid-<?php echo $loc['id']; ?>">
                            <?php
                                $spotTypes = ['standard', 'standard', 'standard', 'standard', 'ev', 'handicap', 'vip', 'compact'];
                                $prefixes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                
                                for ($i = 1; $i <= 32; $i++) {
                                    $prefixIndex = floor(($i - 1) / 4);
                                    $prefix = $prefixes[$prefixIndex % count($prefixes)];
                                    $number = (($i - 1) % 4) + 1;
                                    $spotNum = $prefix . $number;
                                    $spotType = $spotTypes[($i - 1) % count($spotTypes)];
                                    
                                    $typeIcon = '';
                                    $typeLabel = '';
                                    if ($spotType == 'ev') {
                                        $typeIcon = '<i class="fas fa-charging-station"></i>';
                                        $typeLabel = 'EV';
                                    } 
                                    elseif ($spotType == 'handicap') {
                                        $typeIcon = '<i class="fas fa-wheelchair"></i>';
                                        $typeLabel = 'HC';
                                    } 
                                    elseif ($spotType == 'vip') {
                                        $typeIcon = '<i class="fas fa-crown"></i>';
                                        $typeLabel = 'VIP';
                                    } 
                                    elseif ($spotType == 'compact') {
                                        $typeIcon = '<i class="fas fa-car-side"></i>';
                                        $typeLabel = 'Cmp';
                                    } 
                                    else {
                                        $typeIcon = '<i class="fas fa-parking"></i>';
                                        $typeLabel = 'Std';
                                    }
                                ?>
                                <div class="spot-item" 
                                data-spot-number="<?php echo $spotNum; ?>" 
                                data-spot-type="<?php echo $spotType; ?>" 
                                data-location-id="<?php echo $loc['id']; ?>" 
                                data-location-name="<?php echo htmlspecialchars($loc['name']); ?>" 
                                data-price="<?php echo $loc['price_per_hour']; ?>" 
                                data-date="<?php echo $checkin; ?>" 
                                data-start="<?php echo $start_time; ?>" 
                                data-end="<?php echo $end_time; ?>">
                                    <span class="spot-number">
                                        <?php echo $spotNum; ?>
                                    </span>
                                    <span class="spot-type">
                                        <?php echo $typeIcon; ?> <?php echo $typeLabel; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        
                        <div id="selected-info-<?php echo $loc['id']; ?>" style="margin-top: 15px;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
    let selectedSpotData = null;
    let isLoading = false;

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded, adding click handlers to spots...');
        
        const allSpots = document.querySelectorAll('.spot-item');
        console.log('Found ' + allSpots.length + ' spots');
        
        allSpots.forEach(function(spot) {
            spot.addEventListener('click', function() {
                selectSpot(this);
            });
        });
        
        checkAllSpotsAvailability();
        
        setInterval(function() {
            console.log('Refreshing spot availability...');
            checkAllSpotsAvailability();
        }, 30000);
    });

    async function checkSpotAvailability(locationId, spotNumber, date, startTime, endTime) {
        try {
            const response = await fetch('check_availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `location_id=${locationId}&spot_number=${spotNumber}&date=${date}&start_time=${startTime}&end_time=${endTime}`
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error checking availability:', error);
            return { available: true };
        }
    }

    async function updateSpotAvailability(spotElement) {
        const locationId = spotElement.getAttribute('data-location-id');
        const spotNumber = spotElement.getAttribute('data-spot-number');
        const date = spotElement.getAttribute('data-date');
        const startTime = spotElement.getAttribute('data-start');
        const endTime = spotElement.getAttribute('data-end');
        
        const availability = await checkSpotAvailability(locationId, spotNumber, date, startTime, endTime);
        
        if (!availability.available) {
            if (availability.reason === 'already_booked') {
                spotElement.classList.add('booked');
                spotElement.classList.remove('locked', 'selected');
            } else if (availability.reason === 'locked_by_other') {
                spotElement.classList.add('locked');
                spotElement.classList.remove('booked', 'selected');
            }
        } else {
            spotElement.classList.remove('booked', 'locked');
        }
    }

    async function checkAllSpotsAvailability() {
        const allSpots = document.querySelectorAll('.spot-item');
        for (const spot of allSpots) {
            await updateSpotAvailability(spot);
        }
    }

    async function selectSpot(spotElement) {
        <?php if(!isset($_SESSION['user_id'])): ?>
        alert('Please login to book a parking spot.');
        window.location.href = 'login.php?redirect=search_results.php';
        return;
        <?php endif; ?>
        
        if (spotElement.classList.contains('booked')) {
            alert('❌ This spot is already booked. Please select another spot.');
            return;
        }
        
        if (spotElement.classList.contains('locked')) {
            alert('🔒 This spot is currently being booked by another user. Please try another spot.');
            return;
        }
        
        if (isLoading) {
            alert('Please wait, processing your request...');
            return;
        }
        
        isLoading = true;
        
        const locationId = spotElement.getAttribute('data-location-id');
        const spotNumber = spotElement.getAttribute('data-spot-number');
        const locationName = spotElement.getAttribute('data-location-name');
        const pricePerHour = parseFloat(spotElement.getAttribute('data-price'));
        const date = spotElement.getAttribute('data-date');
        const startTime = spotElement.getAttribute('data-start');
        const endTime = spotElement.getAttribute('data-end');
        
        console.log('Selecting spot:', {locationId, spotNumber, locationName, pricePerHour, date, startTime, endTime});
        
        const container = spotElement.parentElement;
        const allSpots = container.querySelectorAll('.spot-item');
        allSpots.forEach(spot => {
            spot.classList.remove('selected');
        });
        
        document.querySelectorAll('[id^="selected-info-"]').forEach(infoDiv => {
            if (infoDiv.id !== `selected-info-${locationId}`) {
                infoDiv.innerHTML = '';
            }
        });
        
        try {
            const checkData = await checkSpotAvailability(locationId, spotNumber, date, startTime, endTime);
            console.log('Availability check:', checkData);
            
            if (!checkData.available) {
                alert('❌ This spot is no longer available. Please select another spot.');
                spotElement.classList.add('booked');
                isLoading = false;
                return;
            }
            
            const lockResponse = await fetch('lock_spot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `location_id=${locationId}&spot_number=${spotNumber}&date=${date}&start_time=${startTime}&end_time=${endTime}`
            });
            const lockData = await lockResponse.json();
            console.log('Lock response:', lockData);
            
            if (!lockData.locked) {
                if (lockData.reason === 'already_booked') {
                    alert('❌ This spot has already been booked. Please select another spot.');
                    spotElement.classList.add('booked');
                } 
                else if (lockData.reason === 'locked_by_other') {
                    alert('🔒 This spot is currently being booked by someone else. Please try another spot.');
                    spotElement.classList.add('locked');
                } 
                else {
                    alert('Unable to lock this spot. Please try again.');
                }
                isLoading = false;
                return;
            }
            
            spotElement.classList.add('selected');
            
            const start = new Date(`2000/01/01 ${startTime}`);
            const end = new Date(`2000/01/01 ${endTime}`);
            let hours = (end - start) / (1000 * 60 * 60);
            if (hours <= 0) hours = 1;
            const totalAmount = (hours * pricePerHour).toFixed(2);
            
            selectedSpotData = {
                location_id: locationId,
                spot_number: spotNumber,
                location_name: locationName,
                price_per_hour: pricePerHour,
                checkin: date,
                start_time: startTime,
                end_time: endTime,
                hours: hours.toFixed(1),
                amount: totalAmount,
                lock_token: lockData.lock_token
            };
            
            console.log('Selected spot data:', selectedSpotData);
            
            const infoDiv = document.getElementById(`selected-info-${locationId}`);
            if (infoDiv) {
                infoDiv.innerHTML = `
                    <div class="selected-info-card">
                        <i class="fas fa-check-circle" style="color: #16a34a;"></i> 
                        <strong>Selected Spot:</strong> ${spotNumber} at ${locationName}<br>
                        <strong>Duration:</strong> ${hours.toFixed(1)} hours &nbsp;|&nbsp; 
                        <strong>Total Amount:</strong> <span style="font-size: 1.2rem; font-weight: 700; color: #16a34a;">$${totalAmount}</span>
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 8px;">
                            <i class="fas fa-clock"></i> Spot reserved for 15 minutes. Complete booking to confirm.
                        </div>
                        <button class="btn-proceed" onclick="proceedToBooking()">
                            <i class="fas fa-arrow-right"></i> Proceed to Booking
                        </button>
                    </div>
                `;
                infoDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
        } catch (error) {
            console.error('Error selecting spot:', error);
            alert('Network error: ' + error.message);
        } finally {
            isLoading = false;
        }
    }

    function proceedToBooking() {
        if (!selectedSpotData) {
            alert('Please select a parking spot first');
            return;
        }
        
        const params = new URLSearchParams({
            location_id: selectedSpotData.location_id,
            spot_number: selectedSpotData.spot_number,
            location_name: selectedSpotData.location_name,
            price_per_hour: selectedSpotData.price_per_hour,
            date: selectedSpotData.checkin,
            from: selectedSpotData.start_time,
            to: selectedSpotData.end_time,
            hours: selectedSpotData.hours,
            amount: selectedSpotData.amount,
            lock_token: selectedSpotData.lock_token
        });
        
        console.log('Redirecting to booking.php?' + params.toString());
        window.location.href = `booking.php?${params.toString()}`;
    }
    </script>
</body>
</html>