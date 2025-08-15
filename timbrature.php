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
    <title>⏰ Timbrature - BAIT Service</title>
    
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
            background-color: #fd7e14;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        #timbratureTable td {
            font-size: 0.8rem;
            padding: 0.5rem 0.25rem;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        #timbratureTable th {
            font-size: 0.75rem;
            padding: 0.75rem 0.25rem;
            background-color: #f8f9fa !important;
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card records">
                    <h3 class="text-primary"><?= number_format($totalRecords) ?></h3>
                    <p class="text-muted mb-0">Timbrature Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="text-success"><?= number_format($totalHours, 1) ?>h</h3>
                    <p class="text-muted mb-0">Ore Lavorate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card employees">
                    <h3 class="text-info"><?= $uniqueEmployees ?></h3>
                    <p class="text-muted mb-0">Dipendenti</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card clients">
                    <h3 class="text-warning"><?= $uniqueClients ?></h3>
                    <p class="text-muted mb-0">Clienti</p>
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
                            <th>#</th>
                            <th>Dipendente</th>
                            <th>Cliente</th>
                            <th>Attività</th>
                            <th>Ora Inizio</th>
                            <th>Ora Fine</th>
                            <th>Ore</th>
                            <th>Città</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($csvData['data'] as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <?php if (!empty($row[0]) && !empty($row[1])): ?>
                                    <span class="badge-employee"><?= htmlspecialchars(trim($row[0] . ' ' . $row[1])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row[3])): ?>
                                    <span class="badge-client"><?= htmlspecialchars($row[3]) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row[7] ?? '') ?></td>
                            <td>
                                <?php 
                                if (!empty($row[8])) {
                                    try {
                                        echo date('d/m H:i', strtotime($row[8]));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($row[8]);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($row[9])) {
                                    try {
                                        echo date('d/m H:i', strtotime($row[9]));
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($row[9]);
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($row[10])) {
                                    if (preg_match('/(\d+):(\d+):(\d+)/', $row[10], $matches)) {
                                        echo $matches[1] . 'h ' . $matches[2] . 'm';
                                    } else {
                                        echo htmlspecialchars($row[10]);
                                    }
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row[5] ?? '') ?></td>
                            <td title="<?= htmlspecialchars($row[20] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($row[20] ?? '', 0, 30)) ?><?= mb_strlen($row[20] ?? '') > 30 ? '...' : '' ?>
                            </td>
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