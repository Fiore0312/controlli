<?php
/**
 * AUDIT TECNICO DASHBOARD - Controllo Dettagliato Singolo Tecnico
 * Sistema di audit quotidiano per verifica attività individuale
 */

require_once 'TechnicianAnalyzer.php';

header('Content-Type: text/html; charset=utf-8');

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Initialize variables
$selectedTechnician = $_GET['tecnico'] ?? null;
$selectedDate = $_GET['data'] ?? date('Y-m-d');
$analysisResult = null;
$technicians = [];
$error = null;

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    // Get list of technicians
    $technicians = $pdo->query("SELECT id, nome_completo FROM tecnici WHERE attivo = 1 ORDER BY nome_completo")->fetchAll();

    // Process analysis if requested
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze'])) {
        $selectedTechnician = $_POST['tecnico_id'];
        $selectedDate = $_POST['analysis_date'];
        
        $analyzer = new TechnicianAnalyzer($pdo);
        $analysisResult = $analyzer->analyzeTechnicianDay($selectedTechnician, $selectedDate);
    }

    // Load existing analysis if available
    if ($selectedTechnician && $selectedDate && !$analysisResult) {
        $stmt = $pdo->prepare("
            SELECT tda.*, t.nome_completo as tecnico_nome,
                   COUNT(te.id) as timeline_events,
                   COUNT(aa.id) as total_alerts_count
            FROM technician_daily_analysis tda
            JOIN tecnici t ON tda.tecnico_id = t.id
            LEFT JOIN timeline_events te ON tda.id = te.daily_analysis_id
            LEFT JOIN audit_alerts aa ON tda.id = aa.daily_analysis_id AND aa.status = 'new'
            WHERE tda.tecnico_id = ? AND tda.data_analisi = ?
            GROUP BY tda.id
        ");
        $stmt->execute([$selectedTechnician, $selectedDate]);
        $existingAnalysis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingAnalysis) {
            $analysisResult = [
                'success' => true,
                'analysis_id' => $existingAnalysis['id'],
                'quality_score' => $existingAnalysis['quality_score'],
                'timeline_events' => $existingAnalysis['timeline_events'],
                'summary' => $existingAnalysis
            ];
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Get timeline events and alerts for display
$timelineEvents = [];
$alerts = [];
if ($analysisResult && $analysisResult['success']) {
    try {
        // Timeline events
        $stmt = $pdo->prepare("
            SELECT * FROM timeline_events 
            WHERE daily_analysis_id = ? 
            ORDER BY start_time
        ");
        $stmt->execute([$analysisResult['analysis_id']]);
        $timelineEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Alerts
        $stmt = $pdo->prepare("
            SELECT * FROM audit_alerts 
            WHERE daily_analysis_id = ? 
            ORDER BY severity DESC, created_at
        ");
        $stmt->execute([$analysisResult['analysis_id']]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Silent error handling
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Audit Tecnico - Controllo Dettagliato</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .main-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .audit-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .timeline-card {
            border-left: 4px solid;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .timeline-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .timeline-deepser { border-left-color: #007bff; }
        .timeline-auto { border-left-color: #28a745; }
        .timeline-teamviewer { border-left-color: #ffc107; }
        .timeline-calendario { border-left-color: #dc3545; }
        
        .score-display {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
        }
        
        .score-excellent { color: #28a745; }
        .score-good { color: #ffc107; }
        .score-warning { color: #fd7e14; }
        .score-critical { color: #dc3545; }
        
        .alert-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .gap-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .gap-ok { background-color: #28a745; }
        .gap-warning { background-color: #ffc107; }
        .gap-critical { background-color: #dc3545; }
        
        .source-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">🔍 Audit Tecnico Dettagliato</h1>
                    <p class="text-muted mb-0">Controllo quotidiano attività individuali</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="laravel_bait/public/index_standalone.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard Principale
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Error Display -->
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Errore: <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Selection Form -->
        <div class="audit-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-user-check me-2"></i>Selezione Tecnico e Data
                </h5>
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tecnico</label>
                        <select name="tecnico_id" class="form-select" required>
                            <option value="">Seleziona tecnico...</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" 
                                        <?= $selectedTechnician == $tech['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Data Analisi</label>
                        <input type="date" name="analysis_date" class="form-control" 
                               value="<?= $selectedDate ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="analyze" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Analizza Tecnico
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($analysisResult): ?>
        
        <!-- Analysis Results -->
        <?php if ($analysisResult['success']): ?>
        
        <!-- Quality Score -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="audit-card text-center">
                    <div class="card-body">
                        <h6 class="card-title">Score Qualità</h6>
                        <div class="score-display 
                            <?= $analysisResult['quality_score'] >= 90 ? 'score-excellent' : 
                                ($analysisResult['quality_score'] >= 75 ? 'score-good' : 
                                ($analysisResult['quality_score'] >= 50 ? 'score-warning' : 'score-critical')) ?>">
                            <?= number_format($analysisResult['quality_score'], 1) ?>%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="audit-card text-center">
                    <div class="card-body">
                        <h6 class="card-title">Eventi Timeline</h6>
                        <div class="score-display text-info">
                            <?= $analysisResult['timeline_events'] ?>
                        </div>
                        <small class="text-muted">eventi rilevati</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="audit-card text-center">
                    <div class="card-body">
                        <h6 class="card-title">Alert Generati</h6>
                        <div class="score-display text-warning">
                            <?= count($alerts) ?>
                        </div>
                        <small class="text-muted">problemi rilevati</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="audit-card text-center">
                    <div class="card-body">
                        <h6 class="card-title">Ore Deepser</h6>
                        <div class="score-display text-success">
                            <?= number_format($analysisResult['summary']['deepser_hours'] ?? 0, 1) ?>h
                        </div>
                        <small class="text-muted">ore dichiarate</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Analysis -->
        <div class="row">
            <div class="col-md-8">
                <div class="audit-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Timeline Giornaliera
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($timelineEvents)): ?>
                            <?php foreach ($timelineEvents as $event): ?>
                            <div class="timeline-card timeline-<?= $event['event_source'] ?> p-3 mb-2 bg-light rounded">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <span class="source-badge badge bg-<?= 
                                            $event['event_source'] === 'deepser' ? 'primary' : 
                                            ($event['event_source'] === 'auto' ? 'success' : 
                                            ($event['event_source'] === 'teamviewer' ? 'warning' : 'danger')) ?>">
                                            <?= strtoupper($event['event_source']) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><?= date('H:i', strtotime($event['start_time'])) ?></strong>
                                        <?php if ($event['end_time']): ?>
                                        - <?= date('H:i', strtotime($event['end_time'])) ?>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= $event['duration_minutes'] ?> min</small>
                                    </div>
                                    <div class="col-md-4">
                                        <strong><?= htmlspecialchars($event['client_name'] ?? 'N/A') ?></strong>
                                        <br>
                                        <small><?= htmlspecialchars(substr($event['activity_description'] ?? '', 0, 50)) ?>...</small>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="badge bg-<?= 
                                            $event['location_type'] === 'remote' ? 'info' : 
                                            ($event['location_type'] === 'onsite' ? 'success' : 'secondary') ?>">
                                            <?= ucfirst($event['location_type']) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-1">
                                        <?php if ($event['is_validated']): ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                        <?php else: ?>
                                        <i class="fas fa-question-circle text-warning"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>Nessun evento timeline trovato per questa data</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Gap Analysis -->
                <div class="audit-card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>Analisi Gap Temporali
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $morningGap = $analysisResult['summary']['morning_gap_minutes'] ?? 0;
                        $afternoonGap = $analysisResult['summary']['afternoon_gap_minutes'] ?? 0;
                        ?>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <span class="gap-indicator gap-<?= $morningGap <= 30 ? 'ok' : ($morningGap <= 60 ? 'warning' : 'critical') ?>"></span>
                                <span>Gap Mattutino: <strong><?= $morningGap ?> min</strong></span>
                            </div>
                            <small class="text-muted">Ritardo vs 09:00</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <span class="gap-indicator gap-<?= $afternoonGap <= 30 ? 'ok' : ($afternoonGap <= 60 ? 'warning' : 'critical') ?>"></span>
                                <span>Gap Pomeridiano: <strong><?= $afternoonGap ?> min</strong></span>
                            </div>
                            <small class="text-muted">Ritardo vs 14:00</small>
                        </div>
                        
                        <?php if ($analysisResult['summary']['has_timeline_gaps']): ?>
                        <div class="alert alert-warning alert-sm">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Gap significativi rilevati
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success alert-sm">
                            <i class="fas fa-check me-2"></i>
                            Timeline rispetta orari standard
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Coherence Checks -->
                <div class="audit-card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Controlli Coerenza
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="badge bg-<?= $analysisResult['summary']['has_remote_with_auto'] ? 'danger' : 'success' ?> me-2">
                                <?= $analysisResult['summary']['has_remote_with_auto'] ? '✗' : '✓' ?>
                            </span>
                            Remote vs Auto
                        </div>
                        
                        <div class="mb-2">
                            <span class="badge bg-<?= $analysisResult['summary']['has_overlapping_activities'] ? 'danger' : 'success' ?> me-2">
                                <?= $analysisResult['summary']['has_overlapping_activities'] ? '✗' : '✓' ?>
                            </span>
                            Sovrapposizioni
                        </div>
                        
                        <div class="mb-2">
                            <span class="badge bg-<?= $analysisResult['summary']['has_missing_teamviewer_activities'] ? 'warning' : 'success' ?> me-2">
                                <?= $analysisResult['summary']['has_missing_teamviewer_activities'] ? '⚠' : '✓' ?>
                            </span>
                            TeamViewer vs Deepser
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
        <div class="audit-card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Alert e Anomalie
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= 
                    $alert['severity'] === 'critical' ? 'danger' : 
                    ($alert['severity'] === 'high' ? 'warning' : 
                    ($alert['severity'] === 'medium' ? 'info' : 'light')) ?> mb-2">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <strong><?= htmlspecialchars($alert['title']) ?></strong>
                            <br>
                            <small><?= htmlspecialchars($alert['message']) ?></small>
                        </div>
                        <div class="col-md-2">
                            <span class="alert-badge badge bg-<?= 
                                $alert['severity'] === 'critical' ? 'danger' : 
                                ($alert['severity'] === 'high' ? 'warning' : 'secondary') ?>">
                                <?= strtoupper($alert['severity']) ?>
                            </span>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-light text-dark">
                                <?= ucfirst(str_replace('_', ' ', $alert['category'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Analysis Error -->
        <div class="audit-card">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h5>Errore nell'Analisi</h5>
                <p class="text-muted"><?= htmlspecialchars($analysisResult['error'] ?? 'Errore sconosciuto') ?></p>
                <button onclick="window.location.reload()" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i>Riprova
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Help Section -->
        <div class="audit-card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Legenda e Controlli
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Fonti Dati:</h6>
                        <ul class="list-unstyled">
                            <li><span class="badge bg-primary me-2">DEEPSER</span>Attività registrate</li>
                            <li><span class="badge bg-success me-2">AUTO</span>Utilizzo veicoli aziendali</li>
                            <li><span class="badge bg-warning me-2">TEAMVIEWER</span>Sessioni remote</li>
                            <li><span class="badge bg-danger me-2">CALENDARIO</span>Appuntamenti programmati</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Controlli Automatici:</h6>
                        <ul class="list-unstyled">
                            <li>✓ Gap temporali (9-11, 14-18)</li>
                            <li>✓ Attività remote con utilizzo auto</li>
                            <li>✓ Sovrapposizioni temporali</li>
                            <li>✓ TeamViewer ≥15min senza attività</li>
                            <li>✓ Coerenza cross-validation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript functionality here
            console.log('Audit Tecnico Dashboard loaded successfully');
        });
    </script>
</body>
</html>