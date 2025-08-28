<?php
/**
 * ATTIVITÀ DEEPSER - Visualizzazione CSV Stile Excel
 * Pagina per visualizzare attivita.csv in formato tabellare Excel-like
 * Integrata con sistema BAIT per confronti immediati
 */

header('Content-Type: text/html; charset=utf-8');

// Configurazione paths
$csvPath = __DIR__ . '/upload_csv/attivita.csv';
$hasCSV = file_exists($csvPath);

// Funzione per leggere CSV con gestione encoding
function readCSVFile($filepath) {
    if (!file_exists($filepath)) {
        return ['headers' => [], 'data' => []];
    }
    
    $csvContent = file_get_contents($filepath);
    
    // Remove BOM if present
    $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    // Parse CSV using proper delimiter detection
    $lines = array_map('trim', explode("\n", $csvContent));
    $lines = array_filter($lines, function($line) { return !empty($line); });
    
    if (empty($lines)) return ['headers' => [], 'data' => []];
    
    // Parse header line - try comma first, then semicolon
    $headerLine = array_shift($lines);
    $delimiter = ',';
    if (substr_count($headerLine, ',') < substr_count($headerLine, ';')) {
        $delimiter = ';';
    }
    
    $headers = str_getcsv($headerLine, $delimiter);
    
    // Clean headers
    $headers = array_map(function($h) {
        return trim(str_replace(['"', "'"], '', $h));
    }, $headers);
    
    $data = [];
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line, $delimiter);
            $data[] = array_pad($row, count($headers), '');
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

$csvData = readCSVFile($csvPath);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attività Deepser - BAIT Service</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- BAIT Unified Design System -->
    <link href="/controlli/assets/css/bait-unified-system.css" rel="stylesheet">
    <style>
    /* Force smaller typography */
    body { font-size: 0.875rem !important; }
    h1 { font-size: 1.25rem !important; font-weight: 400 !important; }
    .table, .table th, .table td { font-size: 0.75rem !important; padding: 0.25rem 0.5rem !important; }
    .btn-dashboard, .dashboard-button, .btn[href*="dashboard"] { display: none !important; }
    .bait-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0; }
    .bait-stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-align: center; }
    .bait-stat-value { font-size: 1.5rem; font-weight: 600; color: #3b82f6; }
    .bait-stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
    .bait-table-container { background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
    .bait-card-header { background: #f8fafc; padding: 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 500; }
    .bait-table { width: 100%; margin: 0; font-size: 0.75rem !important; }
    .bait-table th { background: #f8fafc; padding: 0.5rem; font-weight: 500; font-size: 0.75rem; border-bottom: 1px solid #e2e8f0; }
    .bait-table td { padding: 0.5rem; font-size: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <!-- BAIT Navigation System -->
    <?php
    require_once 'includes/bait_navigation.php';
    renderBaitNavigation(basename(__FILE__, '.php'), 'database');
    ?>

    <div class="container py-4">
        <h1 class="bait-page-title">
            <i class="bi bi-activity me-2"></i>Attività Deepser
        </h1>

        <?php if (!$hasCSV): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>File CSV non trovato</strong><br>
                Il file attivita.csv non è presente nella cartella upload_csv/
            </div>
        <?php elseif (empty($csvData['data'])): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Nessun dato disponibile</strong><br>
                Il file CSV è vuoto o non contiene dati validi.
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="bait-stats-grid">
                <div class="bait-stat-card">
                    <div class="bait-stat-value"><?= count($csvData['data']) ?></div>
                    <div class="bait-stat-label">Attività Totali</div>
                </div>
                <div class="bait-stat-card">
                    <div class="bait-stat-value"><?= count($csvData['headers']) ?></div>
                    <div class="bait-stat-label">Campi Dati</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bait-table-container">
                <div class="bait-card-header">
                    <h5><i class="bi bi-table me-2"></i>Dati Attività</h5>
                </div>
                <div>
                    <table id="activitiesTable" class="bait-table">
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
                                    <?php foreach ($row as $cell): ?>
                                        <td><?= htmlspecialchars($cell) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <?php if (!empty($csvData['data'])): ?>
    <script>
        $(document).ready(function() {
            $('#activitiesTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="bi bi-file-excel me-1"></i>Excel',
                        className: 'btn btn-success btn-sm'
                    }
                ],
                pageLength: 25,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
                },
                scrollX: true,
                columnDefs: [
                    { orderable: false, targets: 0 }
                ]
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>