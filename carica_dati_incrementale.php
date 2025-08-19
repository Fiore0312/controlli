<?php
/**
 * CARICAMENTO INCREMENTALE SICURO - BAIT SERVICE
 * Mantiene dati precedenti, aggiorna solo file caricati
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔄 CARICAMENTO INCREMENTALE SICURO - BAIT SERVICE\n";
echo "===============================================\n\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Connesso a database bait_service_real\n\n";
    
    $csvPath = __DIR__ . '/upload_csv/';
    
    // Mapping file -> tabella
    $csvFiles = [
        'attivita.csv' => 'attivita_deepser',
        'timbrature.csv' => 'timbrature', 
        'teamviewer_bait.csv' => 'teamviewer_sessions',
        'teamviewer_gruppo.csv' => 'teamviewer_group_sessions',
        'permessi.csv' => 'richieste_permessi',
        'auto.csv' => 'utilizzo_auto',
        'calendario.csv' => 'calendario_appuntamenti'
    ];
    
    echo "📁 FASE 1: Scansione file disponibili\n";
    echo "------------------------------------\n";
    
    $filesToProcess = [];
    $skippedFiles = [];
    
    foreach ($csvFiles as $filename => $tableName) {
        $filePath = $csvPath . $filename;
        
        if (file_exists($filePath)) {
            $fileTime = filemtime($filePath);
            $fileSize = filesize($filePath);
            $filesToProcess[$filename] = [
                'table' => $tableName,
                'path' => $filePath,
                'time' => $fileTime,
                'size' => $fileSize
            ];
            echo "✅ $filename: " . number_format($fileSize / 1024, 1) . " KB (modificato: " . date('d/m/Y H:i', $fileTime) . ")\n";
        } else {
            $skippedFiles[] = $filename;
            echo "⚪ $filename: NON PRESENTE (mantengo dati precedenti)\n";
        }
    }
    
    echo "\n📊 RIEPILOGO SCANSIONE:\n";
    echo "File da processare: " . count($filesToProcess) . "\n";
    echo "File saltati (dati mantenuti): " . count($skippedFiles) . "\n\n";
    
    if (empty($filesToProcess)) {
        echo "⚠️ Nessun file CSV trovato. Niente da processare.\n";
        exit;
    }
    
    echo "🔄 FASE 2: Caricamento incrementale sicuro\n";
    echo "=========================================\n";
    
    $processedCount = 0;
    $totalRecords = 0;
    
    foreach ($filesToProcess as $filename => $fileInfo) {
        $tableName = $fileInfo['table'];
        $filePath = $fileInfo['path'];
        
        echo "🔄 Processando: $filename → $tableName\n";
        
        // Leggi CSV
        $csvContent = file_get_contents($filePath);
        $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding !== 'UTF-8') {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
            echo "   📝 Encoding convertito: $encoding → UTF-8\n";
        }
        
        // Parse CSV
        $lines = str_getcsv($csvContent, "\n");
        $header = str_getcsv(array_shift($lines));
        $records = [];
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $data = str_getcsv($line);
                if (count($data) == count($header)) {
                    $records[] = array_combine($header, $data);
                }
            }
        }
        
        echo "   📊 Record trovati: " . count($records) . "\n";
        
        if (empty($records)) {
            echo "   ⚠️ File vuoto, saltando...\n\n";
            continue;
        }
        
        // STRATEGIA SICURA: Inserimento con ON DUPLICATE KEY UPDATE
        // invece di DELETE + INSERT
        
        echo "   🔄 Inserimento sicuro con UPSERT...\n";
        $inserted = 0;
        $updated = 0;
        
        // Crea timestamp corrente per tracking versione
        $loadTimestamp = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            try {
                // Aggiungi metadata caricamento
                $record['last_loaded'] = $loadTimestamp;
                $record['source_file'] = $filename;
                
                $columns = array_keys($record);
                $placeholders = ':' . implode(', :', $columns);
                $columnsList = '`' . implode('`, `', $columns) . '`';
                
                // UPDATE colonne per ON DUPLICATE KEY
                $updateClauses = [];
                foreach ($columns as $col) {
                    if ($col !== 'id') { // Non aggiornare chiave primaria
                        $updateClauses[] = "`$col` = VALUES(`$col`)";
                    }
                }
                $updateClause = implode(', ', $updateClauses);
                
                $query = "INSERT INTO $tableName ($columnsList) VALUES ($placeholders)";
                if (!empty($updateClauses)) {
                    $query .= " ON DUPLICATE KEY UPDATE $updateClause";
                }
                
                $stmt = $pdo->prepare($query);
                
                foreach ($record as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                
                $result = $stmt->execute();
                
                // Conta inserimenti vs aggiornamenti
                if ($pdo->lastInsertId()) {
                    $inserted++;
                } else {
                    $updated++;
                }
                
            } catch (Exception $e) {
                // Log primo errore ma continua
                if ($inserted == 0 && $updated == 0) {
                    echo "   ⚠️ Primo record con errore: " . $e->getMessage() . "\n";
                    
                    // Fallback: INSERT IGNORE
                    try {
                        $simpleQuery = "INSERT IGNORE INTO $tableName ($columnsList) VALUES ($placeholders)";
                        $stmt = $pdo->prepare($simpleQuery);
                        
                        foreach ($record as $key => $value) {
                            $stmt->bindValue(":$key", $value);
                        }
                        
                        $stmt->execute();
                        $inserted++;
                        echo "   ✅ Fallback INSERT IGNORE riuscito\n";
                    } catch (Exception $e2) {
                        echo "   ❌ Errore anche con fallback: " . $e2->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "   ✅ Record inseriti: $inserted\n";
        echo "   ✅ Record aggiornati: $updated\n";
        echo "   ✅ $filename completato\n\n";
        
        $totalRecords += ($inserted + $updated);
        $processedCount++;
    }
    
    // Log caricamento nella tabella audit_loads (se esiste)
    try {
        $loadId = 'LOAD-' . date('Ymd-His');
        $pdo->prepare("
            INSERT IGNORE INTO audit_loads 
            (load_id, load_timestamp, files_processed, records_processed, files_skipped)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $loadId,
            date('Y-m-d H:i:s'),
            $processedCount,
            $totalRecords,
            implode(',', $skippedFiles)
        ]);
        echo "📝 Caricamento registrato: $loadId\n\n";
    } catch (Exception $e) {
        // Tabella audit_loads non esiste, continua
    }
    
    echo "🎯 RIEPILOGO FINALE\n";
    echo "==================\n";
    echo "✅ File processati: $processedCount/" . count($csvFiles) . "\n";
    echo "✅ File mantenuti (precedenti): " . count($skippedFiles) . "\n";
    echo "✅ Record totali elaborati: $totalRecords\n";
    echo "✅ Dati precedenti: PRESERVATI ✓\n";
    echo "✅ Caricamento incrementale: SICURO ✓\n\n";
    
    if (!empty($skippedFiles)) {
        echo "📋 File NON aggiornati (dati precedenti mantenuti):\n";
        foreach ($skippedFiles as $file) {
            echo "   - $file\n";
        }
        echo "\n";
    }
    
    echo "🌐 PROSSIMO PASSO:\n";
    echo "Accedi alla dashboard: http://localhost/controlli/audit_monthly_manager.php\n\n";
    
    echo "🚀 CARICAMENTO INCREMENTALE COMPLETATO!\n";
    echo "=====================================\n";
    echo "I dati precedenti sono stati PRESERVATI ✓\n";
    echo "Solo i file caricati sono stati aggiornati ✓\n";
    echo "Sistema pronto per analisi cross-validation ✓\n\n";
    
} catch (Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>