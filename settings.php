<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner or Manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Owner', 'Manager'])) {
    header("Location: unified_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch current user info
$stmt = $pdo->prepare("SELECT email, profile_image FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();


$errors = [];
$success = '';

// Handle update owner details (username, email)
if (isset($_POST['update_owner_details'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);

    if (empty($new_username)) {
        $errors[] = "Username is required.";
    }
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }

    if (empty($errors)) {
        // Check if username or email already exists for other users
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $stmt->execute([$new_username, $new_email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists.";
        } else {
            // Update username and email
            $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
            $updateStmt->execute([$new_username, $new_email, $user_id]);
            $success = "Owner details updated successfully.";
            $_SESSION['username'] = $new_username;
            $username = $new_username;
        }
    }
}

// Handle profile image upload
if (isset($_POST['update_profile_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('profile_', true) . '.' . $ext;
                $upload_dir = __DIR__ . '/images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $upload_path = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Update profile_image in DB
                    $updateStmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                    $updateStmt->execute([$new_filename, $user_id]);
                    $success = "Profile image updated successfully.";
                    $user['profile_image'] = $new_filename;
                } else {
                    $errors[] = "Failed to upload profile image.";
                }
            } else {
                $errors[] = "Invalid profile image type. Allowed types: JPEG, PNG, GIF.";
            }
        } else {
            $errors[] = "Error uploading profile image.";
        }
    } else {
        $errors[] = "No profile image selected.";
    }
}

// Handle adding new manager user (only Owner can add)
if ($role === 'Owner' && isset($_POST['add_manager'])) {
    $new_username = trim($_POST['new_username']);
    $new_email = trim($_POST['new_email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_username)) {
        $errors[] = "Manager username is required.";
    }
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid manager email is required.";
    }
    if (empty($new_password)) {
        $errors[] = "Manager password is required.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "Manager passwords do not match.";
    }

    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$new_username, $new_email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Manager username or email already exists.";
        } else {
            // Get role_id for Manager
            $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Manager'");
            $roleStmt->execute();
            $roleData = $roleStmt->fetch();
            if (!$roleData) {
                // Create Manager role
                $insertRoleStmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES ('Manager')");
                $insertRoleStmt->execute();
                $role_id = $pdo->lastInsertId();
            } else {
                $role_id = $roleData['role_id'];
            }
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$new_username, $new_email, $password_hash, $role_id]);
            $success = "New manager user added successfully.";
        }
    }
}

// Handle password change for owner and manager
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    if ($new_password !== $confirm_new_password) {
        $errors[] = "New passwords do not match.";
    }

    if (empty($errors)) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $userData = $stmt->fetch();
        if ($userData && password_verify($current_password, $userData['password_hash'])) {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $updateStmt->execute([$new_password_hash, $user_id]);
            $success = "Password changed successfully.";
        } else {
            $errors[] = "Current password is incorrect.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Settings - Store System</title>
    <link rel="stylesheet" href="settings.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body>
    <div class="sidebar">
        <div class="user-info" style="padding: 15px; text-align: center; border-bottom: 1px solid #ddd;">
            <img src="images/<?= htmlspecialchars($user['profile_image'] ?? 'default_profile.png') ?>" alt="Profile Image" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;" />
            <div style="font-weight: 600; color: #0d6efd;"><?= htmlspecialchars($username) ?></div>
        </div>
        <div class="logo">Store System</div>
        <nav>
            <a href="owner_dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="product_management.php"><i class="fas fa-box-open"></i> Products</a>
            <a href="promotions_management.php"><i class="fas fa-tags"></i> Promotions</a>
            <a href="analytics.php"><i class="fas fa-chart-pie"></i> Analytics</a>
              <a href="AI_business_intelligence.php"><i class="fa-solid fa-robot"></i>Business Intel</a>
          
            <a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Settings</a>
            <a href="logout.php"><i class="fa-solid fa-power-off"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <h2>Settings</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-messages" style="color: red;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message" style="color: green; margin-bottom: 15px;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <section>
            <h3>Update Profile Image</h3>
            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="file" name="profile_image" accept="image/*" required />
                <button type="submit" name="update_profile_image">Upload</button>
            </form>
        </section>

        <div class="flex-row-container">
        <section>
            <h3 class="dropdown-header" style="cursor:pointer;"><i class="fa-solid fa-user-cog"></i> Update Owner Details &#x25BC;</h3>
            <div class="dropdown-content" style="display:none;">
                <form method="POST" action="settings.php">
                    <div>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required />
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required />
                    </div>
                    <button type="submit" name="update_owner_details">Update Details</button>
                </form>
            </div>
        </section>

        <?php if ($role === 'Owner'): ?>
        <section>
            <h3 class="dropdown-header" style="cursor:pointer;"><i class="fa-solid fa-user-plus"></i> Add New Manager &#x25BC;</h3>
            <div class="dropdown-content" style="display:none;">
                <form method="POST" action="settings.php">
                    <div>
                        <label for="new_username">Username</label>
                        <input type="text" id="new_username" name="new_username" required />
                    </div>
                    <div>
                        <label for="new_email">Email</label>
                        <input type="email" id="new_email" name="new_email" required />
                    </div>
                    <div>
                        <label for="new_password">Password</label>
                        <input type="password" id="new_password" name="new_password" required />
                    </div>
                    <div>
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required />
                    </div>
                    <button type="submit" name="add_manager">Add Manager</button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <section>
            <h3 class="dropdown-header" style="cursor:pointer;"><i class="fa-solid fa-key"></i> Change Password &#x25BC;</h3>
            <div class="dropdown-content" style="display:none;">
                <form method="POST" action="settings.php">
                    <div>
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required />
                    </div>
                    <div>
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required />
                    </div>
                    <div>
                        <label for="confirm_new_password">Confirm New Password</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required />
                    </div>
                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </div>
        </section>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.dropdown-header');
    headers.forEach(header => {
        header.addEventListener('click', () => {
            const content = header.nextElementSibling;
            if (content.style.display === 'block') {
                content.style.display = 'none';
                header.innerHTML = header.innerHTML.replace('&#x25B2;', '&#x25BC;');
            } else {
                content.style.display = 'block';
                header.innerHTML = header.innerHTML.replace('&#x25BC;', '&#x25B2;');
            }
        });
    });
});
</script>
</body>
</html>
