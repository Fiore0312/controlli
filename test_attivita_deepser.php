<?php
/**
 * TEST PAGINA ATTIVITÀ DEEPSER
 * Verifica che la pagina carichi correttamente i dati CSV
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Test Pagina Attività Deepser</h1>";

// Test 1: File CSV exists
$csvPath = __DIR__ . '/data/input/attivita.csv';
$csvExists = file_exists($csvPath);

echo "<div style='background:" . ($csvExists ? '#d4edda' : '#f8d7da') . ";padding:15px;margin:10px;border-left:5px solid " . ($csvExists ? '#28a745' : '#dc3545') . ";'>";
echo "<h4>Test 1: File CSV " . ($csvExists ? '✅' : '❌') . "</h4>";
echo "<p><strong>Path:</strong> $csvPath</p>";
echo "<p><strong>Esiste:</strong> " . ($csvExists ? 'Sì' : 'No') . "</p>";
if ($csvExists) {
    $fileSize = filesize($csvPath);
    echo "<p><strong>Dimensione:</strong> " . number_format($fileSize) . " bytes</p>";
}
echo "</div>";

if ($csvExists) {
    // Test 2: CSV parsing
    try {
        $csvContent = file_get_contents($csvPath);
        $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
        }
        
        $lines = str_getcsv($csvContent, "\n");
        $headers = str_getcsv(array_shift($lines), ';');
        
        // Clean BOM if present
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        
        $dataRows = 0;
        foreach ($lines as $line) {
            if (trim($line)) $dataRows++;
        }
        
        echo "<div style='background:#d4edda;padding:15px;margin:10px;border-left:5px solid #28a745;'>";
        echo "<h4>Test 2: Parsing CSV ✅</h4>";
        echo "<p><strong>Encoding rilevato:</strong> $encoding</p>";
        echo "<p><strong>Colonne:</strong> " . count($headers) . "</p>";
        echo "<p><strong>Righe dati:</strong> $dataRows</p>";
        echo "<p><strong>Headers trovati:</strong></p>";
        echo "<ul>";
        foreach ($headers as $i => $header) {
            echo "<li>" . ($i+1) . ". " . htmlspecialchars($header) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        // Test 3: Sample data
        if ($dataRows > 0) {
            $sampleRow = str_getcsv($lines[0], ';');
            echo "<div style='background:#e7f3ff;padding:15px;margin:10px;border-left:5px solid #0066cc;'>";
            echo "<h4>Test 3: Esempio Prima Riga ✅</h4>";
            echo "<p><strong>Dati prima riga:</strong></p>";
            echo "<ul>";
            for ($i = 0; $i < min(count($headers), count($sampleRow)); $i++) {
                echo "<li><strong>" . htmlspecialchars($headers[$i]) . ":</strong> " . htmlspecialchars($sampleRow[$i]) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        // Test 4: Specific fields we need
        $requiredFields = ['Id Ticket', 'Iniziata il', 'Conclusa il', 'Azienda', 'Tipologia Attività', 'Creato da'];
        $foundFields = [];
        
        foreach ($requiredFields as $field) {
            if (in_array($field, $headers)) {
                $foundFields[] = $field;
            }
        }
        
        $allFound = count($foundFields) === count($requiredFields);
        
        echo "<div style='background:" . ($allFound ? '#d4edda' : '#fff3cd') . ";padding:15px;margin:10px;border-left:5px solid " . ($allFound ? '#28a745' : '#ffc107') . ";'>";
        echo "<h4>Test 4: Campi Richiesti " . ($allFound ? '✅' : '⚠️') . "</h4>";
        echo "<p><strong>Campi trovati:</strong> " . count($foundFields) . "/" . count($requiredFields) . "</p>";
        echo "<p><strong>Trovati:</strong> " . implode(', ', $foundFields) . "</p>";
        
        if (!$allFound) {
            $missing = array_diff($requiredFields, $foundFields);
            echo "<p style='color:#856404;'><strong>Mancanti:</strong> " . implode(', ', $missing) . "</p>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da;padding:15px;margin:10px;border-left:5px solid #dc3545;'>";
        echo "<h4>Test 2: Parsing CSV ❌</h4>";
        echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Test 5: Page accessibility
echo "<div style='background:#f8f9fa;padding:15px;margin:10px;border-left:5px solid #6c757d;'>";
echo "<h4>Test 5: Accesso Pagina</h4>";
echo "<p>La pagina dovrebbe essere accessibile a:</p>";
echo "<ul>";
echo "<li><a href='attivita_deepser.php' target='_blank'>http://localhost/controlli/attivita_deepser.php</a></li>";
echo "<li>Link dalla dashboard nella sezione 'Azioni Rapide'</li>";
echo "<li>Link nel menu di navigazione</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#e9ecef;padding:20px;margin:20px 0;text-align:center;'>";
echo "<h3>🎯 Risultati Test</h3>";
if ($csvExists && isset($allFound) && $allFound) {
    echo "<p style='color:green;font-size:18px;'><strong>✅ Tutti i test superati!</strong></p>";
    echo "<p>La pagina Attività Deepser dovrebbe funzionare correttamente.</p>";
    echo "<a href='attivita_deepser.php' class='btn btn-success' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🚀 Apri Attività Deepser</a>";
} else {
    echo "<p style='color:orange;font-size:18px;'><strong>⚠️ Alcuni problemi rilevati</strong></p>";
    echo "<p>Controlla i test sopra per i dettagli.</p>";
}
echo "</div>";

echo "<br><a href='laravel_bait/public/index_standalone.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>↩️ Dashboard</a>";
?>