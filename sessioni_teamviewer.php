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

if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                ts.session_id,
                t.nome_completo as tecnico_nome,
                c.nome as cliente_nome,
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
        $hasDbData = count($dbData) > 0;
    } catch (Exception $e) {
        // Database error, will fall back to CSV
        $dbError = $e->getMessage();
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
    <title>🖥️ Sessioni TeamViewer - BAIT Service</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
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
            margin-top: 2rem;
        }
        
        .table-header {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .badge-source {
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .badge-bait { background-color: #17a2b8; }
        .badge-gruppo { background-color: #6f42c1; }
        
        .badge-user {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-session {
            background-color: #fd7e14;
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
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-desktop me-3"></i>Sessioni TeamViewer
                    </h1>
                    <p class="mb-0">Gestione sessioni remote TeamViewer (BAIT + Gruppo)</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="laravel_bait/public/index_standalone.php" class="btn btn-light btn-lg">
                        <i class="fas fa-dashboard me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="laravel_bait/public/index_standalone.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="breadcrumb-item active">Sessioni TeamViewer</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card sessions">
                    <h3 class="stats-number text-info"><?= number_format($totalSessions) ?></h3>
                    <p class="stats-label">Sessioni Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card duration">
                    <h3 class="stats-number text-success"><?= round($totalDuration / 60, 1) ?>h</h3>
                    <p class="stats-label">Durata Totale</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card users">
                    <h3 class="stats-number text-primary"><?= $uniqueUsers ?></h3>
                    <p class="stats-label">Utenti Unici</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card average">
                    <h3 class="stats-number text-warning"><?= $averageDuration ?>min</h3>
                    <p class="stats-label">Durata Media</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Sessioni TeamViewer
                        </h4>
                        <small>
                            Fonte dati: 
                            <?php if ($hasDbData): ?>
                                <span class="badge bg-success me-1">DATABASE ✓</span>
                                <span class="text-muted">(<?= count($dbData) ?> sessioni dal DB)</span>
                            <?php else: ?>
                                <?php if ($hasBaitCSV): ?>
                                    <span class="badge badge-source badge-bait me-1">BAIT CSV ✓</span>
                                <?php endif; ?>
                                <?php if ($hasGruppoCSV): ?>
                                    <span class="badge badge-source badge-gruppo">GRUPPO CSV ✓</span>
                                <?php endif; ?>
                                <?php if (!$hasBaitCSV && !$hasGruppoCSV): ?>
                                    <span class="text-danger">Nessun dato disponibile</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($hasDbData || $hasBaitCSV || $hasGruppoCSV): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Dati Disponibili
                        </span>
                        <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-exclamation-circle me-1"></i>Nessun Dato
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive p-3">
                <?php if (!empty($combinedData)): ?>
                <table id="teamviewerTable" class="table table-striped table-hover" style="width:100%">
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
                <div class="text-center p-5">
                    <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
                    <h4>Nessun dato disponibile</h4>
                    <p class="text-muted">I file teamviewer_bait.csv e teamviewer_gruppo.csv non sono stati trovati o sono vuoti.</p>
                    
                    <!-- Debug Panel -->
                    <div class="alert alert-info text-start mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Informazioni Debug:</h6>
                        <ul class="mb-0">
                            <li><strong>BAIT File:</strong> <?= $debugInfo['bait_exists'] ? '✅ Trovato' : '❌ Mancante' ?> (<?= basename($debugInfo['bait_path']) ?>)</li>
                            <li><strong>Gruppo File:</strong> <?= $debugInfo['gruppo_exists'] ? '✅ Trovato' : '❌ Mancante' ?> (<?= basename($debugInfo['gruppo_path']) ?>)</li>
                            <li><strong>Directory Input:</strong> <?= $debugInfo['input_dir_exists'] ? '✅ Esiste' : '❌ Mancante' ?></li>
                            <li><strong>Directory Leggibile:</strong> <?= $debugInfo['input_dir_readable'] ? '✅ Sì' : '❌ No' ?></li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <a href="laravel_bait/public/index_standalone.php" class="btn btn-primary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                        </a>
                        <a href="audit_monthly_manager.php" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Carica File CSV
                        </a>
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