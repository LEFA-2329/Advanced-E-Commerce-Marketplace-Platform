<?php
session_start();
require_once 'db_connection.php';

$errors = [];
$success = false;

// Check if reset email and user type are set in session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_type'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];
$user_type = $_SESSION['reset_user_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($otp)) {
        $errors[] = "OTP is required.";
    }
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        // Validate OTP
        $stmt = $pdo->prepare("SELECT token_id, expires_at, used FROM password_reset_tokens WHERE email = ? AND token = ? AND user_type = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $otp, $user_type]);
        $token_data = $stmt->fetch();

        if (!$token_data) {
            $errors[] = "Invalid OTP.";
        } elseif ($token_data['used']) {
            $errors[] = "This OTP has already been used.";
        } elseif (strtotime($token_data['expires_at']) < time()) {
            $errors[] = "OTP has expired.";
        }

        if (empty($errors)) {
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            if ($user_type === 'customer') {
                $update_stmt = $pdo->prepare("UPDATE customers SET password_hash = ? WHERE email = ?");
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            }
            $update_stmt->execute([$password_hash, $email]);

            // Mark OTP as used
            $mark_used_stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token_id = ?");
            $mark_used_stmt->execute([$token_data['token_id']]);

            // Clear session reset data
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_type']);

            $success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reset Password - Store System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
            <h1><i class="fas fa-key"></i> Reset Password</h1>
            <p>Enter the OTP sent to your email and your new password</p>
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
                    <p>Your password has been reset successfully.</p>
                    <p><a href="unified_login.php">Click here to login</a></p>
                </div>
            <?php else: ?>
                <?php if (isset($_SESSION['debug_otp'])): ?>
                    <div class="success-message" style="margin-bottom: 20px;">
                        <p><strong>Debug OTP:</strong> <?= htmlspecialchars($_SESSION['debug_otp']) ?></p>
                        <p>This OTP is shown because email server is not configured.</p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="reset_password.php">
                    <div class="form-group">
                        <i class="fas fa-key"></i>
                        <input type="text" name="otp" placeholder="Enter OTP" required>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="new_password" placeholder="New Password" required>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    </div>
                    <button type="submit" class="btn-reset">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="reset-footer">
            <p><a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Back to Forgot Password</a></p>
        </div>
    </div>
</body>
</html>
