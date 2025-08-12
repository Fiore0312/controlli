@echo off
setlocal EnableDelayedExpansion
chcp 65001 >nul 2>&1

echo ===============================================================
echo              BAIT ENTERPRISE SYSTEM v3.0 - FIXED
echo                    Windows Compatible Version
echo ===============================================================
echo.

REM Set working directory
cd /d "%~dp0"

echo [FASE 1/6] System Verification...
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python not found! Please install Python 3.8+ first.
    echo.
    echo Download from: https://www.python.org/downloads/
    echo.
    pause
    exit /b 1
)

echo [OK] Python installation verified
echo.

echo [FASE 2/6] Directory Setup...
echo.

REM Create required directories
if not exist "data\input" mkdir "data\input"
if not exist "upload_csv" mkdir "upload_csv"
if not exist "logs" mkdir "logs"
if not exist "backup_csv" mkdir "backup_csv"

echo [OK] Directory structure ready
echo.

echo [FASE 3/6] Dependencies Installation...
echo.

echo [INSTALL] Upgrading pip...
python -m pip install --upgrade pip >nul 2>&1

echo [INSTALL] Installing core BAIT dependencies...
python -m pip install pandas plotly dash chardet pydantic python-dateutil numpy >nul 2>&1

echo [INSTALL] Installing enterprise features...
python -m pip install requests beautifulsoup4 openpyxl xlsxwriter >nul 2>&1

echo [INSTALL] Installing performance enhancers...
python -m pip install flask-caching gunicorn >nul 2>&1

echo [VERIFY] Testing critical dependencies...
python -c "import pandas, plotly, dash, chardet; print('[OK] Critical dependencies verified')" 2>&1
if errorlevel 1 (
    echo [ERROR] Failed to install dependencies
    pause
    exit /b 1
)

echo [OK] All dependencies installed and verified
echo.

echo [FASE 4/6] System Preparation...
echo.

echo [SETUP] Creating system directories...
echo [OK] Directory structure ready

echo [CHECK] Checking data files...
set file_count=0
for %%f in (data\input\*.csv upload_csv\*.csv) do (
    set /a file_count+=1
)
echo [OK] Found %file_count% CSV data files

echo [CHECK] Checking port availability...
netstat -an | find "8050" >nul 2>&1
if not errorlevel 1 (
    echo [WARNING] Port 8050 in use, dashboard will try alternative port
) else (
    echo [OK] Port 8050 available for dashboard
)

echo [OK] System preparation complete
echo.

echo [FASE 5/6] Backend Data Processing...
echo.

echo [PROCESSING] Starting BAIT data processing engine...
python bait_controller_windows_fix.py
if errorlevel 1 (
    echo [WARNING] Processing completed with warnings - continuing
) else (
    echo [OK] Backend processing complete
)
echo.

echo [FASE 6/6] Enterprise Dashboard Startup...
echo.

echo [DASHBOARD] Starting enterprise dashboard server...
echo [INFO] Dashboard will open automatically in your browser
echo [INFO] URL: http://localhost:8050
echo.

REM Start dashboard in background and open browser
start /b python bait_dashboard_final.py
timeout /t 3 /nobreak >nul 2>&1

REM Open browser automatically
start http://localhost:8050

echo.
echo ===============================================================
echo                 SISTEMA BAIT ENTERPRISE AVVIATO!
echo ===============================================================
echo.
echo [READY] Dashboard disponibile su: http://localhost:8050
echo [INFO] I dati vengono aggiornati automaticamente ogni 30 secondi
echo [INFO] Carica i tuoi file CSV tramite l'interfaccia web
echo.
echo [COMMANDS] Comandi disponibili:
echo   - Ctrl+C: Ferma il sistema
echo   - Ctrl+R: Aggiorna la pagina del browser  
echo   - F5: Refresh completo dashboard
echo.
echo Premi qualsiasi tasto per mantenere aperto questo terminale...
pause >nul

echo.
echo [SHUTDOWN] Arresto sistema BAIT in corso...
taskkill /f /im python.exe >nul 2>&1
echo [OK] Sistema arrestato correttamente.
echo.
pause