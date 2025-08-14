@echo off
title BAIT Service - Dashboard Server Port 8000

echo ================================================================================
echo   BAIT SERVICE - ENTERPRISE DASHBOARD SERVER
echo ================================================================================
echo.

cd /d "C:\xampp\htdocs\controlli\laravel_bait"

echo 🚀 Starting BAIT Enterprise Dashboard on port 8000...
echo.
echo ✅ URL: http://localhost:8000
echo ✅ Status: Server Starting...
echo.
echo 💡 Features:
echo    • Bootstrap 5 Enterprise UI
echo    • Real-time KPI Monitoring  
echo    • MySQL Database Integration (or Demo Mode)
echo    • REST API Endpoints
echo    • Mobile Responsive Design
echo.
echo 🛑 Press CTRL+C to stop server
echo ================================================================================
echo.

REM Start PHP server
C:\xampp\php\php.exe -S localhost:8000 -t public

echo.
echo ❌ Server stopped or failed to start
echo.
echo 🔧 Troubleshooting:
echo    • Check if port 8000 is available
echo    • Verify XAMPP PHP is installed
echo    • Ensure public/index_standalone.php exists
echo.
pause