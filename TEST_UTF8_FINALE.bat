@echo off
title Test UTF-8 Finale

echo ========================================================================
echo   TEST UTF-8 FINALE - VERIFICA CARATTERI CORRETTI
echo ========================================================================
echo.

echo [INFO] Abbiamo applicato:
echo   • Fix database HEX per caratteri corrotti
echo   • Ricreazione alert con UTF-8 pulito  
echo   • Headers PHP UTF-8 aggressivi
echo   • Funzione cleanUTF8Text per rendering
echo   • Connessione MySQL con SET NAMES utf8mb4
echo.

echo [TEST 1] Verifica database MySQL...
echo [QUERY] SELECT message FROM alerts WHERE message LIKE '%%attivit%%';
echo.

C:\xampp\mysql\bin\mysql.exe -u root bait_service_real -e "SELECT id, LEFT(message, 50) as message FROM alerts ORDER BY id;"

echo.
echo [TEST 2] Apertura dashboard enterprise...
start http://localhost/controlli/laravel_bait/public/index_standalone.php

echo.
echo [WAIT] Attendi 3 secondi per caricamento dashboard...
timeout /t 3 /nobreak >nul

echo [TEST 3] Apertura test caratteri diagnostico...
start http://localhost/controlli/test_caratteri_fix.php

echo.
echo ========================================================================
echo   RISULTATI ATTESI
echo ========================================================================
echo.
echo [DATABASE] I messaggi MySQL dovrebbero mostrare "attivit\u00e0" corretto
echo.
echo [DASHBOARD] Gli alert dovrebbero mostrare:
echo   • "Durata attivit\u00e0 anomala rispetto alla media"
echo   • "Timbratura mancante per attivit\u00e0 registrata"  
echo   • "Sessione TeamViewer senza attivit\u00e0 corrispondente"
echo   • Tutti cliccabili con dettagli completi
echo.
echo [TEST DIAGNOSTICO] Dovrebbe mostrare:
echo   • "SUCCESS! Nessun carattere corrotto trovato"
echo   • Righe verdi per tutti gli alert
echo   • Caratteri di test \u00e0 \u00e8 \u00ec \u00f2 \u00f9 corretti
echo.

echo [TROUBLESHOOT] Se vedi ancora caratteri corrotti:
echo   1. Verifica browser cache (CTRL+F5)
echo   2. Prova browser diverso
echo   3. Verifica impostazioni sistema Windows
echo.

pause