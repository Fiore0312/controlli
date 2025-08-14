<?php
/**
 * BAIT Service Enterprise - Complete System Test
 * 
 * Test automatico completo per verificare:
 * - Connessione database MySQL enterprise
 * - Funzionalità API endpoints
 * - Caricamento dati reali vs demo
 * - Performance e latenza
 * - Integrità dati
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Rome');

// Test configuration
$testConfig = [
    'base_url' => 'http://localhost/controlli/laravel_bait/public/',
    'api_endpoints' => [
        'health',
        'dashboard/data',
        'kpis',
        'alerts',
        'database/test',
        'status'
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'bait_service_real',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ]
];

// Test results storage
$testResults = [
    'summary' => [
        'total_tests' => 0,
        'passed' => 0,
        'failed' => 0,
        'warnings' => 0,
        'duration' => 0,
        'timestamp' => date('c')
    ],
    'tests' => [],
    'recommendations' => []
];

// Utility functions
function logTest($name, $status, $message, $details = []) {
    global $testResults;
    
    $test = [
        'name' => $name,
        'status' => $status, // 'pass', 'fail', 'warning'
        'message' => $message,
        'details' => $details,
        'timestamp' => date('c')
    ];
    
    $testResults['tests'][] = $test;
    $testResults['summary']['total_tests']++;
    
    switch ($status) {
        case 'pass':
            $testResults['summary']['passed']++;
            break;
        case 'fail':
            $testResults['summary']['failed']++;
            break;
        case 'warning':
            $testResults['summary']['warnings']++;
            break;
    }
    
    return $test;
}

function addRecommendation($message) {
    global $testResults;
    $testResults['recommendations'][] = $message;
}

// Start testing
$startTime = microtime(true);

echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>BAIT Service - Full System Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
    <style>
        .test-pass { color: #198754; }
        .test-fail { color: #dc3545; }
        .test-warning { color: #fd7e14; }
        .test-progress { min-height: 4px; }
        .test-details { font-family: 'Courier New', monospace; font-size: 0.85em; }
    </style>
</head>
<body class='bg-light'>
    <div class='container py-4'>
        <div class='row'>
            <div class='col-12'>
                <div class='card'>
                    <div class='card-header bg-primary text-white'>
                        <h4><i class='bi bi-gear-wide-connected me-2'></i>BAIT Service Enterprise - Full System Test</h4>
                        <small>Comprehensive testing suite for database, API, and dashboard functionality</small>
                    </div>
                    <div class='card-body'>";

// Test 1: Database Connection
echo "<h5><i class='bi bi-1-circle me-2'></i>Testing Database Connection</h5>";

try {
    $dsn = "mysql:host={$testConfig['database']['host']};port={$testConfig['database']['port']};charset={$testConfig['database']['charset']}";
    $pdo = new PDO($dsn, $testConfig['database']['username'], $testConfig['database']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    logTest('Database Connection', 'pass', 'MySQL connection successful');
    
    // Check if target database exists
    $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->execute([$testConfig['database']['database']]);
    
    if ($stmt->fetch()) {
        logTest('Target Database', 'pass', "Database '{$testConfig['database']['database']}' exists");
        $pdo->exec("USE {$testConfig['database']['database']}");
    } else {
        logTest('Target Database', 'fail', "Database '{$testConfig['database']['database']}' does not exist");
        addRecommendation("Create the database '{$testConfig['database']['database']}' and import schema");
    }
    
} catch (PDOException $e) {
    logTest('Database Connection', 'fail', 'MySQL connection failed: ' . $e->getMessage());
    addRecommendation("Check MySQL server is running and credentials are correct");
}

// Test 2: Table Structure
echo "<h5><i class='bi bi-2-circle me-2'></i>Testing Table Structure</h5>";

if (isset($pdo)) {
    $expectedTables = ['technicians', 'clients', 'activities', 'alerts', 'timbratures'];
    $tableTests = [];
    
    foreach ($expectedTables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE {$table}");
            $columns = $stmt->fetchAll();
            $tableTests[$table] = ['exists' => true, 'columns' => count($columns)];
            logTest("Table {$table}", 'pass', "Table exists with " . count($columns) . " columns");
        } catch (Exception $e) {
            $tableTests[$table] = ['exists' => false, 'error' => $e->getMessage()];
            logTest("Table {$table}", 'fail', "Table does not exist or is not accessible");
        }
    }
    
    $existingTables = array_filter($tableTests, fn($t) => $t['exists']);
    if (count($existingTables) < count($expectedTables)) {
        addRecommendation("Import the complete database schema with all required tables");
    }
}

// Test 3: API Endpoints
echo "<h5><i class='bi bi-3-circle me-2'></i>Testing API Endpoints</h5>";

foreach ($testConfig['api_endpoints'] as $endpoint) {
    $url = $testConfig['base_url'] . 'api/' . $endpoint;
    
    $startRequest = microtime(true);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    $requestTime = round((microtime(true) - $startRequest) * 1000, 2);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            logTest("API /{$endpoint}", 'pass', "Response received in {$requestTime}ms", [
                'response_time' => $requestTime,
                'response_size' => strlen($response),
                'data_keys' => array_keys($data)
            ]);
            
            if ($requestTime > 2000) {
                addRecommendation("API endpoint /{$endpoint} is slow ({$requestTime}ms) - consider optimization");
            }
        } else {
            logTest("API /{$endpoint}", 'fail', "Invalid JSON response");
        }
    } else {
        logTest("API /{$endpoint}", 'fail', "No response or connection error");
        addRecommendation("Check web server configuration and file permissions");
    }
}

// Test 4: Data Loading Performance
echo "<h5><i class='bi bi-4-circle me-2'></i>Testing Data Loading Performance</h5>";

$url = $testConfig['base_url'] . 'api/dashboard/data';
$performanceTests = [];

for ($i = 1; $i <= 5; $i++) {
    $startRequest = microtime(true);
    $response = @file_get_contents($url);
    $requestTime = round((microtime(true) - $startRequest) * 1000, 2);
    $performanceTests[] = $requestTime;
}

$avgTime = round(array_sum($performanceTests) / count($performanceTests), 2);
$minTime = min($performanceTests);
$maxTime = max($performanceTests);

if ($avgTime < 500) {
    logTest('Dashboard Performance', 'pass', "Average response time: {$avgTime}ms (min: {$minTime}ms, max: {$maxTime}ms)");
} elseif ($avgTime < 1000) {
    logTest('Dashboard Performance', 'warning', "Average response time: {$avgTime}ms - acceptable but could be faster");
} else {
    logTest('Dashboard Performance', 'fail', "Average response time: {$avgTime}ms - too slow for production");
    addRecommendation("Optimize database queries and consider caching");
}

// Test 5: Data Source Verification
echo "<h5><i class='bi bi-5-circle me-2'></i>Testing Data Source</h5>";

$response = @file_get_contents($testConfig['base_url'] . 'api/dashboard/data');
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['source'])) {
        if ($data['source'] === 'database') {
            logTest('Data Source', 'pass', 'System is using live database data');
        } else {
            logTest('Data Source', 'warning', 'System is using demo/fallback data');
            addRecommendation("Ensure database contains data or verify connection issues");
        }
    }
}

// Test 6: Error Handling
echo "<h5><i class='bi bi-6-circle me-2'></i>Testing Error Handling</h5>";

$errorTestUrl = $testConfig['base_url'] . 'api/nonexistent';
$errorResponse = @file_get_contents($errorTestUrl);

if ($errorResponse) {
    $errorData = json_decode($errorResponse, true);
    if (isset($errorData['error']) && isset($errorData['available_endpoints'])) {
        logTest('Error Handling', 'pass', 'API properly handles unknown endpoints with helpful error messages');
    } else {
        logTest('Error Handling', 'warning', 'API handles errors but could provide more helpful information');
    }
} else {
    logTest('Error Handling', 'fail', 'API does not handle errors gracefully');
}

// Calculate test duration
$testResults['summary']['duration'] = round((microtime(true) - $startTime), 2);

// Display results summary
echo "<hr><h5><i class='bi bi-clipboard-check me-2'></i>Test Results Summary</h5>";

$passRate = round(($testResults['summary']['passed'] / $testResults['summary']['total_tests']) * 100, 1);

if ($passRate >= 90) {
    $statusClass = 'success';
    $statusIcon = 'check-circle';
    $statusText = 'System is Production Ready';
} elseif ($passRate >= 70) {
    $statusClass = 'warning';
    $statusIcon = 'exclamation-triangle';
    $statusText = 'System has Minor Issues';
} else {
    $statusClass = 'danger';
    $statusIcon = 'x-circle';
    $statusText = 'System has Critical Issues';
}

echo "<div class='alert alert-{$statusClass}'>
        <i class='bi bi-{$statusIcon} me-2'></i>
        <strong>{$statusText}</strong><br>
        Pass Rate: {$passRate}% ({$testResults['summary']['passed']}/{$testResults['summary']['total_tests']} tests passed)<br>
        Test Duration: {$testResults['summary']['duration']} seconds
      </div>";

// Detailed results
echo "<h6>Detailed Results:</h6>";
echo "<div class='table-responsive'>";
echo "<table class='table table-sm'>";
echo "<thead><tr><th>Test</th><th>Status</th><th>Message</th><th>Details</th></tr></thead>";
echo "<tbody>";

foreach ($testResults['tests'] as $test) {
    $statusClass = 'test-' . $test['status'];
    $statusIcon = $test['status'] === 'pass' ? 'check-circle' : ($test['status'] === 'fail' ? 'x-circle' : 'exclamation-triangle');
    
    echo "<tr>";
    echo "<td><strong>{$test['name']}</strong></td>";
    echo "<td><span class='{$statusClass}'><i class='bi bi-{$statusIcon} me-1'></i>" . strtoupper($test['status']) . "</span></td>";
    echo "<td>{$test['message']}</td>";
    echo "<td>";
    if (!empty($test['details'])) {
        echo "<small class='test-details'>" . json_encode($test['details'], JSON_PRETTY_PRINT) . "</small>";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "</div>";

// Recommendations
if (!empty($testResults['recommendations'])) {
    echo "<h6>Recommendations:</h6>";
    echo "<ul class='list-group list-group-flush'>";
    foreach ($testResults['recommendations'] as $rec) {
        echo "<li class='list-group-item'><i class='bi bi-lightbulb text-warning me-2'></i>{$rec}</li>";
    }
    echo "</ul>";
}

// Action buttons
echo "<div class='mt-4 d-flex gap-2'>";
echo "<a href='index_standalone.php' class='btn btn-primary'><i class='bi bi-arrow-left me-2'></i>Back to Dashboard</a>";
echo "<a href='test_database_connection.php' class='btn btn-secondary'><i class='bi bi-database me-2'></i>Database Test</a>";
echo "<button onclick='location.reload()' class='btn btn-outline-primary'><i class='bi bi-arrow-clockwise me-2'></i>Rerun Tests</button>";
echo "<button onclick='downloadResults()' class='btn btn-outline-success'><i class='bi bi-download me-2'></i>Download Results</button>";
echo "</div>";

echo "                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function downloadResults() {
            const results = " . json_encode($testResults, JSON_PRETTY_PRINT) . ";
            const blob = new Blob([JSON.stringify(results, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'bait_system_test_results_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        console.log('🧪 Full System Test Completed');
        console.log('📊 Results:', " . json_encode($testResults['summary']) . ");
    </script>
</body>
</html>";
?>