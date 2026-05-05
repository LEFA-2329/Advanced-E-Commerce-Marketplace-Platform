<?php
session_start();
require_once 'db_connection.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Variables for lockout
$lockout_duration = 900; // 15 minutes lockout
$max_attempts = 4;

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        // Session expired
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $errors[] = "Your session has expired. Please login again.";
    } else {
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        if ($_SESSION['role'] === 'Customer') {
            header("Location: customers/product_browse.php");
        } elseif ($_SESSION['role'] === 'Manager') {
            header("Location: manager/manager_dashboard.php");
        } elseif ($_SESSION['role'] === 'Owner') {
            header("Location: owner_dashboard.php");
        }
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email)) {
        $errors[] = "Username or email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        // Check for lockout in database
        $stmt = $pdo->prepare("SELECT lockout_until FROM login_attempts WHERE user_email = ? AND lockout_until > NOW()");
        $stmt->execute([$username_or_email]);
        $lockout = $stmt->fetch();
        if ($lockout) {
            $remaining_time = strtotime($lockout['lockout_until']) - time();
            $errors[] = "Too many failed login attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
        }
    }

    if (empty($errors)) {
        $role_order = ['customer', 'manager', 'owner']; // default order
        if (isset($_GET['role'])) {
            $requested_role = $_GET['role'];
            if (in_array($requested_role, $role_order)) {
                // Move requested role to front
                $role_order = array_diff($role_order, [$requested_role]);
                array_unshift($role_order, $requested_role);
            }
        }

        $logged_in = false;
        foreach ($role_order as $role) {
            if ($role === 'customer') {
                $stmt = $pdo->prepare("SELECT customer_id, username, password_hash FROM customers WHERE username = ? OR email = ?");
                $stmt->execute([$username_or_email, $username_or_email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, lockout_until = NULL WHERE user_email = ? AND user_type = 'customer'");
                    $stmt->execute([$username_or_email]);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['customer_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = 'Customer';
                    $_SESSION['login_time'] = time(); // Set login time on successful login
                    header("Location: customers/product_browse.php");
                    exit;
                }
            } elseif ($role === 'manager') {
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.username, u.password_hash, r.role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE (u.username = ? OR u.email = ?) AND r.role_name = 'Manager'
                ");
                $stmt->execute([$username_or_email, $username_or_email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, lockout_until = NULL WHERE user_email = ? AND user_type = 'manager'");
                    $stmt->execute([$username_or_email]);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = 'Manager';
                    header("Location: manager/manager_dashboard.php");
                    exit;
                }
            } elseif ($role === 'owner') {
                $stmt = $pdo->prepare("
                    SELECT u.user_id, u.username, u.password_hash, r.role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE (u.username = ? OR u.email = ?) AND r.role_name = 'Owner'
                ");
                $stmt->execute([$username_or_email, $username_or_email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, lockout_until = NULL WHERE user_email = ? AND user_type = 'owner'");
                    $stmt->execute([$username_or_email]);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = 'Owner';
                    header("Location: owner_dashboard.php");
                    exit;
                }
            }
        }

        // If none of the above worked, show generic error
        $errors[] = "Invalid username/email or password.";

        // Increment login attempts
        // Determine user type
        $user_type = null;
        $stmt = $pdo->prepare("SELECT 'customer' as type FROM customers WHERE username = ? OR email = ?");
        $stmt->execute([$username_or_email, $username_or_email]);
        if ($stmt->fetch()) {
            $user_type = 'customer';
        } else {
            $stmt = $pdo->prepare("SELECT 'manager' as type FROM users u JOIN roles r ON u.role_id = r.role_id WHERE (u.username = ? OR u.email = ?) 
            AND r.role_name = 'Manager'");
            $stmt->execute([$username_or_email, $username_or_email]);
            if ($stmt->fetch()) {
                $user_type = 'manager';
            } else {
                $stmt = $pdo->prepare("SELECT 'owner' as type FROM users u JOIN roles r ON u.role_id = r.role_id WHERE (u.username = ? OR u.email = ?) 
                AND r.role_name = 'Owner'");
                $stmt->execute([$username_or_email, $username_or_email]);
                if ($stmt->fetch()) {
                    $user_type = 'owner';
                }
            }
        }

        if ($user_type) {
            // Insert or update login_attempts
            $stmt = $pdo->prepare("INSERT INTO login_attempts (user_email, user_type, attempt_count, last_attempt) VALUES (?, ?, 1, NOW()) ON CONFLICT (user_email, user_type) DO UPDATE SET attempt_count = login_attempts.attempt_count + 1, last_attempt = NOW(), lockout_until = CASE WHEN login_attempts.attempt_count + 1 >= ? THEN NOW() + INTERVAL '15 minutes' ELSE NULL END");
            $stmt->execute([$username_or_email, $user_type, $max_attempts]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, orangered 0%, orange 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 53, 0.3);
        }

        .error-messages {
            background: #fee;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #721c24;
        }

        .error-messages ul {
            list-style: none;
        }

        .error-messages li {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e1e5e9;
            font-size: 14px;
            color: #666;
        }

        .login-footer a {
            color: #ff6b35;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .login-header {
                padding: 20px 15px;
            }
            
            .login-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-store"></i> Store System</h1>
            <p>Sign in to access your account</p>
        </div>

        <div class="login-form">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="unified_login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username_or_email" placeholder="Username or Email" required 
                           value="<?= isset($username_or_email) ? htmlspecialchars($username_or_email) : '' ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>

        <div class="login-footer">
            <p>Visit site <a href="index.php">Click here</a></p>
            <p style="margin-top: 10px;"><a href="forgot_password.php" style="color: #ff6b35; text-decoration: none;">Forgot Password?</a></p>
        </div>
        <!-- <div class="login-footer">
            <p>Register for your Own online store <a href="owner_signup.php">Click here</a> (Only for store owners)</p>
        </div> -->
    </div>
</body>
</html>
