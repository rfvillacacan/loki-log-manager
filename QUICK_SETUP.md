# Quick Setup Guide - Database Setup

## 🚀 Fast Setup (3 Steps)

### Step 1: Start MySQL in XAMPP
1. Open **XAMPP Control Panel**
2. Click **Start** button next to **MySQL**
3. Wait for status to turn **green**

### Step 2: Setup Database
Open in your browser:
```
http://localhost/YOUR_PROJECT_FOLDER/setup_database.php
```
*(Replace `YOUR_PROJECT_FOLDER` with your actual project folder name)*

Click the **"Setup Database & Table"** button.

### Step 3: Verify Setup
You should see:
- ✅ **Database Connection:** Connected successfully to database
- ✅ **Table Status:** Table log_entries exists
- ✅ **Current Records:** 0 (or number of existing records)

---

## ✅ That's It!

Your database is now ready. You can:
1. Upload log files via `log_upload_manager.php`
2. Import data to database
3. View and analyze data in `log_data_display.php`

---

## 🔧 Troubleshooting

### If you see "Connection failed":
1. **Check MySQL is running** in XAMPP Control Panel
2. **Verify credentials** - Default XAMPP uses:
   - Username: `root`
   - Password: (empty/blank)

### If you see "Table doesn't exist":
- Click the **"Setup Database & Table"** button again
- The script will create it automatically

### Need more help?
- Run `verify_database.php` for detailed diagnostics
- Check `README.md` for comprehensive documentation

---

**Quick Test:**
After setup, try uploading a log file to verify everything works!

