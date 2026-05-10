<?php
// featured parking spots dataset (simulated DB)
$featured_spots = [
    [
        'name' => 'Central Plaza Garage',
        'price' => '$3.50 / hour',
        'distance' => '0.2 mi',
        'availability' => '78 spots free',
        'features' => 'EV charging · 24/7 security',
        'icon' => 'fa-building'
    ],
    [
        'name' => 'Harbor View Parking',
        'price' => '$2.90 / hour',
        'distance' => '0.5 mi',
        'availability' => '42 spots free',
        'features' => 'Covered · CCTV',
        'icon' => 'fa-water'
    ],
    [
        'name' => 'Grand Station Underground',
        'price' => '$4.20 / hour',
        'distance' => '0.1 mi',
        'availability' => '120 spots free',
        'features' => 'Direct station access',
        'icon' => 'fa-train'
    ],
    [
        'name' => 'Riverside SmartPark',
        'price' => '$3.00 / hour',
        'distance' => '0.7 mi',
        'availability' => '25 spots free',
        'features' => 'Contactless entry',
        'icon' => 'fa-tree'
    ]
];

if (!empty($featured_spots)):
    foreach($featured_spots as $spot): ?>
    <div class="parking-card">
        <div class="card-img">
            <i class="fas <?php echo $spot['icon']; ?>"></i>
        </div>
        <div class="card-content">
            <h3><?php echo htmlspecialchars($spot['name']); ?></h3>
            <div class="price-tag"><?php echo htmlspecialchars($spot['price']); ?></div>
            <div class="features">
                <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($spot['distance']); ?></span>
                <span><i class="fas fa-car"></i> <?php echo htmlspecialchars($spot['availability']); ?></span>
            </div>
            <p style="font-size:0.85rem; margin: 8px 0;"><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($spot['features']); ?></p>
            <a href="booking.php?spot=<?php echo urlencode($spot['name']); ?>" class="btn-sm">Book now →</a>
        </div>
    </div>
<?php 
    endforeach;
else: ?>
    <p>No parking spots available at the moment.</p>
<?php endif; ?>