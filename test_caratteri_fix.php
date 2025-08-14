<?php
// Test caratteri italiani dopo fix

header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

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
    
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Test Caratteri</title></head><body>";
    echo "<h2>🧪 Test Caratteri Italiani - Post Fix</h2>";
    
    echo "<h3>📊 Alert Messages</h3>";
    $stmt = $pdo->query("SELECT id, message FROM alerts ORDER BY id LIMIT 10");
    $alerts = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Message (dovrebbe mostrare caratteri corretti)</th></tr>";
    
    foreach ($alerts as $alert) {
        $hasCorruptedChars = strpos($alert['message'], '├') !== false;
        $style = $hasCorruptedChars ? 'background-color: #ffcccc;' : 'background-color: #ccffcc;';
        
        echo "<tr style='$style'>";
        echo "<td>" . htmlspecialchars($alert['id']) . "</td>";
        echo "<td>" . htmlspecialchars($alert['message']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>🎯 Attività Descriptions</h3>";
    $stmt = $pdo->query("SELECT id, descrizione FROM attivita WHERE descrizione IS NOT NULL ORDER BY id LIMIT 5");
    $activities = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Descrizione (dovrebbe mostrare caratteri corretti)</th></tr>";
    
    foreach ($activities as $activity) {
        $hasCorruptedChars = strpos($activity['descrizione'], '├') !== false;
        $style = $hasCorruptedChars ? 'background-color: #ffcccc;' : 'background-color: #ccffcc;';
        
        echo "<tr style='$style'>";
        echo "<td>" . htmlspecialchars($activity['id']) . "</td>";
        echo "<td>" . htmlspecialchars($activity['descrizione']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test caratteri specifici
    echo "<h3>🔤 Test Caratteri Specifici</h3>";
    echo "<div style='font-size: 18px; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;'>";
    echo "<strong>Caratteri di test:</strong><br>";
    echo "à (a con accento grave)<br>";
    echo "è (e con accento grave)<br>"; 
    echo "ì (i con accento grave)<br>";
    echo "ò (o con accento grave)<br>";
    echo "ù (u con accento grave)<br>";
    echo "attività (parola completa)<br>";
    echo "</div>";
    
    // Verifica generale
    $corruptedCount = 0;
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM (
            SELECT message as text FROM alerts WHERE message LIKE '%├%'
            UNION ALL
            SELECT descrizione as text FROM attivita WHERE descrizione LIKE '%├%'
            UNION ALL
            SELECT note as text FROM timbrature WHERE note LIKE '%├%'
        ) corrupted
    ");
    $result = $stmt->fetch();
    $corruptedCount = $result['count'];
    
    echo "<h3>📈 Risultato Fix</h3>";
    if ($corruptedCount == 0) {
        echo "<div style='padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 5px;'>";
        echo "<strong>✅ SUCCESS!</strong> Nessun carattere corrotto trovato nel database.";
        echo "<br><strong>Dashboard dovrebbe mostrare caratteri corretti!</strong>";
        echo "</div>";
    } else {
        echo "<div style='padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>";
        echo "<strong>❌ ATTENZIONE!</strong> Trovati ancora $corruptedCount record con caratteri corrotti.";
        echo "<br><strong>Rilancia il fix:</strong> FIX_CARATTERI_DATABASE.bat";
        echo "</div>";
    }
    
    echo "<br><a href='laravel_bait/public/index_standalone.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Vai alla Dashboard</a>";
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Errore: " . $e->getMessage() . "</strong>";
}
?>