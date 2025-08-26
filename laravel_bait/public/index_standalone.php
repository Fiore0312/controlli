<?php
/**
 * BAIT Service Enterprise Dashboard - Clean Professional Version
 * Dashboard professionale per controllo attività tecnici
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
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

// Database connection
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

// Load dashboard data
function loadDashboardData() {
    $pdo = getDatabase();
    if (!$pdo) {
        return [
            'kpis' => [
                'tecnici_attivi' => 0,
                'attivita_oggi' => 0,
                'alert_attivi' => 0,
                'coverage_percentage' => 0
            ],
            'recent_alerts' => [],
            'tecnici_stats' => []
        ];
    }
    
    try {
        // KPI principali
        $kpis = [];
        
        // Tecnici attivi
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tecnici WHERE attivo = 1");
        $kpis['tecnici_attivi'] = $stmt->fetchColumn();
        
        // Attività di oggi - controlla varie tabelle
        $attivita_oggi = 0;
        try {
            // Prova deepser_attivita
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM deepser_attivita WHERE DATE(iniziata_il) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            // Prova utilizzi_auto di oggi
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilizzi_auto WHERE DATE(data_utilizzo) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            // Prova timbrature di oggi
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT tecnico_id) FROM timbrature WHERE DATE(data) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['attivita_oggi'] = $attivita_oggi;
        
        // Alert attivi - controlla varie tabelle
        $alert_attivi = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM audit_alerts WHERE status = 'new'");
            $alert_attivi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'");
            $alert_attivi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['alert_attivi'] = $alert_attivi;
        
        // Coverage percentage - calcolo più realistico
        $tecnici_con_attivita = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT tecnico_id) FROM utilizzi_auto WHERE DATE(data_utilizzo) = CURDATE()");
            $stmt->execute();
            $tecnici_con_attivita = $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['coverage_percentage'] = $kpis['tecnici_attivi'] > 0 ? 
            round(($tecnici_con_attivita / $kpis['tecnici_attivi']) * 100) : 0;
        
        // Alert recenti (ultimi 10)
        $stmt = $pdo->prepare("
            SELECT aa.*, t.nome_completo as tecnico_nome,
                   DATE(aa.created_at) as alert_date,
                   TIME(aa.created_at) as alert_time
            FROM audit_alerts aa
            LEFT JOIN technician_daily_analysis tda ON aa.daily_analysis_id = tda.id
            LEFT JOIN tecnici t ON tda.tecnico_id = t.id
            WHERE aa.status = 'new'
            ORDER BY aa.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recent_alerts = $stmt->fetchAll();
        
        // Stats per tecnico
        $stmt = $pdo->prepare("
            SELECT t.nome_completo, t.id,
                   COUNT(tda.id) as analisi_count,
                   AVG(tda.quality_score) as avg_quality,
                   COUNT(aa.id) as alert_count
            FROM tecnici t
            LEFT JOIN technician_daily_analysis tda ON t.id = tda.tecnico_id AND DATE(tda.created_at) >= CURDATE() - INTERVAL 7 DAY
            LEFT JOIN audit_alerts aa ON tda.id = aa.daily_analysis_id AND aa.status = 'new'
            WHERE t.attivo = 1
            GROUP BY t.id, t.nome_completo
            ORDER BY t.nome_completo
        ");
        $stmt->execute();
        $tecnici_stats = $stmt->fetchAll();
        
        return [
            'kpis' => $kpis,
            'recent_alerts' => $recent_alerts,
            'tecnici_stats' => $tecnici_stats
        ];
        
    } catch (Exception $e) {
        error_log("Error loading dashboard data: " . $e->getMessage());
        return [
            'kpis' => [
                'tecnici_attivi' => 0,
                'attivita_oggi' => 0,
                'alert_attivi' => 0,
                'coverage_percentage' => 0
            ],
            'recent_alerts' => [],
            'tecnici_stats' => []
        ];
    }
}

// Utility functions
function getSeverityBadge($severity) {
    $classes = [
        'critical' => 'bg-danger',
        'high' => 'bg-warning',
        'medium' => 'bg-info',
        'low' => 'bg-secondary'
    ];
    return $classes[$severity] ?? 'bg-secondary';
}

function getQualityBadge($score) {
    if ($score >= 90) return 'bg-success';
    if ($score >= 75) return 'bg-info';
    if ($score >= 60) return 'bg-warning';
    return 'bg-danger';
}

// Load data
$data = loadDashboardData();
$isConnected = getDatabase() !== null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT Service - Dashboard Enterprise</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bait-primary: #2563eb;
            --bait-secondary: #64748b;
            --bait-success: #059669;
            --bait-warning: #d97706;
            --bait-danger: #dc2626;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--bait-primary) !important;
        }
        
        .kpi-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            height: 100%;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .section-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .alert-item {
            padding: 1rem;
            border: none;
            border-left: 4px solid;
            border-radius: 0 8px 8px 0;
            margin-bottom: 0.5rem;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        
        .alert-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }
        
        .alert-critical { border-left-color: var(--bait-danger); }
        .alert-high { border-left-color: var(--bait-warning); }
        .alert-medium { border-left-color: var(--bait-primary); }
        .alert-low { border-left-color: var(--bait-secondary); }
        
        .tech-stat {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        
        .tech-stat:hover {
            background: #f1f5f9;
            border-color: var(--bait-primary);
        }
        
        .status-online {
            color: var(--bait-success);
        }
        
        .status-offline {
            color: var(--bait-secondary);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi bi-shield-check me-2"></i>
                BAIT Service Enterprise
            </a>
            
            <div class="d-flex align-items-center">
                <span class="badge <?= $isConnected ? 'bg-success' : 'bg-warning' ?> me-3">
                    <i class="bi bi-database me-1"></i>
                    <?= $isConnected ? 'Database Online' : 'Database Offline' ?>
                </span>
                
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Status Alert -->
        <?php if (!$isConnected): ?>
        <div class="alert alert-warning mb-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> Impossibile connettersi al database. Verificare che il database 'bait_service_real' sia disponibile.
        </div>
        <?php endif; ?>
        
        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="kpi-icon bg-primary me-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1">Tecnici Attivi</h6>
                                <h3 class="mb-0"><?= number_format($data['kpis']['tecnici_attivi']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="kpi-icon bg-success me-3">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1">Attività Oggi</h6>
                                <h3 class="mb-0"><?= number_format($data['kpis']['attivita_oggi']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="kpi-icon bg-warning me-3">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1">Alert Attivi</h6>
                                <h3 class="mb-0"><?= number_format($data['kpis']['alert_attivi']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="kpi-icon bg-info me-3">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1">Coverage</h6>
                                <h3 class="mb-0"><?= $data['kpis']['coverage_percentage'] ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card section-card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning text-primary me-2"></i>
                            Azioni Rapide
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/utilizzo_auto.php" target="_blank" class="btn btn-outline-success w-100 py-3">
                                    <i class="bi bi-car-front fs-4 d-block mb-1"></i>
                                    <small>Auto</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/attivita_deepser.php" target="_blank" class="btn btn-outline-primary w-100 py-3">
                                    <i class="bi bi-briefcase fs-4 d-block mb-1"></i>
                                    <small>Attività</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/richieste_permessi.php" target="_blank" class="btn btn-outline-danger w-100 py-3">
                                    <i class="bi bi-calendar-check fs-4 d-block mb-1"></i>
                                    <small>Permessi</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/timbrature.php" target="_blank" class="btn btn-outline-info w-100 py-3">
                                    <i class="bi bi-clock fs-4 d-block mb-1"></i>
                                    <small>Timbrature</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/sessioni_teamviewer.php" target="_blank" class="btn btn-outline-warning w-100 py-3">
                                    <i class="bi bi-display fs-4 d-block mb-1"></i>
                                    <small>TeamViewer</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/calendario.php" target="_blank" class="btn btn-outline-dark w-100 py-3">
                                    <i class="bi bi-calendar fs-4 d-block mb-1"></i>
                                    <small>Calendario</small>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Seconda riga con i 4 bottoni audit + AI -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/audit_tecnico_dashboard.php" target="_blank" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="bi bi-person-check fs-4 d-block mb-1"></i>
                                    <small>Audit Tecnico</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/audit_monthly_manager.php" target="_blank" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="bi bi-calendar3 fs-4 d-block mb-1"></i>
                                    <small>Audit Mensile</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/bait_incongruenze_manager.php" target="_blank" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="bi bi-exclamation-triangle fs-4 d-block mb-1"></i>
                                    <small>Incongruenze</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/bait_ai_chat.php" target="_blank" class="btn btn-outline-secondary w-100 py-3">
                                    <i class="bi bi-robot fs-4 d-block mb-1"></i>
                                    <small>AI Chat</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="/controlli/index.php" target="_blank" class="btn btn-outline-info w-100 py-3">
                                    <i class="bi bi-cloud-upload fs-4 d-block mb-1"></i>
                                    <small>Carica File</small>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="btn btn-outline-primary w-100 py-3" style="cursor: pointer;" onclick="toggleDateFilter()">
                                    <i class="bi bi-calendar-range fs-4 d-block mb-1"></i>
                                    <small>Filtro Date</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Date Filter Panel (Hidden by default) -->
        <div class="row mb-4" id="dateFilterPanel" style="display: none;">
            <div class="col-12">
                <div class="card section-card">
                    <div class="card-header bg-primary text-white border-bottom-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-range me-2"></i>
                            Filtro Date per Report Dinamici
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Data Inizio</label>
                                <input type="date" id="startDate" class="form-control form-control-lg" 
                                       value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Data Fine</label>
                                <input type="date" id="endDate" class="form-control form-control-lg" 
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-lg w-100" onclick="applyDateFilter()">
                                    <i class="bi bi-filter me-1"></i>Applica
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-secondary btn-lg w-100" onclick="resetDateFilter()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </button>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <div class="badge bg-primary fs-6 px-3 py-2" id="dateRangeDisplay">
                                        Ultimi 30 giorni
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Date Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(1)">Oggi</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(7)">Ultimi 7 giorni</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(30)">Ultimi 30 giorni</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(90)">Ultimi 3 mesi</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setCurrentMonth()">Mese corrente</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Alert Recenti -->
            <div class="col-lg-6">
                <div class="card section-card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-bell text-warning me-2"></i>
                            Alert Recenti
                            <span class="badge bg-secondary ms-2" id="alertDateRange">Tutti</span>
                        </h5>
                    </div>
                    <div class="card-body" id="alertRecentiContent">
                        <?php if (empty($data['recent_alerts'])): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-3 mb-2"></i>
                            <p class="mb-0">Nessun alert attivo</p>
                            <small>Sistema funzionando regolarmente</small>
                        </div>
                        <?php else: ?>
                        <div class="overflow-auto" style="max-height: 400px;">
                            <?php foreach ($data['recent_alerts'] as $alert): ?>
                            <div class="alert-item alert-<?= strtolower($alert['severita']) ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($alert['titolo']) ?></h6>
                                        <p class="mb-1 small text-muted"><?= htmlspecialchars($alert['descrizione']) ?></p>
                                        <div class="d-flex align-items-center small text-muted">
                                            <i class="bi bi-person me-1"></i>
                                            <?= htmlspecialchars($alert['tecnico_nome'] ?? 'Sistema') ?>
                                            <span class="mx-2">•</span>
                                            <i class="bi bi-clock me-1"></i>
                                            <?= $alert['alert_time'] ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= getSeverityBadge($alert['severita']) ?>">
                                        <?= ucfirst($alert['severita']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Statistiche Tecnici -->
            <div class="col-lg-6">
                <div class="card section-card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-people text-primary me-2"></i>
                            Statistiche Tecnici
                            <span class="badge bg-secondary ms-2" id="statsDateRange">Tutti</span>
                        </h5>
                    </div>
                    <div class="card-body" id="statisticheTecniciContent">
                        <div class="overflow-auto" style="max-height: 400px;">
                            <?php foreach ($data['tecnici_stats'] as $tecnico): ?>
                            <div class="tech-stat mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($tecnico['nome_completo']) ?></h6>
                                        <div class="d-flex align-items-center small text-muted">
                                            <span>Analisi: <?= $tecnico['analisi_count'] ?></span>
                                            <span class="mx-2">•</span>
                                            <span>Alert: <?= $tecnico['alert_count'] ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($tecnico['avg_quality']): ?>
                                        <span class="badge <?= getQualityBadge($tecnico['avg_quality']) ?>">
                                            <?= round($tecnico['avg_quality']) ?>%
                                        </span>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1">
                                            <a href="/controlli/audit_tecnico_dashboard.php?tecnico=<?= $tecnico['id'] ?>" 
                                               class="text-decoration-none">
                                                <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh ogni 30 secondi (disabilitato quando il filtro è attivo)
        let autoRefreshEnabled = true;
        let autoRefreshTimer;
        
        function startAutoRefresh() {
            if (autoRefreshEnabled) {
                autoRefreshTimer = setTimeout(() => {
                    location.reload();
                }, 30000);
            }
        }
        
        function stopAutoRefresh() {
            if (autoRefreshTimer) {
                clearTimeout(autoRefreshTimer);
                autoRefreshTimer = null;
            }
        }
        
        // Toggle del pannello filtro date
        function toggleDateFilter() {
            const panel = document.getElementById('dateFilterPanel');
            const isVisible = panel.style.display !== 'none';
            
            if (isVisible) {
                panel.style.display = 'none';
                autoRefreshEnabled = true;
                startAutoRefresh();
            } else {
                panel.style.display = 'block';
                autoRefreshEnabled = false;
                stopAutoRefresh();
            }
        }
        
        // Imposta date rapide
        function setQuickDate(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            
            updateDateRangeDisplay();
        }
        
        // Imposta mese corrente
        function setCurrentMonth() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            
            document.getElementById('startDate').value = start.toISOString().split('T')[0];
            document.getElementById('endDate').value = end.toISOString().split('T')[0];
            
            updateDateRangeDisplay();
        }
        
        // Aggiorna display intervallo date
        function updateDateRangeDisplay() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const display = document.getElementById('dateRangeDisplay');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                display.textContent = `${diffDays + 1} giorni`;
            }
        }
        
        // Applica filtro date
        function applyDateFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('Seleziona entrambe le date');
                return;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                alert('La data di inizio deve essere precedente alla data di fine');
                return;
            }
            
            // Mostra loading
            showLoadingSpinner();
            
            // Aggiorna badge date
            const startFormatted = new Date(startDate).toLocaleDateString('it-IT');
            const endFormatted = new Date(endDate).toLocaleDateString('it-IT');
            const dateRange = `${startFormatted} - ${endFormatted}`;
            
            document.getElementById('alertDateRange').textContent = dateRange;
            document.getElementById('statsDateRange').textContent = dateRange;
            
            // Chiamata AJAX per aggiornare i dati
            fetch(`dashboard_api.php?action=filter&start=${startDate}&end=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    updateAlertRecenti(data.alerts || []);
                    updateStatisticheTecnici(data.stats || []);
                    hideLoadingSpinner();
                })
                .catch(error => {
                    console.error('Errore nel caricamento dati filtrati:', error);
                    hideLoadingSpinner();
                    alert('Errore nel caricamento dei dati filtrati');
                });
        }
        
        // Reset filtro date
        function resetDateFilter() {
            // Reset alle date default
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - 30);
            
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            document.getElementById('dateRangeDisplay').textContent = 'Ultimi 30 giorni';
            
            // Reset badge
            document.getElementById('alertDateRange').textContent = 'Tutti';
            document.getElementById('statsDateRange').textContent = 'Tutti';
            
            // Ricarica la pagina per tornare ai dati non filtrati
            location.reload();
        }
        
        // Mostra spinner di caricamento
        function showLoadingSpinner() {
            const alertContent = document.getElementById('alertRecentiContent');
            const statsContent = document.getElementById('statisticheTecniciContent');
            
            const spinner = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-2 text-muted">Aggiornamento dati in corso...</p>
                </div>
            `;
            
            alertContent.innerHTML = spinner;
            statsContent.innerHTML = spinner;
        }
        
        // Nascondi spinner
        function hideLoadingSpinner() {
            // Lo spinner verrà sostituito dai dati aggiornati
        }
        
        // Aggiorna sezione Alert Recenti
        function updateAlertRecenti(alerts) {
            const content = document.getElementById('alertRecentiContent');
            
            if (alerts.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-3 mb-2"></i>
                        <p class="mb-0">Nessun alert nel periodo selezionato</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            alerts.forEach(alert => {
                const priorityClass = {
                    'CRITICO': 'alert-critical',
                    'ALTO': 'alert-high',
                    'MEDIO': 'alert-medium',
                    'BASSO': 'alert-low'
                }[alert.priority] || 'alert-medium';
                
                html += `
                    <div class="alert-item ${priorityClass}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${alert.title || 'Alert'}</h6>
                                <p class="mb-1 text-muted small">${alert.description || ''}</p>
                                <small class="text-muted">
                                    <i class="bi bi-calendar me-1"></i>${alert.date || ''}
                                    <i class="bi bi-person-fill ms-2 me-1"></i>${alert.tecnico || ''}
                                </small>
                            </div>
                            <span class="badge bg-${alert.priority === 'CRITICO' ? 'danger' : alert.priority === 'ALTO' ? 'warning' : 'primary'} ms-2">
                                ${alert.priority || 'MEDIO'}
                            </span>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }
        
        // Aggiorna sezione Statistiche Tecnici
        function updateStatisticheTecnici(stats) {
            const content = document.getElementById('statisticheTecniciContent');
            
            if (stats.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-people-fill fs-3 mb-2"></i>
                        <p class="mb-0">Nessuna statistica nel periodo selezionato</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="overflow-auto" style="max-height: 400px;">';
            stats.forEach(tecnico => {
                html += `
                    <div class="tech-stat mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${tecnico.nome_completo || 'Tecnico'}</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <small class="text-muted d-block">Ore</small>
                                        <strong class="text-primary">${tecnico.ore_lavorate || 0}</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Attività</small>
                                        <strong class="text-success">${tecnico.numero_attivita || 0}</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Clienti</small>
                                        <strong class="text-info">${tecnico.clienti_unici || 0}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="status-${tecnico.status || 'offline'}">
                                    <i class="bi bi-circle-fill"></i>
                                </span>
                                <div class="mt-1">
                                    <small class="text-muted">Efficienza</small>
                                    <div><strong class="text-primary">${tecnico.efficienza || 0}%</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            content.innerHTML = html;
        }
        
        // Event listeners per aggiornamento automatico display date
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            console.log('Dashboard caricata alle:', now.toLocaleString('it-IT'));
            
            // Event listeners per i campi data
            document.getElementById('startDate').addEventListener('change', updateDateRangeDisplay);
            document.getElementById('endDate').addEventListener('change', updateDateRangeDisplay);
            
            updateDateRangeDisplay();
            startAutoRefresh();
        });
    </script>
</body>
</html>