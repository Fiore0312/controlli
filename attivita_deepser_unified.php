<?php
/**
 * ATTIVITÀ DEEPSER - Dashboard Unified Design
 * Layout unificato con design TeamViewer per consistenza visiva
 */

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Rome');

try {
    // Connessione database
    $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query per attività con join tecnici
    $stmt = $pdo->query("
        SELECT 
            a.*,
            COALESCE(t.nome_completo, 'N/A') as tecnico_nome,
            DATE(a.data_attivita) as data_formatted,
            TIME(a.ora_inizio) as ora_inizio_formatted,
            TIME(a.ora_fine) as ora_fine_formatted,
            TIMESTAMPDIFF(MINUTE, 
                CONCAT(a.data_attivita, ' ', a.ora_inizio), 
                CONCAT(a.data_attivita, ' ', a.ora_fine)
            ) as durata_minuti
        FROM deepser_attivita a 
        LEFT JOIN tecnici t ON a.tecnico_id = t.id 
        ORDER BY a.data_attivita DESC, a.ora_inizio DESC
    ");
    
    $activities = $stmt->fetchAll();
    $totalActivities = count($activities);
    
    // Calcola statistiche
    $totalMinutes = 0;
    $tecnici = [];
    $clienti = [];
    $today = date('Y-m-d');
    $todayActivities = 0;
    
    foreach ($activities as $activity) {
        $totalMinutes += intval($activity['durata_minuti'] ?? 0);
        if ($activity['tecnico_nome'] && $activity['tecnico_nome'] !== 'N/A') {
            $tecnici[$activity['tecnico_nome']] = true;
        }
        if ($activity['cliente_nome']) {
            $clienti[$activity['cliente_nome']] = true;
        }
        if ($activity['data_formatted'] === $today) {
            $todayActivities++;
        }
    }
    
    $totalHours = round($totalMinutes / 60, 1);
    $avgMinutes = $totalActivities > 0 ? round($totalMinutes / $totalActivities, 1) : 0;
    $uniqueTecnici = count($tecnici);
    $uniqueClienti = count($clienti);
    
} catch (Exception $e) {
    $activities = [];
    $totalActivities = 0;
    $totalHours = 0;
    $avgMinutes = 0;
    $uniqueTecnici = 0;
    $uniqueClienti = 0;
    $todayActivities = 0;
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💼 Attività Deepser - BAIT Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            border-left: 4px solid;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .stats-card.activities { border-left-color: #17a2b8; }
        .stats-card.duration { border-left-color: #28a745; }
        .stats-card.tecnici { border-left-color: #6f42c1; }
        .stats-card.today { border-left-color: #fd7e14; }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
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
        
        .table-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .table-header .stats {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .table {
            margin: 0;
            font-size: 0.9rem;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: 600;
            color: #495057;
        }
        
        .badge-tecnico {
            background-color: #28a745;
            color: white;
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .badge-cliente {
            background-color: #17a2b8;
            color: white;
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .badge-durata {
            background-color: #6f42c1;
            color: white;
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .badge-oggi {
            background-color: #fd7e14;
            color: white;
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .activity-row {
            transition: background-color 0.2s ease;
        }
        
        .activity-row:hover {
            background-color: #f8f9fa;
        }
        
        .description-cell {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .search-controls {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filter-badge {
            display: inline-block;
            margin: 0.2rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .filter-badge:hover {
            transform: scale(1.05);
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .stats-number {
                font-size: 2rem;
            }
            
            .table-responsive {
                max-height: 500px;
            }
            
            .description-cell {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Errore connessione database:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card activities">
                    <div class="stats-number"><?= number_format($totalActivities) ?></div>
                    <div class="stats-label">
                        <i class="bi bi-briefcase me-1"></i>
                        Attività Totali
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card duration">
                    <div class="stats-number"><?= $totalHours ?>h</div>
                    <div class="stats-label">
                        <i class="bi bi-clock me-1"></i>
                        Ore Totali
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card tecnici">
                    <div class="stats-number"><?= $uniqueTecnici ?></div>
                    <div class="stats-label">
                        <i class="bi bi-people me-1"></i>
                        Tecnici Attivi
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card today">
                    <div class="stats-number"><?= $todayActivities ?></div>
                    <div class="stats-label">
                        <i class="bi bi-calendar-check me-1"></i>
                        Oggi
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="search-controls">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Cerca attività, tecnico, cliente...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex flex-wrap align-items-center">
                        <small class="text-muted me-2">Filtri rapidi:</small>
                        <span class="badge bg-info filter-badge" onclick="filterByToday()">Oggi</span>
                        <span class="badge bg-success filter-badge" onclick="filterByWeek()">Questa settimana</span>
                        <span class="badge bg-warning filter-badge" onclick="clearFilters()">Tutti</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activities Table -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="bi bi-briefcase-fill me-2"></i>
                    Attività Deepser
                </h5>
                <div class="stats">
                    <?= number_format($totalActivities) ?> attività • 
                    <?= $totalHours ?> ore totali • 
                    Media: <?= $avgMinutes ?> min/attività •
                    <?= $uniqueClienti ?> clienti
                </div>
            </div>
            
            <?php if (empty($activities)): ?>
                <div class="no-data">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                    <h5 class="mt-3">Nessuna attività trovata</h5>
                    <p class="text-muted">Le attività appariranno qui dopo l'importazione dei file CSV</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="activitiesTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tecnico</th>
                                <th>Orario</th>
                                <th>Durata</th>
                                <th>Cliente</th>
                                <th>Descrizione</th>
                                <th>Importato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr class="activity-row" data-date="<?= $activity['data_formatted'] ?>" data-tecnico="<?= htmlspecialchars($activity['tecnico_nome']) ?>" data-cliente="<?= htmlspecialchars($activity['cliente_nome'] ?? '') ?>">
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($activity['data_formatted'])) ?></strong>
                                        <?php if ($activity['data_formatted'] === date('Y-m-d')): ?>
                                            <br><span class="badge badge-oggi">Oggi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-tecnico">
                                            <?= htmlspecialchars($activity['tecnico_nome']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= $activity['ora_inizio_formatted'] ?></strong>
                                        <?php if ($activity['ora_fine_formatted']): ?>
                                            <br><small class="text-muted">→ <?= $activity['ora_fine_formatted'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($activity['durata_minuti'] > 0): ?>
                                            <span class="badge badge-durata">
                                                <?php 
                                                $hours = floor($activity['durata_minuti'] / 60);
                                                $minutes = $activity['durata_minuti'] % 60;
                                                if ($hours > 0) {
                                                    echo $hours . 'h ' . $minutes . 'm';
                                                } else {
                                                    echo $minutes . 'm';
                                                }
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($activity['cliente_nome']): ?>
                                            <span class="badge badge-cliente">
                                                <?= htmlspecialchars($activity['cliente_nome']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="description-cell">
                                        <?= htmlspecialchars(substr($activity['descrizione'] ?? 'N/A', 0, 100)) ?>
                                        <?php if (strlen($activity['descrizione'] ?? '') > 100): ?>
                                            <span class="text-muted">...</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m H:i', strtotime($activity['created_at'] ?? $activity['data_attivita'])) ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#activitiesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter functions
        function filterByToday() {
            const today = new Date().toISOString().split('T')[0];
            const rows = document.querySelectorAll('#activitiesTable tbody tr');
            
            rows.forEach(row => {
                const date = row.getAttribute('data-date');
                row.style.display = date === today ? '' : 'none';
            });
            
            document.getElementById('searchInput').value = '';
        }

        function filterByWeek() {
            const today = new Date();
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            const rows = document.querySelectorAll('#activitiesTable tbody tr');
            
            rows.forEach(row => {
                const date = new Date(row.getAttribute('data-date'));
                row.style.display = date >= weekAgo ? '' : 'none';
            });
            
            document.getElementById('searchInput').value = '';
        }

        function clearFilters() {
            const rows = document.querySelectorAll('#activitiesTable tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            document.getElementById('searchInput').value = '';
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>