<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$owner_id = $_SESSION['user_id'];
$errors = [];
$success_count = 0;
$error_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed with error code: " . $file['error'];
    } else {
        // Check file type
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_type !== 'csv') {
            $errors[] = "Only CSV files are allowed.";
        } else {
            // Process the CSV file
            if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
                $header = fgetcsv($handle);
                
                // Validate CSV header
                $expected_columns = ['name', 'description', 'price', 'stock_quantity', 'category', 'image_url'];
                $header_diff = array_diff($expected_columns, $header);
                
                if (!empty($header_diff)) {
                    $errors[] = "CSV file is missing required columns: " . implode(', ', $header_diff);
                } else {
                    // Process each row
                    $row_number = 1;
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $row_number++;
                        
                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }
                        
                        // Map data to columns
                        $product_data = array_combine($header, $data);
                        
                        // Validate required fields
                        if (empty($product_data['name']) || empty($product_data['price']) || empty($product_data['stock_quantity'])) {
                            $errors[] = "Row $row_number: Missing required fields (name, price, or stock_quantity)";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate price
                        if (!is_numeric($product_data['price']) || $product_data['price'] < 0) {
                            $errors[] = "Row $row_number: Invalid price format";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate stock quantity
                        if (!is_numeric($product_data['stock_quantity']) || $product_data['stock_quantity'] < 0) {
                            $errors[] = "Row $row_number: Invalid stock quantity format";
                            $error_count++;
                            continue;
                        }
                        
                        try {
                            // Insert product into database
                            $stmt = $pdo->prepare("
                                INSERT INTO products (owner_id, name, description, price, stock_quantity, category, image_url)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $owner_id,
                                trim($product_data['name']),
                                trim($product_data['description'] ?? ''),
                                floatval($product_data['price']),
                                intval($product_data['stock_quantity']),
                                trim($product_data['category'] ?? ''),
                                trim($product_data['image_url'] ?? '')
                            ]);
                            
                            $success_count++;
                            
                        } catch (PDOException $e) {
                            $errors[] = "Row $row_number: Database error - " . $e->getMessage();
                            $error_count++;
                        }
                    }
                    fclose($handle);
                }
            } else {
                $errors[] = "Could not open the uploaded file.";
            }
        }
    }
}

// Store results in session for display
$_SESSION['bulk_upload_results'] = [
    'success_count' => $success_count,
    'error_count' => $error_count,
    'errors' => $errors
];

header("Location: product_management.php");
exit;
?>
