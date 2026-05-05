<?php
// Script to seed 20 sample products for owner user_id = 1

require_once __DIR__ . '/../db_connection.php';


$owner_id = 1;

$products = [
    [
        'name' => 'L Couch',
        'description' => 'a cushioned piece of furniture that can seat multiple people..',
        'price' => 9499.95,
        'stock_quantity' => 35,
        'category' => 'furniture',
        'image_url' => 'images/Couch One.png'
    ],
    [
        'name' => 'Three Seater',
        'description' => 'a larger sofa designed for three people.',
        'price' => 3499.99,
        'stock_quantity' => 11,
        'category' => 'furniture',
        'image_url' => 'images/Couch Two.png'
    ],
    [
        'name' => 'Bunk Couch',
        'description' => 'multifunctional furniture piece that functions as a sofa during the day and converts into a bunk bed, or a series of stacked beds, at night.',
        'price' => 1999.99,
        'stock_quantity' => 10,
        'category' => 'furniture',
        'image_url' => 'images/Couch Four.png'
    ],
    [
        'name' => 'Gaming Chair',
        'description' => 'ergonomic seat, inspired by racing car seats, designed for long-term comfort and support during gaming, work, or streaming sessions.',
        'price' => 1499.99,
        'stock_quantity' => 75,
        'category' => 'furniture',
        'image_url' => 'images/chair 3.png'
    ],
    [
        'name' => 'Vibing Chair',
        'description' => 'chair designed for relaxation and comfort.',
        'price' => 2499.99,
        'stock_quantity' => 50,
        'category' => 'furniture',
        'image_url' => 'images/chair 1.png'
    ],
    [
        'name' => 'Coding Chair',
        'description' => 'driving continual improvement to the program.',
        'price' => 1999.99,
        'stock_quantity' => 45,
        'category' => 'furniture',
        'image_url' => 'images/chair 4.png'
    ],
    [
        'name' => 'Office Chair',
        'description' => 'chair that is designed for use at a desk in an office.',
        'price' => 1299.99,
        'stock_quantity' => 49,
        'category' => 'furniture',
        'image_url' => 'images/chair 2.png'
    ],
    [
        'name' => 'Mombasa Table',
        'description' => 'provide a convenient surface for everyday items like drinks, books, or lamps, and can also enhance the overall decor of your room.',
        'price' => 499.99,
        'stock_quantity' => 60,
        'category' => 'furniture',
        'image_url' => 'images/table 1.png'
    ],
    [
        'name' => 'Coffee Table',
        'description' => 'low table designed to be placed in a sitting area for convenient support of beverages.',
        'price' => 499.99,
        'stock_quantity' => 280,
        'category' => 'furniture',
        'image_url' => 'images/table 2.png'
    ],
    [
        'name' => 'Loung Table',
        'description' => 'Premium Samsung Galaxy S23 Ultra with S Pen.',
        'price' => 199.99,
        'stock_quantity' => 150,
        'category' => 'furniture',
        'image_url' => 'images/table 3.png'
    ],
    [
        'name' => 'Wooden Table',
        'description' => 'composed of a flat surface and one or more supports (legs).',
        'price' => 189.99,
        'stock_quantity' => 80,
        'category' => 'furniture',
        'image_url' => 'images/table 4.png'
    ],
    [
        'name' => 'Varnity Wardrobe',
        'description' => 'multifunctional furniture piece that combines storage for clothing and accessories with a built-in vanity for personal grooming and dressing.',
        'price' => 3499.99,
        'stock_quantity' => 40,
        'category' => 'furniture',
        'image_url' => 'images/w1.png'
    ],
    [
        'name' => 'Bedside Drawer',
        'description' => 'A drawer located in a nightstand, typically used for storing personal items such as books, reading glasses, or other nighttime necessities.',
        'price' => 1499.95,
        'stock_quantity' => 90,
        'category' => 'furniture',
        'image_url' => 'images/w2.png'
    ],
    [
        'name' => 'Bachelor Wardrobe',
        'description' => 'the clothing style of a single man, focusing on versatility and classic essentials like a black button-down, fitted shirts, and smart shoes for a sharp, go-anywhere look.',
        'price' => 2999.99,
        'stock_quantity' => 168,
        'category' => 'furniture',
        'image_url' => 'images/w3.png'
    ],
    [
        'name' => 'Long Wardrobe',
        'description' => 'either the practical clothing and accessories a single man might need for a classic, stylish, and versatile look, focusing on essentials like fitted shirts, quality denim, and sharp shoes; or a more specific type of furniture, like the "Shello 1 Door Bachelor Wardrobe" offered by City Furniture, which is a locally manufactured piece of furniture designed for organizing clothes.',
        'price' => 1799.99,
        'stock_quantity' => 37,
        'category' => 'furniture',
        'image_url' => 'images/w4.png'
    ]
    // [
    //     'name' => 'Washing Machine',
    //     'description' => 'Front-loading washing machine with multiple wash programs.',
    //     'price' => 8999.00,
    //     'stock_quantity' => 45,
    //     'category' => 'Home & Garden',
    //     'image_url' => 'images/Washing Machine.jpg'
    // ],
    // [
    //     'name' => 'Wall Clock',
    //     'description' => 'Elegant wall clock with a modern design.',
    //     'price' => 499.00,
    //     'stock_quantity' => 29,
    //     'category' => 'Home & Garden',
    //     'image_url' => 'images/Wall Clock.webp'
    // ],
    // [
    //     'name' => 'Hair care Set',
    //     'description' => 'Hair care Set',
    //     'price' => 699.00,
    //     'stock_quantity' => 90,
    //     'category' => 'Beauty',
    //     'image_url' => 'images/Hair care Set.webp'
    // ],
    // [
    //     'name' => 'Hair Dryer',
    //     'description' => 'Powerful hair dryer with multiple heat settings.',
    //     'price' => 399.00,
    //     'stock_quantity' => 65,
    //     'category' => 'Beauty',
    //     'image_url' => 'images/Hair Dryer.webp'
    // ],
    // [
    //     'name' => 'Mirrorless Camera',
    //     'description' => 'Capture stunning photos with this mirrorless camera.',
    //     'price' => 1299.00,
    //     'stock_quantity' => 30,
    //     'category' => 'Electronics',
    //     'image_url' => 'images/Mirrorless Camera.webp'
    // ]
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