<?php
/**
 * BAIT Service - Incremental Upload Manager
 * Sistema completo per upload incrementale giornaliero con backup automatico
 */

class IncrementalUploadManager {
    private $pdo;
    private $uploadDir;
    private $archiveDir;
    private $logEntries = [];
    private $processedFiles = [];
    private $importStats = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->uploadDir = __DIR__ . '/upload_csv/';
        $this->archiveDir = $this->uploadDir . 'old/';
        
        // Ensure directories exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->archiveDir)) {
            mkdir($this->archiveDir, 0755, true);
        }
        
        $this->createAuditTables();
    }

    /**
     * Create necessary audit tables if they don't exist
     */
    private function createAuditTables() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS daily_imports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    import_date DATE NOT NULL,
                    import_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    file_name VARCHAR(100) NOT NULL,
                    records_total INT DEFAULT 0,
                    records_new INT DEFAULT 0,
                    records_existing INT DEFAULT 0,
                    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
                    error_message TEXT NULL,
                    UNIQUE KEY unique_daily_file (import_date, file_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS import_audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    import_id INT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    level ENUM('INFO', 'WARNING', 'ERROR', 'SUCCESS') DEFAULT 'INFO',
                    message TEXT NOT NULL,
                    details JSON NULL,
                    FOREIGN KEY (import_id) REFERENCES daily_imports(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $this->log('SUCCESS', 'Audit tables initialized successfully');
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to create audit tables: ' . $e->getMessage());
        }
    }

    /**
     * Process uploaded files with incremental logic
     */
    public function processUploadedFiles($uploadedFiles) {
        $this->log('INFO', 'Starting incremental upload processing', [
            'files_received' => count($uploadedFiles),
            'upload_time' => date('Y-m-d H:i:s')
        ]);

        $results = [
            'success' => false,
            'files_processed' => 0,
            'total_new_records' => 0,
            'details' => [],
            'errors' => [],
            'backup_info' => [],
            'import_stats' => []
        ];

        // File mapping
        $fileMapping = [
            'attivita' => 'attivita.csv',
            'timbrature' => 'timbrature.csv',
            'teamviewer_bait' => 'teamviewer_bait.csv',
            'teamviewer_gruppo' => 'teamviewer_gruppo.csv',
            'permessi' => 'permessi.csv',
            'auto' => 'auto.csv',
            'calendario' => 'calendario.csv'
        ];

        foreach ($uploadedFiles as $fieldName => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || !isset($fileMapping[$fieldName])) {
                continue;
            }

            $fileName = $fileMapping[$fieldName];
            $tempFile = $file['tmp_name'];
            $targetPath = $this->uploadDir . $fileName;

            try {
                // Step 1: Backup existing file if it exists
                $backupInfo = $this->backupExistingFile($fileName);
                if ($backupInfo['backed_up']) {
                    $results['backup_info'][] = $backupInfo['message'];
                    $this->log('INFO', 'File backup completed', $backupInfo);
                }

                // Step 2: Move new file to target location
                if (is_uploaded_file($tempFile)) {
                    // Real HTTP upload
                    if (!move_uploaded_file($tempFile, $targetPath)) {
                        throw new Exception("Failed to move uploaded file to target location");
                    }
                } else {
                    // For testing or existing file processing
                    if (!copy($tempFile, $targetPath)) {
                        throw new Exception("Failed to copy file to target location");
                    }
                }

                $results['files_processed']++;
                $this->log('SUCCESS', "File uploaded successfully: $fileName");

                // Step 3: Process incremental import
                $importResult = $this->processIncrementalImport($fileName);
                $results['total_new_records'] += $importResult['new_records'];
                $results['import_stats'][] = $importResult;
                $results['details'][] = $importResult['summary'];

            } catch (Exception $e) {
                $results['errors'][] = "❌ Error processing $fileName: " . $e->getMessage();
                $this->log('ERROR', "File processing failed: $fileName", ['error' => $e->getMessage()]);
            }
        }

        $results['success'] = $results['files_processed'] > 0;
        
        $this->log('INFO', 'Upload processing completed', [
            'files_processed' => $results['files_processed'],
            'total_new_records' => $results['total_new_records']
        ]);

        return $results;
    }

    /**
     * Backup existing file with date-based naming
     */
    private function backupExistingFile($fileName) {
        $sourcePath = $this->uploadDir . $fileName;
        $result = ['backed_up' => false, 'message' => '', 'backup_path' => ''];

        if (!file_exists($sourcePath)) {
            return $result;
        }

        // Generate backup name with current date (DDMM format)
        $dateStr = date('dm'); // e.g., 2808 for August 28
        $backupName = str_replace('.csv', "_$dateStr.csv", $fileName);
        $backupPath = $this->archiveDir . $backupName;

        // If backup already exists, add time suffix
        if (file_exists($backupPath)) {
            $timeStr = date('His'); // HHMMSS
            $backupName = str_replace('.csv', "_{$dateStr}_{$timeStr}.csv", $fileName);
            $backupPath = $this->archiveDir . $backupName;
        }

        if (copy($sourcePath, $backupPath)) {
            $result['backed_up'] = true;
            $result['backup_path'] = $backupPath;
            $result['message'] = "💾 Backup created: $backupName (" . number_format(filesize($backupPath)) . " bytes)";
        }

        return $result;
    }

    /**
     * Process incremental import - only import new records
     */
    private function processIncrementalImport($fileName) {
        $filePath = $this->uploadDir . $fileName;
        
        $result = [
            'file_name' => $fileName,
            'total_records' => 0,
            'new_records' => 0,
            'existing_records' => 0,
            'summary' => '',
            'table_name' => ''
        ];

        // Map files to database tables
        $tableMapping = [
            'attivita.csv' => 'deepser_attivita',
            'timbrature.csv' => 'timbrature',
            'teamviewer_bait.csv' => 'teamviewer_sessions',
            'teamviewer_gruppo.csv' => 'teamviewer_sessions',
            'permessi.csv' => 'richieste_permessi',
            'auto.csv' => 'utilizzi_auto',
            'calendario.csv' => 'eventi_calendario'
        ];

        if (!isset($tableMapping[$fileName])) {
            $result['summary'] = "⚠️ No table mapping for $fileName";
            return $result;
        }

        $tableName = $tableMapping[$fileName];
        $result['table_name'] = $tableName;

        // Record import start
        $importId = $this->recordImportStart($fileName);

        try {
            // Read and parse CSV
            $csvData = $this->readCSVWithSemicolon($filePath);
            if (!$csvData || count($csvData) <= 1) {
                $result['summary'] = "⚠️ $fileName: Empty or invalid CSV file";
                return $result;
            }

            $headers = array_shift($csvData);
            $result['total_records'] = count($csvData);

            // Process based on file type
            switch ($fileName) {
                case 'attivita.csv':
                    $importResult = $this->importAttivitaIncremental($csvData, $headers);
                    break;
                case 'timbrature.csv':
                    $importResult = $this->importTimbratureIncremental($csvData, $headers);
                    break;
                case 'teamviewer_bait.csv':
                case 'teamviewer_gruppo.csv':
                    $importResult = $this->importTeamViewerIncremental($csvData, $headers, $fileName);
                    break;
                case 'permessi.csv':
                    $importResult = $this->importPermessiIncremental($csvData, $headers);
                    break;
                case 'auto.csv':
                    $importResult = $this->importAutoIncremental($csvData, $headers);
                    break;
                case 'calendario.csv':
                    $importResult = $this->importCalendarioIncremental($csvData, $headers);
                    break;
                default:
                    $importResult = ['new' => 0, 'existing' => 0];
                    $this->log('WARNING', "No import handler for $fileName - skipping");
            }

            $result['new_records'] = $importResult['new'];
            $result['existing_records'] = $importResult['existing'];

            // Create summary message
            $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
            $result['summary'] = "✅ $fileName: {$result['new_records']} nuovi record importati, {$result['existing_records']} esistenti saltati (file: $fileTime)";

            $this->recordImportComplete($importId, $result);

        } catch (Exception $e) {
            $result['summary'] = "❌ $fileName: Error - " . $e->getMessage();
            $this->recordImportError($importId, $e->getMessage());
            $this->log('ERROR', "Import failed for $fileName", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Import attivita with duplicate detection
     */
    private function importAttivitaIncremental($csvData, $headers) {
        $newRecords = 0;
        $existingRecords = 0;

        foreach ($csvData as $row) {
            if (count($row) < 8) continue;

            // Parse data
            $id_ticket = trim($row[1] ?? '');
            $iniziata_il = trim($row[2] ?? '');
            $azienda = trim($row[4] ?? '');
            $descrizione = trim($row[7] ?? '');
            $durata = trim($row[9] ?? '');
            $creato_da = trim($row[11] ?? '');

            // Parse date
            $data_attivita = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $iniziata_il, $matches)) {
                $data_attivita = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            if (!$data_attivita || empty($id_ticket)) continue;

            // Check if record already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM deepser_attivita 
                WHERE descrizione LIKE ? AND data_attivita = ?
                LIMIT 1
            ");
            $stmt->execute(['%' . $id_ticket . '%', $data_attivita]);
            
            if ($stmt->fetch()) {
                $existingRecords++;
                continue;
            }

            // Parse duration
            $ore_lavorate = 0;
            if (preg_match('/(\d+):(\d+)/', $durata, $matches)) {
                $ore_lavorate = floatval($matches[1]) + (floatval($matches[2]) / 60);
            }

            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO deepser_attivita (tecnico_id, cliente_id, descrizione, data_attivita, 
                                            ore_lavorate, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                1, // Default tecnico_id
                1, // Default cliente_id
                $descrizione . ' [' . $azienda . '] - Ticket: ' . $id_ticket,
                $data_attivita,
                $ore_lavorate
            ]);

            $newRecords++;
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Import timbrature with duplicate detection
     */
    private function importTimbratureIncremental($csvData, $headers) {
        $newRecords = 0;
        $existingRecords = 0;

        foreach ($csvData as $row) {
            if (count($row) < 11) continue;

            $dipendente_nome = trim($row[0] ?? '');
            $dipendente_cognome = trim($row[1] ?? '');
            $ora_inizio = trim($row[8] ?? '');
            $ora_fine = trim($row[9] ?? '');

            // Parse date
            $data_timbratura = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $ora_inizio, $matches)) {
                $data_timbratura = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            if (!$data_timbratura || empty($dipendente_nome)) continue;

            // Check if record already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM timbrature 
                WHERE data_timbratura = ? AND note LIKE ?
                LIMIT 1
            ");
            $stmt->execute([$data_timbratura, '%' . $dipendente_nome . '%']);
            
            if ($stmt->fetch()) {
                $existingRecords++;
                continue;
            }

            // Parse times and hours
            $ora_ingresso = null;
            $ora_uscita = null;
            $ore_lavorate = 0;

            if (preg_match('/\d{2}\/\d{2}\/\d{4} (\d{2}:\d{2})/', $ora_inizio, $matches)) {
                $ora_ingresso = $matches[1] . ':00';
            }
            if (preg_match('/\d{2}\/\d{2}\/\d{4} (\d{2}:\d{2})/', $ora_fine, $matches)) {
                $ora_uscita = $matches[1] . ':00';
            }

            $ore_str = trim($row[10] ?? '');
            if (preg_match('/(\d+):(\d+)/', $ore_str, $matches)) {
                $ore_lavorate = floatval($matches[1]) + (floatval($matches[2]) / 60);
            }

            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO timbrature (tecnico_id, data_timbratura, ora_ingresso, ora_uscita, 
                                      ore_lavorate, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                1, // Default tecnico_id
                $data_timbratura,
                $ora_ingresso,
                $ora_uscita,
                $ore_lavorate,
                $dipendente_nome . ' ' . $dipendente_cognome
            ]);

            $newRecords++;
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Import TeamViewer with duplicate detection
     */
    private function importTeamViewerIncremental($csvData, $headers, $fileName) {
        $newRecords = 0;
        $existingRecords = 0;
        $fileType = strpos($fileName, 'bait') !== false ? 'bait' : 'gruppo';

        // csvData già ha l'header rimosso dal chiamante
        foreach ($csvData as $row) {
            if (count($row) < 6) continue;

            try {
                if ($fileType === 'bait') {
                    // teamviewer_bait.csv format - CORRETTO
                    // Header: Assegnatario,Nome,Codice,Tipo di sessione,Gruppo,Inizio,Fine,Durata,Note,Classificazione,Commenti del cliente
                    $assegnatario = trim($row[0]); // Assegnatario = tecnico
                    $nome_cliente = trim($row[1]); // Nome = cliente  
                    $codice = trim($row[2]); // Codice sessione
                    $inizio = isset($row[5]) ? trim($row[5]) : ''; // Inizio
                    $fine = isset($row[6]) ? trim($row[6]) : ''; // Fine
                    $durata_str = isset($row[7]) ? trim($row[7]) : ''; // Durata es. "12m", "1h 23m"
                    
                    // Converte durata in minuti
                    $durata_minuti = 0;
                    if (preg_match('/(\d+)h/', $durata_str, $matches)) {
                        $durata_minuti += intval($matches[1]) * 60;
                    }
                    if (preg_match('/(\d+)m/', $durata_str, $matches)) {
                        $durata_minuti += intval($matches[1]);
                    }
                    
                    // Check for duplicates based on session_id (codice)
                    $stmt = $this->pdo->prepare("SELECT id FROM teamviewer_sessions WHERE session_id = ?");
                    $stmt->execute([$codice]);
                    
                    if ($stmt->fetch()) {
                        $existingRecords++;
                        continue;
                    }
                    
                    // Parse date/time - formato corretto d/m/Y H:i
                    $data_inizio = DateTime::createFromFormat('d/m/Y H:i', $inizio);
                    if ($data_inizio) {
                        $data_sessione = $data_inizio->format('Y-m-d');
                        $ora_inizio = $data_inizio->format('H:i:s');
                        
                        // Calcola ora fine
                        $data_fine = clone $data_inizio;
                        $data_fine->add(new DateInterval('PT' . $durata_minuti . 'M'));
                        $ora_fine = $data_fine->format('H:i:s');
                        
                        // Insert nel database
                        $stmt = $this->pdo->prepare("
                            INSERT INTO teamviewer_sessions 
                            (session_id, tecnico_id, cliente_id, data_sessione, ora_inizio, ora_fine, durata_minuti, tipo_sessione, computer_remoto, descrizione, created_at)
                            VALUES (?, 1, 1, ?, ?, ?, ?, 'user', ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $codice,
                            $data_sessione,
                            $ora_inizio,
                            $ora_fine,
                            $durata_minuti,
                            $nome_cliente,
                            "Tecnico: $assegnatario"
                        ]);
                        
                        $newRecords++;
                    }
                    
                } else {
                    // teamviewer_gruppo.csv format  
                    // Header: Utente,Computer,ID,Tipo di sessione,Gruppo,Inizio,Fine,Durata,Valuta,Tariffa,Calcolo,Note
                    $utente = trim($row[0]); // Utente = tecnico
                    $computer = trim($row[1]); // Computer
                    $session_id = trim($row[2]); // ID sessione
                    $inizio = isset($row[5]) ? trim($row[5]) : ''; // Inizio
                    $fine = isset($row[6]) ? trim($row[6]) : ''; // Fine
                    $durata_minuti = isset($row[7]) ? intval(trim($row[7])) : 0; // Durata in minuti
                    
                    // Check for duplicates based on session_id
                    $stmt = $this->pdo->prepare("SELECT id FROM teamviewer_sessions WHERE session_id = ?");
                    $stmt->execute([$session_id]);
                    
                    if ($stmt->fetch()) {
                        $existingRecords++;
                        continue;
                    }
                    
                    // Parse date/time - formato corretto d/m/Y H:i
                    $data_inizio = DateTime::createFromFormat('d/m/Y H:i', $inizio);
                    if ($data_inizio) {
                        $data_sessione = $data_inizio->format('Y-m-d');
                        $ora_inizio = $data_inizio->format('H:i:s');
                        
                        $data_fine = DateTime::createFromFormat('Y-m-d H:i', $fine);
                        $ora_fine = $data_fine ? $data_fine->format('H:i:s') : $ora_inizio;
                        
                        // Insert nel database
                        $stmt = $this->pdo->prepare("
                            INSERT INTO teamviewer_sessions 
                            (session_id, tecnico_id, cliente_id, data_sessione, ora_inizio, ora_fine, durata_minuti, tipo_sessione, computer_remoto, descrizione, created_at)
                            VALUES (?, 1, 1, ?, ?, ?, ?, 'server', ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $session_id,
                            $data_sessione,
                            $ora_inizio,
                            $ora_fine,
                            $durata_minuti,
                            $computer,
                            "Gruppo: $utente"
                        ]);
                        
                        $newRecords++;
                    }
                }
                
            } catch (Exception $e) {
                $this->log('ERROR', "ERRORE TeamViewer import: " . $e->getMessage());
                continue;
            }
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Read CSV with proper delimiter and encoding (detects ; or , based on filename)
     */
    private function readCSVWithSemicolon($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $data = [];
        $content = file_get_contents($filePath);
        
        // Handle BOM and encoding
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', ['Windows-1252', 'ISO-8859-1']);
        }
        
        // Detect delimiter - teamviewer_gruppo uses comma, others use semicolon
        $delimiter = (strpos($filePath, 'teamviewer_gruppo') !== false) ? ',' : ';';
        
        // Split by lines and parse with detected delimiter
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $row = str_getcsv($line, $delimiter);
                $data[] = $row;
            }
        }
        
        return $data;
    }

    /**
     * Record import start in audit table
     */
    private function recordImportStart($fileName) {
        $stmt = $this->pdo->prepare("
            INSERT INTO daily_imports (import_date, file_name, status)
            VALUES (CURDATE(), ?, 'started')
            ON DUPLICATE KEY UPDATE 
            import_timestamp = CURRENT_TIMESTAMP, status = 'started'
        ");
        $stmt->execute([$fileName]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Record import completion
     */
    private function recordImportComplete($importId, $result) {
        $stmt = $this->pdo->prepare("
            UPDATE daily_imports SET 
            records_total = ?, records_new = ?, records_existing = ?, 
            status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([
            $result['total_records'],
            $result['new_records'], 
            $result['existing_records'],
            $importId
        ]);
    }

    /**
     * Record import error
     */
    private function recordImportError($importId, $errorMessage) {
        $stmt = $this->pdo->prepare("
            UPDATE daily_imports SET 
            status = 'failed', error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$errorMessage, $importId]);
    }

    /**
     * Add log entry
     */
    private function log($level, $message, $details = null) {
        $this->logEntries[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'details' => $details
        ];
    }

    /**
     * Get all log entries
     */
    public function getLogs() {
        return $this->logEntries;
    }

    /**
     * Import permessi with duplicate detection
     */
    private function importPermessiIncremental($csvData, $headers) {
        $newRecords = 0;
        $existingRecords = 0;

        foreach ($csvData as $row) {
            if (count($row) < 4) continue;

            // Il CSV ha: Data della richiesta;Dipendente;Tipo;Data inizio;Data fine;Stato;Note
            $data_richiesta = trim($row[0] ?? '');
            $dipendente = trim($row[1] ?? '');
            $tipo = trim($row[2] ?? '');
            $data_inizio = trim($row[3] ?? '');
            $data_fine = trim($row[4] ?? '');
            $stato = trim($row[5] ?? '');
            $note = trim($row[6] ?? '');

            if (empty($dipendente) || empty($tipo) || empty($data_richiesta)) continue;

            // Parse data_richiesta (formato: 2025-06-18 17:53:47)
            $data_richiesta_parsed = null;
            if (preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2}:\d{2})/', $data_richiesta, $matches)) {
                $data_richiesta_parsed = $data_richiesta; // È già in formato MySQL
            }

            // Parse data_inizio (formato: 25/08/2025 o 04/08/2025 15:00:00)
            $data_inizio_parsed = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data_inizio, $matches)) {
                $data_inizio_parsed = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            // Parse data_fine
            $data_fine_parsed = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data_fine, $matches)) {
                $data_fine_parsed = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            if (!$data_richiesta_parsed) continue;

            // Check for duplicates
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM richieste_permessi 
                WHERE data_richiesta = ? AND tipo = ? AND data_inizio = ?
            ");
            $stmt->execute([$data_richiesta_parsed, $tipo, $data_inizio_parsed]);
            
            if ($stmt->fetchColumn() > 0) {
                $existingRecords++;
                continue;
            }

            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO richieste_permessi (tecnico_id, tipo, data_richiesta, data_inizio, data_fine, stato, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                1, // Default tecnico_id
                $tipo, 
                $data_richiesta_parsed, 
                $data_inizio_parsed, 
                $data_fine_parsed, 
                $stato,
                $dipendente . ' - ' . $note
            ]);
            $newRecords++;
            
            $this->log('info', "Importato permesso: {$dipendente} - {$tipo}");
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Import auto with duplicate detection - FORMATO CORRETTO
     */
    private function importAutoIncremental($csvData, $headers) {
        $newRecords = 0;
        $existingRecords = 0;

        foreach ($csvData as $row) {
            if (count($row) < 6) continue;

            // Il CSV ha: Dipendente;Data;Auto;Presa Data e Ora;Riconsegna Data e Ora;Cliente;Ore...
            $dipendente = trim($row[0] ?? '');
            $data = trim($row[1] ?? '');
            $auto = trim($row[2] ?? '');
            $presa_data_ora = trim($row[3] ?? '');
            $riconsegna_data_ora = trim($row[4] ?? '');
            $cliente = trim($row[5] ?? '');

            // Skip se è una riga di Excel pivot table o vuota
            if (empty($dipendente) || empty($data) || empty($auto) || 
                strpos($dipendente, 'Somma') !== false ||
                strpos($dipendente, 'Etichette') !== false ||
                strpos($dipendente, '(vuoto)') !== false) {
                continue;
            }

            // Parse data (formato: 05/08/2025)
            $data_utilizzo_parsed = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $matches)) {
                $data_utilizzo_parsed = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            // Parse datetime per presa (formato: 05/08/2025 18:15)
            $ora_presa_datetime = null;
            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}:\d{2})/', $presa_data_ora, $matches)) {
                $ora_presa_datetime = $matches[3] . '-' . $matches[2] . '-' . $matches[1] . ' ' . $matches[4] . ':00';
            }

            // Parse datetime per riconsegna
            $ora_riconsegna_datetime = null;
            if (!empty($riconsegna_data_ora) && preg_match('/(\d{2})\/(\d{2})\/(\d{4}) (\d{2}:\d{2})/', $riconsegna_data_ora, $matches)) {
                $ora_riconsegna_datetime = $matches[3] . '-' . $matches[2] . '-' . $matches[1] . ' ' . $matches[4] . ':00';
            }

            if (!$data_utilizzo_parsed || !$ora_presa_datetime) continue;

            // Check for duplicates
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM utilizzi_auto 
                WHERE data_utilizzo = ? AND ora_presa = ? AND destinazione = ?
            ");
            $stmt->execute([$data_utilizzo_parsed, $ora_presa_datetime, $cliente]);
            
            if ($stmt->fetchColumn() > 0) {
                $existingRecords++;
                continue;
            }

            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO utilizzi_auto (auto_id, tecnico_id, data_utilizzo, ora_presa, 
                                         ora_riconsegna, km_iniziali, km_finali, km_percorsi,
                                         destinazione, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                1, // Default auto_id
                1, // Default tecnico_id  
                $data_utilizzo_parsed, 
                $ora_presa_datetime, 
                $ora_riconsegna_datetime,
                0, // km_iniziali
                0, // km_finali
                0, // km_percorsi
                $cliente,
                "Auto: {$auto} - Tecnico: {$dipendente}"
            ]);
            $newRecords++;
            
            $this->log('info', "Importato utilizzo auto: {$dipendente} - {$auto} - {$cliente}");
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Import calendario ICS with duplicate detection - FORMATO ICS CORRETTO
     */
    private function importCalendarioIncremental($csvData, $headers) {
        $newRecords = 0;
        $existingRecords = 0;

        // Leggi il file come testo per parsare ICS
        $filePath = $this->uploadDir . 'calendario.csv';
        if (!file_exists($filePath)) {
            $this->log('error', 'File calendario.csv non trovato');
            return ['new' => 0, 'existing' => 0];
        }

        $icsContent = file_get_contents($filePath);
        $events = $this->parseICSContent($icsContent);

        foreach ($events as $event) {
            if (empty($event['summary']) || empty($event['dtstart'])) continue;

            // Check for duplicates
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM eventi_calendario 
                WHERE titolo = ? AND data_inizio = ?
            ");
            $stmt->execute([$event['summary'], $event['dtstart']]);
            
            if ($stmt->fetchColumn() > 0) {
                $existingRecords++;
                continue;
            }

            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO eventi_calendario (tecnico_id, titolo, descrizione, data_inizio, 
                                              data_fine, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                1, // Default tecnico_id
                $event['summary'], 
                $event['description'] ?: 'Evento importato da calendario',
                $event['dtstart'], 
                $event['dtend']
            ]);
            $newRecords++;
            
            $this->log('info', "Importato evento calendario: {$event['summary']}");
        }

        return ['new' => $newRecords, 'existing' => $existingRecords];
    }

    /**
     * Parse ICS content per estrarre eventi
     */
    private function parseICSContent($icsContent) {
        $events = [];
        $lines = explode("\n", $icsContent);
        $currentEvent = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
            } elseif ($line === 'END:VEVENT' && $currentEvent !== null) {
                // Solo aggiunge eventi con almeno dtstart
                if (!empty($currentEvent) && isset($currentEvent['dtstart'])) {
                    // Se manca summary, usa una descrizione di default
                    if (empty($currentEvent['summary'])) {
                        $currentEvent['summary'] = 'Evento calendario - ' . ($currentEvent['dtstart'] ?? date('Y-m-d'));
                    }
                    $events[] = $currentEvent;
                }
                $currentEvent = null;
            } elseif ($currentEvent !== null) {
                if (strpos($line, 'SUMMARY:') === 0) {
                    $currentEvent['summary'] = trim(substr($line, 8));
                } elseif (strpos($line, 'DESCRIPTION:') === 0) {
                    $currentEvent['description'] = trim(substr($line, 12));
                } elseif (strpos($line, 'DTSTART') === 0) {
                    // Gestisce sia DTSTART: che DTSTART;TZID=...
                    $colonPos = strpos($line, ':');
                    if ($colonPos !== false) {
                        $dt = substr($line, $colonPos + 1);
                        $currentEvent['dtstart'] = $this->parseICSDateTime($dt);
                    }
                } elseif (strpos($line, 'DTEND') === 0) {
                    // Gestisce sia DTEND: che DTEND;TZID=...
                    $colonPos = strpos($line, ':');
                    if ($colonPos !== false) {
                        $dt = substr($line, $colonPos + 1);
                        $currentEvent['dtend'] = $this->parseICSDateTime($dt);
                    }
                } elseif (strpos($line, 'LOCATION:') === 0) {
                    $currentEvent['location'] = trim(substr($line, 9));
                }
            }
        }
        
        return $events;
    }

    /**
     * Parse ICS datetime format
     */
    private function parseICSDateTime($icsDateTime) {
        // Formato ICS: 20250801T073000Z o 20250801T073000
        if (preg_match('/(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})/', $icsDateTime, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . 
                   $matches[4] . ':' . $matches[5] . ':' . $matches[6];
        }
        return null;
    }

    /**
     * Get import statistics for today
     */
    public function getTodayImportStats() {
        $stmt = $this->pdo->prepare("
            SELECT file_name, records_total, records_new, records_existing, 
                   import_timestamp, status
            FROM daily_imports 
            WHERE import_date = CURDATE()
            ORDER BY import_timestamp DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>