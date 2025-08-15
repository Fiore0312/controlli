<?php
/**
 * UTILIZZO AUTO - Gestione utilizzo vetture aziendali
 * Visualizzazione CSV + Inserimento manuale con database
 */

header('Content-Type: text/html; charset=utf-8');

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
        cliente VARCHAR(255),
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
        INSERT INTO auto_aziendali (modello, targa, colore) VALUES
        ('Peugeot 208', 'AA123BB', 'Bianco'),
        ('Ford Fiesta', 'CC456DD', 'Rosso'),
        ('Fiat Panda', 'EE789FF', 'Blu'),
        ('Renault Clio', 'GG012HH', 'Nero')
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
                    INSERT INTO utilizzi_auto (tecnico_id, auto_id, data_utilizzo, ora_presa, ora_riconsegna, cliente, ore_utilizzo, stato) 
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
                
                $stmt->execute([
                    $_POST['tecnico_id'],
                    $_POST['auto_id'],
                    $_POST['data_utilizzo'],
                    $_POST['ora_presa'],
                    !empty($_POST['ora_riconsegna']) ? $_POST['ora_riconsegna'] : null,
                    $_POST['cliente'],
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
                    SET tecnico_id=?, auto_id=?, data_utilizzo=?, ora_presa=?, ora_riconsegna=?, cliente=?, ore_utilizzo=?, stato=?
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
                
                $stmt->execute([
                    $_POST['tecnico_id'],
                    $_POST['auto_id'],
                    $_POST['data_utilizzo'],
                    $_POST['ora_presa'],
                    !empty($_POST['ora_riconsegna']) ? $_POST['ora_riconsegna'] : null,
                    $_POST['cliente'],
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
    $csvPath = __DIR__ . '/data/input/auto.csv';
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
    $auto = $pdo->query("SELECT id, modello, targa FROM auto_aziendali ORDER BY modello")->fetchAll();

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
    <title>🚗 Utilizzo Auto - BAIT Service</title>
    
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
        }
        
        .badge-status {
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 0.1rem;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <h3 class="text-success"><?= $stats['total_utilizzi'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Utilizzi Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card completed">
                    <h3 class="text-info"><?= $stats['utilizzi_completati'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Completati</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card ongoing">
                    <h3 class="text-warning"><?= $stats['utilizzi_in_corso'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">In Corso</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="text-primary"><?= number_format($stats['ore_totali'] ?? 0, 1) ?>h</h3>
                    <p class="text-muted mb-0">Ore Totali</p>
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
                                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['modello']) ?> (<?= htmlspecialchars($a['targa']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data Utilizzo</label>
                                <input type="date" class="form-control" name="data_utilizzo" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control" name="cliente" placeholder="Nome cliente">
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
            <div class="table-header" style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; padding: 1rem 1.5rem;">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Utilizzi Auto Registrati
                </h5>
            </div>
            <div class="table-responsive p-3">
                <table id="utilizziTable" class="table table-hover" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
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
                        <?php foreach ($utilizzi as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <span class="badge bg-primary"><?= htmlspecialchars($u['tecnico_nome'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($u['auto_modello'] ?? 'N/A') ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['data_utilizzo'])) ?></td>
                            <td><?= $u['ora_presa'] ? date('d/m H:i', strtotime($u['ora_presa'])) : '-' ?></td>
                            <td><?= $u['ora_riconsegna'] ? date('d/m H:i', strtotime($u['ora_riconsegna'])) : '-' ?></td>
                            <td><?= htmlspecialchars($u['cliente']) ?></td>
                            <td><?= $u['ore_utilizzo'] ? number_format($u['ore_utilizzo'], 2) . 'h' : '-' ?></td>
                            <td>
                                <span class="badge badge-status <?= 
                                    $u['stato'] === 'Completato' ? 'bg-success' : 
                                    ($u['stato'] === 'In_Corso' ? 'bg-warning' : 'bg-danger') 
                                ?>">
                                    <?= $u['stato'] ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="editUtilizzo(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteUtilizzo(<?= $u['id'] ?>)">
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
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
        let editMode = false;

        $(document).ready(function() {
            // Initialize DataTable
            $('#utilizziTable').DataTable({
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
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
                },
                order: [[3, 'desc']] // Order by date
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
            $('[name="cliente"]').val(utilizzo.cliente);
            $('[name="id"]').val(utilizzo.id);
            $('[name="action"]').val('update');
            
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