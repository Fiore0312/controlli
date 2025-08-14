@echo off
title Verifica Fix Caratteri Italiani

echo ========================================================================
echo   VERIFICA FIX CARATTERI ITALIANI - CONTROLLO RAPIDO
echo ========================================================================
echo.

echo [TEST] Apertura test caratteri...
start http://localhost/controlli/test_caratteri_fix.php

echo.
echo [WAIT] Attendi 3 secondi per caricamento test...
timeout /t 3 /nobreak >nul

echo [DASHBOARD] Apertura dashboard per verifica finale...
start http://localhost/controlli/laravel_bait/public/index_standalone.php

echo.
echo ========================================================================
echo   VERIFICA COMPLETATA
echo ========================================================================
echo.
echo [EXPECTED] Nel test dovresti vedere:
echo   • Righe VERDI (nessun carattere corrotto)
echo   • "SUCCESS! Nessun carattere corrotto trovato"
echo   • Caratteri à, è, ì, ò, ù visualizzati correttamente
echo.
echo [EXPECTED] Nella dashboard dovresti vedere:
echo   • "attività" al posto di "attivit├á"
echo   • Tutti gli accenti italiani corretti
echo   • Alert cliccabili con dettagli corretti
echo.

pause