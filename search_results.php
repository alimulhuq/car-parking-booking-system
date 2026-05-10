<?php
require_once 'config/db.php';

// capture search params with validation
$location = isset($_GET['location']) ? htmlspecialchars(trim($_GET['location'])) : 'Downtown';
$checkin = isset($_GET['checkin']) ? $_GET['checkin'] : date('Y-m-d');
$start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '09:00';
$end_time = isset($_GET['end_time']) ? $_GET['end_time'] : '18:00';

// Validate date and time
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
    $checkin = date('Y-m-d');
}
if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
    $start_time = '09:00';
}
if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    $end_time = '18:00';
}

// Get all parking locations
$parking_locations = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM parking_locations WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $parking_locations = $stmt->fetchAll();
} catch (Exception $e) {
    // Sample data fallback
    $parking_locations = [
        ['id' => 1, 'name' => 'Central Plaza Garage', 'address' => '100 Central Plaza, Downtown, NY', 'total_spots' => 150, 'price_per_hour' => 3.50, 'features' => '24/7 security, EV charging, CCTV'],
        ['id' => 2, 'name' => 'Harbor View Parking', 'address' => '500 Harbor Drive, CA', 'total_spots' => 80, 'price_per_hour' => 2.90, 'features' => 'Ocean view, Security patrol'],
        ['id' => 3, 'name' => 'Grand Station Underground', 'address' => '200 Station Road, Chicago, IL', 'total_spots' => 200, 'price_per_hour' => 4.20, 'features' => 'Direct station access, Elevators']
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f9ff; }
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logo-area { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .logo-area i { color: #2563eb; }
        .logo-bold { color: #1e40af; }
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
        .btn-outline-light:hover { background: #2563eb; color: white; }
        .results-card {
            background: white;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .results-card:hover { box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eef2ff;
        }
        .location-name { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .price-hour { font-size: 1.8rem; font-weight: 800; color: #2563eb; }
        .location-features { display: flex; flex-wrap: wrap; gap: 15px; margin: 15px 0; color: #64748b; font-size: 0.9rem; }
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
        }
        .spot-item {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .spot-item:hover:not(.booked) { border-color: #2563eb; transform: scale(1.02); }
        .spot-item.selected { background: #2563eb; border-color: #2563eb; color: white; }
        .spot-item.booked { opacity: 0.5; cursor: not-allowed; background: #f1f5f9; }
        .spot-number { font-size: 1.1rem; font-weight: 700; display: block; }
        .spot-type { font-size: 0.7rem; color: #64748b; margin-top: 5px; display: block; }
        .spot-item.selected .spot-type { color: rgba(255,255,255,0.9); }
        .availability-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-left: 10px; }
        .availability-high { background: #dcfce7; color: #16a34a; }
        .availability-medium { background: #fef3c7; color: #d97706; }
        .availability-low { background: #fee2e2; color: #dc2626; }
        .refresh-btn { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 40px; cursor: pointer; font-size: 0.85rem; }
        .search-summary { background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 30px; }
        @media (max-width: 768px) {
            .spots-grid { grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); }
            .location-header { flex-direction: column; }
        }
        .spot-item.booked {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f1f5f9;
            position: relative;
        }
        .spot-item.booked:after {
            content: "BOOKED";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.7rem;
            font-weight: 700;
            color: #dc2626;
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .spot-item.locked {
            opacity: 0.6;
            cursor: wait;
            background: #fef3c7;
            position: relative;
        }
        .spot-item.locked:after {
            content: "🔒";
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7rem;
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
                <a href="my_bookings.php" class="btn-outline-light"><i class="fas fa-history"></i> My Bookings</a>
                <a href="profile.php" class="btn-outline-light"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></a>
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
                    <h2><i class="fas fa-search"></i> Available Parking for "<strong><?php echo htmlspecialchars($location); ?></strong>"</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($checkin)); ?> | <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($start_time)); ?> - <?php echo date('g:i A', strtotime($end_time)); ?></p>
                </div>
                <button class="refresh-btn" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>
        
        <div id="parking-results">
            <?php foreach($parking_locations as $loc): 
                // Get available spots count
                $available_count = $loc['total_spots'];
                $availability_class = $available_count > 50 ? 'availability-high' : ($available_count > 20 ? 'availability-medium' : 'availability-low');
            ?>
            <div class="results-card" data-location-id="<?php echo $loc['id']; ?>">
                <div class="location-header">
                    <div>
                        <div class="location-name"><i class="fas fa-building"></i> <?php echo htmlspecialchars($loc['name']); ?></div>
                        <div class="location-features">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($loc['address']); ?></span>
                            <span><i class="fas fa-star"></i> <?php echo htmlspecialchars($loc['features']); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="price-hour">$<?php echo number_format($loc['price_per_hour'], 2); ?><span style="font-size: 0.9rem;">/hour</span></div>
                        <span class="availability-badge <?php echo $availability_class; ?>"><i class="fas fa-car"></i> <?php echo $available_count; ?> spots available</span>
                    </div>
                </div>
                
                <h4><i class="fas fa-parking"></i> Select a Parking Spot:</h4>
                <div class="spots-grid" id="spots-grid-<?php echo $loc['id']; ?>" 
                     data-location-id="<?php echo $loc['id']; ?>" 
                     data-location-name="<?php echo htmlspecialchars($loc['name']); ?>"
                     data-price="<?php echo $loc['price_per_hour']; ?>"
                     data-date="<?php echo $checkin; ?>" 
                     data-start="<?php echo $start_time; ?>" 
                     data-end="<?php echo $end_time; ?>">
                    <!-- Spots will be loaded by JavaScript -->
                    <div style="text-align: center; padding: 20px;">Loading spots...</div>
                </div>
                
                <div id="selected-info-<?php echo $loc['id']; ?>" style="margin-top: 15px;"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// Store selected spot data
let selectedSpotData = null;
let isLoading = false;

// Function to load spots
function loadSpots(locationId, locationName, pricePerHour, date, startTime, endTime) {
    const container = document.getElementById(`spots-grid-${locationId}`);
    if (!container) return;
    
    // Generate spots
    const spotTypes = ['standard', 'standard', 'standard', 'ev', 'handicap', 'vip'];
    const spots = [];
    const prefixes = ['A', 'B', 'C', 'D', 'E', 'F'];
    
    // Generate 24 sample spots
    for (let i = 1; i <= 24; i++) {
        const prefixIndex = Math.floor((i - 1) / 4);
        const prefix = prefixes[prefixIndex];
        const number = ((i - 1) % 4) + 1;
        spots.push({
            number: `${prefix}${number}`,
            type: spotTypes[(i - 1) % spotTypes.length]
        });
    }
    
    container.innerHTML = '';
    
    for (const spot of spots) {
        const spotDiv = document.createElement('div');
        spotDiv.className = 'spot-item';
        spotDiv.setAttribute('data-spot-number', spot.number);
        spotDiv.setAttribute('data-spot-type', spot.type);
        spotDiv.innerHTML = `
            <span class="spot-number">${spot.number}</span>
            <span class="spot-type">
                ${spot.type === 'ev' ? '<i class="fas fa-charging-station"></i> EV' : 
                  spot.type === 'handicap' ? '<i class="fas fa-wheelchair"></i> Handicap' :
                  spot.type === 'vip' ? '<i class="fas fa-crown"></i> VIP' :
                  '<i class="fas fa-parking"></i> Std'}
            </span>
        `;
        
        // Check availability on click
        spotDiv.onclick = async () => {
            if (spotDiv.classList.contains('booked')) {
                alert('This spot is already booked. Please select another spot.');
                return;
            }
            await selectSpot(locationId, spot.number, locationName, pricePerHour, date, startTime, endTime, spotDiv);
        };
        
        container.appendChild(spotDiv);
    }
}

// Function to select a spot and lock it
async function selectSpot(locationId, spotNumber, locationName, pricePerHour, date, startTime, endTime, element) {
    if (isLoading) {
        alert('Please wait...');
        return;
    }
    
    isLoading = true;
    
    // Remove selection from other spots
    const container = element.parentElement;
    container.querySelectorAll('.spot-item').forEach(spot => {
        spot.classList.remove('selected');
    });
    
    // First, check availability
    try {
        const checkResponse = await fetch('check_availability.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `location_id=${locationId}&spot_number=${spotNumber}&date=${date}&start_time=${startTime}&end_time=${endTime}`
        });
        const checkData = await checkResponse.json();
        
        if (!checkData.available) {
            alert('This spot is already booked. Please select another spot.');
            element.classList.add('booked');
            isLoading = false;
            return;
        }
        
        // Try to lock the spot
        const lockResponse = await fetch('lock_spot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `location_id=${locationId}&spot_number=${spotNumber}&date=${date}&start_time=${startTime}&end_time=${endTime}`
        });
        const lockData = await lockResponse.json();
        
        if (!lockData.locked) {
            if (lockData.reason === 'already_booked') {
                alert('This spot has already been booked. Please select another spot.');
                element.classList.add('booked');
            } else {
                alert('This spot is currently being booked by someone else. Please try another spot.');
            }
            isLoading = false;
            return;
        }
        
        // Add selection
        element.classList.add('selected');
        
        // Calculate duration and amount
        const start = new Date(`2000/01/01 ${startTime}`);
        const end = new Date(`2000/01/01 ${endTime}`);
        const hours = (end - start) / (1000 * 60 * 60);
        const totalAmount = (hours * pricePerHour).toFixed(2);
        
        // Store selected spot data
        window.selectedSpotData = {
            location_id: locationId,
            spot_number: spotNumber,
            location_name: locationName,
            price_per_hour: pricePerHour,
            checkin: date,
            start_time: startTime,
            end_time: endTime,
            hours: hours,
            amount: totalAmount,
            lock_token: lockData.lock_token
        };
        
        // Show selected info
        const infoDiv = document.getElementById(`selected-info-${locationId}`);
        infoDiv.innerHTML = `
            <div style="background: #dcfce7; padding: 15px; border-radius: 12px; margin-top: 15px;">
                <i class="fas fa-check-circle" style="color: #16a34a;"></i> 
                <strong>Selected:</strong> Spot ${spotNumber} at ${locationName}<br>
                <strong>Duration:</strong> ${hours} hours | <strong>Total:</strong> $${totalAmount}
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 5px;">
                    <i class="fas fa-clock"></i> Spot reserved for 15 minutes
                </div>
                <br>
                <button onclick="proceedToBooking()" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer;">
                    <i class="fas fa-arrow-right"></i> Proceed to Booking
                </button>
            </div>
        `;
        
    } catch (error) {
        console.error('Error:', error);
        alert('Network error. Please try again.');
    } finally {
        isLoading = false;
    }
}

// Function to proceed to booking
function proceedToBooking() {
    if (!window.selectedSpotData) {
        alert('Please select a parking spot first');
        return;
    }
    
    const data = window.selectedSpotData;
    const params = new URLSearchParams({
        location_id: data.location_id,
        spot_number: data.spot_number,
        location_name: data.location_name,
        price_per_hour: data.price_per_hour,
        date: data.checkin,
        from: data.start_time,
        to: data.end_time,
        hours: data.hours,
        amount: data.amount,
        lock_token: data.lock_token
    });
    
    window.location.href = `booking.php?${params.toString()}`;
}

// Load all spots when page loads
document.addEventListener('DOMContentLoaded', () => {
    <?php foreach($parking_locations as $loc): ?>
    loadSpots(<?php echo $loc['id']; ?>, '<?php echo addslashes($loc['name']); ?>', <?php echo $loc['price_per_hour']; ?>, '<?php echo $checkin; ?>', '<?php echo $start_time; ?>', '<?php echo $end_time; ?>');
    <?php endforeach; ?>
});
</script>
</body>
</html>