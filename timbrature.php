<?php
/**
 * TIMBRATURE - Visualizzazione timbrature.csv SEMPLIFICATA
 * Gestione timbrature e controllo orari con filtro colonne automatico
 */

header('Content-Type: text/html; charset=utf-8');

// Helper functions for column type detection
class TimbratureParser {
    
    // Real timestamp columns (actual date/time values)
    public static function isTimestampColumn($header) {
        $timestampColumns = [
            'ora inizio',
            'ora fine', 
            'ora inizio arrotondata',
            'ora fine arrotondata'
        ];
        
        $header = strtolower(trim($header));
        foreach ($timestampColumns as $col) {
            if (strpos($header, $col) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // Duration/calculation columns (Excel time calculations) - RIDOTTE
    public static function isDurationColumn($header) {
        $durationColumns = [
            'ore',
            'ore arrotondate', 
            'sessantesimi al netto delle pause',
            'sessantesimi arrotondati al netto delle pause',
            'pausa sessantesimi'
        ];
        
        $header = strtolower(trim($header));
        foreach ($durationColumns as $col) {
            if (strpos($header, $col) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

// Lista colonne da escludere dalla visualizzazione
function getExcludedColumns() {
    return [
        'attività',
        'descrizione attività', 
        'attività descrizione',
        'ore in centesimi',
        'ore arrotondate in centesimi', 
        'pausa centesimi',
        'centesimi al netto delle pause',
        'centesimi arrotondati al netto delle pause'
    ];
}

// Controlla se una colonna deve essere esclusa
function shouldExcludeColumn($header) {
    $excludedColumns = getExcludedColumns();
    $headerLower = strtolower(trim($header));
    
    foreach ($excludedColumns as $excluded) {
        if (strpos($headerLower, strtolower($excluded)) !== false) {
            return true;
        }
    }
    return false;
}

// Funzione per leggere CSV con gestione encoding e filtro colonne
function readCSVFile($filepath) {
    if (!file_exists($filepath)) {
        return ['headers' => [], 'data' => [], 'excluded_columns' => []];
    }
    
    $csvContent = file_get_contents($filepath);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    $lines = str_getcsv($csvContent, "\n");
    if (empty($lines)) return ['headers' => [], 'data' => [], 'excluded_columns' => []];
    
    $originalHeaders = str_getcsv(array_shift($lines), ';');
    // Clean BOM if present
    if (!empty($originalHeaders[0])) {
        $originalHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $originalHeaders[0]);
    }
    
    // Filtro headers e identifica indici colonne da mantenere
    $filteredHeaders = [];
    $validColumnIndices = [];
    $excludedColumns = [];
    
    foreach ($originalHeaders as $index => $header) {
        if (!shouldExcludeColumn($header)) {
            $filteredHeaders[] = $header;
            $validColumnIndices[] = $index;
        } else {
            $excludedColumns[] = $header;
        }
    }
    
    $data = [];
    foreach ($lines as $line) {
        if (trim($line) && !strpos($line, 'Sblocco stop')) {
            $row = str_getcsv($line, ';');
            // Skip invalid rows
            if (count($row) >= 5 && !empty($row[0]) && !strpos($row[0], '---')) {
                // Filtra solo le colonne valide
                $filteredRow = [];
                foreach ($validColumnIndices as $validIndex) {
                    $filteredRow[] = isset($row[$validIndex]) ? $row[$validIndex] : '';
                }
                $data[] = $filteredRow;
            }
        }
    }
    
    return [
        'headers' => $filteredHeaders, 
        'data' => $data, 
        'excluded_columns' => $excludedColumns,
        'original_column_count' => count($originalHeaders),
        'filtered_column_count' => count($filteredHeaders)
    ];
}

$csvPath = __DIR__ . '/data/input/timbrature.csv';
$hasCSV = file_exists($csvPath);
$csvData = $hasCSV ? readCSVFile($csvPath) : ['headers' => [], 'data' => [], 'excluded_columns' => []];
$totalRecords = count($csvData['data']);

// Calcola statistiche
$totalHours = 0;
$employees = [];
$clients = [];

foreach ($csvData['data'] as $row) {
    if (count($row) > 5) {
        // Employee
        if (!empty($row[0]) && !empty($row[1])) {
            $employee = trim($row[0] . ' ' . $row[1]);
            $employees[$employee] = true;
        }
        
        // Client - cerca in diverse colonne possibili
        $clientFound = false;
        for ($i = 3; $i <= 6 && $i < count($row); $i++) {
            if (!empty($row[$i]) && !$clientFound) {
                $clients[$row[$i]] = true;
                $clientFound = true;
            }
        }
        
        // Hours - cerca in diverse colonne per ore valide
        for ($i = 6; $i < count($row) && $i < 12; $i++) {
            if (!empty($row[$i])) {
                // Parse time format H:i:s
                if (preg_match('/(\d+):(\d+):(\d+)/', $row[$i], $matches)) {
                    $hours = intval($matches[1]) + (intval($matches[2]) / 60) + (intval($matches[3]) / 3600);
                    if ($hours > 0 && $hours <= 24) { // Ore realistiche
                        $totalHours += $hours;
                        break; // Una volta trovata, non cercare altre colonne
                    }
                }
                // Parse decimal format ma esclude valori corrotti
                elseif (preg_match('/^(\d{1,2}(?:\.\d{1,2})?)$/', $row[$i], $matches)) {
                    $hours = floatval($matches[1]);
                    if ($hours > 0 && $hours <= 24) {
                        $totalHours += $hours;
                        break;
                    }
                }
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
    <title>⏰ Timbrature - Vista Semplificata</title>
    
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
        .stats-card.filtered { border-left-color: #6c757d; }
        
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
            font-size: 0.9rem;
            text-transform: none;
            letter-spacing: 0.3px;
            padding: 0.8rem 0.6rem;
            vertical-align: middle;
            white-space: nowrap;
            position: relative;
            color: #24292f;
            text-align: left;
        }
        
        #timbratureTable thead th:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%);
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
        
        #timbratureTable tbody td {
            padding: 0.6rem 0.6rem;
            font-size: 0.85rem;
            vertical-align: middle;
            border: 1px solid #e1e8ed;
            border-top: none;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.4;
            position: relative;
        }
        
        .row-number {
            background: linear-gradient(180deg, #f6f8fa 0%, #e1e8ed 100%) !important;
            font-weight: 600;
            text-align: center;
            color: #656d76;
            width: 50px;
            min-width: 50px;
            max-width: 50px;
            border-right: 2px solid #8c959f !important;
            font-size: 0.8rem;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        .badge-employee {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #495057;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
            min-width: 40px;
        }
        
        .badge-client {
            background: linear-gradient(135deg, #ea580c 0%, #dc2626 100%);
            color: white;
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #dc2626;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            display: inline-block;
            text-align: center;
        }
        
        .hours-display {
            font-weight: bold;
            color: #16a34a;
            font-size: 0.9rem;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
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
        
        .filter-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #0d47a1;
        }
        
        @media (max-width: 768px) {
            .stats-row .col-md-3 {
                margin-bottom: 1rem;
            }
            
            #timbratureTable tbody td {
                font-size: 0.75rem;
                padding: 0.4rem 0.3rem;
            }
            
            .row-number {
                width: 40px;
                min-width: 40px;
                max-width: 40px;
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
                        <i class="fas fa-clock me-3"></i>Timbrature Semplificate
                    </h1>
                    <p class="mb-0">Visualizzazione ottimizzata senza colonne ridondanti</p>
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

        <!-- Filter Information -->
        <?php if (!empty($csvData['excluded_columns'])): ?>
        <div class="filter-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2"><i class="fas fa-filter me-2"></i>Filtro Attivo</h6>
                    <p class="mb-0">
                        <strong><?= count($csvData['excluded_columns']) ?> colonne</strong> sono state nascoste per semplificare la visualizzazione:
                        <em><?= implode(', ', array_slice($csvData['excluded_columns'], 0, 3)) ?><?= count($csvData['excluded_columns']) > 3 ? ', e altre...' : '' ?></em>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <small class="text-muted">
                        Mostrando <?= count($csvData['headers']) ?> di <?= $csvData['original_column_count'] ?? count($csvData['headers']) ?> colonne
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>Dati Caricati
                            </span>
                            <?php if (!empty($csvData['excluded_columns'])): ?>
                            <span class="badge bg-info" title="Colonne filtrate: <?= htmlspecialchars(implode(', ', $csvData['excluded_columns'])) ?>">
                                <i class="fas fa-filter me-1"></i><?= count($csvData['excluded_columns']) ?> filtrate
                            </span>
                            <?php endif; ?>
                        </div>
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
                                    // Client/Company columns - esclude A&B Consulting dalle visualizzazioni
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        // Evidenzia A&B Consulting come azienda interna
                                        if (stripos($cell, 'A&B Consulting') !== false || stripos($cell, 'A & B Consulting') !== false) {
                                            $cellData = '<span class="badge bg-secondary text-white" title="Azienda interna - Sede">🏢 SEDE</span>';
                                            $hasTooltip = true;
                                            $tooltipContent = htmlspecialchars($cell) . ' (Azienda interna)';
                                        } else {
                                            $cellData = '<span class="badge-client">' . htmlspecialchars(mb_substr($cell, 0, 25)) . '</span>';
                                            if (mb_strlen($cell) > 25) {
                                                $hasTooltip = true;
                                                $tooltipContent = htmlspecialchars($cell);
                                            }
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (TimbratureParser::isTimestampColumn($header)) {
                                    // Real timestamp columns (ora inizio, ora fine, etc.)
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        // Try to parse as datetime
                                        try {
                                            // Handle d/m/Y H:i format (Italian format)
                                            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/', $cell, $matches)) {
                                                $dateObj = DateTime::createFromFormat('d/m/Y H:i', $cell);
                                                if ($dateObj && $dateObj->format('Y') > 1900) {
                                                    $cellData = '<span class="text-primary fw-medium">' . $dateObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $dateObj->format('H:i') . '</small>';
                                                } else {
                                                    $cellData = '<span class="badge bg-warning">⚠️ Data invalida</span>';
                                                }
                                            } elseif (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?)/', $cell)) {
                                                $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $cell);
                                                if (!$dateObj) $dateObj = DateTime::createFromFormat('Y-m-d H:i', $cell);
                                                if ($dateObj && $dateObj->format('Y') > 1900) {
                                                    $cellData = '<span class="text-primary fw-medium">' . $dateObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $dateObj->format('H:i') . '</small>';
                                                } else {
                                                    $cellData = '<span class="badge bg-warning">⚠️ Data invalida</span>';
                                                }
                                            } elseif (preg_match('/(\d{2}:\d{2}(:\d{2})?)/', $cell)) {
                                                $cellData = '<span class="text-info fw-medium">' . htmlspecialchars($cell) . '</span>';
                                            } else {
                                                $cellData = htmlspecialchars($cell);
                                            }
                                        } catch (Exception $e) {
                                            $cellData = '<span class="badge bg-danger">❌ Errore parsing</span>';
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } elseif (TimbratureParser::isDurationColumn($header)) {
                                    // Duration/Hours calculation columns (SOLO QUELLE VALIDE)
                                    $cellClass = 'text-center';
                                    if (!empty($cell)) {
                                        // Handle Excel duration format "12/31/1899 X:XX:XX AM/PM"
                                        if (preg_match('/12\/31\/1899\s+(\d{1,2}):(\d{2}):(\d{2})\s+(AM|PM)/i', $cell, $matches)) {
                                            $hours = intval($matches[1]);
                                            $minutes = intval($matches[2]);
                                            $seconds = intval($matches[3]);
                                            $ampm = strtoupper($matches[4]);
                                            
                                            // Convert to 24h format
                                            if ($ampm === 'PM' && $hours !== 12) {
                                                $hours += 12;
                                            } elseif ($ampm === 'AM' && $hours === 12) {
                                                $hours = 0;
                                            }
                                            
                                            // Convert seconds to additional minutes
                                            $totalMinutes = $minutes + round($seconds / 60);
                                            if ($totalMinutes >= 60) {
                                                $hours += floor($totalMinutes / 60);
                                                $totalMinutes = $totalMinutes % 60;
                                            }
                                            
                                            $cellData = '<span class="hours-display">' . $hours . 'h ' . $totalMinutes . 'm</span>';
                                            
                                        } elseif (preg_match('/(\d+)h\s*(\d+)?m?/', $cell, $matches)) {
                                            // Format like "8h 30m"
                                            $hours = intval($matches[1]);
                                            $minutes = isset($matches[2]) ? intval($matches[2]) : 0;
                                            $cellData = '<span class="hours-display">' . $hours . 'h ' . $minutes . 'm</span>';
                                            
                                        } elseif (preg_match('/(\d+):(\d+):(\d+)/', $cell, $matches)) {
                                            // Format like "8:30:00"
                                            $hours = intval($matches[1]);
                                            $minutes = intval($matches[2]);
                                            $cellData = '<span class="hours-display">' . $hours . 'h ' . $minutes . 'm</span>';
                                            
                                        } else {
                                            // Fallback for other formats
                                            $cellData = '<span class="hours-display">' . htmlspecialchars($cell) . '</span>';
                                        }
                                    } else {
                                        $cellData = '<span class="text-muted">-</span>';
                                    }
                                } else {
                                    // Default cell formatting
                                    $cellData = !empty($cell) ? htmlspecialchars($cell) : '<span class="text-muted">-</span>';
                                    if (mb_strlen($cell) > 35) {
                                        $cellData = htmlspecialchars(mb_substr($cell, 0, 35) . '...');
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
                order: [[2, 'desc']], // Order by first data column
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ],
                scrollX: true
            });
            <?php endif; ?>
            
            // Show filter info on page load
            console.log('Dashboard Timbrature Semplificata caricata');
            <?php if (!empty($csvData['excluded_columns'])): ?>
            console.log('Colonne filtrate:', <?= json_encode($csvData['excluded_columns']) ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html>