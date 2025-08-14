@echo off
title BAIT Enterprise - Fix XAMPP e Avvio

echo ========================================================================
echo   BAIT ENTERPRISE - DIAGNOSI E FIX XAMPP
echo ========================================================================
echo.

echo [DIAGNOSI] Controllo stato XAMPP...
echo.

REM Verifica se Apache è in esecuzione
tasklist | find /i "httpd.exe" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] Apache XAMPP è già in esecuzione
) else (
    echo [PROBLEMA] Apache XAMPP non è in esecuzione!
    echo.
    echo [FIX] Tentativo avvio automatico Apache...
    
    REM Prova ad avviare Apache
    if exist "C:\xampp\apache\bin\httpd.exe" (
        echo [AVVIO] Avvio Apache XAMPP...
        start "" "C:\xampp\apache\bin\httpd.exe" -D FOREGROUND
        timeout /t 3 /nobreak >nul
    ) else (
        echo [ERROR] XAMPP non trovato in C:\xampp\
        echo.
        echo [SOLUZIONE MANUALE]
        echo   1. Apri XAMPP Control Panel
        echo   2. Clicca START su Apache
        echo   3. Clicca START su MySQL (opzionale)
        echo   4. Rilancia questo script
        echo.
        pause
        exit /b 1
    )
)

echo.
echo [VERIFICA] Test connessione web server...

REM Test semplice connessione
curl -s http://localhost/ >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] Web server risponde correttamente
) else (
    echo [WARNING] Web server potrebbe non rispondere
    echo [INFO] Prova apertura manuale: http://localhost/
)

echo.
echo [TEST] Verifica file dashboard Laravel...

if exist "C:\xampp\htdocs\controlli\laravel_bait\public\index_standalone.php" (
    echo [OK] File dashboard trovato
    
    echo.
    echo [AVVIO] Apertura dashboard BAIT Enterprise...
    
    REM Apri dashboard
    start http://localhost/controlli/laravel_bait/public/index_standalone.php
    
    echo.
    echo ========================================================================
    echo   DASHBOARD BAIT ENTERPRISE AVVIATA
    echo ========================================================================
    echo.
    echo [URL]         http://localhost/controlli/laravel_bait/public/index_standalone.php
    echo [TEST DB]     http://localhost/controlli/laravel_bait/public/test_database_connection.php
    echo [XAMPP]       http://localhost/
    echo.
    echo [INFO] Se la pagina non si apre:
    echo   1. Verifica che XAMPP Control Panel mostri Apache VERDE
    echo   2. Prova http://localhost/ per test XAMPP
    echo   3. Controlla che non ci siano firewall/antivirus che bloccano
    echo.
    
) else (
    echo [ERROR] File dashboard non trovato!
    echo [PATH] Controllare: C:\xampp\htdocs\controlli\laravel_bait\public\index_standalone.php
)

echo.
echo [STATUS] Mantieni questa finestra aperta per monitoraggio
echo [STOP] Chiudi per terminare
echo.

pause