<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'BAIT Service Enterprise' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }
        .feature-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        .metric-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-gear-fill me-2"></i>
                BAIT Service Enterprise
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/upload">Upload CSV</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/process">Manual Process</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/api-docs">API Docs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/status">Status</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">
                        Sistema di Controllo Enterprise
                    </h1>
                    <p class="lead mb-4">
                        Controllo automatizzato delle attività tecniche con rilevamento sovrapposizioni,
                        validazione business rules e dashboard real-time per massima efficienza operativa.
                    </p>
                    <div class="d-flex gap-3">
                        <button onclick="triggerProcessing()" class="btn btn-light btn-lg">
                            <i class="bi bi-play-circle me-2"></i>
                            Avvia Elaborazione
                        </button>
                        <button onclick="loadDashboard()" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-graph-up me-2"></i>
                            Visualizza Dati
                        </button>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card bg-dark bg-opacity-25 border-0">
                        <div class="card-body text-center">
                            <h5 class="card-title text-light">Sistema Status</h5>
                            <div id="system-status" class="spinner-border text-light" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div id="status-info" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="display-6 fw-bold">Funzionalità Enterprise</h2>
                    <p class="text-muted">Sistema completo migrato da Python a Laravel per prestazioni superiori</p>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Temporal Overlap Detection -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-exclamation-triangle-fill text-danger fs-2"></i>
                            </div>
                            <h5 class="card-title">Rilevamento Sovrapposizioni</h5>
                            <p class="card-text text-muted">
                                Detection automatico sovrapposizioni temporali con confidence scoring 
                                CRITICO/ALTO/MEDIO per eliminare perdite di fatturazione.
                            </p>
                            <span class="badge bg-danger">CRITICO</span>
                        </div>
                    </div>
                </div>

                <!-- Travel Time Intelligence -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-geo-alt-fill text-warning fs-2"></i>
                            </div>
                            <h5 class="card-title">Travel Time Intelligence</h5>
                            <p class="card-text text-muted">
                                Analisi intelligente tempi di viaggio con geo-intelligence Milano,
                                whitelist BAIT Service e eliminazione falsi positivi.
                            </p>
                            <span class="badge bg-warning">MEDIO</span>
                        </div>
                    </div>
                </div>

                <!-- Business Rules Engine -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-cpu-fill text-primary fs-2"></i>
                            </div>
                            <h5 class="card-title">Business Rules Engine</h5>
                            <p class="card-text text-muted">
                                Validazione avanzata business rules v2.0 con confidence scoring
                                multi-dimensionale e cross-validation TeamViewer.
                            </p>
                            <span class="badge bg-primary">ALTO</span>
                        </div>
                    </div>
                </div>

                <!-- CSV Processing -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-file-earmark-spreadsheet-fill text-success fs-2"></i>
                            </div>
                            <h5 class="card-title">CSV Processing Enterprise</h5>
                            <p class="card-text text-muted">
                                Processing robusto CSV con encoding detection, validazione struttura
                                e backup automatico. Support multi-format e error handling.
                            </p>
                            <span class="badge bg-success">OPERATIVO</span>
                        </div>
                    </div>
                </div>

                <!-- Real-time Dashboard -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-speedometer2 text-info fs-2"></i>
                            </div>
                            <h5 class="card-title">Dashboard Real-time</h5>
                            <p class="card-text text-muted">
                                Visualizzazione real-time KPI, metriche performance, efficienza tecnici
                                e trending analysis con cache intelligente.
                            </p>
                            <span class="badge bg-info">REAL-TIME</span>
                        </div>
                    </div>
                </div>

                <!-- API Enterprise -->
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-secondary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-api text-secondary fs-2"></i>
                            </div>
                            <h5 class="card-title">API REST Enterprise</h5>
                            <p class="card-text text-muted">
                                API REST complete con endpoint per ogni funzionalità,
                                compatibili con dashboard Python esistente.
                            </p>
                            <span class="badge bg-secondary">API</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Metrics Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-4">
                    <h3 class="fw-bold">Metriche Live</h3>
                    <p class="text-muted">Dati aggiornati in tempo reale dal sistema</p>
                </div>
            </div>
            
            <div class="row g-4" id="live-metrics">
                <!-- Metrics will be loaded via JavaScript -->
                <div class="col-md-3">
                    <div class="card metric-card h-100">
                        <div class="card-body text-center">
                            <div class="spinner-border text-primary mb-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h6 class="text-muted">Caricamento...</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card h-100">
                        <div class="card-body text-center">
                            <div class="spinner-border text-success mb-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h6 class="text-muted">Caricamento...</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card h-100">
                        <div class="card-body text-center">
                            <div class="spinner-border text-warning mb-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h6 class="text-muted">Caricamento...</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card metric-card h-100">
                        <div class="card-body text-center">
                            <div class="spinner-border text-danger mb-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h6 class="text-muted">Caricamento...</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>BAIT Service Enterprise Control System</h6>
                    <p class="text-muted mb-0">{{ $version ?? 'Laravel Enterprise 1.0' }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        Migrato da Python a Laravel per prestazioni enterprise
                    </p>
                    <small class="text-muted">
                        Ultima elaborazione: <span id="last-processing">Loading...</span>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Load system status on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStatus();
            loadLiveMetrics();
            
            // Auto-refresh every 30 seconds
            setInterval(loadSystemStatus, 30000);
            setInterval(loadLiveMetrics, 30000);
        });

        async function loadSystemStatus() {
            try {
                const response = await fetch('/api/system/status');
                const data = await response.json();
                
                const statusEl = document.getElementById('system-status');
                const infoEl = document.getElementById('status-info');
                
                if (data.system.status === 'operational') {
                    statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-1"></i>';
                    infoEl.innerHTML = `
                        <div class="text-success fw-bold">Sistema Operativo</div>
                        <small class="text-light">Versione: ${data.system.version}</small>
                    `;
                } else {
                    statusEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-1"></i>';
                    infoEl.innerHTML = '<div class="text-danger fw-bold">Sistema Non Operativo</div>';
                }
            } catch (error) {
                console.error('Failed to load system status:', error);
                const statusEl = document.getElementById('system-status');
                statusEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>';
            }
        }

        async function loadLiveMetrics() {
            try {
                const response = await fetch('/api/system/status');
                const data = await response.json();
                
                const metricsHtml = `
                    <div class="col-md-3">
                        <div class="card metric-card h-100">
                            <div class="card-body text-center">
                                <div class="text-primary fs-2 fw-bold">${data.database.activities.toLocaleString()}</div>
                                <h6 class="text-muted mb-0">Attività Totali</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card h-100">
                            <div class="card-body text-center">
                                <div class="text-success fs-2 fw-bold">${data.database.technicians}</div>
                                <h6 class="text-muted mb-0">Tecnici Attivi</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card h-100">
                            <div class="card-body text-center">
                                <div class="text-warning fs-2 fw-bold">${data.recent_activity.alerts_last_hour}</div>
                                <h6 class="text-muted mb-0">Alert Ultima Ora</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card h-100">
                            <div class="card-body text-center">
                                <div class="text-danger fs-2 fw-bold">${data.recent_activity.critical_alerts_today}</div>
                                <h6 class="text-muted mb-0">Alert Critici Oggi</h6>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('live-metrics').innerHTML = metricsHtml;
                
                // Update last processing time
                document.getElementById('last-processing').textContent = 
                    data.system.last_processing || 'Mai';
                    
            } catch (error) {
                console.error('Failed to load live metrics:', error);
            }
        }

        async function triggerProcessing() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-spinner spin me-2"></i>Elaborazione in corso...';
            
            try {
                const response = await fetch('/api/activities/process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({ force_refresh: true })
                });
                
                const result = await response.json();
                
                if (result.metadata) {
                    alert(`Elaborazione completata!\n\nFile processati: ${result.metadata.files_processed.length}\nRecord totali: ${result.kpis_v2.system_kpis.total_records_processed}\nAlert generati: ${result.kpis_v2.system_kpis.alerts_generated}\nTempo: ${result.metadata.processing_duration.toFixed(2)}s`);
                    
                    // Refresh metrics
                    loadLiveMetrics();
                } else {
                    alert('Elaborazione completata (modalità demo)');
                }
                
            } catch (error) {
                console.error('Processing failed:', error);
                alert('Errore durante l\'elaborazione. Controllare i log.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        async function loadDashboard() {
            window.location.href = '/dashboard';
        }
    </script>
    
    <style>
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>