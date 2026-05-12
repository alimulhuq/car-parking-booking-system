<?php
// Fetch parking locations from database
$featured_spots = [];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM parking_locations 
        WHERE is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $featured_spots = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback data
    $featured_spots = [
        [
            'name' => 'Central Plaza Garage',
            'price_per_hour' => 3.50,
            'address' => '100 Central Plaza, Downtown, NY',
            'total_spots' => 150,
            'available_spots' => 95,
            'features' => '24/7 security, EV charging, CCTV',
            'id' => 1
        ],
        [
            'name' => 'Harbor View Parking',
            'price_per_hour' => 2.90,
            'address' => '500 Harbor Drive, CA',
            'total_spots' => 80,
            'available_spots' => 42,
            'features' => 'Ocean view, Security patrol',
            'id' => 2
        ],
        [
            'name' => 'Grand Station Underground',
            'price_per_hour' => 4.20,
            'address' => '200 Station Road, Chicago, IL',
            'total_spots' => 200,
            'available_spots' => 120,
            'features' => 'Direct station access, Elevators',
            'id' => 3
        ],
        [
            'name' => 'Riverside SmartPark',
            'price_per_hour' => 3.00,
            'address' => '500 River Road, Boston, MA',
            'total_spots' => 75,
            'available_spots' => 25,
            'features' => 'Contactless entry, 24/7 security',
            'id' => 4
        ]
    ];
}

if (!empty($featured_spots)):
    foreach($featured_spots as $spot): 
        $available = isset($spot['available_spots']) ? $spot['available_spots'] : $spot['total_spots'];
        $availability_class = $available > 50 ? 'high' : ($available > 20 ? 'medium' : 'low');
        ?>
        <div class="parking-card">
            <div class="card-img">
                <i class="fas fa-building"></i>
            </div>
            <div class="card-content">
                <h3><?php echo htmlspecialchars($spot['name']); ?></h3>
                <div class="price-tag">$<?php echo number_format($spot['price_per_hour'], 2); ?> <span>/ hour</span></div>
                <div class="features">
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($spot['address'], 0, 30)); ?>...</span>
                    <span><i class="fas fa-car"></i> <?php echo $available; ?> spots free</span>
                </div>
                <p style="font-size:0.85rem; margin: 8px 0;">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars(substr($spot['features'] ?? 'Secure parking', 0, 40)); ?>
                </p>
                <a href="search_results.php?location=<?php echo urlencode($spot['name']); ?>&checkin=<?php echo date('Y-m-d'); ?>&start_time=09:00&end_time=18:00" class="btn-sm">
                    Book now →
                </a>
            </div>
        </div>
    <?php 
    endforeach;
else: ?>
    <p>No parking spots available at the moment.</p>
<?php endif; ?>