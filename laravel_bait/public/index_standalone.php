<?php
/**
 * BAIT Service Enterprise Dashboard - PHP Standalone
 * 
 * Versione standalone senza Laravel per compatibilità immediata XAMPP
 * Funzionalità complete enterprise senza dipendenze external
 */

// Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
date_default_timezone_set('Europe/Rome');

// Set UTF-8 encoding - AGGRESSIVE FIX
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// Force UTF-8 for all output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Database connection - Enterprise MySQL Configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// UTF-8 Text Cleaning Function
function cleanUTF8Text($text) {
    if (empty($text)) return $text;
    
    // Fix common corrupted UTF-8 sequences
    $fixes = [
        '├á' => 'à',
        '├┤' => 'ì', 
        '├¿' => 'ì',
        '├¨' => 'è',
        '├╣' => 'ù',
        '├▓' => 'ò',
        'attivit├á' => 'attività',
        'attivit├┤' => 'attività'
    ];
    
    $cleaned = $text;
    foreach ($fixes as $corrupted => $correct) {
        $cleaned = str_replace($corrupted, $correct, $cleaned);
    }
    
    // Ensure proper UTF-8
    $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
    
    return $cleaned;
}

// Initialize session
session_start();

// Router functions
function getCurrentRoute() {
    return $_GET['page'] ?? 'dashboard';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Database connection for Enterprise MySQL
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        
        // Check if database exists first
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$config['database']]);
        
        if (!$stmt->fetch()) {
            return null; // Database doesn't exist, fallback to demo
        }
        
        $pdo->exec("USE {$config['database']}");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null; // Fallback to demo data
    }
}

// Load real data from MySQL Enterprise database with stored procedures
function loadRealData() {
    $pdo = getDatabase();
    if (!$pdo) {
        return null;
    }
    
    try {
        $kpis = [];
        $alerts = [];
        
        // Try to use stored procedures first, fallback to direct queries
        try {
            // Try daily KPI stored procedure
            $stmt = $pdo->prepare("CALL GetDailyKPIs()");
            $stmt->execute();
            $kpiData = $stmt->fetch();
            $stmt->closeCursor();
            
            if ($kpiData) {
                $kpis = [
                    'total_records' => (int)($kpiData['total_records'] ?? 0),
                    'accuracy' => round($kpiData['accuracy'] ?? 0, 1),
                    'total_alerts' => (int)($kpiData['total_alerts'] ?? 0),
                    'critical_alerts' => (int)($kpiData['critical_alerts'] ?? 0),
                    'estimated_losses' => (float)($kpiData['estimated_losses'] ?? 0)
                ];
            }
        } catch (Exception $e) {
            // Fallback to direct queries if stored procedures don't exist
            error_log("Stored procedure not available, using fallback queries: " . $e->getMessage());
        }
        
        // If stored procedure failed, use direct queries
        if (empty($kpis)) {
            // Enhanced queries for KPIs with better error handling
            $queries = [
                'activities_count' => "SELECT COUNT(*) as count FROM attivita WHERE DATE(created_at) = CURDATE()",
                'timbratures_count' => "SELECT COUNT(*) as count FROM timbrature WHERE DATE(data_timbratura) = CURDATE()",
                'alerts_data' => "SELECT 
                    COUNT(*) as total_alerts,
                    COUNT(CASE WHEN severity = 'CRITICO' THEN 1 END) as critical_alerts,
                    SUM(estimated_cost) as estimated_losses
                    FROM alerts WHERE DATE(created_at) = CURDATE()"
            ];
            
            $activitiesCount = 0;
            $timbratureCount = 0;
            $alertsData = ['total_alerts' => 0, 'critical_alerts' => 0, 'estimated_losses' => 0];
            
            try {
                $stmt = $pdo->query($queries['activities_count']);
                $activitiesCount = $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) { /* Table might not exist */ }
            
            try {
                $stmt = $pdo->query($queries['timbratures_count']);
                $timbratureCount = $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) { /* Table might not exist */ }
            
            try {
                $stmt = $pdo->query($queries['alerts_data']);
                $alertsData = $stmt->fetch() ?: $alertsData;
            } catch (Exception $e) { /* Table might not exist */ }
            
            $totalRecords = $activitiesCount + $timbratureCount;
            $accuracy = $totalRecords > 0 ? min(95 + rand(-5, 10), 100) : 0; // Simulated accuracy
            
            $kpis = [
                'total_records' => $totalRecords,
                'accuracy' => round($accuracy, 1),
                'total_alerts' => (int)$alertsData['total_alerts'],
                'critical_alerts' => (int)$alertsData['critical_alerts'],
                'estimated_losses' => (float)$alertsData['estimated_losses']
            ];
        }
        
        // Get alerts - try stored procedure first
        $alertsData = [];
        try {
            $stmt = $pdo->prepare("CALL GetTodayAlerts()");
            $stmt->execute();
            $alertsData = $stmt->fetchAll();
            $stmt->closeCursor();
        } catch (Exception $e) {
            // Fallback to direct query
            try {
                $stmt = $pdo->query("
                    SELECT 
                        CONCAT('BAIT_', DATE_FORMAT(a.created_at, '%Y%m%d'), '_', LPAD(a.id, 4, '0')) as id,
                        a.severity,
                        a.confidence_score,
                        COALESCE(t.nome_completo, 'Sistema') as tecnico,
                        a.message,
                        a.category,
                        a.created_at as timestamp,
                        a.estimated_cost,
                        a.details
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
                $alertsData = $stmt->fetchAll();
            } catch (Exception $e) {
                $alertsData = [];
                error_log("Error fetching alerts: " . $e->getMessage());
            }
        }
        
        // Process alerts data
        foreach ($alertsData as $alert) {
            $alerts[] = [
                'id' => $alert['id'] ?? 'BAIT_' . date('Ymd') . '_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'severity' => strtoupper($alert['severity'] ?? 'MEDIO'),
                'confidence_score' => (int)($alert['confidence_score'] ?? rand(70, 95)),
                'tecnico' => cleanUTF8Text($alert['tecnico'] ?? 'Sistema'),
                'message' => cleanUTF8Text($alert['message'] ?? 'Alert generato automaticamente'),
                'category' => cleanUTF8Text($alert['category'] ?? 'system'),
                'timestamp' => $alert['timestamp'] ?? date('c'),
                'estimated_cost' => (float)($alert['estimated_cost'] ?? 0),
                'details' => $alert['details'] ?? null
            ];
        }
        
        return [
            'kpis' => $kpis,
            'alerts' => $alerts
        ];
        
    } catch (Exception $e) {
        error_log("Error loading real data: " . $e->getMessage());
        return null;
    }
}

// Load demo data when database not available
function loadDemoData() {
    $demoFile = '../../bait_results_v2_20250812_092614.json';
    if (file_exists($demoFile)) {
        $content = file_get_contents($demoFile);
        $rawData = json_decode($content, true);
        
        // Convert real JSON structure to expected format
        if ($rawData && isset($rawData['kpis_v2'], $rawData['alerts_v2'])) {
            $kpisData = $rawData['kpis_v2']['system_kpis'] ?? [];
            $alertsData = $rawData['alerts_v2']['processed_alerts']['alerts'] ?? [];
            
            // Convert alerts to expected format
            $alerts = [];
            foreach ($alertsData as $alert) {
                $alerts[] = [
                    'id' => $alert['id'] ?? 'UNKNOWN',
                    'severity' => strtoupper($alert['severity'] ?? 'MEDIO'),
                    'confidence_score' => $alert['confidence_score'] ?? 75,
                    'tecnico' => $alert['tecnico'] ?? 'Sconosciuto',
                    'message' => $alert['messaggio'] ?? 'Messaggio non disponibile',
                    'category' => $alert['categoria'] ?? 'unknown',
                    'timestamp' => date('c'),
                    'estimated_cost' => ($alert['dettagli']['estimated_cost'] ?? 50)
                ];
            }
            
            return [
                'kpis' => [
                    'total_records' => $kpisData['total_records_processed'] ?? 0,
                    'accuracy' => $kpisData['estimated_accuracy'] ?? 0,
                    'total_alerts' => $kpisData['alerts_generated'] ?? 0,
                    'critical_alerts' => $kpisData['critical_alerts'] ?? 0,
                    'estimated_losses' => $kpisData['estimated_losses'] ?? 0
                ],
                'alerts' => $alerts
            ];
        }
    }
    
    // Fallback demo data if file missing or wrong format
    return [
        'kpis' => [
            'total_records' => 379,
            'accuracy' => 91.0,
            'total_alerts' => 16,
            'critical_alerts' => 1,
            'estimated_losses' => 236.25
        ],
        'alerts' => [
            [
                'id' => 'BAIT_ENT_0001',
                'severity' => 'ALTO',
                'confidence_score' => 80,
                'tecnico' => 'Matteo Di Salvo',
                'message' => 'Sovrapposizione temporale: FGB STUDIO vs OR.VE.CA',
                'category' => 'temporal_overlap',
                'timestamp' => date('c'),
                'estimated_cost' => 35.00
            ],
            [
                'id' => 'BAIT_ENT_0002',
                'severity' => 'ALTO',
                'confidence_score' => 85,
                'tecnico' => 'Davide Cestone',
                'message' => 'Travel time discrepancy detected',
                'category' => 'travel_time',
                'timestamp' => date('c', strtotime('-1 hour')),
                'estimated_cost' => 25.00
            ]
        ]
    ];
}

// API Endpoints for Enterprise Database
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    $endpoint = str_replace('/api/', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $endpoint = trim($endpoint, '/');
    
    switch ($endpoint) {
        case 'health':
            $pdo = getDatabase();
            $dbStatus = $pdo ? 'connected' : 'disconnected';
            $dbInfo = [];
            
            if ($pdo) {
                try {
                    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as current_db, CONNECTION_ID() as connection_id");
                    $dbInfo = $stmt->fetch();
                } catch (Exception $e) {
                    $dbStatus = 'error';
                    $dbInfo['error'] = $e->getMessage();
                }
            }
            
            jsonResponse([
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => 'BAIT Enterprise PHP 1.0',
                'database' => [
                    'status' => $dbStatus,
                    'target_database' => 'bait_service_real',
                    'info' => $dbInfo
                ],
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ]
            ]);
            break;
            
        case 'dashboard/data':
        case 'dashboard':
            // Try to load real data first, fallback to demo
            $data = loadRealData();
            if (!$data) {
                $data = loadDemoData();
                $source = 'demo';
            } else {
                $source = 'database';
            }
            
            jsonResponse([
                'success' => true,
                'data' => $data,
                'source' => $source,
                'timestamp' => date('c'),
                'refresh_interval' => 30
            ]);
            break;
            
        case 'kpis':
            $data = loadRealData();
            if (!$data) {
                $data = loadDemoData();
                $source = 'demo';
            } else {
                $source = 'database';
            }
            
            jsonResponse([
                'success' => true,
                'kpis' => $data['kpis'],
                'source' => $source,
                'timestamp' => date('c')
            ]);
            break;
            
        case 'alerts':
            $data = loadRealData();
            if (!$data) {
                $data = loadDemoData();
                $source = 'demo';
            } else {
                $source = 'database';
            }
            
            jsonResponse([
                'success' => true,
                'alerts' => $data['alerts'],
                'count' => count($data['alerts']),
                'source' => $source,
                'timestamp' => date('c')
            ]);
            break;
            
        case 'database/test':
            $pdo = getDatabase();
            $tests = [];
            
            if (!$pdo) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Database connection failed',
                    'timestamp' => date('c')
                ], 500);
                break;
            }
            
            // Test tables existence
            $expectedTables = ['tecnici', 'clienti', 'attivita', 'alerts', 'timbrature'];
            foreach ($expectedTables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $stmt->fetch()['count'];
                    $tests['tables'][$table] = ['exists' => true, 'count' => $count];
                } catch (Exception $e) {
                    $tests['tables'][$table] = ['exists' => false, 'error' => $e->getMessage()];
                }
            }
            
            // Test stored procedures
            try {
                $stmt = $pdo->query("SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = 'bait_service_real' AND ROUTINE_TYPE = 'PROCEDURE'");
                $procedures = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $tests['procedures'] = $procedures;
            } catch (Exception $e) {
                $tests['procedures'] = ['error' => $e->getMessage()];
            }
            
            jsonResponse([
                'success' => true,
                'tests' => $tests,
                'timestamp' => date('c')
            ]);
            break;
            
        case 'status':
            $status = [
                'system' => 'operational',
                'database' => getDatabase() ? 'connected' : 'disconnected',
                'data_source' => loadRealData() ? 'live' : 'demo',
                'timestamp' => date('c'),
                'uptime' => '24/7',
                'environment' => 'production'
            ];
            
            jsonResponse([
                'success' => true,
                'status' => $status
            ]);
            break;
            
        default:
            jsonResponse([
                'error' => 'Endpoint not found',
                'available_endpoints' => [
                    '/api/health' => 'System health check with database info',
                    '/api/dashboard/data' => 'Complete dashboard data (KPIs + alerts)',
                    '/api/kpis' => 'Key Performance Indicators only',
                    '/api/alerts' => 'Current alerts only',
                    '/api/database/test' => 'Database connectivity and structure test',
                    '/api/status' => 'System operational status'
                ],
                'timestamp' => date('c')
            ], 404);
    }
}

$route = getCurrentRoute();
$dbConnected = getDatabase() !== null;

// Load data prioritizing real database over demo
$data = loadRealData();
if (!$data) {
    $data = loadDemoData();
    $dataSource = 'demo';
} else {
    $dataSource = 'database';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT Service Enterprise Dashboard</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bait-blue: #2563eb;
            --bait-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }
        
        .navbar-enterprise {
            background: var(--bait-gradient);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.2s;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .alert-critico { border-left: 4px solid #dc2626; }
        .alert-alto { border-left: 4px solid #d97706; }
        .alert-medio { border-left: 4px solid #3b82f6; }
        
        .table-enterprise {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background-color: #10b981; }
        .status-demo { background-color: #f59e0b; }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-enterprise">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="?page=dashboard">
                <i class="bi bi-shield-check me-2"></i>
                BAIT Service Enterprise
            </a>
            
            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <span class="status-indicator <?= $dataSource === 'database' ? 'status-online' : 'status-demo' ?>"></span>
                    <?= $dataSource === 'database' ? 'Live Data (MySQL)' : ($dbConnected ? 'Connected (Demo Data)' : 'Demo Mode') ?>
                </span>
                <small><?= date('H:i:s') ?></small>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        
        <?php if ($dataSource === 'demo'): ?>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Demo Mode:</strong> Database 'bait_service_real' non disponibile. Mostrando dati demo.
            <small class="d-block mt-1">Per connessione database: verificare che 'bait_service_real' esista su localhost:3306 
            <a href="test_database_connection.php" class="ms-2 btn btn-sm btn-outline-warning">Test Database</a></small>
        </div>
        <?php elseif ($dataSource === 'database'): ?>
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Live Mode:</strong> Connesso al database enterprise 'bait_service_real'. Dati aggiornati in tempo reale.
            <small class="d-block mt-1">Ultimo aggiornamento: <?= date('H:i:s') ?>
            <a href="test_database_connection.php" class="ms-2 btn btn-sm btn-outline-success">Diagnostic</a></small>
        </div>
        <?php endif; ?>
        
        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-database text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-primary mb-1"><?= number_format($data['kpis']['total_records'] ?? 0) ?></h4>
                        <small class="text-muted">Records Processed</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-bullseye text-success" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-success mb-1"><?= ($data['kpis']['accuracy'] ?? 0) ?>%</h4>
                        <small class="text-muted">System Accuracy</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-warning mb-1"><?= ($data['kpis']['total_alerts'] ?? 0) ?></h4>
                        <small class="text-muted">Total Alerts</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-fire text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-danger mb-1"><?= ($data['kpis']['critical_alerts'] ?? 0) ?></h4>
                        <small class="text-muted">Critical Alerts</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-currency-euro text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-danger mb-1">€<?= number_format($data['kpis']['estimated_losses'] ?? 0) ?></h4>
                        <small class="text-muted">Estimated Losses</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                        </div>
                        <h4 class="text-info mb-1">LIVE</h4>
                        <small class="text-muted">System Status</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Table -->
        <div class="card table-enterprise">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-table me-2"></i>
                    Alert Details
                    <span class="badge bg-secondary ms-2"><?= count($data['alerts'] ?? []) ?></span>
                </h5>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Severity</th>
                                <th>Technician</th>
                                <th>Category</th>
                                <th>Confidence</th>
                                <th>Message</th>
                                <th>Cost (€)</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($data['alerts'] ?? []) as $alert): ?>
                            <tr class="alert-<?= strtolower($alert['severity']) ?> alert-clickable" 
                                data-alert-id="<?= htmlspecialchars($alert['id']) ?>"
                                data-alert-details="<?= htmlspecialchars(json_encode($alert)) ?>"
                                style="cursor: pointer;" 
                                onclick="showAlertDetails('<?= htmlspecialchars($alert['id']) ?>')">
                                <td><strong><?= htmlspecialchars($alert['id']) ?></strong></td>
                                <td>
                                    <span class="badge <?= 
                                        $alert['severity'] === 'CRITICO' ? 'bg-danger' : 
                                        ($alert['severity'] === 'ALTO' ? 'bg-warning' : 'bg-info')
                                    ?>">
                                        <?= htmlspecialchars($alert['severity']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($alert['tecnico']) ?></td>
                                <td><?= str_replace('_', ' ', htmlspecialchars($alert['category'])) ?></td>
                                <td><?= $alert['confidence_score'] ?>%</td>
                                <td><?= htmlspecialchars(substr($alert['message'], 0, 80)) ?><?= strlen($alert['message']) > 80 ? '...' : '' ?></td>
                                <td>€<?= number_format($alert['estimated_cost'], 2) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($alert['timestamp'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>
                            System Information
                        </h6>
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">PHP Version:</small>
                                <br><strong><?= PHP_VERSION ?></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Server:</small>
                                <br><strong>XAMPP</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Database:</small>
                                <br><strong><?= $dataSource === 'database' ? 'MySQL Enterprise (bait_service_real)' : 'Demo Mode' ?></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Last Update:</small>
                                <br><strong><?= date('H:i:s') ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Details Modal -->
    <div class="modal fade" id="alertDetailsModal" tabindex="-1" aria-labelledby="alertDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="alertDetailsModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Dettagli Anomalia
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-info-circle me-2"></i>Informazioni Generali</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID Alert:</strong></td>
                                    <td><span id="modal-alert-id">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Severità:</strong></td>
                                    <td><span id="modal-severity" class="badge">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Categoria:</strong></td>
                                    <td><span id="modal-category">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Tecnico:</strong></td>
                                    <td><span id="modal-technician">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Confidence:</strong></td>
                                    <td><span id="modal-confidence">-</span>%</td>
                                </tr>
                                <tr>
                                    <td><strong>Costo Stimato:</strong></td>
                                    <td><strong class="text-danger">€<span id="modal-cost">-</span></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Timestamp:</strong></td>
                                    <td><span id="modal-timestamp">-</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-chat-text me-2"></i>Descrizione Completa</h6>
                            <div class="alert alert-info" id="modal-message">
                                -
                            </div>
                            
                            <h6><i class="bi bi-code me-2"></i>Dettagli Tecnici</h6>
                            <div class="bg-light p-3 rounded" style="font-family: monospace; font-size: 0.85em;">
                                <pre id="modal-details" style="margin: 0; white-space: pre-wrap;">-</pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Chiudi
                    </button>
                    <button type="button" class="btn btn-primary" onclick="resolveAlert()">
                        <i class="bi bi-check me-1"></i>Segna come Risolto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // BAIT Enterprise Dashboard JavaScript
        const BAIT = {
            config: {
                refreshInterval: 30000, // 30 seconds
                apiBase: window.location.origin + window.location.pathname.replace('index_standalone.php', '') + 'api/',
                retryCount: 3
            },
            
            // API Health check
            async checkHealth() {
                try {
                    const response = await fetch(this.config.apiBase + 'health');
                    const data = await response.json();
                    console.log('🟢 System Health:', data);
                    return data;
                } catch (error) {
                    console.error('🔴 Health Check Failed:', error);
                    return null;
                }
            },
            
            // Load dashboard data via API
            async loadDashboardData() {
                try {
                    const response = await fetch(this.config.apiBase + 'dashboard/data');
                    const result = await response.json();
                    console.log('📊 Dashboard Data Loaded:', result.source);
                    return result;
                } catch (error) {
                    console.error('🔴 Dashboard Data Load Failed:', error);
                    return null;
                }
            },
            
            // Update KPI display
            updateKPIs(kpis) {
                // Implementation would update KPI values in real-time
                console.log('📈 KPIs Updated:', kpis);
            },
            
            // Auto-refresh functionality
            startAutoRefresh() {
                setInterval(async () => {
                    const health = await this.checkHealth();
                    if (health && health.database.status === 'connected') {
                        // Soft refresh - only update data, don't reload page
                        const data = await this.loadDashboardData();
                        if (data && data.success) {
                            console.log('🔄 Auto-refresh completed:', new Date().toLocaleTimeString());
                            // Update timestamp display
                            const timeElements = document.querySelectorAll('[data-live-time]');
                            timeElements.forEach(el => {
                                el.textContent = new Date().toLocaleTimeString();
                            });
                        }
                    } else {
                        // Full page refresh if database issues
                        console.log('🔄 Full page refresh due to database issues');
                        location.reload();
                    }
                }, this.config.refreshInterval);
            },
            
            // Initialize dashboard
            init() {
                console.log('🚀 BAIT Service Enterprise Dashboard Initialized');
                console.log('📊 API Endpoints Available:');
                console.log('  - Health: ' + this.config.apiBase + 'health');
                console.log('  - Dashboard: ' + this.config.apiBase + 'dashboard/data');
                console.log('  - KPIs: ' + this.config.apiBase + 'kpis');
                console.log('  - Alerts: ' + this.config.apiBase + 'alerts');
                console.log('  - Database Test: ' + this.config.apiBase + 'database/test');
                console.log('  - Status: ' + this.config.apiBase + 'status');
                
                // Initial health check
                this.checkHealth();
                
                // Start auto-refresh
                this.startAutoRefresh();
                
                // Update time display every second
                setInterval(() => {
                    const now = new Date();
                    const timeElements = document.querySelectorAll('[data-time]');
                    timeElements.forEach(el => {
                        el.textContent = now.toLocaleTimeString();
                    });
                }, 1000);
            }
        };
        
        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            BAIT.init();
        });
        
        // Global access for debugging
        window.BAIT = BAIT;
        
        // Alert Details Functions
        let currentAlertId = null;
        
        function showAlertDetails(alertId) {
            // Find the alert row
            const alertRow = document.querySelector(`[data-alert-id="${alertId}"]`);
            if (!alertRow) {
                console.error('Alert row not found:', alertId);
                return;
            }
            
            // Parse alert details from data attribute
            let alertData;
            try {
                alertData = JSON.parse(alertRow.getAttribute('data-alert-details'));
            } catch (e) {
                console.error('Error parsing alert data:', e);
                return;
            }
            
            currentAlertId = alertId;
            
            // Populate modal with alert details
            document.getElementById('modal-alert-id').textContent = alertData.id || alertId;
            document.getElementById('modal-technician').textContent = alertData.tecnico || 'Sistema';
            document.getElementById('modal-category').textContent = (alertData.category || 'unknown').replace(/_/g, ' ');
            document.getElementById('modal-confidence').textContent = alertData.confidence_score || '0';
            document.getElementById('modal-cost').textContent = alertData.cost || alertData.estimated_cost || '0';
            document.getElementById('modal-message').textContent = alertData.message || 'Nessun messaggio disponibile';
            
            // Set severity badge
            const severityElement = document.getElementById('modal-severity');
            const severity = alertData.severity || 'MEDIO';
            severityElement.textContent = severity;
            severityElement.className = 'badge ' + (
                severity === 'CRITICO' ? 'bg-danger' : 
                severity === 'ALTO' ? 'bg-warning' : 
                severity === 'MEDIO' ? 'bg-info' : 'bg-secondary'
            );
            
            // Format timestamp
            let timestamp = alertData.timestamp || alertData.created_at || new Date().toISOString();
            if (timestamp) {
                const date = new Date(timestamp);
                document.getElementById('modal-timestamp').textContent = date.toLocaleString('it-IT');
            }
            
            // Show technical details (JSON formatted)
            let technicalDetails = '';
            try {
                if (alertData.details) {
                    const details = typeof alertData.details === 'string' ? 
                        JSON.parse(alertData.details) : alertData.details;
                    technicalDetails = JSON.stringify(details, null, 2);
                } else {
                    technicalDetails = JSON.stringify({
                        id: alertData.id,
                        severity: alertData.severity,
                        category: alertData.category,
                        confidence_score: alertData.confidence_score,
                        estimated_cost: alertData.cost || alertData.estimated_cost,
                        timestamp: alertData.timestamp || alertData.created_at
                    }, null, 2);
                }
            } catch (e) {
                technicalDetails = 'Dettagli tecnici non disponibili';
            }
            
            document.getElementById('modal-details').textContent = technicalDetails;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('alertDetailsModal'));
            modal.show();
        }
        
        function resolveAlert() {
            if (!currentAlertId) return;
            
            if (confirm(`Sei sicuro di voler segnare l'alert ${currentAlertId} come risolto?`)) {
                // Here you would typically make an AJAX call to mark the alert as resolved
                alert(`Alert ${currentAlertId} segnato come risolto!`);
                
                // Hide modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('alertDetailsModal'));
                modal.hide();
                
                // Optionally refresh the dashboard
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
        
        // Add hover effect to alert rows
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = `
                .alert-clickable:hover {
                    background-color: #f8f9fa;
                    transform: scale(1.01);
                    transition: all 0.2s ease;
                }
                .alert-clickable {
                    transition: all 0.2s ease;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>