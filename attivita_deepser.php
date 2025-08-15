<?php
/**
 * ATTIVITÀ DEEPSER - Visualizzazione CSV Stile Excel
 * Pagina per visualizzare attivita.csv in formato tabellare Excel-like
 * Integrata con sistema BAIT per confronti immediati
 */

header('Content-Type: text/html; charset=utf-8');

// Configurazione paths
$csvPath = __DIR__ . '/data/input/attivita.csv';
$hasCSV = file_exists($csvPath);

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
    $data = [];
    
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line, ';');
            // Pad array to match headers count
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $data[] = array_slice($row, 0, count($headers));
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

$csvData = $hasCSV ? readCSVFile($csvPath) : ['headers' => [], 'data' => []];
$totalRecords = count($csvData['data']);

// Calcola statistiche
$totalHours = 0;
$companies = [];
$technicians = [];

foreach ($csvData['data'] as $row) {
    // Trova durata (assumendo che sia in una delle colonne)
    if (count($row) > 8 && !empty($row[8])) {
        // Parsing durata formato "1h 30m" o simili
        if (preg_match('/(\d+)h?\s*(\d*)\s*m?/', $row[8], $matches)) {
            $hours = intval($matches[1]);
            $minutes = isset($matches[2]) && !empty($matches[2]) ? intval($matches[2]) : 0;
            $totalHours += $hours + ($minutes / 60);
        }
    }
    
    // Conta aziende uniche (colonna Azienda)
    if (count($row) > 4 && !empty($row[4])) {
        $companies[$row[4]] = true;
    }
    
    // Conta tecnici (colonna Creato da)
    if (count($row) > 10 && !empty($row[10])) {
        $technicians[$row[10]] = true;
    }
}

$uniqueCompanies = count($companies);
$uniqueTechnicians = count($technicians);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Attività Deepser - Vista Excel</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
        
        .stats-card.records { border-left-color: #0d6efd; }
        .stats-card.hours { border-left-color: #198754; }
        .stats-card.companies { border-left-color: #fd7e14; }
        .stats-card.technicians { border-left-color: #6f42c1; }
        
        .stats-number {
            font-size: 2.2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
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
        
        #activitiesTable {
            margin: 0;
        }
        
        #activitiesTable thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem 0.5rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        
        #activitiesTable tbody tr {
            transition: background-color 0.2s;
        }
        
        #activitiesTable tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        #activitiesTable tbody tr:hover {
            background-color: #e3f2fd !important;
        }
        
        #activitiesTable tbody td {
            padding: 0.5rem;
            font-size: 0.85rem;
            vertical-align: middle;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .row-number {
            background-color: #e9ecef;
            font-weight: bold;
            text-align: center;
            color: #495057;
            width: 50px;
            min-width: 50px;
        }
        
        .badge-ticket {
            background-color: #0d6efd;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-company {
            background-color: #fd7e14;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-activity {
            background-color: #198754;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .description-cell {
            max-width: 300px;
            cursor: pointer;
        }
        
        .description-cell:hover {
            background-color: #fff3cd;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            margin-right: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            #activitiesTable tbody td {
                font-size: 0.75rem;
                padding: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Caricamento...</span>
            </div>
            <p class="mt-2">Caricamento dati...</p>
        </div>
    </div>

    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-table me-3"></i>Attività Deepser
                    </h1>
                    <p class="mb-0">Visualizzazione dati CSV in formato Excel per confronti immediati</p>
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
                <li class="breadcrumb-item active">Attività Deepser</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card records">
                    <h3 class="stats-number text-primary"><?= number_format($totalRecords) ?></h3>
                    <p class="stats-label">Record Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="stats-number text-success"><?= number_format($totalHours, 1) ?>h</h3>
                    <p class="stats-label">Ore Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card companies">
                    <h3 class="stats-number text-warning"><?= $uniqueCompanies ?></h3>
                    <p class="stats-label">Aziende</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card technicians">
                    <h3 class="stats-number text-info"><?= $uniqueTechnicians ?></h3>
                    <p class="stats-label">Tecnici</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Dati Attività
                        </h4>
                        <small>File: <?= $hasCSV ? 'attivita.csv' : 'File non trovato' ?></small>
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
                <?php if ($hasCSV && !empty($csvData['data'])): ?>
                <table id="activitiesTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th class="row-number">#</th>
                            <?php foreach ($csvData['headers'] as $header): ?>
                            <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($csvData['data'] as $index => $row): ?>
                        <tr>
                            <td class="row-number"><?= $index + 1 ?></td>
                            <?php foreach ($row as $colIndex => $cell): ?>
                            <td <?= (isset($csvData['headers'][$colIndex]) && $csvData['headers'][$colIndex] === 'Descrizione') ? 'class="description-cell"' : '' ?>
                                <?= (isset($csvData['headers'][$colIndex]) && $csvData['headers'][$colIndex] === 'Descrizione') ? 'title="'.htmlspecialchars($cell).'"' : '' ?>>
                                <?php
                                // Format special columns
                                if (isset($csvData['headers'][$colIndex])) {
                                    $header = $csvData['headers'][$colIndex];
                                    switch ($header) {
                                        case 'Id Ticket':
                                            echo !empty($cell) ? '<span class="badge-ticket">#' . htmlspecialchars($cell) . '</span>' : '';
                                            break;
                                        case 'Azienda':
                                            echo !empty($cell) ? '<span class="badge-company">' . htmlspecialchars($cell) . '</span>' : '';
                                            break;
                                        case 'Tipologia Attività':
                                            echo !empty($cell) ? '<span class="badge-activity">' . htmlspecialchars($cell) . '</span>' : '';
                                            break;
                                        case 'Descrizione':
                                            echo htmlspecialchars(mb_substr($cell, 0, 50) . (mb_strlen($cell) > 50 ? '...' : ''));
                                            break;
                                        default:
                                            echo htmlspecialchars($cell);
                                    }
                                } else {
                                    echo htmlspecialchars($cell);
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-file-excel fa-3x text-muted mb-3"></i>
                    <h4>Nessun dato disponibile</h4>
                    <p class="text-muted">Il file attivita.csv non è stato trovato o è vuoto.</p>
                    <a href="laravel_bait/public/index_standalone.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                    </a>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>

    <script>
        $(document).ready(function() {
            // Show loading
            $('.loading').show();
            
            <?php if ($hasCSV && !empty($csvData['data'])): ?>
            // Initialize DataTable
            var table = $('#activitiesTable').DataTable({
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
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i>Stampa',
                        className: 'btn btn-secondary btn-sm'
                    }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tutti"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
                },
                fixedHeader: true,
                responsive: true,
                order: [[1, 'desc']], // Order by first data column (ID Ticket)
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ]
            });
            
            // Click on description cell to show full text
            $('#activitiesTable').on('click', '.description-cell', function() {
                var fullText = $(this).attr('title');
                if (fullText && fullText.length > 50) {
                    alert('Descrizione completa:\n\n' + fullText);
                }
            });
            <?php endif; ?>
            
            // Hide loading
            setTimeout(function() {
                $('.loading').hide();
            }, 500);
        });
    </script>
</body>
</html>