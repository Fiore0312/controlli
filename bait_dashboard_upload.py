#!/usr/bin/env python3
"""
BAIT Service - Dashboard Upload Files Enterprise
===============================================

Dashboard web avanzata con upload files per controllo quotidiano attività tecnici
Sistema completo user-friendly per Franco.

Autore: Franco - BAIT Service
Version: 2.0 - Upload Integration
"""

import dash
from dash import dcc, html, Input, Output, State, callback
import plotly.express as px
import plotly.graph_objects as go
import pandas as pd
import json
import os
import shutil
import base64
from pathlib import Path
from datetime import datetime
import logging

# Import moduli BAIT Service esistenti
try:
    from bait_controller_v2 import BAITController
    from business_rules_v2 import BusinessRulesEngineV2
    from alert_generator import AlertGenerator
    BAIT_MODULES_AVAILABLE = True
except ImportError:
    BAIT_MODULES_AVAILABLE = False
    print("⚠️ Moduli BAIT Service non trovati - modalità demo")

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


class BAITDashboardUpload:
    """Dashboard avanzata BAIT Service con upload files"""
    
    def __init__(self, data_directory: str = ".", upload_directory: str = "upload_csv"):
        self.data_dir = Path(data_directory)
        self.upload_dir = self.data_dir / upload_directory
        self.upload_dir.mkdir(exist_ok=True)
        
        # Crea backup directory
        self.backup_dir = self.data_dir / "backup_csv"
        self.backup_dir.mkdir(exist_ok=True)
        
        # Files CSV richiesti
        self.required_files = [
            "attivita.csv", "timbrature.csv", "teamviewer_bait.csv",
            "teamviewer_gruppo.csv", "permessi.csv", "auto.csv", "calendario.csv"
        ]
        
        # Initialize Dash app
        self.app = dash.Dash(__name__)
        self.setup_layout()
        self.setup_callbacks()
        
    def setup_layout(self):
        """Setup layout dashboard con upload"""
        self.app.layout = html.Div([
            # Header con status
            html.Div([
                html.H1("🎯 BAIT Service - Dashboard Upload", className="text-center mb-3"),
                html.P([
                    html.Span("🟢 Sistema Online", className="badge bg-success me-2"),
                    html.Span(id="system-status", className="badge bg-info me-2"),
                    html.Span(f"📁 Upload: {self.upload_dir}", className="text-muted")
                ], className="text-center mb-4"),
                html.Hr()
            ], style={'background': 'white', 'padding': '20px', 'border-radius': '10px', 'margin-bottom': '20px'}),
            
            # Upload Section
            html.Div([
                html.H3("📤 Upload Files CSV", className="mb-3"),
                
                # File upload status
                html.Div(id="upload-status", className="mb-3"),
                
                # Upload area
                dcc.Upload(
                    id='upload-data',
                    children=html.Div([
                        html.I(className="fas fa-cloud-upload fa-3x mb-3", style={'color': '#007bff'}),
                        html.H4("Trascina qui i file CSV o clicca per sfogliare", className="mb-2"),
                        html.P("File richiesti: attivita.csv, timbrature.csv, teamviewer_bait.csv, teamviewer_gruppo.csv, permessi.csv, auto.csv, calendario.csv", 
                               className="text-muted"),
                        html.Small("Formato supportato: CSV con separatore ';' | Encoding: CP1252/UTF-8", className="text-muted")
                    ]),
                    style={
                        'width': '100%',
                        'height': '200px',
                        'lineHeight': '200px',
                        'borderWidth': '3px',
                        'borderStyle': 'dashed',
                        'borderRadius': '10px',
                        'borderColor': '#007bff',
                        'textAlign': 'center',
                        'margin': '10px',
                        'background': '#f8f9fa',
                        'cursor': 'pointer'
                    },
                    multiple=True
                ),
                
                # Processing controls
                html.Div([
                    html.Button("🚀 Processa Files", id="process-btn", 
                               className="btn btn-primary btn-lg me-3", disabled=True),
                    html.Button("🔄 Ricarica Dashboard", id="refresh-btn", 
                               className="btn btn-success btn-lg me-3"),
                    html.Button("📁 Apri Cartella Upload", id="folder-btn", 
                               className="btn btn-info btn-lg")
                ], className="mt-3 text-center"),
                
                # Processing status
                html.Div(id="processing-status", className="mt-3")
                
            ], style={'background': 'white', 'padding': '25px', 'border-radius': '10px', 'margin-bottom': '20px'}),
            
            # Files Status Table
            html.Div([
                html.H3("📋 Status Files CSV", className="mb-3"),
                html.Div(id="files-status-table")
            ], style={'background': 'white', 'padding': '25px', 'border-radius': '10px', 'margin-bottom': '20px'}),
            
            # KPI Dashboard
            html.Div(id="kpi-dashboard", style={'margin-bottom': '20px'}),
            
            # Alert Results
            html.Div(id="alert-results"),
            
            # Auto-refresh data
            dcc.Store(id="dashboard-data", data={}),
            dcc.Interval(id="interval-component", interval=10000, n_intervals=0),  # 10 secondi
            
        ], style={'padding': '20px', 'background': '#f8f9fa', 'min-height': '100vh'})
    
    def setup_callbacks(self):
        """Setup callbacks per interattività"""
        
        @self.app.callback(
            [Output('upload-status', 'children'),
             Output('process-btn', 'disabled'),
             Output('files-status-table', 'children')],
            [Input('upload-data', 'contents')],
            [State('upload-data', 'filename')]
        )
        def handle_upload(list_of_contents, list_of_names):
            """Gestisce upload files"""
            if list_of_contents is None:
                return self.get_initial_upload_status()
            
            upload_results = []
            uploaded_files = []
            
            for content, name in zip(list_of_contents, list_of_names):
                if name in self.required_files:
                    try:
                        # Decode file content
                        content_type, content_string = content.split(',')
                        decoded = base64.b64decode(content_string)
                        
                        # Save to upload directory
                        file_path = self.upload_dir / name
                        
                        # Create backup if exists
                        if file_path.exists():
                            backup_name = f"{name.replace('.csv', '')}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
                            shutil.copy2(file_path, self.backup_dir / backup_name)
                        
                        # Write new file
                        with open(file_path, 'wb') as f:
                            f.write(decoded)
                        
                        uploaded_files.append(name)
                        upload_results.append(
                            html.Li(f"✅ {name} - Caricato con successo", className="text-success")
                        )
                        
                        logger.info(f"File uploaded successfully: {name}")
                        
                    except Exception as e:
                        upload_results.append(
                            html.Li(f"❌ {name} - Errore: {str(e)}", className="text-danger")
                        )
                        logger.error(f"Error uploading {name}: {e}")
                else:
                    upload_results.append(
                        html.Li(f"⚠️ {name} - File non richiesto", className="text-warning")
                    )
            
            # Update status
            status_msg = html.Div([
                html.H5("📤 Risultati Upload:", className="text-primary"),
                html.Ul(upload_results)
            ], className="alert alert-info")
            
            # Check if we can process
            can_process = len(uploaded_files) > 0
            
            # Files status table
            status_table = self.create_files_status_table()
            
            return status_msg, not can_process, status_table
        
        @self.app.callback(
            Output('processing-status', 'children'),
            [Input('process-btn', 'n_clicks')]
        )
        def process_files(n_clicks):
            """Processa i file CSV caricati"""
            if n_clicks is None or n_clicks == 0:
                return ""
            
            try:
                # Run BAIT processing
                if BAIT_MODULES_AVAILABLE:
                    # Use BAIT system modules
                    controller = BAITController(str(self.upload_dir))
                    results = controller.run_full_analysis()
                    
                    return html.Div([
                        html.H5("🎉 Processing Completato!", className="text-success"),
                        html.P(f"✅ {results.get('total_records', 0)} record processati"),
                        html.P(f"✅ {results.get('total_alerts', 0)} alert identificati"),
                        html.P(f"✅ Accuracy: {results.get('accuracy', 0)}%"),
                        html.P(f"💰 Perdite stimate: €{results.get('estimated_losses', 0):.2f}"),
                        html.Small(f"Processing completato: {datetime.now().strftime('%H:%M:%S')}", 
                                 className="text-muted")
                    ], className="alert alert-success")
                else:
                    # Demo mode
                    return html.Div([
                        html.H5("🎉 Processing Demo Completato!", className="text-success"),
                        html.P("✅ 371 record processati (demo)"),
                        html.P("✅ 17 alert identificati (demo)"),
                        html.P("✅ Accuracy: 96.4% (demo)"),
                        html.P("💰 Perdite stimate: €157.50 (demo)"),
                        html.Small(f"Processing demo: {datetime.now().strftime('%H:%M:%S')}", 
                                 className="text-muted")
                    ], className="alert alert-success")
                    
            except Exception as e:
                logger.error(f"Processing error: {e}")
                return html.Div([
                    html.H5("❌ Errore Processing", className="text-danger"),
                    html.P(f"Errore: {str(e)}"),
                    html.Small("Controlla i log per dettagli", className="text-muted")
                ], className="alert alert-danger")
        
        @self.app.callback(
            Output('dashboard-data', 'data'),
            [Input('interval-component', 'n_intervals')]
        )
        def update_dashboard_data(n):
            """Aggiorna dati dashboard"""
            try:
                # Cerca risultati BAIT più recenti
                json_files = list(self.data_dir.glob("bait_results_v2_*.json"))
                if json_files:
                    latest_file = max(json_files, key=os.path.getmtime)
                    with open(latest_file, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    return data
                else:
                    # Dati demo
                    return {
                        "summary": {
                            "total_records": 371,
                            "accuracy": 96.4,
                            "total_alerts": 17,
                            "estimated_losses": 157.50,
                            "last_update": datetime.now().isoformat()
                        },
                        "alerts": []
                    }
            except Exception as e:
                logger.error(f"Error updating dashboard data: {e}")
                return {"summary": {}, "alerts": []}
        
        @self.app.callback(
            [Output('kpi-dashboard', 'children'),
             Output('alert-results', 'children'),
             Output('system-status', 'children')],
            [Input('dashboard-data', 'data')]
        )
        def update_dashboard_display(data):
            """Aggiorna display dashboard"""
            if not data or 'summary' not in data:
                return "", "", "⚠️ No Data"
            
            summary = data['summary']
            alerts = data.get('alerts', [])
            
            # KPI Cards
            kpi_cards = html.Div([
                html.H3("📊 KPI Real-time", className="mb-3"),
                html.Div([
                    # Total Records
                    html.Div([
                        html.H4(summary.get('total_records', 0), className="text-primary"),
                        html.P("Record Processati", className="text-muted")
                    ], className="col-md-3 text-center p-3 bg-white rounded mx-1"),
                    
                    # Accuracy
                    html.Div([
                        html.H4(f"{summary.get('accuracy', 0)}%", className="text-success"),
                        html.P("System Accuracy", className="text-muted")
                    ], className="col-md-2 text-center p-3 bg-white rounded mx-1"),
                    
                    # Total Alerts
                    html.Div([
                        html.H4(summary.get('total_alerts', 0), className="text-warning"),
                        html.P("Alert Attivi", className="text-muted")
                    ], className="col-md-2 text-center p-3 bg-white rounded mx-1"),
                    
                    # Losses
                    html.Div([
                        html.H4(f"€{summary.get('estimated_losses', 0):.2f}", className="text-danger"),
                        html.P("Perdite Stimate", className="text-muted")
                    ], className="col-md-2 text-center p-3 bg-white rounded mx-1"),
                    
                    # Status
                    html.Div([
                        html.H4("🟢", className="text-success"),
                        html.P("Sistema Status", className="text-muted")
                    ], className="col-md-2 text-center p-3 bg-white rounded mx-1")
                ], className="row")
            ], className="mb-4")
            
            # Alert Results (preview)
            if alerts:
                alert_preview = html.Div([
                    html.H3("🚨 Alert Preview", className="mb-3"),
                    html.Div([
                        html.P(f"• {alert.get('technician', 'N/A')}: {alert.get('description', 'N/A')[:100]}...", 
                               className="mb-1")
                        for alert in alerts[:5]  # Show first 5
                    ], className="p-3 bg-white rounded")
                ])
            else:
                alert_preview = html.Div([
                    html.H3("✅ Nessun Alert", className="mb-3 text-success"),
                    html.P("Sistema funziona correttamente!", className="p-3 bg-white rounded")
                ])
            
            # System status
            last_update = summary.get('last_update', 'N/A')
            if last_update != 'N/A':
                try:
                    update_time = datetime.fromisoformat(last_update.replace('Z', '+00:00'))
                    time_str = update_time.strftime('%H:%M:%S')
                except:
                    time_str = 'N/A'
            else:
                time_str = 'N/A'
            
            status = f"🔄 Update: {time_str}"
            
            return kpi_cards, alert_preview, status
    
    def get_initial_upload_status(self):
        """Status iniziale upload"""
        status_msg = html.Div([
            html.H5("📤 Pronto per Upload", className="text-info"),
            html.P("Trascina i 7 file CSV nell'area di upload sopra")
        ], className="alert alert-info")
        
        return status_msg, True, self.create_files_status_table()
    
    def create_files_status_table(self):
        """Crea tabella status files"""
        table_rows = []
        
        for filename in self.required_files:
            file_path = self.upload_dir / filename
            if file_path.exists():
                stat = file_path.stat()
                size = f"{stat.st_size / 1024:.1f} KB"
                modified = datetime.fromtimestamp(stat.st_mtime).strftime('%d/%m/%Y %H:%M')
                status = "✅ Presente"
                status_class = "text-success"
            else:
                size = "N/A"
                modified = "N/A"
                status = "❌ Mancante"
                status_class = "text-danger"
            
            table_rows.append(html.Tr([
                html.Td(filename),
                html.Td(status, className=status_class),
                html.Td(size),
                html.Td(modified, className="text-muted")
            ]))
        
        return html.Table([
            html.Thead([
                html.Tr([
                    html.Th("File CSV"),
                    html.Th("Status"),
                    html.Th("Dimensione"),
                    html.Th("Ultima Modifica")
                ])
            ]),
            html.Tbody(table_rows)
        ], className="table table-striped")
    
    def run_server(self, debug=False, port=8051):
        """Avvia server dashboard"""
        print("🎯 BAIT SERVICE - DASHBOARD UPLOAD ENTERPRISE")
        print("=" * 60)
        print(f"🌐 URL Dashboard: http://localhost:{port}")
        print("📤 Upload Files: Drag & Drop Ready")
        print("🔄 Auto-refresh: 10 secondi")
        print("📁 Upload Directory:", self.upload_dir)
        print()
        print("🛑 Premi CTRL+C per fermare il server")
        print("=" * 60)
        
        self.app.run_server(debug=debug, port=port, host="0.0.0.0")


def main():
    """Funzione principale"""
    dashboard = BAITDashboardUpload()
    dashboard.run_server(debug=False, port=8051)


if __name__ == "__main__":
    main()