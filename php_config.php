<?php
// PHP Configuration for Log Parser
// This file can be included to set upload limits programmatically
// Note: These settings may not work on all servers due to security restrictions

// Try to set upload limits (will fail silently if not allowed)
@ini_set('upload_max_filesize', '100M');
@ini_set('post_max_size', '200M');
@ini_set('max_execution_time', '600');
@ini_set('max_input_time', '600');
@ini_set('memory_limit', '512M');

// Enable file uploads
@ini_set('file_uploads', 'On');

// Set maximum number of file uploads
@ini_set('max_file_uploads', '5');

// Function to format bytes to human readable format
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Function to get current PHP limits
function getPHPLimits() {
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
        'memory_limit' => ini_get('memory_limit'),
        'file_uploads' => ini_get('file_uploads'),
        'max_file_uploads' => ini_get('max_file_uploads')
    ];
}
?>
