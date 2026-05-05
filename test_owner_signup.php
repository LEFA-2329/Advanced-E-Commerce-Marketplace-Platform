<?php
// Test script to verify owner registration functionality
require_once 'db_connection.php';

echo "<h2>Owner Registration System Test</h2>";

// Test 1: Check if database tables exist
echo "<h3>1. Database Table Check</h3>";
$tables = ['users', 'stores', 'roles'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Table '$table' does not exist: " . $e->getMessage() . "</p>";
    }
}

// Test 2: Check if users table has new columns
echo "<h3>2. Users Table Columns Check</h3>";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['first_name', 'last_name', 'id_number', 'date_of_birth', 
                        'business_experience', 'business_registration_number', 
                        'tax_number', 'bank_name', 'bank_account_number', 'branch_code'];
    
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "<p style='color: green;'>✓ Column '$column' exists in users table</p>";
        } else {
            echo "<p style='color: red;'>✗ Column '$column' missing from users table</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking users table columns: " . $e->getMessage() . "</p>";
}

// Test 3: Check if stores table has required columns
echo "<h3>3. Stores Table Columns Check</h3>";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'stores'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['store_id', 'owner_id', 'store_name', 'store_description', 
                        'store_category', 'store_logo', 'store_banner', 'store_address',
                        'store_province', 'store_city', 'store_suburb', 'store_postal_code',
                        'store_phone', 'store_email', 'store_website', 'business_hours',
                        'delivery_options', 'return_policy', 'payment_methods',
                        'social_media_links', 'is_verified', 'is_active', 'created_at', 'updated_at'];
    
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "<p style='color: green;'>✓ Column '$column' exists in stores table</p>";
        } else {
            echo "<p style='color: red;'>✗ Column '$column' missing from stores table</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking stores table columns: " . $e->getMessage() . "</p>";
}

// Test 4: Check if Owner role exists
echo "<h3>4. Owner Role Check</h3>";
try {
    $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Owner'");
    $stmt->execute();
    $role = $stmt->fetch();
    
    if ($role) {
        echo "<p style='color: green;'>✓ Owner role exists (ID: " . $role['role_id'] . ")</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Owner role does not exist (will be created during registration)</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking Owner role: " . $e->getMessage() . "</p>";
}

// Test 5: Check if owner_signup.php file exists
echo "<h3>5. File Existence Check</h3>";
if (file_exists('owner_signup.php')) {
    echo "<p style='color: green;'>✓ owner_signup.php file exists</p>";
    
    // Check if form contains required fields
    $content = file_get_contents('owner_signup.php');
    $required_fields = ['first_name', 'last_name', 'cell_number', 'store_name', 'store_category'];
    
    foreach ($required_fields as $field) {
        if (strpos($content, $field) !== false) {
            echo "<p style='color: green;'>✓ Form field '$field' found</p>";
        } else {
            echo "<p style='color: red;'>✗ Form field '$field' not found</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ owner_signup.php file does not exist</p>";
}

echo "<h3>6. Implementation Summary</h3>";
echo "<p>The owner registration system has been enhanced with:</p>";
echo "<ul>";
echo "<li>Additional personal information fields (first name, last name, cell number, ID number, date of birth)</li>";
echo "<li>Store information (store name, description, category)</li>";
echo "<li>Business information (registration number, tax number, business experience)</li>";
echo "<li>Banking information (bank name, account number, branch code)</li>";
echo "<li>New stores table to store store-specific information</li>";
echo "<li>Updated database schema with all required fields</li>";
echo "<li>Enhanced registration form with organized sections</li>";
echo "<li>Proper validation for all required fields</li>";
echo "</ul>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Test the registration form by visiting owner_signup.php</li>";
echo "<li>Verify that data is correctly stored in both users and stores tables</li>";
echo "<li>Test form validation for all required fields</li>";
echo "<li>Verify that the Owner role is created if it doesn't exist</li>";
echo "<li>Test the login functionality after registration</li>";
echo "</ol>";

echo "<p><a href='owner_signup.php' style='color: blue; text-decoration: none; font-weight: bold;'>→ Test Registration Form</a></p>";
?>
