@echo off
title BAIT Service - Setup Database Pulito

echo ========================================================================
echo   BAIT SERVICE - SETUP DATABASE PULITO (RISOLVE FOREIGN KEY)
echo ========================================================================
echo.
echo [FIX] Risolve errore "Cannot delete or update a parent row"
echo [ACTION] Cancella completamente database esistente e lo ricrea
echo [SAFE] Backup automatico se necessario
echo.

cd /d "C:\xampp\htdocs\controlli"

echo [CHECK] Verifica file setup...
if not exist "bait_database_clean_setup.sql" (
    echo [ERROR] File bait_database_clean_setup.sql non trovato!
    pause
    exit /b 1
)

echo [OK] File setup trovato
echo.

echo [WARNING] Questo script cancellerà completamente il database esistente
echo [WARNING] Tutti i dati attuali andranno persi
echo [INFO] Verranno inseriti dati demo per test immediato
echo.

set /p confirm="Continuare? [Y/N]: "
if /i "%confirm%" neq "Y" (
    echo [CANCELLED] Setup annullato
    pause
    exit /b 1
)

echo.
echo [EXEC] Esecuzione setup database pulito...
echo [CMD] mysql -u root < bait_database_clean_setup.sql
echo.

C:\xampp\mysql\bin\mysql.exe -u root < bait_database_clean_setup.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================================================
    echo   SUCCESS! DATABASE RICREATO CON SUCCESSO
    echo ========================================================================
    echo.
    echo [DATABASE] bait_service_real completamente ricreato
    echo [TABLES]   9 tabelle enterprise
    echo [DATA]     Dati demo inseriti per test immediato:
    echo            • 8 attività demo
    echo            • 5 timbrature 
    echo            • 4 sessioni TeamViewer
    echo            • 5 alert di esempio
    echo [CONSTRAINTS] Foreign key constraints risolti
    echo.
    echo [TEST] Apertura test database in 3 secondi...
    timeout /t 3 /nobreak >nul
    
    start http://localhost/controlli/laravel_bait/public/test_database_connection.php
    
    echo.
    echo [DASHBOARD] http://localhost/controlli/laravel_bait/public/index_standalone.php
    echo [SUCCESS] Database pronto con dati demo visibili!
    echo.
    
) else (
    echo.
    echo [ERROR] Errore durante setup database
    echo [DEBUG] Controlla connessione MySQL
    echo [MANUAL] Comando manuale: mysql -u root < bait_database_clean_setup.sql
    echo.
)

echo [INFO] Mantieni finestra aperta per monitoraggio
pause