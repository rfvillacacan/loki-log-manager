<?php
// Start session to access uploaded file
session_start();

// Disable error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Capture any errors that might occur
ob_start();

// Include PHP configuration
if (file_exists('php_config.php')) {
    include_once 'php_config.php';
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if we have a log file
if (!isset($_SESSION['current_log_file']) || !file_exists($_SESSION['current_log_file'])) {
    echo json_encode(['error' => 'No log file available']);
    exit;
}

$logFile = $_SESSION['current_log_file'];

// Get filter parameters
$logLevel = isset($_POST['log_level']) ? $_POST['log_level'] : '';
$search = isset($_POST['search']) ? $_POST['search'] : '';
$filePath = isset($_POST['file_path']) ? $_POST['file_path'] : '';
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

// Read log file
$logContent = file_get_contents($logFile);
$lines = explode("\n", $logContent);

// Regex patterns for different log entry types
$patterns = [
    // Basic log entry pattern
    'basic' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),([^,]+),(.+)$/',
    
    // File-related entries (ALERT, WARNING)
    'file_entry' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),(ALERT|WARNING),FILE:\s*([^,]+)\s+SCORE:\s*(\d+)\s+TYPE:\s*([^,]+)\s+SIZE:\s*(\d+)\s+FIRST_BYTES:\s*([^\/]+)/',
    
    // Results summary
    'results_summary' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),NOTICE,Results:\s*(\d+)\s*alerts,\s*(\d+)\s*warnings,\s*(\d+)\s*notices/',
    
    // System information
    'system_info' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),INFO,(.+)/',
    
    // Initialization messages
    'init_message' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),NOTICE,(.+)/',
    
    // Result messages
    'result_message' => '/^(\d{8}T\d{2}:\d{2}:\d{2}Z),([^,]+),RESULT,(.+)/'
];

// Parse log entries
$parsedEntries = [];
$stats = [
    'total' => 0,
    'alerts' => 0,
    'warnings' => 0,
    'info' => 0,
    'notices' => 0,
    'results' => 0
];

// Initialize all possible log levels to avoid undefined array key warnings
$logLevels = ['alert', 'warning', 'info', 'notice', 'result'];
foreach ($logLevels as $level) {
    if (!isset($stats[$level])) {
        $stats[$level] = 0;
    }
}

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    $stats['total']++;

    // Try to match file entry pattern first (most specific)
    if (preg_match($patterns['file_entry'], $line, $matches)) {
        $entry = [
            'timestamp' => $matches[1],
            'system' => $matches[2],
            'level' => $matches[3],
            'file_path' => $matches[4],
            'score' => $matches[5],
            'type' => $matches[6],
            'size' => $matches[7],
            'first_bytes' => $matches[8],
            'message' => "FILE: {$matches[4]} SCORE: {$matches[5]} TYPE: {$matches[6]} SIZE: {$matches[7]}"
        ];
        $levelKey = strtolower($matches[3]);
        if (isset($stats[$levelKey])) {
            $stats[$levelKey]++;
        }
    }
    // Try basic pattern for other entries
    elseif (preg_match($patterns['basic'], $line, $matches)) {
        $entry = [
            'timestamp' => $matches[1],
            'system' => $matches[2],
            'level' => $matches[3],
            'message' => $matches[4]
        ];
        $levelKey = strtolower($matches[3]);
        if (isset($stats[$levelKey])) {
            $stats[$levelKey]++;
        }
    }
    else {
        // Skip malformed entries
        continue;
    }

    $parsedEntries[] = $entry;
}

 // Apply filters
 $filteredEntries = $parsedEntries;
 
 if (!empty($logLevel)) {
     $filteredEntries = array_filter($filteredEntries, function($entry) use ($logLevel) {
         return $entry['level'] == $logLevel;
     });
 }
 
 if (!empty($search)) {
     $filteredEntries = array_filter($filteredEntries, function($entry) use ($search) {
         return stripos($entry['message'], $search) !== false;
     });
 }
 
 if (!empty($filePath)) {
     $filteredEntries = array_filter($filteredEntries, function($entry) use ($filePath) {
         return isset($entry['file_path']) && stripos($entry['file_path'], $filePath) !== false;
     });
 }
 
 // Recalculate stats based on filtered entries
 $filteredStats = [
     'total' => count($filteredEntries),
     'alerts' => 0,
     'warnings' => 0,
     'info' => 0,
     'notices' => 0,
     'results' => 0
 ];
 
 foreach ($filteredEntries as $entry) {
     $levelKey = strtolower($entry['level']);
     if (isset($filteredStats[$levelKey])) {
         $filteredStats[$levelKey]++;
     }
 }

// Pagination
$entriesPerPage = 50;
$totalPages = ceil(count($filteredEntries) / $entriesPerPage);
$offset = ($page - 1) * $entriesPerPage;
$displayEntries = array_slice($filteredEntries, $offset, $entriesPerPage);

 // Prepare response
 $response = [
     'stats' => $filteredStats, // Use filtered stats instead of original stats
     'entries' => array_values($displayEntries), // Reset array keys for JSON
     'pagination' => [
         'current_page' => $page,
         'total_pages' => $totalPages,
         'total_entries' => count($filteredEntries),
         'entries_per_page' => $entriesPerPage
     ],
     'filters' => [
         'log_level' => $logLevel,
         'search' => $search,
         'file_path' => $filePath
     ]
 ];

// Clear any output buffer to ensure clean JSON
ob_clean();

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// End output buffer
ob_end_flush();
?>
