<?php
/**
 * Log Upload Manager
 * Main interface for uploading, parsing, and managing log files
 * 
 * @author Rolly Falco Villacacan
 * @package Loki Log Manager
 */

// Start session to preserve file state across requests
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHP configuration
if (file_exists('php_config.php')) {
    include_once 'php_config.php';
}

// Include database configuration
require_once 'db_config.php';

// File upload and processing logic
$uploadDir = 'uploads/';
$tempDir = 'temp/';
$exportDir = 'exports/';

// Create directories if they don't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

// Initialize session arrays if they don't exist
if (!isset($_SESSION['uploaded_logs'])) {
    $_SESSION['uploaded_logs'] = [];
}
if (!isset($_SESSION['temp_csv_files'])) {
    $_SESSION['temp_csv_files'] = [];
}
if (!isset($_SESSION['uploaded_file_hashes'])) {
    $_SESSION['uploaded_file_hashes'] = [];
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to clean up specific files
function cleanupSpecificFiles($filePaths) {
    foreach ($filePaths as $filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
    }
}

// Function to check if file is already uploaded
function isFileAlreadyUploaded($fileName, $fileSize, $uploadedLogs) {
    foreach ($uploadedLogs as $log) {
        if ($log['original_name'] === $fileName && $log['size'] === $fileSize) {
            return true;
        }
    }
    return false;
}

// Function to parse log entries with strict 4-column format
function parseLogEntries($logFile) {
    $parsedEntries = [];
    $stats = [
        'total' => 0,
        'alerts' => 0,
        'warnings' => 0,
        'info' => 0,
        'notices' => 0,
        'results' => 0,
        'errors' => 0,
        'critical' => 0
    ];
    
    $totalLines = 0;
    $matchedLines = 0;
    
    // Process file line by line to save memory
    $handle = fopen($logFile, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            $totalLines++;
            if (empty($line)) continue;
            
            // Only process lines that follow the strict 4-column format:
            // timestamp,hostname,log_level,remaining_log_message
            if (preg_match('/^([^,]+),([^,]+),([^,]+),(.+)$/', $line, $matches)) {
                $matchedLines++;
                // Clean timestamp by removing unwanted characters at the beginning
                $timestamp = preg_replace('/^[\/\-\\\|]+/', '', trim($matches[1]));
                $hostname = trim($matches[2]);
                $logLevel = trim($matches[3]);
                $remainingLogMessage = trim($matches[4]);
                
                // Create entry with the 4-column structure
                $entry = [
                    'timestamp' => $timestamp,
                    'hostname' => $hostname,
                    'log_level' => $logLevel,
                    'remaining_log_message' => $remainingLogMessage
                ];
                
                // Update statistics
                $levelKey = strtolower($logLevel);
                if (isset($stats[$levelKey])) {
                    $stats[$levelKey]++;
                }
                
                $parsedEntries[] = $entry;
                
                // Note: Removed memory limit to preserve all entries
                // If memory becomes an issue, consider processing files in smaller chunks
            }
        }
        fclose($handle);
    }
    
    error_log("Parsed log file: " . basename($logFile) . ", Total lines: $totalLines, Matched lines: $matchedLines, Entries: " . count($parsedEntries));
    
    return ['entries' => $parsedEntries, 'stats' => $stats];
}

// Function to generate temporary CSV file
function generateTempCSV($parsedEntries, $tempDir, $logFileName) {
    $filename = 'temp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $logFileName) . '_' . time() . '.csv';
    $filepath = $tempDir . $filename;
    
    $output = fopen($filepath, 'w');
    if ($output === false) {
        error_log("Failed to create CSV file: $filepath");
        return false;
    }
    
    // Write CSV header
    fputcsv($output, ['Timestamp', 'Hostname', 'Log Level', 'Remaining Log Message']);
    
    $rowCount = 0;
    // Write data rows with additional cleaning
    foreach ($parsedEntries as $entry) {
        // Ensure timestamp is clean and properly formatted
        $cleanTimestamp = preg_replace('/^[\/\-\\\|]+/', '', trim($entry['timestamp']));
        
        fputcsv($output, [
            $cleanTimestamp,
            trim($entry['hostname']),
            trim($entry['log_level']),
            trim($entry['remaining_log_message'])
        ]);
        $rowCount++;
    }
    
    fclose($output);
    error_log("Generated CSV file: $filename, Rows: $rowCount, Entries: " . count($parsedEntries));
    return $filename;
}

// Function to merge all temporary CSV files
function mergeTempCSVs($tempCsvFiles, $exportDir, $tempDir) {
    $mergedFilename = 'merged_logs_' . date('Y-m-d_H-i-s') . '.csv';
    $mergedFilepath = $exportDir . $mergedFilename;
    
    $output = fopen($mergedFilepath, 'w');
    if ($output === false) {
        return false;
    }
    
    // Write CSV header
    fputcsv($output, ['Timestamp', 'Hostname', 'Log Level', 'Remaining Log Message']);
    
    $headerWritten = false;
    $totalRows = 0;
    
    foreach ($tempCsvFiles as $csvFile) {
        $filepath = $tempDir . $csvFile;
        error_log("Processing CSV file: $csvFile, Path: $filepath, Exists: " . (file_exists($filepath) ? 'Yes' : 'No'));
        if (file_exists($filepath)) {
            $input = fopen($filepath, 'r');
            if ($input) {
                $fileRows = 0;
                // Skip header for all files except the first
                if ($headerWritten) {
                    fgets($input); // Skip header line
                } else {
                    $headerWritten = true;
                }
                
                // Copy all data rows
                while (($line = fgets($input)) !== false) {
                    $line = trim($line);
                    if (!empty($line)) {
                        fwrite($output, $line . "\n");
                        $fileRows++;
                        $totalRows++;
                    }
                }
                fclose($input);
                
                // Debug: Log file processing info
                error_log("Processed CSV file: $csvFile, Rows: $fileRows");
            }
        } else {
            error_log("CSV file not found: $filepath");
        }
    }
    
    fclose($output);
    error_log("Merged CSV created: $mergedFilename, Total rows: $totalRows");
    return $mergedFilename;
}

// Function to create database table
function createDatabaseTable($dbConfig) {
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
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
        return $pdo;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to import CSV to database with duplicate checking
function importCSVToDatabase($csvFile, $dbConfig) {
    $pdo = createDatabaseTable($dbConfig);
    if (!$pdo) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Prepare statements for checking duplicates and inserting
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM log_entries WHERE timestamp = ? AND hostname = ?");
        $insertStmt = $pdo->prepare("INSERT INTO log_entries (timestamp, hostname, log_level, remaining_log_message) VALUES (?, ?, ?, ?)");
        
        $input = fopen($csvFile, 'r');
        if ($input) {
            // Skip header
            fgets($input);
            
            $count = 0;
            $skipped = 0;
            
            while (($line = fgets($input)) !== false) {
                $data = str_getcsv($line);
                if (count($data) >= 4) {
                    $timestamp = trim($data[0]);
                    $hostname = trim($data[1]);
                    $logLevel = trim($data[2]);
                    $message = trim($data[3]);
                    
                    // Check if this timestamp-hostname combination already exists
                    $checkStmt->execute([$timestamp, $hostname]);
                    $exists = $checkStmt->fetchColumn() > 0;
                    
                    if (!$exists) {
                        // Insert only if it doesn't exist
                        $insertStmt->execute([$timestamp, $hostname, $logLevel, $message]);
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
            }
            fclose($input);
        }
        
        $pdo->commit();
        
        // Return both inserted and skipped counts
        return ['inserted' => $count, 'skipped' => $skipped];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

// Handle file upload
$uploadMessage = '';
$uploadStatus = '';

  if (isset($_POST['upload'])) {
     if (!isset($_FILES['log_file'])) {
         $uploadMessage = 'No files were uploaded. This might be due to file size exceeding server limits.';
         $uploadStatus = 'error';
     } else {
         $uploadedFiles = $_FILES['log_file'];
         $totalFiles = count($uploadedFiles['name']);
         $successCount = 0;
         $errorCount = 0;
         $errorMessages = [];
         
                   // Check for duplicates and count new files
          $newFiles = [];
          $duplicateFiles = [];
          
          for ($i = 0; $i < $totalFiles; $i++) {
              $fileName = $uploadedFiles['name'][$i];
              $fileSize = $uploadedFiles['size'][$i];
              
              if (isFileAlreadyUploaded($fileName, $fileSize, $_SESSION['uploaded_logs'])) {
                  $duplicateFiles[] = $fileName;
              } else {
                  $newFiles[] = $i;
              }
          }
          
          // Limit the number of new files to prevent overload
          $maxFilesPerUpload = 10; // Configurable limit
          if (count($newFiles) > $maxFilesPerUpload) {
              $uploadMessage = "Too many new files selected. Maximum $maxFilesPerUpload new files allowed per upload. " . count($duplicateFiles) . " duplicate files were skipped.";
              $uploadStatus = 'error';
          } else {
                           // Process only new files (skip duplicates)
                            foreach ($newFiles as $fileIndex) {
                  $uploadedFile = [
                      'name' => $uploadedFiles['name'][$fileIndex],
                      'type' => $uploadedFiles['type'][$fileIndex],
                      'tmp_name' => $uploadedFiles['tmp_name'][$fileIndex],
                      'error' => $uploadedFiles['error'][$fileIndex],
                      'size' => $uploadedFiles['size'][$fileIndex]
                  ];
                 
                 if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
                     $fileName = $uploadedFile['name'];
                     $fileSize = $uploadedFile['size'];
                     $fileTmpName = $uploadedFile['tmp_name'];
                     $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                     
                     if ($fileSize > 50 * 1024 * 1024) {
                         $errorMessages[] = "$fileName: File size exceeds 50MB limit.";
                         $errorCount++;
                         continue;
                     }
                     elseif (!in_array($fileExt, ['log', 'txt', 'zip'])) {
                         $errorMessages[] = "$fileName: Only .log, .txt, and .zip files are allowed.";
                         $errorCount++;
                         continue;
                     }
                     else {
                                                   // Generate unique filename
                          $uniqueName = 'log_' . time() . '_' . uniqid() . '_' . $fileIndex . '.' . $fileExt;
                         $uploadPath = $uploadDir . $uniqueName;
                         
                         if (move_uploaded_file($fileTmpName, $uploadPath)) {
                             $logFile = null;
                             $extractedFiles = [];
                             
                             if ($fileExt === 'zip') {
                                                                   // Create unique temp directory for this ZIP
                                  $uniqueTempDir = $tempDir . 'zip_' . time() . '_' . $fileIndex . '/';
                                 if (!is_dir($uniqueTempDir)) {
                                     mkdir($uniqueTempDir, 0755, true);
                                 }
                                 
                                 $zip = new ZipArchive;
                                 if ($zip->open($uploadPath) === TRUE) {
                                     // Extract all files to unique temp directory
                                     $zip->extractTo($uniqueTempDir);
                                     $zip->close();
                                     
                                     // Find all log files in the extracted content
                                     $logFiles = [];
                                     $txtFiles = [];
                                     
                                     $iterator = new RecursiveIteratorIterator(
                                         new RecursiveDirectoryIterator($uniqueTempDir, RecursiveDirectoryIterator::SKIP_DOTS)
                                     );
                                     
                                     foreach ($iterator as $file) {
                                         if ($file->isFile()) {
                                             $extension = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
                                             if ($extension === 'log') {
                                                 $logFiles[] = $file->getPathname();
                                             } elseif ($extension === 'txt') {
                                                 $txtFiles[] = $file->getPathname();
                                             }
                                         }
                                     }
                                     
                                     $allFiles = array_merge($logFiles, $txtFiles);
                                     
                                     if (!empty($allFiles)) {
                                         foreach ($allFiles as $extractedFile) {
                                             if (is_file($extractedFile)) {
                                                 $extractedFiles[] = $extractedFile;
                                             }
                                         }
                                         
                                         if (!empty($extractedFiles)) {
                                             $logFile = $extractedFiles[0];
                                             $successCount++;
                                         } else {
                                             $errorMessages[] = "$fileName: No valid log files found in ZIP archive.";
                                             $errorCount++;
                                         }
                                     } else {
                                         $errorMessages[] = "$fileName: No .log or .txt files found in ZIP archive.";
                                         $errorCount++;
                                     }
                                 } else {
                                     $errorMessages[] = "$fileName: Failed to extract ZIP file.";
                                     $errorCount++;
                                 }
                             } else {
                                 $logFile = $uploadPath;
                                 $successCount++;
                             }
                             
                             // Parse the log file(s) if we have them
                             if ($logFile && file_exists($logFile)) {
                                 $totalEntries = 0;
                                 $allParsedEntries = [];
                                 
                                 if ($fileExt === 'zip' && !empty($extractedFiles)) {
                                     // Process all extracted files from ZIP
                                     foreach ($extractedFiles as $extractedFile) {
                                         if (file_exists($extractedFile)) {
                                             $parseResult = parseLogEntries($extractedFile);
                                             $allParsedEntries = array_merge($allParsedEntries, $parseResult['entries']);
                                             $totalEntries += count($parseResult['entries']);
                                         }
                                     }
                                 } else {
                                     // Process single file
                                     $parseResult = parseLogEntries($logFile);
                                     $allParsedEntries = $parseResult['entries'];
                                     $totalEntries = count($parseResult['entries']);
                                 }
                                 
                                 // Generate temporary CSV file
                                 $tempCsvFilename = generateTempCSV($allParsedEntries, $tempDir, $fileName);
                                 if ($tempCsvFilename) {
                                     // Add to session arrays
                                     $_SESSION['uploaded_logs'][] = [
                                         'original_name' => $fileName,
                                         'stored_name' => $uniqueName,
                                         'size' => $fileSize,
                                         'upload_date' => date('Y-m-d H:i:s'),
                                         'temp_csv' => $tempCsvFilename,
                                         'entries_count' => $totalEntries,
                                         'extracted_files' => $fileExt === 'zip' ? count($extractedFiles) : 1
                                     ];
                                     $_SESSION['temp_csv_files'][] = $tempCsvFilename;
                                 } else {
                                     $errorMessages[] = "$fileName: Failed to generate temporary CSV.";
                                     $errorCount++;
                                 }
                             }
                         } else {
                             $errorMessages[] = "$fileName: Failed to upload file.";
                             $errorCount++;
                         }
                     }
                 } else {
                     $errorMessages[] = "File upload failed with error code: " . $uploadedFile['error'];
                     $errorCount++;
                 }
             }
             
                           // Generate summary message
              $duplicateInfo = count($duplicateFiles) > 0 ? " " . count($duplicateFiles) . " duplicate file(s) were skipped." : "";
              
              if ($successCount > 0 && $errorCount == 0) {
                  $uploadMessage = "Successfully uploaded and processed $successCount file(s)." . $duplicateInfo;
                  $uploadStatus = 'success';
              } elseif ($successCount > 0 && $errorCount > 0) {
                  $uploadMessage = "Successfully processed $successCount file(s). $errorCount file(s) failed." . $duplicateInfo;
                  $uploadStatus = 'success';
              } else {
                  $uploadMessage = "Failed to process any files." . $duplicateInfo;
                  $uploadStatus = 'error';
              }
             
             if (!empty($errorMessages)) {
                 $uploadMessage .= " Errors: " . implode("; ", array_slice($errorMessages, 0, 3)) . (count($errorMessages) > 3 ? "..." : "");
             }
         }
     }
 }

// Handle log deletion
if (isset($_POST['delete_log']) && isset($_POST['log_index'])) {
    $logIndex = (int)$_POST['log_index'];
    if (isset($_SESSION['uploaded_logs'][$logIndex])) {
        $logToDelete = $_SESSION['uploaded_logs'][$logIndex];
        
        // Delete the uploaded file
        $uploadedFilePath = $uploadDir . $logToDelete['stored_name'];
        if (file_exists($uploadedFilePath)) {
            unlink($uploadedFilePath);
        }
        
        // Delete the temporary CSV file
        $tempCsvPath = $tempDir . $logToDelete['temp_csv'];
        if (file_exists($tempCsvPath)) {
            unlink($tempCsvPath);
        }
        
        // Clean up extracted files if this was a ZIP
        if (isset($logToDelete['extracted_files']) && $logToDelete['extracted_files'] > 1) {
            // Clean up any remaining extracted files in temp directory
            $tempFiles = glob($tempDir . '*');
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile) && basename($tempFile) !== $logToDelete['temp_csv']) {
                    unlink($tempFile);
                }
            }
        }
        
        // Remove from temp_csv_files array
        $csvIndex = array_search($logToDelete['temp_csv'], $_SESSION['temp_csv_files']);
        if ($csvIndex !== false) {
            unset($_SESSION['temp_csv_files'][$csvIndex]);
            $_SESSION['temp_csv_files'] = array_values($_SESSION['temp_csv_files']);
        }
        
        // Remove from uploaded_logs array
        unset($_SESSION['uploaded_logs'][$logIndex]);
        $_SESSION['uploaded_logs'] = array_values($_SESSION['uploaded_logs']);
        
        $uploadMessage = 'Log file deleted successfully.';
        $uploadStatus = 'success';
    }
}

// Handle import to database
if (isset($_POST['import_to_db'])) {
    if (!empty($_SESSION['temp_csv_files'])) {
        // Merge all temporary CSV files
        $mergedFilename = mergeTempCSVs($_SESSION['temp_csv_files'], $exportDir, $tempDir);
        if ($mergedFilename) {
            $mergedFilepath = $exportDir . $mergedFilename;
            
            // Import to database
            $importResult = importCSVToDatabase($mergedFilepath, $dbConfig);
            if ($importResult !== false) {
                // Store merged file info in session for export
                $_SESSION['merged_csv'] = $mergedFilename;
                $_SESSION['imported_count'] = $importResult['inserted'];
                $_SESSION['skipped_count'] = $importResult['skipped'];
                
                // Clean up temporary files
                foreach ($_SESSION['temp_csv_files'] as $tempCsv) {
                    $tempPath = $tempDir . $tempCsv;
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                }
                
                // Clear session arrays
                $_SESSION['uploaded_logs'] = [];
                $_SESSION['temp_csv_files'] = [];
                
                // Redirect to data display page
                header('Location: log_data_display.php');
                exit;
            } else {
                $uploadMessage = 'Failed to import data to database.';
                $uploadStatus = 'error';
            }
        } else {
            $uploadMessage = 'Failed to merge CSV files.';
            $uploadStatus = 'error';
        }
    } else {
        $uploadMessage = 'No files to import. Please upload at least one log file.';
        $uploadStatus = 'error';
    }
}

// Handle CSV export
if (isset($_GET['export'])) {
    // Debug: Log the expected vs actual counts
    $expectedTotal = array_sum(array_column($_SESSION['uploaded_logs'], 'entries_count'));
    $actualCsvFiles = count($_SESSION['temp_csv_files']);
    error_log("Export Debug - Expected total entries: $expectedTotal, CSV files to merge: $actualCsvFiles");
    
    foreach ($_SESSION['uploaded_logs'] as $index => $log) {
        error_log("Log $index: {$log['original_name']} - Expected: {$log['entries_count']} entries, CSV: {$log['temp_csv']}");
    }
    
    // Always create a fresh merged CSV file from current temp CSV files
    $tempCsvFiles = glob($tempDir . 'temp_*.csv');
    if (!empty($tempCsvFiles)) {
        $mergedFilename = 'merged_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $mergedFilepath = $exportDir . $mergedFilename;
        
        $output = fopen($mergedFilepath, 'w');
        if ($output !== false) {
            // Write CSV header
            fputcsv($output, ['Timestamp', 'Hostname', 'Log Level', 'Remaining Log Message']);
            
            $headerWritten = false;
            $totalRows = 0;
            
            foreach ($tempCsvFiles as $csvFile) {
                $input = fopen($csvFile, 'r');
                if ($input) {
                    $fileRows = 0;
                    // Skip header for all files except the first
                    if ($headerWritten) {
                        fgets($input); // Skip header line
                    } else {
                        $headerWritten = true;
                    }
                    
                    // Copy all data rows
                    while (($line = fgets($input)) !== false) {
                        fwrite($output, $line);
                        $fileRows++;
                        $totalRows++;
                    }
                    fclose($input);
                    
                    error_log("Processed CSV file: " . basename($csvFile) . ", Rows: $fileRows");
                }
            }
            
            fclose($output);
            error_log("Merged CSV created: $mergedFilename, Total rows: $totalRows");
            
            // Store in session for future use
            $_SESSION['merged_csv'] = $mergedFilename;
            
            // Download the file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $mergedFilename . '"');
            header('Content-Length: ' . filesize($mergedFilepath));
            readfile($mergedFilepath);
            exit;
        }
    }
}

// Handle individual CSV export
if (isset($_GET['export_single'])) {
    $csvFilename = $_GET['export_single'];
    $csvFile = $tempDir . $csvFilename;
    
    if (file_exists($csvFile) && strpos($csvFilename, 'temp_') === 0) {
        // Generate a nice filename for download
        $downloadName = 'log_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($csvFile));
        readfile($csvFile);
        exit;
    }
}

// Handle cleanup - delete all files, reset application, and clear database
if (isset($_POST['cleanup'])) {
    $deletedCount = 0;
    $dbCleared = false;
    $errorMessages = [];
    
    // Clean up uploaded files
    $uploadedFiles = glob($uploadDir . '*');
    foreach ($uploadedFiles as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    // Clean up temp files (CSV files and extracted ZIP contents)
    $tempFiles = glob($tempDir . '*');
    foreach ($tempFiles as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        } elseif (is_dir($file)) {
            // Remove directory and its contents
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $child) {
                if ($child->isDir()) {
                    rmdir($child->getRealPath());
                } else {
                    unlink($child->getRealPath());
                    $deletedCount++;
                }
            }
            rmdir($file);
        }
    }
    
    // Clean up export files (optional - you might want to keep these)
    $exportFiles = glob($exportDir . '*');
    foreach ($exportFiles as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    // Clear database table
    try {
        $pdo = getDatabaseConnection();
        if ($pdo !== false) {
            // Check if table exists first
            $stmt = $pdo->query("SHOW TABLES LIKE 'log_entries'");
            if ($stmt->rowCount() > 0) {
                // Try TRUNCATE first (faster), fallback to DELETE if it fails
                try {
                    $pdo->exec("TRUNCATE TABLE log_entries");
                    $dbCleared = true;
                } catch (PDOException $truncateError) {
                    // If TRUNCATE fails (e.g., due to foreign keys or permissions), try DELETE
                    try {
                        $pdo->exec("DELETE FROM log_entries");
                        $dbCleared = true;
                        error_log("TRUNCATE failed, used DELETE instead: " . $truncateError->getMessage());
                    } catch (PDOException $deleteError) {
                        $errorMessages[] = "Failed to clear database: " . $deleteError->getMessage();
                        error_log("Both TRUNCATE and DELETE failed. TRUNCATE error: " . $truncateError->getMessage() . " DELETE error: " . $deleteError->getMessage());
                    }
                }
            } else {
                $errorMessages[] = "Table 'log_entries' does not exist. Database may not be set up yet.";
            }
        } else {
            $errorMessages[] = "Failed to connect to database. Please check: 1) MySQL is running, 2) Database 'log_parser_db' exists, 3) Database credentials in db_config.php are correct.";
        }
    } catch (PDOException $e) {
        $errorMessages[] = "Database connection error: " . $e->getMessage();
        error_log("Cleanup database error: " . $e->getMessage());
    } catch (Exception $e) {
        $errorMessages[] = "Unexpected error: " . $e->getMessage();
        error_log("Cleanup unexpected error: " . $e->getMessage());
    }
    
    // Clear session data
    $_SESSION['uploaded_logs'] = [];
    $_SESSION['temp_csv_files'] = [];
    $_SESSION['uploaded_file_hashes'] = [];
    unset($_SESSION['merged_csv']);
    unset($_SESSION['imported_count']);
    
    // Build success message
    if ($dbCleared) {
        $message = "Cleanup completed successfully. Deleted $deletedCount files and cleared database.";
        $uploadStatus = 'success';
    } else {
        $message = "Cleanup completed. Deleted $deletedCount files.";
        if (!empty($errorMessages)) {
            $message .= " Database was NOT cleared. Errors: " . implode("; ", $errorMessages);
            $uploadStatus = 'error';
        } else {
            $uploadStatus = 'success';
        }
    }
    
    $uploadMessage = $message;
}

// Check and display PHP upload limits
$maxUploadSize = ini_get('upload_max_filesize');
$maxPostSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Upload Manager - Multi-File Processing</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
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
        .upload-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            background: #fafafa;
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
            margin-right: 10px;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-export {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-export:hover {
            background: #138496;
        }
        .btn-cleanup {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-cleanup:hover {
            background: #5a6268;
        }
        .btn-view-data {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-view-data:hover {
            background: #218838;
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
        .format-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .format-info h4 {
            margin-top: 0;
            color: #856404;
        }
        .format-info code {
            background: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .limits-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 4px;
            padding: 10px;
            margin: 15px 0;
            font-size: 14px;
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .logs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .logs-table tr:hover {
            background: #f8f9fa;
        }
        .action-buttons {
            margin-top: 30px;
            padding: 20px;
            background: #e8f5e8;
            border-radius: 6px;
            border: 1px solid #c8e6c9;
            text-align: center;
        }
        .stats-section {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .stat-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
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
            <h1>📁 Log Upload Manager</h1>
            <p>Upload multiple log files, parse them, and import to database</p>
            <div style="margin-top: 15px;">
                <form method="POST" style="display: inline;" id="cleanup-form">
                    <input type="hidden" name="cleanup" value="1">
                    <button type="button" class="btn-cleanup" onclick="showCleanupConfirm()">
                        🧹 Cleanup All Files
                    </button>
                </form>
                <a href="log_data_display.php" class="btn-view-data" style="text-decoration: none; display: inline-block; margin-left: 10px;">
                    📊 View Database Data
                </a>
            </div>
        </div>
        
        <div class="content">
            <!-- Format Information -->
            <div class="format-info">
                <h4>📋 Required Format</h4>
                <p>Your log files must follow this exact 4-column format:</p>
                <code>timestamp,hostname,log_level,remaining_log_message</code>
                <p><strong>Example:</strong> <code>20250818T04:37:57Z,EXAMPLE-SERVER-01,NOTICE,VERSION: 0.51.0 SYSTEM: EXAMPLE-SERVER-01</code></p>
                <p><em>Only lines matching this format will be processed. All other lines are ignored.</em></p>
            </div>
            
                         <!-- Server Limits -->
             <div class="limits-info">
                 <strong>⚠️ Server Limits:</strong> 
                 Upload: <?php echo $maxUploadSize; ?> per file | 
                 Post: <?php echo $maxPostSize; ?> | 
                 Timeout: <?php echo $maxExecutionTime; ?>s | 
                 Max Files: 10 per upload
             </div>
            
            <!-- Upload Form -->
            <div class="upload-section">
                <h3>📤 Upload Log File</h3>
                <form method="POST" enctype="multipart/form-data">
                                         <div class="form-group">
                         <label for="log_file">Select Log Files:</label>
                         <input type="file" name="log_file[]" id="log_file" accept=".log,.txt,.zip" multiple required>
                         <small style="color: #666;">Supported formats: .log, .txt, .zip (max <?php echo $maxUploadSize; ?> per file) - You can select multiple files</small>
                     </div>
                    <button type="submit" name="upload">Upload & Parse</button>
                </form>
            </div>
            
            <!-- Upload Message -->
            <?php if ($uploadMessage): ?>
                <div class="message <?php echo $uploadStatus; ?>">
                    <strong><?php echo $uploadStatus === 'success' ? '✅' : '❌'; ?></strong> 
                    <?php echo htmlspecialchars($uploadMessage); ?>
                </div>
            <?php endif; ?>
            
            <!-- Uploaded Logs Table -->
            <?php if (!empty($_SESSION['uploaded_logs'])): ?>
                <div class="stats-section">
                    <h3>📊 Upload Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($_SESSION['uploaded_logs']); ?></div>
                            <div class="stat-label">Files Uploaded</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo array_sum(array_column($_SESSION['uploaded_logs'], 'entries_count')); ?></div>
                            <div class="stat-label">Total Entries</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo array_sum(array_map(function($log) { return isset($log['extracted_files']) ? $log['extracted_files'] : 1; }, $_SESSION['uploaded_logs'])); ?></div>
                            <div class="stat-label">Extracted Files</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo formatFileSize(array_sum(array_column($_SESSION['uploaded_logs'], 'size'))); ?></div>
                            <div class="stat-label">Total Size</div>
                        </div>
                    </div>
                </div>
                
                <h3>📋 Uploaded Log Files</h3>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Log File Name</th>
                            <th>Size</th>
                            <th>Upload Date</th>
                            <th>Entries</th>
                            <th>Files</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['uploaded_logs'] as $index => $log): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($log['original_name']); ?></td>
                                <td><?php echo formatFileSize($log['size']); ?></td>
                                <td><?php echo $log['upload_date']; ?></td>
                                <td><?php echo number_format($log['entries_count']); ?></td>
                                <td><?php echo isset($log['extracted_files']) ? $log['extracted_files'] : '1'; ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" class="delete-log-form" data-index="<?php echo $index; ?>">
                                        <input type="hidden" name="log_index" value="<?php echo $index; ?>">
                                        <button type="button" class="btn-danger" onclick="showDeleteConfirm(<?php echo $index; ?>)">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                    <?php if (isset($log['temp_csv']) && file_exists($tempDir . $log['temp_csv'])): ?>
                                        <a href="?export_single=<?php echo urlencode($log['temp_csv']); ?>" class="btn-export" style="text-decoration: none; display: inline-block; margin-left: 5px;">
                                            📤 Export CSV
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <h3>🚀 Ready to Import?</h3>
                    <p>All uploaded logs have been parsed and temporary CSV files generated.</p>
                    
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="import_to_db" class="btn-success">
                            📥 Import to Database
                        </button>
                    </form>
                    
                                         <?php if (!empty($_SESSION['uploaded_logs'])): ?>
                         <a href="?export=1" class="btn-warning" style="text-decoration: none; display: inline-block;">
                             📤 Export Merged CSV
                         </a>
                     <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="message" style="background: #e3f2fd; color: #0d47a1; border-color: #2196f3;">
                    <strong>ℹ️</strong> No log files uploaded yet. Please upload your first log file to get started.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer Credit -->
        <div class="footer-credit">
            <p>Developed by <strong>Rolly Falco Villacacan</strong></p>
            <p style="font-size: 11px; margin-top: 5px;">Loki Log Manager - Simple, Fast, Effective</p>
        </div>
    </div>
    
    <!-- Credit Watermark -->
    <div class="credit-watermark">Rolly Falco Villacacan</div>
    
    <!-- Custom Modal -->
    <div id="customModal" class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 id="modalTitle">⚠️ Confirmation</h3>
            </div>
            <div class="custom-modal-body">
                <div class="warning-icon">⚠️</div>
                <p id="modalMessage"></p>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="custom-modal-btn custom-modal-btn-secondary" onclick="closeCustomModal(false)">Cancel</button>
                <button type="button" class="custom-modal-btn custom-modal-btn-danger" id="modalConfirmBtn" onclick="closeCustomModal(true)">Confirm</button>
            </div>
        </div>
    </div>
    
    <!-- Custom Toast Notification -->
    <div id="customToast" class="custom-toast">
        <div class="custom-toast-content">
            <span class="custom-toast-icon" id="toastIcon"></span>
            <span class="custom-toast-message" id="toastMessage"></span>
        </div>
    </div>
    
    <script>
        // Custom confirmation modal
        let modalCallback = null;
        let modalForm = null;
        
        function showCustomConfirm(title, message, confirmText = 'Confirm', confirmClass = 'danger') {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('modalConfirmBtn').textContent = confirmText;
            document.getElementById('modalConfirmBtn').className = 'custom-modal-btn custom-modal-btn-' + confirmClass;
            document.getElementById('customModal').classList.add('show');
            
            return new Promise((resolve) => {
                modalCallback = resolve;
            });
        }
        
        function closeCustomModal(confirmed) {
            document.getElementById('customModal').classList.remove('show');
            if (modalCallback) {
                modalCallback(confirmed);
                modalCallback = null;
            }
            if (confirmed && modalForm) {
                modalForm.submit();
                modalForm = null;
            }
        }
        
        // Cleanup confirmation
        function showCleanupConfirm() {
            const form = document.getElementById('cleanup-form');
            modalForm = form;
            showCustomConfirm(
                '⚠️ Warning',
                'WARNING: This will delete ALL uploaded files, temporary files, clear the database, and reset the application. This action cannot be undone. Are you sure you want to continue?',
                'Yes, Cleanup All',
                'danger'
            );
        }
        
        // Delete log file confirmation
        function showDeleteConfirm(index) {
            const form = document.querySelector(`.delete-log-form[data-index="${index}"]`);
            modalForm = form;
            showCustomConfirm(
                '🗑️ Delete Log File',
                'Are you sure you want to delete this log file? This action cannot be undone.',
                'Yes, Delete',
                'danger'
            );
        }
        
        // Custom toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('customToast');
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
        
        // Close modal on overlay click
        document.getElementById('customModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCustomModal(false);
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCustomModal(false);
            }
        });
    </script>
</body>
</html>
