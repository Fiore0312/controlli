<?php
/**
 * AUDIT TECNICO SEMPLIFICATO - Fix Temporaneo
 * Versione semplificata che mostra dati reali dalle tabelle esistenti
 */

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
$selectedTechnician = $_POST['tecnico_id'] ?? $_GET['tecnico'] ?? null;
$selectedDate = $_POST['analysis_date'] ?? $_GET['data'] ?? date('Y-m-d');
$analysisData = [];
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

    // If tecnico and date selected, get real data
    if ($selectedTechnician && $selectedDate) {
        // Get tecnico info
        $stmt = $pdo->prepare("SELECT nome_completo FROM tecnici WHERE id = ?");
        $stmt->execute([$selectedTechnician]);
        $technicianInfo = $stmt->fetch();
        
        if ($technicianInfo) {
            $analysisData['tecnico_nome'] = $technicianInfo['nome_completo'];
            
            // Utilizzi auto
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as utilizzi, 
                       SUM(ore_utilizzo) as ore_totali,
                       GROUP_CONCAT(DISTINCT clienti SEPARATOR ', ') as clienti_visitati
                FROM utilizzi_auto 
                WHERE tecnico_id = ? AND DATE(data_utilizzo) = ?
            ");
            $stmt->execute([$selectedTechnician, $selectedDate]);
            $autoData = $stmt->fetch();
            
            // Timbrature
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as timbrature,
                           MIN(ora) as prima_entrata,
                           MAX(ora) as ultima_uscita
                    FROM timbrature 
                    WHERE tecnico_id = ? AND DATE(data) = ?
                ");
                $stmt->execute([$selectedTechnician, $selectedDate]);
                $timbratureData = $stmt->fetch();
            } catch (Exception $e) {
                $timbratureData = ['timbrature' => 0, 'prima_entrata' => null, 'ultima_uscita' => null];
            }
            
            // Deepser attività
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as attivita,
                           SUM(ore_lavorate) as ore_deepser
                    FROM deepser_attivita 
                    WHERE tecnico_id = ? AND DATE(iniziata_il) = ?
                ");
                $stmt->execute([$selectedTechnician, $selectedDate]);
                $deepserData = $stmt->fetch();
            } catch (Exception $e) {
                $deepserData = ['attivita' => 0, 'ore_deepser' => 0];
            }
            
            // TeamViewer
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as sessioni,
                           SUM(durata_minuti) as minuti_totali
                    FROM teamviewer_sessions 
                    WHERE tecnico_id = ? AND DATE(session_start) = ?
                ");
                $stmt->execute([$selectedTechnician, $selectedDate]);
                $teamviewerData = $stmt->fetch();
            } catch (Exception $e) {
                $teamviewerData = ['sessioni' => 0, 'minuti_totali' => 0];
            }
            
            $analysisData['auto'] = $autoData;
            $analysisData['timbrature'] = $timbratureData;
            $analysisData['deepser'] = $deepserData;
            $analysisData['teamviewer'] = $teamviewerData;
            
            // Calcola punteggio semplice
            $score = 0;
            if ($autoData['utilizzi'] > 0) $score += 25;
            if ($timbratureData['timbrature'] > 0) $score += 25;
            if ($deepserData['attivita'] > 0) $score += 25;
            if ($teamviewerData['sessioni'] > 0) $score += 25;
            
            $analysisData['quality_score'] = $score;
        } else {
            $error = "Tecnico non trovato";
        }
    }

} catch (Exception $e) {
    $error = "Errore database: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Tecnico - BAIT Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- BAIT Unified Design System -->
    <link href="/controlli/assets/css/bait-unified-system.css" rel="stylesheet">
    <style>
        .quality-score {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--bait-primary, #3b82f6);
        }
    </style>
</head>
<body>
    <!-- BAIT Navigation System -->
    <?php
    require_once 'includes/bait_navigation.php';
    renderBaitNavigation(basename(__FILE__, '.php'), 'database');
    ?>

    <div class="container py-4">
        <!-- Form Selection -->
        <div class="bait-card">
            <div class="bait-card-header">
                <h5><i class="bi bi-search me-2"></i>Selezione Tecnico e Data</h5>
            </div>
            <div>
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label bait-text-sm">Tecnico</label>
                        <select class="form-select bait-form-control" name="tecnico_id" required>
                            <option value="">Seleziona tecnico...</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" <?= $selectedTechnician == $tech['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label bait-text-sm">Data Analisi</label>
                        <input type="date" class="form-control bait-form-control" name="analysis_date" value="<?= $selectedDate ?>" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="analyze" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Analizza
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($analysisData): ?>
            <!-- Analysis Results -->
            <div class="bait-card">
                <div class="bait-card-header">
                    <h5>
                        <i class="bi bi-clipboard-data me-2"></i>
                        Analisi per <?= htmlspecialchars($analysisData['tecnico_nome']) ?> - <?= date('d/m/Y', strtotime($selectedDate)) ?>
                    </h5>
                </div>
                <div>
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <div class="quality-score"><?= $analysisData['quality_score'] ?>%</div>
                            <div class="text-muted">Punteggio Qualità</div>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-3">
                                <!-- Auto -->
                                <div class="col-md-3">
                                    <div class="bait-stat-card">
                                        <div class="bait-stat-value"><?= $analysisData['auto']['utilizzi'] ?></div>
                                        <div class="bait-stat-label">Utilizzi Auto</div>
                                        <?php if ($analysisData['auto']['ore_totali']): ?>
                                            <small class="bait-text-xs text-muted"><?= number_format($analysisData['auto']['ore_totali'], 1) ?>h</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Timbrature -->
                                <div class="col-md-3">
                                    <div class="bait-stat-card">
                                        <div class="bait-stat-value"><?= $analysisData['timbrature']['timbrature'] ?></div>
                                        <div class="bait-stat-label">Timbrature</div>
                                        <?php if ($analysisData['timbrature']['prima_entrata']): ?>
                                            <small class="bait-text-xs text-muted"><?= date('H:i', strtotime($analysisData['timbrature']['prima_entrata'])) ?> - <?= date('H:i', strtotime($analysisData['timbrature']['ultima_uscita'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Deepser -->
                                <div class="col-md-3">
                                    <div class="bait-stat-card">
                                        <div class="bait-stat-value"><?= $analysisData['deepser']['attivita'] ?></div>
                                        <div class="bait-stat-label">Attività Deepser</div>
                                        <?php if ($analysisData['deepser']['ore_deepser']): ?>
                                            <small class="bait-text-xs text-muted"><?= number_format($analysisData['deepser']['ore_deepser'], 1) ?>h</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- TeamViewer -->
                                <div class="col-md-3">
                                    <div class="bait-stat-card">
                                        <div class="bait-stat-value"><?= $analysisData['teamviewer']['sessioni'] ?></div>
                                        <div class="bait-stat-label">Sessioni TeamViewer</div>
                                        <?php if ($analysisData['teamviewer']['minuti_totali']): ?>
                                            <small class="bait-text-xs text-muted"><?= round($analysisData['teamviewer']['minuti_totali']/60, 1) ?>h</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($analysisData['auto']['clienti_visitati']): ?>
                        <div class="alert alert-info">
                            <strong class="bait-text-sm"><i class="bi bi-geo-alt me-2"></i>Clienti Visitati:</strong> 
                            <span class="bait-text-sm"><?= htmlspecialchars($analysisData['auto']['clienti_visitati']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>