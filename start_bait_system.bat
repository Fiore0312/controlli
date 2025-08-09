@echo off
REM BAIT Service - Sistema Avvio Automatico Windows
REM ==============================================
REM
REM Script di avvio completo per Franco
REM Path: C:\Users\Franco\Desktop\controlli
REM 
REM Autore: Franco - BAIT Service
REM Version: 2.0 - Windows Enterprise

echo.
echo ========================================
echo   BAIT SERVICE - AVVIO AUTOMATICO
echo ========================================
echo.
echo 🎯 Sistema: BAIT Service Enterprise-Grade
echo 👤 Utente: Franco
echo 📁 Directory: %CD%
echo ⏰ Avvio: %DATE% %TIME%
echo.

REM Controlla se Python è installato
echo 🔍 Controllo Python...
python --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Python non trovato!
    echo.
    echo 🔧 SOLUZIONE:
    echo    1. Scarica Python 3.11+ da https://python.org
    echo    2. Durante installazione, spunta "Add to PATH"
    echo    3. Riavvia questo script
    echo.
    pause
    exit /b 1
)

python --version
echo ✅ Python disponibile
echo.

REM Controlla se pip è disponibile  
echo 🔍 Controllo pip...
python -m pip --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ pip non disponibile!
    echo 🔧 Installazione pip...
    python -m ensurepip --upgrade
    if %ERRORLEVEL% NEQ 0 (
        echo ❌ Impossibile installare pip
        pause
        exit /b 1
    )
)
echo ✅ pip disponibile
echo.

REM Crea virtual environment se non esiste
if not exist "bait_env" (
    echo 🔧 Creazione ambiente virtuale...
    python -m venv bait_env
    if %ERRORLEVEL% NEQ 0 (
        echo ❌ Impossibile creare ambiente virtuale
        pause
        exit /b 1
    )
    echo ✅ Ambiente virtuale creato
) else (
    echo ✅ Ambiente virtuale esistente
)
echo.

REM Attiva ambiente virtuale
echo 🔧 Attivazione ambiente virtuale...
call bait_env\Scripts\activate.bat
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Impossibile attivare ambiente virtuale
    pause
    exit /b 1
)
echo ✅ Ambiente virtuale attivato
echo.

REM Controlla e installa dipendenze
echo 🔍 Controllo dipendenze BAIT Service...

REM Lista dipendenze richieste
set DEPENDENCIES=dash plotly pandas chardet openpyxl

echo 📦 Dipendenze richieste: %DEPENDENCIES%
echo.

REM Installa dipendenze mancanti
echo 🔧 Installazione/aggiornamento dipendenze...
python -m pip install --upgrade pip >nul 2>&1
python -m pip install %DEPENDENCIES% >nul 2>&1

if %ERRORLEVEL% NEQ 0 (
    echo ⚠️  Installazione dipendenze con potenziali warning
    echo 🔧 Tentativo installazione individuale...
    
    for %%d in (%DEPENDENCIES%) do (
        echo    Installing %%d...
        python -m pip install %%d >nul 2>&1
        if %ERRORLEVEL% EQU 0 (
            echo    ✅ %%d installato
        ) else (
            echo    ⚠️  %%d - possibili warning
        )
    )
) else (
    echo ✅ Dipendenze installate con successo
)
echo.

REM Verifica dipendenze critiche
echo 🔍 Verifica dipendenze critiche...
python -c "import dash, plotly, pandas; print('✅ Dipendenze critiche OK')" 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Dipendenze critiche mancanti
    echo 🔧 Tentativo installazione forzata...
    python -m pip install dash plotly pandas --force-reinstall >nul 2>&1
    python -c "import dash, plotly, pandas; print('✅ Dipendenze OK dopo reinstall')" 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo ❌ Impossibile installare dipendenze critiche
        echo.
        echo 🔧 TROUBLESHOOTING:
        echo    1. Verifica connessione internet
        echo    2. Esegui come amministratore
        echo    3. Controlla firewall/antivirus
        pause
        exit /b 1
    )
)
echo.

REM Crea cartella upload se non esiste
if not exist "upload_csv" (
    echo 🔧 Creazione cartella upload...
    mkdir upload_csv
    echo ✅ Cartella upload_csv creata
) else (
    echo ✅ Cartella upload_csv esistente
)
echo.

REM Controlla files sistema BAIT Service
echo 🔍 Controllo files sistema BAIT Service...
set SYSTEM_OK=1

if not exist "bait_dashboard_upload.py" (
    echo ❌ File mancante: bait_dashboard_upload.py
    set SYSTEM_OK=0
)

if not exist "bait_controller_v2.py" (
    echo ⚠️  File opzionale mancante: bait_controller_v2.py ^(modalità demo^)
)

if %SYSTEM_OK% EQU 0 (
    echo.
    echo ❌ Files sistema mancanti - impossibile continuare
    pause
    exit /b 1
)
echo ✅ Files sistema verificati
echo.

REM Controlla se la porta 8051 è libera
echo 🔍 Controllo porta 8051...
netstat -an | find ":8051" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo ⚠️  Porta 8051 in uso - fermata processi precedenti...
    taskkill /F /IM python.exe >nul 2>&1
    timeout /t 2 /nobreak >nul
    echo ✅ Porta liberata
) else (
    echo ✅ Porta 8051 disponibile
)
echo.

REM Avvio dashboard
echo ========================================
echo   🚀 AVVIO DASHBOARD BAIT SERVICE
echo ========================================
echo.
echo 🌐 URL Dashboard: http://localhost:8051
echo 📤 Upload Files: Drag ^& Drop Ready
echo 🔄 Auto-refresh: 10 secondi
echo 📁 Upload Directory: upload_csv\
echo.
echo 📋 WORKFLOW QUOTIDIANO:
echo    1. Trascina i 7 CSV nella dashboard
echo    2. Clicca "Processa Files"
echo    3. Visualizza risultati in tempo reale
echo.
echo 🛑 Per fermare: Premi CTRL+C o chiudi questa finestra
echo ========================================
echo.

REM Avvia dashboard in background e apri browser
echo 🔧 Avvio dashboard...
start /B python bait_dashboard_upload.py

REM Attesa per l'avvio del server
echo ⏳ Attesa avvio server...
timeout /t 5 /nobreak >nul

REM Apri browser automaticamente
echo 🌐 Apertura browser...
start http://localhost:8051

REM Loop di monitoraggio
echo.
echo ✅ DASHBOARD AVVIATA CON SUCCESSO!
echo.
echo 💡 SUGGERIMENTI:
echo    • Lascia questa finestra aperta
echo    • Usa la dashboard per upload file
echo    • Controlla alert in tempo reale
echo.
echo 🔍 Monitoraggio sistema attivo...
echo    Premi CTRL+C per fermare
echo.

:monitor
REM Controllo se il processo è ancora attivo
tasklist | find /i "python.exe" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ⚠️  Sistema fermato inaspettatamente
    echo 🔄 Tentativo riavvio automatico...
    start /B python bait_dashboard_upload.py
    timeout /t 5 /nobreak >nul
)

REM Attesa 30 secondi prima del prossimo controllo
timeout /t 30 /nobreak >nul
goto monitor

REM Cleanup in caso di interruzione
:cleanup
echo.
echo 🛑 Sistema fermato dall'utente
echo 🔧 Pulizia processi...
taskkill /F /IM python.exe >nul 2>&1
echo ✅ Cleanup completato
pause