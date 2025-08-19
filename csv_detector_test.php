<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAIT Service - Test CSV Auto-Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .detection-card { transition: all 0.3s ease; }
        .detection-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .confidence-high { background: linear-gradient(135deg, #28a745, #20c997); }
        .confidence-medium { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .confidence-low { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .upload-zone { border: 2px dashed #dee2e6; transition: all 0.3s ease; }
        .upload-zone:hover { border-color: #007bff; background-color: #f8f9fa; }
        .upload-zone.dragover { border-color: #28a745; background-color: #d4edda; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-magic text-primary me-3"></i>
                    BAIT Service - Test CSV Auto-Detection
                </h1>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb me-2"></i>Rivoluzione CSV</h5>
                    <p class="mb-0">
                        <strong>NOVITÀ:</strong> Il sistema ora riconosce automaticamente il tipo di file CSV dal contenuto, 
                        non più solo dal nome! Carica "rapportini_agosto.csv" e il sistema lo riconoscerà come file attività.
                    </p>
                </div>
            </div>
        </div>

        <!-- Upload Zone -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-upload me-2"></i>Test Upload Intelligente</h5>
                    </div>
                    <div class="card-body">
                        <div class="upload-zone p-5 text-center rounded" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h5>Trascina qui i tuoi file CSV con qualsiasi nome</h5>
                            <p class="text-muted">Il sistema riconoscerà automaticamente il tipo di contenuto</p>
                            <input type="file" id="fileInput" multiple accept=".csv" class="d-none">
                            <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-folder-open me-2"></i>Seleziona File
                            </button>
                        </div>
                        <div id="uploadResults" class="mt-4" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test con File Esistenti -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-vial me-2"></i>Test File Esistenti</h5>
                    </div>
                    <div class="card-body">
                        <p>Testa l'auto-detection sui file già presenti nel sistema:</p>
                        <div class="row" id="existingFiles">
                            <!-- Files will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tipi Supportati -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-list me-2"></i>Tipi CSV Supportati</h5>
                    </div>
                    <div class="card-body" id="supportedTypes">
                        <!-- Types will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Setup drag & drop
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            
            uploadFiles(formData);
        }
        
        function uploadFiles(formData) {
            const resultsDiv = document.getElementById('uploadResults');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Analizzando file...</div>';
            
            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displayUploadResults(data);
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="alert alert-danger">Errore durante l\'upload: ' + error + '</div>';
            });
        }
        
        function displayUploadResults(data) {
            const resultsDiv = document.getElementById('uploadResults');
            let html = '<h6>Risultati Upload:</h6>';
            
            if (data.success) {
                html += '<div class="alert alert-success">' + data.message + '</div>';
                
                if (data.uploaded_files && data.uploaded_files.length > 0) {
                    html += '<div class="row">';
                    data.uploaded_files.forEach(file => {
                        let badgeClass = file.auto_detected ? 'bg-success' : 'bg-primary';
                        let icon = file.auto_detected ? 'fa-magic' : 'fa-file';
                        
                        html += `
                            <div class="col-md-6 mb-3">
                                <div class="card detection-card">
                                    <div class="card-body">
                                        <h6><i class="fas ${icon} me-2"></i>${file.original_name}</h6>
                                        ${file.auto_detected ? `
                                            <p class="mb-2"><strong>Rilevato come:</strong> 
                                                <span class="badge ${badgeClass}">${file.final_name}</span>
                                            </p>
                                            <p class="mb-2"><strong>Confidence:</strong> ${file.confidence}%</p>
                                            <p class="mb-0"><small class="text-muted">${file.detection_info}</small></p>
                                        ` : `
                                            <p class="mb-0"><span class="badge ${badgeClass}">Nome standard</span></p>
                                        `}
                                        <p class="mb-0"><small class="text-muted">Dimensione: ${file.size}</small></p>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
            } else {
                html += '<div class="alert alert-danger">' + data.message + '</div>';
            }
            
            resultsDiv.innerHTML = html;
        }
        
        // Carica tipi supportati
        function loadSupportedTypes() {
            fetch('csv_detector_api.php?action=supported_types')
            .then(response => response.json())
            .then(data => {
                displaySupportedTypes(data);
            })
            .catch(error => {
                console.error('Error loading supported types:', error);
            });
        }
        
        function displaySupportedTypes(types) {
            const container = document.getElementById('supportedTypes');
            let html = '<div class="row">';
            
            Object.keys(types).forEach(type => {
                const info = types[type];
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-file-csv text-primary me-2"></i>${type}
                                </h6>
                                <p class="card-text">${info.description}</p>
                                <p class="mb-2"><strong>Colonne richieste:</strong></p>
                                <div class="mb-2">
                                    ${info.required_columns.map(col => `<span class="badge bg-danger me-1">${col}</span>`).join('')}
                                </div>
                                <p class="mb-2"><strong>Colonne opzionali:</strong></p>
                                <div>
                                    ${info.optional_columns.map(col => `<span class="badge bg-secondary me-1">${col}</span>`).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        // Carica file esistenti
        function loadExistingFiles() {
            fetch('csv_detector_api.php?action=test_existing')
            .then(response => response.json())
            .then(data => {
                displayExistingFiles(data);
            })
            .catch(error => {
                console.error('Error loading existing files:', error);
            });
        }
        
        function displayExistingFiles(files) {
            const container = document.getElementById('existingFiles');
            let html = '';
            
            files.forEach(file => {
                let confidenceClass = 'confidence-low';
                if (file.confidence >= 70) confidenceClass = 'confidence-high';
                else if (file.confidence >= 40) confidenceClass = 'confidence-medium';
                
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card detection-card h-100">
                            <div class="card-header text-white ${confidenceClass}">
                                <h6 class="mb-0">
                                    <i class="fas fa-file-csv me-2"></i>${file.filename}
                                </h6>
                            </div>
                            <div class="card-body">
                                ${file.success ? `
                                    <p><strong>Tipo:</strong> <span class="badge bg-primary">${file.detected_type}</span></p>
                                    <p><strong>Confidence:</strong> ${file.confidence}%</p>
                                    <p><small class="text-muted">${file.description}</small></p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="showDetails('${file.filename}')">
                                        <i class="fas fa-info-circle me-1"></i>Dettagli
                                    </button>
                                ` : `
                                    <p class="text-danger">❌ ${file.error}</p>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (html === '') {
                html = '<div class="col-12"><p class="text-muted">Nessun file CSV trovato nella directory upload_csv/</p></div>';
            }
            
            container.innerHTML = html;
        }
        
        function showDetails(filename) {
            // Implementa modal con dettagli completi
            alert('Dettagli per ' + filename + ' (da implementare)');
        }
        
        // Inizializza
        document.addEventListener('DOMContentLoaded', function() {
            loadSupportedTypes();
            loadExistingFiles();
        });
    </script>
</body>
</html>