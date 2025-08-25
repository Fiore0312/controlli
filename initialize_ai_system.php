<?php
/**
 * BAIT AI System Initialization Script
 * Sets up the AI chat system and indexes project files
 */

require_once 'FileAnalyzer.php';

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
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    echo "<h2>🤖 BAIT AI System Initialization</h2>";
    echo "<div style='font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 8px;'>";
    
    // Initialize FileAnalyzer
    echo "<p>📁 Initializing FileAnalyzer...</p>";
    $fileAnalyzer = new FileAnalyzer($pdo);
    echo "<p>✅ FileAnalyzer initialized successfully</p>";
    
    // Index all project files
    echo "<p>🔍 Starting file indexing process...</p>";
    $startTime = microtime(true);
    $results = $fileAnalyzer->indexAllFiles();
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "<p><strong>📊 Indexing Results:</strong></p>";
    echo "<ul>";
    echo "<li>Files scanned: {$results['scanned']}</li>";
    echo "<li>Files indexed: {$results['indexed']}</li>";
    echo "<li>Files updated: {$results['updated']}</li>";
    echo "<li>Files skipped: {$results['skipped']}</li>";
    echo "<li>Errors: {$results['errors']}</li>";
    echo "<li>Duration: {$duration}s</li>";
    echo "</ul>";
    
    // Get statistics
    $stats = $fileAnalyzer->getFileStatistics();
    
    echo "<p><strong>📈 File Statistics:</strong></p>";
    echo "<ul>";
    echo "<li>Total files: {$stats['totals']['total_files']}</li>";
    echo "<li>Total size: " . round($stats['totals']['total_size'] / 1024, 2) . " KB</li>";
    echo "<li>Average complexity: " . round($stats['totals']['avg_complexity'], 1) . "</li>";
    echo "</ul>";
    
    echo "<p><strong>📋 Files by type:</strong></p>";
    echo "<ul>";
    foreach ($stats['by_type'] as $type) {
        echo "<li>{$type['file_type']}: {$type['count']} files (avg complexity: " . round($type['avg_complexity'], 1) . ")</li>";
    }
    echo "</ul>";
    
    echo "<p>✅ <strong>System initialization completed successfully!</strong></p>";
    echo "<p>🚀 <a href='bait_ai_chat.php' style='color: #007bff;'>Open BAIT AI Chat Interface</a></p>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #fff5f5; padding: 1rem; border: 1px solid #fed7d7; border-radius: 8px;'>";
    echo "<h3>❌ Initialization Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>