<?php
/**
 * SETUP DATABASE AUTO - Creazione tabelle per gestione utilizzo auto
 */

header('Content-Type: text/html; charset=utf-8');

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    echo "<h1>🚗 Setup Database Auto</h1>";

    // 1. Tabella auto aziendali
    $sql_auto = "
    CREATE TABLE IF NOT EXISTS auto_aziendali (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modello VARCHAR(100) NOT NULL,
        targa VARCHAR(20) UNIQUE,
        colore VARCHAR(50),
        anno_immatricolazione YEAR,
        km_attuali INT DEFAULT 0,
        stato ENUM('Disponibile', 'In_Uso', 'Manutenzione', 'Fuori_Servizio') DEFAULT 'Disponibile',
        costo_km DECIMAL(5,2) DEFAULT 0.35,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_auto);
    echo "<p>✅ Tabella auto_aziendali creata</p>";

    // 2. Tabella utilizzi auto
    $sql_utilizzi = "
    CREATE TABLE IF NOT EXISTS utilizzi_auto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tecnico_id INT,
        auto_id INT,
        data_utilizzo DATE NOT NULL,
        ora_presa DATETIME,
        ora_riconsegna DATETIME NULL,
        cliente VARCHAR(255),
        azienda_id INT NULL,
        km_partenza INT,
        km_arrivo INT,
        ore_utilizzo DECIMAL(4,2) GENERATED ALWAYS AS (
            CASE 
                WHEN ora_riconsegna IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, ora_presa, ora_riconsegna) / 60.0
                ELSE NULL 
            END
        ) STORED,
        costo_stimato DECIMAL(8,2) GENERATED ALWAYS AS (
            CASE 
                WHEN km_arrivo IS NOT NULL AND km_partenza IS NOT NULL
                THEN (km_arrivo - km_partenza) * 0.35
                ELSE NULL 
            END
        ) STORED,
        note TEXT,
        stato ENUM('In_Corso', 'Completato', 'Annullato') DEFAULT 'In_Corso',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE SET NULL,
        FOREIGN KEY (auto_id) REFERENCES auto_aziendali(id) ON DELETE SET NULL,
        FOREIGN KEY (azienda_id) REFERENCES aziende_reali(id) ON DELETE SET NULL,
        INDEX idx_data_utilizzo (data_utilizzo),
        INDEX idx_tecnico_data (tecnico_id, data_utilizzo),
        INDEX idx_auto_data (auto_id, data_utilizzo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql_utilizzi);
    echo "<p>✅ Tabella utilizzi_auto creata</p>";

    // 3. Inserimento auto demo
    $auto_demo = [
        ['Peugeot 208', 'AA123BB', 'Bianco', 2021],
        ['Ford Fiesta', 'CC456DD', 'Rosso', 2020],
        ['Fiat Panda', 'EE789FF', 'Blu', 2019],
        ['Renault Clio', 'GG012HH', 'Nero', 2022]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO auto_aziendali (modello, targa, colore, anno_immatricolazione) VALUES (?, ?, ?, ?)");
    $auto_inserite = 0;
    foreach ($auto_demo as $auto) {
        if ($stmt->execute($auto)) {
            $auto_inserite++;
        }
    }
    echo "<p>✅ Inserite $auto_inserite auto demo</p>";

    // 4. Import dati da CSV se esiste
    $csvPath = __DIR__ . '/data/input/auto.csv';
    if (file_exists($csvPath)) {
        $csvContent = file_get_contents($csvPath);
        $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
        }
        
        $lines = str_getcsv($csvContent, "\n");
        if (!empty($lines)) {
            array_shift($lines); // Remove header
            
            $imported = 0;
            foreach ($lines as $line) {
                if (trim($line) && !strpos($line, 'Somma di Ore')) {
                    $row = str_getcsv($line, ';');
                    if (count($row) >= 6 && !empty($row[0]) && !empty($row[1])) {
                        // Trova tecnico
                        $tecnicoStmt = $pdo->prepare("SELECT id FROM tecnici WHERE nome_completo LIKE ?");
                        $tecnicoStmt->execute(['%' . trim($row[0]) . '%']);
                        $tecnico = $tecnicoStmt->fetch();
                        
                        if ($tecnico) {
                            // Trova auto
                            $autoStmt = $pdo->prepare("SELECT id FROM auto_aziendali WHERE modello LIKE ?");
                            $autoStmt->execute(['%' . trim($row[2]) . '%']);
                            $auto = $autoStmt->fetch();
                            
                            if ($auto) {
                                try {
                                    $data_utilizzo = date('Y-m-d', strtotime(str_replace('/', '-', $row[1])));
                                    $ora_presa = !empty($row[3]) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $row[3]))) : null;
                                    $ora_riconsegna = !empty($row[4]) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $row[4]))) : null;
                                    
                                    $insertStmt = $pdo->prepare("
                                        INSERT IGNORE INTO utilizzi_auto 
                                        (tecnico_id, auto_id, data_utilizzo, ora_presa, ora_riconsegna, cliente, stato)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    
                                    $stato = $ora_riconsegna ? 'Completato' : 'In_Corso';
                                    
                                    if ($insertStmt->execute([
                                        $tecnico['id'], 
                                        $auto['id'], 
                                        $data_utilizzo, 
                                        $ora_presa, 
                                        $ora_riconsegna, 
                                        trim($row[5] ?? ''), 
                                        $stato
                                    ])) {
                                        $imported++;
                                    }
                                } catch (Exception $e) {
                                    // Skip invalid date formats
                                }
                            }
                        }
                    }
                }
            }
            echo "<p>✅ Importati $imported record da auto.csv</p>";
        }
    }

    // 5. Statistiche
    $stats = [
        'auto_totali' => $pdo->query("SELECT COUNT(*) FROM auto_aziendali")->fetchColumn(),
        'utilizzi_totali' => $pdo->query("SELECT COUNT(*) FROM utilizzi_auto")->fetchColumn(),
        'utilizzi_completati' => $pdo->query("SELECT COUNT(*) FROM utilizzi_auto WHERE stato = 'Completato'")->fetchColumn(),
        'utilizzi_in_corso' => $pdo->query("SELECT COUNT(*) FROM utilizzi_auto WHERE stato = 'In_Corso'")->fetchColumn()
    ];

    echo "<div style='background:#d4edda;padding:20px;margin:20px 0;text-align:center;border-left:5px solid #28a745;'>";
    echo "<h3>📊 Setup Completato!</h3>";
    echo "<p><strong>Auto totali:</strong> {$stats['auto_totali']}</p>";
    echo "<p><strong>Utilizzi totali:</strong> {$stats['utilizzi_totali']}</p>";
    echo "<p><strong>Utilizzi completati:</strong> {$stats['utilizzi_completati']}</p>";
    echo "<p><strong>Utilizzi in corso:</strong> {$stats['utilizzi_in_corso']}</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;border-left:5px solid #dc3545;'>";
    echo "<strong>❌ Errore setup:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><a href='utilizzo_auto.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚗 Vai a Utilizzo Auto</a>";
echo " <a href='laravel_bait/public/index_standalone.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📊 Dashboard</a>";
?>