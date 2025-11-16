# PowerShell script to fix PHP upload limits in XAMPP
# This script updates php.ini to allow larger file uploads
#
# IMPORTANT: Update the $phpIniPath variable below to match your XAMPP installation path
# Default XAMPP path on Windows: C:\xampp\php\php.ini
# On Linux/Mac: /opt/lampp/etc/php.ini or /Applications/XAMPP/etc/php.ini

# Configure your XAMPP PHP path here
$phpIniPath = "C:\xampp\php\php.ini"  # Update this path if your XAMPP is installed elsewhere

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "XAMPP PHP Upload Limits Fixer" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if php.ini exists
if (-not (Test-Path $phpIniPath)) {
    Write-Host "ERROR: php.ini not found at: $phpIniPath" -ForegroundColor Red
    Write-Host "Please check your XAMPP installation path." -ForegroundColor Yellow
    exit 1
}

Write-Host "Found php.ini at: $phpIniPath" -ForegroundColor Green
Write-Host ""

# Settings to update
$settings = @{
    'upload_max_filesize' = '200M'
    'post_max_size' = '250M'
    'max_execution_time' = '600'
    'max_input_time' = '600'
    'memory_limit' = '512M'
}

Write-Host "Updating PHP settings:" -ForegroundColor Yellow
Write-Host ""

# Read file line by line
$lines = Get-Content $phpIniPath
$newLines = @()
$changesMade = $false

foreach ($line in $lines) {
    $originalLine = $line
    $lineModified = $false
    
    foreach ($setting in $settings.GetEnumerator()) {
        $name = $setting.Key
        $value = $setting.Value
        
        # Check if this line contains the setting (not commented out)
        $pattern = "^(\s*)$name\s*=\s*.*"
        $isComment = $line -match "^\s*;"
        
        if (($line -match $pattern) -and (-not $isComment)) {
            $oldValue = $line
            $newLine = "$name = $value"
            
            # Check if it's already set correctly
            if ($line -match "=\s*$value") {
                Write-Host "  ✓ $name is already set to $value" -ForegroundColor Green
                $newLines += $line
            } else {
                $newLines += $newLine
                Write-Host "  → $name : $oldValue → $newLine" -ForegroundColor Cyan
                $changesMade = $true
                $lineModified = $true
            }
            break
        }
    }
    
    if (-not $lineModified) {
        $newLines += $line
    }
}

Write-Host ""

if ($changesMade) {
    # Create backup
    $backupPath = "$phpIniPath.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    Copy-Item $phpIniPath $backupPath
    Write-Host "Backup created: $backupPath" -ForegroundColor Green
    Write-Host ""
    
    # Write updated content
    $newLines | Set-Content -Path $phpIniPath
    Write-Host "✓ php.ini updated successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "IMPORTANT: Restart Apache in XAMPP!" -ForegroundColor Yellow
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "To restart Apache:" -ForegroundColor White
    Write-Host "  1. Open XAMPP Control Panel" -ForegroundColor White
    Write-Host "  2. Click 'Stop' next to Apache" -ForegroundColor White
    Write-Host "  3. Click 'Start' next to Apache" -ForegroundColor White
    Write-Host ""
    Write-Host "Or run this command as Administrator:" -ForegroundColor White
    Write-Host "  net stop Apache2.4" -ForegroundColor Cyan
    Write-Host "  net start Apache2.4" -ForegroundColor Cyan
    Write-Host ""
} else {
    Write-Host "No changes needed - settings are already correct!" -ForegroundColor Green
    Write-Host ""
}

Write-Host "New settings:" -ForegroundColor Yellow
foreach ($setting in $settings.GetEnumerator()) {
    Write-Host "  $($setting.Key) = $($setting.Value)" -ForegroundColor White
}
Write-Host ""
