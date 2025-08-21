@echo off
REM =============================================================================
REM BAIT SERVICE ENTERPRISE - Automated Backup Script
REM Production-ready backup with rotation and verification
REM =============================================================================

echo [%date% %time%] Starting BAIT Service Database Backup...

REM Configuration
set MYSQL_PATH=C:\xampp\mysql\bin
set BACKUP_PATH=C:\xampp\htdocs\controlli\backup_mysql
set DATABASE_NAME=bait_service_real
set MYSQL_USER=root
set MYSQL_PASSWORD=

REM Create backup directory if not exists
if not exist "%BACKUP_PATH%" mkdir "%BACKUP_PATH%"

REM Generate timestamp for backup filename
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "YY=%dt:~2,2%" & set "YYYY=%dt:~0,4%" & set "MM=%dt:~4,2%" & set "DD=%dt:~6,2%"
set "HH=%dt:~8,2%" & set "Min=%dt:~10,2%" & set "Sec=%dt:~12,2%"
set "timestamp=%YYYY%%MM%%DD%_%HH%%Min%%Sec%"

REM Backup filenames
set FULL_BACKUP=%BACKUP_PATH%\bait_service_full_%timestamp%.sql
set SCHEMA_BACKUP=%BACKUP_PATH%\bait_service_schema_%timestamp%.sql
set DATA_BACKUP=%BACKUP_PATH%\bait_service_data_%timestamp%.sql

echo [%date% %time%] Backup files will be created:
echo   Full: %FULL_BACKUP%
echo   Schema: %SCHEMA_BACKUP%
echo   Data: %DATA_BACKUP%

REM 1. Full database backup with structure and data
echo [%date% %time%] Creating full database backup...
"%MYSQL_PATH%\mysqldump.exe" ^
  --user=%MYSQL_USER% ^
  --single-transaction ^
  --routines ^
  --triggers ^
  --events ^
  --hex-blob ^
  --default-character-set=utf8mb4 ^
  --set-charset ^
  --comments ^
  --dump-date ^
  %DATABASE_NAME% > "%FULL_BACKUP%"

if %ERRORLEVEL% neq 0 (
    echo [%date% %time%] ERROR: Full backup failed!
    goto :error
)

REM 2. Schema-only backup for structure reference  
echo [%date% %time%] Creating schema-only backup...
"%MYSQL_PATH%\mysqldump.exe" ^
  --user=%MYSQL_USER% ^
  --no-data ^
  --routines ^
  --triggers ^
  --events ^
  --default-character-set=utf8mb4 ^
  %DATABASE_NAME% > "%SCHEMA_BACKUP%"

if %ERRORLEVEL% neq 0 (
    echo [%date% %time%] ERROR: Schema backup failed!
    goto :error
)

REM 3. Data-only backup for critical tables
echo [%date% %time%] Creating critical data backup...
"%MYSQL_PATH%\mysqldump.exe" ^
  --user=%MYSQL_USER% ^
  --no-create-info ^
  --single-transaction ^
  --hex-blob ^
  --default-character-set=utf8mb4 ^
  --where="data_creazione >= DATE_SUB(NOW(), INTERVAL 90 DAY)" ^
  %DATABASE_NAME% ^
  audit_alerts ^
  alert_dettagliati ^
  technician_daily_analysis > "%DATA_BACKUP%"

if %ERRORLEVEL% neq 0 (
    echo [%date% %time%] ERROR: Data backup failed!
    goto :error
)

REM 4. Verify backup files exist and have content
echo [%date% %time%] Verifying backup files...

if not exist "%FULL_BACKUP%" (
    echo [%date% %time%] ERROR: Full backup file not created!
    goto :error
)

if not exist "%SCHEMA_BACKUP%" (
    echo [%date% %time%] ERROR: Schema backup file not created!
    goto :error
)

if not exist "%DATA_BACKUP%" (
    echo [%date% %time%] ERROR: Data backup file not created!
    goto :error
)

REM Check file sizes
for %%I in ("%FULL_BACKUP%") do set full_size=%%~zI
for %%I in ("%SCHEMA_BACKUP%") do set schema_size=%%~zI
for %%I in ("%DATA_BACKUP%") do set data_size=%%~zI

if %full_size% LSS 1000 (
    echo [%date% %time%] ERROR: Full backup file too small - likely failed!
    goto :error
)

REM 5. Log backup success to database
echo [%date% %time%] Logging backup completion to database...
"%MYSQL_PATH%\mysql.exe" -u %MYSQL_USER% -e ^
  "USE %DATABASE_NAME%; ^
   INSERT INTO backup_metadata (backup_type, backup_filename, backup_start_time, backup_end_time, backup_size_bytes, backup_status, backup_path, verification_status) VALUES ^
   ('full', 'bait_service_full_%timestamp%.sql', NOW(), NOW(), %full_size%, 'completed', '%BACKUP_PATH%', 'verified');"

REM 6. Cleanup old backups (keep last 30 days)
echo [%date% %time%] Cleaning up old backup files...
forfiles /p "%BACKUP_PATH%" /s /m *.sql /d -30 /c "cmd /c echo Deleting @path && del @path" 2>nul

REM 7. Success summary
echo [%date% %time%] ========================================
echo BACKUP COMPLETED SUCCESSFULLY!
echo Full backup size: %full_size% bytes
echo Schema backup size: %schema_size% bytes  
echo Data backup size: %data_size% bytes
echo Backup location: %BACKUP_PATH%
echo ========================================

REM Optional: Send email notification (requires configured SMTP)
REM powershell.exe -Command "Send-MailMessage -From 'backup@baitservice.com' -To 'admin@baitservice.com' -Subject 'BAIT DB Backup Success %timestamp%' -Body 'Database backup completed successfully at %date% %time%' -SmtpServer 'localhost'"

goto :end

:error
echo [%date% %time%] ========================================
echo BACKUP FAILED! 
echo Check error messages above.
echo Manual intervention required.
echo ========================================

REM Log error to database if possible
"%MYSQL_PATH%\mysql.exe" -u %MYSQL_USER% -e ^
  "USE %DATABASE_NAME%; ^
   INSERT INTO backup_metadata (backup_type, backup_filename, backup_start_time, backup_end_time, backup_status, backup_path) VALUES ^
   ('full', 'bait_service_full_%timestamp%.sql', NOW(), NOW(), 'failed', '%BACKUP_PATH%');" 2>nul

exit /b 1

:end
echo [%date% %time%] Backup script completed.
exit /b 0