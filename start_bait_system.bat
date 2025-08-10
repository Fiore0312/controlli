@echo off
REM BAIT Service - Sistema Avvio Automatico Windows
REM ==============================================
REM
REM Script di avvio completo per Franco
REM Path: C:\Users\Franco\Desktop\controlli
REM 
REM Autore: Franco - BAIT Service
REM Version: 2.1 - Windows Compatible

echo.
echo ========================================
echo   BAIT SERVICE - AVVIO AUTOMATICO
echo ========================================
echo.
echo [SISTEMA] BAIT Service Enterprise-Grade
echo [UTENTE]  Franco
echo [PATH]    %CD%
echo [AVVIO]   %DATE% %TIME%
echo.

REM Controlla se Python è installato
echo [CHECK] Controllo Python...
python --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] Python non trovato!
    echo.
    echo [SOLUZIONE]
    echo    1. Scarica Python 3.11+ da https://python.org
    echo    2. Durante installazione, spunta "Add to PATH"
    echo    3. Riavvia questo script
    echo.
    pause
    exit /b 1
)

python --version
echo [OK] Python disponibile
echo.

REM Controlla se pip è disponibile  
echo [CHECK] Controllo pip...
python -m pip --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] pip non disponibile!
    echo [FIX] Installazione pip...
    python -m ensurepip --upgrade
    if %ERRORLEVEL% NEQ 0 (
        echo [ERRORE] Impossibile installare pip
        pause
        exit /b 1
    )
)
echo [OK] pip disponibile
echo.

REM Elimina ambiente virtuale corrotto se esiste
if exist "bait_env" (
    echo [CLEANUP] Rimozione ambiente virtuale esistente...
    rmdir /s /q "bait_env" >nul 2>&1
    timeout /t 2 /nobreak >nul
)

REM Crea nuovo virtual environment
echo [SETUP] Creazione nuovo ambiente virtuale...
python -m venv bait_env
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] Impossibile creare ambiente virtuale
    pause
    exit /b 1
)
echo [OK] Ambiente virtuale creato

REM Attiva ambiente virtuale - Versione robusta
echo [SETUP] Attivazione ambiente virtuale...
set VENV_PATH=%CD%\bait_env\Scripts\activate.bat
echo [DEBUG] Percorso activate: %VENV_PATH%

if exist "%VENV_PATH%" (
    call "%VENV_PATH%"
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Ambiente virtuale attivato
    ) else (
        echo [ERRORE] Fallita attivazione ambiente virtuale
        pause
        exit /b 1
    )
) else (
    echo [ERRORE] File activate.bat non trovato in %VENV_PATH%
    pause
    exit /b 1
)
echo.

REM Controlla e installa dipendenze
echo [DEPS] Controllo dipendenze BAIT Service...

REM Lista dipendenze richieste
set DEPENDENCIES=dash plotly pandas chardet openpyxl

echo [DEPS] Dipendenze richieste: %DEPENDENCIES%
echo.

REM Installa dipendenze mancanti
echo [INSTALL] Installazione/aggiornamento dipendenze...
python -m pip install --upgrade pip
if %ERRORLEVEL% NEQ 0 (
    echo [WARNING] Possibili problemi con upgrade pip
)

python -m pip install %DEPENDENCIES%
if %ERRORLEVEL% NEQ 0 (
    echo [WARNING] Installazione con possibili warning - tentativo individuale...
    
    for %%d in (%DEPENDENCIES%) do (
        echo [INSTALL] Installing %%d...
        python -m pip install %%d
        if %ERRORLEVEL% EQU 0 (
            echo [OK] %%d installato
        ) else (
            echo [WARNING] %%d - possibili warning ma continuo
        )
    )
) else (
    echo [OK] Dipendenze installate con successo
)
echo.

REM Verifica dipendenze critiche
echo [CHECK] Verifica dipendenze critiche...
python -c "import dash, plotly, pandas; print('[OK] Dipendenze critiche verificate')" 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] Dipendenze critiche mancanti
    echo [FIX] Tentativo installazione forzata...
    python -m pip install dash plotly pandas --force-reinstall
    python -c "import dash, plotly, pandas; print('[OK] Dipendenze OK dopo reinstall')" 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo [ERRORE] Impossibile installare dipendenze critiche
        echo.
        echo [TROUBLESHOOTING]
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
    echo [SETUP] Creazione cartella upload...
    mkdir upload_csv
    echo [OK] Cartella upload_csv creata
) else (
    echo [OK] Cartella upload_csv esistente
)
echo.

REM Controlla files sistema BAIT Service
echo [CHECK] Controllo files sistema BAIT Service...
set SYSTEM_OK=1

if not exist "bait_dashboard_upload.py" (
    echo [ERRORE] File mancante: bait_dashboard_upload.py
    set SYSTEM_OK=0
)

if not exist "bait_controller_v2.py" (
    echo [WARNING] File opzionale mancante: bait_controller_v2.py (modalita demo)
)

if %SYSTEM_OK% EQU 0 (
    echo.
    echo [ERRORE] Files sistema mancanti - impossibile continuare
    pause
    exit /b 1
)
echo [OK] Files sistema verificati
echo.

REM Controlla se la porta 8052 è libera
echo [CHECK] Controllo porta 8052...
netstat -an | find ":8052" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [WARNING] Porta 8052 in uso - fermata processi precedenti...
    taskkill /F /IM python.exe >nul 2>&1
    timeout /t 2 /nobreak >nul
    echo [OK] Porta liberata
) else (
    echo [OK] Porta 8052 disponibile
)
echo.

REM Processing automatico dati
echo ========================================
echo   [PROCESSING] ELABORAZIONE DATI BAIT
echo ========================================
echo.
echo [PROCESSING] Avvio elaborazione dati automatica...
python bait_controller_v2.py
if %ERRORLEVEL% NEQ 0 (
    echo [WARNING] Processing completato con warning - continuo con dashboard
) else (
    echo [OK] Processing completato con successo
)
echo.

REM Avvio dashboard
echo ========================================
echo   [LAUNCH] AVVIO DASHBOARD BAIT SERVICE
echo ========================================
echo.
echo [URL]      Dashboard Upload: http://localhost:8052
echo [URL]      Dashboard Simple: http://localhost:8051
echo [UPLOAD]   Files: Drag ^& Drop Ready
echo [REFRESH]  Auto-refresh: 10 secondi
echo [FOLDER]   Upload Directory: upload_csv\
echo.
echo [WORKFLOW] QUOTIDIANO:
echo    1. Processing automatico al avvio (COMPLETATO)
echo    2. Dashboard semplice disponibile su porta 8051
echo    3. Dashboard upload disponibile su porta 8052
echo.
echo [STOP] Per fermare: Premi CTRL+C o chiudi questa finestra
echo ========================================
echo.

REM Avvia dashboard semplice in finestra separata
echo [LAUNCH] Avvio dashboard semplice (porta 8051)...
start python bait_simple_dashboard.py

REM Attesa per l'avvio dashboard semplice
timeout /t 3 /nobreak >nul

REM Avvia dashboard upload in background
echo [LAUNCH] Avvio dashboard upload (porta 8052)...
start /B python bait_dashboard_upload.py

REM Attesa per l'avvio del server upload
echo [WAIT] Attesa avvio server upload...
timeout /t 5 /nobreak >nul

REM Apri entrambe le dashboard automaticamente
echo [BROWSER] Apertura dashboard semplice...
start http://localhost:8051
timeout /t 2 /nobreak >nul
echo [BROWSER] Apertura dashboard upload...
start http://localhost:8052

REM Loop di monitoraggio
echo.
echo [SUCCESS] DASHBOARD AVVIATA CON SUCCESSO!
echo.
echo [TIPS] SUGGERIMENTI:
echo    - Lascia questa finestra aperta
echo    - Usa la dashboard per upload file
echo    - Controlla alert in tempo reale
echo.
echo [MONITOR] Monitoraggio sistema attivo...
echo          Premi CTRL+C per fermare
echo.

:monitor
REM Controllo se il processo è ancora attivo
tasklist | find /i "python.exe" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [WARNING] Sistema fermato inaspettatamente
    echo [RESTART] Tentativo riavvio automatico...
    start /B python bait_dashboard_upload.py
    timeout /t 5 /nobreak >nul
)

REM Attesa 30 secondi prima del prossimo controllo
timeout /t 30 /nobreak >nul
goto monitor

REM Cleanup in caso di interruzione
:cleanup
echo.
echo [STOP] Sistema fermato dall'utente
echo [CLEANUP] Pulizia processi...
taskkill /F /IM python.exe >nul 2>&1
echo [OK] Cleanup completato
pause