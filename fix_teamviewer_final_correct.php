<?php
// FIX FINALE TEAMVIEWER - Mappatura Precisa Come Funzionava Ieri
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Fix FINALE TeamViewer - Mappatura Precisa</h1>";

try {
    // Connessione database
    $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Database connesso</p>";
    
    // Svuota dati esistenti
    $pdo->exec("DELETE FROM teamviewer_sessions WHERE session_id NOT LIKE 'TV%'");
    echo "<p>🗑️ Dati import precedenti rimossi</p>";
    
    $totalImported = 0;
    
    // MAPPING TECNICI - Crea mappa nome → ID
    $stmt = $pdo->query("SELECT id, nome_completo FROM tecnici");
    $tecniciMap = [];
    $tecniciRows = $stmt->fetchAll();
    foreach ($tecniciRows as $tecnico) {
        // Estrai nome e cognome per matching flessibile
        $parts = explode(' ', trim($tecnico['nome_completo']));
        $nome = $parts[0] ?? '';
        $cognome = end($parts);
        
        // Multiple mappings per matching flessibile
        $tecniciMap[$tecnico['nome_completo']] = $tecnico['id'];
        $tecniciMap[trim($nome . ' ' . $cognome)] = $tecnico['id'];
        
        // Aggiungi mapping specifici per i tecnici che conosci
        if (stripos($tecnico['nome_completo'], 'Matteo Signo') !== false) {
            $tecniciMap['Matteo Signo'] = $tecnico['id'];
        }
        if (stripos($tecnico['nome_completo'], 'Gabriele De Palma') !== false) {
            $tecniciMap['Gabriele De Palma'] = $tecnico['id'];
        }
        if (stripos($tecnico['nome_completo'], 'Alex Ferrario') !== false) {
            $tecniciMap['Alex Ferrario'] = $tecnico['id'];
        }
        if (stripos($tecnico['nome_completo'], 'Davide Cestone') !== false) {
            $tecniciMap['Davide Cestone'] = $tecnico['id'];
        }
        if (stripos($tecnico['nome_completo'], 'Marco Birocchi') !== false) {
            $tecniciMap['Marco Birocchi'] = $tecnico['id'];
        }
        if (stripos($tecnico['nome_completo'], 'Nicole Caiola') !== false) {
            $tecniciMap['Nicole Caiola'] = $tecnico['id'];
        }
    }
    
    echo "<div style='background: #f0f8ff; padding: 10px; margin: 10px 0;'>";
    echo "<strong>👥 Tecnici mappati:</strong><br>";
    foreach ($tecniciRows as $tecnico) {
        echo "ID {$tecnico['id']}: {$tecnico['nome_completo']}<br>";
    }
    echo "</div>";
    
    // 1. IMPORT teamviewer_gruppo.csv
    // COLONNE: Utente (tecnico), Computer (cliente), ID, Tipo di sessione, Gruppo, Inizio, Fine, Durata, ...
    $csvFile1 = __DIR__ . '/upload_csv/teamviewer_gruppo.csv';
    if (file_exists($csvFile1)) {
        echo "<h2>📁 Processing teamviewer_gruppo.csv</h2>";
        echo "<p><strong>Mappatura:</strong> Utente→Tecnico, Computer→Cliente</p>";
        
        $handle = fopen($csvFile1, 'r');
        $header = fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 8) {
                try {
                    // MAPPATURA CORRETTA per teamviewer_gruppo.csv:
                    $utente = trim($row[0]);      // Colonna "Utente" = TECNICO
                    $computer = trim($row[1]);    // Colonna "Computer" = CLIENTE
                    $session_id = trim($row[2]);  // Colonna "ID"
                    $tipo_sessione = trim($row[3]); // Colonna "Tipo di sessione"
                    $inizio_str = trim($row[5]);  // Colonna "Inizio"
                    $fine_str = trim($row[6]);    // Colonna "Fine" 
                    $durata_minuti = intval(trim($row[7])); // Colonna "Durata" - SONO GIÀ MINUTI
                    
                    // Trova tecnico_id
                    $tecnico_id = $tecniciMap[$utente] ?? 1;
                    
                    // Parse date ISO format "2025-08-25 14:48"
                    $data_inizio = $inizio_str . ':00';
                    $data_fine = $fine_str . ':00';
                    
                    if ($data_inizio && $data_fine) {
                        $data_sessione = date('Y-m-d', strtotime($data_inizio));
                        $ora_inizio = date('H:i:s', strtotime($data_inizio));
                        $ora_fine = date('H:i:s', strtotime($data_fine));
                        
                        // Insert nel database
                        $stmt = $pdo->prepare("
                            INSERT INTO teamviewer_sessions 
                            (session_id, tecnico_id, cliente_id, data_sessione, ora_inizio, ora_fine, durata_minuti, tipo_sessione, computer_remoto, descrizione, created_at)
                            VALUES (?, ?, 1, ?, ?, ?, ?, 'server', ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $session_id,
                            $tecnico_id,
                            $data_sessione,
                            $ora_inizio,
                            $ora_fine,
                            $durata_minuti, // CORRETTO: sono già minuti
                            $computer,
                            "Gruppo: $utente"
                        ]);
                        
                        $totalImported++;
                        
                        if ($totalImported <= 3) {
                            echo "<div style='background: #f0f8ff; padding: 8px; margin: 5px 0; border-left: 3px solid #2563eb;'>";
                            echo "<strong>Record $totalImported:</strong> $utente → $computer ($durata_minuti min) [$data_sessione $ora_inizio-$ora_fine]";
                            echo "</div>";
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Errore riga: " . $e->getMessage() . "</p>";
                }
            }
        }
        fclose($handle);
        echo "<p>✅ teamviewer_gruppo.csv processato</p>";
    }
    
    // 2. IMPORT teamviewer_bait.csv  
    // COLONNE: Assegnatario (tecnico), Utente, Nome (cliente), E-mail, Codice, Tipo di sessione, Gruppo, Inizio, Fine, Durata, ...
    $csvFile2 = __DIR__ . '/upload_csv/teamviewer_bait.csv';
    if (file_exists($csvFile2)) {
        echo "<h2>📁 Processing teamviewer_bait.csv</h2>";
        echo "<p><strong>Mappatura:</strong> Assegnatario→Tecnico, Nome→Cliente</p>";
        
        $handle = fopen($csvFile2, 'r');
        $header = fgetcsv($handle); // Skip header
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 10) {
                try {
                    // MAPPATURA CORRETTA per teamviewer_bait.csv:
                    $assegnatario = trim($row[0]);  // Colonna "Assegnatario" = TECNICO
                    $nome_cliente = trim($row[2]);  // Colonna "Nome" = CLIENTE
                    $codice = trim($row[4]);        // Colonna "Codice"
                    $inizio_str = trim($row[7]);    // Colonna "Inizio"
                    $fine_str = trim($row[8]);      // Colonna "Fine"
                    $durata_str = trim($row[9]);    // Colonna "Durata": "12m " o "1h 23m"
                    
                    // Trova tecnico_id
                    $tecnico_id = $tecniciMap[$assegnatario] ?? 1;
                    
                    // Converte durata CORRETTAMENTE: "12m " → 12, "1h 23m" → 83
                    $durata_minuti = 0;
                    if (preg_match('/(\d+)h/', $durata_str, $matches)) {
                        $durata_minuti += intval($matches[1]) * 60;
                    }
                    if (preg_match('/(\d+)m/', $durata_str, $matches)) {
                        $durata_minuti += intval($matches[1]);
                    }
                    
                    // Parse date ISO format "2025-08-01 10:03"
                    $data_inizio = $inizio_str . ':00';
                    $data_fine = $fine_str . ':00';
                    
                    if ($data_inizio && $data_fine) {
                        $data_sessione = date('Y-m-d', strtotime($data_inizio));
                        $ora_inizio = date('H:i:s', strtotime($data_inizio));
                        $ora_fine = date('H:i:s', strtotime($data_fine));
                        
                        // Insert nel database
                        $stmt = $pdo->prepare("
                            INSERT INTO teamviewer_sessions 
                            (session_id, tecnico_id, cliente_id, data_sessione, ora_inizio, ora_fine, durata_minuti, tipo_sessione, computer_remoto, descrizione, created_at)
                            VALUES (?, ?, 1, ?, ?, ?, ?, 'user', ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $codice,
                            $tecnico_id,
                            $data_sessione,
                            $ora_inizio,
                            $ora_fine,
                            $durata_minuti,
                            $nome_cliente,
                            "BAIT: $assegnatario"
                        ]);
                        
                        $totalImported++;
                        
                        if (($totalImported - 17) <= 3 && ($totalImported - 17) > 0) {
                            echo "<div style='background: #f0f8ff; padding: 8px; margin: 5px 0; border-left: 3px solid #2563eb;'>";
                            echo "<strong>Record $totalImported:</strong> $assegnatario → $nome_cliente ($durata_minuti min) [$data_sessione $ora_inizio-$ora_fine]";
                            echo "</div>";
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Errore riga: " . $e->getMessage() . "</p>";
                }
            }
        }
        fclose($handle);
        echo "<p>✅ teamviewer_bait.csv processato</p>";
    }
    
    // Verifica finale
    $stmt = $pdo->query("SELECT COUNT(*) FROM teamviewer_sessions");
    $finalCount = $stmt->fetchColumn();
    
    echo "<div style='background: #f0f9ff; padding: 20px; margin: 20px 0; border: 2px solid #2563eb; border-radius: 8px;'>";
    echo "<h2>🎯 RISULTATO IMPORT FINALE</h2>";
    echo "<p><strong>Record importati: $totalImported</strong></p>";
    echo "<p><strong>Total record database: $finalCount</strong></p>";
    echo "<p><strong>Status: " . ($finalCount > 4 ? "✅ SUCCESS - Mappatura corretta!" : "❌ FAILED") . "</strong></p>";
    echo "</div>";
    
    // Sample con mappatura corretta
    echo "<h2>📊 Sample con Mappatura Corretta</h2>";
    $stmt = $pdo->query("
        SELECT ts.session_id, t.nome_completo, ts.computer_remoto, ts.data_sessione, ts.ora_inizio, ts.ora_fine, ts.durata_minuti, ts.descrizione 
        FROM teamviewer_sessions ts 
        LEFT JOIN tecnici t ON ts.tecnico_id = t.id 
        ORDER BY ts.created_at DESC LIMIT 8
    ");
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $sample) {
        echo "<div style='background: #f8f9fa; padding: 8px; margin: 5px 0; border-radius: 5px;'>";
        echo "<strong>{$sample['nome_completo']}</strong> → {$sample['computer_remoto']} ({$sample['durata_minuti']}min) - {$sample['data_sessione']} {$sample['ora_inizio']}-{$sample['ora_fine']} - {$sample['descrizione']}";
        echo "</div>";
    }
    
    // Verifica durata totale corretta
    $stmt = $pdo->query("SELECT SUM(durata_minuti) as total_minuti FROM teamviewer_sessions WHERE session_id NOT LIKE 'TV%'");
    $totalMinuti = $stmt->fetchColumn();
    $totalOre = round($totalMinuti / 60, 1);
    
    echo "<h2>⏱️ Verifica Durate Corrette</h2>";
    echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 5px;'>";
    echo "<strong>Durata totale:</strong> {$totalMinuti} minuti = {$totalOre} ore<br>";
    echo "<strong>Durata media:</strong> " . round($totalMinuti / $finalCount, 1) . " minuti per sessione";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #fff5f5; color: #dc3545; padding: 15px; border-radius: 5px;'>";
    echo "❌ ERRORE: " . $e->getMessage();
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='sessioni_teamviewer.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;'>🎯 Verifica TeamViewer Page FINALE</a></p>";
?>