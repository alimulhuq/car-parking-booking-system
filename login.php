<?php
    require_once 'config/db.php';

    $error = '';

    if (isLoggedIn()) {
        header('Location: index.php');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password';
        } else {
            $stmt = $pdo->prepare("SELECT id, fullname, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'];
                $_SESSION['user_email'] = $user['email'];
                
                // Redirect to previous page if set, otherwise index
                $redirect = $_GET['redirect'] ?? 'index.php';
                header("Location: $redirect");
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ParkEase</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            max-width: 450px;
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
        .demo-info {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 0.85rem;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-header">
        <i class="fas fa-parking" style="font-size: 3rem; color: #2563eb;"></i>
        <h2>Welcome Back</h2>
        <p style="color: #64748b;">Login to manage your parking</p>
    </div>
    
    <?php if($error): ?>
        <div class="error-msg">
            <i class="fas fa-exclamation-circle"></i> 
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>Email Address</label>
            <div class="input-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <div class="input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required>
            </div>
        </div>
        
        <button type="submit" class="btn-auth">Login</button>
    </form>
    
    <div class="auth-footer">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
    
    <div class="demo-info">
        <i class="fas fa-info-circle"></i> Demo credentials: john@example.com / password123
    </div>
</div>
</body>
</html>