<?php
// Test rapido query alert per debug

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
                   $config['username'], $config['password']);
    
    echo "<h2>Test Query Alert</h2>";
    
    // Test nuova query fixed
    $stmt = $pdo->query("
        SELECT 
            CONCAT('BAIT_', DATE_FORMAT(a.created_at, '%Y%m%d'), '_', LPAD(a.id, 4, '0')) as id,
            a.severity,
            a.confidence_score,
            COALESCE(t.nome_completo, 'Sistema') as tecnico,
            a.message,
            a.category,
            a.created_at as timestamp,
            a.estimated_cost
        FROM alerts a
        LEFT JOIN tecnici t ON a.tecnico_id = t.id 
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY 
            CASE a.severity 
                WHEN 'CRITICO' THEN 1 
                WHEN 'ALTO' THEN 2 
                WHEN 'MEDIO' THEN 3 
                ELSE 4 
            END,
            confidence_score DESC, 
            created_at DESC
        LIMIT 20
    ");
    
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Risultati: " . count($alerts) . " alert trovati</h3>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th>ID</th>
            <th>Severity</th>
            <th>Tecnico</th>
            <th>Category</th>
            <th>Confidence</th>
            <th>Message</th>
            <th>Cost</th>
            <th>Timestamp</th>
          </tr>";
    
    foreach ($alerts as $alert) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($alert['id']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($alert['severity']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($alert['tecnico']) . "</td>";
        echo "<td>" . htmlspecialchars($alert['category']) . "</td>";
        echo "<td>" . htmlspecialchars($alert['confidence_score']) . "%</td>";
        echo "<td>" . htmlspecialchars($alert['message']) . "</td>";
        echo "<td>€" . htmlspecialchars($alert['estimated_cost']) . "</td>";
        echo "<td>" . htmlspecialchars($alert['timestamp']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    if (count($alerts) > 0) {
        echo "<br><strong style='color: green;'>✓ Query funziona! Gli alert dovrebbero ora apparire nella dashboard.</strong>";
        echo "<br><a href='laravel_bait/public/index_standalone.php'>Vai alla Dashboard</a>";
    } else {
        echo "<br><strong style='color: red;'>✗ Nessun alert trovato. Controllare dati nel database.</strong>";
    }
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Errore: " . $e->getMessage() . "</strong>";
}
?>