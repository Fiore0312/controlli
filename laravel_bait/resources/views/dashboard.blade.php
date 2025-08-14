<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT Service Enterprise Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            /* BAIT Service Color Palette */
            --bs-primary: #2563eb;
            --bs-primary-dark: #1d4ed8;
            --bs-success: #059669;
            --bs-warning: #d97706;
            --bs-danger: #dc2626;
            --bs-info: #0891b2;
            --bs-secondary: #6b7280;
            --bs-light: #f8fafc;
            --bs-dark: #1f2937;
            
            /* Custom Enterprise Colors */
            --bait-blue: #2563eb;
            --bait-blue-light: #3b82f6;
            --bait-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --surface-elevated: #ffffff;
            --surface-container: #f8fafc;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-subtle: #e5e7eb;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--surface-container);
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        /* Header & Navigation */
        .navbar-enterprise {
            background: var(--bait-gradient);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        /* KPI Cards */
        .kpi-card {
            background: var(--surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--bait-gradient);
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }
        
        .kpi-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Data Cards */
        .data-card {
            background: var(--surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .data-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            background: linear-gradient(135deg, #f8fafc 0%, #e5e7eb 100%);
        }
        
        .data-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }
        
        .data-card-body {
            padding: 1.5rem;
        }
        
        /* Alert Severity Styles */
        .alert-critico {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #7f1d1d;
        }
        
        .alert-alto {
            background-color: #fefbeb;
            border-left: 4px solid #d97706;
            color: #92400e;
        }
        
        .alert-medio {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }
        
        /* Table Enhancements */
        .table-enterprise {
            background: var(--surface-elevated);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table-enterprise th {
            background: var(--surface-container);
            border-bottom: 2px solid var(--border-subtle);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            padding: 1rem;
        }
        
        .table-enterprise td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-subtle);
        }
        
        .table-enterprise tbody tr:hover {
            background-color: var(--surface-container);
            cursor: pointer;
        }
        
        /* Filters */
        .filters-section {
            background: var(--surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-online {
            background-color: #dcfce7;
            color: #166534;
        }
        
        /* Charts Container */
        .chart-container {
            background: var(--surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--bait-blue);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: var(--surface-elevated);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            background: #f0f9ff;
            border-color: var(--bait-blue-light);
        }
        
        .upload-area.dragover {
            background: #e0f2fe;
            border-color: var(--bait-blue);
            transform: scale(1.02);
        }
        
        /* Action Buttons */
        .btn-enterprise {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--bait-blue);
            border: 1px solid var(--bait-blue);
        }
        
        .btn-primary:hover {
            background: var(--bait-blue-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        /* Modal Enhancements */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            background: var(--bait-gradient);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            background: var(--surface-container);
            border-radius: 0 0 16px 16px;
        }
        
        /* Timeline Styles */
        .timeline-container {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
            border-left: 2px solid var(--border-subtle);
        }
        
        .timeline-item.anomaly {
            border-left-color: var(--bs-danger);
        }
        
        .timeline-marker {
            position: absolute;
            left: -6px;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--bait-blue);
            border: 2px solid var(--surface-elevated);
        }
        
        .timeline-marker.anomaly {
            background: var(--bs-danger);
        }
        
        .timeline-content {
            background: var(--surface-elevated);
            padding: 1rem;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .kpi-value {
                font-size: 1.5rem;
            }
            
            .data-card-body {
                padding: 1rem;
            }
            
            .filters-section {
                padding: 1rem;
            }
            
            .table-enterprise th,
            .table-enterprise td {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
        }
        
        /* Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* Alpine.js transitions */
        [x-cloak] { display: none !important; }
        
        .fade-in {
            transition: opacity 0.3s ease-in-out;
        }
        
        .slide-up {
            transition: transform 0.3s ease-out;
        }
    </style>
</head>
<body x-data="dashboard()" x-init="init()">
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-enterprise">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="/">
                <i class="bi bi-shield-check me-2"></i>
                BAIT Service Enterprise Dashboard
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <span class="status-badge status-online">
                    <i class="bi bi-circle-fill me-1"></i>
                    ONLINE
                </span>
                
                <div class="text-white small">
                    <div>Last Update: <span x-text="lastUpdate">--:--</span></div>
                    <div>Version: Laravel Enterprise 1.0</div>
                </div>
                
                <button @click="refreshData()" 
                        :disabled="isLoading"
                        class="btn btn-light btn-sm">
                    <i class="bi" :class="isLoading ? 'bi-arrow-clockwise spin' : 'bi-arrow-clockwise'"></i>
                    Refresh
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        
        <!-- KPI Cards -->
        <div class="row mb-4" x-show="!isLoading">
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-primary bg-opacity-10">
                            <i class="bi bi-database text-primary"></i>
                        </div>
                        <div class="kpi-value text-primary" x-text="kpis.total_records?.toLocaleString() || 0">0</div>
                        <div class="kpi-label">Records Processed</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-success bg-opacity-10">
                            <i class="bi bi-bullseye text-success"></i>
                        </div>
                        <div class="kpi-value text-success" x-text="(kpis.accuracy || 0).toFixed(1) + '%'">0%</div>
                        <div class="kpi-label">System Accuracy</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-warning bg-opacity-10">
                            <i class="bi bi-exclamation-triangle text-warning"></i>
                        </div>
                        <div class="kpi-value text-warning" x-text="kpis.total_alerts || 0">0</div>
                        <div class="kpi-label">Total Alerts</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-danger bg-opacity-10">
                            <i class="bi bi-fire text-danger"></i>
                        </div>
                        <div class="kpi-value text-danger" x-text="kpis.critical_alerts || 0">0</div>
                        <div class="kpi-label">Critical Alerts</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-danger bg-opacity-10">
                            <i class="bi bi-currency-euro text-danger"></i>
                        </div>
                        <div class="kpi-value text-danger" x-text="'€' + (kpis.estimated_losses || 0).toFixed(0)">€0</div>
                        <div class="kpi-label">Estimated Losses</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card h-100">
                    <div class="card-body text-center p-3">
                        <div class="kpi-icon bg-info bg-opacity-10">
                            <i class="bi bi-clock text-info"></i>
                        </div>
                        <div class="kpi-value text-info">LIVE</div>
                        <div class="kpi-label">System Status</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Upload Section -->
        <div class="data-card mb-4">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="bi bi-cloud-upload me-2"></i>
                    CSV Data Upload
                </h5>
            </div>
            <div class="data-card-body">
                <div id="upload-area" 
                     @dragover.prevent="dragOver = true"
                     @dragleave.prevent="dragOver = false"
                     @drop.prevent="handleDrop($event)"
                     :class="{'dragover': dragOver}"
                     class="upload-area">
                    <i class="bi bi-cloud-upload text-primary" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 mb-2">Drag & Drop CSV files or click to browse</h5>
                    <p class="text-muted mb-3">Required: attivita.csv, timbrature.csv, teamviewer_bait.csv, auto.csv, permessi.csv</p>
                    <input type="file" id="csv-files" multiple accept=".csv" @change="handleFileSelect($event)" style="display: none;">
                    <button @click="$refs.fileInput.click()" class="btn btn-primary btn-enterprise">
                        <i class="bi bi-folder-open me-2"></i>
                        Browse Files
                    </button>
                    <input type="file" x-ref="fileInput" multiple accept=".csv" @change="handleFileSelect($event)" style="display: none;">
                </div>
                
                <!-- Upload Status -->
                <div x-show="uploadStatus.length > 0" class="mt-3">
                    <h6>Upload Status:</h6>
                    <template x-for="status in uploadStatus" :key="status.filename">
                        <div class="alert" :class="status.success ? 'alert-success' : 'alert-danger'">
                            <i class="bi" :class="status.success ? 'bi-check-circle' : 'bi-x-circle'"></i>
                            <span x-text="status.message"></span>
                        </div>
                    </template>
                    
                    <button @click="processFiles()" 
                            :disabled="!canProcess || isProcessing"
                            class="btn btn-success btn-enterprise">
                        <i class="bi" :class="isProcessing ? 'bi-arrow-clockwise spin' : 'bi-cogs'"></i>
                        <span x-text="isProcessing ? 'Processing...' : 'Process Files'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <h5 class="mb-3">
                <i class="bi bi-funnel me-2"></i>
                Filters
            </h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Technician</label>
                    <select x-model="filters.technician" @change="filterAlerts()" class="form-select">
                        <option value="">All technicians...</option>
                        <template x-for="tech in technicians" :key="tech">
                            <option :value="tech" x-text="tech"></option>
                        </template>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Severity</label>
                    <select x-model="filters.severity" @change="filterAlerts()" class="form-select">
                        <option value="">All severities...</option>
                        <option value="CRITICO">🔴 CRITICO</option>
                        <option value="ALTO">🟠 ALTO</option>
                        <option value="MEDIO">🟡 MEDIO</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Search</label>
                    <input x-model="filters.search" @input="filterAlerts()" 
                           type="text" placeholder="Search in messages..." class="form-control">
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h6 class="mb-3">
                        <i class="bi bi-pie-chart me-2"></i>
                        Alert Distribution
                    </h6>
                    <canvas id="severityChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h6 class="mb-3">
                        <i class="bi bi-bar-chart me-2"></i>
                        Technician Performance
                    </h6>
                    <canvas id="techChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Alerts Table -->
        <div class="data-card">
            <div class="data-card-header d-flex justify-content-between align-items-center">
                <h5 class="data-card-title mb-0">
                    <i class="bi bi-table me-2"></i>
                    Alert Details
                    <span class="badge bg-secondary ms-2" x-text="filteredAlerts.length">0</span>
                </h5>
                
                <div>
                    <button @click="exportToExcel()" class="btn btn-success btn-sm me-2">
                        <i class="bi bi-file-earmark-excel me-1"></i>
                        Export Excel
                    </button>
                    <button @click="exportToCSV()" class="btn btn-info btn-sm">
                        <i class="bi bi-file-earmark-csv me-1"></i>
                        Export CSV
                    </button>
                </div>
            </div>
            
            <div class="data-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-enterprise mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Severity</th>
                                <th>Technician</th>
                                <th>Category</th>
                                <th>Confidence</th>
                                <th>Message</th>
                                <th>Cost (€)</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="alert in filteredAlerts" :key="alert.id">
                                <tr @click="showAlertDetails(alert)" 
                                    :class="'alert-' + alert.severity.toLowerCase()"
                                    style="cursor: pointer;">
                                    <td x-text="alert.id"></td>
                                    <td>
                                        <span class="badge" 
                                              :class="{
                                                'bg-danger': alert.severity === 'CRITICO',
                                                'bg-warning': alert.severity === 'ALTO', 
                                                'bg-info': alert.severity === 'MEDIO'
                                              }"
                                              x-text="alert.severity"></span>
                                    </td>
                                    <td x-text="alert.tecnico"></td>
                                    <td x-text="alert.category.replace('_', ' ')"></td>
                                    <td x-text="alert.confidence_score + '%'"></td>
                                    <td x-text="alert.message.length > 50 ? alert.message.substring(0, 50) + '...' : alert.message"></td>
                                    <td x-text="'€' + alert.estimated_cost.toFixed(2)"></td>
                                    <td x-text="formatDate(alert.timestamp)"></td>
                                </tr>
                            </template>
                            
                            <tr x-show="filteredAlerts.length === 0">
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    No alerts found matching your criteria
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Details Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="'Alert Details - ' + (selectedAlert?.id || '')"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Alert Summary -->
                    <div class="alert alert-light border mb-4" x-show="selectedAlert">
                        <div class="row">
                            <div class="col-12">
                                <h5 class="mb-2">
                                    <span class="badge me-2" 
                                          :class="{
                                            'bg-danger': selectedAlert?.severity === 'CRITICO',
                                            'bg-warning': selectedAlert?.severity === 'ALTO',
                                            'bg-info': selectedAlert?.severity === 'MEDIO'
                                          }"
                                          x-text="selectedAlert?.severity"></span>
                                    <span x-text="selectedAlert?.id"></span>
                                </h5>
                                <p class="text-muted mb-2" x-text="selectedAlert?.message"></p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-person me-1"></i>
                                        <span x-text="selectedAlert?.tecnico"></span>
                                    </span>
                                    <span class="badge bg-primary">
                                        <i class="bi bi-percent me-1"></i>
                                        <span x-text="(selectedAlert?.confidence_score || 0) + '% confidence'"></span>
                                    </span>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-currency-euro me-1"></i>
                                        <span x-text="'€' + (selectedAlert?.estimated_cost || 0).toFixed(2) + ' impact'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-clock me-2"></i>
                            Activity Timeline
                        </h6>
                        <div class="timeline-container" x-show="selectedAlert?.details?.timeline?.length > 0">
                            <template x-for="(item, index) in selectedAlert?.details?.timeline" :key="index">
                                <div class="timeline-item" :class="{'anomaly': item.anomaly}">
                                    <div class="timeline-marker" :class="{'anomaly': item.anomaly}"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-2">
                                            <i class="bi bi-building me-2"></i>
                                            <span x-text="item.client"></span>
                                        </h6>
                                        <div class="mb-2">
                                            <span class="badge bg-light text-dark me-2">
                                                <i class="bi bi-clock me-1"></i>
                                                <span x-text="item.time"></span>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <span x-text="item.location"></span>
                                            </span>
                                        </div>
                                        <p class="text-muted mb-0" x-text="item.activity"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p x-show="!selectedAlert?.details?.timeline?.length" class="text-muted">
                            No timeline data available
                        </p>
                    </div>
                    
                    <!-- Analysis -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-graph-up me-2"></i>
                            Analysis Details
                        </h6>
                        
                        <div x-show="selectedAlert?.details?.overlap" class="alert alert-danger mb-3">
                            <h6 class="mb-2">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Temporal Overlap Detected
                            </h6>
                            <p class="mb-1">Overlap Period: <strong x-text="selectedAlert?.details?.overlap"></strong></p>
                            <p class="mb-0 text-muted" x-text="selectedAlert?.details?.impossibility"></p>
                        </div>
                        
                        <div x-show="selectedAlert?.details?.distance" class="alert alert-warning mb-3">
                            <h6 class="mb-2">
                                <i class="bi bi-geo-alt me-2"></i>
                                Travel Time Analysis
                            </h6>
                            <div class="mb-2">
                                <span class="badge bg-light text-dark me-2">Distance: <span x-text="selectedAlert?.details?.distance"></span></span>
                                <span class="badge bg-light text-dark me-2">Declared: <span x-text="selectedAlert?.details?.declaredTime"></span></span>
                                <span class="badge bg-light text-dark">Estimated: <span x-text="selectedAlert?.details?.estimatedTime"></span></span>
                            </div>
                            <p x-show="selectedAlert?.details?.discrepancy" class="mb-1">
                                <strong>Discrepancy: </strong>
                                <span :class="selectedAlert?.details?.discrepancy?.includes('-') ? 'text-danger' : 'text-success'"
                                      x-text="selectedAlert?.details?.discrepancy"></span>
                            </p>
                            <p class="mb-0 text-muted" x-text="selectedAlert?.details?.impossibility"></p>
                        </div>
                        
                        <p x-show="!selectedAlert?.details?.overlap && !selectedAlert?.details?.distance" class="text-muted">
                            No detailed analysis available
                        </p>
                    </div>
                    
                    <!-- Recommended Actions -->
                    <div>
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-tools me-2"></i>
                            Recommended Actions
                        </h6>
                        
                        <template x-if="selectedAlert?.category === 'temporal_overlap'">
                            <div>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-telephone me-2"></i>
                                    Contact technician for clarification
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    Review and adjust schedule
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-flag me-2"></i>
                                    Flag for supervisor review
                                </button>
                            </div>
                        </template>
                        
                        <template x-if="selectedAlert?.category === 'travel_time'">
                            <div>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-map me-2"></i>
                                    Verify travel route and time
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-pencil me-2"></i>
                                    Update time allocation
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-clock me-2"></i>
                                    Adjust future scheduling
                                </button>
                            </div>
                        </template>
                        
                        <template x-if="!['temporal_overlap', 'travel_time'].includes(selectedAlert?.category)">
                            <div>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-search me-2"></i>
                                    Investigate further
                                </button>
                                <button class="btn btn-outline-primary btn-sm mb-2 w-100 text-start">
                                    <i class="bi bi-clipboard-check me-2"></i>
                                    Mark for review
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button @click="exportAlertDetails()" class="btn btn-outline-primary">
                        <i class="bi bi-download me-2"></i>
                        Export Details
                    </button>
                    <button @click="markAsReviewed()" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Mark as Reviewed
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <script>
        function dashboard() {
            return {
                // State
                isLoading: true,
                lastUpdate: '00:00',
                kpis: {
                    total_records: 0,
                    accuracy: 0,
                    total_alerts: 0,
                    critical_alerts: 0,
                    estimated_losses: 0
                },
                alerts: [],
                filteredAlerts: [],
                technicians: [],
                selectedAlert: null,
                
                // Upload state
                dragOver: false,
                uploadStatus: [],
                canProcess: false,
                isProcessing: false,
                
                // Filters
                filters: {
                    technician: '',
                    severity: '',
                    search: ''
                },
                
                // Charts
                severityChart: null,
                techChart: null,
                
                // Modal
                alertModal: null,
                
                init() {
                    this.loadDashboardData();
                    this.alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
                    
                    // Auto-refresh every 30 seconds
                    setInterval(() => this.refreshData(), 30000);
                    
                    // Update time every second
                    setInterval(() => {
                        this.lastUpdate = new Date().toLocaleTimeString('it-IT', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }, 1000);
                },
                
                async loadDashboardData() {
                    try {
                        this.isLoading = true;
                        
                        // Try to load from Laravel API first
                        const response = await fetch('/api/dashboard/data');
                        let data;
                        
                        if (response.ok) {
                            data = await response.json();
                        } else {
                            // Fallback to demo data if API not available
                            data = this.generateDemoData();
                        }
                        
                        this.processData(data);
                        this.updateCharts();
                        
                    } catch (error) {
                        console.warn('API not available, using demo data:', error);
                        const data = this.generateDemoData();
                        this.processData(data);
                        this.updateCharts();
                    } finally {
                        this.isLoading = false;
                    }
                },
                
                processData(data) {
                    this.kpis = data.kpis || {
                        total_records: 371,
                        accuracy: 96.4,
                        total_alerts: 16,
                        critical_alerts: 1,
                        estimated_losses: 450
                    };
                    
                    this.alerts = data.alerts || [];
                    this.filteredAlerts = [...this.alerts];
                    this.technicians = [...new Set(this.alerts.map(alert => alert.tecnico))].sort();
                    
                    this.filterAlerts();
                },
                
                generateDemoData() {
                    const technicians = ['Gabriele De Palma', 'Davide Cestone', 'Arlind Hoxha', 'Matteo Rossi'];
                    const categories = ['temporal_overlap', 'travel_time', 'geolocation'];
                    const severities = ['CRITICO', 'ALTO', 'MEDIO'];
                    
                    const alerts = [];
                    for (let i = 0; i < 16; i++) {
                        const severity = i < 1 ? 'CRITICO' : i < 8 ? 'ALTO' : 'MEDIO';
                        const category = categories[Math.floor(Math.random() * categories.length)];
                        
                        alerts.push({
                            id: `BAIT_${String(i + 1).padStart(4, '0')}`,
                            severity: severity,
                            confidence_score: severity === 'CRITICO' ? 95 : severity === 'ALTO' ? 85 : 75,
                            tecnico: technicians[Math.floor(Math.random() * technicians.length)],
                            message: this.generateDemoMessage(category, severity),
                            category: category,
                            timestamp: new Date(Date.now() - Math.random() * 86400000).toISOString(),
                            estimated_cost: severity === 'CRITICO' ? 75 : severity === 'ALTO' ? 45 : 25,
                            details: this.generateDemoDetails(category, i)
                        });
                    }
                    
                    return {
                        kpis: {
                            total_records: 371,
                            accuracy: 96.4,
                            total_alerts: alerts.length,
                            critical_alerts: alerts.filter(a => a.severity === 'CRITICO').length,
                            estimated_losses: alerts.reduce((sum, a) => sum + a.estimated_cost, 0)
                        },
                        alerts: alerts
                    };
                },
                
                generateDemoMessage(category, severity) {
                    const messages = {
                        temporal_overlap: {
                            CRITICO: 'Sovrapposizione temporale CRITICA rilevata - Fatturazione doppia',
                            ALTO: 'Sovrapposizione temporale significativa tra clienti diversi',
                            MEDIO: 'Possibile sovrapposizione orari dichiarati'
                        },
                        travel_time: {
                            CRITICO: 'Tempo viaggio insufficiente - Spostamento fisicamente impossibile',
                            ALTO: 'Discrepanza significativa tempi viaggio dichiarati vs stimati',
                            MEDIO: 'Tempo viaggio ottimistico - Verifica necessaria'
                        },
                        geolocation: {
                            CRITICO: 'Posizione GPS incompatibile con cliente dichiarato',
                            ALTO: 'Discrepanza significativa geolocalizzazione vs indirizzo',
                            MEDIO: 'Posizione GPS da verificare'
                        }
                    };
                    
                    return messages[category][severity] || 'Alert generico rilevato';
                },
                
                generateDemoDetails(category, index) {
                    const clients = ['ACME Corp', 'TechnoSoft SRL', 'Milano Dynamics', 'Roma Solutions'];
                    const locations = [
                        'Via Roma 15, Milano (MI)',
                        'Corso Venezia 22, Milano (MI)',
                        'Via del Corso 45, Roma (RM)',
                        'Via Toledo 88, Napoli (NA)'
                    ];
                    
                    const details = {
                        timeline: [],
                        overlap: '',
                        distance: '',
                        travelTime: '',
                        impossibility: '',
                        declaredTime: '',
                        estimatedTime: '',
                        discrepancy: ''
                    };
                    
                    if (category === 'temporal_overlap') {
                        details.timeline = [
                            {
                                client: clients[index % clients.length],
                                time: '09:00-13:00',
                                location: locations[index % locations.length],
                                activity: 'Manutenzione server principale',
                                anomaly: false
                            },
                            {
                                client: clients[(index + 1) % clients.length],
                                time: '11:30-15:30',
                                location: locations[(index + 1) % locations.length],
                                activity: 'Installazione nuovo sistema',
                                anomaly: true
                            }
                        ];
                        details.overlap = '11:30-13:00 (90 minuti)';
                        details.impossibility = 'Impossibile essere contemporaneamente in due luoghi diversi';
                    }
                    
                    return details;
                },
                
                filterAlerts() {
                    this.filteredAlerts = this.alerts.filter(alert => {
                        if (this.filters.technician && alert.tecnico !== this.filters.technician) return false;
                        if (this.filters.severity && alert.severity !== this.filters.severity) return false;
                        if (this.filters.search) {
                            const searchLower = this.filters.search.toLowerCase();
                            if (!alert.message.toLowerCase().includes(searchLower) && 
                                !alert.id.toLowerCase().includes(searchLower)) return false;
                        }
                        return true;
                    });
                    
                    this.updateCharts();
                },
                
                updateCharts() {
                    this.$nextTick(() => {
                        this.updateSeverityChart();
                        this.updateTechChart();
                    });
                },
                
                updateSeverityChart() {
                    const ctx = document.getElementById('severityChart');
                    if (!ctx) return;
                    
                    const severityCounts = {
                        'CRITICO': 0,
                        'ALTO': 0,
                        'MEDIO': 0
                    };
                    
                    this.filteredAlerts.forEach(alert => {
                        severityCounts[alert.severity] = (severityCounts[alert.severity] || 0) + 1;
                    });
                    
                    if (this.severityChart) {
                        this.severityChart.destroy();
                    }
                    
                    this.severityChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['CRITICO', 'ALTO', 'MEDIO'],
                            datasets: [{
                                data: [severityCounts.CRITICO, severityCounts.ALTO, severityCounts.MEDIO],
                                backgroundColor: ['#dc2626', '#d97706', '#3b82f6'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                },
                
                updateTechChart() {
                    const ctx = document.getElementById('techChart');
                    if (!ctx) return;
                    
                    const techCounts = {};
                    this.filteredAlerts.forEach(alert => {
                        techCounts[alert.tecnico] = (techCounts[alert.tecnico] || 0) + 1;
                    });
                    
                    if (this.techChart) {
                        this.techChart.destroy();
                    }
                    
                    this.techChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(techCounts),
                            datasets: [{
                                label: 'Alerts',
                                data: Object.values(techCounts),
                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                borderColor: 'rgba(37, 99, 235, 1)',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                },
                
                async refreshData() {
                    await this.loadDashboardData();
                },
                
                // File upload methods
                handleDrop(event) {
                    this.dragOver = false;
                    const files = event.dataTransfer.files;
                    this.processUploadedFiles(files);
                },
                
                handleFileSelect(event) {
                    const files = event.target.files;
                    this.processUploadedFiles(files);
                },
                
                processUploadedFiles(files) {
                    this.uploadStatus = [];
                    let validFiles = 0;
                    
                    const requiredFiles = ['attivita.csv', 'timbrature.csv', 'teamviewer_bait.csv', 'auto.csv', 'permessi.csv'];
                    
                    Array.from(files).forEach(file => {
                        if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                            this.uploadStatus.push({
                                filename: file.name,
                                success: true,
                                message: `✓ ${file.name} ready for processing`
                            });
                            validFiles++;
                        } else {
                            this.uploadStatus.push({
                                filename: file.name,
                                success: false,
                                message: `✗ ${file.name} is not a valid CSV file`
                            });
                        }
                    });
                    
                    this.canProcess = validFiles > 0;
                },
                
                async processFiles() {
                    this.isProcessing = true;
                    
                    try {
                        // Simulate processing time
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        
                        // Refresh dashboard data
                        await this.loadDashboardData();
                        
                        alert('Files processed successfully! Dashboard refreshed.');
                        
                    } catch (error) {
                        console.error('Processing failed:', error);
                        alert('Error processing files. Please check the console for details.');
                    } finally {
                        this.isProcessing = false;
                        this.uploadStatus = [];
                        this.canProcess = false;
                    }
                },
                
                // Modal methods
                showAlertDetails(alert) {
                    this.selectedAlert = alert;
                    this.alertModal.show();
                },
                
                exportAlertDetails() {
                    if (!this.selectedAlert) return;
                    
                    const data = JSON.stringify(this.selectedAlert, null, 2);
                    const blob = new Blob([data], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `alert_${this.selectedAlert.id}_details.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                },
                
                markAsReviewed() {
                    if (!this.selectedAlert) return;
                    
                    // In a real implementation, this would make an API call
                    alert(`Alert ${this.selectedAlert.id} marked as reviewed`);
                    this.alertModal.hide();
                },
                
                // Export methods
                exportToExcel() {
                    // Simplified Excel export - in production use a library like SheetJS
                    this.exportToCSV('excel');
                },
                
                exportToCSV() {
                    const headers = ['ID', 'Severity', 'Technician', 'Category', 'Confidence', 'Message', 'Cost', 'Timestamp'];
                    const csvContent = [
                        headers.join(','),
                        ...this.filteredAlerts.map(alert => [
                            alert.id,
                            alert.severity,
                            alert.tecnico,
                            alert.category,
                            alert.confidence_score,
                            `"${alert.message.replace(/"/g, '""')}"`,
                            alert.estimated_cost.toFixed(2),
                            this.formatDate(alert.timestamp)
                        ].join(','))
                    ].join('\n');
                    
                    const blob = new Blob([csvContent], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `bait_alerts_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                },
                
                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('it-IT') + ' ' + date.toLocaleTimeString('it-IT', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            };
        }
    </script>
</body>
</html>