# 🔍 Loki Log Manager

A powerful, lightweight PHP-based web application for parsing, managing, and analyzing log files. Built with simplicity in mind, designed to run seamlessly on XAMPP for rapid local development and deployment.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/License-MIT-green)
![XAMPP](https://img.shields.io/badge/XAMPP-Ready-red)

---

## 📋 Table of Contents

- [Features](#-features)
- [Why PHP + XAMPP?](#-why-php--xampp)
- [Requirements](#-requirements)
- [Complete Setup Guide](#-complete-setup-guide)
- [Quick Start Guide](#-quick-start-guide)
- [Using the Application](#-using-the-application)
- [Testing the Application](#-testing-the-application)
- [Project Structure](#-project-structure)
- [Configuration](#-configuration)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)
- [Author](#-author)

---

## ✨ Features

### 🚀 Core Functionality
- **Multi-File Upload**: Upload multiple log files (.log, .txt, .zip) in a single session
- **Intelligent Parsing**: Strict 4-column parsing (timestamp, hostname, log level, message)
- **Automatic Database Setup**: One-click database and table creation
- **Database Integration**: Bulk import with duplicate detection
- **Real-time Analytics**: Advanced filtering, searching, and data visualization
- **CSV Export**: Export filtered or complete datasets for external analysis

### 🎯 Advanced Features
- **Memory Efficient**: Line-by-line processing handles large files without memory issues
- **Session Management**: Preserve upload state across requests
- **Transaction Safety**: Database imports with rollback on errors
- **Responsive Design**: Mobile-friendly interface with DataTables integration
- **Large File Support**: Handles files up to 200MB+ with configurable limits
- **Custom UI**: Beautiful custom modals and toast notifications

### 🔧 Developer-Friendly
- **Zero Dependencies**: Pure PHP, no Composer or npm required
- **XAMPP Ready**: Works out-of-the-box with XAMPP (Apache + MySQL)
- **Easy Setup**: One-click database initialization
- **Clean Code**: Well-structured, commented, and maintainable

---

## 🎯 Why PHP + XAMPP?

This project showcases the power of **PHP as a backend scripting language** for rapid development:

- ✅ **Easiest Setup**: XAMPP provides Apache, MySQL, and PHP in one package
- ✅ **Zero Configuration**: Download, install, and start developing immediately
- ✅ **No Dependencies**: No need for Node.js, Python, or complex package managers
- ✅ **Perfect for Local Development**: Ideal for daily tasks, automations, and quick prototypes
- ✅ **Production Ready**: Can be easily deployed to any PHP hosting environment
- ✅ **Lightweight**: Minimal resource footprint, runs smoothly on any workstation

**Perfect for developers who want to focus on building features, not configuring environments.**

---

## 📋 Requirements

### Server Requirements
- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher (or MariaDB 10.2+)
- **Apache** web server (included in XAMPP)
- **PHP Extensions**:
  - PDO
  - PDO_MySQL
  - ZipArchive
  - FileInfo

### Recommended Setup
- **XAMPP** (Windows/Linux/Mac) - [Download here](https://www.apachefriends.org/)
- Modern web browser with JavaScript enabled

---

## 🛠️ Complete Setup Guide

Follow these steps carefully to set up the entire application from scratch.

### Step 1: Install XAMPP

1. **Download XAMPP**
   - Visit [https://www.apachefriends.org/](https://www.apachefriends.org/)
   - Download the latest version for your operating system
   - Run the installer

2. **Install XAMPP**
   - Choose installation directory (default is fine)
   - Select components: **Apache**, **MySQL**, and **PHP** (required)
   - Complete the installation

3. **Start XAMPP Services**
   - Open **XAMPP Control Panel**
   - Click **Start** button for **Apache**
   - Click **Start** button for **MySQL**
   - Both should show green "Running" status

### Step 2: Download/Clone the Project

**Option A: Using Git (Recommended)**
```bash
# Navigate to XAMPP htdocs directory
cd C:\xampp\htdocs  # Windows
# or
cd /opt/lampp/htdocs  # Linux
# or
cd /Applications/XAMPP/htdocs  # Mac

# Clone the repository
git clone https://github.com/yourusername/loki-log-manager.git

# Navigate to project folder
cd loki-log-manager
```

**Option B: Manual Download**
1. Download the project ZIP file
2. Extract to: `C:\xampp\htdocs\loki-log-manager` (Windows)
3. Or extract to your XAMPP htdocs directory

### Step 3: Configure PHP Upload Limits (Important!)

For large log files, you need to increase PHP upload limits.

**Option A: Using PowerShell Script (Windows - Easiest)**
```powershell
# Open PowerShell in the project directory
cd C:\xampp\htdocs\loki-log-manager

# Run the script (you may need to allow script execution)
.\fix_upload_limits.ps1
```

**Option B: Manual Configuration**
1. Open `C:\xampp\php\php.ini` in a text editor
2. Find and update these values:
   ```ini
   upload_max_filesize = 200M
   post_max_size = 250M
   max_execution_time = 600
   max_input_time = 600
   memory_limit = 512M
   ```
3. Save the file
4. **Restart Apache** in XAMPP Control Panel

### Step 4: Setup Database

**🎉 Good News: The setup script automatically creates the database for you!**

The `setup_database.php` script will:
- ✅ Automatically detect if the database exists
- ✅ Create the database if it doesn't exist
- ✅ Create the `log_entries` table
- ✅ Set up all required indexes

**Automatic Setup (Recommended):**

1. Open your web browser
2. Navigate to: `http://localhost/loki-log-manager/setup_database.php`
   - Replace `loki-log-manager` with your actual project folder name
3. You'll see the database setup page
4. Click the **"Setup Database & Table"** button
5. Wait for the success message:
   - ✅ "Database setup completed successfully!"
   - ✅ Shows connection status
   - ✅ Shows table status
   - ✅ Shows current record count

**Manual Database Setup (Alternative):**

If you prefer to create the database manually:

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Click **"New"** in the left sidebar
3. Enter database name: `log_parser_db`
4. Select collation: `utf8mb4_unicode_ci`
5. Click **"Create"**
6. Then run `setup_database.php` to create the table

**Verify Database Setup:**

After running the setup script, you should see:
- ✅ Connection Status: "Connected successfully to database"
- ✅ Table Status: "Table log_entries exists"
- ✅ Record Count: 0 (initially)

### Step 5: Verify Installation

1. Open: `http://localhost/loki-log-manager/log_upload_manager.php`
2. You should see the upload interface
3. If you see any errors, check the [Troubleshooting](#-troubleshooting) section

---

## 🚀 Quick Start Guide

Once setup is complete, here's how to get started quickly:

1. **Access the Application**
   - Open: `http://localhost/loki-log-manager/log_upload_manager.php`

2. **Test with Sample File**
   - The project includes `sample_loki_log.log` for testing
   - Upload this file to verify everything works

3. **Import to Database**
   - After uploading, click "Import to Database"
   - View your data in the analytics page

---

## 📖 Using the Application

### 1. Upload Log Files

**Step-by-Step:**

1. **Navigate to Upload Manager**
   - Go to: `http://localhost/loki-log-manager/log_upload_manager.php`

2. **Select Files**
   - Click **"Choose Files"** button
   - Select one or more log files:
     - Supported formats: `.log`, `.txt`, `.zip`
     - Maximum file size: 200MB per file
     - You can select multiple files at once

3. **Upload & Parse**
   - Click **"Upload & Parse"** button
   - Wait for processing to complete
   - You'll see:
     - ✅ Number of files uploaded
     - ✅ Total entries parsed
     - ✅ File details in the table below

4. **Review Uploaded Files**
   - Check the "Uploaded Logs" table
   - See file names, sizes, entry counts
   - Export individual CSV files if needed
   - Delete files if needed

**💡 Tip**: Use the included **`sample_loki_log.log`** file to test the upload functionality!

### 2. Import to Database

**Step-by-Step:**

1. **After Uploading Files**
   - Make sure you have uploaded at least one log file
   - Review the parsed entries count

2. **Click "Import to Database"**
   - Located at the bottom of the uploaded files table
   - This will:
     - Merge all temporary CSV files
     - Import data to MySQL database
     - Check for duplicates (based on timestamp + hostname)
     - Show success message with import count

3. **Automatic Redirect**
   - After successful import, you'll be redirected to the Data Display page
   - You'll see:
     - ✅ Number of entries imported
     - ⚠️ Number of duplicates skipped (if any)

### 3. Analyze Data

**Navigate to Data Display:**
- Click **"📊 View Database Data"** button in the header
- Or go to: `http://localhost/loki-log-manager/log_data_display.php`

**Available Features:**

#### A. View Statistics
- **Total Entries**: Total number of log entries in database
- **Unique Hosts**: Number of different hostnames
- **Log Levels**: Number of different log levels
- **Date Range**: Earliest and latest timestamps

#### B. Filter Data

**Hostname Filter:**
1. Select a hostname from the dropdown
2. Click **"Apply Filters"**
3. Table shows only entries from that hostname

**Log Level Filter:**
1. Select a log level (ERROR, WARNING, INFO, etc.)
2. Click **"Apply Filters"**
3. Table shows only entries of that level

**Date Range Filter:**
1. Click the date range field
2. Select start and end dates
3. Click **"Apply Filters"**
4. Table shows entries within that date range

**Global Search:**
1. Use the search box (top right of table)
2. Type any keyword
3. Table filters in real-time across all columns

**Combine Filters:**
- You can use multiple filters together
- Example: Filter by hostname AND log level AND date range

#### C. Clear Filters
- Click **"Clear All"** button to reset all filters
- Table shows all entries again

#### D. Export Data

**Export Filtered Results:**
1. Apply your desired filters
2. Click **"Export CSV"** button
3. CSV file downloads with only filtered data

**Export All Data:**
1. Clear all filters (or don't apply any)
2. Click **"Export CSV"** button
3. CSV file downloads with all data

#### E. Sort and Paginate
- Click column headers to sort
- Use pagination controls at bottom
- Adjust entries per page (10, 25, 50, 100)

#### F. Truncate Table (Delete All Data)
⚠️ **Warning: This permanently deletes all data!**

1. Click **"🗑️ Truncate Table"** button
2. Confirm in the modal dialog
3. All data is permanently deleted
4. Page refreshes showing empty table

### 4. Cleanup All Files

**From Upload Manager Page:**

1. Click **"🧹 Cleanup All Files"** button (top right)
2. Confirm in the warning modal
3. This will:
   - ✅ Delete all uploaded files
   - ✅ Delete all temporary CSV files
   - ✅ Delete all export files
   - ✅ **Clear the database** (truncate log_entries table)
   - ✅ Reset all session data

⚠️ **This action cannot be undone!**

---

## 🧪 Testing the Application

Follow these steps to thoroughly test the application:

### Test 1: Database Setup

1. **Open Setup Page**
   - Go to: `http://localhost/loki-log-manager/setup_database.php`

2. **Check Status**
   - Should show: "Connected successfully to database"
   - Should show: "Table log_entries exists" or "does not exist"

3. **Run Setup**
   - Click **"Setup Database & Table"** button
   - Should see success message
   - Status should update to show table exists

### Test 2: Upload Sample File

1. **Navigate to Upload Manager**
   - Go to: `http://localhost/loki-log-manager/log_upload_manager.php`

2. **Upload Sample File**
   - Click **"Choose Files"**
   - Select: `sample_loki_log.log` (included in project)
   - Click **"Upload & Parse"**

3. **Verify Upload**
   - Should see: "Successfully uploaded and processed 1 file(s)"
   - Should see file in "Uploaded Logs" table
   - Should show entry count (should be 24 entries from sample file)

### Test 3: Import to Database

1. **Click "Import to Database"**
   - Button at bottom of uploaded files section

2. **Verify Import**
   - Should redirect to Data Display page
   - Should show: "✅ Success! X log entries have been imported"
   - Should show statistics with 24 total entries

### Test 4: View and Filter Data

1. **Check Statistics**
   - Total Entries: Should show 24
   - Unique Hosts: Should show 2 (EXAMPLE-SERVER-01, EXAMPLE-SERVER-02)
   - Log Levels: Should show multiple levels

2. **Test Hostname Filter**
   - Select "EXAMPLE-SERVER-01" from dropdown
   - Click "Apply Filters"
   - Table should show only entries from that server

3. **Test Log Level Filter**
   - Select "ERROR" from dropdown
   - Click "Apply Filters"
   - Table should show only ERROR entries

4. **Test Search**
   - Type "database" in search box
   - Table should filter to show matching entries

5. **Test Export**
   - Apply a filter
   - Click "Export CSV"
   - Download should contain only filtered data

### Test 5: Cleanup

1. **Go Back to Upload Manager**
   - Click "← Back to Upload Manager" link

2. **Run Cleanup**
   - Click "🧹 Cleanup All Files"
   - Confirm in modal

3. **Verify Cleanup**
   - Should see success message
   - Uploaded files table should be empty
   - Go to Data Display page
   - Statistics should show 0 entries

### Test 6: Upload Multiple Files

1. **Upload Multiple Files**
   - Select `sample_loki_log.log` multiple times (or create test files)
   - Upload them

2. **Verify Duplicate Detection**
   - System should skip duplicate files
   - Should show message about duplicates

3. **Import All**
   - Import to database
   - Verify all entries are imported

### Test 7: Upload ZIP File

1. **Create ZIP File**
   - Zip the `sample_loki_log.log` file
   - Name it `test_logs.zip`

2. **Upload ZIP**
   - Select the ZIP file
   - Upload and parse

3. **Verify Extraction**
   - System should extract and parse files from ZIP
   - Should show multiple extracted files count

---

## 📁 Project Structure

```
loki-log-manager/
├── log_upload_manager.php      # Main upload interface
├── log_data_display.php        # Data analysis and display
├── setup_database.php          # Database setup utility (auto-creates DB)
├── ajax_filter.php             # AJAX filtering endpoint
├── db_config.php               # Database configuration
├── fix_upload_limits.ps1       # Upload limits fixer script (Windows)
├── sample_loki_log.log         # ⭐ Sample log file for testing
├── README.md                   # This file
├── LICENSE                     # MIT License
├── QUICK_SETUP.md              # Quick reference guide
├── uploads/                    # Uploaded log files (auto-created)
├── temp/                       # Temporary CSV files (auto-created)
└── exports/                    # Exported CSV files (auto-created)
```

**Important Files:**
- **`sample_loki_log.log`**: ⭐ **Use this file to test the application!** Contains 24 sample log entries in the correct format.

---

## 📊 Database Schema

### log_entries Table

The setup script automatically creates this table:

```sql
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
```

**Duplicate Detection:**
- The system prevents duplicate entries based on `timestamp + hostname` combination
- If the same timestamp and hostname exist, the entry is skipped during import

---

## 📝 Log Format Requirements

Your log files **must** follow this exact 4-column format:

```
timestamp,hostname,log_level,remaining_log_message
```

### Format Details:
- **timestamp**: ISO 8601 format (e.g., `20250818T04:37:57Z`)
- **hostname**: Server/host identifier (e.g., `EXAMPLE-SERVER-01`)
- **log_level**: Log severity (e.g., `ERROR`, `WARNING`, `INFO`, `NOTICE`, `CRITICAL`, `ALERT`)
- **remaining_log_message**: The actual log message

### Example Log Entries:
```
20250818T04:37:57Z,EXAMPLE-SERVER-01,NOTICE,VERSION: 0.51.0 SYSTEM: EXAMPLE-SERVER-01
20250818T04:37:58Z,EXAMPLE-SERVER-02,ERROR,Connection failed to database
20250818T04:37:59Z,EXAMPLE-SERVER-01,INFO,Service started successfully
```

**Important Notes:**
- ✅ Only lines matching this exact format will be processed
- ❌ All other lines are ignored
- ✅ Commas in the message are allowed (system handles them correctly)
- ✅ Multiple files can be uploaded at once
- ✅ ZIP files are automatically extracted and processed

**💡 Tip**: Use the included **`sample_loki_log.log`** file as a reference for the correct format!

---

## 🔧 Configuration

### Database Settings

Edit `db_config.php`:

```php
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'log_parser_db',
    'username' => 'root',
    'password' => '',  // Set your MySQL password here if you have one
    'charset' => 'utf8mb4'
];
```

**Note**: If your MySQL has a password, update the `password` field.

### File Upload Limits

**Using PowerShell Script (Windows):**
```powershell
.\fix_upload_limits.ps1
```

**Manual Configuration:**
Edit `C:\xampp\php\php.ini`:
```ini
upload_max_filesize = 200M
post_max_size = 250M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

**After changing php.ini:**
- Restart Apache in XAMPP Control Panel

---

## 🐛 Troubleshooting

### Issue: "Upload Fails" or "File Too Large"

**Solutions:**
1. ✅ Check PHP upload limits in `php.ini`
2. ✅ Run `fix_upload_limits.ps1` script
3. ✅ Restart Apache after changing php.ini
4. ✅ Verify file size is under 200MB
5. ✅ Check file type is supported (.log, .txt, .zip)

### Issue: "Database Connection Error"

**Solutions:**
1. ✅ Verify MySQL service is running in XAMPP Control Panel
2. ✅ Check database credentials in `db_config.php`
3. ✅ Run `setup_database.php` to create database
4. ✅ Verify database name is correct: `log_parser_db`
5. ✅ Check if MySQL password is set (update `db_config.php` if needed)

### Issue: "Table Does Not Exist"

**Solutions:**
1. ✅ Run `setup_database.php` to create the table
2. ✅ Check if database exists in phpMyAdmin
3. ✅ Verify MySQL service is running

### Issue: "No Files Are Being Parsed"

**Solutions:**
1. ✅ Verify log format matches required 4-column structure
2. ✅ Check for proper comma separation
3. ✅ Ensure timestamp format is correct (YYYYMMDDTHH:MM:SSZ)
4. ✅ Use `sample_loki_log.log` as a reference
5. ✅ Check for special characters that might break parsing

### Issue: "Page Not Found" or "404 Error"

**Solutions:**
1. ✅ Verify project folder name in URL matches actual folder name
2. ✅ Check if Apache is running in XAMPP
3. ✅ Verify files are in correct location: `C:\xampp\htdocs\loki-log-manager`
4. ✅ Try: `http://localhost/loki-log-manager/` (with trailing slash)

### Issue: "Permission Denied" Errors

**Solutions:**
1. ✅ Check folder permissions for `uploads/`, `temp/`, `exports/`
2. ✅ Ensure folders are writable by web server
3. ✅ On Windows, usually not an issue
4. ✅ On Linux/Mac, may need: `chmod 755 uploads temp exports`

---

## 📈 Performance Tips

1. **Large Files**: System handles files up to 200MB efficiently
2. **Database**: Use SSD storage for better query performance
3. **Memory**: System processes files line-by-line to minimize memory usage
4. **Indexing**: Database indexes are automatically created for optimal performance
5. **Filtering**: Use filters to reduce data load when working with large datasets

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- Built with pure PHP for maximum simplicity
- Designed for XAMPP environment
- Inspired by the need for simple, effective log management tools

---

## 📞 Support

For issues, questions, or suggestions:
- Open an issue on GitHub
- Check the troubleshooting section above
- Review the documentation files in the repository

---

## 👤 Author

**Rolly Falco Villacacan**

*Made with ❤️ using PHP and XAMPP*

*Simple. Fast. Effective. No dependencies. Just works.*

---

**⭐ Don't forget to test with the included `sample_loki_log.log` file!**
