<?php
/**
 * SESSIONI TEAMVIEWER - Visualizzazione teamviewer_bait.csv + teamviewer_gruppo.csv
 * Gestione sessioni remote TeamViewer
 */

header('Content-Type: text/html; charset=utf-8');

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

// Funzione per leggere CSV con gestione encoding
function readCSVFile($filepath) {
    if (!file_exists($filepath)) {
        return ['headers' => [], 'data' => []];
    }
    
    $csvContent = file_get_contents($filepath);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    $lines = str_getcsv($csvContent, "\n");
    if (empty($lines)) return ['headers' => [], 'data' => []];
    
    $headers = str_getcsv(array_shift($lines), ';');
    // Clean BOM if present
    if (!empty($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    
    $data = [];
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line, ';');
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $data[] = array_slice($row, 0, count($headers));
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

// Load data from database first, CSV as fallback
$pdo = getDatabase();
$dbData = [];
$hasDbData = false;

// Database data loaded successfully - debug removed

if ($pdo) {
    try {
        // Debug: Check if teamviewer_sessions table exists and has data
        $stmt = $pdo->query("SHOW TABLES LIKE '%teamviewer%'");
        $tables = $stmt->fetchAll();
        error_log("TeamViewer tables found: " . print_r($tables, true));
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM teamviewer_sessions");  
        $count = $stmt->fetchColumn();
        error_log("TeamViewer records count: " . $count);
        
        if ($count > 0) {
            // Sample data for debugging
            $stmt = $pdo->query("SELECT * FROM teamviewer_sessions LIMIT 2");
            $sampleData = $stmt->fetchAll();
            error_log("Sample TeamViewer data: " . print_r($sampleData, true));
        }
        
        // Prima controlla se ci sono dati nella tabella
        if ($count > 0) {
            $stmt = $pdo->query("
                SELECT 
                    ts.session_id,
                    COALESCE(t.nome_completo, 'Sconosciuto') as tecnico_nome,
                    COALESCE(c.ragione_sociale, ts.computer_remoto, 'Cliente sconosciuto') as cliente_nome,
                    ts.data_sessione,
                    ts.ora_inizio,
                    ts.ora_fine,
                    ts.durata_minuti,
                    ts.tipo_sessione,
                    ts.descrizione,
                    ts.computer_remoto
                FROM teamviewer_sessions ts
                LEFT JOIN tecnici t ON ts.tecnico_id = t.id
                LEFT JOIN clienti c ON ts.cliente_id = c.id
                ORDER BY ts.data_sessione DESC, ts.ora_inizio DESC
            ");
            
            $dbData = $stmt->fetchAll();
            $hasDbData = true; // Se ci sono record nella tabella, abbiamo dati DB
            
            error_log("Query executed successfully. Records found: " . count($dbData));
        } else {
            $hasDbData = false;
            error_log("No records in teamviewer_sessions table");
        }
        
    } catch (Exception $e) {
        // Database error, will fall back to CSV
        $dbError = $e->getMessage();
        error_log("TeamViewer query error: " . $e->getMessage());
    }
}

// Fallback to CSV files if no database data
$csvBaitPath = __DIR__ . '/upload_csv/teamviewer_bait.csv';
$csvGruppoPath = __DIR__ . '/upload_csv/teamviewer_gruppo.csv';

$hasBaitCSV = file_exists($csvBaitPath);
$hasGruppoCSV = file_exists($csvGruppoPath);

$baitData = $hasBaitCSV ? readCSVFile($csvBaitPath) : ['headers' => [], 'data' => []];
$gruppoData = $hasGruppoCSV ? readCSVFile($csvGruppoPath) : ['headers' => [], 'data' => []];

// Calculate statistics using database data first, CSV as fallback
$totalSessions = 0;
$totalDuration = 0; // in minutes
$users = [];
$sessionTypes = [];
$combinedData = [];
$combinedHeaders = ['Fonte', 'Tecnico', 'Cliente/Computer', 'Session ID', 'Tipo Sessione', 'Data', 'Ora Inizio', 'Durata', 'Note'];

// Initialize debug information for file status
$debugInfo = [
    'bait_exists' => file_exists($csvBaitPath),
    'bait_path' => $csvBaitPath,
    'gruppo_exists' => file_exists($csvGruppoPath),
    'gruppo_path' => $csvGruppoPath,
    'input_dir_exists' => is_dir(__DIR__ . '/upload_csv'),
    'input_dir_readable' => is_readable(__DIR__ . '/upload_csv')
];

if ($hasDbData) {
    // Use database data
    $totalSessions = count($dbData);
    
    foreach ($dbData as $session) {
        // Format for display
        $displayRow = [
            'DATABASE',
            $session['tecnico_nome'] ?? 'Sconosciuto',
            $session['cliente_nome'] ?? $session['computer_remoto'] ?? '-',
            $session['session_id'] ?? '-',
            ucfirst($session['tipo_sessione'] ?? 'user'),
            $session['data_sessione'] ?? '-',
            $session['ora_inizio'] ?? '-',
            $session['durata_minuti'] ? $session['durata_minuti'] . ' min' : '-',
            $session['descrizione'] ?? '-'
        ];
        $combinedData[] = $displayRow;
        
        // Calculate statistics
        if (!empty($session['tecnico_nome'])) {
            $users[$session['tecnico_nome']] = true;
        }
        
        if (!empty($session['durata_minuti'])) {
            $totalDuration += intval($session['durata_minuti']);
        }
        
        if (!empty($session['tipo_sessione'])) {
            $sessionTypes[$session['tipo_sessione']] = true;
        }
    }
} else {
    // Fallback to CSV data with improved parsing
    function parseTeamViewerRow($rawRow, $source) {
        if (empty($rawRow) || count($rawRow) < 6) {
            return null;
        }
        
        if ($source === 'BAIT') {
            return [
                $source,
                $rawRow[0] ?? '',          // Assegnatario
                $rawRow[1] ?? '',          // Nome cliente  
                $rawRow[2] ?? '',          // Codice sessione
                $rawRow[3] ?? '',          // Tipo di sessione
                $rawRow[5] ?? '',          // Inizio
                $rawRow[6] ?? '',          // Fine
                $rawRow[7] ?? '',          // Durata
                $rawRow[8] ?? ''           // Note
            ];
        } else {
            return [
                $source,
                $rawRow[0] ?? '',          // Utente
                $rawRow[1] ?? '',          // Computer
                $rawRow[2] ?? '',          // ID sessione
                $rawRow[3] ?? '',          // Tipo di sessione
                $rawRow[5] ?? '',          // Inizio
                $rawRow[6] ?? '',          // Fine  
                $rawRow[7] ?? '',          // Durata
                $rawRow[8] ?? ''           // Note
            ];
        }
    }
    
    // Add BAIT data
    foreach ($baitData['data'] as $row) {
        $parsedRow = parseTeamViewerRow($row, 'BAIT');
        if ($parsedRow) {
            $combinedData[] = $parsedRow;
        }
    }
    
    // Add Gruppo data
    foreach ($gruppoData['data'] as $row) {
        $parsedRow = parseTeamViewerRow($row, 'GRUPPO');
        if ($parsedRow) {
            $combinedData[] = $parsedRow;
        }
    }
    
    $totalSessions = count($combinedData);
    
    // Calculate statistics from CSV
    foreach ($combinedData as $row) {
        if (count($row) >= 8) {
            // User/Assignee (colonna 1)
            if (!empty($row[1])) {
                $users[$row[1]] = true;
            }
            
            // Duration parsing (colonna 7)
            if (!empty($row[7])) {
                $duration = trim($row[7]);
                if (preg_match('/(\d+)h\s*(\d+)m/', $duration, $matches)) {
                    $hours = intval($matches[1]);
                    $minutes = intval($matches[2]);
                    $totalDuration += ($hours * 60) + $minutes;
                } elseif (preg_match('/(\d+)m/', $duration, $matches)) {
                    $totalDuration += intval($matches[1]);
                } elseif (preg_match('/^(\d+)$/', $duration, $matches)) {
                    $totalDuration += intval($matches[1]);
                }
            }
            
            // Session types (colonna 4)
            if (!empty($row[4])) {
                $sessionTypes[$row[4]] = true;
            }
        }
    }
}

$uniqueUsers = count($users);
$uniqueSessionTypes = count($sessionTypes);  
$averageDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions, 1) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessioni TeamViewer - BAIT Service</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- BAIT Unified Design System -->
    <link href="/controlli/assets/css/bait-unified-system.css" rel="stylesheet">
    
    <style>
        .bait-status-active {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .bait-status-inactive {
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-bait { background-color: #3b82f6; }
        .badge-gruppo { background-color: #8b5cf6; }
        
        .badge-user, .badge-session, .badge-duration {
            font-size: var(--bait-font-xs);
            padding: var(--bait-spacing-xs) var(--bait-spacing-sm);
            border-radius: 12px;
            font-weight: 500;
        }
        
        .badge-user { background-color: #10b981; }
        .badge-session { background-color: #f59e0b; }
        .badge-duration { background-color: #6b7280; }
    </style>
</head>
<body>
    <!-- BAIT Navigation System -->
    <?php
    require_once 'includes/bait_navigation.php';
    renderBaitNavigation(basename(__FILE__, '.php'), 'database');
    ?>

    <div class="container py-4">
        <h1 class="bait-page-title"><i class="bi bi-display me-2"></i>Sessioni TeamViewer</h1>

        <!-- Statistics Cards -->
        <div class="bait-stats-grid">
            <div class="bait-stat-card">
                <div class="bait-stat-value"><?= number_format($totalSessions) ?></div>
                <div class="bait-stat-label">Sessioni Totali</div>
            </div>
            <div class="bait-stat-card">
                <div class="bait-stat-value"><?= round($totalDuration / 60, 1) ?>h</div>
                <div class="bait-stat-label">Durata Totale</div>
            </div>
            <div class="bait-stat-card">
                <div class="bait-stat-value"><?= $uniqueUsers ?></div>
                <div class="bait-stat-label">Utenti Unici</div>
            </div>
            <div class="bait-stat-card">
                <div class="bait-stat-value"><?= $averageDuration ?>min</div>
                <div class="bait-stat-label">Durata Media</div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="bait-table-container">
            <div class="bait-card-header d-flex justify-content-between align-items-center">
                <h5>
                    <i class="bi bi-display"></i>
                    Sessioni TeamViewer
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <small class="bait-text-xs">
                        Fonte: 
                        <?php if ($hasDbData): ?>
                            <span class="bait-status-active">DATABASE</span>
                        <?php else: ?>
                            <?php if ($hasBaitCSV): ?>
                                <span class="badge badge-bait me-1">BAIT</span>
                            <?php endif; ?>
                            <?php if ($hasGruppoCSV): ?>
                                <span class="badge badge-gruppo">GRUPPO</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>
                    
                    <?php if ($hasDbData || $hasBaitCSV || $hasGruppoCSV): ?>
                    <span class="bait-status-active">Dati OK</span>
                    <?php else: ?>
                    <span class="bait-status-inactive">No Data</span>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <?php if (!empty($combinedData)): ?>
                <table id="teamviewerTable" class="bait-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fonte</th>
                            <th>Utente/Assegnatario</th>
                            <th>Nome/Computer</th>
                            <th>Codice/ID</th>
                            <th>Tipo Sessione</th>
                            <th>Inizio</th>
                            <th>Fine</th>
                            <th>Durata</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($combinedData as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <span class="badge badge-source <?= $row[0] === 'BAIT' ? 'badge-bait' : 'badge-gruppo' ?>">
                                    <?= htmlspecialchars($row[0]) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row[1])): ?>
                                    <span class="badge-user"><?= htmlspecialchars($row[1]) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row[2] ?? '') ?></td>
                            <td>
                                <?php if (!empty($row[3])): ?>
                                    <code><?= htmlspecialchars($row[3]) ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row[4])): ?>
                                    <span class="badge-session"><?= htmlspecialchars($row[4]) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($row[5])) {
                                    try {
                                        echo date('d/m/Y H:i', strtotime(str_replace('/', '-', $row[5])));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($row[5]);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($row[6])) {
                                    try {
                                        echo date('d/m/Y H:i', strtotime(str_replace('/', '-', $row[6])));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($row[6]);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($row[7])): ?>
                                    <?php
                                    $duration = trim($row[7]);
                                    
                                    // Uniforma la formattazione della durata
                                    if (preg_match('/^\d+$/', $duration)) {
                                        // Solo numero senza "m" - aggiungi "m"
                                        $formattedDuration = $duration . 'm';
                                    } elseif (preg_match('/^\d+m$/', $duration)) {
                                        // Già ha "m" - mantieni così
                                        $formattedDuration = $duration;
                                    } elseif (preg_match('/(\d+)h?\s*(\d*)m?/', $duration, $matches)) {
                                        // Formato complesso con ore - converti tutto in minuti
                                        $hours = intval($matches[1]);
                                        $minutes = isset($matches[2]) && !empty($matches[2]) ? intval($matches[2]) : 0;
                                        $totalMinutes = ($hours * 60) + $minutes;
                                        
                                        if ($totalMinutes >= 60) {
                                            $h = floor($totalMinutes / 60);
                                            $m = $totalMinutes % 60;
                                            $formattedDuration = $m > 0 ? $h . 'h ' . $m . 'm' : $h . 'h';
                                        } else {
                                            $formattedDuration = $totalMinutes . 'm';
                                        }
                                    } else {
                                        // Fallback - usa valore originale
                                        $formattedDuration = $duration;
                                    }
                                    ?>
                                    <span class="badge-duration"><?= htmlspecialchars($formattedDuration) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row[8] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-display" style="font-size: 3rem; color: var(--bait-contrast-muted);"></i>
                    <h4 class="mt-3 mb-2">Nessun dato disponibile</h4>
                    <p class="bait-text-sm text-muted mb-4">File TeamViewer non trovati o vuoti</p>
                    
                    <div class="alert alert-info text-start">
                        <h6 class="bait-text-sm"><i class="bi bi-info-circle me-2"></i>Info Debug:</h6>
                        <ul class="bait-text-xs mb-0">
                            <li><strong>BAIT:</strong> <?= $debugInfo['bait_exists'] ? '✅' : '❌' ?> <?= basename($debugInfo['bait_path']) ?></li>
                            <li><strong>Gruppo:</strong> <?= $debugInfo['gruppo_exists'] ? '✅' : '❌' ?> <?= basename($debugInfo['gruppo_path']) ?></li>
                            <li><strong>Directory:</strong> <?= $debugInfo['input_dir_exists'] ? '✅' : '❌' ?> upload_csv/</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if (!empty($combinedData)): ?>
            $('#teamviewerTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i>Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i>PDF',
                        className: 'btn btn-danger btn-sm'
                    }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tutti"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
                },
                order: [[6, 'desc']], // Order by start time
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ]
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>