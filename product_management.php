<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Fetch profile image filename
$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$profile_image = $user && $user['profile_image'] ? $user['profile_image'] : 'default_profile.png';

// Handle delete product request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $owner_id = $_SESSION['user_id'];
    // Delete only if product belongs to owner
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ? AND owner_id = ?");
    $stmt->execute([$delete_id, $owner_id]);
    header("Location: product_management.php");
    exit;
}

// Fetch products with optional search, filtered by owner
$search = '';
$owner_id = $_SESSION['user_id'];
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE owner_id = ? AND name ILIKE ? ORDER BY created_at DESC");
    $stmt->execute([$owner_id, '%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE owner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$owner_id]);
}
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Product Management - Store System</title>
        <link rel="stylesheet" href="products.css" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
       <style>
        body,html{
            overflow-y:scroll;
        }
     
       </style>
    </head>
<body>
<div class="sidebar">
        <div class="user-info" style="padding: 15px; text-align: center; border-bottom: 1px solid #ddd;">
            <img class="profile-image" src="images/<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" /> 
            <div class="profile-name"><?= htmlspecialchars($username) ?><small style="color:rgb(145, 255, 0);font-weight:bold;font-size:2rem;text-shadow:none;margin-left:10px;">.</small></div>
        </div>
        <div class="logo">My Store</div>
        <nav>
            <a href="owner_dashboard.php" class="active"><i class="fa-solid fa-house"></i>Home</a>
            <a href="product_management.php" style="background:#00aaa2;color:white;"><i class="fas fa-box-open" ></i>Products</a>
            <a href="promotions_management.php"><i class="fas fa-tags"></i>Promotions</a>
            <a href="analytics.php"><i class="fas fa-chart-pie"></i>Analytics</a>
             <a href="AI_business_intelligence.php"><i class="fa-solid fa-robot"></i>Business Intel</a>
    
            <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i></a>
            <a href="settings.php" class="settings"><i class="fa-solid fa-gear"  style="cursor:pointer"></i></a>
        </nav>
    </div>
    
  

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3" style="position: relative;">
            <h2>Product Management</h2>
            <form class="d-flex" method="GET" action="product_management.php" style="flex-grow: 1; margin-left: 20px;">
                <input class="form-control me-2 search-bar" type="search" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" />
                <button class="btn search-btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <button id="addProductBtn" class="btn btn-add" >
            <i class="fa-solid fa-plus"></i> Add New Product
            </button>
            <button id="bulkUploadBtn" class="btn btn-primary ms-2" >
            <i class="fa-solid fa-upload"></i> Bulk Upload
            </button>
        </div>

        <?php if (isset($_SESSION['bulk_upload_results'])): ?>
            <?php $results = $_SESSION['bulk_upload_results']; unset($_SESSION['bulk_upload_results']); ?>
            <div class="alert <?= $results['error_count'] > 0 ? 'alert-warning' : 'alert-success' ?> mb-3">
                <h5>Bulk Upload Results:</h5>
                <p>Successfully imported: <?= $results['success_count'] ?> products</p>
                <p>Failed: <?= $results['error_count'] ?> products</p>
                
                <?php if (!empty($results['errors'])): ?>
                    <details>
                        <summary>Error Details</summary>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($results['errors'] as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <div class="product-container">

        <?php if (count($products) === 0): ?>
            <p class="text-muted">No products found.</p>
        <?php else: ?>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="images/<?= htmlspecialchars(basename($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" />
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <h5 class="product-title"><?= isset($product['name']) ? htmlspecialchars($product['name']) : 'Unnamed Product' ?></h5>
                        <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        <p class="product-price"><strong>Price:</strong> R <?= number_format($product['price'], 2) ?></p>
                        <p class="product-stock"><strong>Stock:</strong> <?= intval($product['stock_quantity']) ?></p>
                        <div class="product-actions">
            <button class="btn btn-edit btn-sm btn-outline-primary me-2 product-action-btn editProductBtn" data-product='<?= json_encode($product) ?>' title="Edit">
                <i class="fa fa-edit "></i>
            </button>
            <a href="product_management.php?delete=<?= $product['product_id'] ?>" class="btn btn-delete btn-sm btn-outline-danger product-action-btn" onclick="return confirm('Are you sure you want to delete this product?');" title="Delete">
                <i class="fa fa-trash "></i>
            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>

    </div>

    <!-- Modal for Add/Edit Product -->
    <div id="productModal" class="modal" style="display:none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; position: relative;">
            <span id="closeModal" style="color: transparent; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2 id="modalTitle">Add Product</h2>
            <form id="productForm" method="POST" action="product_form.php" novalidate>
                <input type="hidden" id="product_id" name="product_id" value="" />
                <div class="mb-3">
                    <label for="name" class="form-label">Product Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required />
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" placeholder="Enter product description" name="description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="price" class="form-label">Price (ZAR) *</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required />
                </div>
                <div class="mb-3">
                    <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                    <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" required />
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" class="form-control" id="category" name="category" />
                </div>
                <div class="mb-3">
                    <label for="model_3d_url" class="form-label">3D Model URL (optional, for Furniture category)</label>
                    <input type="url" class="form-control" id="model_3d_url" name="model_3d_url" placeholder="https://example.com/3dmodel" />
                </div>
                <div class="mb-3">
                    <label for="image_url" class="form-label">Image Filename (from images folder)</label>
                    <input type="text" class="form-control" id="image_url" name="image_url" placeholder="e.g. product_image.jpeg" />
                </div>
                <button type="submit" class="btn btn-success" id="submitBtn"><i class="fa-solid fa-plus"></i> Add</button>
                <button type="button" class="btn btn-secondary ms-2" id="cancelBtn"><i class="fa-solid fa-xmark"></i> Cancel</button>
            </form>
        </div>
    </div>

    <!-- Modal for Bulk Upload -->
    <div id="bulkUploadModal" class="modal" style="display:none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; position: relative;">
            <span class="close" style="float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2>Bulk Product Upload</h2>
            <p>Upload a CSV file with product data. The CSV should have the following columns:</p>
            <ul>
                <li>name (required)</li>
                <li>description</li>
                <li>price (required)</li>
                <li>stock_quantity (required)</li>
                <li>category</li>
                <li>image_url</li>
            </ul>
            <form method="POST" action="bulk_upload.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csv_file" class="form-label">CSV File</label>
                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required />
                </div>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-upload"></i> Upload</button>
                <button type="button" class="btn btn-secondary ms-2" id="cancelBulkUpload"><i class="fa-solid fa-xmark"></i> Cancel</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('productModal');
        const bulkUploadModal = document.getElementById('bulkUploadModal');
        const addProductBtn = document.getElementById('addProductBtn');
        const bulkUploadBtn = document.getElementById('bulkUploadBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelBulkUpload = document.getElementById('cancelBulkUpload');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const productForm = document.getElementById('productForm');

        // Open modal for adding new product
        addProductBtn.onclick = () => {
            modal.style.display = 'block';
            modalTitle.textContent = 'Add Product';
            submitBtn.textContent = 'Add Product';
            productForm.action = 'product_form.php';
            productForm.reset();
            document.getElementById('product_id').value = '';
        };

        // Open modal for bulk upload
        bulkUploadBtn.onclick = () => {
            bulkUploadModal.style.display = 'block';
        };

        // Open modal for editing product
        document.querySelectorAll('.editProductBtn').forEach(button => {
            button.addEventListener('click', () => {
                const product = JSON.parse(button.getAttribute('data-product'));
                modal.style.display = 'block';
                modalTitle.textContent = 'Edit Product';
                submitBtn.textContent = 'Update Product';
                productForm.action = 'product_form.php?edit=' + product.product_id;
                document.getElementById('product_id').value = product.product_id || '';
                document.getElementById('name').value = product.name || '';
                document.getElementById('description').value = product.description || '';
                document.getElementById('price').value = product.price || '';
                document.getElementById('stock_quantity').value = product.stock_quantity || '';
                document.getElementById('category').value = product.category || '';
                document.getElementById('model_3d_url').value = product.model_3d_url || '';
                document.getElementById('image_url').value = product.image_url || '';
            });
        });

        // Close modals
        closeModal.onclick = () => {
            modal.style.display = 'none';
        };
        cancelBtn.onclick = () => {
            modal.style.display = 'none';
        };
        cancelBulkUpload.onclick = () => {
            bulkUploadModal.style.display = 'none';
        };

        // Close modals when clicking outside the modal content
        window.onclick = (event) => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
            if (event.target == bulkUploadModal) {
                bulkUploadModal.style.display = 'none';
            }
        };
    </script>
</body>
</html>
