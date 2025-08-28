<?php
/**
 * BAIT Service - Upload CSV Incremental System (Iframe Optimized)
 * Versione ottimizzata per essere utilizzata all'interno di iframe dashboard
 * Include comunicazione parent-child per report risultati
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
$isIframeMode = isset($_GET['iframe']) || strpos($_SERVER['HTTP_REFERER'] ?? '', 'index_standalone.php') !== false;

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAIT Service - Upload CSV Incrementale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            <?php if (!$isIframeMode): ?>
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            <?php else: ?>
            background: #f8f9fa;
            margin: 0;
            padding: 15px;
            <?php endif; ?>
        }

        .container-fluid {
            max-width: 100%;
            <?php if ($isIframeMode): ?>
            padding: 0;
            <?php endif; ?>
        }

        .upload-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: <?php echo $isIframeMode ? '10px' : '20px'; ?>;
            box-shadow: 0 <?php echo $isIframeMode ? '5px 15px' : '20px 40px'; ?> rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: <?php echo $isIframeMode ? '15px 20px' : '25px 30px'; ?>;
            border: none;
        }

        .card-header h3 {
            margin: 0;
            font-size: <?php echo $isIframeMode ? '1.2rem' : '1.5rem'; ?>;
        }

        .card-header p {
            margin: 0;
            margin-top: 5px;
            opacity: 0.8;
            font-size: <?php echo $isIframeMode ? '0.8rem' : '0.9rem'; ?>;
        }

        .card-body {
            padding: <?php echo $isIframeMode ? '20px' : '30px'; ?>;
        }

        .file-upload-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: <?php echo $isIframeMode ? '15px' : '25px'; ?>;
            margin-bottom: 20px;
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }

        .file-upload-section:hover {
            border-color: #007bff;
            background: #f0f7ff;
        }

        .file-input-group {
            margin-bottom: 15px;
        }

        .file-input-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
            font-size: <?php echo $isIframeMode ? '0.85rem' : '0.9rem'; ?>;
        }

        .file-input {
            width: 100%;
            padding: <?php echo $isIframeMode ? '8px' : '12px'; ?>;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: <?php echo $isIframeMode ? '0.8rem' : '14px'; ?>;
            transition: all 0.3s ease;
        }

        .file-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .btn-upload {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: <?php echo $isIframeMode ? '10px 25px' : '15px 40px'; ?>;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: <?php echo $isIframeMode ? '0.9rem' : '16px'; ?>;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 15px;
        }

        .btn-upload:hover {
            background: linear-gradient(135deg, #218838, #1ea085);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.4);
        }

        .results-section {
            background: #fff;
            border-radius: 10px;
            padding: <?php echo $isIframeMode ? '15px' : '25px'; ?>;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: <?php echo $isIframeMode ? '0.85rem' : '0.9rem'; ?>;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(<?php echo $isIframeMode ? '120px' : '150px'; ?>, 1fr));
            gap: <?php echo $isIframeMode ? '10px' : '15px'; ?>;
            margin: 15px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: <?php echo $isIframeMode ? '12px' : '15px'; ?>;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: <?php echo $isIframeMode ? '1.3rem' : '1.8rem'; ?>;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: <?php echo $isIframeMode ? '0.7rem' : '0.8rem'; ?>;
        }

        .log-container {
            max-height: <?php echo $isIframeMode ? '250px' : '300px'; ?>;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            border: 1px solid #dee2e6;
        }

        .log-entry {
            padding: 5px;
            margin-bottom: 3px;
            border-radius: 4px;
            font-family: monospace;
            font-size: <?php echo $isIframeMode ? '0.7rem' : '0.75rem'; ?>;
        }

        .log-info { background: #d1ecf1; }
        .log-success { background: #d4edda; }
        .log-warning { background: #fff3cd; }
        .log-error { background: #f8d7da; }

        .table-responsive {
            max-height: <?php echo $isIframeMode ? '300px' : '400px'; ?>;
            overflow-y: auto;
        }

        .table {
            font-size: <?php echo $isIframeMode ? '0.8rem' : '0.85rem'; ?>;
        }

        .badge {
            font-size: <?php echo $isIframeMode ? '0.7rem' : '0.75rem'; ?>;
        }

        /* Success animation for iframe */
        .success-animation {
            animation: successPulse 2s ease-in-out;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); 
            }
            .file-upload-section .row .col-md-6 {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php if ($uploadResult && $uploadResult['success']): ?>
            <!-- Success Report Section -->
            <div id="success-report" class="results-section success-animation">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>🎉 Upload Incrementale Completato!</strong>
                    Processati <?= $uploadResult['total_files'] ?> file con <?= $uploadResult['total_new_records'] ?> nuovi record.
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $uploadResult['total_files'] ?></div>
                        <div class="stat-label">File Processati</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $uploadResult['total_new_records'] ?></div>
                        <div class="stat-label">Nuovi Record</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $uploadResult['total_existing_records'] ?></div>
                        <div class="stat-label">Record Esistenti</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count($uploadResult['backups']) ?></div>
                        <div class="stat-label">Backup Creati</div>
                    </div>
                </div>

                <?php if (!empty($uploadResult['backups'])): ?>
                    <h6><i class="bi bi-files me-2"></i>File Backup Automatici:</h6>
                    <div class="alert alert-info">
                        <?php foreach ($uploadResult['backups'] as $backup): ?>
                            <small>📁 Backup created: <?= htmlspecialchars($backup['backup_name']) ?> (<?= number_format($backup['backup_size']) ?> bytes)</small><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>📊 Statistiche Import Incrementale:</h6>
                        <?php if (!empty($uploadResult['file_results'])): ?>
                            <div class="row">
                                <?php foreach ($uploadResult['file_results'] as $fileName => $result): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="card">
                                            <div class="card-body p-2">
                                                <h6 class="card-title text-primary"><?= htmlspecialchars($fileName) ?></h6>
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-success"><?= $result['new'] ?> Nuovi</span>
                                                    <span class="badge bg-warning"><?= $result['existing'] ?> Esistenti</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!empty($uploadResult['logs'])): ?>
                            <h6><i class="bi bi-list-ul me-2"></i>Log Dettagliato:</h6>
                            <div class="log-container">
                                <?php foreach (array_slice($uploadResult['logs'], -10) as $log): ?>
                                    <div class="log-entry log-<?= $log['level'] ?>">
                                        <strong>[<?= $log['timestamp'] ?>]</strong> 
                                        <?= htmlspecialchars($log['message']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Nuovo Upload
                    </button>
                </div>
            </div>

            <script>
                // Comunica successo al parent se in iframe
                if (window.parent !== window) {
                    window.parent.postMessage({
                        type: 'upload_success',
                        data: {
                            files: <?= $uploadResult['total_files'] ?>,
                            newRecords: <?= $uploadResult['total_new_records'] ?>,
                            existingRecords: <?= $uploadResult['total_existing_records'] ?>,
                            backups: <?= count($uploadResult['backups']) ?>
                        }
                    }, '*');
                }
            </script>
        <?php elseif ($uploadResult && !$uploadResult['success']): ?>
            <!-- Error Report -->
            <div class="results-section">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Errore nell'Upload:</strong> <?= htmlspecialchars($uploadResult['error'] ?? 'Errore sconosciuto') ?>
                </div>
                
                <?php if (!empty($uploadResult['logs'])): ?>
                    <h6>🔍 Debug Information:</h6>
                    <div class="log-container">
                        <?php foreach ($uploadResult['logs'] as $log): ?>
                            <div class="log-entry log-<?= $log['level'] ?>">
                                <strong>[<?= $log['timestamp'] ?>]</strong> 
                                <?= htmlspecialchars($log['message']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="location.reload()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Riprova Upload
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Upload Form -->
            <div class="upload-card">
                <div class="card-header">
                    <h3>
                        <i class="bi bi-cloud-upload-fill me-3"></i>
                        Sistema Upload Incrementale BAIT Service
                    </h3>
                    <p>
                        Caricamento giornaliero con backup automatico e rilevamento duplicati
                    </p>
                </div>

                <div class="card-body">
                    <?php if (!$pdo): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Errore Connessione Database:</strong> Impossibile connettersi al database. 
                            Verificare che XAMPP/MySQL sia attivo.
                        </div>
                    <?php else: ?>
                        
                        <!-- Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="file-upload-section">
                                <h6 class="mb-3">
                                    <i class="bi bi-files me-2"></i>
                                    Seleziona i file CSV da caricare
                                </h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="file-input-group">
                                            <label><i class="bi bi-briefcase me-1"></i> Attività Deepser:</label>
                                            <input type="file" name="attivita" class="file-input" accept=".csv">
                                        </div>
                                        
                                        <div class="file-input-group">
                                            <label><i class="bi bi-clock me-1"></i> Timbrature:</label>
                                            <input type="file" name="timbrature" class="file-input" accept=".csv">
                                        </div>
                                        
                                        <div class="file-input-group">
                                            <label><i class="bi bi-display me-1"></i> TeamViewer BAIT:</label>
                                            <input type="file" name="teamviewer_bait" class="file-input" accept=".csv">
                                        </div>
                                        
                                        <div class="file-input-group">
                                            <label><i class="bi bi-people me-1"></i> TeamViewer Gruppo:</label>
                                            <input type="file" name="teamviewer_gruppo" class="file-input" accept=".csv">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="file-input-group">
                                            <label><i class="bi bi-calendar-check me-1"></i> Permessi:</label>
                                            <input type="file" name="permessi" class="file-input" accept=".csv">
                                        </div>
                                        
                                        <div class="file-input-group">
                                            <label><i class="bi bi-car-front me-1"></i> Auto:</label>
                                            <input type="file" name="auto" class="file-input" accept=".csv">
                                        </div>
                                        
                                        <div class="file-input-group">
                                            <label><i class="bi bi-calendar me-1"></i> Calendario:</label>
                                            <input type="file" name="calendario" class="file-input" accept=".csv,.ics">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="upload_csv" class="btn-upload" id="submitBtn">
                                    <i class="bi bi-cloud-upload me-2"></i>
                                    Carica e Processa File
                                </button>
                            </div>
                        </form>

                        <!-- Sistema Incrementale Info -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Sistema Incrementale Attivo</strong>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <i class="bi bi-folder text-warning"></i>
                                    <small><strong>Backup Automatico</strong><br>
                                    File esistenti salvati in<br>
                                    upload_csv/old/</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="bi bi-search text-primary"></i>
                                    <small><strong>Rilevamento Duplicati</strong><br>
                                    Solo nuovi record importati</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="bi bi-list-check text-success"></i>
                                    <small><strong>Log Completo</strong><br>
                                    Tracciamento dettagliato operazioni</small>
                                </div>
                                <div class="col-md-3">
                                    <i class="bi bi-bar-chart text-info"></i>
                                    <small><strong>Statistiche</strong><br>
                                    Report nuovi vs esistenti</small>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Import Stats (if available) -->
            <?php if ($pdo): ?>
                <?php
                $uploadManager = new IncrementalUploadManager($pdo);
                $todayStats = $uploadManager->getTodayImportStats();
                ?>
                <?php if (!empty($todayStats)): ?>
                    <div class="results-section">
                        <h6><i class="bi bi-graph-up me-2"></i>Statistiche Import di Oggi:</h6>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Totali</th>
                                        <th>Nuovi</th>
                                        <th>Esistenti</th>
                                        <th>Timestamp</th>
                                        <th>Stato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayStats as $stat): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($stat['file_name']) ?></strong></td>
                                            <td><span class="badge bg-primary"><?= $stat['records_total'] ?></span></td>
                                            <td><span class="badge bg-success"><?= $stat['records_new'] ?></span></td>
                                            <td><span class="badge bg-warning"><?= $stat['records_existing'] ?></span></td>
                                            <td><small><?= $stat['import_timestamp'] ?></small></td>
                                            <td>
                                                <?php if ($stat['status'] === 'completed'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check"></i> OK</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="bi bi-x"></i> <?= $stat['status'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Upload form handling
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const inputs = document.querySelectorAll('input[type="file"]');
            
            // Check if at least one file is selected
            let hasFiles = false;
            inputs.forEach(input => {
                if (input.files.length > 0) {
                    hasFiles = true;
                }
            });
            
            if (!hasFiles) {
                e.preventDefault();
                alert('Seleziona almeno un file CSV da caricare.');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>Caricamento in corso...';
            submitBtn.disabled = true;

            // Notify parent if in iframe
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'upload_started'
                }, '*');
            }
        });
        
        // Add file change animations
        document.querySelectorAll('.file-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    this.style.borderColor = '#28a745';
                    this.style.backgroundColor = '#f8fff9';
                } else {
                    this.style.borderColor = '#e9ecef';
                    this.style.backgroundColor = '#fff';
                }
            });
        });

        // Listen for messages from parent
        window.addEventListener('message', function(event) {
            if (event.data.type === 'refresh_request') {
                location.reload();
            }
        });
    </script>
    
    <style>
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>