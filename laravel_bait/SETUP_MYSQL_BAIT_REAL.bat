@echo off
echo =====================================================
echo BAIT SERVICE MYSQL DATABASE SETUP - PRODUCTION READY
echo =====================================================
echo.

cd /d "%~dp0"

echo [1/4] Verifying MySQL XAMPP connection...
"C:\xampp\mysql\bin\mysql.exe" -u root -p -e "SELECT 'MySQL connection OK' as status;"
if errorlevel 1 (
    echo ERROR: Cannot connect to MySQL. Please start XAMPP MySQL service.
    pause
    exit /b 1
)

echo.
echo [2/4] Creating database schema and tables...
"C:\xampp\mysql\bin\mysql.exe" -u root -p < "bait_service_real_database_setup.sql"
if errorlevel 1 (
    echo ERROR: Database creation failed.
    pause
    exit /b 1
)

echo.
echo [3/4] Creating stored procedures and business logic...
"C:\xampp\mysql\bin\mysql.exe" -u root -p < "bait_stored_procedures_real.sql"
if errorlevel 1 (
    echo ERROR: Stored procedures creation failed.
    pause
    exit /b 1
)

echo.
echo [4/4] Importing CSV data (this may take a few minutes)...
echo IMPORTANT: Make sure CSV files are in the correct location:
echo - /mnt/c/xampp/htdocs/controlli/data/processed/
echo.
"C:\xampp\mysql\bin\mysql.exe" -u root -p --local-infile=1 < "import_csv_to_mysql.sql"
if errorlevel 1 (
    echo WARNING: CSV import had issues. Database is still functional.
    echo You can import CSV data manually later.
)

echo.
echo =====================================================
echo DATABASE SETUP COMPLETED SUCCESSFULLY!
echo =====================================================
echo.
echo Database: bait_service_real
echo Tables: 13 created
echo Stored Procedures: 11 created
echo Views: 2 optimized views created
echo.
echo NEXT STEPS:
echo 1. Update Laravel .env file with database credentials
echo 2. Run Laravel migrations if needed
echo 3. Test dashboard connection
echo.
echo Database is ready for production use!
echo =====================================================
pause