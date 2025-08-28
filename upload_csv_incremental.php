<?php
/**
 * BAIT Service - Upload CSV Incremental System
 * Sistema completo di upload incrementale giornaliero
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Rome');

require_once __DIR__ . '/IncrementalUploadManager.php';

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Database connection
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

$uploadResult = null;
$pdo = getDatabase();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv']) && $pdo) {
    $uploadManager = new IncrementalUploadManager($pdo);
    $uploadResult = $uploadManager->processUploadedFiles($_FILES);
    $uploadResult['logs'] = $uploadManager->getLogs();
    $uploadResult['today_stats'] = $uploadManager->getTodayImportStats();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT Service - Upload CSV Incrementale</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bait-blue: #2563eb;
            --bait-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }
        
        .header-gradient {
            background: var(--bait-gradient);
            color: white;
            padding: 2rem 0;
        }
        
        .upload-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .upload-header {
            background: var(--bait-gradient);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .btn-upload {
            background: var(--bait-gradient);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .log-entry {
            font-family: monospace;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
        .log-info { color: #17a2b8; }
    </style>
</head>

<body>
    <div class="header-gradient">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-cloud-upload me-3"></i>
                        Upload CSV Incrementale
                    </h1>
                    <p class="mb-0 opacity-75">Sistema di caricamento incrementale giornaliero con backup automatico</p>
                </div>
                <div>
                    <a href="/controlli/laravel_bait/public/index_standalone.php" class="btn btn-light">
                        <i class="bi bi-arrow-left me-2"></i>
                        Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php if ($uploadResult): ?>
                <?php if ($uploadResult['success']): ?>
                <div class="alert alert-success alert-custom mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <h5 class="mb-1">🎉 Upload Incrementale Completato!</h5>
                            <p class="mb-0">
                                <strong><?= $uploadResult['files_processed'] ?></strong> file processati - 
                                <strong><?= $uploadResult['total_new_records'] ?></strong> nuovi record importati
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($uploadResult['backup_info'])): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6 class="text-primary mb-2">
                            <i class="bi bi-archive me-1"></i>
                            File Backup Automatici:
                        </h6>
                        <?php foreach ($uploadResult['backup_info'] as $backup): ?>
                        <div class="small text-muted"><?= htmlspecialchars($backup) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($uploadResult['import_stats'])): ?>
                    <div class="mt-4">
                        <h6 class="text-success mb-3">
                            <i class="bi bi-database-check me-1"></i>
                            Statistiche Import Incrementale:
                        </h6>
                        <div class="row">
                            <?php foreach ($uploadResult['import_stats'] as $stat): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body p-3">
                                        <h6 class="card-title text-primary">📄 <?= htmlspecialchars($stat['file_name']) ?></h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="fs-5 fw-bold text-success"><?= $stat['new_records'] ?></div>
                                                <small class="text-muted">Nuovi</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fs-5 fw-bold text-warning"><?= $stat['existing_records'] ?></div>
                                                <small class="text-muted">Esistenti</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="fs-5 fw-bold text-info"><?= $stat['total_records'] ?></div>
                                                <small class="text-muted">Totali</small>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-success">✅ <?= htmlspecialchars(str_replace('✅', '', $stat['summary'])) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($uploadResult['logs'])): ?>
                    <div class="mt-4 p-3 bg-dark rounded">
                        <h6 class="text-light mb-3">
                            <i class="bi bi-journal-text me-1"></i>
                            Log di Sistema (Tempo Reale):
                        </h6>
                        <div class="text-light" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.8rem;">
                            <?php foreach ($uploadResult['logs'] as $log): ?>
                            <div class="log-entry log-<?= strtolower($log['level']) ?>">
                                [<?= $log['timestamp'] ?>] <?= strtoupper($log['level']) ?>: <?= htmlspecialchars($log['message']) ?>
                                <?php if ($log['details']): ?>
                                <small class="text-muted d-block ps-4"><?= htmlspecialchars(json_encode($log['details'], JSON_PRETTY_PRINT)) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($uploadResult['today_stats'])): ?>
                    <div class="mt-4 p-3 bg-info bg-opacity-10 rounded">
                        <h6 class="text-info mb-3">
                            <i class="bi bi-calendar-check me-1"></i>
                            Statistiche Import di Oggi:
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Orario</th>
                                        <th>Totali</th>
                                        <th>Nuovi</th>
                                        <th>Esistenti</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uploadResult['today_stats'] as $stat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($stat['file_name']) ?></td>
                                        <td><?= date('H:i:s', strtotime($stat['import_timestamp'])) ?></td>
                                        <td><?= $stat['records_total'] ?></td>
                                        <td class="text-success fw-bold"><?= $stat['records_new'] ?></td>
                                        <td class="text-warning"><?= $stat['records_existing'] ?></td>
                                        <td>
                                            <?php if ($stat['status'] === 'completed'): ?>
                                            <span class="badge bg-success">✅ Completato</span>
                                            <?php elseif ($stat['status'] === 'failed'): ?>
                                            <span class="badge bg-danger">❌ Errore</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">⏳ In corso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 text-center">
                        <div class="btn-group" role="group">
                            <a href="/controlli/laravel_bait/public/index_standalone.php" class="btn btn-success">
                                <i class="bi bi-house me-1"></i> Dashboard Principale
                            </a>
                            <a href="/controlli/upload_csv/old/" class="btn btn-outline-secondary">
                                <i class="bi bi-archive me-1"></i> Archivio File
                            </a>
                            <button type="button" class="btn btn-outline-info" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i> Ricarica Pagina
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-4">
                    <h5 class="mb-1">⚠️ Upload Non Completato</h5>
                    <p class="mb-0">Nessun file è stato processato correttamente.</p>
                    <?php if (!empty($uploadResult['errors'])): ?>
                    <div class="mt-2">
                        <?php foreach ($uploadResult['errors'] as $error): ?>
                        <div class="small text-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="card upload-card">
                    <div class="upload-header">
                        <i class="bi bi-files fs-1 mb-3"></i>
                        <h3>Sistema Upload Incrementale BAIT Service</h3>
                        <p class="mb-0">Caricamento giornaliero con backup automatico e rilevamento duplicati</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-briefcase me-2"></i>
                                        Attività Deepser
                                        <span class="badge bg-danger ms-2">RICHIESTO</span>
                                    </label>
                                    <input type="file" name="attivita" class="form-control" accept=".csv" required>
                                    <small class="text-muted">File delle attività tecnici da Deepser (CSV con semicolon)</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-clock me-2"></i>
                                        Timbrature
                                    </label>
                                    <input type="file" name="timbrature" class="form-control" accept=".csv">
                                    <small class="text-muted">Registro timbrature tecnici (CSV con semicolon)</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-display me-2"></i>
                                        TeamViewer BAIT
                                    </label>
                                    <input type="file" name="teamviewer_bait" class="form-control" accept=".csv">
                                    <small class="text-muted">Sessioni TeamViewer BAIT Service</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-people me-2"></i>
                                        TeamViewer Gruppo
                                    </label>
                                    <input type="file" name="teamviewer_gruppo" class="form-control" accept=".csv">
                                    <small class="text-muted">Sessioni TeamViewer di gruppo</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-car-front me-2"></i>
                                        Utilizzi Auto
                                    </label>
                                    <input type="file" name="auto" class="form-control" accept=".csv">
                                    <small class="text-muted">Utilizzi auto aziendali</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-calendar-event me-2"></i>
                                        Permessi
                                    </label>
                                    <input type="file" name="permessi" class="form-control" accept=".csv">
                                    <small class="text-muted">Richieste permessi e ferie</small>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-calendar3 me-2"></i>
                                        Calendario
                                    </label>
                                    <input type="file" name="calendario" class="form-control" accept=".csv,.ics">
                                    <small class="text-muted">Eventi calendario (.csv o .ics)</small>
                                </div>
                            </div>
                            
                            <div class="bg-light p-4 rounded mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Sistema Incrementale Attivo
                                </h6>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <i class="bi bi-archive text-warning fs-4"></i>
                                        <h6 class="mt-2">Backup Automatico</h6>
                                        <small>File esistenti salvati in upload_csv/old/</small>
                                    </div>
                                    <div class="col-md-3">
                                        <i class="bi bi-search text-info fs-4"></i>
                                        <h6 class="mt-2">Rilevamento Duplicati</h6>
                                        <small>Solo nuovi record importati</small>
                                    </div>
                                    <div class="col-md-3">
                                        <i class="bi bi-journal-text text-success fs-4"></i>
                                        <h6 class="mt-2">Log Completo</h6>
                                        <small>Tracciamento dettagliato operazioni</small>
                                    </div>
                                    <div class="col-md-3">
                                        <i class="bi bi-bar-chart text-primary fs-4"></i>
                                        <h6 class="mt-2">Statistiche</h6>
                                        <small>Report nuovi vs esistenti</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="upload_csv" class="btn btn-upload btn-lg">
                                    <i class="bi bi-cloud-upload me-2"></i>
                                    Carica e Processa (Incrementale)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced file input handling
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        this.classList.add('is-valid');
                        const fileName = this.files[0].name;
                        const fileSize = (this.files[0].size / 1024).toFixed(1) + ' KB';
                        this.nextElementSibling.innerHTML = `✅ Selezionato: <strong>${fileName}</strong> (${fileSize})`;
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            });
            
            // Auto-scroll to results if present
            <?php if ($uploadResult && $uploadResult['success']): ?>
            document.querySelector('.alert-success').scrollIntoView({behavior: 'smooth'});
            <?php endif; ?>
        });
    </script>
</body>
</html>