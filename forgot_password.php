<?php
session_start();
require_once 'db_connection.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        // Check if email exists in either customers or users table
        $user_type = '';
        $user_exists = false;
        
        // Check in customers table
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $user_type = 'customer';
            $user_exists = true;
        }
        
        // Check in users table (for owners/managers)
        if (!$user_exists) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $user_type = 'user';
                $user_exists = true;
            }
        }

        if ($user_exists) {
            // Check rate limiting
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt = $pdo->prepare("SELECT attempt_count, last_attempt FROM password_reset_attempts WHERE email = ?");
            $stmt->execute([$email]);
            $attempt = $stmt->fetch();

            if ($attempt) {
                $time_diff = time() - strtotime($attempt['last_attempt']);
                if ($time_diff < 3600 && $attempt['attempt_count'] >= 3) {
                    $errors[] = "Too many password reset attempts. Please try again in " . ceil((3600 - $time_diff) / 60) . " minutes.";
                } else {
                    // Update attempt count
                    if ($time_diff < 3600) {
                        $stmt = $pdo->prepare("UPDATE password_reset_attempts SET attempt_count = attempt_count + 1, last_attempt = NOW() WHERE email = ?");
                    } else {
                        $stmt = $pdo->prepare("UPDATE password_reset_attempts SET attempt_count = 1, last_attempt = NOW() WHERE email = ?");
                    }
                    $stmt->execute([$email]);
                }
            } else {
                // Create new attempt record
                $stmt = $pdo->prepare("INSERT INTO password_reset_attempts (email, ip_address) VALUES (?, ?)");
                $stmt->execute([$email, $ip_address]);
            }

            if (empty($errors)) {
                // Generate OTP (6-digit code)
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes from now

                // Store OTP in database
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $otp, $user_type, $expires_at]);

                // Send OTP via email
                $subject = "Password Reset OTP - Store System";
                $message = "
                <html>
                <head>
                    <title>Password Reset OTP</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Your OTP code is:</p>
                    <h3 style='color: #ff6b35; font-size: 24px;'>$otp</h3>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you didn't request this reset, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>Store System Team</p>
                </body>
                </html>
                ";

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Store System <noreply@store-system.com>" . "\r\n";

                // Try to send email, but handle failure gracefully for development
                $email_sent = @mail($email, $subject, $message, $headers);
                
                if ($email_sent) {
                    $success = true;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_type'] = $user_type;
                } else {
                    // For development/testing, we'll simulate email sending and show the OTP
                    $success = true;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_type'] = $user_type;
                    $_SESSION['debug_otp'] = $otp; // Store OTP in session for debugging
                    $errors[] = "Email server not configured. For testing, your OTP is: $otp";
                }
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = true; // Show success message even if email doesn't exist
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Store System</title>
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

        .reset-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .reset-header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }

        .reset-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .reset-form {
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

        .btn-reset {
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

        .btn-reset:hover {
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

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #155724;
        }

        .error-messages ul, .success-message ul {
            list-style: none;
        }

        .error-messages li, .success-message li {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .reset-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e1e5e9;
            font-size: 14px;
            color: #666;
        }

        .reset-footer a {
            color: #ff6b35;
            text-decoration: none;
        }

        .reset-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .reset-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .reset-header {
                padding: 20px 15px;
            }
            
            .reset-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1><i class="fas fa-key"></i> Forgot Password</h1>
            <p>Enter your email to reset your password</p>
        </div>

        <div class="reset-form">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <p>If the email address exists in our system, we've sent a password reset OTP to your email address.</p>
                    <p>Please check your inbox and <a href="reset_password.php">click here to enter the OTP</a>.</p>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                    
                    <div class="form-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Enter your email address" required 
                               value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                    </div>

                    <button type="submit" class="btn-reset">
                        <i class="fas fa-paper-plane"></i> Send Reset OTP
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="reset-footer">
            <p><a href="unified_login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
        </div>
    </div>
</body>
</html>
