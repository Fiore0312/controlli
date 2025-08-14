@echo off
title BAIT Service Enterprise - Startup Script
color 0A

echo ===============================================
echo    BAIT Service Enterprise - Startup Script
echo ===============================================
echo.

REM Check if XAMPP is running
echo [1/6] Checking XAMPP Services...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ Apache is running
) else (
    echo ❌ Apache is not running
    echo Starting Apache...
    start "" "C:\xampp\apache_start.bat"
    timeout /t 3 /nobreak >nul
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo ✓ MySQL is running
) else (
    echo ❌ MySQL is not running
    echo Starting MySQL...
    start "" "C:\xampp\mysql_start.bat"
    timeout /t 5 /nobreak >nul
)

echo.
echo [2/6] Testing Database Connection...
php -r "
try {
    $pdo = new PDO('mysql:host=localhost;port=3306', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '✓ MySQL connection successful\n';
    
    $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute(['bait_service_real']);
    
    if ($stmt->fetch()) {
        echo '✓ Database bait_service_real exists\n';
    } else {
        echo '❌ Database bait_service_real not found\n';
        echo 'Creating database...\n';
        $pdo->exec('CREATE DATABASE IF NOT EXISTS bait_service_real CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        echo '✓ Database created\n';
    }
} catch (Exception $e) {
    echo '❌ Database connection failed: ' . $e->getMessage() . '\n';
    exit(1);
}
"

echo.
echo [3/6] Checking Database Schema...
php -r "
try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=bait_service_real', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $tables = ['technicians', 'clients', 'activities', 'alerts', 'timbratures'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            $existingTables[] = $table;
        } catch (Exception $e) {
            // Table doesn't exist
        }
    }
    
    echo '✓ Found ' . count($existingTables) . '/' . count($tables) . ' tables\n';
    
    if (count($existingTables) < count($tables)) {
        echo 'Missing tables: ' . implode(', ', array_diff($tables, $existingTables)) . '\n';
        echo 'Run: mysql -u root < bait_service_real_database_setup.sql\n';
    }
} catch (Exception $e) {
    echo '❌ Schema check failed: ' . $e->getMessage() . '\n';
}
"

echo.
echo [4/6] Testing API Endpoints...
php -r "
$baseUrl = 'http://localhost/controlli/laravel_bait/public/api/';
$endpoints = ['health', 'status', 'dashboard/data'];
$working = 0;

foreach ($endpoints as $endpoint) {
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($baseUrl . $endpoint, false, $context);
    
    if ($response && json_decode($response)) {
        echo '✓ API /' . $endpoint . ' working\n';
        $working++;
    } else {
        echo '❌ API /' . $endpoint . ' failed\n';
    }
}

echo 'API Status: ' . $working . '/' . count($endpoints) . ' endpoints working\n';
"

echo.
echo [5/6] Opening Dashboard...
timeout /t 2 /nobreak >nul
start "" "http://localhost/controlli/laravel_bait/public/index_standalone.php"

echo.
echo [6/6] Opening Test Suite...
timeout /t 1 /nobreak >nul
start "" "http://localhost/controlli/laravel_bait/public/test_full_system.php"

echo.
echo ===============================================
echo    BAIT Service Enterprise Started Successfully!
echo ===============================================
echo.
echo Available URLs:
echo   📊 Dashboard: http://localhost/controlli/laravel_bait/public/index_standalone.php
echo   🧪 Test Suite: http://localhost/controlli/laravel_bait/public/test_full_system.php
echo   🔍 DB Test: http://localhost/controlli/laravel_bait/public/test_database_connection.php
echo.
echo API Endpoints:
echo   📡 Health: http://localhost/controlli/laravel_bait/public/api/health
echo   📊 Dashboard Data: http://localhost/controlli/laravel_bait/public/api/dashboard/data
echo   📈 KPIs: http://localhost/controlli/laravel_bait/public/api/kpis
echo   🚨 Alerts: http://localhost/controlli/laravel_bait/public/api/alerts
echo   ⚙️  Status: http://localhost/controlli/laravel_bait/public/api/status
echo.
echo Press any key to close this window...
pause >nul