<?php
/**
 * Quick Fix Test - Verifica rapida funzionamento
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "BAIT Service - Quick Fix Test\n";
echo "=============================\n\n";

// Test 1: File esistenti
$files = [
    'teamviewer_bait.csv',
    'teamviewer_gruppo.csv', 
    'calendario.csv',
    'timbrature.csv'
];

$inputDir = __DIR__ . '/data/input/';
echo "Directory Input: $inputDir\n";
echo "Directory esiste: " . (is_dir($inputDir) ? "SÌ" : "NO") . "\n\n";

echo "TEST FILE:\n";
foreach ($files as $file) {
    $path = $inputDir . $file;
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;
    echo "- $file: " . ($exists ? "TROVATO ($size bytes)" : "MANCANTE") . "\n";
}

echo "\n";

// Test 2: Funzioni include
echo "TEST FUNZIONI:\n";
$testFunctions = [
    'readCSVFile',
    'createBackupWithRetry', 
    'isFileInUse',
    'parseTeamViewerRow'
];

foreach ($testFunctions as $func) {
    echo "- $func: " . (function_exists($func) ? "DISPONIBILE" : "MANCANTE") . "\n";
}

echo "\n";

// Test 3: Database connection
echo "TEST DATABASE:\n";
try {
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=bait_service_real;charset=utf8mb4", 
                   'root', '', [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                   ]);
    echo "- Connessione MySQL: FUNZIONANTE\n";
    
    // Test query semplice per ticket
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM audit_alerts LIMIT 1");
    $result = $stmt->fetch();
    echo "- Tabella audit_alerts: ACCESSIBILE ({$result['count']} record)\n";
    
} catch (Exception $e) {
    echo "- Connessione MySQL: ERRORE - " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Timestamp
echo "TIMESTAMP:\n";
echo "- Data/ora corrente: " . date('Y-m-d H:i:s') . "\n";
echo "- Timezone: " . date_default_timezone_get() . "\n";
echo "- PHP Version: " . PHP_VERSION . "\n";
echo "- OS: " . PHP_OS_FAMILY . "\n";

echo "\nTest completato!\n";
?>