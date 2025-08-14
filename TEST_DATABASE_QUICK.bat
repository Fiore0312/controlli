@echo off
title Test Database Rapido

echo ========================================================================
echo   TEST DATABASE RAPIDO - VERIFICA FIX
echo ========================================================================
echo.

echo [INFO] Test dopo fix errore sintassi SQL
echo [FIX]  current_time -^> current_datetime
echo [FIX]  Nomi tabelle corretti: tecnici, clienti, attivita
echo.

echo [TEST] Apertura test database...
start http://localhost/controlli/laravel_bait/public/test_database_connection.php

echo.
echo [WAIT] Attendi 3 secondi per caricamento...
timeout /t 3 /nobreak >nul

echo [DASHBOARD] Apertura dashboard con dati...
start http://localhost/controlli/laravel_bait/public/index_standalone.php

echo.
echo ========================================================================
echo   TEST COMPLETATO
echo ========================================================================
echo.
echo [EXPECTED] Nel test database dovresti vedere:
echo   • Connection successful ✓
echo   • Database exists ✓  
echo   • 5 tabelle trovate ✓
echo   • 3 views trovate ✓
echo   • 5 alert records ✓
echo   • Health check OK ✓
echo.
echo [EXPECTED] Nella dashboard dovresti vedere:
echo   • KPI cards con numeri reali
echo   • Tabella attivit\u00e0 con 8 righe
echo   • Alert panel con 5 alert
echo   • Grafici con dati demo
echo.

pause