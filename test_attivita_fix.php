<?php
/**
 * TEST FIX CARATTERE ATTIVITÀ
 * Verifica che il carattere corrotto ATTIVIT└ sia stato corretto
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

    echo "<h1>✅ Test Fix Carattere ATTIVITÀ</h1>";

    // Check for any remaining corrupted characters
    $stmt = $pdo->query("
        SELECT id, descrizione_completa 
        FROM alert_dettagliati 
        WHERE descrizione_completa LIKE '%ATTIVIT%'
        ORDER BY id
        LIMIT 5
    ");
    
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Sample Alert Descriptions After Fix</h2>";
    
    foreach ($alerts as $alert) {
        // Check if ATTIVITÀ is properly displayed
        $hasCorrectChar = strpos($alert['descrizione_completa'], 'ATTIVITÀ') !== false;
        $hasCorruptedChar = strpos($alert['descrizione_completa'], 'ATTIVIT└') !== false;
        
        echo "<div style='background:" . ($hasCorrectChar && !$hasCorruptedChar ? '#d4edda' : '#f8d7da') . ";padding:15px;margin:10px;border-left:5px solid " . ($hasCorrectChar && !$hasCorruptedChar ? '#28a745' : '#dc3545') . ";'>";
        echo "<h4>Alert #{$alert['id']} " . ($hasCorrectChar && !$hasCorruptedChar ? '✅' : '❌') . "</h4>";
        
        // Show just the part with ATTIVITÀ
        if (preg_match('/ATTIVIT[ÀÓ└�][^.]*/', $alert['descrizione_completa'], $matches)) {
            echo "<p><strong>Found:</strong> " . htmlspecialchars($matches[0]) . "</p>";
        }
        
        if ($hasCorruptedChar) {
            echo "<p style='color:red;'>⚠️ Still has corrupted character ATTIVIT└</p>";
        }
        if ($hasCorrectChar) {
            echo "<p style='color:green;'>✅ Correctly shows ATTIVITÀ</p>";
        }
        echo "</div>";
    }
    
    // Check if there are any remaining corrupted characters
    $corruptedCheck = $pdo->query("SELECT COUNT(*) as count FROM alert_dettagliati WHERE descrizione_completa LIKE '%ATTIVIT└%'")->fetch();
    
    echo "<div style='background:#e9ecef;padding:20px;margin:20px 0;text-align:center;'>";
    echo "<h3>🔍 Corruption Check Results</h3>";
    
    if ($corruptedCheck['count'] == 0) {
        echo "<p style='color:green;font-size:18px;'><strong>✅ No corrupted ATTIVIT└ characters found!</strong></p>";
        echo "<p>All characters have been successfully fixed to ATTIVITÀ</p>";
    } else {
        echo "<p style='color:red;font-size:18px;'><strong>❌ Found {$corruptedCheck['count']} records with corrupted characters</strong></p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;border-left:5px solid #dc3545;'>";
    echo "<strong>❌ Errore:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><a href='laravel_bait/public/index_standalone.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Dashboard Updated</a>";
?>