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
        
        .badge-status {
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .badge-type {
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <h3 class="text-danger"><?= number_format($totalRecords) ?></h3>
                    <p class="text-muted mb-0">Richieste Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card approved">
                    <h3 class="text-success"><?= $approvate ?></h3>
                    <p class="text-muted mb-0">Approvate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card vacation">
                    <h3 class="text-info"><?= $ferie ?></h3>
                    <p class="text-muted mb-0">Ferie</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card permits">
                    <h3 class="text-warning"><?= $permessi ?></h3>
                    <p class="text-muted mb-0">Permessi</p>
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
                            <th>#</th>
                            <?php foreach ($csvData['headers'] as $header): ?>
                            <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($csvData['data'] as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <?php foreach ($row as $colIndex => $cell): ?>
                            <td>
                                <?php
                                if (isset($csvData['headers'][$colIndex])) {
                                    $header = $csvData['headers'][$colIndex];
                                    switch ($header) {
                                        case 'Dipendente':
                                            echo !empty($cell) ? '<span class="badge bg-primary">' . htmlspecialchars($cell) . '</span>' : '';
                                            break;
                                        case 'Tipo':
                                            $badgeClass = strpos(strtolower($cell), 'ferie') !== false ? 'bg-info' : 'bg-secondary';
                                            echo !empty($cell) ? '<span class="badge badge-type ' . $badgeClass . '">' . htmlspecialchars($cell) . '</span>' : '';
                                            break;
                                        case 'Stato':
                                            $badgeClass = strpos(strtolower($cell), 'approv') !== false ? 'bg-success' : 
                                                         (strpos(strtolower($cell), 'respint') !== false ? 'bg-danger' : 'bg-warning');
                                            echo !empty($cell) ? '<span class="badge badge-status ' . $badgeClass . '">' . htmlspecialchars($cell) . '</span>' : 
                                                 '<span class="badge badge-status bg-warning">Pending</span>';
                                            break;
                                        case 'Data della richiesta':
                                        case 'Data inizio':
                                        case 'Data fine':
                                            if (!empty($cell) && $cell !== '0') {
                                                try {
                                                    $date = date('d/m/Y', strtotime(str_replace('/', '-', $cell)));
                                                    echo $date !== '01/01/1970' ? $date : htmlspecialchars($cell);
                                                } catch (Exception $e) {
                                                    echo htmlspecialchars($cell);
                                                }
                                            }
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
                <div class="text-center p-5">
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
            $('#permessiTable').DataTable({
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
                order: [[1, 'desc']], // Order by request date
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ]
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>