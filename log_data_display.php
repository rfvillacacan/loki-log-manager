<?php
/**
 * Log Data Display
 * Advanced filtering and analysis interface for imported log data
 * 
 * @author Rolly Falco Villacacan
 * @package Loki Log Manager
 */

// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHP configuration
if (file_exists('php_config.php')) {
    include_once 'php_config.php';
}

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'log_parser_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Function to handle date filtering for the specific timestamp format YYYYMMDDTHH:MM:SSZ
function buildDateFilter($dateFromFilter, $dateToFilter) {
    $conditions = [];
    $params = [];
    
    if (!empty($dateFromFilter)) {
        // Convert YYYYMMDDTHH:MM:SSZ to YYYY-MM-DD for comparison
        $conditions[] = "DATE(CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2))) >= ?";
        $params[] = $dateFromFilter;
    }
    
    if (!empty($dateToFilter)) {
        // Convert YYYYMMDDTHH:MM:SSZ to YYYY-MM-DD for comparison
        $conditions[] = "DATE(CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2))) <= ?";
        $params[] = $dateToFilter;
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

// Handle truncate table request
if (isset($_POST['action']) && $_POST['action'] === 'truncate_table') {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Truncate the table
        $pdo->exec("TRUNCATE TABLE log_entries");
        
        echo json_encode(['success' => true, 'message' => 'Table truncated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error truncating table: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX requests for DataTables
if (isset($_POST['action']) && $_POST['action'] === 'get_data') {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // DataTables parameters
        $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';
        
        // Filter parameters
        $hostnameFilter = isset($_POST['hostname_filter']) ? $_POST['hostname_filter'] : '';
        $logLevelFilter = isset($_POST['log_level_filter']) ? $_POST['log_level_filter'] : '';
        $dateFromFilter = isset($_POST['date_from_filter']) ? $_POST['date_from_filter'] : '';
        $dateToFilter = isset($_POST['date_to_filter']) ? $_POST['date_to_filter'] : '';
        
        // Column mapping for ordering
        $columns = ['id', 'timestamp', 'hostname', 'log_level', 'remaining_log_message', 'created_at'];
        $orderBy = $columns[$orderColumn] ?? 'id';
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if (!empty($searchValue)) {
            $whereConditions[] = "(timestamp LIKE ? OR hostname LIKE ? OR log_level LIKE ? OR remaining_log_message LIKE ?)";
            $searchParam = "%$searchValue%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($hostnameFilter)) {
            $whereConditions[] = "hostname = ?";
            $params[] = $hostnameFilter;
        }
        
        if (!empty($logLevelFilter)) {
            $whereConditions[] = "log_level = ?";
            $params[] = $logLevelFilter;
        }
        
        // Handle date filtering with the correct timestamp format
        $dateFilter = buildDateFilter($dateFromFilter, $dateToFilter);
        $whereConditions = array_merge($whereConditions, $dateFilter['conditions']);
        $params = array_merge($params, $dateFilter['params']);
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM log_entries $whereClause";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get filtered data
        $dataQuery = "SELECT * FROM log_entries $whereClause ORDER BY $orderBy $orderDir LIMIT $start, $length";
        $dataStmt = $pdo->prepare($dataQuery);
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTables
        $formattedData = [];
        foreach ($data as $row) {
            $formattedData[] = [
                $row['id'],
                htmlspecialchars($row['timestamp']),
                htmlspecialchars($row['hostname']),
                '<span class="log-level log-level-' . strtolower($row['log_level']) . '">' . htmlspecialchars($row['log_level']) . '</span>',
                '<div class="log-message">' . htmlspecialchars(substr($row['remaining_log_message'], 0, 100)) . 
                (strlen($row['remaining_log_message']) > 100 ? '...' : '') . '</div>',
                $row['created_at']
            ];
        }
        
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $formattedData
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX requests for filter options
if (isset($_POST['action']) && $_POST['action'] === 'get_filter_options') {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get unique hostnames
        $hostnameStmt = $pdo->query("SELECT DISTINCT hostname FROM log_entries ORDER BY hostname");
        $hostnames = $hostnameStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get unique log levels
        $logLevelStmt = $pdo->query("SELECT DISTINCT log_level FROM log_entries ORDER BY log_level");
        $logLevels = $logLevelStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get date range - handle the specific timestamp format YYYYMMDDTHH:MM:SSZ
        $dateRangeStmt = $pdo->query("SELECT 
            MIN(DATE(CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2)))) as min_date, 
            MAX(DATE(CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2)))) as max_date 
            FROM log_entries 
            WHERE timestamp REGEXP '^[0-9]{8}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$'");
        $dateRange = $dateRangeStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get statistics
        $statsStmt = $pdo->query("SELECT 
            COUNT(*) as total_entries,
            COUNT(DISTINCT hostname) as unique_hosts,
            COUNT(DISTINCT log_level) as unique_levels
        FROM log_entries");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'hostnames' => $hostnames,
            'log_levels' => $logLevels,
            'date_range' => $dateRange,
            'stats' => $stats
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle export request
if (isset($_GET['export'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get filter parameters from URL
        $hostnameFilter = isset($_GET['hostname_filter']) ? $_GET['hostname_filter'] : '';
        $logLevelFilter = isset($_GET['log_level_filter']) ? $_GET['log_level_filter'] : '';
        $dateFromFilter = isset($_GET['date_from_filter']) ? $_GET['date_from_filter'] : '';
        $dateToFilter = isset($_GET['date_to_filter']) ? $_GET['date_to_filter'] : '';
        $searchValue = isset($_GET['search_value']) ? $_GET['search_value'] : '';
        
        // Build WHERE clause (same logic as DataTable)
        $whereConditions = [];
        $params = [];
        
        if (!empty($searchValue)) {
            $whereConditions[] = "(timestamp LIKE ? OR hostname LIKE ? OR log_level LIKE ? OR remaining_log_message LIKE ?)";
            $searchParam = "%$searchValue%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($hostnameFilter)) {
            $whereConditions[] = "hostname = ?";
            $params[] = $hostnameFilter;
        }
        
        if (!empty($logLevelFilter)) {
            $whereConditions[] = "log_level = ?";
            $params[] = $logLevelFilter;
        }
        
        // Handle date filtering with the correct timestamp format
        $dateFilter = buildDateFilter($dateFromFilter, $dateToFilter);
        $whereConditions = array_merge($whereConditions, $dateFilter['conditions']);
        $params = array_merge($params, $dateFilter['params']);
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $filename = 'log_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write header
        fputcsv($output, ['ID', 'Timestamp', 'Hostname', 'Log Level', 'Remaining Log Message', 'Created At']);
        
        // Write filtered data
        $query = "SELECT * FROM log_entries $whereClause ORDER BY id";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        echo "Export failed: " . $e->getMessage();
    }
}

// Get initial statistics
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $statsStmt = $pdo->query("SELECT COUNT(*) as total FROM log_entries");
    $totalEntries = $statsStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    $totalEntries = 0;
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Data Display - Advanced Analytics</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
    
    <!-- DateRangePicker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
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
        .stats-section {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        .filters-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #856404;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .log-level {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .log-level-error {
            background: #f8d7da;
            color: #721c24;
        }
        .log-level-critical {
            background: #721c24;
            color: white;
        }
        .log-level-warning {
            background: #fff3cd;
            color: #856404;
        }
        .log-level-notice {
            background: #d1ecf1;
            color: #0c5460;
        }
        .log-level-info {
            background: #d4edda;
            color: #155724;
        }
        .log-level-alert {
            background: #f8d7da;
            color: #721c24;
        }
        .log-message {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            word-break: break-word;
        }
        
        /* Fix for DataTable message column overflow */
        #log-table td:nth-child(6) {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            word-break: break-word;
            overflow: hidden;
        }
        
        /* Additional fix for message column */
        .message-column {
            max-width: 300px !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            white-space: normal !important;
            word-break: break-word !important;
            overflow: hidden !important;
        }
        .dataTables_wrapper {
            margin-top: 20px;
        }
        .dataTables_filter {
            margin-bottom: 15px;
        }
        .dataTables_length {
            margin-bottom: 15px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
            margin-bottom: 20px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Custom Modal Styles */
        .custom-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .custom-modal-overlay.show {
            display: flex;
        }
        .custom-modal {
            background: white;
            border-radius: 8px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .custom-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .custom-modal-header h3 {
            margin: 0;
            font-size: 20px;
        }
        .custom-modal-body {
            padding: 25px;
            color: #333;
            line-height: 1.6;
        }
        .custom-modal-body .warning-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 15px;
        }
        .custom-modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .custom-modal-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .custom-modal-btn-primary {
            background: #667eea;
            color: white;
        }
        .custom-modal-btn-primary:hover {
            background: #5568d3;
        }
        .custom-modal-btn-secondary {
            background: #6c757d;
            color: white;
        }
        .custom-modal-btn-secondary:hover {
            background: #5a6268;
        }
        .custom-modal-btn-danger {
            background: #dc3545;
            color: white;
        }
        .custom-modal-btn-danger:hover {
            background: #c82333;
        }
        
        /* Custom Toast Notification */
        .custom-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10001;
            min-width: 300px;
            max-width: 500px;
            display: none;
            animation: toastSlideIn 0.3s ease-out;
        }
        .custom-toast.show {
            display: block;
        }
        @keyframes toastSlideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .custom-toast.success {
            border-left: 4px solid #28a745;
        }
        .custom-toast.error {
            border-left: 4px solid #dc3545;
        }
        .custom-toast.warning {
            border-left: 4px solid #ffc107;
        }
        .custom-toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .custom-toast-icon {
            font-size: 24px;
        }
        .custom-toast-message {
            flex: 1;
            font-weight: 500;
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
        .footer-credit a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .footer-credit a:hover {
            text-decoration: underline;
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
            <h1>📊 Log Data Analytics</h1>
            <p>Advanced filtering and analysis of imported log data</p>
        </div>
        
        <div class="content">
            <!-- Back Link -->
            <a href="log_upload_manager.php" class="back-link">← Back to Upload Manager</a>
            
            <!-- Success Message -->
            <?php if (isset($_SESSION['imported_count'])): ?>
                <div class="success-message">
                    <strong>✅ Success!</strong> 
                    <?php echo number_format($_SESSION['imported_count']); ?> log entries have been imported to the database.
                    <?php if (isset($_SESSION['skipped_count']) && $_SESSION['skipped_count'] > 0): ?>
                        <br><strong>⚠️ Skipped:</strong> <?php echo number_format($_SESSION['skipped_count']); ?> duplicate entries (timestamp-hostname combination already exists).
                    <?php endif; ?>
                </div>
                <?php 
                unset($_SESSION['imported_count']); 
                unset($_SESSION['skipped_count']); 
                ?>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if (isset($dbError)): ?>
                <div class="error-message">
                    <strong>❌ Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Section -->
            <div class="stats-section">
                <h3>📈 Database Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number" id="total-entries"><?php echo number_format($totalEntries); ?></div>
                        <div class="stat-label">Total Entries</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="unique-hosts">-</div>
                        <div class="stat-label">Unique Hosts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="unique-levels">-</div>
                        <div class="stat-label">Log Levels</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="date-range">-</div>
                        <div class="stat-label">Date Range</div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Filters -->
            <div class="filters-section">
                <h3>🔍 Advanced Filters</h3>
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="hostname-filter">Hostname:</label>
                        <select id="hostname-filter">
                            <option value="">All Hostnames</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="log-level-filter">Log Level:</label>
                        <select id="log-level-filter">
                            <option value="">All Levels</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date-range-filter">Date Range:</label>
                        <input type="text" id="date-range-filter" placeholder="Select date range">
                    </div>
                    <div class="filter-buttons">
                        <button type="button" id="apply-filters" class="btn btn-primary">Apply Filters</button>
                        <button type="button" id="clear-filters" class="btn btn-warning">Clear All</button>
                        <button type="button" id="export-csv" class="btn btn-success">Export CSV</button>
                        <button type="button" id="truncate-table" class="btn btn-danger">🗑️ Truncate Table</button>
                    </div>
                </div>
            </div>
            
            <!-- DataTable -->
            <div id="loading" class="loading">
                <h3>Loading data...</h3>
                <p>Please wait while we fetch the log entries.</p>
            </div>
            
            <table id="log-table" class="display responsive nowrap" style="width:100%; display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>Hostname</th>
                        <th>Log Level</th>
                        <th>Created At</th>
                        <th>Message</th>
                    </tr>
                </thead>
            </table>
        </div>
        
        <!-- Footer Credit -->
        <div class="footer-credit">
            <p>Developed by <strong>Rolly Falco Villacacan</strong></p>
            <p style="font-size: 11px; margin-top: 5px;">Loki Log Manager - Simple, Fast, Effective</p>
        </div>
    </div>
    
    <!-- Credit Watermark -->
    <div class="credit-watermark">Rolly Falco Villacacan</div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
    
    <!-- DateRangePicker JS -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
    <script>
        $(document).ready(function() {
            let dataTable;
            let filterOptions = {};
            
            // Initialize DateRangePicker
            $('#date-range-filter').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });
            
            $('#date-range-filter').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            });
            
            $('#date-range-filter').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
            
            // Load filter options
            function loadFilterOptions() {
                $.ajax({
                    url: 'log_data_display.php',
                    type: 'POST',
                    data: { action: 'get_filter_options' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            console.error('Error loading filter options:', response.error);
                            return;
                        }
                        
                        filterOptions = response;
                        
                        // Populate hostname filter
                        let hostnameSelect = $('#hostname-filter');
                        response.hostnames.forEach(function(hostname) {
                            hostnameSelect.append($('<option>', {
                                value: hostname,
                                text: hostname
                            }));
                        });
                        
                        // Populate log level filter
                        let logLevelSelect = $('#log-level-filter');
                        response.log_levels.forEach(function(level) {
                            logLevelSelect.append($('<option>', {
                                value: level,
                                text: level
                            }));
                        });
                        
                        // Update statistics
                        $('#unique-hosts').text(response.stats.unique_hosts);
                        $('#unique-levels').text(response.stats.unique_levels);
                        $('#date-range').text(response.date_range.min_date + ' to ' + response.date_range.max_date);
                        
                        // Initialize DataTable
                        initializeDataTable();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading filter options:', error);
                        $('#loading').html('<div class="error-message"><strong>Error:</strong> Failed to load filter options.</div>');
                    }
                });
            }
            
            // Initialize DataTable
            function initializeDataTable() {
                dataTable = $('#log-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'log_data_display.php',
                        type: 'POST',
                        data: function(d) {
                            d.action = 'get_data';
                            d.hostname_filter = $('#hostname-filter').val();
                            d.log_level_filter = $('#log-level-filter').val();
                            
                            let dateRange = $('#date-range-filter').val();
                            if (dateRange) {
                                let dates = dateRange.split(' - ');
                                d.date_from_filter = dates[0];
                                d.date_to_filter = dates[1];
                            }
                        }
                    },
                    columns: [
                        { data: 0, width: '60px' },
                        { data: 1, width: '150px' },
                        { data: 2, width: '120px' },
                        { data: 3, width: '100px' },
                        { data: 5, width: '120px' },
                        { data: 4, width: '300px', className: 'message-column' }
                    ],
                    responsive: true,
                    colReorder: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        search: "Search all columns:",
                        lengthMenu: "Show _MENU_ entries per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        processing: "Processing...",
                        emptyTable: "No data available in table"
                    },
                    initComplete: function() {
                        $('#loading').hide();
                        $('#log-table').show();
                    }
                });
            }
            
            // Apply filters
            $('#apply-filters').click(function() {
                if (dataTable) {
                    dataTable.ajax.reload();
                }
            });
            
            // Clear filters
            $('#clear-filters').click(function() {
                $('#hostname-filter').val('');
                $('#log-level-filter').val('');
                $('#date-range-filter').val('');
                if (dataTable) {
                    dataTable.ajax.reload();
                }
            });
            
            // Export CSV with current filters
            $('#export-csv').click(function() {
                let exportUrl = '?export=1';
                
                // Add current filter values to URL
                let hostnameFilter = $('#hostname-filter').val();
                let logLevelFilter = $('#log-level-filter').val();
                let dateRange = $('#date-range-filter').val();
                let searchValue = dataTable ? dataTable.search() : '';
                
                if (hostnameFilter) {
                    exportUrl += '&hostname_filter=' + encodeURIComponent(hostnameFilter);
                }
                if (logLevelFilter) {
                    exportUrl += '&log_level_filter=' + encodeURIComponent(logLevelFilter);
                }
                if (dateRange) {
                    let dates = dateRange.split(' - ');
                    exportUrl += '&date_from_filter=' + encodeURIComponent(dates[0]);
                    exportUrl += '&date_to_filter=' + encodeURIComponent(dates[1]);
                }
                if (searchValue) {
                    exportUrl += '&search_value=' + encodeURIComponent(searchValue);
                }
                
                // Trigger download
                window.location.href = exportUrl;
            });
            
            // Truncate table
            $('#truncate-table').click(function() {
                showCustomConfirm(
                    '⚠️ Warning',
                    'WARNING: This will permanently delete ALL data from the log_entries table. This action cannot be undone. Are you sure you want to continue?',
                    'Yes, Delete All',
                    'danger'
                ).then(function(confirmed) {
                    if (confirmed) {
                        $.ajax({
                            url: 'log_data_display.php',
                            type: 'POST',
                            data: {
                                action: 'truncate_table'
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast('✅ Table truncated successfully!', 'success');
                                    // Reload the page to refresh statistics
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    showToast('❌ Error: ' + response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast('❌ Error: ' + error, 'error');
                            }
                        });
                    }
                });
            });
            
            // Auto-apply filters on change
            $('#hostname-filter, #log-level-filter').change(function() {
                if (dataTable) {
                    dataTable.ajax.reload();
                }
            });
            
            // Load initial data
            loadFilterOptions();
            
            // Refresh data every 30 seconds
            setInterval(function() {
                if (dataTable) {
                    dataTable.ajax.reload(null, false);
                }
            }, 30000);
        });
        
        // Custom confirmation modal
        function showCustomConfirm(title, message, confirmText = 'Confirm', confirmClass = 'danger') {
            return new Promise((resolve) => {
                // Create modal if it doesn't exist
                let modal = document.getElementById('customModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'customModal';
                    modal.className = 'custom-modal-overlay';
                    modal.innerHTML = `
                        <div class="custom-modal">
                            <div class="custom-modal-header">
                                <h3 id="modalTitle">⚠️ Confirmation</h3>
                            </div>
                            <div class="custom-modal-body">
                                <div class="warning-icon">⚠️</div>
                                <p id="modalMessage"></p>
                            </div>
                            <div class="custom-modal-footer">
                                <button type="button" class="custom-modal-btn custom-modal-btn-secondary" id="modalCancelBtn">Cancel</button>
                                <button type="button" class="custom-modal-btn custom-modal-btn-danger" id="modalConfirmBtn">Confirm</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    
                    // Add event listeners
                    document.getElementById('modalCancelBtn').addEventListener('click', function() {
                        closeCustomModal(false);
                    });
                    document.getElementById('modalConfirmBtn').addEventListener('click', function() {
                        closeCustomModal(true);
                    });
                    
                    // Close on overlay click
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeCustomModal(false);
                        }
                    });
                    
                    // Close on Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && modal.classList.contains('show')) {
                            closeCustomModal(false);
                        }
                    });
                }
                
                // Set modal content
                document.getElementById('modalTitle').textContent = title;
                document.getElementById('modalMessage').textContent = message;
                document.getElementById('modalConfirmBtn').textContent = confirmText;
                document.getElementById('modalConfirmBtn').className = 'custom-modal-btn custom-modal-btn-' + confirmClass;
                
                // Store resolve function
                modal._resolve = resolve;
                
                // Show modal
                modal.classList.add('show');
            });
        }
        
        function closeCustomModal(confirmed) {
            const modal = document.getElementById('customModal');
            if (modal && modal._resolve) {
                modal.classList.remove('show');
                modal._resolve(confirmed);
                modal._resolve = null;
            }
        }
        
        // Custom toast notification
        function showToast(message, type = 'success') {
            let toast = document.getElementById('customToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'customToast';
                toast.className = 'custom-toast';
                toast.innerHTML = `
                    <div class="custom-toast-content">
                        <span class="custom-toast-icon" id="toastIcon"></span>
                        <span class="custom-toast-message" id="toastMessage"></span>
                    </div>
                `;
                document.body.appendChild(toast);
            }
            
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = 'custom-toast ' + type;
            toastMessage.textContent = message;
            
            if (type === 'success') {
                toastIcon.textContent = '✅';
            } else if (type === 'error') {
                toastIcon.textContent = '❌';
            } else if (type === 'warning') {
                toastIcon.textContent = '⚠️';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>
