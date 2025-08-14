@echo off
title BAIT Service - Fix Caratteri Database

echo ========================================================================
echo   BAIT SERVICE - FIX CARATTERI CORROTTI DATABASE
echo ========================================================================
echo.
echo [PROBLEMA] Caratteri italiani corrotti: attivit├á → attività
echo [SOLUZIONE] Correzione diretta nel database MySQL
echo [TARGET] Tabelle: alerts, attivita, timbrature, clienti, etc.
echo.

cd /d "C:\xampp\htdocs\controlli"

echo [CHECK] Verifica file script...
if not exist "fix_charset_database.sql" (
    echo [ERROR] File fix_charset_database.sql non trovato!
    pause
    exit /b 1
)

echo [OK] File script trovato
echo.

echo [WARNING] Questo script modificherà i dati nel database
echo [INFO] Tutti i caratteri ├á, ├┤, ├¿, ├ verranno corretti
echo [TARGET] Database: bait_service_real
echo.

set /p confirm="Continuare con la correzione caratteri? [Y/N]: "
if /i "%confirm%" neq "Y" (
    echo [CANCELLED] Fix annullato
    pause
    exit /b 1
)

echo.
echo [EXEC] Esecuzione fix caratteri database...
echo [CMD] mysql -u root < fix_charset_database.sql
echo.

C:\xampp\mysql\bin\mysql.exe -u root < fix_charset_database.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================================================
    echo   SUCCESS! CARATTERI CORRETTI NEL DATABASE
    echo ========================================================================
    echo.
    echo [FIX APPLICATI]
    echo   • attivit├á → attività
    echo   • ├á → à
    echo   • ├┤ → ì  
    echo   • ├¿ → ì
    echo   • Altri caratteri UTF-8 corrotti
    echo.
    echo [TABELLE CORRETTE]
    echo   • alerts (messaggi)
    echo   • attivita (descrizioni, tipo)
    echo   • timbrature (note)
    echo   • clienti (ragione sociale)
    echo   • teamviewer_sessions
    echo   • permessi
    echo.
    echo [TEST] Ricarica dashboard per vedere i caratteri corretti:
    echo   http://localhost/controlli/laravel_bait/public/index_standalone.php
    echo.
    
    timeout /t 3 /nobreak >nul
    start http://localhost/controlli/laravel_bait/public/index_standalone.php
    
) else (
    echo.
    echo [ERROR] Errore durante fix caratteri
    echo [DEBUG] Controlla connessione MySQL
    echo [MANUAL] Comando manuale: mysql -u root < fix_charset_database.sql
    echo.
)

echo [INFO] Mantieni finestra aperta per controllo risultati
pause