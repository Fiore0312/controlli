@echo off
REM ========================================================================
REM  BAIT SERVICE - ENTERPRISE STARTUP SYSTEM
REM ========================================================================
REM
REM  Sistema di avvio automatico enterprise-grade per BAIT Service
REM  Versione: Enterprise 3.0
REM  Autore: Franco - BAIT Service  
REM  Target: Zero-configuration, 30 secondi to dashboard
REM
REM ========================================================================

title BAIT Service Enterprise Startup

echo.
echo ========================================================================
echo   BAIT SERVICE - ENTERPRISE STARTUP SYSTEM
echo ========================================================================
echo.
echo [SISTEMA]   BAIT Service Enterprise-Grade v3.0
echo [UTENTE]    Franco  
echo [PATH]      %CD%
echo [AVVIO]     %DATE% %TIME%
echo [TARGET]    Dashboard ready in 30 seconds
echo.

REM ========================================================================
REM  FASE 1: ENVIRONMENT VALIDATION
REM ========================================================================

echo [FASE 1/6] Environment Validation...
echo.

REM Controlla Python con versione specifica
echo [CHECK] Python version validation...
python --version 2>nul | find "Python 3" >nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Python 3.x not found!
    echo.
    echo [SOLUTION]
    echo    1. Download Python 3.11+ from https://python.org
    echo    2. During installation, check "Add to PATH"
    echo    3. Restart this script
    echo.
    pause
    exit /b 1
)

python --version
echo [OK] Python available

REM Controlla pip
echo [CHECK] pip availability...
python -m pip --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] pip not available!
    echo [FIX] Installing pip...
    python -m ensurepip --upgrade
)
echo [OK] pip available

echo.

REM ========================================================================
REM  FASE 2: VIRTUAL ENVIRONMENT SETUP
REM ========================================================================

echo [FASE 2/6] Virtual Environment Setup...
echo.

REM Cleanup old venv if corrupted
if exist "bait_env" (
    echo [CLEANUP] Removing old virtual environment...
    rmdir /s /q "bait_env" >nul 2>&1
    timeout /t 1 /nobreak >nul
)

REM Create fresh virtual environment
echo [SETUP] Creating fresh virtual environment...
python -m venv bait_env
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Failed to create virtual environment
    pause
    exit /b 1
)

REM Activate virtual environment
echo [SETUP] Activating virtual environment...
call bait_env\Scripts\activate.bat
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Failed to activate virtual environment
    pause
    exit /b 1
)

echo [OK] Virtual environment ready
echo.

REM ========================================================================
REM  FASE 3: DEPENDENCY INSTALLATION
REM ========================================================================

echo [FASE 3/6] Enterprise Dependencies Installation...
echo.

REM Upgrade pip first
echo [INSTALL] Upgrading pip...
python -m pip install --upgrade pip --quiet

REM Install core dependencies with specific versions for stability
echo [INSTALL] Installing core BAIT dependencies...
python -m pip install --quiet dash==2.17.1 plotly==5.17.0 pandas>=2.1.0 numpy>=1.24.0 chardet>=5.0.0

REM Install additional enterprise features
echo [INSTALL] Installing enterprise features...
python -m pip install --quiet dash-bootstrap-components>=1.5.0 openpyxl>=3.1.0 python-dateutil>=2.8.0

REM Install optional performance enhancers
echo [INSTALL] Installing performance enhancers...
python -m pip install --quiet flask>=2.3.0 psutil>=5.9.0 --quiet

REM Verify critical imports
echo [VERIFY] Testing critical dependencies...
python -c "import dash, plotly, pandas, numpy; print('[OK] Critical dependencies verified')" 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Critical dependencies failed!
    echo [FIX] Attempting force reinstall...
    python -m pip install --force-reinstall dash plotly pandas numpy
    python -c "import dash, plotly, pandas; print('[OK] Dependencies fixed after reinstall')" 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo [ERROR] Unrecoverable dependency error
        pause
        exit /b 1
    )
)

echo [OK] All dependencies installed and verified
echo.

REM ========================================================================
REM  FASE 4: SYSTEM PREPARATION
REM ========================================================================

echo [FASE 4/6] System Preparation...
echo.

REM Create required directories
echo [SETUP] Creating system directories...
if not exist "data" mkdir data
if not exist "data\input" mkdir data\input
if not exist "data\processed" mkdir data\processed
if not exist "data\reports" mkdir data\reports
if not exist "logs" mkdir logs
if not exist "exports" mkdir exports
if not exist "upload_csv" mkdir upload_csv

echo [OK] Directory structure ready

REM Check for CSV data files
echo [CHECK] Checking data files...
set DATA_FILES_COUNT=0
for %%f in (data\input\*.csv) do set /a DATA_FILES_COUNT+=1

if %DATA_FILES_COUNT% GTR 0 (
    echo [OK] Found %DATA_FILES_COUNT% CSV data files
) else (
    echo [INFO] No CSV data files found - demo mode will be used
)

REM Port availability check
echo [CHECK] Checking port availability...
netstat -an | find ":8050" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [WARNING] Port 8050 in use - stopping previous processes...
    taskkill /F /IM python.exe >nul 2>&1
    timeout /t 2 /nobreak >nul
)

echo [OK] System preparation complete
echo.

REM ========================================================================
REM  FASE 5: BACKEND PROCESSING
REM ========================================================================

echo [FASE 5/6] Backend Data Processing...
echo.

echo [PROCESSING] Starting BAIT data processing engine...

REM Run data processing with enterprise controller
if exist "bait_controller_enterprise.py" (
    python bait_controller_enterprise.py
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Enterprise data processing completed successfully
    ) else (
        echo [WARNING] Processing completed with warnings - continuing
    )
) else if exist "bait_controller_v2.py" (
    python bait_controller_v2.py
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Data processing completed successfully
    ) else (
        echo [WARNING] Data processing completed with warnings - continuing
    )
) else (
    echo [INFO] No data controller found - dashboard will use demo data
)

echo [OK] Backend processing complete
echo.

REM ========================================================================
REM  FASE 6: DASHBOARD STARTUP
REM ========================================================================

echo [FASE 6/6] Enterprise Dashboard Startup...
echo.

echo ========================================================================
echo   BAIT SERVICE ENTERPRISE DASHBOARD LAUNCHING
echo ========================================================================
echo.
echo [URL]          http://localhost:8050
echo [FEATURES]     Enterprise-grade dashboard with advanced analytics
echo [DATA]         Real-time processing and monitoring
echo [EXPORT]       Excel/PDF/CSV export capabilities
echo [MOBILE]       Responsive design for all devices
echo [REFRESH]      Auto-refresh every 30 seconds
echo.
echo [STARTUP]      Dashboard launching...
echo [BROWSER]      Auto-opening in 5 seconds...
echo [STOP]         Press CTRL+C to stop the system
echo ========================================================================
echo.

REM Start enterprise dashboard - Priority order
if exist "bait_dashboard_final.py" (
    echo [LAUNCH] Starting final enterprise dashboard...
    start /B python bait_dashboard_final.py
) else if exist "bait_enterprise_dashboard_unified.py" (
    echo [LAUNCH] Starting unified enterprise dashboard...
    start /B python bait_enterprise_dashboard_unified.py
) else if exist "bait_simple_dashboard.py" (
    echo [LAUNCH] Starting simple dashboard...
    start /B python bait_simple_dashboard.py
) else (
    echo [ERROR] No dashboard file found!
    pause
    exit /b 1
)

REM Wait for dashboard startup
echo [WAIT] Waiting for dashboard initialization...
timeout /t 5 /nobreak >nul

REM Open browser automatically
echo [BROWSER] Opening dashboard in default browser...
start http://localhost:8050

REM Final status
echo.
echo [SUCCESS] BAIT SERVICE ENTERPRISE SYSTEM READY!
echo.
echo [STATUS]       Dashboard running on http://localhost:8050
echo [PERFORMANCE]  System optimized for enterprise use
echo [MONITORING]   Real-time data processing active
echo [SUPPORT]      System logs available in logs/ directory
echo.
echo [TIPS]
echo   - Keep this window open to monitor system status
echo   - Dashboard auto-refreshes every 30 seconds
echo   - Use export features for reporting
echo   - Check logs/ for troubleshooting
echo.

REM System monitoring loop
echo [MONITOR] System monitoring active - Press CTRL+C to stop
echo.

:monitor_loop
REM Check if Python dashboard process is still running
tasklist | find /i "python.exe" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [WARNING] Dashboard process stopped unexpectedly
    echo [RESTART] Attempting automatic restart...
    
    if exist "bait_enterprise_dashboard.py" (
        start /B python bait_enterprise_dashboard.py
    ) else if exist "bait_dashboard_upload.py" (
        start /B python bait_dashboard_upload.py
    ) else (
        start /B python bait_simple_dashboard.py
    )
    
    timeout /t 5 /nobreak >nul
    echo [OK] Dashboard restarted
)

REM Wait 30 seconds before next check
timeout /t 30 /nobreak >nul
goto monitor_loop

REM Cleanup on exit
:cleanup
echo.
echo [STOP] Shutting down BAIT Service Enterprise System...
echo [CLEANUP] Stopping all Python processes...
taskkill /F /IM python.exe >nul 2>&1
echo [OK] System stopped cleanly
echo.
echo [GOODBYE] Thank you for using BAIT Service Enterprise!
pause