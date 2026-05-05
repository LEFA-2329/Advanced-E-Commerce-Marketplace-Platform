
<?php
// Script to seed 20 sample products for owner user_id = 1

require_once __DIR__ . '/../db_connection.php';

$owner_id = 1;

$products = [
    [
        'name' => 'Wireless Bluetooth Headphones',
        'description' => 'High-quality wireless headphones with noise cancellation.',
        'price' => 899.99,
        'stock_quantity' => 50,
        'category' => 'Electronics',
        'image_url' => 'wireless_bluetooth_headphones.jpeg'
    ],
    [
        'name' => 'Smart Fitness Watch',
        'description' => 'Track your fitness and health with this smart watch.',
        'price' => 1299.50,
        'stock_quantity' => 30,
        'category' => 'Wearables',
        'image_url' => 'images/smart_fitness_watch.jpeg'
    ],
    [
        'name' => 'Portable Power Bank 10000mAh',
        'description' => 'Compact power bank for charging devices on the go.',
        'price' => 299.00,
        'stock_quantity' => 100,
        'category' => 'Accessories',
        'image_url' => 'images/portable_power_bank.jpeg'
    ],
    [
        'name' => 'Wireless Mouse',
        'description' => 'Ergonomic wireless mouse with adjustable DPI.',
        'price' => 199.99,
        'stock_quantity' => 75,
        'category' => 'Computer Peripherals',
        'image_url' => 'images/wireless_mouse.jpeg'
    ],
    [
        'name' => 'Gaming Keyboard',
        'description' => 'Mechanical keyboard with RGB lighting.',
        'price' => 799.00,
        'stock_quantity' => 40,
        'category' => 'Gaming',
        'image_url' => 'images/gaming_keyboard.jpeg'
    ],
    [
        'name' => '4K Ultra HD Smart TV 55 inch',
        'description' => 'Experience stunning visuals with this 4K smart TV.',
        'price' => 8999.99,
        'stock_quantity' => 20,
        'category' => 'Home Entertainment',
        'image_url' => 'images/4k_ultra_hd_smart_tv.jpeg'
    ],
    [
        'name' => 'Digital Camera',
        'description' => 'Capture moments with this high-resolution digital camera.',
        'price' => 4999.00,
        'stock_quantity' => 15,
        'category' => 'Photography',
        'image_url' => 'images/digital_camera.jpeg'
    ],
    [
        'name' => 'Electric Kettle',
        'description' => 'Fast boiling electric kettle with auto shut-off.',
        'price' => 399.99,
        'stock_quantity' => 60,
        'category' => 'Kitchen Appliances',
        'image_url' => 'images/electric_kettle.jpeg'
    ],
    [
        'name' => 'Air Purifier',
        'description' => 'Keep your indoor air clean and fresh.',
        'price' => 1299.00,
        'stock_quantity' => 25,
        'category' => 'Home Appliances',
        'image_url' => 'images/air_purifier.jpeg'
    ],
    [
        'name' => 'Smartphone 128GB',
        'description' => 'Latest model smartphone with 128GB storage.',
        'price' => 6999.99,
        'stock_quantity' => 35,
        'category' => 'Mobile Phones',
        'image_url' => 'images/smartphone_128gb.jpeg'
    ],
    [
        'name' => 'Laptop Backpack',
        'description' => 'Durable backpack with padded laptop compartment.',
        'price' => 499.99,
        'stock_quantity' => 80,
        'category' => 'Bags & Accessories',
        'image_url' => 'images/laptop_backpack.jpeg'
    ],
    [
        'name' => 'Wireless Charger',
        'description' => 'Fast wireless charger compatible with most devices.',
        'price' => 299.99,
        'stock_quantity' => 90,
        'category' => 'Accessories',
        'image_url' => 'images/wireless_charger.jpeg'
    ],
    [
        'name' => 'Noise Cancelling Earbuds',
        'description' => 'Compact earbuds with active noise cancellation.',
        'price' => 799.00,
        'stock_quantity' => 45,
        'category' => 'Audio',
        'image_url' => 'images/noise_cancelling_earbuds.jpeg'
    ],
    [
        'name' => 'Smart Home Speaker',
        'description' => 'Voice-controlled smart speaker with excellent sound.',
        'price' => 1299.00,
        'stock_quantity' => 30,
        'category' => 'Smart Home',
        'image_url' => 'images/smart_home_speaker.jpeg'
    ],
    [
        'name' => 'Electric Toothbrush',
        'description' => 'Rechargeable electric toothbrush with multiple modes.',
        'price' => 499.00,
        'stock_quantity' => 50,
        'category' => 'Personal Care',
        'image_url' => 'images/electric_toothbrush.jpeg'
    ],
    [
        'name' => 'Fitness Yoga Mat',
        'description' => 'Non-slip yoga mat for all fitness levels.',
        'price' => 299.00,
        'stock_quantity' => 70,
        'category' => 'Fitness',
        'image_url' => 'images/fitness_yoga_mat.jpeg'
    ],
    [
        'name' => 'LED Desk Lamp',
        'description' => 'Adjustable LED desk lamp with touch control.',
        'price' => 399.00,
        'stock_quantity' => 65,
        'category' => 'Home & Office',
        'image_url' => 'images/led_desk_lamp.jpeg'
    ],
    [
        'name' => 'Gaming Chair',
        'description' => 'Ergonomic gaming chair with lumbar support.',
        'price' => 2999.00,
        'stock_quantity' => 20,
        'category' => 'Gaming',
        'image_url' => 'images/gaming_chair.jpeg'
    ],
    [
        'name' => 'Smart Thermostat',
        'description' => 'Control your home temperature remotely.',
        'price' => 1499.00,
        'stock_quantity' => 25,
        'category' => 'Smart Home',
        'image_url' => 'images/smart_thermostat.jpeg'
    ],
    [
        'name' => 'Portable Bluetooth Speaker',
        'description' => 'Compact speaker with powerful sound.',
        'price' => 699.00,
        'stock_quantity' => 40,
        'category' => 'Audio',
        'image_url' => 'images/portable_bluetooth_speaker.jpeg'
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
