<?php
/**
 * RICHIESTE PERMESSI - Visualizzazione permessi.csv
 * Gestione ferie e permessi dipendenti
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

$csvPath = __DIR__ . '/data/input/permessi.csv';
$hasCSV = file_exists($csvPath);
$csvData = $hasCSV ? readCSVFile($csvPath) : ['headers' => [], 'data' => []];
$totalRecords = count($csvData['data']);

// Calcola statistiche
$approvate = 0;
$pending = 0;
$ferie = 0;
$permessi = 0;

foreach ($csvData['data'] as $row) {
    if (count($row) > 5) {
        $stato = strtolower($row[5]);
        if (strpos($stato, 'approv') !== false) $approvate++;
        if (empty($stato) || $stato === 'pending') $pending++;
        
        $tipo = strtolower($row[2] ?? '');
        if (strpos($tipo, 'ferie') !== false) $ferie++;
        if (strpos($tipo, 'permesso') !== false) $permessi++;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 Richieste Permessi - BAIT Service</title>
    
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        
        .stats-card.total { border-left-color: #dc3545; }
        .stats-card.approved { border-left-color: #28a745; }
        .stats-card.vacation { border-left-color: #17a2b8; }
        .stats-card.permits { border-left-color: #ffc107; }
        
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
        
        #permessiTable {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #d0d7de;
        }
        
        #permessiTable thead th {
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
        
        #permessiTable thead th:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%);
        }
        
        #permessiTable thead th.sorting:after,
        #permessiTable thead th.sorting_asc:after,
        #permessiTable thead th.sorting_desc:after {
            opacity: 0.8;
            font-size: 0.8em;
        }
        
        #permessiTable tbody tr {
            transition: background-color 0.15s ease;
            border-bottom: 1px solid #e1e8ed;
        }
        
        #permessiTable tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        #permessiTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        #permessiTable tbody tr:hover {
            background-color: #dbeafe !important;
            border-color: #3b82f6;
        }
        
        #permessiTable tbody tr:hover td {
            border-color: #3b82f6;
        }
        
        #permessiTable tbody td {
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
        
        #permessiTable tbody td:hover {
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
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .badge-employee {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #495057;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
            min-width: 35px;
        }
        
        .badge-status-approved {
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
        
        .badge-status-rejected {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #b91c1c;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .badge-status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #d97706;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .badge-type-vacation {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #138496;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .badge-type-permission {
            background: linear-gradient(135deg, #6f42c1 0%, #5a379a 100%);
            color: white;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid #5a379a;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            margin-right: 0.5rem;
        }
        
        .cell-selected {
            background-color: #dbeafe !important;
            border: 2px solid #3b82f6 !important;
            outline: none;
        }
        
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
        
        @media (max-width: 768px) {
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            #permessiTable tbody td {
                font-size: 0.7rem;
                padding: 0.3rem 0.2rem;
            }
            
            .row-number {
                width: 35px;
                min-width: 35px;
                max-width: 35px;
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-check me-3"></i>Richieste Permessi
                    </h1>
                    <p class="mb-0">Gestione ferie e permessi dipendenti</p>
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
                <li class="breadcrumb-item active">Richieste Permessi</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <h3 class="stats-number text-danger"><?= number_format($totalRecords) ?></h3>
                    <p class="stats-label">Richieste Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card approved">
                    <h3 class="stats-number text-success"><?= $approvate ?></h3>
                    <p class="stats-label">Approvate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card vacation">
                    <h3 class="stats-number text-info"><?= $ferie ?></h3>
                    <p class="stats-label">Ferie</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card permits">
                    <h3 class="stats-number text-warning"><?= $permessi ?></h3>
                    <p class="stats-label">Permessi</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Richieste Permessi
                        </h4>
                        <small>File: <?= $hasCSV ? 'permessi.csv' : 'File non trovato' ?></small>
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
                <table id="permessiTable" class="table table-striped table-hover" style="width:100%">
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
                                    case 'Dipendente':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $initials = '';
                                            $words = explode(' ', $cell);
                                            foreach ($words as $word) {
                                                if (!empty($word)) {
                                                    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                                                }
                                            }
                                            $cellData = '<span class="badge-employee cell-tooltip" title="' . htmlspecialchars($cell) . '">' . $initials . '<div class="tooltip-content">' . htmlspecialchars($cell) . '</div></span>';
                                            $hasTooltip = true;
                                        } else {
                                            $cellData = '<span class="text-muted">-</span>';
                                        }
                                        break;
                                    case 'Tipo':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $badgeClass = strpos(strtolower($cell), 'ferie') !== false ? 'badge-type-vacation' : 'badge-type-permission';
                                            $cellData = '<span class="' . $badgeClass . '">' . htmlspecialchars(mb_substr($cell, 0, 15)) . '</span>';
                                            if (mb_strlen($cell) > 15) {
                                                $hasTooltip = true;
                                                $tooltipContent = htmlspecialchars($cell);
                                            }
                                        } else {
                                            $cellData = '<span class="text-muted">-</span>';
                                        }
                                        break;
                                    case 'Stato':
                                        $cellClass = 'text-center';
                                        if (!empty($cell)) {
                                            $stato = strtolower($cell);
                                            if (strpos($stato, 'approv') !== false) {
                                                $badgeClass = 'badge-status-approved';
                                            } elseif (strpos($stato, 'respint') !== false || strpos($stato, 'rifiut') !== false) {
                                                $badgeClass = 'badge-status-rejected';
                                            } else {
                                                $badgeClass = 'badge-status-pending';
                                            }
                                            $cellData = '<span class="' . $badgeClass . '">' . htmlspecialchars($cell) . '</span>';
                                        } else {
                                            $cellData = '<span class="badge-status-pending">Pending</span>';
                                        }
                                        break;
                                    case 'Data della richiesta':
                                    case 'Data inizio':
                                    case 'Data fine':
                                        $cellClass = 'text-center';
                                        if (!empty($cell) && $cell !== '0') {
                                            try {
                                                $dateObj = DateTime::createFromFormat('d/m/Y', $cell);
                                                if (!$dateObj) {
                                                    $dateObj = DateTime::createFromFormat('Y-m-d', $cell);
                                                }
                                                if ($dateObj) {
                                                    $cellData = '<span class="text-primary fw-medium">' . $dateObj->format('d/m/Y') . '</span>';
                                                } else {
                                                    $cellData = htmlspecialchars($cell);
                                                }
                                            } catch (Exception $e) {
                                                $cellData = htmlspecialchars($cell);
                                            }
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
                                <?php if ($hasTooltip && !empty($tooltipContent)): ?>
                                <div class="tooltip-content"><?= $tooltipContent ?></div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center p-5 no-data">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h4>Nessun dato disponibile</h4>
                    <p class="text-muted">Il file permessi.csv non è stato trovato o è vuoto.</p>
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
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if ($hasCSV && !empty($csvData['data'])): ?>
            // Initialize DataTable with Excel-style features
            var table = $('#permessiTable').DataTable({
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
                order: [[1, 'desc']], // Order by request date
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
            
            // Excel-like cell interactions
            var selectedCell = null;
            $('#permessiTable tbody td').on('click', function(e) {
                if ($(this).hasClass('row-number')) return;
                
                // Remove previous selection
                $('.cell-selected').removeClass('cell-selected');
                
                // Add selection to current cell
                $(this).addClass('cell-selected');
                selectedCell = $(this);
                
                e.stopPropagation();
            });
            
            // Clear selection when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#permessiTable').length) {
                    $('.cell-selected').removeClass('cell-selected');
                    selectedCell = null;
                }
            });
            
            // Double-click to copy cell content
            $('#permessiTable tbody td').on('dblclick', function() {
                if ($(this).hasClass('row-number')) return;
                
                var textContent = $(this).text().trim();
                if (textContent && textContent !== '-') {
                    navigator.clipboard.writeText(textContent).then(function() {
                        showToast('Contenuto copiato!', 'success');
                    }.bind(this)).catch(function() {
                        showToast('Errore nella copia', 'error');
                    });
                }
            });
            
            // Row highlighting on hover
            $('#permessiTable tbody tr').on('mouseenter', function() {
                $(this).find('.row-number').css('background', 'linear-gradient(180deg, #e8f0fe 0%, #c8d6e5 100%)');
            }).on('mouseleave', function() {
                $(this).find('.row-number').css('background', '');
            });
            
            // Column highlighting on header hover
            $('#permessiTable thead th').on('mouseenter', function() {
                var columnIndex = $(this).index();
                $('#permessiTable tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).addClass('column-highlight');
                });
            }).on('mouseleave', function() {
                $('#permessiTable tbody td').removeClass('column-highlight');
            });
            
            // Add column highlight CSS
            $('<style>').text(`
                .column-highlight {
                    background-color: #f0f9ff !important;
                    border-left: 2px solid #3b82f6 !important;
                    border-right: 2px solid #3b82f6 !important;
                }
            `).appendTo('head');
            
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
            <?php endif; ?>
        });
    </script>
</body>
</html>