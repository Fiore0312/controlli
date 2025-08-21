<?php
/**
 * CALENDARIO - Visualizzazione calendario.csv
 * Gestione calendario appuntamenti e pianificazione
 */

header('Content-Type: text/html; charset=utf-8');

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
    
    $headers = str_getcsv(array_shift($lines), ',');
    // Clean BOM if present
    if (!empty($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    
    $data = [];
    foreach ($lines as $line) {
        if (trim($line) && !strpos($line, 'Somma di Ore') && !strpos($line, 'Etichette')) {
            $row = str_getcsv($line, ',');
            // Skip summary rows
            if (count($row) >= 3 && !empty($row[1]) && !strpos($row[1], 'Totale')) {
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $data[] = array_slice($row, 0, count($headers));
            }
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

$csvPath = __DIR__ . '/data/input/calendario.csv';
$hasCSV = file_exists($csvPath);

// Debug info per troubleshooting
$debugInfo = [
    'file_path' => $csvPath,
    'file_exists' => $hasCSV,
    'input_dir_exists' => is_dir(__DIR__ . '/data/input/'),
    'input_dir_readable' => is_readable(__DIR__ . '/data/input/')
];
$csvData = $hasCSV ? readCSVFile($csvPath) : ['headers' => [], 'data' => []];

// Funzione robusta per parsare Outlook calendar - gestisce formati instabili
function parseOutlookCalendar($rawData) {
    // Controlla se il file ha un formato parsabile
    if (empty($rawData['data']) || count($rawData['data']) === 0) {
        return [
            'events' => [],
            'total_hours' => 0,
            'employees' => [],
            'clients' => [],
            'locations' => [],
            'parsing_status' => 'empty'
        ];
    }
    
    // Se il CSV ha troppe colonne su una riga (formato Outlook concatenato)
    $totalColumns = 0;
    foreach ($rawData['data'] as $row) {
        $totalColumns = max($totalColumns, count($row));
    }
    
    if ($totalColumns > 50) {
        // Formato Outlook speciale - troppo complesso per parsing affidabile
        return [
            'events' => [],
            'total_hours' => 0,
            'employees' => [],
            'clients' => [],
            'locations' => [],
            'parsing_status' => 'complex_outlook_format',
            'raw_data_available' => true,
            'total_columns' => $totalColumns,
            'suggestion' => 'Il file calendario.csv usa un formato Outlook specializzato. Per visualizzazione ottimale, esportare il calendario in formato CSV standard con righe separate per ogni evento.'
        ];
    }
    
    // Formato standard - procedi con parsing normale
    $parsedData = [];
    $totalHours = 0;
    $employees = [];
    $clients = [];
    $locations = [];
    
    foreach ($rawData['data'] as $row) {
        if (count($row) > 6 && !empty($row[0])) {
            $summary = $row[0] ?? '';
            $dtstart = $row[1] ?? '';
            $dtend = $row[2] ?? '';
            $attendee = $row[5] ?? '';
            $location = $row[6] ?? '';
            
            if ($summary === 'SUMMARY' || trim($summary) === '') {
                continue;
            }
            
            // Estrai dipendente
            $dipendente = '';
            if (!empty($attendee) && $attendee !== 'ATTENDEE') {
                $dipendente = $attendee;
            } elseif (preg_match('/- ([A-Za-z\s]+)$/', $summary, $matches)) {
                $dipendente = trim($matches[1]);
            } elseif (preg_match('/([A-Za-z]+\s+[A-Za-z]+)/', $summary, $matches)) {
                $dipendente = trim($matches[1]);
            }
            
            $cliente = $summary;
            if (preg_match('/^([^-]+)/', $summary, $matches)) {
                $cliente = trim($matches[1]);
            }
            
            $ore = 0;
            if (!empty($dtstart) && !empty($dtend)) {
                try {
                    $start = strtotime($dtstart);
                    $end = strtotime($dtend);
                    if ($start && $end && $end > $start) {
                        $ore = ($end - $start) / 3600;
                    }
                } catch (Exception $e) {
                    $ore = 0;
                }
            }
            
            if (!empty($dipendente)) $employees[$dipendente] = true;
            if (!empty($cliente)) $clients[$cliente] = true;
            if (!empty($location)) $locations[$location] = true;
            $totalHours += $ore;
            
            $parsedData[] = [
                'dipendente' => $dipendente,
                'cliente' => $cliente,
                'dove' => $location,
                'data_inizio' => $dtstart,
                'data_fine' => $dtend,
                'ore_totali' => $ore
            ];
        }
    }
    
    return [
        'events' => $parsedData,
        'total_hours' => $totalHours,
        'employees' => $employees,
        'clients' => $clients,
        'locations' => $locations,
        'parsing_status' => 'success'
    ];
}

// Parse Outlook calendar data
$calendarData = parseOutlookCalendar($csvData);
$totalRecords = count($calendarData['events']);
$totalHours = $calendarData['total_hours'];
$employees = $calendarData['employees'];
$clients = $calendarData['clients'];
$locations = $calendarData['locations'];

$uniqueEmployees = count($employees);
$uniqueClients = count($clients);
$uniqueLocations = count($locations);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Calendario - BAIT Service</title>
    
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
            background: linear-gradient(135deg, #fd7e14 0%, #e66500 100%);
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
        
        .stats-card.events { border-left-color: #fd7e14; }
        .stats-card.hours { border-left-color: #28a745; }
        .stats-card.employees { border-left-color: #6f42c1; }
        .stats-card.clients { border-left-color: #17a2b8; }
        
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
        
        .badge-employee {
            background-color: #6f42c1;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-client {
            background-color: #17a2b8;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-vacation {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-location {
            background-color: #fd7e14;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }
        
        .badge-hours {
            background-color: #28a745;
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
                        <i class="fas fa-calendar-alt me-3"></i>Calendario
                    </h1>
                    <p class="mb-0">Pianificazione appuntamenti e gestione calendario</p>
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
                <li class="breadcrumb-item active">Calendario</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card events">
                    <h3 class="stats-number text-warning"><?= number_format($totalRecords) ?></h3>
                    <p class="stats-label">Eventi Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="stats-number text-success"><?= number_format($totalHours, 1) ?>h</h3>
                    <p class="stats-label">Ore Pianificate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card employees">
                    <h3 class="stats-number text-primary"><?= $uniqueEmployees ?></h3>
                    <p class="stats-label">Dipendenti</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card clients">
                    <h3 class="stats-number text-info"><?= $uniqueClients ?></h3>
                    <p class="stats-label">Clienti/Eventi</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Eventi Calendario
                        </h4>
                        <small>File: <?= $hasCSV ? 'calendario.csv' : 'File non trovato' ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($hasCSV): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Dati Caricati
                        </span>
                        <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-exclamation-circle me-1"></i>File Mancante
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive p-3">
                <?php if ($hasCSV && !empty($calendarData['events'])): ?>
                <table id="calendarioTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Dipendente</th>
                            <th>Cliente/Evento</th>
                            <th>Dove</th>
                            <th>Data e Ora Inizio</th>
                            <th>Data e Ora Fine</th>
                            <th>Ore Totali</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendarData['events'] as $index => $event): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <?php if (!empty($event['dipendente'])): ?>
                                    <?php if (strpos($event['cliente'], 'Ferie') !== false): ?>
                                        <span class="badge-vacation"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php else: ?>
                                        <span class="badge-employee"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($event['cliente'])): ?>
                                    <span class="badge-client"><?= htmlspecialchars($event['cliente']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($event['dove'])): ?>
                                    <span class="badge-location" title="<?= htmlspecialchars($event['dove']) ?>">
                                        <?= htmlspecialchars(mb_substr($event['dove'], 0, 30)) ?><?= mb_strlen($event['dove']) > 30 ? '...' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($event['data_inizio'])) {
                                    try {
                                        echo date('d/m/Y H:i', strtotime($event['data_inizio']));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($event['data_inizio']);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($event['data_fine'])) {
                                    try {
                                        echo date('d/m/Y H:i', strtotime($event['data_fine']));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($event['data_fine']);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($event['ore_totali'] > 0): ?>
                                    <span class="badge-hours"><?= number_format($event['ore_totali'], 1) ?>h</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center p-5">
                    <?php if (isset($calendarData['parsing_status']) && $calendarData['parsing_status'] === 'complex_outlook_format'): ?>
                        <i class="fas fa-info-circle fa-3x text-warning mb-3"></i>
                        <h4>Formato Outlook Speciale Rilevato</h4>
                        <p class="text-muted">Il file calendario.csv usa un formato Outlook concatenato.</p>
                        <div class="alert alert-warning text-start mt-3">
                            <h6><i class="fas fa-lightbulb me-2"></i>Suggerimento:</h6>
                            <p class="mb-2"><?= $calendarData['suggestion'] ?></p>
                            <p class="mb-0"><strong>File rilevato:</strong> <?= $calendarData['total_columns'] ?> colonne in formato concatenato.</p>
                        </div>
                    <?php else: ?>
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h4>Nessun dato disponibile</h4>
                        <p class="text-muted">Il file calendario.csv non è stato trovato o è vuoto.</p>
                    <?php endif; ?>
                    
                    <!-- Debug Panel -->
                    <div class="alert alert-info text-start mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Informazioni Debug:</h6>
                        <ul class="mb-0">
                            <li><strong>File:</strong> <?= $debugInfo['file_exists'] ? '✅ Trovato' : '❌ Mancante' ?> (<?= basename($debugInfo['file_path']) ?>)</li>
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
            <?php if ($hasCSV && !empty($csvData['data'])): ?>
            $('#calendarioTable').DataTable({
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
                order: [[4, 'desc']], // Order by start date
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ]
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>