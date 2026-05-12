-- ============================================================
-- CREATE DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS parkease_db;
USE parkease_db;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. VEHICLES TABLE
-- ============================================================
DROP TABLE IF EXISTS vehicles;
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    vehicle_model VARCHAR(100) DEFAULT NULL,
    vehicle_color VARCHAR(50) DEFAULT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vehicle_per_user (user_id, vehicle_number),
    INDEX idx_user_id (user_id),
    INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. PARKING LOCATIONS TABLE
-- ============================================================
DROP TABLE IF EXISTS parking_locations;
CREATE TABLE parking_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    total_spots INT NOT NULL DEFAULT 1,
    available_spots INT DEFAULT NULL,
    price_per_hour DECIMAL(10, 2) NOT NULL,
    price_per_day DECIMAL(10, 2) DEFAULT NULL,
    features TEXT DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    opening_time TIME DEFAULT '00:00:00',
    closing_time TIME DEFAULT '23:59:59',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active),
    INDEX idx_price (price_per_hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. INDIVIDUAL PARKING SPOTS TABLE
-- ============================================================
DROP TABLE IF EXISTS parking_spots;
CREATE TABLE parking_spots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_location_id INT NOT NULL,
    spot_number VARCHAR(20) NOT NULL,
    spot_type ENUM('standard', 'ev', 'handicap', 'vip', 'compact') DEFAULT 'standard',
    floor_level INT DEFAULT 1,
    is_available BOOLEAN DEFAULT TRUE,
    is_reserved BOOLEAN DEFAULT FALSE,
    dimensions VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_location_id) REFERENCES parking_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_spot_per_location (parking_location_id, spot_number),
    INDEX idx_availability (is_available),
    INDEX idx_spot_type (spot_type),
    INDEX idx_floor_level (floor_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. SPOT LOCKS TABLE
-- ============================================================
DROP TABLE IF EXISTS spot_locks;
CREATE TABLE spot_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_location_id INT NOT NULL,
    spot_number VARCHAR(20) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    locked_by INT NOT NULL,
    lock_token VARCHAR(64) NOT NULL UNIQUE,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (parking_location_id) REFERENCES parking_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_spot (parking_location_id, spot_number, booking_date),
    INDEX idx_token (lock_token),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. BOOKINGS TABLE (COMPLETE WITH ALL COLUMNS)
-- ============================================================
DROP TABLE IF EXISTS bookings;
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT DEFAULT NULL,
    parking_location_id INT NOT NULL,
    parking_spot_id INT DEFAULT NULL,
    spot_number VARCHAR(20) DEFAULT NULL,
    location_name VARCHAR(100) NOT NULL,
    booking_ref VARCHAR(20) UNIQUE NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_hours INT GENERATED ALWAYS AS 
        (TIMESTAMPDIFF(HOUR, CONCAT(booking_date, ' ', start_time), CONCAT(booking_date, ' ', end_time))) STORED,
    amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_method_type VARCHAR(50) DEFAULT NULL,
    card_last_four VARCHAR(4) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
    special_requests TEXT DEFAULT NULL,
    is_spot_locked BOOLEAN DEFAULT FALSE,
    lock_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (parking_location_id) REFERENCES parking_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (parking_spot_id) REFERENCES parking_spots(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_booking_ref (booking_ref),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. PAYMENTS TABLE
-- ============================================================
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    card_last_four VARCHAR(4) DEFAULT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    payment_status ENUM('pending', 'successful', 'failed', 'refunded') DEFAULT 'pending',
    payment_response TEXT DEFAULT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. REVIEWS TABLE
-- ============================================================
DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    parking_location_id INT NOT NULL,
    booking_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT DEFAULT NULL,
    images TEXT DEFAULT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parking_location_id) REFERENCES parking_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_booking (user_id, booking_id),
    INDEX idx_parking_location (parking_location_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. NOTIFICATIONS TABLE
-- ============================================================
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking', 'payment', 'reminder', 'promotion', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. COUPONS TABLE
-- ============================================================
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value DECIMAL(10, 2) NOT NULL,
    min_purchase DECIMAL(10, 2) DEFAULT 0,
    max_discount DECIMAL(10, 2) DEFAULT NULL,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_valid_dates (valid_from, valid_until),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. USER_COUPONS TABLE
-- ============================================================
DROP TABLE IF EXISTS user_coupons;
CREATE TABLE user_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coupon_id INT NOT NULL,
    booking_id INT DEFAULT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_coupon (user_id, coupon_id),
    INDEX idx_user_id (user_id),
    INDEX idx_used_at (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. PARKING_HISTORY TABLE
-- ============================================================
DROP TABLE IF EXISTS parking_history;
CREATE TABLE parking_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    entry_time TIMESTAMP NULL,
    exit_time TIMESTAMP NULL,
    actual_duration_hours DECIMAL(5, 2) DEFAULT NULL,
    entry_gate VARCHAR(100) DEFAULT NULL,
    exit_gate VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_entry_time (entry_time),
    INDEX idx_exit_time (exit_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EVENTS
-- ============================================================

SET GLOBAL event_scheduler = ON;

-- Event to clean expired locks every minute
DROP EVENT IF EXISTS clean_expired_locks;
CREATE EVENT IF NOT EXISTS clean_expired_locks
ON SCHEDULE EVERY 1 MINUTE
DO
UPDATE spot_locks SET is_active = FALSE WHERE expires_at < NOW();

-- Event to auto-cancel pending bookings after 30 minutes
DROP EVENT IF EXISTS auto_cancel_pending_bookings;
CREATE EVENT IF NOT EXISTS auto_cancel_pending_bookings
ON SCHEDULE EVERY 5 MINUTE
DO
UPDATE bookings 
SET status = 'cancelled' 
WHERE status = 'pending' 
AND payment_status = 'pending'
AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

-- ============================================================
-- VIEWS
-- ============================================================

-- View for available spots
DROP VIEW IF EXISTS v_available_spots;
CREATE VIEW v_available_spots AS
SELECT 
    pl.id as location_id,
    pl.name as location_name,
    pl.address,
    pl.total_spots,
    pl.available_spots,
    pl.price_per_hour,
    pl.features,
    COUNT(DISTINCT b.id) as booked_count,
    pl.total_spots - COUNT(DISTINCT b.id) as calculated_available
FROM parking_locations pl
LEFT JOIN bookings b ON pl.id = b.parking_location_id 
    AND b.status = 'confirmed' 
    AND b.booking_date >= CURDATE()
WHERE pl.is_active = 1
GROUP BY pl.id;

-- View for active bookings
DROP VIEW IF EXISTS v_active_bookings;
CREATE VIEW v_active_bookings AS
SELECT 
    b.id,
    b.booking_ref,
    b.location_name,
    b.spot_number,
    b.booking_date,
    b.start_time,
    b.end_time,
    b.amount,
    b.status,
    b.payment_status,
    u.fullname as user_name,
    u.email as user_email,
    v.vehicle_number
FROM bookings b
JOIN users u ON b.user_id = u.id
LEFT JOIN vehicles v ON b.vehicle_id = v.id
WHERE b.status IN ('confirmed', 'pending')
ORDER BY b.booking_date ASC, b.start_time ASC;

-- View for user booking summary
DROP VIEW IF EXISTS v_user_booking_summary;
CREATE VIEW v_user_booking_summary AS
SELECT 
    u.id as user_id,
    u.fullname,
    u.email,
    COUNT(b.id) as total_bookings,
    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as active_bookings,
    SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount ELSE 0 END) as total_spent
FROM users u
LEFT JOIN bookings b ON u.id = b.user_id
GROUP BY u.id;

-- ============================================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX idx_bookings_user_status ON bookings(user_id, status);
CREATE INDEX idx_bookings_date_status ON bookings(booking_date, status);
CREATE INDEX idx_bookings_payment ON bookings(payment_status, status);
CREATE INDEX idx_vehicles_user_default ON vehicles(user_id, is_default);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_spot_locks_cleanup ON spot_locks(expires_at, is_active);
CREATE INDEX idx_bookings_location_date ON bookings(parking_location_id, booking_date);
CREATE INDEX idx_payments_booking ON payments(booking_id);

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

SELECT '=========================================' as '';
SELECT 'ParkEase Database Setup Completed!' as status;
SELECT '=========================================' as '';
SELECT 'Users:' as '', COUNT(*) as count FROM users;
SELECT 'Vehicles:' as '', COUNT(*) as count FROM vehicles;
SELECT 'Parking Locations:' as '', COUNT(*) as count FROM parking_locations;
SELECT 'Parking Spots:' as '', COUNT(*) as count FROM parking_spots;
SELECT 'Bookings:' as '', COUNT(*) as count FROM bookings;
SELECT 'Payments:' as '', COUNT(*) as count FROM payments;
SELECT 'Spot Locks:' as '', COUNT(*) as count FROM spot_locks;
SELECT 'Coupons:' as '', COUNT(*) as count FROM coupons;
SELECT '=========================================' as '';