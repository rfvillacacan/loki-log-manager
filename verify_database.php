<?php
/**
 * Database Verification Script
 * This script verifies MySQL database setup and functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'log_parser_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

echo "=== Database Verification Report ===\n\n";

// Test 1: Check MySQL Service
echo "1. Testing MySQL Connection...\n";
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✓ MySQL service is running and accessible\n";
    echo "   ✓ Connection successful to host: {$dbConfig['host']}\n";
} catch (PDOException $e) {
    echo "   ✗ MySQL connection failed: " . $e->getMessage() . "\n";
    echo "   → Please ensure MySQL service is running in XAMPP\n";
    exit(1);
}

// Test 2: Check Database Existence
echo "\n2. Checking Database '{$dbConfig['dbname']}'...\n";
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbConfig['dbname']}'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Database '{$dbConfig['dbname']}' exists\n";
    } else {
        echo "   ✗ Database '{$dbConfig['dbname']}' does not exist\n";
        echo "   → Creating database...\n";
        $pdo->exec("CREATE DATABASE `{$dbConfig['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "   ✓ Database created successfully\n";
    }
} catch (PDOException $e) {
    echo "   ✗ Error checking database: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Connect to Specific Database
echo "\n3. Connecting to database '{$dbConfig['dbname']}'...\n";
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✓ Successfully connected to database\n";
} catch (PDOException $e) {
    echo "   ✗ Failed to connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Check Table Existence
echo "\n4. Checking table 'log_entries'...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'log_entries'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Table 'log_entries' exists\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE log_entries");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   ✓ Table structure:\n";
        foreach ($columns as $column) {
            echo "      - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Check indexes
        $stmt = $pdo->query("SHOW INDEXES FROM log_entries");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($indexes) > 0) {
            echo "   ✓ Indexes found:\n";
            $uniqueIndexes = [];
            foreach ($indexes as $index) {
                if (!in_array($index['Key_name'], $uniqueIndexes)) {
                    echo "      - {$index['Key_name']} on {$index['Column_name']}\n";
                    $uniqueIndexes[] = $index['Key_name'];
                }
            }
        }
        
        // Check record count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM log_entries");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "   ✓ Current records: " . number_format($count) . "\n";
        
    } else {
        echo "   ✗ Table 'log_entries' does not exist\n";
        echo "   → Creating table...\n";
        
        $sql = "CREATE TABLE IF NOT EXISTS log_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp VARCHAR(255) NOT NULL,
            hostname VARCHAR(255) NOT NULL,
            log_level VARCHAR(50) NOT NULL,
            remaining_log_message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_hostname (hostname),
            INDEX idx_log_level (log_level),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "   ✓ Table created successfully\n";
    }
} catch (PDOException $e) {
    echo "   ✗ Error checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Test Insert Operation
echo "\n5. Testing INSERT operation...\n";
try {
    $testData = [
        'timestamp' => date('Ymd\TH:i:s\Z'),
        'hostname' => 'TEST_HOST',
        'log_level' => 'INFO',
        'remaining_log_message' => 'Database verification test entry'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO log_entries (timestamp, hostname, log_level, remaining_log_message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$testData['timestamp'], $testData['hostname'], $testData['log_level'], $testData['remaining_log_message']]);
    
    $testId = $pdo->lastInsertId();
    echo "   ✓ INSERT successful (ID: $testId)\n";
    
    // Clean up test data
    $pdo->prepare("DELETE FROM log_entries WHERE id = ?")->execute([$testId]);
    echo "   ✓ Test data cleaned up\n";
} catch (PDOException $e) {
    echo "   ✗ INSERT test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Test SELECT Operation
echo "\n6. Testing SELECT operation...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count, COUNT(DISTINCT hostname) as hosts, COUNT(DISTINCT log_level) as levels FROM log_entries");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ SELECT successful\n";
    echo "      - Total entries: " . number_format($stats['count']) . "\n";
    echo "      - Unique hostnames: " . number_format($stats['hosts']) . "\n";
    echo "      - Unique log levels: " . number_format($stats['levels']) . "\n";
} catch (PDOException $e) {
    echo "   ✗ SELECT test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: Check PHP Extensions
echo "\n7. Checking PHP Extensions...\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'zip', 'fileinfo'];
$allExtensionsOk = true;

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ Extension '$ext' is loaded\n";
    } else {
        echo "   ✗ Extension '$ext' is NOT loaded\n";
        $allExtensionsOk = false;
    }
}

if (!$allExtensionsOk) {
    echo "   → Please enable missing extensions in php.ini\n";
}

// Test 8: Check Directory Permissions
echo "\n8. Checking Directory Permissions...\n";
$directories = ['uploads', 'temp', 'exports'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "   ✓ Directory '$dir' created\n";
        } else {
            echo "   ✗ Failed to create directory '$dir'\n";
        }
    } else {
        if (is_writable($dir)) {
            echo "   ✓ Directory '$dir' is writable\n";
        } else {
            echo "   ✗ Directory '$dir' is NOT writable\n";
            echo "   → Please set proper permissions (chmod 755 or 777)\n";
        }
    }
}

// Summary
echo "\n=== Verification Summary ===\n";
echo "✓ All database tests passed!\n";
echo "✓ Database is ready for use\n";
echo "\nNext steps:\n";
echo "1. Open setup_database.php in your browser to verify via web interface\n";
echo "2. Use log_upload_manager.php to upload and parse log files\n";
echo "3. Import parsed data to database using the 'Import to Database' button\n";
echo "4. View and analyze data in log_data_display.php\n";
?>

