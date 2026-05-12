<?php
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Create uploads directory if it doesn't exist with proper permissions
$upload_dir = 'uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // First update the basic profile info
    try {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$fullname, $phone, $address, $user_id]);
        $_SESSION['user_name'] = $fullname;
        $success_msg = 'Profile updated successfully!';
    } catch (Exception $e) {
        $error_msg = "Error updating profile: " . $e->getMessage();
    }
    
    // Handle profile picture upload separately
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['profile_picture']['type'];
            $file_size = $_FILES['profile_picture']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file_size > $max_size) {
                $error_msg = "Profile picture is too large. Maximum size is 5MB.";
            } elseif (!in_array($file_type, $allowed_types)) {
                $error_msg = "Only JPG, PNG, and GIF files are allowed.";
            } else {
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                    // Delete old profile picture if exists
                    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $old_pic = $stmt->fetch();
                    if ($old_pic && $old_pic['profile_picture'] && file_exists($old_pic['profile_picture'])) {
                        unlink($old_pic['profile_picture']);
                    }
                    
                    // Update database with new profile picture path
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$file_path, $user_id]);
                    $success_msg = "Profile updated successfully with new picture!";
                } else {
                    $error_msg = "Failed to upload profile picture. Please check folder permissions.";
                }
            }
        } elseif ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.",
                UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
            ];
            $error_msg = $upload_errors[$_FILES['profile_picture']['error']] ?? "Unknown upload error.";
        }
    }
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Handle profile picture removal
if (isset($_GET['remove_picture'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if ($user_data && $user_data['profile_picture'] && file_exists($user_data['profile_picture'])) {
            unlink($user_data['profile_picture']);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_msg = "Profile picture removed successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error_msg = "Error removing profile picture: " . $e->getMessage();
    }
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch user vehicles
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

// Handle vehicle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
    $vehicle_model = trim($_POST['vehicle_model']);
    $vehicle_color = trim($_POST['vehicle_color']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Check if vehicle already exists
    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE user_id = ? AND vehicle_number = ?");
    $stmt->execute([$user_id, $vehicle_number]);
    
    if ($stmt->fetch()) {
        $error_msg = 'Vehicle with this number already exists!';
    } else {
        // If setting as default, remove default from other vehicles
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE vehicles SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, vehicle_number, vehicle_model, vehicle_color, is_default) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $vehicle_number, $vehicle_model, $vehicle_color, $is_default])) {
            $success_msg = 'Vehicle added successfully!';
            // Refresh vehicles list
            $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
            $stmt->execute([$user_id]);
            $vehicles = $stmt->fetchAll();
        } else {
            $error_msg = 'Failed to add vehicle. Please try again.';
        }
    }
}

// Handle vehicle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $vehicle_number = strtoupper(trim($_POST['vehicle_number']));
    $vehicle_model = trim($_POST['vehicle_model']);
    $vehicle_color = trim($_POST['vehicle_color']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // If setting as default, remove default from other vehicles
    if ($is_default) {
        $stmt = $pdo->prepare("UPDATE vehicles SET is_default = 0 WHERE user_id = ? AND id != ?");
        $stmt->execute([$user_id, $vehicle_id]);
    }
    
    $stmt = $pdo->prepare("UPDATE vehicles SET vehicle_number = ?, vehicle_model = ?, vehicle_color = ?, is_default = ? WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$vehicle_number, $vehicle_model, $vehicle_color, $is_default, $vehicle_id, $user_id])) {
        $success_msg = 'Vehicle updated successfully!';
        // Refresh vehicles list
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$user_id]);
        $vehicles = $stmt->fetchAll();
    } else {
        $error_msg = 'Failed to update vehicle.';
    }
}

// Handle vehicle deletion
if (isset($_GET['delete_vehicle'])) {
    $vehicle_id = $_GET['delete_vehicle'];
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$vehicle_id, $user_id])) {
        $success_msg = 'Vehicle removed successfully!';
        // Refresh vehicles list
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$user_id]);
        $vehicles = $stmt->fetchAll();
    } else {
        $error_msg = 'Failed to remove vehicle.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ParkEase</title>
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

        .main-nav a.active {
            color: #2563eb;
            font-weight: 600;
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

        .profile-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }

        .profile-sidebar {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .profile-main {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .profile-avatar {
            text-align: center;
            margin-bottom: 24px;
        }

        .avatar-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #2563eb;
            margin-bottom: 16px;
        }

        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: white;
            font-size: 4rem;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .profile-email {
            color: #64748b;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            padding-top: 20px;
            border-top: 1px solid #eef2ff;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
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
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .form-group input[type="file"] {
            padding: 10px;
            background: #f8fafc;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .vehicle-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
        }

        .vehicle-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .vehicle-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .default-badge {
            background: #dcfce7;
            color: #16a34a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .vehicle-details {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .vehicle-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            transition: 0.2s;
        }

        .btn-edit {
            color: #2563eb;
        }

        .btn-edit:hover {
            background: #2563eb10;
        }

        .btn-delete {
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .picture-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .profile-sidebar {
                position: static;
            }
            .vehicle-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .vehicle-details {
                flex-direction: column;
                gap: 5px;
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
                <a href="profile.php" class="active profile-link">
                    <i class="fas fa-user-circle"></i> 
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </a>
            </nav>
            <div class="header-cta">
                <a href="logout.php" class="btn-outline-light"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <div class="profile-container">
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
        
        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php if($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                        <img src="<?php echo $user['profile_picture']; ?>?t=<?php echo time(); ?>" alt="Profile Picture" class="avatar-image">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <h3 class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></h3>
                    <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                    <?php if($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                        <div class="picture-actions">
                            <a href="?remove_picture=1" class="btn-danger" onclick="return confirm('Are you sure you want to remove your profile picture?')">
                                <i class="fas fa-trash"></i> Remove Picture
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) as total_bookings, SUM(amount) as total_spent FROM bookings WHERE user_id = ? AND status = 'confirmed'");
                $stmt->execute([$user_id]);
                $stats = $stmt->fetch();
                ?>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                        <div class="stat-label">Bookings</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($vehicles); ?></div>
                        <div class="stat-label">Vehicles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($stats['total_spent'] ?? 0, 0); ?></div>
                        <div class="stat-label">Spent</div>
                    </div>
                </div>
            </div>
            
            <div class="profile-main">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-user-edit"></i> Edit Profile</h2>
                <form method="POST" enctype="multipart/form-data" style="margin-bottom: 40px;">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email (Cannot be changed)</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background: #f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <small style="color: #64748b;">Allowed formats: JPG, PNG, GIF (Max 5MB)</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
                
                <h2 style="margin-bottom: 20px;"><i class="fas fa-car"></i> My Vehicles</h2>
                <button onclick="showAddVehicleModal()" class="btn-secondary" style="margin-bottom: 20px;">
                    <i class="fas fa-plus"></i> Add New Vehicle
                </button>
                
                <div id="vehiclesList">
                    <?php if(empty($vehicles)): ?>
                        <p style="color: #64748b; text-align: center; padding: 40px;">No vehicles added yet. Add your first vehicle for faster booking!</p>
                    <?php else: ?>
                        <?php foreach($vehicles as $vehicle): ?>
                            <div class="vehicle-card">
                                <div class="vehicle-header">
                                    <span class="vehicle-number">
                                        <i class="fas fa-car"></i> <?php echo htmlspecialchars($vehicle['vehicle_number']); ?>
                                    </span>
                                    <?php if($vehicle['is_default']): ?>
                                        <span class="default-badge"><i class="fas fa-star"></i> Default Vehicle</span>
                                    <?php endif; ?>
                                </div>
                                <div class="vehicle-details">
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($vehicle['vehicle_model'] ?: 'Not specified'); ?></span>
                                    <span><i class="fas fa-palette"></i> <?php echo htmlspecialchars($vehicle['vehicle_color'] ?: 'Not specified'); ?></span>
                                </div>
                                <div class="vehicle-actions">
                                    <button onclick="editVehicle(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['vehicle_number']); ?>', '<?php echo htmlspecialchars($vehicle['vehicle_model']); ?>', '<?php echo htmlspecialchars($vehicle['vehicle_color']); ?>', <?php echo $vehicle['is_default']; ?>)" class="btn-icon btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="if(confirm('Are you sure you want to remove this vehicle?')) window.location.href='profile.php?delete_vehicle=<?php echo $vehicle['id']; ?>'" class="btn-icon btn-delete">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add New Vehicle</h2>
            <form method="POST" id="vehicleForm">
                <input type="hidden" name="vehicle_id" id="vehicle_id">
                <div class="form-group">
                    <label>Vehicle Number *</label>
                    <input type="text" name="vehicle_number" id="vehicle_number" required placeholder="ABC 1234">
                </div>
                <div class="form-group">
                    <label>Vehicle Model</label>
                    <input type="text" name="vehicle_model" id="vehicle_model" placeholder="e.g., Toyota Camry">
                </div>
                <div class="form-group">
                    <label>Vehicle Color</label>
                    <input type="text" name="vehicle_color" id="vehicle_color" placeholder="e.g., Red, Blue">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_default" id="is_default" value="1">
                        Set as default vehicle
                    </label>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" name="add_vehicle" id="submitBtn" class="btn-primary">Add Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddVehicleModal() {
            document.getElementById('modalTitle').innerText = 'Add New Vehicle';
            document.getElementById('vehicle_id').value = '';
            document.getElementById('vehicle_number').value = '';
            document.getElementById('vehicle_model').value = '';
            document.getElementById('vehicle_color').value = '';
            document.getElementById('is_default').checked = false;
            document.getElementById('submitBtn').name = 'add_vehicle';
            document.getElementById('vehicleForm').action = '';
            document.getElementById('vehicleModal').style.display = 'flex';
        }

        function editVehicle(id, number, model, color, isDefault) {
            document.getElementById('modalTitle').innerText = 'Edit Vehicle';
            document.getElementById('vehicle_id').value = id;
            document.getElementById('vehicle_number').value = number;
            document.getElementById('vehicle_model').value = model;
            document.getElementById('vehicle_color').value = color;
            document.getElementById('is_default').checked = isDefault == 1;
            document.getElementById('submitBtn').name = 'update_vehicle';
            document.getElementById('vehicleForm').action = '';
            document.getElementById('vehicleModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('vehicleModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('vehicleModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success, .alert-error');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>