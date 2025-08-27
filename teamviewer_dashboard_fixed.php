<?php
/**
 * TEAMVIEWER DASHBOARD FIXED - Versione Corretta per Dashboard
 * Usa la stessa logica del file che funziona bene
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connessione database
    $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query CORRETTA come nel file che funziona
    $stmt = $pdo->query("
        SELECT 
            ts.session_id,
            t.nome_completo as tecnico_nome,
            ts.computer_remoto,
            ts.data_sessione,
            ts.ora_inizio,
            ts.ora_fine, 
            ts.durata_minuti,
            ts.tipo_sessione,
            ts.descrizione
        FROM teamviewer_sessions ts
        LEFT JOIN tecnici t ON ts.tecnico_id = t.id
        WHERE ts.session_id NOT LIKE 'TV%'
        ORDER BY ts.data_sessione DESC, ts.ora_inizio DESC
    ");
    
    $sessions = $stmt->fetchAll();
    $totalSessions = count($sessions);
    
    // Calcola statistiche CORRETTE
    $totalMinutes = 0;
    $tecnici = [];
    
    foreach ($sessions as $session) {
        $totalMinutes += intval($session['durata_minuti']); 
        if ($session['tecnico_nome']) {
            $tecnici[$session['tecnico_nome']] = true;
        }
    }
    
    $totalHours = round($totalMinutes / 60, 1);
    $avgMinutes = $totalSessions > 0 ? round($totalMinutes / $totalSessions, 1) : 0;
    $uniqueUsers = count($tecnici);
    
} catch (Exception $e) {
    $sessions = [];
    $totalSessions = 0;
    $totalHours = 0;
    $avgMinutes = 0;
    $uniqueUsers = 0;
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🖥️ Sessioni TeamViewer - BAIT Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        .stats-card.sessions { border-left-color: #17a2b8; }
        .stats-card.duration { border-left-color: #28a745; }
        .stats-card.users { border-left-color: #6f42c1; }
        .stats-card.average { border-left-color: #fd7e14; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .table-header {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            padding: 1rem;
        }
        
        .badge-tecnico {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-duration {
            background-color: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card sessions">
                    <h4 class="stats-number text-info"><?= $totalSessions ?></h4>
                    <p class="stats-label mb-0">Sessioni Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card duration">
                    <h4 class="stats-number text-success"><?= $totalHours ?>h</h4>
                    <p class="stats-label mb-0">Durata Totale</p>
                    <small class="text-muted"><?= $totalMinutes ?> minuti</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card users">
                    <h4 class="stats-number text-primary"><?= $uniqueUsers ?></h4>
                    <p class="stats-label mb-0">Utenti Unici</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card average">
                    <h4 class="stats-number text-warning"><?= $avgMinutes ?>min</h4>
                    <p class="stats-label mb-0">Durata Media</p>
                </div>
            </div>
        </div>

        <!-- Sessions Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-0">
                            <i class="fas fa-desktop me-2"></i>Sessioni TeamViewer Attive
                        </h5>
                        <small class="text-muted">Fonte: DATABASE (<?= $totalSessions ?> sessioni)</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Dati Aggiornati
                        </span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (!empty($sessions)): ?>
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tecnico</th>
                            <th>Cliente/Computer</th>
                            <th>Data</th>
                            <th>Orario</th>
                            <th>Durata</th>
                            <th>Session ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $index => $session): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <?php if ($session['tecnico_nome']): ?>
                                    <span class="badge-tecnico"><?= htmlspecialchars($session['tecnico_nome']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Sconosciuto</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($session['computer_remoto'] ?? '-') ?></strong>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($session['data_sessione'])) ?>
                            </td>
                            <td>
                                <small>
                                    <?= date('H:i', strtotime($session['ora_inizio'])) ?>
                                    -
                                    <?= date('H:i', strtotime($session['ora_fine'])) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge-duration"><?= $session['durata_minuti'] ?>min</span>
                            </td>
                            <td>
                                <code style="font-size: 0.8rem;"><?= htmlspecialchars($session['session_id']) ?></code>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
                    <h5>Nessuna sessione TeamViewer trovata</h5>
                    <p class="text-muted">Importa i file CSV per visualizzare le sessioni.</p>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger mt-3">
                            <strong>Errore:</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="text-center mt-4">
            <a href="sessioni_teamviewer.php" class="btn btn-outline-primary">
                <i class="fas fa-external-link-alt me-1"></i>Apri Pagina Completa
            </a>
            <a href="upload_csv_simple.php" class="btn btn-outline-success ms-2">
                <i class="fas fa-upload me-1"></i>Carica Nuovi CSV
            </a>
        </div>
    </div>
</body>
</html>