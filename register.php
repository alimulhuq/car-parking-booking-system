<?php
    require_once 'config/db.php';

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        
        if (empty($fullname) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields';
        } 
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } 
        elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } 
        elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } 
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } 
            else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$fullname, $email, $hashed_password, $phone])) {
                    $user_id = $pdo->lastInsertId();
                    
                    if (!empty($vehicle_number)) {
                        $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, vehicle_number, is_default) VALUES (?, ?, 1)");
                        $stmt->execute([$user_id, $vehicle_number]);
                    }
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $fullname;
                    $_SESSION['user_email'] = $email;
                    header('Location: index.php');
                    exit();
                } 
                else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            max-width: 500px;
            margin: 60px auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-header h2 {
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
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 48px;
            font-size: 1rem;
            transition: 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .btn-auth {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 48px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-auth:hover {
            background: #1d4ed8;
        }
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            color: #64748b;
        }
        .auth-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .success-msg {
            background: #dcfce7;
            color: #16a34a;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .input-icon input {
            padding-left: 42px;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-header">
        <i class="fas fa-parking" style="font-size: 3rem; color: #2563eb;"></i>
        <h2>Create Account</h2>
        <p style="color: #64748b;">Join ParkEase for smarter parking</p>
    </div>
    
    <?php if($error): ?>
        <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="success-msg"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Full Name *</label>
            <div class="input-icon">
                <i class="fas fa-user"></i>
                <input type="text" name="fullname" required value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Email Address *</label>
            <div class="input-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Phone Number</label>
            <div class="input-icon">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Vehicle Number (Optional)</label>
            <div class="input-icon">
                <i class="fas fa-car"></i>
                <input type="text" name="vehicle_number" placeholder="ABC 1234" value="<?php echo htmlspecialchars($_POST['vehicle_number'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Password *</label>
            <div class="input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Confirm Password *</label>
            <div class="input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" required>
            </div>
        </div>
        
        <button type="submit" class="btn-auth">Register Now</button>
    </form>
    
    <div class="auth-footer">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>
</body>
</html>