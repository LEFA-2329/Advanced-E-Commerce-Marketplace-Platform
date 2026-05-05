<?php
session_start();
require_once '../db_connection.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $cell_number = trim($_POST['cell_number']);
    $province = trim($_POST['province']);
    $city = trim($_POST['city']);
    $suburb = trim($_POST['suburb']);
    $gender = trim($_POST['gender']);
    $age = intval($_POST['age']);

    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } else {
        // Enforce strong password: at least 8 characters, at least one uppercase, one lowercase, one digit, one special character
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        if (!preg_match($pattern, $password)) {
            $errors[] = "Password must be at least 8 characters long and include uppercase, lowercase, digit, and special character.";
        }
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO customers (username, email, password_hash, cell_number, province, city, suburb, gender, age) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$username, $email, $password_hash, $cell_number, $province, $city, $suburb, $gender, $age]);
            header("Location: ../unified_login.php?registered=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Customer Registration - Store System</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css" />
    <style>
        body{
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, orangered 0%, orange 100%);
            overflow-y:scroll;
        }
        .container{
            border-top:none;
            border-radius:20px;
            box-shadow:0 0 20px rgba(0,0,0,0.1);
            width:650px
        }
        .login-header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-bottom: 20px;
            border-top-left-radius: 20px;
            border-bottom-right-radius: 20px;
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
        button{
            background:orange;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-header">
            <h1><i class="fas fa-store"></i> Store System</h1>
            <p>Sign up & start enjoying deals</p>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error-messages" style="color: red;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="registration.php" novalidate>
            <div>
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
            </div>
            <div>
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
            </div>
            <div>
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required />
            </div>
            <div>
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required />
            </div>
            <div>
                <label for="cell_number">Cell Number *</label>
                <input type="text" id="cell_number" name="cell_number" required value="<?= htmlspecialchars($_POST['cell_number'] ?? '') ?>" />
            </div>
            <div>
                <label for="province">Province *</label>
                <select id="province" name="province" required>
                    <option value="">Select a province</option>
                    <option value="Eastern Cape" <?= (isset($_POST['province']) && $_POST['province'] === 'Eastern Cape') ? 'selected' : '' ?>>Eastern Cape</option>
                    <option value="Free State" <?= (isset($_POST['province']) && $_POST['province'] === 'Free State') ? 'selected' : '' ?>>Free State</option>
                    <option value="Gauteng" <?= (isset($_POST['province']) && $_POST['province'] === 'Gauteng') ? 'selected' : '' ?>>Gauteng</option>
                    <option value="KwaZulu-Natal" <?= (isset($_POST['province']) && $_POST['province'] === 'KwaZulu-Natal') ? 'selected' : '' ?>>KwaZulu-Natal</option>
                    <option value="Limpopo" <?= (isset($_POST['province']) && $_POST['province'] === 'Limpopo') ? 'selected' : '' ?>>Limpopo</option>
                    <option value="Mpumalanga" <?= (isset($_POST['province']) && $_POST['province'] === 'Mpumalanga') ? 'selected' : '' ?>>Mpumalanga</option>
                    <option value="Northern Cape" <?= (isset($_POST['province']) && $_POST['province'] === 'Northern Cape') ? 'selected' : '' ?>>Northern Cape</option>
                    <option value="North West" <?= (isset($_POST['province']) && $_POST['province'] === 'North West') ? 'selected' : '' ?>>North West</option>
                    <option value="Western Cape" <?= (isset($_POST['province']) && $_POST['province'] === 'Western Cape') ? 'selected' : '' ?>>Western Cape</option>
                </select>
            </div>
            <div>
                <label for="city">City *</label>
                <input type="text" id="city" name="city" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" />
            </div>
            <div>
                <label for="suburb">Suburb *</label>
                <input type="text" id="suburb" name="suburb" required value="<?= htmlspecialchars($_POST['suburb'] ?? '') ?>" />
            </div>
            <div>
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select gender</option>
                    <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div>
                <label for="age">Age *</label>
                <input type="number" id="age" name="age" required min="13" max="120" value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" />
            </div>
            <button type="submit">Register</button>
        </form>
        <p id="check" style="color:#333">Already have an account? <a href="../unified_login.php" style="color:orange">Login here</a>.</p>
    </div>
</body>
</html>
