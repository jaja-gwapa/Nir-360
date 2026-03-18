<?php
/**
 * One-time diagnostic: list missing columns in users and profiles tables.
 * Open in browser: .../nir360/public/check_schema.php
 * Delete this file after use.
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$boot = require $baseDir . '/bootstrap.php';
$pdo = $boot['pdo'];

if ($pdo === null) {
    die('Database not connected. Check config/database.php and MySQL.');
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>NIR360 schema check</title>";
echo "<style>body{font-family:sans-serif;max-width:720px;margin:2rem auto;padding:0 1rem;} code{background:#f0f0f0;padding:2px 6px;} .ok{color:green;} .miss{color:#b91c1c;} pre{background:#f8fafc;padding:1rem;overflow:auto;}</style></head><body>";
echo "<h1>NIR360 – Schema check</h1>";

$requiredUsers = [
    'id' => 'INT (PK, usually exists)',
    'username' => 'VARCHAR(50) NOT NULL UNIQUE',
    'email' => 'VARCHAR(255) NOT NULL UNIQUE',
    'mobile' => 'VARCHAR(20) NOT NULL UNIQUE',
    'password_hash' => 'VARCHAR(255) NOT NULL',
    'role' => "ENUM or VARCHAR – e.g. 'user','responder','admin'",
    'is_phone_verified' => 'TINYINT(1) DEFAULT 0',
    'is_verified' => 'TINYINT(1) DEFAULT 0',
    'verification_status' => "ENUM('pending','verified','rejected') DEFAULT 'pending'",
    'government_id_path' => 'VARCHAR(512) NULL',
    'province' => "VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental'",
    'city' => "VARCHAR(100) NOT NULL DEFAULT 'Bago City'",
    'barangay' => 'VARCHAR(255) NULL',
    'street_address' => 'VARCHAR(512) NULL',
    'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'DATETIME ON UPDATE CURRENT_TIMESTAMP',
    'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
    'profile_photo' => 'VARCHAR(255) NULL',
    'latitude' => 'DECIMAL(10,8) NULL',
    'longitude' => 'DECIMAL(11,8) NULL',
    'location_address' => 'TEXT NULL',
];

$requiredProfiles = [
    'id', 'user_id', 'full_name', 'birthdate', 'address', 'province', 'barangay', 'city',
    'street_address', 'emergency_contact_name', 'emergency_contact_mobile',
    'created_at', 'updated_at',
];

$alterUsers = [
    'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(50) NOT NULL UNIQUE AFTER id;",
    'verification_status' => "ALTER TABLE users ADD COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending' AFTER is_verified;",
    'government_id_path' => "ALTER TABLE users ADD COLUMN government_id_path VARCHAR(512) NULL;",
    'province' => "ALTER TABLE users ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT 'Negros Occidental';",
    'city' => "ALTER TABLE users ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT 'Bago City';",
    'barangay' => "ALTER TABLE users ADD COLUMN barangay VARCHAR(255) NULL;",
    'street_address' => "ALTER TABLE users ADD COLUMN street_address VARCHAR(512) NULL;",
    'is_active' => "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;",
    'profile_photo' => "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL;",
    'latitude' => "ALTER TABLE users ADD COLUMN latitude DECIMAL(10,8) NULL;",
    'longitude' => "ALTER TABLE users ADD COLUMN longitude DECIMAL(11,8) NULL;",
    'location_address' => "ALTER TABLE users ADD COLUMN location_address TEXT NULL;",
];

$stmt = $pdo->query("SHOW COLUMNS FROM users");
$usersCols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$stmt = $pdo->query("SHOW COLUMNS FROM profiles");
$profilesCols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$missingUsers = [];
foreach (array_keys($requiredUsers) as $col) {
    if (!in_array($col, $usersCols, true)) {
        $missingUsers[] = $col;
    }
}

$missingProfiles = [];
foreach ($requiredProfiles as $col) {
    if (!in_array($col, $profilesCols, true)) {
        $missingProfiles[] = $col;
    }
}

echo "<h2>Table: <code>users</code></h2>";
if (empty($missingUsers)) {
    echo "<p class='ok'>All required columns are present. Registration and admin features should work.</p>";
} else {
    echo "<p class='miss'><strong>Missing columns:</strong> " . implode(', ', $missingUsers) . "</p>";
    echo "<p>Run the following in phpMyAdmin (skip any line that gives &quot;Duplicate column name&quot;):</p>";
    echo "<pre>";
    echo "USE nir360;\n\n";
    foreach ($missingUsers as $col) {
        if (isset($alterUsers[$col])) {
            echo $alterUsers[$col] . "\n";
        } else {
            echo "-- Add column: " . $col . " (see fix_users_table.sql or schema.sql for definition)\n";
        }
    }
    echo "</pre>";
}

echo "<h2>Table: <code>profiles</code></h2>";
if (empty($missingProfiles)) {
    echo "<p class='ok'>All required columns are present.</p>";
} else {
    echo "<p class='miss'><strong>Missing columns:</strong> " . implode(', ', $missingProfiles) . "</p>";
    echo "<p>Create the table or add columns from <code>nir360/sql/schema.sql</code> (profiles section).</p>";
}

echo "<h2>Summary</h2>";
if (empty($missingUsers) && empty($missingProfiles)) {
    echo "<p class='ok'>Nothing missing. You can delete this file (check_schema.php) and try registering again.</p>";
} else {
    echo "<p>After running the SQL above, reload this page to confirm. Then delete <code>check_schema.php</code>.</p>";
}
echo "</body></html>";
