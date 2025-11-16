<?php
/**
 * Database Setup Script
 * One-click database initialization for Log Parser System
 * 
 * @author Rolly Falco Villacacan
 * @package Loki Log Manager
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

$setupMessage = '';
$setupStatus = '';

if (isset($_POST['setup_database'])) {
    try {
        // First, try to connect without database name to create it
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect to the specific database
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Create log_entries table
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
        
        // Test the connection and table
        $testStmt = $pdo->query("SELECT COUNT(*) as count FROM log_entries");
        $count = $testStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $setupMessage = "Database setup completed successfully! Database '{$dbConfig['dbname']}' and table 'log_entries' have been created. Current record count: $count";
        $setupStatus = 'success';
        
    } catch (PDOException $e) {
        $setupMessage = "Database setup failed: " . $e->getMessage();
        $setupStatus = 'error';
    }
}

// Test current connection
$connectionStatus = '';
$tableStatus = '';
$currentCount = 0;

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $connectionStatus = 'Connected successfully to database';
    
    // Check if table exists
    $tableStmt = $pdo->query("SHOW TABLES LIKE 'log_entries'");
    if ($tableStmt->rowCount() > 0) {
        $tableStatus = 'Table log_entries exists';
        
        // Get record count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM log_entries");
        $currentCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    } else {
        $tableStatus = 'Table log_entries does not exist';
    }
    
} catch (PDOException $e) {
    $connectionStatus = 'Connection failed: ' . $e->getMessage();
    $tableStatus = 'Cannot check table status';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Log Parser System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .status-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #495057;
        }
        .status-value {
            font-family: 'Courier New', monospace;
            color: #6c757d;
        }
        .status-success {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .setup-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border: 1px solid;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        button:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .info-section {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-section h4 {
            margin-top: 0;
            color: #0d47a1;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .navigation {
            margin-top: 30px;
            text-align: center;
        }
        .nav-link {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
        .nav-link:hover {
            background: #0056b3;
        }
        .nav-link.disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        /* Footer Credit Styles */
        .footer-credit {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 13px;
            background: #f8f9fa;
        }
        .footer-credit strong {
            color: #667eea;
        }
        .credit-watermark {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(102, 126, 234, 0.1);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            color: #667eea;
            z-index: 1000;
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Database Setup</h1>
            <p>Configure the database for Log Parser System</p>
        </div>
        
        <div class="content">
            <!-- Current Status -->
            <div class="status-section">
                <h3>📊 Current Status</h3>
                <div class="status-item">
                    <span class="status-label">Database Connection:</span>
                    <span class="status-value <?php echo strpos($connectionStatus, 'Connected') !== false ? 'status-success' : 'status-error'; ?>">
                        <?php echo htmlspecialchars($connectionStatus); ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Table Status:</span>
                    <span class="status-value <?php echo strpos($tableStatus, 'exists') !== false ? 'status-success' : 'status-error'; ?>">
                        <?php echo htmlspecialchars($tableStatus); ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Current Records:</span>
                    <span class="status-value">
                        <?php echo number_format($currentCount); ?>
                    </span>
                </div>
            </div>
            
            <!-- Setup Message -->
            <?php if ($setupMessage): ?>
                <div class="message <?php echo $setupStatus; ?>">
                    <strong><?php echo $setupStatus === 'success' ? '✅' : '❌'; ?></strong> 
                    <?php echo htmlspecialchars($setupMessage); ?>
                </div>
            <?php endif; ?>
            
            <!-- Database Setup -->
            <div class="setup-section">
                <h3>🔧 Database Setup</h3>
                <p>Click the button below to create the database and required table if they don't exist.</p>
                
                <form method="POST">
                    <button type="submit" name="setup_database">Setup Database & Table</button>
                </form>
            </div>
            
            <!-- Configuration Info -->
            <div class="info-section">
                <h4>⚙️ Configuration Details</h4>
                <p><strong>Database Name:</strong> <?php echo $dbConfig['dbname']; ?></p>
                <p><strong>Host:</strong> <?php echo $dbConfig['host']; ?></p>
                <p><strong>Username:</strong> <?php echo $dbConfig['username']; ?></p>
                <p><strong>Table:</strong> log_entries</p>
                
                <h4>📋 Table Structure</h4>
                <div class="code-block">
CREATE TABLE log_entries (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="navigation">
                <a href="log_upload_manager.php" class="nav-link <?php echo strpos($tableStatus, 'exists') === false ? 'disabled' : ''; ?>">
                    📁 Go to Upload Manager
                </a>
                <a href="log_data_display.php" class="nav-link <?php echo strpos($tableStatus, 'exists') === false ? 'disabled' : ''; ?>">
                    📊 View Data Display
                </a>
            </div>
            
            <!-- Footer Credit -->
            <div class="footer-credit">
                <p>Developed by <strong>Rolly Falco Villacacan</strong></p>
                <p style="font-size: 11px; margin-top: 5px;">Loki Log Manager - Simple, Fast, Effective</p>
            </div>
        </div>
    </div>
    
    <!-- Credit Watermark -->
    <div class="credit-watermark">Rolly Falco Villacacan</div>
</body>
</html>
