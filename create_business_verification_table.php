<?php
require_once 'db_connection.php';

// Create the business verification table
try {
    // Create the approved_business_entities table
    $sql = "
        CREATE TABLE IF NOT EXISTS approved_business_entities (
            id SERIAL PRIMARY KEY,
            business_registration_number VARCHAR(50) NOT NULL,
            tax_number VARCHAR(50) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            business_type VARCHAR(100),
            registration_date DATE,
            tax_clearance_status VARCHAR(20) DEFAULT 'active',
            contact_email VARCHAR(255),
            contact_phone VARCHAR(255),
            physical_address TEXT,
            province VARCHAR(50),
            city VARCHAR(100),
            postal_code VARCHAR(10),
            annual_turnover DECIMAL(15,2),
            employee_count INTEGER,
            industry_sector VARCHAR(100),
            business_status VARCHAR(20) DEFAULT 'active',
            approval_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_by VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(business_registration_number, tax_number)
        );
    ";

    $pdo->exec($sql);

    // Create indexes separately for PostgreSQL
    $indexSql = "
        CREATE INDEX IF NOT EXISTS idx_business_reg ON approved_business_entities(business_registration_number);
        CREATE INDEX IF NOT EXISTS idx_tax_number ON approved_business_entities(tax_number);
        CREATE INDEX IF NOT EXISTS idx_business_status ON approved_business_entities(business_status);
        CREATE INDEX IF NOT EXISTS idx_province ON approved_business_entities(province);
        CREATE INDEX IF NOT EXISTS idx_business_type ON approved_business_entities(business_type);
    ";
    $pdo->exec($indexSql);

    echo "✓ Business verification table created successfully!\n";

    // Insert sample approved business entities
    $sampleBusinesses = [
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
        ]
    ];

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

    foreach ($sampleBusinesses as $business) {
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
    }

    echo "✓ Sample approved business entities inserted successfully!\n";

    // Create a function to check business eligibility
    echo "✓ Business verification system ready!\n";
    echo "\nTo check if a business is eligible for registration, use:\n";
    echo "SELECT * FROM approved_business_entities \n";
    echo "WHERE business_registration_number = ? AND tax_number = ? AND business_status = 'active'\n";

} catch (Exception $e) {
    echo "✗ Error creating business verification table: " . $e->getMessage() . "\n";
}
?>
