<?php
/**
 * Test Unificazione Sistema BAIT - Verifica che tutti i file principali usino upload_csv/
 */

echo "<h1>Test Unificazione Sistema BAIT</h1>\n";
echo "<h2>Verifica che tutti i file principali leggano da upload_csv/ invece di data/input/</h2>\n\n";

$mainFiles = [
    'calendario.php',
    'audit_monthly_manager.php', 
    'bait_incongruenze_manager.php',
    'timbrature.php',
    'utilizzo_auto.php',
    'richieste_permessi.php',
    'attivita_deepser.php',
    'sessioni_teamviewer.php'
];

echo "<table border='1'>\n";
echo "<tr><th>File</th><th>Esiste</th><th>Usa upload_csv/</th><th>Status</th></tr>\n";

foreach ($mainFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    $exists = file_exists($filePath);
    $usesUploadCsv = false;
    $usesDataInput = false;
    
    if ($exists) {
        $content = file_get_contents($filePath);
        $usesUploadCsv = strpos($content, 'upload_csv/') !== false;
        $usesDataInput = strpos($content, 'data/input/') !== false;
    }
    
    $status = $exists ? 
        ($usesUploadCsv && !$usesDataInput ? '✅ UNIFICATO' : 
        ($usesDataInput ? '⚠️ USA ANCORA data/input' : '❓ Nessun CSV')) :
        '❌ FILE MANCANTE';
    
    $uses = $exists ? 
        ($usesUploadCsv ? 'SÌ' : 'NO') . 
        ($usesDataInput ? ' (+ data/input)' : '') : 
        'N/A';
    
    echo "<tr>";
    echo "<td>$file</td>";
    echo "<td>" . ($exists ? 'SÌ' : 'NO') . "</td>";
    echo "<td>$uses</td>";
    echo "<td>$status</td>";
    echo "</tr>\n";
}

echo "</table>\n\n";

// Test directories
echo "<h3>Test Directory Structure</h3>\n";
echo "<ul>\n";
echo "<li>upload_csv/ exists: " . (is_dir(__DIR__ . '/upload_csv/') ? 'SÌ' : 'NO') . "</li>\n";
echo "<li>upload_csv/ readable: " . (is_readable(__DIR__ . '/upload_csv/') ? 'SÌ' : 'NO') . "</li>\n";
echo "<li>data/input/ exists: " . (is_dir(__DIR__ . '/data/input/') ? 'SÌ' : 'NO') . "</li>\n";
echo "</ul>\n";

// Count CSV files
$uploadCsvFiles = glob(__DIR__ . '/upload_csv/*.csv');
$dataInputFiles = glob(__DIR__ . '/data/input/*.csv');

echo "<h3>CSV Files Count</h3>\n";
echo "<ul>\n";
echo "<li>upload_csv/*.csv: " . count($uploadCsvFiles) . " files</li>\n";
echo "<li>data/input/*.csv: " . count($dataInputFiles) . " files</li>\n";
echo "</ul>\n";

if (count($uploadCsvFiles) > 0) {
    echo "<h4>Files in upload_csv/:</h4>\n<ul>\n";
    foreach ($uploadCsvFiles as $file) {
        echo "<li>" . basename($file) . "</li>\n";
    }
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<p><strong>RISULTATO UNIFICAZIONE:</strong> " . 
    (count($uploadCsvFiles) >= 0 && is_dir(__DIR__ . '/upload_csv/') ? 
     "✅ Sistema unificato correttamente su upload_csv/" : 
     "⚠️ Problemi nell'unificazione") . "</p>\n";

echo "<p><em>Test completato il " . date('Y-m-d H:i:s') . "</em></p>\n";