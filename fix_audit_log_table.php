<?php
/**
 * FIX TABELLA AUDIT_LOG - Correzione struttura per caricamento CSV
 */

echo "🔧 FIX TABELLA AUDIT_LOG - CORREZIONE STRUTTURA\n";
echo "===============================================\n\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Connesso al database bait_service_real\n\n";
    
    // Verifico struttura corrente
    echo "📊 FASE 1: Verifica struttura attuale\n";
    echo "------------------------------------\n";
    $result = $pdo->query("DESCRIBE audit_log");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colonne esistenti: " . implode(', ', $columns) . "\n\n";
    
    // Controlla se le colonne richieste esistono
    $requiredColumns = ['event_type', 'description', 'metadata'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missingColumns[] = $col;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "⚠️ Colonne mancanti: " . implode(', ', $missingColumns) . "\n\n";
        
        echo "🔧 FASE 2: Aggiunta colonne mancanti\n";
        echo "-----------------------------------\n";
        
        // Aggiungi colonne mancanti
        if (in_array('event_type', $missingColumns)) {
            $pdo->exec("ALTER TABLE audit_log ADD COLUMN event_type VARCHAR(100) NOT NULL DEFAULT 'file_upload' AFTER id");
            echo "✅ Colonna 'event_type' aggiunta\n";
        }
        
        if (in_array('description', $missingColumns)) {
            $pdo->exec("ALTER TABLE audit_log ADD COLUMN description TEXT NULL AFTER event_type");
            echo "✅ Colonna 'description' aggiunta\n";
        }
        
        if (in_array('metadata', $missingColumns)) {
            $pdo->exec("ALTER TABLE audit_log ADD COLUMN metadata JSON NULL AFTER description");
            echo "✅ Colonna 'metadata' aggiunta\n";
        }
        
    } else {
        echo "✅ Tutte le colonne richieste sono già presenti\n";
    }
    
    echo "\n📊 FASE 3: Verifica struttura finale\n";
    echo "-----------------------------------\n";
    
    $result = $pdo->query("DESCRIBE audit_log");
    $finalColumns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalColumns as $col) {
        echo "- {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    echo "\n🧪 FASE 4: Test inserimento log\n";
    echo "------------------------------\n";
    
    // Test con un log di prova
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (event_type, description, metadata, created_at)
        VALUES ('file_upload', 'Test caricamento CSV', ?, NOW())
    ");
    
    $testMetadata = json_encode([
        'file_name' => 'test.csv',
        'file_size_bytes' => 1024,
        'upload_date' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->execute([$testMetadata]);
    
    echo "✅ Test inserimento completato con successo\n";
    echo "✅ Record ID: " . $pdo->lastInsertId() . "\n";
    
    // Pulisci record test
    $pdo->exec("DELETE FROM audit_log WHERE event_type = 'file_upload' AND description = 'Test caricamento CSV'");
    echo "✅ Record test rimosso\n";
    
    echo "\n🎯 CORREZIONE COMPLETATA!\n";
    echo "========================\n";
    echo "La tabella audit_log è ora compatibile con il sistema di caricamento CSV.\n";
    echo "Puoi riprovare il caricamento dei file!\n\n";
    
    echo "🌐 TORNA A:\n";
    echo "http://localhost/controlli/audit_monthly_manager.php\n\n";
    
} catch (Exception $e) {
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
    echo "📍 Riga: " . $e->getLine() . "\n";
}

?>