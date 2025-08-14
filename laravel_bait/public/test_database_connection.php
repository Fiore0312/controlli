<?php
/**
 * BAIT Service Enterprise - Database Connection Test
 * 
 * Script per testare la connessione al database MySQL enterprise
 * e verificare che tutte le tabelle, views e stored procedures siano presenti
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Rome');

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Test results
$results = [
    'connection' => false,
    'database_exists' => false,
    'tables' => [],
    'views' => [],
    'procedures' => [],
    'sample_data' => [],
    'errors' => []
];

echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>BAIT Service - Database Connection Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
    <style>
        .test-success { color: #198754; }
        .test-error { color: #dc3545; }
        .test-warning { color: #fd7e14; }
    </style>
</head>
<body class='bg-light'>
    <div class='container py-4'>
        <div class='row'>
            <div class='col-12'>
                <div class='card'>
                    <div class='card-header'>
                        <h4><i class='bi bi-database-check me-2'></i>BAIT Service Enterprise - Database Test</h4>
                        <small class='text-muted'>Testing connection to: {$config['database']} @ {$config['host']}:{$config['port']}</small>
                    </div>
                    <div class='card-body'>";

try {
    // Test 1: Basic Connection
    echo "<h5><i class='bi bi-1-circle me-2'></i>Testing Database Connection</h5>";
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='alert alert-success test-success'>
            <i class='bi bi-check-circle me-2'></i>MySQL connection successful
          </div>";
    $results['connection'] = true;
    
    // Test 2: Database Exists
    echo "<h5><i class='bi bi-2-circle me-2'></i>Checking Database Existence</h5>";
    
    $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->execute([$config['database']]);
    $dbExists = $stmt->fetch();
    
    if ($dbExists) {
        echo "<div class='alert alert-success test-success'>
                <i class='bi bi-check-circle me-2'></i>Database '{$config['database']}' exists
              </div>";
        $results['database_exists'] = true;
        
        // Switch to database
        $pdo->exec("USE {$config['database']}");
        
        // Test 3: Check Tables
        echo "<h5><i class='bi bi-3-circle me-2'></i>Checking Tables</h5>";
        
        $expectedTables = ['tecnici', 'clienti', 'attivita', 'alerts', 'timbrature'];
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='row'>";
        foreach ($expectedTables as $table) {
            $exists = in_array($table, $tables);
            $results['tables'][$table] = $exists;
            
            echo "<div class='col-md-6 mb-2'>
                    <span class='" . ($exists ? 'test-success' : 'test-error') . "'>
                        <i class='bi bi-" . ($exists ? 'check' : 'x') . "-circle me-2'></i>
                        Table: {$table}
                    </span>
                  </div>";
        }
        echo "</div>";
        
        // Test 4: Check Views
        echo "<h5><i class='bi bi-4-circle me-2'></i>Checking Views</h5>";
        
        $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = '{$config['database']}'");
        $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($views)) {
            echo "<div class='alert alert-warning test-warning'>
                    <i class='bi bi-exclamation-triangle me-2'></i>No views found
                  </div>";
        } else {
            echo "<div class='alert alert-success test-success'>
                    <i class='bi bi-check-circle me-2'></i>Found " . count($views) . " views: " . implode(', ', $views) . "
                  </div>";
        }
        $results['views'] = $views;
        
        // Test 5: Check Stored Procedures
        echo "<h5><i class='bi bi-5-circle me-2'></i>Checking Stored Procedures</h5>";
        
        $stmt = $pdo->query("SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = '{$config['database']}' AND ROUTINE_TYPE = 'PROCEDURE'");
        $procedures = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($procedures)) {
            echo "<div class='alert alert-warning test-warning'>
                    <i class='bi bi-exclamation-triangle me-2'></i>No stored procedures found
                  </div>";
        } else {
            echo "<div class='alert alert-success test-success'>
                    <i class='bi bi-check-circle me-2'></i>Found " . count($procedures) . " stored procedures: " . implode(', ', $procedures) . "
                  </div>";
        }
        $results['procedures'] = $procedures;
        
        // Test 6: Sample Data Test
        echo "<h5><i class='bi bi-6-circle me-2'></i>Testing Sample Data</h5>";
        
        foreach ($expectedTables as $table) {
            if (in_array($table, $tables)) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $stmt->fetch()['count'];
                    $results['sample_data'][$table] = $count;
                    
                    echo "<div class='row mb-1'>
                            <div class='col-md-3'><strong>{$table}:</strong></div>
                            <div class='col-md-3'>{$count} records</div>
                          </div>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger test-error'>
                            <i class='bi bi-x-circle me-2'></i>Error reading {$table}: " . $e->getMessage() . "
                          </div>";
                    $results['errors'][] = "Table {$table}: " . $e->getMessage();
                }
            }
        }
        
        // Test 7: Connection Health Check
        echo "<h5><i class='bi bi-7-circle me-2'></i>Connection Health Check</h5>";
        
        $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_datetime");
        $health = $stmt->fetch();
        
        echo "<div class='alert alert-info'>
                <i class='bi bi-info-circle me-2'></i>
                <strong>MySQL Version:</strong> {$health['version']}<br>
                <strong>Current Time:</strong> {$health['current_datetime']}<br>
                <strong>Character Set:</strong> {$config['charset']}
              </div>";
        
    } else {
        echo "<div class='alert alert-danger test-error'>
                <i class='bi bi-x-circle me-2'></i>Database '{$config['database']}' does not exist!
              </div>";
        $results['errors'][] = "Database does not exist";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger test-error'>
            <i class='bi bi-x-circle me-2'></i>Connection failed: " . $e->getMessage() . "
          </div>";
    $results['errors'][] = "Connection failed: " . $e->getMessage();
}

// Summary
echo "<hr><h5><i class='bi bi-clipboard-check me-2'></i>Test Summary</h5>";

$totalTests = 7;
$passedTests = 0;
if ($results['connection']) $passedTests++;
if ($results['database_exists']) $passedTests++;
if (count(array_filter($results['tables'])) >= 3) $passedTests++;
if (!empty($results['views'])) $passedTests++;
if (!empty($results['procedures'])) $passedTests++;
if (!empty($results['sample_data'])) $passedTests++;
if (empty($results['errors'])) $passedTests++;

$percentage = round(($passedTests / $totalTests) * 100);

if ($percentage >= 80) {
    $statusClass = 'success';
    $statusIcon = 'check-circle';
    $statusText = 'System Ready for Production';
} elseif ($percentage >= 60) {
    $statusClass = 'warning';
    $statusIcon = 'exclamation-triangle';
    $statusText = 'System Partially Ready - Some Issues Found';
} else {
    $statusClass = 'danger';
    $statusIcon = 'x-circle';
    $statusText = 'System Not Ready - Critical Issues Found';
}

echo "<div class='alert alert-{$statusClass}'>
        <i class='bi bi-{$statusIcon} me-2'></i>
        <strong>{$statusText}</strong><br>
        Tests Passed: {$passedTests}/{$totalTests} ({$percentage}%)
      </div>";

// Action Items
if (!empty($results['errors'])) {
    echo "<h6>Action Items:</h6>";
    echo "<ul>";
    foreach ($results['errors'] as $error) {
        echo "<li class='text-danger'>{$error}</li>";
    }
    echo "</ul>";
}

echo "<div class='mt-3'>
        <a href='index_standalone.php' class='btn btn-primary'>
            <i class='bi bi-arrow-left me-2'></i>Back to Dashboard
        </a>
        <button onclick='location.reload()' class='btn btn-secondary'>
            <i class='bi bi-arrow-clockwise me-2'></i>Rerun Test
        </button>
      </div>";

echo "                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        console.log('Database Test Results:', " . json_encode($results, JSON_PRETTY_PRINT) . ");
    </script>
</body>
</html>";
?>