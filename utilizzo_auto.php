<?php
/**
 * UTILIZZO AUTO - Gestione utilizzo vetture aziendali
 * Visualizzazione CSV + Inserimento manuale con database
 * Con selezione multipla clienti e aziende normalizzate
 */

header('Content-Type: text/html; charset=utf-8');

// Include aziende normalizzate
require_once __DIR__ . '/aziende_normalizzate.php';

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Database connection
try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    // Create tables if not exist
    $createTables = "
    CREATE TABLE IF NOT EXISTS auto_aziendali (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modello VARCHAR(100) NOT NULL,
        targa VARCHAR(20) UNIQUE,
        colore VARCHAR(50),
        stato ENUM('Disponibile', 'In_Uso', 'Manutenzione') DEFAULT 'Disponibile',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS utilizzi_auto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tecnico_id INT,
        auto_id INT,
        data_utilizzo DATE NOT NULL,
        ora_presa DATETIME,
        ora_riconsegna DATETIME NULL,
        clienti TEXT, -- Cambio da VARCHAR a TEXT per supportare più clienti
        ore_utilizzo DECIMAL(4,2),
        note TEXT,
        stato ENUM('In_Corso', 'Completato', 'Annullato') DEFAULT 'In_Corso',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE SET NULL,
        FOREIGN KEY (auto_id) REFERENCES auto_aziendali(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($createTables);

    // Insert sample cars if table is empty
    $carCount = $pdo->query("SELECT COUNT(*) FROM auto_aziendali")->fetchColumn();
    if ($carCount == 0) {
        $sampleCars = "
        INSERT INTO auto_aziendali (modello, colore) VALUES
        ('Peugeot 208', 'Bianco'),
        ('Ford Fiesta', 'Rosso'),
        ('Fiat Panda', 'Blu'),
        ('Renault Clio', 'Nero')
        ";
        $pdo->exec($sampleCars);
    }

} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("
                    INSERT INTO utilizzi_auto (tecnico_id, auto_id, clienti, data_utilizzo, ora_presa, ora_riconsegna, ore_utilizzo, stato) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $ore_utilizzo = null;
                $stato = 'In_Corso';
                
                if (!empty($_POST['ora_presa']) && !empty($_POST['ora_riconsegna'])) {
                    $presa = new DateTime($_POST['ora_presa']);
                    $riconsegna = new DateTime($_POST['ora_riconsegna']);
                    $diff = $riconsegna->diff($presa);
                    $ore_utilizzo = round($diff->h + ($diff->i / 60), 2);
                    $stato = 'Completato';
                }
                
                // Gestisci selezione multipla clienti
                $clienti_selezionati = isset($_POST['clienti']) ? implode(', ', $_POST['clienti']) : '';
                
                $stmt->execute([
                    $_POST['tecnico_id'],
                    $_POST['auto_id'],
                    $clienti_selezionati,
                    $_POST['data_utilizzo'],
                    $_POST['ora_presa'],
                    !empty($_POST['ora_riconsegna']) ? $_POST['ora_riconsegna'] : null,
                    $ore_utilizzo,
                    $stato
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Utilizzo auto aggiunto con successo']);
                exit;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM utilizzi_auto WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => true, 'message' => 'Utilizzo eliminato']);
                exit;
                
            case 'update':
                $stmt = $pdo->prepare("
                    UPDATE utilizzi_auto 
                    SET tecnico_id=?, auto_id=?, clienti=?, data_utilizzo=?, ora_presa=?, ora_riconsegna=?, ore_utilizzo=?, stato=?
                    WHERE id=?
                ");
                
                $ore_utilizzo = null;
                $stato = 'In_Corso';
                
                if (!empty($_POST['ora_presa']) && !empty($_POST['ora_riconsegna'])) {
                    $presa = new DateTime($_POST['ora_presa']);
                    $riconsegna = new DateTime($_POST['ora_riconsegna']);
                    $diff = $riconsegna->diff($presa);
                    $ore_utilizzo = round($diff->h + ($diff->i / 60), 2);
                    $stato = 'Completato';
                }
                
                // Gestisci selezione multipla clienti per modifica
                $clienti_selezionati = isset($_POST['clienti']) ? implode(', ', $_POST['clienti']) : '';
                
                $stmt->execute([
                    $_POST['tecnico_id'],
                    $_POST['auto_id'],
                    $clienti_selezionati,
                    $_POST['data_utilizzo'],
                    $_POST['ora_presa'],
                    !empty($_POST['ora_riconsegna']) ? $_POST['ora_riconsegna'] : null,
                    $ore_utilizzo,
                    $stato,
                    $_POST['id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Utilizzo aggiornato']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Initialize variables
$utilizzi = [];
$tecnici = [];
$auto = [];
$stats = ['total_utilizzi' => 0, 'utilizzi_completati' => 0, 'utilizzi_in_corso' => 0, 'ore_totali' => 0];
$csvData = [];

// Load data
try {
    // Get utilizzi from database
    $utilizzi = $pdo->query("
        SELECT u.*, t.nome_completo as tecnico_nome, a.modello as auto_modello
        FROM utilizzi_auto u
        LEFT JOIN tecnici t ON u.tecnico_id = t.id
        LEFT JOIN auto_aziendali a ON u.auto_id = a.id
        ORDER BY u.data_utilizzo DESC, u.ora_presa DESC
    ")->fetchAll();

    // Load CSV data as backup
    $csvPath = __DIR__ . '/upload_csv/auto.csv';
    $csvData = [];
    if (file_exists($csvPath)) {
        $csvContent = file_get_contents($csvPath);
        $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
        }
        
        $lines = str_getcsv($csvContent, "\n");
        if (!empty($lines)) {
            array_shift($lines); // Remove header
            foreach ($lines as $line) {
                if (trim($line) && !strpos($line, 'Somma di Ore')) {
                    $row = str_getcsv($line, ';');
                    if (count($row) >= 6 && !empty($row[0])) {
                        $csvData[] = $row;
                    }
                }
            }
        }
    }

    // Get dropdown data
    $tecnici = $pdo->query("SELECT id, nome_completo FROM tecnici WHERE attivo = 1 ORDER BY nome_completo")->fetchAll();
    $auto = $pdo->query("SELECT id, modello FROM auto_aziendali ORDER BY modello")->fetchAll();
    
    // Get aziende normalizzate
    $aziende_clienti = getAziendeNormalizzate();

    // Calculate statistics
    $stats = [
        'total_utilizzi' => count($utilizzi),
        'utilizzi_completati' => count(array_filter($utilizzi, function($u) { return $u['stato'] === 'Completato'; })),
        'utilizzi_in_corso' => count(array_filter($utilizzi, function($u) { return $u['stato'] === 'In_Corso'; })),
        'ore_totali' => array_sum(array_column($utilizzi, 'ore_utilizzo'))
    ];

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚗 Utilizzo Auto - Vista Excel</title>
    
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        
        .stats-card.total { border-left-color: #28a745; }
        .stats-card.completed { border-left-color: #17a2b8; }
        .stats-card.ongoing { border-left-color: #ffc107; }
        .stats-card.hours { border-left-color: #6f42c1; }
        
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
        
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .form-header {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px 12px 0 0;
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
        
        #utilizziTable {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #d0d7de;
        }
        
        #utilizziTable thead th {
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
        
        #utilizziTable thead th:hover {
            background: linear-gradient(180deg, #eef2f5 0%, #d1d9e0 100%);
        }
        
        #utilizziTable thead th.sorting:after,
        #utilizziTable thead th.sorting_asc:after,
        #utilizziTable thead th.sorting_desc:after {
            opacity: 0.8;
            font-size: 0.8em;
        }
        
        #utilizziTable tbody tr {
            transition: background-color 0.15s ease;
            border-bottom: 1px solid #e1e8ed;
        }
        
        #utilizziTable tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        #utilizziTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        #utilizziTable tbody tr:hover {
            background-color: #dbeafe !important;
            border-color: #3b82f6;
        }
        
        #utilizziTable tbody tr:hover td {
            border-color: #3b82f6;
        }
        
        #utilizziTable tbody td {
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
        
        #utilizziTable tbody td:hover {
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
        
        .badge-technician {
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
        
        .badge-vehicle {
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
        
        .badge-status-completed {
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
        
        .badge-status-ongoing {
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
        
        .badge-status-cancelled {
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
            opacity: 0;
            position: fixed;
            z-index: 99999;
            background-color: #1f2937;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            max-width: 300px;
            min-width: 150px;
            white-space: normal;
            word-wrap: break-word;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            border: 1px solid #4b5563;
            line-height: 1.4;
            pointer-events: none;
            transition: opacity 0.2s ease-in-out;
        }
        
        .cell-tooltip:hover .tooltip-content {
            visibility: visible;
            opacity: 1;
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
            
            #utilizziTable tbody td {
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
                        <i class="fas fa-car me-3"></i>Utilizzo Auto
                    </h1>
                    <p class="mb-0">Gestione utilizzo vetture aziendali con inserimento manuale</p>
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
                <li class="breadcrumb-item active">Utilizzo Auto</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <h3 class="stats-number text-success"><?= number_format($stats['total_utilizzi'] ?? 0) ?></h3>
                    <p class="stats-label">Utilizzi Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card completed">
                    <h3 class="stats-number text-info"><?= number_format($stats['utilizzi_completati'] ?? 0) ?></h3>
                    <p class="stats-label">Completati</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card ongoing">
                    <h3 class="stats-number text-warning"><?= number_format($stats['utilizzi_in_corso'] ?? 0) ?></h3>
                    <p class="stats-label">In Corso</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="stats-number text-primary"><?= number_format($stats['ore_totali'] ?? 0, 1) ?>h</h3>
                    <p class="stats-label">Ore Totali</p>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>Nuovo Utilizzo Auto
                    <button class="btn btn-sm btn-outline-light float-end" type="button" data-bs-toggle="collapse" data-bs-target="#formCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </h5>
            </div>
            <div class="collapse show" id="formCollapse">
                <div class="p-4">
                    <form id="autoForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Tecnico</label>
                                <select class="form-select" name="tecnico_id" required>
                                    <option value="">Seleziona tecnico...</option>
                                    <?php foreach ($tecnici as $tecnico): ?>
                                        <option value="<?= $tecnico['id'] ?>"><?= htmlspecialchars($tecnico['nome_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Auto</label>
                                <select class="form-select" name="auto_id" required>
                                    <option value="">Seleziona auto...</option>
                                    <?php foreach ($auto as $a): ?>
                                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['modello']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Utilizzo</label>
                                <input type="date" class="form-control" name="data_utilizzo" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Clienti Visitati <small class="text-muted">(Selezione multipla - più clienti per singolo utilizzo)</small></label>
                                <select class="form-select" name="clienti[]" multiple required size="4" style="height: auto;">
                                    <?php foreach ($aziende_clienti as $azienda): ?>
                                        <option value="<?= htmlspecialchars($azienda) ?>"><?= htmlspecialchars($azienda) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> Tieni premuto Ctrl/Cmd per selezionare più aziende
                                </small>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <label class="form-label">Ora Presa</label>
                                <input type="datetime-local" class="form-control" name="ora_presa" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ora Riconsegna</label>
                                <input type="datetime-local" class="form-control" name="ora_riconsegna">
                                <small class="text-muted">Lascia vuoto se ancora in uso</small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success me-2">
                                    <i class="fas fa-save me-1"></i>Salva Utilizzo
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="">
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-car me-2"></i>Utilizzi Auto Registrati
                        </h4>
                        <small>Gestione utilizzo vetture aziendali</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i><?= count($utilizzi) ?> Record
                        </span>
                    </div>
                </div>
            </div>
            <div class="table-responsive p-3">
                <table id="utilizziTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th class="row-number">#</th>
                            <th>Tecnico</th>
                            <th>Auto</th>
                            <th>Data</th>
                            <th>Ora Presa</th>
                            <th>Ora Riconsegna</th>
                            <th>Cliente</th>
                            <th>Ore</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilizzi as $index => $u): ?>
                        <?php
                            // Generate technician initials
                            $technicoInitials = '';
                            if ($u['tecnico_nome']) {
                                $words = explode(' ', $u['tecnico_nome']);
                                foreach ($words as $word) {
                                    if (!empty($word)) {
                                        $technicoInitials .= mb_strtoupper(mb_substr($word, 0, 1));
                                    }
                                }
                            }
                            
                            // Format dates
                            $oraPresaFormatted = '';
                            $oraRiconsegnaFormatted = '';
                            
                            if ($u['ora_presa']) {
                                $presaObj = DateTime::createFromFormat('Y-m-d H:i:s', $u['ora_presa']);
                                if ($presaObj) {
                                    $oraPresaFormatted = '<span class="text-primary fw-medium">' . $presaObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $presaObj->format('H:i') . '</small>';
                                }
                            }
                            
                            if ($u['ora_riconsegna']) {
                                $riconsegnaObj = DateTime::createFromFormat('Y-m-d H:i:s', $u['ora_riconsegna']);
                                if ($riconsegnaObj) {
                                    $oraRiconsegnaFormatted = '<span class="text-primary fw-medium">' . $riconsegnaObj->format('d/m/Y') . '</span><br><small class="text-muted">' . $riconsegnaObj->format('H:i') . '</small>';
                                }
                            }
                        ?>
                        <tr>
                            <td class="row-number"><?= $index + 1 ?></td>
                            <td class="text-center">
                                <?php if ($u['tecnico_nome']): ?>
                                <span class="badge-technician cell-tooltip" title="<?= htmlspecialchars($u['tecnico_nome']) ?>">
                                    <?= $technicoInitials ?>
                                    <div class="tooltip-content"><?= htmlspecialchars($u['tecnico_nome']) ?></div>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($u['auto_modello']): ?>
                                <span class="badge-vehicle"><?= htmlspecialchars(mb_substr($u['auto_modello'], 0, 15)) ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="text-primary fw-medium"><?= date('d/m/Y', strtotime($u['data_utilizzo'])) ?></span>
                            </td>
                            <td class="text-center">
                                <?= $oraPresaFormatted ?: '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center">
                                <?= $oraRiconsegnaFormatted ?: '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($u['clienti'])): ?>
                                    <?php 
                                    $clienti_lista = explode(', ', $u['clienti']);
                                    $clienti_brevi = array_map(function($cliente) {
                                        return mb_substr($cliente, 0, 15) . (strlen($cliente) > 15 ? '...' : '');
                                    }, $clienti_lista);
                                    ?>
                                    <span class="badge-client cell-tooltip" title="<?= htmlspecialchars($u['clienti']) ?>">
                                        <?= count($clienti_lista) > 1 ? count($clienti_lista) . ' clienti' : htmlspecialchars($clienti_brevi[0]) ?>
                                        <div class="tooltip-content"><?= htmlspecialchars($u['clienti']) ?></div>
                                    </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($u['ore_utilizzo']): ?>
                                <span class="hours-display"><?= number_format($u['ore_utilizzo'], 2) ?>h</span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="<?= 
                                    $u['stato'] === 'Completato' ? 'badge-status-completed' : 
                                    ($u['stato'] === 'In_Corso' ? 'badge-status-ongoing' : 'badge-status-cancelled')
                                ?>">
                                    <?= $u['stato'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="editUtilizzo(<?= htmlspecialchars(json_encode($u)) ?>)" title="Modifica">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteUtilizzo(<?= $u['id'] ?>)" title="Elimina">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
        let editMode = false;

        $(document).ready(function() {
            // Initialize DataTable with Excel-style features
            var table = $('#utilizziTable').DataTable({
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
                order: [[3, 'desc']], // Order by date
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
            $('#utilizziTable tbody td').on('click', function(e) {
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
                if (!$(e.target).closest('#utilizziTable').length) {
                    $('.cell-selected').removeClass('cell-selected');
                    selectedCell = null;
                }
            });
            
            // Double-click to copy cell content
            $('#utilizziTable tbody td').on('dblclick', function() {
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
            $('#utilizziTable tbody tr').on('mouseenter', function() {
                $(this).find('.row-number').css('background', 'linear-gradient(180deg, #e8f0fe 0%, #c8d6e5 100%)');
            }).on('mouseleave', function() {
                $(this).find('.row-number').css('background', '');
            });
            
            // Column highlighting on header hover
            $('#utilizziTable thead th').on('mouseenter', function() {
                var columnIndex = $(this).index();
                $('#utilizziTable tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).addClass('column-highlight');
                });
            }).on('mouseleave', function() {
                $('#utilizziTable tbody td').removeClass('column-highlight');
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

            // Enhanced tooltip positioning
            let currentTooltip = null;
            
            $('.cell-tooltip').on('mouseenter', function(e) {
                const $tooltip = $(this).find('.tooltip-content');
                currentTooltip = $tooltip[0];
                
                // Position tooltip near mouse cursor
                const updateTooltipPosition = function(event) {
                    if (currentTooltip) {
                        const x = event.clientX + 15;
                        const y = event.clientY - 10;
                        
                        // Keep tooltip within viewport
                        const rect = currentTooltip.getBoundingClientRect();
                        const maxX = window.innerWidth - rect.width - 20;
                        const maxY = window.innerHeight - rect.height - 20;
                        
                        $(currentTooltip).css({
                            left: Math.min(x, maxX) + 'px',
                            top: Math.max(20, Math.min(y, maxY)) + 'px'
                        });
                    }
                };
                
                // Initial position
                updateTooltipPosition(e);
                
                // Update position on mouse move
                $(this).on('mousemove.tooltip', updateTooltipPosition);
            }).on('mouseleave', function() {
                $(this).off('mousemove.tooltip');
                currentTooltip = null;
            });

            // Form submission
            $('#autoForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Errore: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Errore di comunicazione con il server');
                    }
                });
            });
        });

        function editUtilizzo(utilizzo) {
            editMode = true;
            
            // Populate form
            $('[name="tecnico_id"]').val(utilizzo.tecnico_id);
            $('[name="auto_id"]').val(utilizzo.auto_id);
            $('[name="data_utilizzo"]').val(utilizzo.data_utilizzo);
            $('[name="id"]').val(utilizzo.id);
            $('[name="action"]').val('update');
            
            // Gestisci selezione multipla clienti per modifica
            if (utilizzo.clienti) {
                const clientiArray = utilizzo.clienti.split(', ');
                $('[name="clienti[]"]').val(clientiArray);
            } else {
                $('[name="clienti[]"]').val([]);
            }
            
            if (utilizzo.ora_presa) {
                $('[name="ora_presa"]').val(utilizzo.ora_presa.replace(' ', 'T'));
            }
            if (utilizzo.ora_riconsegna) {
                $('[name="ora_riconsegna"]').val(utilizzo.ora_riconsegna.replace(' ', 'T'));
            }
            
            // Show form
            $('#formCollapse').addClass('show');
            $('.form-header h5').html('<i class="fas fa-edit me-2"></i>Modifica Utilizzo Auto');
            $('button[type="submit"]').html('<i class="fas fa-save me-1"></i>Aggiorna Utilizzo');
        }

        function deleteUtilizzo(id) {
            if (confirm('Sei sicuro di voler eliminare questo utilizzo?')) {
                $.post('', {
                    action: 'delete',
                    id: id
                }, function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Errore: ' + response.message);
                    }
                }, 'json');
            }
        }

        // Reset form on reset button
        $('button[type="reset"]').on('click', function() {
            editMode = false;
            $('[name="action"]').val('add');
            $('[name="id"]').val('');
            $('.form-header h5').html('<i class="fas fa-plus-circle me-2"></i>Nuovo Utilizzo Auto');
            $('button[type="submit"]').html('<i class="fas fa-save me-1"></i>Salva Utilizzo');
        });
    </script>
</body>
</html>