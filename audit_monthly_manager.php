<?php
/**
 * AUDIT MONTHLY MANAGER - Sistema Caricamento CSV Mensile Progressivo
 * Gestisce dashboard audit dal 1° al 31 del mese con archiviazione automatica
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
$currentMonth = date('Y-m');
$currentDay = date('j');
$uploadResult = null;
$analysisResult = null;
$monthlyStats = null;
$error = null;

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    // Handle CSV upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
        $uploadResult = handleCSVUpload($pdo);
    }

    // Handle full month analysis
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze_month'])) {
        $analysisResult = analyzeFullMonth($pdo);
    }

    // Get monthly statistics
    $monthlyStats = getMonthlyStatistics($pdo, $currentMonth);

} catch (Exception $e) {
    $error = $e->getMessage();
}

/**
 * Gestisce caricamento CSV quotidiano
 */
function handleCSVUpload($pdo) {
    $results = [
        'success' => false,
        'files_processed' => 0,
        'errors' => [],
        'details' => []
    ];

    $uploadDir = __DIR__ . '/data/input/';
    
    // Verifica che la directory esista
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Lista dei file CSV attesi
    $expectedFiles = ['attivita.csv', 'timbrature.csv', 'teamviewer_bait.csv', 'teamviewer_gruppo.csv', 'permessi.csv', 'auto.csv'];
    
    foreach ($expectedFiles as $fileName) {
        $fileKey = str_replace('.csv', '', $fileName);
        
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES[$fileKey]['tmp_name'];
            $destination = $uploadDir . $fileName;
            
            // Backup file precedente se esiste
            if (file_exists($destination)) {
                $backupName = $uploadDir . 'backup_' . date('Y-m-d_H-i-s') . '_' . $fileName;
                rename($destination, $backupName);
            }
            
            // Carica nuovo file
            if (move_uploaded_file($tmpName, $destination)) {
                $results['files_processed']++;
                $results['details'][] = "✅ $fileName caricato con successo";
                
                // Log upload nel database
                logFileUpload($pdo, $fileName, filesize($destination));
                
            } else {
                $results['errors'][] = "❌ Errore caricamento $fileName";
            }
        }
    }
    
    if ($results['files_processed'] > 0) {
        $results['success'] = true;
        
        // Aggiorna sessione audit corrente
        updateAuditSession($pdo);
    }
    
    return $results;
}

/**
 * Analizza tutto il mese corrente
 */
function analyzeFullMonth($pdo) {
    $analyzer = new TechnicianAnalyzer($pdo);
    $results = [
        'success' => false,
        'technicians_analyzed' => 0,
        'days_analyzed' => 0,
        'total_alerts' => 0,
        'average_score' => 0,
        'details' => []
    ];

    try {
        // Ottieni lista tecnici attivi
        $technicians = $pdo->query("SELECT id, nome_completo FROM tecnici WHERE attivo = 1")->fetchAll();
        
        // Ottieni giorni del mese corrente da analizzare
        $currentMonth = date('Y-m');
        $currentDay = date('j');
        $daysToAnalyze = [];
        
        for ($day = 1; $day <= $currentDay; $day++) {
            $daysToAnalyze[] = date('Y-m-d', mktime(0, 0, 0, date('n'), $day, date('Y')));
        }
        
        $totalScore = 0;
        $totalAnalyses = 0;
        $totalAlerts = 0;
        
        foreach ($technicians as $technician) {
            foreach ($daysToAnalyze as $date) {
                // Verifica se l'analisi esiste già
                $stmt = $pdo->prepare("
                    SELECT id FROM technician_daily_analysis 
                    WHERE tecnico_id = ? AND data_analisi = ?
                ");
                $stmt->execute([$technician['id'], $date]);
                
                if (!$stmt->fetchColumn()) {
                    // Esegui analisi
                    $analysisResult = $analyzer->analyzeTechnicianDay($technician['id'], $date);
                    
                    if ($analysisResult['success']) {
                        $totalScore += $analysisResult['quality_score'];
                        $totalAnalyses++;
                        
                        // Conta alert per questo tecnico/giorno
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM audit_alerts aa
                            JOIN technician_daily_analysis tda ON aa.daily_analysis_id = tda.id
                            WHERE tda.tecnico_id = ? AND tda.data_analisi = ? AND aa.status = 'new'
                        ");
                        $stmt->execute([$technician['id'], $date]);
                        $alerts = $stmt->fetchColumn();
                        $totalAlerts += $alerts;
                        
                        $results['details'][] = "✅ {$technician['nome_completo']} - $date: Score {$analysisResult['quality_score']}%";
                    } else {
                        $results['details'][] = "❌ {$technician['nome_completo']} - $date: Errore analisi";
                    }
                }
            }
        }
        
        $results['technicians_analyzed'] = count($technicians);
        $results['days_analyzed'] = count($daysToAnalyze);
        $results['total_alerts'] = $totalAlerts;
        $results['average_score'] = $totalAnalyses > 0 ? round($totalScore / $totalAnalyses, 1) : 0;
        $results['success'] = true;
        
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Ottieni statistiche mensili
 */
function getMonthlyStatistics($pdo, $monthYear) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT tda.tecnico_id) as unique_technicians,
            COUNT(DISTINCT tda.data_analisi) as analysis_days,
            COUNT(tda.id) as total_analyses,
            AVG(tda.quality_score) as avg_quality_score,
            SUM(tda.total_alerts) as total_alerts,
            COUNT(CASE WHEN tda.quality_score >= 90 THEN 1 END) as excellent_days,
            COUNT(CASE WHEN tda.quality_score < 50 THEN 1 END) as critical_days
        FROM technician_daily_analysis tda
        JOIN audit_sessions aus ON tda.audit_session_id = aus.id
        WHERE aus.month_year = ?
    ");
    $stmt->execute([$monthYear]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistiche per tecnico
    $stmt = $pdo->prepare("
        SELECT 
            t.nome_completo,
            COUNT(tda.id) as days_analyzed,
            AVG(tda.quality_score) as avg_score,
            SUM(tda.total_alerts) as total_alerts,
            MAX(tda.quality_score) as best_score,
            MIN(tda.quality_score) as worst_score
        FROM technician_daily_analysis tda
        JOIN tecnici t ON tda.tecnico_id = t.id
        JOIN audit_sessions aus ON tda.audit_session_id = aus.id
        WHERE aus.month_year = ?
        GROUP BY tda.tecnico_id, t.nome_completo
        ORDER BY avg_score DESC
    ");
    $stmt->execute([$monthYear]);
    $stats['by_technician'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alert per categoria
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(aa.categoria, aa.alert_type, 'sconosciuta') as category,
            COUNT(*) as count,
            AVG(CASE WHEN COALESCE(aa.severita, aa.severity) = 'CRITICAL' THEN 4 
                     WHEN COALESCE(aa.severita, aa.severity) = 'ERROR' THEN 3 
                     WHEN COALESCE(aa.severita, aa.severity) = 'WARNING' THEN 2 
                     ELSE 1 END) as avg_severity_score
        FROM audit_alerts aa
        JOIN technician_daily_analysis tda ON aa.daily_analysis_id = tda.id
        JOIN audit_sessions aus ON tda.audit_session_id = aus.id
        WHERE aus.month_year = ? AND COALESCE(aa.stato_risoluzione, aa.status, 'NUOVO') = 'NUOVO'
        GROUP BY COALESCE(aa.categoria, aa.alert_type, 'sconosciuta')
        ORDER BY count DESC
    ");
    $stmt->execute([$monthYear]);
    $stats['alerts_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

/**
 * Log caricamento file
 */
function logFileUpload($pdo, $fileName, $fileSize) {
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (event_type, description, metadata, created_at)
        VALUES ('file_upload', ?, ?, NOW())
    ");
    
    $metadata = json_encode([
        'file_name' => $fileName,
        'file_size_bytes' => $fileSize,
        'upload_date' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->execute([
        "File CSV caricato: $fileName",
        $metadata
    ]);
}

/**
 * Aggiorna sessione audit corrente
 */
function updateAuditSession($pdo) {
    $currentMonth = date('Y-m');
    $currentDay = date('j');
    
    $stmt = $pdo->prepare("
        UPDATE audit_sessions 
        SET current_day = ?, updated_at = NOW()
        WHERE month_year = ? AND session_status = 'active'
    ");
    $stmt->execute([$currentDay, $currentMonth]);
    
    // Se non esiste, creala
    if ($pdo->rowCount() === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO audit_sessions (month_year, current_day, session_status)
            VALUES (?, ?, 'active')
        ");
        $stmt->execute([$currentMonth, $currentDay]);
    }
}

/**
 * Ottieni sessione audit corrente
 */
function getCurrentAuditSession($pdo) {
    $currentMonth = date('Y-m');
    
    $stmt = $pdo->prepare("
        SELECT * FROM audit_sessions 
        WHERE month_year = ? AND session_status = 'active'
    ");
    $stmt->execute([$currentMonth]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$currentSession = getCurrentAuditSession($pdo);
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 BAIT Service - Audit Mensile Enterprise</title>
    <meta name="description" content="Sistema enterprise per gestione audit mensile CSV progressivo BAIT Service">
    <meta name="author" content="BAIT Service Enterprise Team">
    
    <!-- Google Fonts - Inter for professional typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- BAIT Enterprise Design System -->
    <link href="assets/css/bait-enterprise.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Enterprise Custom Styles -->
    <style>
        /* Skip Link for Accessibility */
        .bait-skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--bait-primary);
            color: var(--text-on-primary);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            text-decoration: none;
            z-index: var(--z-tooltip);
            transition: top var(--duration-fast) var(--ease-out);
        }
        
        .bait-skip-link:focus {
            top: 6px;
        }
        
        /* Enterprise Layout Enhancements */
        .bait-enterprise-layout {
            min-height: 100vh;
            background: var(--surface-secondary);
        }
        
        .bait-upload-zone {
            border: 2px dashed var(--border-accent);
            border-radius: var(--radius-2xl);
            padding: var(--space-10);
            text-align: center;
            transition: all var(--duration-normal) var(--ease-out);
            background: var(--surface-primary);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .bait-upload-zone::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(transparent, var(--bait-primary-50), transparent 30%);
            animation: rotate 3s linear infinite;
            opacity: 0;
            transition: opacity var(--duration-normal) var(--ease-out);
        }
        
        .bait-upload-zone:hover::before {
            opacity: 1;
        }
        
        .bait-upload-zone:hover {
            border-color: var(--bait-primary);
            background: var(--bait-primary-50);
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .bait-upload-zone.dragover {
            border-color: var(--bait-success);
            background: var(--bait-success-bg);
            transform: scale(1.02);
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Enhanced File Status Items */
        .bait-file-status-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-2);
            transition: all var(--duration-fast) var(--ease-out);
            background: var(--surface-primary);
        }
        
        .bait-file-status-item:hover {
            background: var(--surface-tertiary);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        
        .bait-file-status-item.uploaded {
            border-color: var(--bait-success-border);
            background: var(--bait-success-bg);
        }
        
        .bait-file-status-item.missing {
            border-color: var(--border-secondary);
            background: var(--bait-gray-50);
            opacity: 0.7;
        }
        
        /* Progress Enhancement */
        .bait-month-progress {
            position: relative;
            background: var(--surface-primary);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            text-align: center;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .bait-month-progress::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        /* Alert Categories Enhancement */
        .bait-alert-category {
            background: var(--surface-primary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-4);
            transition: all var(--duration-fast) var(--ease-out);
            cursor: pointer;
        }
        
        .bait-alert-category:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--border-accent);
        }
        
        /* Breadcrumb Navigation */
        .bait-breadcrumb {
            background: var(--surface-primary);
            padding: var(--space-3) var(--space-6);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            margin-bottom: var(--space-6);
        }
        
        .bait-breadcrumb-item {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .bait-breadcrumb-item.active {
            color: var(--text-primary);
            font-weight: var(--font-weight-medium);
        }
        
        .bait-breadcrumb-separator {
            margin: 0 var(--space-2);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <?php 
    // Render unified navigation
    renderBaitNavigation('audit_monthly_manager', 'database'); 
    ?>

    <div class="container">
        <!-- Error Display -->
        <?php if ($error): ?>
        <div class="bait-alert bait-alert-danger" role="alert">
            <div class="bait-alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="bait-alert-content">
                <div class="bait-alert-title">Errore Sistema</div>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <button class="bait-alert-dismiss" aria-label="Chiudi alert">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Enterprise KPI Dashboard -->
        <section class="bait-dashboard-grid bait-mb-8" aria-label="Metriche principali">
            <!-- Monthly Progress KPI -->
            <div class="bait-kpi-card bait-month-progress">
                <div class="bait-kpi-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="bait-progress-circle" aria-label="Progresso mensile">
                    <svg width="80" height="80">
                        <circle class="bait-progress-circle-bg" cx="40" cy="40" r="32"></circle>
                        <circle class="bait-progress-circle-fill" cx="40" cy="40" r="32" 
                                style="stroke-dasharray: <?= round((($currentDay / date('t')) * 201), 2) ?> 201;"
                                aria-describedby="progress-text"></circle>
                    </svg>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <div class="bait-kpi-value text-primary"><?= $currentDay ?></div>
                        <small class="text-muted">/ <?= date('t') ?></small>
                    </div>
                </div>
                <div class="bait-kpi-label">Progresso Mensile</div>
                <div class="bait-kpi-trend positive" id="progress-text">
                    <i class="fas fa-arrow-up"></i>
                    <span><?= round(($currentDay / date('t')) * 100, 1) ?>% completato</span>
                </div>
            </div>
            
            <?php if ($monthlyStats): ?>
            <!-- Tecnici Analizzati KPI -->
            <div class="bait-kpi-card">
                <div class="bait-kpi-icon" style="background: var(--bait-success-bg); color: var(--bait-success);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="bait-kpi-value text-success"><?= $monthlyStats['unique_technicians'] ?? 0 ?></div>
                <div class="bait-kpi-label">Tecnici Analizzati</div>
                <div class="bait-kpi-trend positive">
                    <i class="fas fa-chart-line"></i>
                    <span><?= $monthlyStats['total_analyses'] ?? 0 ?> analisi totali</span>
                </div>
            </div>
            
            <!-- Score Qualità KPI -->
            <div class="bait-kpi-card">
                <div class="bait-kpi-icon" style="background: var(--bait-info-bg); color: var(--bait-info);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="bait-kpi-value text-info"><?= number_format($monthlyStats['avg_quality_score'] ?? 0, 1) ?>%</div>
                <div class="bait-kpi-label">Score Medio Qualità</div>
                <div class="bait-kpi-trend <?= ($monthlyStats['avg_quality_score'] ?? 0) >= 80 ? 'positive' : 'negative' ?>">
                    <i class="fas fa-<?= ($monthlyStats['avg_quality_score'] ?? 0) >= 80 ? 'thumbs-up' : 'exclamation-triangle' ?>"></i>
                    <span><?= ($monthlyStats['avg_quality_score'] ?? 0) >= 80 ? 'Ottima qualità' : 'Da migliorare' ?></span>
                </div>
            </div>
            
            <!-- Alert Totali KPI -->
            <div class="bait-kpi-card">
                <div class="bait-kpi-icon" style="background: var(--bait-warning-bg); color: var(--bait-warning);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="bait-kpi-value text-warning"><?= $monthlyStats['total_alerts'] ?? 0 ?></div>
                <div class="bait-kpi-label">Alert Generati</div>
                <div class="bait-kpi-trend <?= ($monthlyStats['critical_days'] ?? 0) > 5 ? 'negative' : 'positive' ?>">
                    <i class="fas fa-<?= ($monthlyStats['critical_days'] ?? 0) > 5 ? 'arrow-down' : 'check-circle' ?>"></i>
                    <span><?= $monthlyStats['critical_days'] ?? 0 ?> giorni critici</span>
                </div>
            </div>
            
            <!-- Giorni Eccellenti KPI -->
            <div class="bait-kpi-card">
                <div class="bait-kpi-icon" style="background: var(--bait-primary-50); color: var(--bait-primary);">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="bait-kpi-value text-primary"><?= $monthlyStats['excellent_days'] ?? 0 ?></div>
                <div class="bait-kpi-label">Giorni Eccellenti</div>
                <div class="bait-kpi-trend positive">
                    <i class="fas fa-medal"></i>
                    <span>Score ≥ 90%</span>
                </div>
            </div>
            
            <!-- Coverage Timeline KPI -->
            <div class="bait-kpi-card">
                <div class="bait-kpi-icon" style="background: var(--bait-secondary-light); color: white;">
                    <i class="fas fa-chart-area"></i>
                </div>
                <div class="bait-kpi-value"><?= $monthlyStats['analysis_days'] ?? 0 ?></div>
                <div class="bait-kpi-label">Giorni Copertura</div>
                <div class="bait-kpi-trend positive">
                    <i class="fas fa-percentage"></i>
                    <span><?= round((($monthlyStats['analysis_days'] ?? 0) / $currentDay) * 100, 1) ?>% copertura</span>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Results & Notifications Section -->
        <?php if ($uploadResult): ?>
        <div class="bait-card" role="region" aria-label="Risultati caricamento">
            <div class="bait-card-header">
                <h3 class="bait-card-title">
                    <i class="fas fa-<?= $uploadResult['success'] ? 'check-circle text-success' : 'exclamation-triangle text-danger' ?>"></i>
                    Risultati Caricamento CSV
                </h3>
            </div>
            <div class="bait-card-body">
                <?php if ($uploadResult['success']): ?>
                <div class="bait-alert bait-alert-success" role="alert">
                    <div class="bait-alert-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="bait-alert-content">
                        <div class="bait-alert-title">Caricamento Completato</div>
                        <div><?= $uploadResult['files_processed'] ?> file processati con successo. Sistema pronto per l'analisi.</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bait-alert bait-alert-danger" role="alert">
                    <div class="bait-alert-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="bait-alert-content">
                        <div class="bait-alert-title">Errore Caricamento</div>
                        <div>Verificare i file selezionati e riprovare l'operazione.</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($uploadResult['details'])): ?>
                <div class="bait-mt-4">
                    <h6 class="font-semibold mb-3">Dettagli Operazione:</h6>
                    <div class="bait-grid bait-gap-2">
                        <?php foreach ($uploadResult['details'] as $detail): ?>
                        <div class="bait-flex bait-items-center bait-gap-2 bait-p-2 rounded">
                            <i class="fas fa-info-circle text-primary"></i>
                            <span class="text-sm"><?= $detail ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($uploadResult['errors'])): ?>
                <div class="bait-mt-4">
                    <h6 class="font-semibold mb-3 text-danger">Errori Rilevati:</h6>
                    <div class="bait-grid bait-gap-2">
                        <?php foreach ($uploadResult['errors'] as $error): ?>
                        <div class="bait-flex bait-items-center bait-gap-2 bait-p-2 bg-danger-subtle rounded">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                            <span class="text-sm text-danger"><?= $error ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Analysis Results -->
        <?php if ($analysisResult): ?>
        <div class="audit-card">
            <div class="card-body">
                <?php if ($analysisResult['success']): ?>
                <div class="alert alert-success">
                    <i class="fas fa-chart-line me-2"></i>
                    <strong>Analisi completata!</strong> 
                    <?= $analysisResult['technicians_analyzed'] ?> tecnici analizzati per <?= $analysisResult['days_analyzed'] ?> giorni.
                    Score medio: <?= $analysisResult['average_score'] ?>%
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Errore analisi:</strong> <?= htmlspecialchars($analysisResult['error'] ?? 'Errore sconosciuto') ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($analysisResult['details']) && count($analysisResult['details']) <= 20): ?>
                <h6>Dettagli analisi:</h6>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($analysisResult['details'] as $detail): ?>
                    <small class="d-block"><?= $detail ?></small>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bait-grid bait-grid-lg-2 bait-gap-6">
            <!-- Enterprise CSV Upload Section -->
            <div class="bait-card" role="region" aria-label="Caricamento file CSV">
                <div class="bait-card-header">
                    <h3 class="bait-card-title">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Caricamento CSV Quotidiano
                    </h3>
                    <div class="bait-card-subtitle">
                        Sistema enterprise per processamento file giornalieri
                    </div>
                </div>
                <div class="bait-card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm" aria-label="Form caricamento CSV">
                        <!-- Enhanced Upload Zone with Drag & Drop -->
                        <div class="bait-upload-zone" id="uploadZone" role="button" tabindex="0" 
                             aria-label="Area caricamento file - trascina i file qui o clicca per selezionare"
                             ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                            <div style="position: relative; z-index: 2;">
                                <i class="fas fa-cloud-upload-alt fa-4x text-primary mb-4"></i>
                                <h5 class="font-semibold mb-2">Carica File CSV Giornalieri</h5>
                                <p class="text-muted mb-4">Trascina i file qui o clicca per selezionare</p>
                                <div class="bait-badge bait-badge-primary mb-3">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    Data: <?= date('d/m/Y') ?>
                                </div>
                                <div class="text-xs text-muted">
                                    Formati supportati: CSV • Dimensione max: 10MB per file
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enhanced File Input Grid -->
                        <div class="bait-grid bait-grid-md-2 bait-gap-4 bait-mt-6">
                            <div class="bait-form-group">
                                <label class="bait-form-label required" for="attivita">
                                    <i class="fas fa-tasks me-2 text-primary"></i>
                                    Attività Deepser
                                </label>
                                <input type="file" name="attivita" id="attivita" class="bait-form-control" 
                                       accept=".csv" required aria-describedby="attivita-help">
                                <div class="bait-form-help" id="attivita-help">
                                    File principale per analisi tecnici (richiesto)
                                </div>
                            </div>
                            
                            <div class="bait-form-group">
                                <label class="bait-form-label" for="timbrature">
                                    <i class="fas fa-clock me-2 text-success"></i>
                                    Timbrature
                                </label>
                                <input type="file" name="timbrature" id="timbrature" class="bait-form-control" 
                                       accept=".csv" aria-describedby="timbrature-help">
                                <div class="bait-form-help" id="timbrature-help">
                                    Orari ingresso/uscita clienti
                                </div>
                            </div>
                            
                            <div class="bait-form-group">
                                <label class="bait-form-label" for="teamviewer_bait">
                                    <i class="fas fa-desktop me-2 text-info"></i>
                                    TeamViewer BAIT
                                </label>
                                <input type="file" name="teamviewer_bait" id="teamviewer_bait" class="bait-form-control" 
                                       accept=".csv" aria-describedby="teamviewer-help">
                                <div class="bait-form-help" id="teamviewer-help">
                                    Sessioni remote individuali
                                </div>
                            </div>
                            
                            <div class="bait-form-group">
                                <label class="bait-form-label" for="teamviewer_gruppo">
                                    <i class="fas fa-users me-2 text-info"></i>
                                    TeamViewer Gruppo
                                </label>
                                <input type="file" name="teamviewer_gruppo" id="teamviewer_gruppo" class="bait-form-control" 
                                       accept=".csv">
                                <div class="bait-form-help">
                                    Sessioni remote di gruppo
                                </div>
                            </div>
                            
                            <div class="bait-form-group">
                                <label class="bait-form-label" for="permessi">
                                    <i class="fas fa-calendar-times me-2 text-warning"></i>
                                    Permessi
                                </label>
                                <input type="file" name="permessi" id="permessi" class="bait-form-control" 
                                       accept=".csv">
                                <div class="bait-form-help">
                                    Ferie e permessi approvati
                                </div>
                            </div>
                            
                            <div class="bait-form-group">
                                <label class="bait-form-label" for="auto">
                                    <i class="fas fa-car me-2 text-secondary"></i>
                                    Utilizzo Auto
                                </label>
                                <input type="file" name="auto" id="auto" class="bait-form-control" 
                                       accept=".csv">
                                <div class="bait-form-help">
                                    Registro auto aziendale
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload Progress Bar (Hidden by default) -->
                        <div id="uploadProgress" class="bait-hidden bait-mt-4">
                            <div class="bait-progress">
                                <div class="bait-progress-bar" id="progressBar" style="width: 0%"></div>
                            </div>
                            <div class="text-center text-sm text-muted bait-mt-2" id="progressText">
                                Caricamento in corso...
                            </div>
                        </div>
                        
                        <!-- Enhanced Submit Button -->
                        <div class="bait-flex bait-gap-3 bait-mt-6">
                            <button type="submit" name="upload_csv" class="bait-btn bait-btn-primary" 
                                    id="uploadBtn" aria-describedby="upload-help">
                                <i class="fas fa-upload me-2"></i>
                                <span>Carica e Processa CSV</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-outline" onclick="clearForm()" 
                                    aria-label="Pulisci form">
                                <i class="fas fa-eraser me-2"></i>
                                <span>Pulisci</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost" onclick="validateFiles()" 
                                    aria-label="Valida file">
                                <i class="fas fa-check-circle me-2"></i>
                                <span>Valida</span>
                            </button>
                        </div>
                        <div class="bait-form-help bait-mt-2" id="upload-help">
                            Il caricamento processa automaticamente i file e aggiorna le statistiche mensili
                        </div>
                    </form>
                </div>
            </div>

            <!-- Enterprise Analysis Controls -->
            <div class="bait-card" role="region" aria-label="Controlli analisi e gestione">
                <div class="bait-card-header">
                    <h3 class="bait-card-title">
                        <i class="fas fa-cogs"></i>
                        Centro Controllo Analisi
                    </h3>
                    <div class="bait-card-subtitle">
                        Gestione operazioni enterprise e monitoraggio sistema
                    </div>
                </div>
                <div class="bait-card-body">
                    <form method="POST" id="analysisForm">
                        <!-- Analysis Action Buttons -->
                        <div class="bait-grid bait-gap-4 bait-mb-6">
                            <button type="submit" name="analyze_month" class="bait-btn bait-btn-primary bait-btn-lg" 
                                    aria-describedby="analyze-help">
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <div class="font-semibold">Analizza Mese Completo</div>
                                    <div class="text-xs opacity-75">Tutti i tecnici, giorni 1-<?= $currentDay ?></div>
                                </div>
                            </button>
                            
                            <a href="/controlli/audit_tecnico_dashboard.php" class="bait-btn bait-btn-secondary bait-btn-lg" 
                               aria-describedby="audit-help">
                                <i class="fas fa-user-check"></i>
                                <div>
                                    <div class="font-semibold">Audit Tecnico Singolo</div>
                                    <div class="text-xs opacity-75">Analisi dettagliata individuale</div>
                                </div>
                            </a>
                            
                            <button type="button" class="bait-btn bait-btn-outline bait-btn-lg" 
                                    onclick="generateReport()" aria-describedby="report-help">
                                <i class="fas fa-file-pdf"></i>
                                <div>
                                    <div class="font-semibold">Genera Report PDF</div>
                                    <div class="text-xs opacity-75">Esporta statistiche mensili</div>
                                </div>
                            </button>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="bait-flex bait-gap-2 bait-mb-6">
                            <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" onclick="refreshData()">
                                <i class="fas fa-sync-alt"></i>
                                <span>Aggiorna</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" onclick="clearCache()">
                                <i class="fas fa-broom"></i>
                                <span>Pulisci Cache</span>
                            </button>
                            <button type="button" class="bait-btn bait-btn-ghost bait-btn-sm" onclick="showLogs()">
                                <i class="fas fa-list-alt"></i>
                                <span>Log Sistema</span>
                            </button>
                        </div>
                        
                        <div class="bait-form-help bait-mb-4">
                            <div id="analyze-help" class="text-xs">L'analisi completa processa tutti i dati caricati per il mese corrente</div>
                            <div id="audit-help" class="text-xs">Accesso al dashboard per analisi tecnico specifico</div>
                            <div id="report-help" class="text-xs">Generazione report PDF con tutte le statistiche mensili</div>
                        </div>
                    </form>
                    
                    <!-- Enhanced File Status Monitor -->
                    <div class="bait-border-t bait-pt-6">
                        <h6 class="font-semibold bait-mb-4">
                            <i class="fas fa-heartbeat me-2 text-success"></i>
                            Stato File Sistema
                        </h6>
                        <div class="bait-grid bait-gap-2">
                            <?php
                            $uploadDir = __DIR__ . '/data/input/';
                            $requiredFiles = [
                                'attivita.csv' => ['icon' => 'fas fa-tasks', 'label' => 'Attività Deepser', 'critical' => true],
                                'timbrature.csv' => ['icon' => 'fas fa-clock', 'label' => 'Timbrature', 'critical' => false],
                                'teamviewer_bait.csv' => ['icon' => 'fas fa-desktop', 'label' => 'TeamViewer BAIT', 'critical' => false],
                                'auto.csv' => ['icon' => 'fas fa-car', 'label' => 'Utilizzo Auto', 'critical' => false],
                                'permessi.csv' => ['icon' => 'fas fa-calendar-times', 'label' => 'Permessi', 'critical' => false],
                                'teamviewer_gruppo.csv' => ['icon' => 'fas fa-users', 'label' => 'TeamViewer Gruppo', 'critical' => false]
                            ];
                            
                            foreach ($requiredFiles as $file => $config) {
                                $filePath = $uploadDir . $file;
                                $exists = file_exists($filePath);
                                $cssClass = $exists ? 'uploaded' : 'missing';
                                $statusIcon = $exists ? 'fas fa-check-circle text-success' : 'fas fa-clock text-muted';
                                $lastModified = $exists ? date('d/m H:i', filemtime($filePath)) : 'Non caricato';
                                $fileSize = $exists ? round(filesize($filePath) / 1024, 1) . ' KB' : '-';
                                
                                echo "<div class='bait-file-status-item $cssClass' role='listitem'>";
                                echo "<i class='{$config['icon']} text-primary'></i>";
                                echo "<div class='flex-1'>";
                                echo "<div class='font-medium'>{$config['label']}";
                                if ($config['critical']) echo " <span class='bait-badge bait-badge-danger bait-badge-sm'>CRITICO</span>";
                                echo "</div>";
                                echo "<div class='text-xs text-muted'>Ultimo aggiornamento: $lastModified • Dimensione: $fileSize</div>";
                                echo "</div>";
                                echo "<i class='$statusIcon'></i>";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enterprise Technician Performance Table -->
        <?php if ($monthlyStats && !empty($monthlyStats['by_technician'])): ?>
        <section class="bait-data-table bait-mt-8" role="region" aria-label="Statistiche performance tecnici">
            <div class="bait-card-header">
                <h3 class="bait-card-title">
                    <i class="fas fa-chart-bar"></i>
                    Performance Tecnici - Analisi Mensile
                </h3>
                <div class="bait-card-subtitle">
                    Dashboard completa delle prestazioni individuali con metriche avanzate
                </div>
            </div>
            <div class="table-responsive">
                <table class="bait-table" role="table" aria-label="Tabella performance tecnici">
                    <thead>
                        <tr role="row">
                            <th scope="col" class="bait-table-row-number">#</th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-user"></i>
                                    <span>Tecnico</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Giorni</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-chart-line"></i>
                                    <span>Score Medio</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-arrow-up"></i>
                                    <span>Best</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-arrow-down"></i>
                                    <span>Worst</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Alert</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-trending-up"></i>
                                    <span>Trend</span>
                                </div>
                            </th>
                            <th scope="col">
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas fa-cogs"></i>
                                    <span>Azioni</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowIndex = 0; foreach ($monthlyStats['by_technician'] as $tech): $rowIndex++; ?>
                        <tr role="row" class="bait-hover-lift">
                            <td class="bait-table-row-number"><?= $rowIndex ?></td>
                            <td>
                                <div class="bait-flex bait-items-center bait-gap-3">
                                    <div class="bait-kpi-icon" style="width: 32px; height: 32px; font-size: 14px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold"><?= htmlspecialchars($tech['nome_completo']) ?></div>
                                        <div class="text-xs text-muted">ID: <?= str_pad($rowIndex, 3, '0', STR_PAD_LEFT) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="bait-badge bait-badge-info">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?= $tech['days_analyzed'] ?> giorni
                                </div>
                            </td>
                            <td>
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <div class="bait-badge bait-badge-<?= 
                                        $tech['avg_score'] >= 90 ? 'success' : 
                                        ($tech['avg_score'] >= 75 ? 'warning' : 'danger') ?>">
                                        <?= number_format($tech['avg_score'], 1) ?>%
                                    </div>
                                    <div class="bait-progress" style="width: 60px; height: 4px;">
                                        <div class="bait-progress-bar" style="width: <?= $tech['avg_score'] ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="font-semibold text-success"><?= number_format($tech['best_score'], 1) ?>%</span>
                            </td>
                            <td>
                                <span class="font-semibold text-danger"><?= number_format($tech['worst_score'], 1) ?>%</span>
                            </td>
                            <td>
                                <div class="bait-badge bait-badge-<?= $tech['total_alerts'] > 10 ? 'danger' : ($tech['total_alerts'] > 5 ? 'warning' : 'success') ?>">
                                    <i class="fas fa-bell me-1"></i>
                                    <?= $tech['total_alerts'] ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $improvement = $tech['best_score'] - $tech['worst_score'];
                                $trend = $improvement > 20 ? 'improving' : ($improvement > 10 ? 'stable' : 'declining');
                                $trendIcon = $trend === 'improving' ? 'fa-arrow-up text-success' : 
                                           ($trend === 'stable' ? 'fa-minus text-secondary' : 'fa-arrow-down text-danger');
                                $trendText = $trend === 'improving' ? 'Miglioramento' : 
                                           ($trend === 'stable' ? 'Stabile' : 'In calo');
                                ?>
                                <div class="bait-flex bait-items-center bait-gap-2">
                                    <i class="fas <?= $trendIcon ?>"></i>
                                    <span class="text-xs"><?= $trendText ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="bait-flex bait-gap-1">
                                    <button class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="viewTechnicianDetails('<?= $tech['nome_completo'] ?>')" 
                                            aria-label="Visualizza dettagli tecnico">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="bait-btn bait-btn-ghost bait-btn-sm" 
                                            onclick="exportTechnicianReport('<?= $tech['nome_completo'] ?>')" 
                                            aria-label="Esporta report tecnico">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <!-- Enterprise Alert Categories Dashboard -->
        <?php if ($monthlyStats && !empty($monthlyStats['alerts_by_category'])): ?>
        <section class="bait-card bait-mt-8" role="region" aria-label="Analisi alert per categoria">
            <div class="bait-card-header">
                <h3 class="bait-card-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Analisi Alert per Categoria
                </h3>
                <div class="bait-card-subtitle">
                    Breakdown dettagliato degli alert generati con metriche di severità
                </div>
            </div>
            <div class="bait-card-body">
                <div class="bait-grid bait-grid-md-2 bait-grid-lg-3 bait-gap-4">
                    <?php foreach ($monthlyStats['alerts_by_category'] as $index => $category): 
                        $severityClass = $category['avg_severity_score'] >= 3.5 ? 'danger' : 
                                       ($category['avg_severity_score'] >= 2.5 ? 'warning' : 'info');
                        $categoryIcon = [
                            'timing' => 'fas fa-clock',
                            'overlap' => 'fas fa-copy',
                            'distance' => 'fas fa-route',
                            'validation' => 'fas fa-check-circle',
                            'data_quality' => 'fas fa-database'
                        ];
                        $icon = $categoryIcon[strtolower($category['category'])] ?? 'fas fa-exclamation';
                    ?>
                    <div class="bait-alert-category" onclick="showAlertDetails('<?= $category['category'] ?>')" 
                         role="button" tabindex="0" aria-label="Visualizza dettagli categoria <?= $category['category'] ?>">
                        <div class="bait-flex bait-items-center bait-gap-3 bait-mb-3">
                            <div class="bait-kpi-icon" style="background: var(--bait-<?= $severityClass ?>-bg); color: var(--bait-<?= $severityClass ?>);">
                                <i class="<?= $icon ?>"></i>
                            </div>
                            <div class="flex-1">
                                <h6 class="font-semibold bait-mb-1">
                                    <?= ucfirst(str_replace('_', ' ', $category['category'])) ?>
                                </h6>
                                <div class="text-xs text-muted">Categoria #<?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></div>
                            </div>
                        </div>
                        
                        <div class="bait-flex bait-justify-between bait-items-center bait-mb-3">
                            <div class="bait-badge bait-badge-<?= $severityClass ?>">
                                <i class="fas fa-bell me-1"></i>
                                <?= $category['count'] ?> alert
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-muted">Severità Media</div>
                                <div class="font-semibold text-<?= $severityClass ?>">
                                    <?= number_format($category['avg_severity_score'], 1) ?>/4.0
                                </div>
                            </div>
                        </div>
                        
                        <div class="bait-progress bait-mb-2">
                            <div class="bait-progress-bar" style="width: <?= ($category['avg_severity_score'] / 4) * 100 ?>%"></div>
                        </div>
                        
                        <div class="bait-flex bait-justify-between bait-items-center">
                            <small class="text-muted">
                                <?= round(($category['count'] / array_sum(array_column($monthlyStats['alerts_by_category'], 'count'))) * 100, 1) ?>% del totale
                            </small>
                            <button class="bait-btn bait-btn-ghost bait-btn-sm" 
                                    onclick="event.stopPropagation(); exportCategoryReport('<?= $category['category'] ?>')" 
                                    aria-label="Esporta report categoria">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Alert Summary Stats -->
                <div class="bait-border-t bait-pt-6 bait-mt-6">
                    <div class="bait-grid bait-grid-md-4 bait-gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary">
                                <?= array_sum(array_column($monthlyStats['alerts_by_category'], 'count')) ?>
                            </div>
                            <div class="text-xs text-muted">Alert Totali</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-warning">
                                <?= count($monthlyStats['alerts_by_category']) ?>
                            </div>
                            <div class="text-xs text-muted">Categorie Attive</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-info">
                                <?= number_format(array_sum(array_column($monthlyStats['alerts_by_category'], 'avg_severity_score')) / count($monthlyStats['alerts_by_category']), 1) ?>
                            </div>
                            <div class="text-xs text-muted">Severità Media</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-success">
                                <?= round((array_sum(array_column($monthlyStats['alerts_by_category'], 'count')) / ($monthlyStats['total_analyses'] ?? 1)) * 100, 1) ?>%
                            </div>
                            <div class="text-xs text-muted">Tasso Alert</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Help Section -->
        <div class="audit-card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>Guida Sistema Audit Mensile
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Workflow Quotidiano:</h6>
                        <ol>
                            <li>Carica CSV giornalieri (mattina)</li>
                            <li>Analizza tecnici singolarmente</li>
                            <li>Rivedi alert e anomalie</li>
                            <li>Invia correzioni se necessarie</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>Gestione Mensile:</h6>
                        <ul>
                            <li>Dashboard progressiva (1-31 giorni)</li>
                            <li>Reset automatico il 1° del mese</li>
                            <li>Archiviazione fine mese</li>
                            <li>Statistiche e trend analysis</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function generateReport() {
            alert('Funzionalità report PDF in arrivo nella prossima versione!');
        }
        
        // Auto-refresh progress
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Audit Monthly Manager loaded successfully');
            
            // Update progress ring animation
            const progressCircle = document.querySelector('.progress-circle');
            if (progressCircle) {
                const currentDay = <?= $currentDay ?>;
                const totalDays = <?= date('t') ?>;
                const percentage = currentDay / totalDays;
                const offset = 314 - (314 * percentage);
                progressCircle.style.strokeDashoffset = offset;
            }
        });
    </script>
</body>
</html>