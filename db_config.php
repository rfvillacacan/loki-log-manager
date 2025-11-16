<?php
/**
 * Centralized Database Configuration
 * 
 * This file contains the database configuration for the entire application.
 * Include this file in other PHP files instead of duplicating the configuration.
 * 
 * Usage:
 *   require_once 'db_config.php';
 *   $pdo = new PDO(...);
 * 
 * @author Rolly Falco Villacacan
 * @package Loki Log Manager
 */

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'log_parser_db',
    'username' => 'root',
    'password' => '',  // Empty password for default XAMPP installation
    'charset' => 'utf8mb4'
];

/**
 * Get PDO connection instance
 * 
 * @param bool $createDatabase If true, creates database if it doesn't exist
 * @return PDO|false Returns PDO instance on success, false on failure
 */
function getDatabaseConnection($createDatabase = false) {
    global $dbConfig;
    
    try {
        if ($createDatabase) {
            // First connect without database name to create it
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}",
                $dbConfig['username'],
                $dbConfig['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        
        // Connect to the specific database
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create the log_entries table if it doesn't exist
 * 
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function createLogEntriesTable($pdo) {
    try {
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
        return true;
    } catch (PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Test database connection
 * 
 * @return array Array with 'success' (bool) and 'message' (string)
 */
function testDatabaseConnection() {
    global $dbConfig;
    
    try {
        $pdo = getDatabaseConnection();
        if ($pdo === false) {
            return [
                'success' => false,
                'message' => 'Failed to connect to database'
            ];
        }
        
        // Test query
        $stmt = $pdo->query("SELECT 1");
        $stmt->fetch();
        
        return [
            'success' => true,
            'message' => 'Database connection successful'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}
?>

