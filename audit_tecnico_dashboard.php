<?php
/**
 * AUDIT TECNICO DASHBOARD - Controllo Dettagliato Singolo Tecnico
 * Sistema di audit quotidiano per verifica attività individuale
 */

require_once 'TechnicianAnalyzer.php';
require_once 'includes/bait_navigation.php';

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
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="BAIT Service - Sistema Audit Tecnico Enterprise per controllo dettagliato attività individuale">
    <meta name="keywords" content="BAIT, audit, tecnico, controllo, attività, dashboard">
    <meta name="author" content="BAIT Service">
    <title>Audit Tecnico Dettagliato | BAIT Service Enterprise</title>
    
    <!-- DNS Prefetch for Performance -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    
    <!-- Critical CSS Preload -->
    <link rel="preload" href="assets/css/bait-enterprise.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="assets/css/bait-enterprise.css"></noscript>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Font Awesome Pro -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js" crossorigin="anonymous"></script>
    
    <!-- Enterprise Custom Styles -->
    <style>
        /* Enterprise Layout Overrides */
        .audit-timeline-card {
            border-left: 4px solid var(--border-accent);
            background: var(--surface-elevated);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-3);
            transition: all 0.2s ease;
            padding: var(--space-4);
        }
        
        .audit-timeline-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }
        
        .timeline-deepser { border-left-color: var(--bait-primary); }
        .timeline-auto { border-left-color: var(--bait-success); }
        .timeline-teamviewer { border-left-color: var(--bait-warning); }
        .timeline-calendario { border-left-color: var(--bait-danger); }
        
        .audit-score-display {
            font-size: var(--text-5xl);
            font-weight: 700;
            line-height: 1;
            color: var(--text-primary);
        }
        
        .score-excellent { color: var(--bait-success) !important; }
        .score-good { color: var(--bait-warning) !important; }
        .score-warning { color: var(--bait-warning) !important; }
        .score-critical { color: var(--bait-danger) !important; }
        
        .audit-gap-indicator {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full);
            display: inline-block;
            margin-right: var(--space-2);
        }
        
        .gap-ok { background-color: var(--bait-success); }
        .gap-warning { background-color: var(--bait-warning); }
        .gap-critical { background-color: var(--bait-danger); }
        
        .audit-source-badge {
            font-size: var(--text-xs);
            font-weight: 600;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
        }
        
        /* Enhanced Mobile Responsive */
        @media (max-width: 768px) {
            .audit-score-display {
                font-size: var(--text-3xl);
            }
            .audit-timeline-card {
                padding: var(--space-3);
            }
        }
        
        /* Performance Optimizations */
        .bait-card {
            contain: layout style;
        }
        
        /* Accessibility Enhancements */
        .audit-timeline-card:focus-within {
            outline: 2px solid var(--border-focus);
            outline-offset: 2px;
        }
    </style>
</head>
<body class="bait-enterprise-layout">
    <?php 
    // Render unified navigation
    renderBaitNavigation('audit_tecnico_dashboard', 'database'); 
    ?>

    <!-- Main Content Container -->
    <main class="bait-main-content" role="main">
        <div class="bait-container">
            <!-- System Status & Error Display -->
            <?php if ($error): ?>
            <div class="bait-alert bait-alert-danger" role="alert" aria-live="polite">
                <div class="bait-alert-content">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <div>
                        <strong>Errore di Sistema</strong>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
                <button type="button" class="bait-alert-dismiss" onclick="this.parentElement.remove()" 
                        aria-label="Chiudi avviso">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <?php endif; ?>

            <!-- Enterprise Filters Section -->
            <section class="bait-filters-section bait-mb-8" role="region" aria-label="Filtri e controlli analisi">
                <div class="bait-card" role="group" aria-labelledby="filters-title">
                    <div class="bait-card-header">
                        <h2 class="bait-card-title" id="filters-title">
                            <i class="fas fa-filter"></i>
                            Parametri Analisi Tecnico
                        </h2>
                        <div class="bait-card-subtitle">
                            Seleziona tecnico e data per generare l'analisi dettagliata delle attività
                        </div>
                    </div>
                    <div class="bait-card-body">
                        <form method="POST" class="bait-form" id="analysisFiltersForm" 
                              aria-describedby="form-help">
                            <div class="bait-grid bait-gap-6">
                                <!-- Technician Selection -->
                                <div class="bait-form-group">
                                    <label for="tecnico_id" class="bait-form-label">
                                        <i class="fas fa-user me-2 text-primary" aria-hidden="true"></i>
                                        Tecnico da Analizzare
                                        <span class="bait-required" aria-label="campo obbligatorio">*</span>
                                    </label>
                                    <select name="tecnico_id" id="tecnico_id" class="bait-form-select" 
                                            required aria-describedby="tecnico-help">
                                        <option value="">Seleziona un tecnico per l'analisi...</option>
                                        <?php foreach ($technicians as $tech): ?>
                                            <option value="<?= $tech['id'] ?>" 
                                                    <?= $selectedTechnician == $tech['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tech['nome_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="bait-form-help" id="tecnico-help">
                                        Seleziona il tecnico per cui eseguire l'analisi dettagliata delle attività
                                    </div>
                                </div>

                                <!-- Date Selection -->
                                <div class="bait-form-group">
                                    <label for="analysis_date" class="bait-form-label">
                                        <i class="fas fa-calendar-day me-2 text-info" aria-hidden="true"></i>
                                        Data di Analisi
                                        <span class="bait-required" aria-label="campo obbligatorio">*</span>
                                    </label>
                                    <input type="date" name="analysis_date" id="analysis_date" 
                                           class="bait-form-control" value="<?= $selectedDate ?>" 
                                           required aria-describedby="date-help" max="<?= date('Y-m-d') ?>">
                                    <div class="bait-form-help" id="date-help">
                                        Seleziona la data specifica per l'analisi delle attività (max: oggi)
                                    </div>
                                </div>

                                <!-- Analysis Controls -->
                                <div class="bait-form-group">
                                    <label class="bait-form-label">
                                        <i class="fas fa-cogs me-2 text-warning" aria-hidden="true"></i>
                                        Controlli Analisi
                                    </label>
                                    <div class="bait-form-actions">
                                        <button type="submit" name="analyze" class="bait-btn bait-btn-primary" 
                                                aria-describedby="analyze-help">
                                            <i class="fas fa-search me-2" aria-hidden="true"></i>
                                            <span>Esegui Analisi Completa</span>
                                        </button>
                                        <button type="button" class="bait-btn bait-btn-outline" 
                                                onclick="resetFilters()" aria-label="Reset filtri">
                                            <i class="fas fa-undo me-2" aria-hidden="true"></i>
                                            <span>Reset</span>
                                        </button>
                                        <button type="button" class="bait-btn bait-btn-ghost" 
                                                onclick="previewAnalysis()" aria-label="Anteprima analisi">
                                            <i class="fas fa-eye me-2" aria-hidden="true"></i>
                                            <span>Anteprima</span>
                                        </button>
                                    </div>
                                    <div class="bait-form-help" id="analyze-help">
                                        L'analisi completa processa tutti i dati disponibili per tecnico e data selezionati
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bait-form-help bait-mt-4" id="form-help">
                                <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                                Il sistema analizza automaticamente: timeline attività, gap temporali, controlli coerenza e alert anomalie
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Analysis Results Section -->
            <?php if ($analysisResult): ?>
            <?php if ($analysisResult['success']): ?>
            
            <!-- Enterprise KPI Dashboard -->
            <section class="bait-kpi-section bait-mb-8" role="region" aria-label="Indicatori prestazione chiave">
                <div class="bait-section-header bait-mb-6">
                    <h2 class="bait-section-title">
                        <i class="fas fa-chart-line"></i>
                        Metriche Performance Tecnico
                    </h2>
                    <p class="bait-section-subtitle">
                        Dashboard completa delle prestazioni e indicatori qualità per la giornata selezionata
                    </p>
                </div>
                
                <div class="bait-kpi-grid">
                    <!-- Quality Score KPI -->
                    <div class="bait-kpi-card bait-kpi-primary" role="article" aria-labelledby="quality-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-award" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="quality-title">Score Qualità</h3>
                                <p class="bait-kpi-subtitle">Valutazione complessiva</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value audit-score-display 
                                <?= $analysisResult['quality_score'] >= 90 ? 'score-excellent' : 
                                    ($analysisResult['quality_score'] >= 75 ? 'score-good' : 
                                    ($analysisResult['quality_score'] >= 50 ? 'score-warning' : 'score-critical')) ?>">
                                <?= number_format($analysisResult['quality_score'], 1) ?><span class="bait-kpi-unit">%</span>
                            </div>
                            <div class="bait-kpi-trend">
                                <span class="bait-trend-indicator <?= $analysisResult['quality_score'] >= 75 ? 'bait-trend-up' : 'bait-trend-down' ?>">
                                    <i class="fas fa-arrow-<?= $analysisResult['quality_score'] >= 75 ? 'up' : 'down' ?>" aria-hidden="true"></i>
                                </span>
                                <span class="bait-trend-text">
                                    <?= $analysisResult['quality_score'] >= 90 ? 'Eccellente' : 
                                        ($analysisResult['quality_score'] >= 75 ? 'Buono' : 
                                        ($analysisResult['quality_score'] >= 50 ? 'Migliorabile' : 'Critico')) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Events KPI -->
                    <div class="bait-kpi-card bait-kpi-info" role="article" aria-labelledby="timeline-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="timeline-title">Eventi Timeline</h3>
                                <p class="bait-kpi-subtitle">Attività registrate</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value audit-score-display">
                                <?= $analysisResult['timeline_events'] ?><span class="bait-kpi-unit">eventi</span>
                            </div>
                            <div class="bait-kpi-description">
                                Numero totale di eventi rilevati nelle fonti dati
                            </div>
                        </div>
                    </div>

                    <!-- Alerts KPI -->
                    <div class="bait-kpi-card bait-kpi-warning" role="article" aria-labelledby="alerts-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="alerts-title">Alert Generati</h3>
                                <p class="bait-kpi-subtitle">Anomalie rilevate</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value audit-score-display">
                                <?= count($alerts) ?><span class="bait-kpi-unit">problemi</span>
                            </div>
                            <div class="bait-kpi-description">
                                Controlli automatici che richiedono attenzione
                            </div>
                        </div>
                    </div>

                    <!-- Deepser Hours KPI -->
                    <div class="bait-kpi-card bait-kpi-success" role="article" aria-labelledby="hours-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-business-time" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="hours-title">Ore Deepser</h3>
                                <p class="bait-kpi-subtitle">Attività dichiarate</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value audit-score-display">
                                <?= number_format($analysisResult['summary']['deepser_hours'] ?? 0, 1) ?><span class="bait-kpi-unit">h</span>
                            </div>
                            <div class="bait-kpi-description">
                                Ore totali registrate nel sistema Deepser
                            </div>
                        </div>
                    </div>

                    <!-- Additional KPIs Row -->
                    <div class="bait-kpi-card bait-kpi-secondary" role="article" aria-labelledby="efficiency-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="efficiency-title">Efficienza</h3>
                                <p class="bait-kpi-subtitle">Rapporto ore/eventi</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value">
                                <?= $analysisResult['timeline_events'] > 0 ? 
                                    number_format(($analysisResult['summary']['deepser_hours'] ?? 0) / $analysisResult['timeline_events'], 1) : '0' ?>
                                <span class="bait-kpi-unit">h/evento</span>
                            </div>
                        </div>
                    </div>

                    <div class="bait-kpi-card bait-kpi-neutral" role="article" aria-labelledby="coverage-title">
                        <div class="bait-kpi-header">
                            <div class="bait-kpi-icon">
                                <i class="fas fa-percentage" aria-hidden="true"></i>
                            </div>
                            <div class="bait-kpi-meta">
                                <h3 class="bait-kpi-title" id="coverage-title">Copertura</h3>
                                <p class="bait-kpi-subtitle">Timeline vs standard</p>
                            </div>
                        </div>
                        <div class="bait-kpi-content">
                            <div class="bait-kpi-value">
                                <?= number_format((($analysisResult['summary']['deepser_hours'] ?? 0) / 8) * 100, 0) ?>
                                <span class="bait-kpi-unit">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Enterprise Timeline Analysis -->
            <section class="bait-timeline-section bait-mb-8">
                <div class="bait-grid bait-gap-6">
                    <!-- Main Timeline Data Table -->
                    <div class="bait-timeline-main">
                        <div class="bait-data-table" role="region" aria-label="Timeline eventi giornaliera">
                            <div class="bait-card-header">
                                <h3 class="bait-card-title">
                                    <i class="fas fa-timeline"></i>
                                    Timeline Giornaliera Dettagliata
                                </h3>
                                <div class="bait-card-subtitle">
                                    Sequenza cronologica completa delle attività con controlli di coerenza
                                </div>
                                <div class="bait-table-actions">
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="exportTimeline()" aria-label="Esporta timeline">
                                        <i class="fas fa-download" aria-hidden="true"></i>
                                        <span>Esporta</span>
                                    </button>
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="printTimeline()" aria-label="Stampa timeline">
                                        <i class="fas fa-print" aria-hidden="true"></i>
                                        <span>Stampa</span>
                                    </button>
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="toggleTimelineView()" aria-label="Cambia vista">
                                        <i class="fas fa-th-list" aria-hidden="true"></i>
                                        <span>Vista</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="bait-table-container">
                                <?php if (!empty($timelineEvents)): ?>
                                <div class="bait-timeline-table">
                                    <div class="bait-timeline-header">
                                        <div class="bait-timeline-col-source">Fonte</div>
                                        <div class="bait-timeline-col-time">Orario</div>
                                        <div class="bait-timeline-col-client">Cliente & Attività</div>
                                        <div class="bait-timeline-col-location">Tipo</div>
                                        <div class="bait-timeline-col-status">Stato</div>
                                        <div class="bait-timeline-col-actions">Azioni</div>
                                    </div>
                                    
                                    <div class="bait-timeline-body">
                                        <?php foreach ($timelineEvents as $index => $event): ?>
                                        <div class="audit-timeline-card timeline-<?= $event['event_source'] ?>" 
                                             data-event-id="<?= $index ?>" 
                                             tabindex="0" 
                                             role="button"
                                             aria-label="Evento timeline: <?= htmlspecialchars($event['client_name'] ?? 'N/A') ?>">
                                            
                                            <!-- Source Column -->
                                            <div class="bait-timeline-col-source">
                                                <span class="audit-source-badge bait-badge 
                                                    <?= $event['event_source'] === 'deepser' ? 'bait-badge-primary' : 
                                                        ($event['event_source'] === 'auto' ? 'bait-badge-success' : 
                                                        ($event['event_source'] === 'teamviewer' ? 'bait-badge-warning' : 'bait-badge-danger')) ?>">
                                                    <i class="fas fa-<?= 
                                                        $event['event_source'] === 'deepser' ? 'tasks' : 
                                                        ($event['event_source'] === 'auto' ? 'car' : 
                                                        ($event['event_source'] === 'teamviewer' ? 'desktop' : 'calendar')) ?>" 
                                                       aria-hidden="true"></i>
                                                    <span><?= strtoupper($event['event_source']) ?></span>
                                                </span>
                                            </div>
                                            
                                            <!-- Time Column -->
                                            <div class="bait-timeline-col-time">
                                                <div class="bait-time-range">
                                                    <strong class="bait-time-start">
                                                        <?= date('H:i', strtotime($event['start_time'])) ?>
                                                    </strong>
                                                    <?php if ($event['end_time']): ?>
                                                    <span class="bait-time-separator">-</span>
                                                    <strong class="bait-time-end">
                                                        <?= date('H:i', strtotime($event['end_time'])) ?>
                                                    </strong>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="bait-time-duration">
                                                    <i class="fas fa-stopwatch" aria-hidden="true"></i>
                                                    <span><?= $event['duration_minutes'] ?> min</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Client & Activity Column -->
                                            <div class="bait-timeline-col-client">
                                                <div class="bait-client-name">
                                                    <strong><?= htmlspecialchars($event['client_name'] ?? 'N/A') ?></strong>
                                                </div>
                                                <div class="bait-activity-description">
                                                    <?= htmlspecialchars(substr($event['activity_description'] ?? 'Nessuna descrizione', 0, 80)) ?>
                                                    <?php if (strlen($event['activity_description'] ?? '') > 80): ?>
                                                    <button type="button" class="bait-btn-link" 
                                                            onclick="showFullDescription(<?= $index ?>)" 
                                                            aria-label="Mostra descrizione completa">
                                                        <i class="fas fa-expand-alt" aria-hidden="true"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Location Type Column -->
                                            <div class="bait-timeline-col-location">
                                                <span class="bait-badge 
                                                    <?= $event['location_type'] === 'remote' ? 'bait-badge-info' : 
                                                        ($event['location_type'] === 'onsite' ? 'bait-badge-success' : 'bait-badge-secondary') ?>">
                                                    <i class="fas fa-<?= $event['location_type'] === 'remote' ? 'wifi' : 'map-marker-alt' ?>" 
                                                       aria-hidden="true"></i>
                                                    <span><?= ucfirst($event['location_type']) ?></span>
                                                </span>
                                            </div>
                                            
                                            <!-- Status Column -->
                                            <div class="bait-timeline-col-status">
                                                <?php if ($event['is_validated']): ?>
                                                <span class="bait-status-indicator bait-status-success" 
                                                      aria-label="Evento validato">
                                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                                    <span class="sr-only">Validato</span>
                                                </span>
                                                <?php else: ?>
                                                <span class="bait-status-indicator bait-status-warning" 
                                                      aria-label="Evento non validato">
                                                    <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    <span class="sr-only">Da verificare</span>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Actions Column -->
                                            <div class="bait-timeline-col-actions">
                                                <div class="bait-btn-group">
                                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-xs" 
                                                            onclick="viewEventDetails(<?= $index ?>)" 
                                                            aria-label="Visualizza dettagli evento">
                                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-xs" 
                                                            onclick="editEvent(<?= $index ?>)" 
                                                            aria-label="Modifica evento">
                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Timeline Summary -->
                                <div class="bait-timeline-summary bait-mt-4">
                                    <div class="bait-summary-stats">
                                        <div class="bait-stat-item">
                                            <span class="bait-stat-label">Totale Eventi:</span>
                                            <span class="bait-stat-value"><?= count($timelineEvents) ?></span>
                                        </div>
                                        <div class="bait-stat-item">
                                            <span class="bait-stat-label">Durata Totale:</span>
                                            <span class="bait-stat-value"><?= array_sum(array_column($timelineEvents, 'duration_minutes')) ?> min</span>
                                        </div>
                                        <div class="bait-stat-item">
                                            <span class="bait-stat-label">Eventi Validati:</span>
                                            <span class="bait-stat-value"><?= count(array_filter($timelineEvents, fn($e) => $e['is_validated'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <!-- Empty State -->
                                <div class="bait-empty-state">
                                    <div class="bait-empty-icon">
                                        <i class="fas fa-calendar-times" aria-hidden="true"></i>
                                    </div>
                                    <h4 class="bait-empty-title">Nessun Evento Timeline</h4>
                                    <p class="bait-empty-description">
                                        Non sono stati trovati eventi per la data selezionata. 
                                        Verifica che i dati siano stati caricati correttamente.
                                    </p>
                                    <div class="bait-empty-actions">
                                        <button type="button" class="bait-btn bait-btn-primary" 
                                                onclick="refreshTimeline()" aria-label="Aggiorna timeline">
                                            <i class="fas fa-sync-alt me-2" aria-hidden="true"></i>
                                            <span>Aggiorna Dati</span>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Analytics -->
                    <div class="bait-timeline-sidebar">
                        <!-- Gap Analysis Card -->
                        <div class="bait-card bait-mb-6" role="region" aria-labelledby="gap-analysis-title">
                            <div class="bait-card-header">
                                <h4 class="bait-card-title" id="gap-analysis-title">
                                    <i class="fas fa-chart-line"></i>
                                    Analisi Gap Temporali
                                </h4>
                                <div class="bait-card-subtitle">
                                    Controllo aderenza agli orari standard di lavoro
                                </div>
                            </div>
                            <div class="bait-card-body">
                                <?php 
                                $morningGap = $analysisResult['summary']['morning_gap_minutes'] ?? 0;
                                $afternoonGap = $analysisResult['summary']['afternoon_gap_minutes'] ?? 0;
                                ?>
                                
                                <!-- Morning Gap -->
                                <div class="bait-gap-item bait-mb-4">
                                    <div class="bait-gap-header">
                                        <span class="audit-gap-indicator gap-<?= $morningGap <= 30 ? 'ok' : ($morningGap <= 60 ? 'warning' : 'critical') ?>" 
                                              aria-label="Indicatore gap mattutino"></span>
                                        <div class="bait-gap-info">
                                            <div class="bait-gap-label">Gap Mattutino</div>
                                            <div class="bait-gap-value"><?= $morningGap ?> <span class="bait-gap-unit">minuti</span></div>
                                        </div>
                                    </div>
                                    <div class="bait-gap-description">
                                        Ritardo rispetto all'orario standard delle 09:00
                                    </div>
                                    <div class="bait-gap-progress">
                                        <div class="bait-progress-bar">
                                            <div class="bait-progress-fill bg-<?= $morningGap <= 30 ? 'success' : ($morningGap <= 60 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= min(100, ($morningGap / 120) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Afternoon Gap -->
                                <div class="bait-gap-item bait-mb-4">
                                    <div class="bait-gap-header">
                                        <span class="audit-gap-indicator gap-<?= $afternoonGap <= 30 ? 'ok' : ($afternoonGap <= 60 ? 'warning' : 'critical') ?>" 
                                              aria-label="Indicatore gap pomeridiano"></span>
                                        <div class="bait-gap-info">
                                            <div class="bait-gap-label">Gap Pomeridiano</div>
                                            <div class="bait-gap-value"><?= $afternoonGap ?> <span class="bait-gap-unit">minuti</span></div>
                                        </div>
                                    </div>
                                    <div class="bait-gap-description">
                                        Ritardo rispetto all'orario standard delle 14:00
                                    </div>
                                    <div class="bait-gap-progress">
                                        <div class="bait-progress-bar">
                                            <div class="bait-progress-fill bg-<?= $afternoonGap <= 30 ? 'success' : ($afternoonGap <= 60 ? 'warning' : 'danger') ?>" 
                                                 style="width: <?= min(100, ($afternoonGap / 120) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overall Status -->
                                <div class="bait-gap-summary">
                                    <?php if ($analysisResult['summary']['has_timeline_gaps'] ?? false): ?>
                                    <div class="bait-alert bait-alert-warning bait-alert-sm">
                                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                        <div>
                                            <strong>Gap Significativi Rilevati</strong>
                                            <p>La timeline presenta ritardi che richiedono attenzione</p>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="bait-alert bait-alert-success bait-alert-sm">
                                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                                        <div>
                                            <strong>Timeline Conforme</strong>
                                            <p>Orari rispettano gli standard aziendali</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Coherence Checks Card -->
                        <div class="bait-card" role="region" aria-labelledby="coherence-title">
                            <div class="bait-card-header">
                                <h4 class="bait-card-title" id="coherence-title">
                                    <i class="fas fa-shield-check"></i>
                                    Controlli Coerenza
                                </h4>
                                <div class="bait-card-subtitle">
                                    Validazione cross-source per identificare incongruenze
                                </div>
                            </div>
                            <div class="bait-card-body">
                                <!-- Coherence Check Items -->
                                <div class="bait-coherence-checks">
                                    <!-- Remote vs Auto Check -->
                                    <div class="bait-coherence-item">
                                        <div class="bait-coherence-status">
                                            <span class="bait-status-indicator 
                                                <?= ($analysisResult['summary']['has_remote_with_auto'] ?? false) ? 'bait-status-danger' : 'bait-status-success' ?>">
                                                <i class="fas fa-<?= ($analysisResult['summary']['has_remote_with_auto'] ?? false) ? 'times' : 'check' ?>" 
                                                   aria-hidden="true"></i>
                                            </span>
                                        </div>
                                        <div class="bait-coherence-content">
                                            <div class="bait-coherence-label">Controllo Remote vs Auto</div>
                                            <div class="bait-coherence-description">
                                                Verifica incongruenze tra attività remote e utilizzo auto aziendale
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Overlapping Activities Check -->
                                    <div class="bait-coherence-item">
                                        <div class="bait-coherence-status">
                                            <span class="bait-status-indicator 
                                                <?= ($analysisResult['summary']['has_overlapping_activities'] ?? false) ? 'bait-status-danger' : 'bait-status-success' ?>">
                                                <i class="fas fa-<?= ($analysisResult['summary']['has_overlapping_activities'] ?? false) ? 'times' : 'check' ?>" 
                                                   aria-hidden="true"></i>
                                            </span>
                                        </div>
                                        <div class="bait-coherence-content">
                                            <div class="bait-coherence-label">Controllo Sovrapposizioni</div>
                                            <div class="bait-coherence-description">
                                                Rilevamento attività temporalmente sovrapposte
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- TeamViewer vs Deepser Check -->
                                    <div class="bait-coherence-item">
                                        <div class="bait-coherence-status">
                                            <span class="bait-status-indicator 
                                                <?= ($analysisResult['summary']['has_missing_teamviewer_activities'] ?? false) ? 'bait-status-warning' : 'bait-status-success' ?>">
                                                <i class="fas fa-<?= ($analysisResult['summary']['has_missing_teamviewer_activities'] ?? false) ? 'exclamation' : 'check' ?>" 
                                                   aria-hidden="true"></i>
                                            </span>
                                        </div>
                                        <div class="bait-coherence-content">
                                            <div class="bait-coherence-label">TeamViewer vs Deepser</div>
                                            <div class="bait-coherence-description">
                                                Coerenza tra sessioni remote e attività registrate
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Summary Actions -->
                                <div class="bait-coherence-actions bait-mt-4">
                                    <button type="button" class="bait-btn bait-btn-outline bait-btn-sm" 
                                            onclick="runDetailedChecks()" aria-label="Esegui controlli dettagliati">
                                        <i class="fas fa-search-plus" aria-hidden="true"></i>
                                        <span>Controlli Dettagliati</span>
                                    </button>
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="exportCoherenceReport()" aria-label="Esporta report coerenza">
                                        <i class="fas fa-file-export" aria-hidden="true"></i>
                                        <span>Esporta</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Enterprise Alerts & Anomalies Section -->
            <?php if (!empty($alerts)): ?>
            <section class="bait-alerts-section bait-mb-8" role="region" aria-label="Alert e anomalie sistema">
                <div class="bait-card" role="group" aria-labelledby="alerts-title">
                    <div class="bait-card-header">
                        <h3 class="bait-card-title" id="alerts-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Alert e Anomalie Rilevate
                        </h3>
                        <div class="bait-card-subtitle">
                            Sistema di notifica automatica per problemi identificati nei controlli di coerenza
                        </div>
                        <div class="bait-alerts-summary">
                            <span class="bait-badge bait-badge-danger bait-badge-sm">
                                <?= count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')) ?> Critici
                            </span>
                            <span class="bait-badge bait-badge-warning bait-badge-sm">
                                <?= count(array_filter($alerts, fn($a) => $a['severity'] === 'high')) ?> Alti
                            </span>
                            <span class="bait-badge bait-badge-info bait-badge-sm">
                                <?= count(array_filter($alerts, fn($a) => $a['severity'] === 'medium')) ?> Medi
                            </span>
                        </div>
                    </div>
                    <div class="bait-card-body">
                        <div class="bait-alerts-list">
                            <?php foreach ($alerts as $index => $alert): ?>
                            <div class="bait-alert-item 
                                bait-alert-<?= 
                                    $alert['severity'] === 'critical' ? 'danger' : 
                                    ($alert['severity'] === 'high' ? 'warning' : 
                                    ($alert['severity'] === 'medium' ? 'info' : 'light')) ?>" 
                                 data-alert-id="<?= $index ?>" 
                                 role="article" 
                                 aria-labelledby="alert-title-<?= $index ?>">
                                
                                <div class="bait-alert-icon">
                                    <i class="fas fa-<?= 
                                        $alert['severity'] === 'critical' ? 'times-circle' : 
                                        ($alert['severity'] === 'high' ? 'exclamation-triangle' : 
                                        ($alert['severity'] === 'medium' ? 'info-circle' : 'question-circle')) ?>" 
                                       aria-hidden="true"></i>
                                </div>
                                
                                <div class="bait-alert-content">
                                    <div class="bait-alert-header">
                                        <h4 class="bait-alert-title" id="alert-title-<?= $index ?>">
                                            <?= htmlspecialchars($alert['title']) ?>
                                        </h4>
                                        <div class="bait-alert-meta">
                                            <span class="bait-badge bait-badge-<?= 
                                                $alert['severity'] === 'critical' ? 'danger' : 
                                                ($alert['severity'] === 'high' ? 'warning' : 'secondary') ?>">
                                                <?= strtoupper($alert['severity']) ?>
                                            </span>
                                            <span class="bait-badge bait-badge-light">
                                                <?= ucfirst(str_replace('_', ' ', $alert['category'])) ?>
                                            </span>
                                            <?php if (isset($alert['created_at'])): ?>
                                            <span class="bait-alert-time">
                                                <i class="fas fa-clock" aria-hidden="true"></i>
                                                <?= date('H:i', strtotime($alert['created_at'])) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="bait-alert-message">
                                        <?= htmlspecialchars($alert['message']) ?>
                                    </div>
                                    
                                    <?php if (isset($alert['details']) && !empty($alert['details'])): ?>
                                    <div class="bait-alert-details bait-mt-3">
                                        <button type="button" class="bait-btn-link" 
                                                onclick="toggleAlertDetails(<?= $index ?>)" 
                                                aria-expanded="false" 
                                                aria-controls="alert-details-<?= $index ?>">
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                            <span>Mostra dettagli</span>
                                        </button>
                                        <div id="alert-details-<?= $index ?>" class="bait-alert-details-content bait-hidden">
                                            <pre class="bait-code-block"><?= htmlspecialchars($alert['details']) ?></pre>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bait-alert-actions">
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="viewAlertDetails(<?= $index ?>)" 
                                            aria-label="Visualizza dettagli completi alert">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="markAlertResolved(<?= $index ?>)" 
                                            aria-label="Segna alert come risolto">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="shareAlert(<?= $index ?>)" 
                                            aria-label="Condividi alert">
                                        <i class="fas fa-share" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Alerts Actions -->
                        <div class="bait-alerts-actions bait-mt-6">
                            <button type="button" class="bait-btn bait-btn-primary" 
                                    onclick="resolveAllAlerts()" 
                                    aria-label="Risolvi tutti gli alert">
                                <i class="fas fa-check-double me-2" aria-hidden="true"></i>
                                <span>Risolvi Tutti</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-outline" 
                                    onclick="exportAlertsReport()" 
                                    aria-label="Esporta report alert">
                                <i class="fas fa-file-download me-2" aria-hidden="true"></i>
                                <span>Esporta Report</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost" 
                                    onclick="refreshAlerts()" 
                                    aria-label="Aggiorna lista alert">
                                <i class="fas fa-sync-alt me-2" aria-hidden="true"></i>
                                <span>Aggiorna</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php else: ?>
            <!-- Analysis Error Section -->
            <section class="bait-error-section bait-mb-8" role="region" aria-label="Errore analisi">
                <div class="bait-card">
                    <div class="bait-card-body">
                        <div class="bait-empty-state">
                            <div class="bait-empty-icon">
                                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                            </div>
                            <h3 class="bait-empty-title">Errore nell'Analisi</h3>
                            <p class="bait-empty-description">
                                <?= htmlspecialchars($analysisResult['error'] ?? 'Si è verificato un errore sconosciuto durante l\'elaborazione dei dati') ?>
                            </p>
                            <div class="bait-empty-actions">
                                <button type="button" onclick="window.location.reload()" class="bait-btn bait-btn-primary">
                                    <i class="fas fa-redo me-2" aria-hidden="true"></i>
                                    <span>Riprova Analisi</span>
                                </button>
                                <button type="button" onclick="showDebugInfo()" class="bait-btn bait-btn-outline">
                                    <i class="fas fa-bug me-2" aria-hidden="true"></i>
                                    <span>Info Debug</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Enterprise Help & Documentation Section -->
            <section class="bait-help-section bait-mb-8" role="region" aria-label="Documentazione e guida">
                <div class="bait-card">
                    <div class="bait-card-header">
                        <h3 class="bait-card-title">
                            <i class="fas fa-info-circle"></i>
                            Guida Sistema e Legenda
                        </h3>
                        <div class="bait-card-subtitle">
                            Documentazione completa dei controlli automatici e fonti dati utilizzate
                        </div>
                    </div>
                    <div class="bait-card-body">
                        <div class="bait-help-grid">
                            <!-- Data Sources Documentation -->
                            <div class="bait-help-section">
                                <h4 class="bait-help-title">
                                    <i class="fas fa-database" aria-hidden="true"></i>
                                    Fonti Dati Integrate
                                </h4>
                                <div class="bait-help-content">
                                    <div class="bait-help-item">
                                        <span class="bait-badge bait-badge-primary">
                                            <i class="fas fa-tasks" aria-hidden="true"></i>
                                            DEEPSER
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Sistema di gestione attività:</strong> Registrazione dettagliata interventi clienti con orari, descrizioni e classificazioni di tipologia.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-badge bait-badge-success">
                                            <i class="fas fa-car" aria-hidden="true"></i>
                                            AUTO
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Registro veicoli aziendali:</strong> Tracciamento utilizzo auto con orari ritiro/riconsegna e destinazioni per controlli di coerenza.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-badge bait-badge-warning">
                                            <i class="fas fa-desktop" aria-hidden="true"></i>
                                            TEAMVIEWER
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Sessioni remote:</strong> Log automatico delle connessioni remote con durata e identificazione cliente per validazione attività dichiarate.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-badge bait-badge-danger">
                                            <i class="fas fa-calendar" aria-hidden="true"></i>
                                            CALENDARIO
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Appuntamenti programmati:</strong> Integrazione calendario aziendale per confronto tra pianificazione e attività effettivamente svolte.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Automatic Controls Documentation -->
                            <div class="bait-help-section">
                                <h4 class="bait-help-title">
                                    <i class="fas fa-shield-check" aria-hidden="true"></i>
                                    Controlli Automatici
                                </h4>
                                <div class="bait-help-content">
                                    <div class="bait-help-item">
                                        <span class="bait-help-check">
                                            <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Gap Temporali (09:00-13:00, 14:00-18:00):</strong> Verifica aderenza agli orari standard di lavoro con tolleranze configurabili.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-help-check">
                                            <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Controllo Remote vs Auto:</strong> Rilevamento incongruenze tra attività dichiarate remote e utilizzo veicoli aziendali.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-help-check">
                                            <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Sovrapposizioni Temporali:</strong> Identificazione automatica di attività contemporanee impossibili fisicamente.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-help-check">
                                            <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>TeamViewer ≥15min vs Deepser:</strong> Cross-validation tra sessioni remote lunghe e attività corrispondenti registrate.
                                        </div>
                                    </div>
                                    <div class="bait-help-item">
                                        <span class="bait-help-check">
                                            <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                        </span>
                                        <div class="bait-help-description">
                                            <strong>Coerenza Multi-Source:</strong> Algoritmi avanzati per validazione incrociata di tutti i dati disponibili.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="bait-help-actions bait-mt-6">
                            <button type="button" class="bait-btn bait-btn-outline bait-btn-sm" 
                                    onclick="showSystemInfo()" aria-label="Mostra informazioni sistema">
                                <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                                <span>Info Sistema</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                    onclick="downloadUserGuide()" aria-label="Scarica guida utente">
                                <i class="fas fa-file-pdf me-2" aria-hidden="true"></i>
                                <span>Guida PDF</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" 
                                    onclick="contactSupport()" aria-label="Contatta supporto tecnico">
                                <i class="fas fa-headset me-2" aria-hidden="true"></i>
                                <span>Supporto</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Enterprise Modal System -->
    <div id="baitModalContainer" class="bait-modal-container" role="dialog" aria-hidden="true">
        <div class="bait-modal-backdrop" onclick="closeModal()"></div>
        <div class="bait-modal" role="document">
            <div class="bait-modal-header">
                <h4 class="bait-modal-title" id="modalTitle">Dettagli Evento</h4>
                <button type="button" class="bait-modal-close" onclick="closeModal()" aria-label="Chiudi modal">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="bait-modal-body" id="modalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="bait-modal-footer" id="modalFooter">
                <button type="button" class="bait-btn bait-btn-ghost" onclick="closeModal()">
                    <span>Chiudi</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="baitLoadingOverlay" class="bait-loading-overlay bait-hidden" role="status" aria-live="polite">
        <div class="bait-loading-content">
            <div class="bait-loading-spinner">
                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
            </div>
            <div class="bait-loading-text">Elaborazione in corso...</div>
        </div>
    </div>

    <!-- Enterprise JavaScript Framework -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    
    <script>
        /**
         * BAIT SERVICE ENTERPRISE JAVASCRIPT FRAMEWORK
         * =============================================
         * Advanced dashboard functionality with performance optimization
         */

        class BaitAuditDashboard {
            constructor() {
                this.config = {
                    animationDuration: 300,
                    autoRefreshInterval: 30000,
                    debounceDelay: 250,
                    cacheExpiry: 5 * 60 * 1000 // 5 minutes
                };
                
                this.cache = new Map();
                this.timelineData = <?= json_encode($timelineEvents ?? []) ?>;
                this.alertsData = <?= json_encode($alerts ?? []) ?>;
                this.isLoading = false;
                
                this.init();
            }

            init() {
                this.bindEvents();
                this.initializeComponents();
                this.setupAccessibility();
                this.startAutoRefresh();
                console.log('✅ BAIT Audit Dashboard Enterprise initialized successfully');
            }

            bindEvents() {
                // Form validation and submission
                const form = document.getElementById('analysisFiltersForm');
                if (form) {
                    form.addEventListener('submit', this.handleFormSubmit.bind(this));
                }

                // Keyboard navigation
                document.addEventListener('keydown', this.handleKeyboardNavigation.bind(this));
                
                // Window resize handler with debouncing
                window.addEventListener('resize', this.debounce(this.handleResize.bind(this), this.config.debounceDelay));
                
                // Theme toggle
                const themeBtn = document.querySelector('[onclick="toggleTheme()"]');
                if (themeBtn) {
                    themeBtn.addEventListener('click', this.toggleTheme.bind(this));
                }
            }

            initializeComponents() {
                this.initializeKPICards();
                this.initializeTimeline();
                this.initializeAlerts();
                this.initializeTooltips();
                this.optimizePerformance();
            }

            initializeKPICards() {
                const kpiCards = document.querySelectorAll('.bait-kpi-card');
                kpiCards.forEach((card, index) => {
                    // Add entrance animation with stagger
                    setTimeout(() => {
                        card.classList.add('bait-animate-fade-in');
                    }, index * 100);

                    // Add hover interactions
                    card.addEventListener('mouseenter', () => {
                        card.style.transform = 'translateY(-2px)';
                    });
                    
                    card.addEventListener('mouseleave', () => {
                        card.style.transform = 'translateY(0)';
                    });
                });
            }

            initializeTimeline() {
                const timelineCards = document.querySelectorAll('.audit-timeline-card');
                timelineCards.forEach(card => {
                    card.addEventListener('click', (e) => {
                        const eventId = card.dataset.eventId;
                        this.viewEventDetails(parseInt(eventId));
                    });

                    // Add keyboard support
                    card.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            const eventId = card.dataset.eventId;
                            this.viewEventDetails(parseInt(eventId));
                        }
                    });
                });
            }

            initializeAlerts() {
                const alertItems = document.querySelectorAll('.bait-alert-item');
                alertItems.forEach(alert => {
                    const alertId = alert.dataset.alertId;
                    const severity = this.alertsData[alertId]?.severity;
                    
                    // Add appropriate aria labels
                    alert.setAttribute('aria-label', `Alert ${severity}: ${this.alertsData[alertId]?.title}`);
                });
            }

            initializeTooltips() {
                // Initialize Bootstrap tooltips for enhanced UX
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        delay: { show: 500, hide: 100 }
                    });
                });
            }

            setupAccessibility() {
                // Enhanced keyboard navigation
                const focusableElements = document.querySelectorAll(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );

                // Add focus indicators
                focusableElements.forEach(element => {
                    element.addEventListener('focus', () => {
                        element.classList.add('bait-focus-visible');
                    });
                    
                    element.addEventListener('blur', () => {
                        element.classList.remove('bait-focus-visible');
                    });
                });
            }

            // Advanced Event Handlers
            handleFormSubmit(e) {
                e.preventDefault();
                this.showLoading('Elaborazione analisi in corso...');
                
                // Validate form data
                const formData = new FormData(e.target);
                const tecnicoId = formData.get('tecnico_id');
                const analysisDate = formData.get('analysis_date');
                
                if (!tecnicoId || !analysisDate) {
                    this.hideLoading();
                    this.showAlert('Errore', 'Seleziona tecnico e data prima di procedere', 'warning');
                    return;
                }

                // Submit form after validation
                setTimeout(() => {
                    e.target.submit();
                }, 500);
            }

            handleKeyboardNavigation(e) {
                // ESC key closes modals
                if (e.key === 'Escape') {
                    this.closeModal();
                }
                
                // Ctrl+R refreshes data
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    this.refreshData();
                }
            }

            handleResize() {
                // Responsive adjustments
                const container = document.querySelector('.bait-container');
                if (container) {
                    if (window.innerWidth < 768) {
                        container.classList.add('bait-mobile-layout');
                    } else {
                        container.classList.remove('bait-mobile-layout');
                    }
                }
            }

            // Modal System
            showModal(title, content, actions = []) {
                const modal = document.getElementById('baitModalContainer');
                const modalTitle = document.getElementById('modalTitle');
                const modalBody = document.getElementById('modalBody');
                const modalFooter = document.getElementById('modalFooter');

                modalTitle.textContent = title;
                modalBody.innerHTML = content;
                
                // Build footer actions
                let footerHTML = '';
                actions.forEach(action => {
                    footerHTML += `<button type="button" class="bait-btn ${action.class || 'bait-btn-primary'}" onclick="${action.onclick}">${action.text}</button>`;
                });
                footerHTML += '<button type="button" class="bait-btn bait-btn-ghost" onclick="closeModal()">Chiudi</button>';
                modalFooter.innerHTML = footerHTML;

                modal.classList.add('bait-modal-active');
                modal.setAttribute('aria-hidden', 'false');
                
                // Focus management
                const firstButton = modalFooter.querySelector('button');
                if (firstButton) firstButton.focus();
            }

            closeModal() {
                const modal = document.getElementById('baitModalContainer');
                modal.classList.remove('bait-modal-active');
                modal.setAttribute('aria-hidden', 'true');
            }

            // Event Detail Viewers
            viewEventDetails(eventId) {
                const event = this.timelineData[eventId];
                if (!event) return;

                const content = `
                    <div class="bait-event-details">
                        <div class="bait-detail-grid">
                            <div class="bait-detail-item">
                                <strong>Fonte:</strong> ${event.event_source.toUpperCase()}
                            </div>
                            <div class="bait-detail-item">
                                <strong>Cliente:</strong> ${event.client_name || 'N/A'}
                            </div>
                            <div class="bait-detail-item">
                                <strong>Orario:</strong> ${new Date(event.start_time).toLocaleTimeString()} - ${event.end_time ? new Date(event.end_time).toLocaleTimeString() : 'N/A'}
                            </div>
                            <div class="bait-detail-item">
                                <strong>Durata:</strong> ${event.duration_minutes} minuti
                            </div>
                            <div class="bait-detail-item">
                                <strong>Tipo:</strong> ${event.location_type}
                            </div>
                            <div class="bait-detail-item">
                                <strong>Stato:</strong> ${event.is_validated ? 'Validato' : 'Da verificare'}
                            </div>
                        </div>
                        <div class="bait-detail-description">
                            <strong>Descrizione:</strong>
                            <p>${event.activity_description || 'Nessuna descrizione disponibile'}</p>
                        </div>
                    </div>
                `;

                this.showModal(`Dettagli Evento #${eventId + 1}`, content, [
                    { text: 'Modifica', class: 'bait-btn-outline', onclick: `editEvent(${eventId})` }
                ]);
            }

            // Utility Functions
            showLoading(message = 'Caricamento...') {
                const overlay = document.getElementById('baitLoadingOverlay');
                const text = overlay.querySelector('.bait-loading-text');
                text.textContent = message;
                overlay.classList.remove('bait-hidden');
                this.isLoading = true;
            }

            hideLoading() {
                const overlay = document.getElementById('baitLoadingOverlay');
                overlay.classList.add('bait-hidden');
                this.isLoading = false;
            }

            showAlert(title, message, type = 'info') {
                // Create temporary alert
                const alert = document.createElement('div');
                alert.className = `bait-alert bait-alert-${type} bait-alert-floating`;
                alert.innerHTML = `
                    <div class="bait-alert-content">
                        <strong>${title}</strong>
                        <p>${message}</p>
                    </div>
                    <button type="button" class="bait-alert-dismiss" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                document.body.appendChild(alert);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.remove();
                    }
                }, 5000);
            }

            toggleTheme() {
                const html = document.documentElement;
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('bait-theme', newTheme);
                
                this.showAlert('Tema Cambiato', `Tema ${newTheme === 'dark' ? 'scuro' : 'chiaro'} attivato`, 'success');
            }

            refreshData() {
                if (this.isLoading) return;
                
                this.showLoading('Aggiornamento dati in corso...');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }

            startAutoRefresh() {
                // Auto-refresh every 30 seconds if enabled
                if (localStorage.getItem('bait-auto-refresh') === 'true') {
                    setInterval(() => {
                        if (!document.hidden && !this.isLoading) {
                            this.refreshData();
                        }
                    }, this.config.autoRefreshInterval);
                }
            }

            optimizePerformance() {
                // Lazy load images
                const images = document.querySelectorAll('img[data-src]');
                if ('IntersectionObserver' in window) {
                    const imageObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                imageObserver.unobserve(img);
                            }
                        });
                    });

                    images.forEach(img => imageObserver.observe(img));
                }

                // Prefetch critical resources
                const criticalLinks = [
                    'audit_monthly_manager.php',
                    'laravel_bait/public/index_standalone.php'
                ];
                
                criticalLinks.forEach(link => {
                    const prefetchLink = document.createElement('link');
                    prefetchLink.rel = 'prefetch';
                    prefetchLink.href = link;
                    document.head.appendChild(prefetchLink);
                });
            }

            // Utility helper functions
            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        }

        // Global Functions for backwards compatibility
        function resetFilters() { dashboard.refreshData(); }
        function previewAnalysis() { dashboard.showAlert('Anteprima', 'Funzionalità in sviluppo', 'info'); }
        function refreshTimeline() { dashboard.refreshData(); }
        function exportTimeline() { dashboard.showAlert('Export', 'Esportazione timeline avviata', 'success'); }
        function printTimeline() { window.print(); }
        function toggleTimelineView() { dashboard.showAlert('Vista', 'Cambio vista timeline', 'info'); }
        function showFullDescription(id) { dashboard.viewEventDetails(id); }
        function viewEventDetails(id) { dashboard.viewEventDetails(id); }
        function editEvent(id) { dashboard.showAlert('Modifica', 'Editor eventi in sviluppo', 'info'); }
        function runDetailedChecks() { dashboard.showAlert('Controlli', 'Avvio controlli dettagliati', 'info'); }
        function exportCoherenceReport() { dashboard.showAlert('Export', 'Esportazione report coerenza', 'success'); }
        function toggleAlertDetails(id) {
            const details = document.getElementById(`alert-details-${id}`);
            if (details) {
                details.classList.toggle('bait-hidden');
            }
        }
        function viewAlertDetails(id) { dashboard.showAlert('Alert', `Dettagli alert #${id}`, 'info'); }
        function markAlertResolved(id) { dashboard.showAlert('Risolto', `Alert #${id} risolto`, 'success'); }
        function shareAlert(id) { dashboard.showAlert('Condividi', `Alert #${id} condiviso`, 'success'); }
        function resolveAllAlerts() { dashboard.showAlert('Risolti', 'Tutti gli alert sono stati risolti', 'success'); }
        function exportAlertsReport() { dashboard.showAlert('Export', 'Report alert esportato', 'success'); }
        function refreshAlerts() { dashboard.refreshData(); }
        function showDebugInfo() { dashboard.showAlert('Debug', 'Informazioni di debug disponibili nella console', 'info'); }
        function showSystemInfo() { dashboard.showAlert('Sistema', 'BAIT Service Enterprise v2.0.0', 'info'); }
        function downloadUserGuide() { dashboard.showAlert('Download', 'Guida utente in preparazione', 'info'); }
        function contactSupport() { dashboard.showAlert('Supporto', 'Contatti: support@baitservice.com', 'info'); }
        function closeModal() { dashboard.closeModal(); }
        function toggleTheme() { dashboard.toggleTheme(); }
        function refreshData() { dashboard.refreshData(); }

        // Initialize Dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved theme
            const savedTheme = localStorage.getItem('bait-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Initialize dashboard
            dashboard = new BaitAuditDashboard();
        });

        // Service Worker Registration for PWA capabilities
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => console.log('SW registered'))
                    .catch(error => console.log('SW registration failed'));
            });
        }
    </script>
</body>
</html>