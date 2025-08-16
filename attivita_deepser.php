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
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #d0d7de;
        }
        
        #activitiesTable thead th {
            background: linear-gradient(180deg, #f6f8fa 0%, #e1e8ed 100%);
            border: 1px solid #d0d7de;
            border-bottom: 2px solid #8c959f;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: none;
            letter-spacing: 0.3px;
            padding: 0.6rem 0.4rem;
            vertical-align: middle;
            white-space: nowrap;
            position: relative;
            color: #24292f;
            text-align: left;
        }
        
        #activitiesTable thead th:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%);
        }
        
        #activitiesTable thead th.sorting:after,
        #activitiesTable thead th.sorting_asc:after,
        #activitiesTable thead th.sorting_desc:after {
            opacity: 0.8;
            font-size: 0.8em;
        }
        
        #activitiesTable tbody tr {
            transition: background-color 0.15s ease;
            border-bottom: 1px solid #e1e8ed;
        }
        
        #activitiesTable tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        #activitiesTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        #activitiesTable tbody tr:hover {
            background-color: #dbeafe !important;
            border-color: #3b82f6;
        }
        
        #activitiesTable tbody tr:hover td {
            border-color: #3b82f6;
        }
        
        #activitiesTable tbody td {
            padding: 0.4rem 0.4rem;
            font-size: 0.8rem;
            vertical-align: middle;
            border: 1px solid #e1e8ed;
            border-top: none;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.4;
            position: relative;
        }
        
        #activitiesTable tbody td:hover {
            background-color: #f0f9ff;
            cursor: pointer;
        }
        
        .row-number {
            background: linear-gradient(180deg, #f6f8fa 0%, #e1e8ed 100%) !important;
            font-weight: 600;
            text-align: center;
            color: #656d76;
            width: 45px;
            min-width: 45px;
            max-width: 45px;
            border-right: 2px solid #8c959f !important;
            font-size: 0.75rem;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        .row-number:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%) !important;
        }
        
        .badge-ticket {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #1e40af;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
            min-width: 40px;
        }
        
        .badge-company {
            background: linear-gradient(135deg, #ea580c 0%, #dc2626 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #dc2626;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .badge-activity {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #15803d;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .description-cell {
            max-width: 300px;
            cursor: pointer;
            position: relative;
        }
        
        .description-cell:hover {
            background-color: #fef3c7 !important;
            border-color: #f59e0b !important;
        }
        
        .description-cell:hover:after {
            content: '👁️ Clicca per dettagli';
            position: absolute;
            top: -25px;
            right: 0;
            background: #374151;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.65rem;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
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
        
        /* Excel-like column resize handle */
        #activitiesTable thead th {
            resize: horizontal;
            overflow: auto;
        }
        
        /* Enhanced cell selection */
        .cell-selected {
            background-color: #dbeafe !important;
            border: 2px solid #3b82f6 !important;
            outline: none;
        }
        
        /* Column header sorting indicators */
        #activitiesTable thead th.sorting {
            cursor: pointer;
        }
        
        #activitiesTable thead th.sorting:hover {
            background: linear-gradient(180deg, #e8f0fe 0%, #c8d6e5 100%);
        }
        
        /* Better tooltips */
        .cell-tooltip {
            position: relative;
            overflow: visible;
        }
        
        .cell-tooltip .tooltip-content {
            visibility: hidden;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #374151;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            max-width: 300px;
            word-wrap: break-word;
            white-space: normal;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .cell-tooltip:hover .tooltip-content {
            visibility: visible;
        }
        
        .cell-tooltip .tooltip-content::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #374151 transparent transparent transparent;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            #activitiesTable tbody td {
                font-size: 0.7rem;
                padding: 0.3rem 0.2rem;
            }
            
            .row-number {
                width: 35px;
                min-width: 35px;
                max-width: 35px;
                font-size: 0.65rem;
            }
            
            .description-cell:hover:after {
                display: none;
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
                            <?php
                                $header = isset($csvData['headers'][$colIndex]) ? $csvData['headers'][$colIndex] : '';
                                $cellClass = '';
                                $cellData = '';
                                $hasTooltip = false;
                                $tooltipContent = '';
                                
                                // Determine cell styling and content
                                switch ($header) {
                                    case 'Id Ticket':
                                        $cellClass = 'text-center';
                                        $cellData = !empty($cell) ? '<span class="badge-ticket">#' . htmlspecialchars($cell) . '</span>' : '<span class="text-muted">-</span>';
                                        break;
                                    case 'Azienda':
                                        $cellClass = 'text-center';
                                        $cellData = !empty($cell) ? '<span class="badge-company">' . htmlspecialchars(mb_substr($cell, 0, 20)) . '</span>' : '<span class="text-muted">-</span>';
                                        if (mb_strlen($cell) > 20) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                        break;
                                    case 'Tipologia Attività':
                                        $cellClass = 'text-center';
                                        $cellData = !empty($cell) ? '<span class="badge-activity">' . htmlspecialchars(mb_substr($cell, 0, 15)) . '</span>' : '<span class="text-muted">-</span>';
                                        if (mb_strlen($cell) > 15) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                        break;
                                    case 'Descrizione':
                                        $cellClass = 'description-cell';
                                        $cellData = htmlspecialchars(mb_substr($cell, 0, 45) . (mb_strlen($cell) > 45 ? '...' : ''));
                                        if (mb_strlen($cell) > 45) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                        break;
                                    case 'Iniziata il':
                                    case 'Conclusa il':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $dateObj = DateTime::createFromFormat('d/m/Y H:i', $cell);
                                            if ($dateObj) {
                                                $cellData = '<span class="text-primary fw-medium">' . $dateObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $dateObj->format('H:i') . '</small>';
                                            } else {
                                                $cellData = htmlspecialchars($cell);
                                            }
                                        } else {
                                            $cellData = '<span class="text-muted">-</span>';
                                        }
                                        break;
                                    case 'Durata':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $cellData = '<span class="fw-bold text-success">' . htmlspecialchars($cell) . '</span>';
                                        } else {
                                            $cellData = '<span class="text-muted">-</span>';
                                        }
                                        break;
                                    case 'Creato da':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $initials = '';
                                            $words = explode(' ', $cell);
                                            foreach ($words as $word) {
                                                if (!empty($word)) {
                                                    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                                                }
                                            }
                                            $cellData = '<span class="badge bg-secondary" title="' . htmlspecialchars($cell) . '">' . $initials . '</span>';
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        } else {
                                            $cellData = '<span class="text-muted">-</span>';
                                        }
                                        break;
                                    default:
                                        $cellData = !empty($cell) ? htmlspecialchars($cell) : '<span class="text-muted">-</span>';
                                        if (mb_strlen($cell) > 30) {
                                            $cellData = htmlspecialchars(mb_substr($cell, 0, 30) . '...');
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                }
                            ?>
                            <td class="<?= $cellClass ?> <?= $hasTooltip ? 'cell-tooltip' : '' ?>" data-header="<?= htmlspecialchars($header) ?>">
                                <?= $cellData ?>
                                <?php if ($hasTooltip): ?>
                                <div class="tooltip-content"><?= $tooltipContent ?></div>
                                <?php endif; ?>
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
                    { 
                        orderable: false, 
                        targets: 0,
                        className: 'row-number'
                    }, // Disable ordering on row number column
                    {
                        targets: '_all',
                        className: 'cell-content'
                    }
                ],
                scrollX: true,
                scrollY: '60vh',
                scrollCollapse: true,
                fixedColumns: {
                    leftColumns: 1
                }
            });
            
            // Enhanced cell interactions
            $('#activitiesTable').on('click', '.description-cell', function() {
                var tooltipContent = $(this).find('.tooltip-content').text();
                if (tooltipContent && tooltipContent.length > 45) {
                    // Create a professional modal-style popup
                    var modalContent = `
                        <div class="modal fade" id="descriptionModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title"><i class="fas fa-file-text me-2"></i>Descrizione Completa</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="bg-light p-3 rounded">
                                            <p class="mb-0" style="line-height: 1.6; font-size: 0.95rem;">${tooltipContent}</p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Chiudi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remove existing modal if any
                    $('#descriptionModal').remove();
                    
                    // Add and show modal
                    $('body').append(modalContent);
                    $('#descriptionModal').modal('show');
                }
            });
            
            // Cell selection functionality (Excel-like)
            var selectedCell = null;
            $('#activitiesTable tbody td').on('click', function(e) {
                if ($(this).hasClass('description-cell')) return; // Skip description cells
                
                // Remove previous selection
                $('.cell-selected').removeClass('cell-selected');
                
                // Add selection to current cell
                $(this).addClass('cell-selected');
                selectedCell = $(this);
                
                e.stopPropagation();
            });
            
            // Clear selection when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#activitiesTable').length) {
                    $('.cell-selected').removeClass('cell-selected');
                    selectedCell = null;
                }
            });
            
            // Keyboard navigation (Excel-like)
            $(document).on('keydown', function(e) {
                if (selectedCell && selectedCell.length) {
                    var currentRow = selectedCell.parent();
                    var currentCellIndex = selectedCell.index();
                    var newCell = null;
                    
                    switch(e.keyCode) {
                        case 37: // Left arrow
                            newCell = selectedCell.prev();
                            break;
                        case 38: // Up arrow
                            newCell = currentRow.prev().find('td').eq(currentCellIndex);
                            break;
                        case 39: // Right arrow
                            newCell = selectedCell.next();
                            break;
                        case 40: // Down arrow
                            newCell = currentRow.next().find('td').eq(currentCellIndex);
                            break;
                    }
                    
                    if (newCell && newCell.length && !newCell.hasClass('row-number')) {
                        $('.cell-selected').removeClass('cell-selected');
                        newCell.addClass('cell-selected');
                        selectedCell = newCell;
                        
                        // Scroll into view if needed
                        newCell[0].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                        
                        e.preventDefault();
                    }
                }
            });
            <?php endif; ?>
            
            // Enhanced table features
            
            // Double-click to copy cell content
            $('#activitiesTable tbody td').on('dblclick', function() {
                if ($(this).hasClass('row-number')) return;
                
                var textContent = $(this).text().trim();
                if (textContent && textContent !== '-') {
                    navigator.clipboard.writeText(textContent).then(function() {
                        // Show copy feedback
                        var originalBg = $(this).css('background-color');
                        $(this).css('background-color', '#d4edda');
                        setTimeout(() => {
                            $(this).css('background-color', originalBg);
                        }, 200);
                        
                        // Show toast notification
                        showToast('Contenuto copiato!', 'success');
                    }.bind(this)).catch(function() {
                        showToast('Errore nella copia', 'error');
                    });
                }
            });
            
            // Row highlighting on hover
            $('#activitiesTable tbody tr').on('mouseenter', function() {
                $(this).find('.row-number').css('background', 'linear-gradient(180deg, #e8f0fe 0%, #c8d6e5 100%)');
            }).on('mouseleave', function() {
                $(this).find('.row-number').css('background', '');
            });
            
            // Column highlighting on header hover
            $('#activitiesTable thead th').on('mouseenter', function() {
                var columnIndex = $(this).index();
                $('#activitiesTable tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).addClass('column-highlight');
                });
            }).on('mouseleave', function() {
                $('#activitiesTable tbody td').removeClass('column-highlight');
            });
            
            // Add column highlight CSS
            $('<style>').text(`
                .column-highlight {
                    background-color: #f0f9ff !important;
                    border-left: 2px solid #3b82f6 !important;
                    border-right: 2px solid #3b82f6 !important;
                }
            `).appendTo('head');
            
            // Hide loading
            setTimeout(function() {
                $('.loading').hide();
            }, 500);
            
            // Toast notification function
            function showToast(message, type = 'info') {
                var toastClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
                var toast = $(`
                    <div class="toast align-items-center text-white ${toastClass} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                
                $('body').append(toast);
                var bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        });
    </script>
</body>
</html>