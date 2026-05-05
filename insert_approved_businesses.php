<?php
require_once 'db_connection.php';

echo "=== Inserting Approved Business Entities ===\n\n";

try {
    // Sample approved business data
    $approvedBusinesses = [
        [
            'business_registration_number' => '2023/123456/07',
            'tax_number' => '9876543210',
            'company_name' => 'Tech Solutions SA Pty Ltd',
            'business_type' => 'Private Company',
            'registration_date' => '2023-01-15',
            'tax_clearance_status' => 'active',
            'contact_email' => 'admin@techsolutions.co.za',
            'contact_phone' => '+27 21 123 4567',
            'physical_address' => '123 Main Street, Cape Town, 8001',
            'province' => 'Western Cape',
            'city' => 'Cape Town',
            'postal_code' => '8001',
            'annual_turnover' => 2500000.00,
            'employee_count' => 15,
            'industry_sector' => 'Information Technology',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for e-commerce operations'
        ],
        [
            'business_registration_number' => '2022/987654/23',
            'tax_number' => '1234567890',
            'company_name' => 'Fashion Forward Retail CC',
            'business_type' => 'Close Corporation',
            'registration_date' => '2022-06-20',
            'tax_clearance_status' => 'active',
            'contact_email' => 'info@fashionforward.co.za',
            'contact_phone' => '+27 11 987 6543',
            'physical_address' => '456 Commerce Street, Johannesburg, 2001',
            'province' => 'Gauteng',
            'city' => 'Johannesburg',
            'postal_code' => '2001',
            'annual_turnover' => 1800000.00,
            'employee_count' => 8,
            'industry_sector' => 'Retail Fashion',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for online retail operations'
        ],
        [
            'business_registration_number' => '2024/567890/08',
            'tax_number' => '5678901234',
            'company_name' => 'Home & Garden Supplies Ltd',
            'business_type' => 'Private Company',
            'registration_date' => '2024-03-10',
            'tax_clearance_status' => 'active',
            'contact_email' => 'sales@homegarden.co.za',
            'contact_phone' => '+27 31 567 8901',
            'physical_address' => '789 Industrial Road, Durban, 4001',
            'province' => 'KwaZulu-Natal',
            'city' => 'Durban',
            'postal_code' => '4001',
            'annual_turnover' => 3200000.00,
            'employee_count' => 22,
            'industry_sector' => 'Home Improvement',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for wholesale and retail operations'
        ],
        [
            'business_registration_number' => '2023/345678/24',
            'tax_number' => '3456789012',
            'company_name' => 'Beauty Essentials Trading',
            'business_type' => 'Sole Proprietor',
            'registration_date' => '2023-09-05',
            'tax_clearance_status' => 'active',
            'contact_email' => 'contact@beautyessentials.co.za',
            'contact_phone' => '+27 21 345 6789',
            'physical_address' => '321 Wellness Avenue, Stellenbosch, 7600',
            'province' => 'Western Cape',
            'city' => 'Stellenbosch',
            'postal_code' => '7600',
            'annual_turnover' => 950000.00,
            'employee_count' => 5,
            'industry_sector' => 'Beauty and Personal Care',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for cosmetic retail operations'
        ],
        [
            'business_registration_number' => '2022/789012/09',
            'tax_number' => '7890123456',
            'company_name' => 'Sports Gear Pro Pty Ltd',
            'business_type' => 'Private Company',
            'registration_date' => '2022-11-12',
            'tax_clearance_status' => 'active',
            'contact_email' => 'orders@sportsgearpro.co.za',
            'contact_phone' => '+27 11 789 0123',
            'physical_address' => '654 Fitness Boulevard, Pretoria, 0001',
            'province' => 'Gauteng',
            'city' => 'Pretoria',
            'postal_code' => '0001',
            'annual_turnover' => 4100000.00,
            'employee_count' => 18,
            'industry_sector' => 'Sports Equipment',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for sports retail and equipment sales'
        ],
        [
            'business_registration_number' => '2024/112233/44',
            'tax_number' => '1122334455',
            'company_name' => 'Digital Marketing Hub CC',
            'business_type' => 'Close Corporation',
            'registration_date' => '2024-05-18',
            'tax_clearance_status' => 'active',
            'contact_email' => 'hello@digitalmarketinghub.co.za',
            'contact_phone' => '+27 21 112 2334',
            'physical_address' => '987 Innovation Drive, Bellville, 7530',
            'province' => 'Western Cape',
            'city' => 'Bellville',
            'postal_code' => '7530',
            'annual_turnover' => 1650000.00,
            'employee_count' => 12,
            'industry_sector' => 'Digital Marketing',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for digital marketing and advertising services'
        ],
        [
            'business_registration_number' => '2023/556677/88',
            'tax_number' => '5566778899',
            'company_name' => 'Green Energy Solutions Ltd',
            'business_type' => 'Private Company',
            'registration_date' => '2023-07-22',
            'tax_clearance_status' => 'active',
            'contact_email' => 'info@greenenergy.co.za',
            'contact_phone' => '+27 11 556 6778',
            'physical_address' => '147 Sustainable Street, Sandton, 2196',
            'province' => 'Gauteng',
            'city' => 'Sandton',
            'postal_code' => '2196',
            'annual_turnover' => 5800000.00,
            'employee_count' => 28,
            'industry_sector' => 'Renewable Energy',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for green energy products and consulting'
        ],
        [
            'business_registration_number' => '2024/334455/66',
            'tax_number' => '3344556677',
            'company_name' => 'Artisan Crafts Collective',
            'business_type' => 'Cooperative',
            'registration_date' => '2024-02-14',
            'tax_clearance_status' => 'active',
            'contact_email' => 'crafts@artisan.co.za',
            'contact_phone' => '+27 33 334 4556',
            'physical_address' => '258 Heritage Lane, Pietermaritzburg, 3201',
            'province' => 'KwaZulu-Natal',
            'city' => 'Pietermaritzburg',
            'postal_code' => '3201',
            'annual_turnover' => 720000.00,
            'employee_count' => 6,
            'industry_sector' => 'Arts and Crafts',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for handmade crafts and artisan products'
        ],
        [
            'business_registration_number' => '2023/778899/00',
            'tax_number' => '7788990011',
            'company_name' => 'Health & Wellness Center',
            'business_type' => 'Private Company',
            'registration_date' => '2023-11-30',
            'tax_clearance_status' => 'active',
            'contact_email' => 'wellness@healthcenter.co.za',
            'contact_phone' => '+27 21 778 8990',
            'physical_address' => '369 Vitality Road, Somerset West, 7130',
            'province' => 'Western Cape',
            'city' => 'Somerset West',
            'postal_code' => '7130',
            'annual_turnover' => 2100000.00,
            'employee_count' => 14,
            'industry_sector' => 'Health and Wellness',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for health supplements and wellness products'
        ],
        [
            'business_registration_number' => '2024/990011/22',
            'tax_number' => '9900112233',
            'company_name' => 'Smart Home Technologies CC',
            'business_type' => 'Close Corporation',
            'registration_date' => '2024-08-05',
            'tax_clearance_status' => 'active',
            'contact_email' => 'smart@smarthome.co.za',
            'contact_phone' => '+27 11 990 0112',
            'physical_address' => '741 Technology Park, Midrand, 1685',
            'province' => 'Gauteng',
            'city' => 'Midrand',
            'postal_code' => '1685',
            'annual_turnover' => 3900000.00,
            'employee_count' => 20,
            'industry_sector' => 'Smart Home Technology',
            'business_status' => 'active',
            'approved_by' => 'System Admin',
            'notes' => 'Approved for smart home devices and automation systems'
        ]
    ];

    // Prepare insert statement
    $insertStmt = $pdo->prepare("
        INSERT INTO approved_business_entities
        (business_registration_number, tax_number, company_name, business_type,
         registration_date, tax_clearance_status, contact_email, contact_phone,
         physical_address, province, city, postal_code, annual_turnover,
         employee_count, industry_sector, business_status, approved_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT (business_registration_number, tax_number)
        DO UPDATE SET
            company_name = EXCLUDED.company_name,
            contact_email = EXCLUDED.contact_email,
            updated_at = CURRENT_TIMESTAMP
    ");

    $insertedCount = 0;
    foreach ($approvedBusinesses as $business) {
        try {
            $insertStmt->execute([
                $business['business_registration_number'],
                $business['tax_number'],
                $business['company_name'],
                $business['business_type'],
                $business['registration_date'],
                $business['tax_clearance_status'],
                $business['contact_email'],
                $business['contact_phone'],
                $business['physical_address'],
                $business['province'],
                $business['city'],
                $business['postal_code'],
                $business['annual_turnover'],
                $business['employee_count'],
                $business['industry_sector'],
                $business['business_status'],
                $business['approved_by'],
                $business['notes']
            ]);
            $insertedCount++;
            echo "✓ Inserted: {$business['company_name']}\n";
        } catch (Exception $e) {
            echo "✗ Error inserting {$business['company_name']}: {$e->getMessage()}\n";
        }
    }

    echo "\n=== Insertion Summary ===\n";
    echo "Total businesses processed: " . count($approvedBusinesses) . "\n";
    echo "Successfully inserted: $insertedCount\n";

    // Display statistics
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) as total_businesses,
            COUNT(CASE WHEN province = 'Western Cape' THEN 1 END) as western_cape,
            COUNT(CASE WHEN province = 'Gauteng' THEN 1 END) as gauteng,
            COUNT(CASE WHEN province = 'KwaZulu-Natal' THEN 1 END) as kzn
        FROM approved_business_entities
        WHERE business_status = 'active'
    ");
    $stats = $statsStmt->fetch();

    echo "\n=== Business Distribution ===\n";
    echo "Total active businesses: {$stats['total_businesses']}\n";
    echo "Western Cape: {$stats['western_cape']}\n";
    echo "Gauteng: {$stats['gauteng']}\n";
    echo "KwaZulu-Natal: {$stats['kzn']}\n";

    // Show sample data for testing
    echo "\n=== Sample Data for Testing ===\n";
    $sampleStmt = $pdo->query("
        SELECT id as reference_number, business_registration_number, tax_number, company_name, province
        FROM approved_business_entities
        WHERE business_status = 'active'
        ORDER BY id
        LIMIT 5
    ");

    while ($row = $sampleStmt->fetch()) {
        echo "ID: {$row['reference_number']} | Reg: {$row['business_registration_number']} | Tax: {$row['tax_number']} | {$row['company_name']} ({$row['province']})\n";
    }

    echo "\n=== Testing Instructions ===\n";
    echo "Use the sample data above to test owner registration:\n";
    echo "1. Valid registration: Use any of the business registration + tax number combinations above\n";
    echo "2. Invalid registration: Use 'INVALID-REG-999' and 'INVALID-TAX-999'\n";
    echo "3. Test owner limit: Try registering more than 5 owners\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
