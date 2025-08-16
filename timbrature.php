<?php
/**
 * TIMBRATURE - Visualizzazione timbrature.csv
 * Gestione timbrature e controllo orari
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
        if (trim($line) && !strpos($line, 'Sblocco stop')) {
            $row = str_getcsv($line, ';');
            // Skip invalid rows
            if (count($row) >= 5 && !empty($row[0]) && !strpos($row[0], '---')) {
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $data[] = array_slice($row, 0, count($headers));
            }
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

$csvPath = __DIR__ . '/data/input/timbrature.csv';
$hasCSV = file_exists($csvPath);
$csvData = $hasCSV ? readCSVFile($csvPath) : ['headers' => [], 'data' => []];
$totalRecords = count($csvData['data']);

// Calcola statistiche
$totalHours = 0;
$employees = [];
$clients = [];

foreach ($csvData['data'] as $row) {
    if (count($row) > 10) {
        // Employee
        if (!empty($row[0]) && !empty($row[1])) {
            $employee = trim($row[0] . ' ' . $row[1]);
            $employees[$employee] = true;
        }
        
        // Client
        if (!empty($row[3])) {
            $clients[$row[3]] = true;
        }
        
        // Hours - try different columns that might contain hours
        if (!empty($row[10])) {
            // Try to parse time format
            if (preg_match('/(\d+):(\d+):(\d+)/', $row[10], $matches)) {
                $hours = intval($matches[1]) + (intval($matches[2]) / 60) + (intval($matches[3]) / 3600);
                $totalHours += $hours;
            }
        }
    }
}

$uniqueEmployees = count($employees);
$uniqueClients = count($clients);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⏰ Timbrature - Vista Excel</title>
    
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
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
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
        
        .stats-card.records { border-left-color: #6f42c1; }
        .stats-card.hours { border-left-color: #28a745; }
        .stats-card.employees { border-left-color: #17a2b8; }
        .stats-card.clients { border-left-color: #fd7e14; }
        
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
        
        #timbratureTable {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #d0d7de;
        }
        
        #timbratureTable thead th {
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
        
        #timbratureTable thead th:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%);
        }
        
        #timbratureTable thead th.sorting:after,
        #timbratureTable thead th.sorting_asc:after,
        #timbratureTable thead th.sorting_desc:after {
            opacity: 0.8;
            font-size: 0.8em;
        }
        
        #timbratureTable tbody tr {
            transition: background-color 0.15s ease;
            border-bottom: 1px solid #e1e8ed;
        }
        
        #timbratureTable tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        #timbratureTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        #timbratureTable tbody tr:hover {
            background-color: #dbeafe !important;
            border-color: #3b82f6;
        }
        
        #timbratureTable tbody tr:hover td {
            border-color: #3b82f6;
        }
        
        #timbratureTable tbody td {
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
        
        #timbratureTable tbody td:hover {
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
        
        .badge-client {
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
        
        .hours-display {
            font-weight: bold;
            color: #16a34a;
            font-size: 0.85rem;
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
        
        @media (max-width: 768px) {
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            #timbratureTable tbody td {
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
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-clock me-3"></i>Timbrature
                    </h1>
                    <p class="mb-0">Controllo orari e timbrature dipendenti</p>
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
                <li class="breadcrumb-item active">Timbrature</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card records">
                    <h3 class="stats-number text-primary"><?= number_format($totalRecords) ?></h3>
                    <p class="stats-label">Timbrature Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="stats-number text-success"><?= number_format($totalHours, 1) ?>h</h3>
                    <p class="stats-label">Ore Lavorate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card employees">
                    <h3 class="stats-number text-info"><?= number_format($uniqueEmployees) ?></h3>
                    <p class="stats-label">Dipendenti</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card clients">
                    <h3 class="stats-number text-warning"><?= number_format($uniqueClients) ?></h3>
                    <p class="stats-label">Clienti</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Dati Timbrature
                        </h4>
                        <small>File: <?= $hasCSV ? 'timbrature.csv' : 'File non trovato' ?></small>
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
                <table id="timbratureTable" class="table table-striped table-hover" style="width:100%">
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
                                
                                // Determine cell styling and content based on common column patterns
                                if (strpos(strtolower($header), 'nome') !== false || strpos(strtolower($header), 'dipendente') !== false || 
                                    ($colIndex <= 1 && !empty($row[0]) && !empty($row[1]))) {
                                    // Employee name columns
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        $employeeName = $colIndex == 0 && !empty($row[1]) ? trim($cell . ' ' . $row[1]) : $cell;
                                        $initials = '';
                                        $words = explode(' ', $employeeName);
                                        foreach ($words as $word) {
                                            if (!empty($word)) {
                                                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                                            }
                                        }
                                        $cellData = '<span class="badge-employee cell-tooltip" title="' . htmlspecialchars($employeeName) . '">' . $initials . '<div class="tooltip-content">' . htmlspecialchars($employeeName) . '</div></span>';
                                        $hasTooltip = true;
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (strpos(strtolower($header), 'cliente') !== false || strpos(strtolower($header), 'azienda') !== false) {
                                    // Client/Company columns
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        $cellData = '<span class="badge-client">' . htmlspecialchars(mb_substr($cell, 0, 20)) . '</span>';
                                        if (mb_strlen($cell) > 20) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (strpos(strtolower($header), 'attivit') !== false || strpos(strtolower($header), 'tipologia') !== false) {
                                    // Activity columns
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        $cellData = '<span class="badge-activity">' . htmlspecialchars(mb_substr($cell, 0, 15)) . '</span>';
                                        if (mb_strlen($cell) > 15) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (strpos(strtolower($header), 'ora') !== false || strpos(strtolower($header), 'time') !== false || 
                                         strpos(strtolower($header), 'inizio') !== false || strpos(strtolower($header), 'fine') !== false ||
                                         preg_match('/(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4})/', $cell)) {
                                    // Time/Date columns
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        // Try to parse as datetime first
                                        try {
                                            if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?)/', $cell)) {
                                                $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $cell);
                                                if (!$dateObj) $dateObj = DateTime::createFromFormat('Y-m-d H:i', $cell);
                                                if ($dateObj) {
                                                    $cellData = '<span class="text-primary fw-medium">' . $dateObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $dateObj->format('H:i') . '</small>';
                                                } else {
                                                    $cellData = htmlspecialchars($cell);
                                                }
                                            } elseif (preg_match('/(\d{2}:\d{2}(:\d{2})?)/', $cell)) {
                                                $cellData = '<span class="text-info fw-medium">' . htmlspecialchars($cell) . '</span>';
                                            } else {
                                                $cellData = htmlspecialchars($cell);
                                            }
                                        } catch (Exception $e) {
                                            $cellData = htmlspecialchars($cell);
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (strpos(strtolower($header), 'ore') !== false || strpos(strtolower($header), 'hour') !== false ||
                                         preg_match('/(\d+)h\s*(\d+)?m?/', $cell) || preg_match('/(\d+):(\d+):(\d+)/', $cell)) {
                                    // Hours/Duration columns
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        if (preg_match('/(\d+):(\d+):(\d+)/', $cell, $matches)) {
                                            $cellData = '<span class="hours-display">' . $matches[1] . 'h ' . $matches[2] . 'm</span>';
                                        } elseif (preg_match('/(\d+(?:\.\d+)?)/', $cell, $matches)) {
                                            $hours = floatval($matches[1]);
                                            $h = floor($hours);
                                            $m = round(($hours - $h) * 60);
                                            $cellData = '<span class="hours-display">' . $h . 'h ' . $m . 'm</span>';
                                        } else {
                                            $cellData = '<span class="hours-display">' . htmlspecialchars($cell) . '</span>';
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (strpos(strtolower($header), 'descri') !== false || strpos(strtolower($header), 'note') !== false) {
                                    // Description columns
                                    $cellClass = 'description-cell';
                                    if (!empty($cell)) {
                                        $cellData = htmlspecialchars(mb_substr($cell, 0, 45) . (mb_strlen($cell) > 45 ? '...' : ''));
                                        if (mb_strlen($cell) > 45) {
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell);
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } else {
                                    // Default cell formatting
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
                <div class="text-center p-5">
                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                    <h4>Nessun dato disponibile</h4>
                    <p class="text-muted">Il file timbrature.csv non è stato trovato o è vuoto.</p>
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
            $('#timbratureTable').DataTable({
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
                order: [[4, 'desc']], // Order by start time
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ],
                scrollX: true
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>