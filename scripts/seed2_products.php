
<?php
// Script to seed 20 sample products for owner user_id = 1

require_once __DIR__ . '/../db_connection.php';

$owner_id = 1;

$products = [
    [
        'name' => 'Air force 1 Sneakers',
        'description' => 'Classic Air Force 1 sneakers with premium leather.',
        'price' => 2100.99,
        'stock_quantity' => 150,
        'category' => 'Footwear',
        'image_url' => 'images/airforce1.webp'
    ],
    [
        'name' => 'Adidas Ultraboost',
        'description' => 'Comfortable Adidas Ultraboost running shoes.',
        'price' => 1700.50,
        'stock_quantity' => 110,
        'category' => 'Footwear',
        'image_url' => 'images/Adidas_Ultraboost.jpeg'
    ],
    [
        'name' => 'Nike Air Max 270',
        'description' => 'Stylish Nike Air Max 270 with air cushioning.',
        'price' => 2799.00,
        'stock_quantity' => 100,
        'category' => 'Footwear',
        'image_url' => 'images/Air_max.webp'
    ],
    [
        'name' => 'Puma RS-X',
        'description' => 'Bold Puma RS-X sneakers with retro design.',
        'price' => 2399.99,
        'stock_quantity' => 75,
        'category' => 'Footwear',
        'image_url' => 'images/pumaRS-X.webp'
    ],
    [
        'name' => 'Puma Future soccer boots',
        'description' => 'High-performance Puma Future soccer boots for agility.',
        'price' => 1699.00,
        'stock_quantity' => 50,
        'category' => 'Sports',
        'image_url' => 'images/pumaFuture.avif'
    ],
    [
        'name' => 'Adidas Copa soccer boots',
        'description' => 'Classic Adidas Copa soccer boots with leather upper.',
        'price' => 1599.99,
        'stock_quantity' => 45,
        'category' => 'Sports',
        'image_url' => 'images/adidasCopa.avif'
    ],
    [
        'name' => 'Nike Mercurial soccer boots',
        'description' => 'Lightweight Nike Mercurial soccer boots for speed.',
        'price' => 1899.00,
        'stock_quantity' => 49,
        'category' => 'Sports',
        'image_url' => 'images/nikeMec.jpg'
    ],
    [
        'name' => 'Adidas Predator soccer boots',
        'description' => 'Adidas Predator soccer boots with control frame.',
        'price' => 1799.99,
        'stock_quantity' => 60,
        'category' => 'Sports',
        'image_url' => 'images/adidasPredator.avif'
    ],
    [
        'name' => 'Iphone 14 Pro Max',
        'description' => 'Latest iPhone 14 Pro Max with advanced features.',
        'price' => 24999.99,
        'stock_quantity' => 280,
        'category' => 'Mobile Phones',
        'image_url' => 'images/iphone14.jpeg'
    ],
    [
        'name' => 'Samsung Galaxy S23 Ultra',
        'description' => 'Premium Samsung Galaxy S23 Ultra with S Pen.',
        'price' => 19999.99,
        'stock_quantity' => 150,
        'category' => 'Mobile Phones',
        'image_url' => 'images/s23.jpeg'
    ],
    [
        'name' => 'Laptop Dell XPS 13',
        'description' => 'High-performance Dell XPS 13 laptop with InfinityEdge display.',
        'price' => 17999.99,
        'stock_quantity' => 80,
        'category' => 'Laptops',
        'image_url' => 'images/Laptop Dell XPS 13.webp'
    ],
    [
        'name' => 'Calculator Casio',
        'description' => 'Casio scientific calculator with advanced functions.',
        'price' => 399.99,
        'stock_quantity' => 90,
        'category' => 'Calculators',
        'image_url' => 'images/Calculator Casio.jpg'
    ],
    [
        'name' => 'Washing Machine',
        'description' => 'Front-loading washing machine with multiple wash programs.',
        'price' => 8999.00,
        'stock_quantity' => 45,
        'category' => 'Home Appliances',
        'image_url' => 'images/Washing Machine.jpg'
    ],
    [
        'name' => 'Mirrorless Camera',
        'description' => 'Capture stunning photos with this mirrorless camera.',
        'price' => 1299.00,
        'stock_quantity' => 30,
        'category' => 'Photography',
        'image_url' => 'images/Mirrorless Camera.webp'
    ],
    [
        'name' => 'Wall Clock',
        'description' => 'Elegant wall clock with a modern design.',
        'price' => 499.00,
        'stock_quantity' => 29,
        'category' => 'Home Decor',
        'image_url' => 'images/Wall Clock.webp'
    ],
    [
        'name' => 'Hair care Set',
        'description' => 'Hair care Set',
        'price' => 699.00,
        'stock_quantity' => 90,
        'category' => 'Personal Care',
        'image_url' => 'images/Hair care Set.webp'
    ],
    [
        'name' => 'Hair Dryer',
        'description' => 'Powerful hair dryer with multiple heat settings.',
        'price' => 399.00,
        'stock_quantity' => 65,
        'category' => 'Personal Care',
        'image_url' => 'images/Hair Dryer.webp'
    ],
    [
        'name' => 'Samsung smartwatch',
        'description' => 'Stylish Samsung smartwatch with health tracking features.',
        'price' => 1299.00,
        'stock_quantity' => 168,
        'category' => 'Watches',
        'image_url' => 'images/Samsung smartwatch.webp'
    ],
    [
        'name' => 'Casio G-Shock Watch',
        'description' => 'Durable Casio G-Shock watch with multiple features.',
        'price' => 999.00,
        'stock_quantity' => 37,
        'category' => 'Watches',
        'image_url' => 'images/Casio G-Shock Watch.webp'
    ],
    [
        'name' => 'Huawei MatePad Pro',
        'description' => 'High-performance tablet with stylus support.',
        'price' => 8999.99,
        'stock_quantity' => 40,
        'category' => 'Tablets',
        'image_url' => 'images/huawei MatePad Pro.webp'
    ],
];

foreach ($products as $product) {
    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category, image_url, owner_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $product['name'],
        $product['description'],
        $product['price'],
        $product['stock_quantity'],
        $product['category'],
        $product['image_url'],
        $owner_id
    ]);
}

echo "Inserted " . count($products) . " products for owner_id = $owner_id.\n";
?>
