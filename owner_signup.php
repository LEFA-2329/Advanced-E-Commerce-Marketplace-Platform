<?php
session_start();
require_once 'db_connection.php'; // Assumes a file for DB connection

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

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

    // Validate additional required fields
    if (empty($errors)) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $cell_number = trim($_POST['cell_number'] ?? '');
        $store_name = trim($_POST['store_name'] ?? '');
        $store_category = trim($_POST['store_category'] ?? '');

        if (empty($first_name)) {
            $errors[] = "First name is required.";
        }
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        }
        if (empty($cell_number)) {
            $errors[] = "Cell number is required.";
        }
        if (empty($store_name)) {
            $errors[] = "Store name is required.";
        }
        if (empty($store_category)) {
            $errors[] = "Store category is required.";
        }

        // Validate business registration and tax number
        $business_registration_number = trim($_POST['business_registration_number'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');

        if (empty($business_registration_number)) {
            $errors[] = "Business registration number is required.";
        }
        if (empty($tax_number)) {
            $errors[] = "Tax number is required.";
        }

        // Check if business is approved for registration
        if (!empty($business_registration_number) && !empty($tax_number)) {
            $businessCheckStmt = $pdo->prepare("
                SELECT business_status, company_name, province, industry_sector, id as reference_number
                FROM approved_business_entities
                WHERE business_registration_number = ? AND tax_number = ? AND business_status = 'active'
            ");
            $businessCheckStmt->execute([$business_registration_number, $tax_number]);
            $approvedBusiness = $businessCheckStmt->fetch();

            if (!$approvedBusiness) {
                $errors[] = "❌ REGISTRATION REJECTED: Your business registration number and tax number combination is not found in our approved business database. Only pre-approved businesses can register as store owners. Please contact support at support@store-f.co.za for business verification and approval.";
            } else {
                // Store approved business info for later use
                $_SESSION['approved_business'] = $approvedBusiness;
                $_SESSION['business_reference_id'] = $approvedBusiness['reference_number'];

                // Log successful business verification
                error_log("Business verification successful for: " . $business_registration_number . " (Reference: " . $approvedBusiness['reference_number'] . ")");
            }
        } else {
            $errors[] = "❌ REGISTRATION REJECTED: Both business registration number and tax number are required for owner registration. These must match our approved business database.";
        }
    }

    if (empty($errors)) {
        // Check owner limit (maximum 5 owners)
        $ownerCountStmt = $pdo->prepare("SELECT COUNT(*) as owner_count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Owner'");
        $ownerCountStmt->execute();
        $ownerCount = $ownerCountStmt->fetch()['owner_count'];

        if ($ownerCount >= 5) {
            $errors[] = "Registration limit reached. The system currently allows a maximum of 5 store owners. Please contact support for additional owner accounts.";
        }

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists.";
        } else {
            // Get role_id for Owner
            $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Owner'");
            $roleStmt->execute();
            $role = $roleStmt->fetch();
            if (!$role) {
                // Create Owner role
                $insertRoleStmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES ('Owner')");
                $insertRoleStmt->execute();
                $role_id = $pdo->lastInsertId();
            } else {
                $role_id = $role['role_id'];
            }

            // Get additional form data
            $id_number = trim($_POST['id_number'] ?? '');
            $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
            $business_experience = trim($_POST['business_experience'] ?? '');
            $business_registration_number = trim($_POST['business_registration_number'] ?? '');
            $tax_number = trim($_POST['tax_number'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $bank_account_number = trim($_POST['bank_account_number'] ?? '');
            $branch_code = trim($_POST['branch_code'] ?? '');
            $store_description = trim($_POST['store_description'] ?? '');

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user data
            $insertStmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role_id, cell_number, 
                                  first_name, last_name, id_number, date_of_birth, 
                                  business_experience, business_registration_number, 
                                  tax_number, bank_name, bank_account_number, branch_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $username, $email, $password_hash, $role_id, $cell_number,
                $first_name, $last_name, $id_number, $date_of_birth,
                $business_experience, $business_registration_number,
                $tax_number, $bank_name, $bank_account_number, $branch_code
            ]);
            
            $user_id = $pdo->lastInsertId();

            // Insert store data
            $storeStmt = $pdo->prepare("
                INSERT INTO stores (owner_id, store_name, store_description, store_category)
                VALUES (?, ?, ?, ?)
            ");
            
            $storeStmt->execute([$user_id, $store_name, $store_description, $store_category]);

            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'Owner';

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            header("Location: owner_dashboard.php");
            exit;
        }
    }
}
?>

<?php

require_once 'db_connection.php';

// Redirect if already logged in as Owner
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Owner') {
    header("Location: owner_dashboard.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Owner Signup - Store System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Professional Color Scheme */
        :root {
            --primary-dark: #1a365d;
            --primary: #2d3748;
            --primary-light: #4a5568;
            --accent: #3182ce;
            --accent-light: #4299e1;
            --success: #38a169;
            --warning: #d69e2e;
            --danger: #e53e3e;
            --text-dark: #2d3748;
            --text-light: #718096;
            --white: #ffffff;
            --gray-50: #f7fafc;
            --gray-100: #edf2f7;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }

        .signup-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
            position: relative;
        }

        .signup-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--accent) 100%);
            color: var(--white);
            text-align: center;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }

        .signup-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateX(-50%) translateY(-50%) rotate(0deg); }
            100% { transform: translateX(-50%) translateY(-50%) rotate(360deg); }
        }

        .signup-header h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            background: linear-gradient(45deg, var(--white), var(--gray-200), var(--accent-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .signup-header p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 2;
            font-weight: 300;
        }

        .progress-indicator {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            position: relative;
            z-index: 2;
        }

        .progress-step {
            display: flex;
            align-items: center;
            margin: 0 15px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .progress-step.active {
            color: var(--white);
            font-weight: 600;
        }

        .progress-step::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            margin-right: 8px;
        }

        .signup-form {
            padding: 40px;
            max-height: 70vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--accent) var(--gray-200);
        }

        .signup-form::-webkit-scrollbar {
            width: 6px;
        }

        .signup-form::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 3px;
        }

        .signup-form::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }

        .form-section {
            margin-bottom: 35px;
            padding: 25px;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            position: relative;
            transition: all 0.3s ease;
        }

        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            border-radius: 2px 0 0 2px;
        }

        .form-section h3 {
            color: var(--primary-dark);
            margin-bottom: 25px;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-section h3 i {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.6rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--white);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(49, 130, 206, 0.1);
            transform: translateY(-1px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.5;
        }

        .form-group select {
            cursor: pointer;
        }

        .btn-signup {
            width: 100%;
            padding: 18px 30px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(49, 130, 206, 0.3);
        }

        .btn-signup:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(49, 130, 206, 0.4);
        }

        .btn-signup:active {
            transform: translateY(-1px);
        }

        .error-messages {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            border: 1px solid var(--danger);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            color: #c53030;
            border-left: 4px solid var(--danger);
        }

        .error-messages ul {
            list-style: none;
            padding: 0;
        }

        .error-messages li {
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .error-messages li::before {
            content: '⚠️';
            flex-shrink: 0;
            margin-top: 2px;
        }

        .login-link {
            text-align: center;
            padding: 30px;
            border-top: 1px solid var(--gray-200);
            font-size: 1rem;
            color: var(--text-light);
            background: var(--gray-50);
        }

        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: var(--accent-light);
            text-decoration: underline;
        }

        .required {
            color: var(--danger);
            font-weight: 700;
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 5px;
            font-style: italic;
        }

        @media (max-width: 1024px) {
            .signup-container {
                max-width: 800px;
            }

            .signup-header h1 {
                font-size: 2.4rem;
            }

            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .signup-container {
                margin: 10px;
                border-radius: 15px;
            }

            .signup-header {
                padding: 30px 20px;
            }

            .signup-header h1 {
                font-size: 2rem;
            }

            .signup-form {
                padding: 25px;
                max-height: 60vh;
            }

            .form-section {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .progress-indicator {
                flex-wrap: wrap;
                gap: 10px;
            }

            .progress-step {
                margin: 0;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .signup-header h1 {
                font-size: 1.8rem;
            }

            .signup-form {
                padding: 20px;
            }

            .form-section {
                padding: 15px;
            }

            .btn-signup {
                padding: 16px 20px;
                font-size: 1rem;
            }
        }

        /* Loading animation */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        /* Success message styling */
        .success-message {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            border: 1px solid var(--success);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            color: #22543d;
            border-left: 4px solid var(--success);
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-header">
            <h1><i class="fas fa-store"></i> Store Owner Registration</h1>
            <p>Create your online store account and start selling today</p>
            <div class="progress-indicator">
                <div class="progress-step active">Account Setup</div>
                <div class="progress-step">Personal Info</div>
                <div class="progress-step">Store Details</div>
                <div class="progress-step">Business Info</div>
            </div>
        </div>

        <div class="signup-form">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="owner_signup.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                
                <!-- Account Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Username <span class="required">*</span></label>
                            <input type="text" id="username" name="username" required
                                   value="<?= isset($username) ? htmlspecialchars($username) : '' ?>"
                                   placeholder="Choose a username">
                            <div class="form-hint">Choose a unique username for your account</div>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required
                                   value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
                                   placeholder="your@email.com">
                            <div class="form-hint">We'll use this for account verification and important updates</div>
                        </div>
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                            <input type="password" id="password" name="password" required
                                   placeholder="At least 8 characters">
                            <div class="form-hint">Must include uppercase, lowercase, number, and special character</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   placeholder="Confirm your password">
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" required
                                   placeholder="Your first name">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" required
                                   placeholder="Your last name">
                        </div>
                        <div class="form-group">
                            <label for="cell_number">Cell Number <span class="required">*</span></label>
                            <input type="tel" id="cell_number" name="cell_number" required
                                   placeholder="+27 123 456 7890">
                            <div class="form-hint">For account verification and important notifications</div>
                        </div>
                        <div class="form-group">
                            <label for="id_number">ID Number</label>
                            <input type="text" id="id_number" name="id_number" 
                                   placeholder="South African ID number">
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                    </div>
                </div>

                <!-- Store Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-store"></i> Store Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="store_name">Store Name <span class="required">*</span></label>
                            <input type="text" id="store_name" name="store_name" required
                                   placeholder="Your store name">
                            <div class="form-hint">Choose a unique and memorable name for your online store</div>
                        </div>
                        <div class="form-group">
                            <label for="store_category">Store Category <span class="required">*</span></label>
                            <select id="store_category" name="store_category" required>
                                <option value="">Select category</option>
                                <option value="fashion">Fashion & Clothing</option>
                                <option value="electronics">Electronics</option>
                                <option value="home">Home & Garden</option>
                                <option value="beauty">Beauty & Health</option>
                                <option value="sports">Sports & Outdoors</option>
                                <option value="food">Food & Beverages</option>
                                <option value="art">Arts & Crafts</option>
                                <option value="other">Other</option>
                            </select>
                            <div class="form-hint">Select the primary category that best describes your products</div>
                        </div>
                        <div class="form-group">
                            <label for="store_description">Store Description</label>
                            <textarea id="store_description" name="store_description" 
                                      placeholder="Describe your store and what you sell"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Business Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-building"></i> Business Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="business_registration_number">Business Registration Number <span class="required">*</span></label>
                            <input type="text" id="business_registration_number" name="business_registration_number" required
                                   placeholder="CIPC registration number">
                            <div class="form-hint">Must be approved for e-commerce registration</div>
                        </div>
                        <div class="form-group">
                            <label for="tax_number">Tax Number <span class="required">*</span></label>
                            <input type="text" id="tax_number" name="tax_number" required
                                   placeholder="SARS tax number">
                            <div class="form-hint">Must match approved business registration</div>
                        </div>
                        <div class="form-group">
                            <label for="business_experience">Business Experience</label>
                            <textarea id="business_experience" name="business_experience" 
                                      placeholder="Tell us about your business experience"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Banking Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-university"></i> Banking Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bank_name">Bank Name</label>
                            <input type="text" id="bank_name" name="bank_name" 
                                   placeholder="e.g., ABSA, FNB, Standard Bank">
                        </div>
                        <div class="form-group">
                            <label for="bank_account_number">Account Number</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" 
                                   placeholder="Bank account number">
                        </div>
                        <div class="form-group">
                            <label for="branch_code">Branch Code</label>
                            <input type="text" id="branch_code" name="branch_code" 
                                   placeholder="Bank branch code">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-signup">
                    <i class="fas fa-rocket"></i>
                    <span>Create Store Account</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <div class="login-link">
            <p>Already have an account? <a href="unified_login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
