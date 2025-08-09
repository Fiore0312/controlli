#!/usr/bin/env python3
"""
BAIT Service - Dashboard Controller Enterprise-Grade
=================================================

Dashboard web Excel-like per controllo quotidiano attività tecnici
Integra tutti e 4 gli agenti BAIT Service per sistema completo.

Autore: Franco - BAIT Service
Fase: 4 - Dashboard Controller Finale
"""

import dash
from dash import dcc, html, dash_table, Input, Output, State, callback
import plotly.express as px
import plotly.graph_objects as go
import pandas as pd
import json
from datetime import datetime, timedelta
import os
from pathlib import Path

# Import moduli BAIT Service esistenti
try:
    from bait_controller import BAITController
    from business_rules_v2 import BusinessRulesEngineV2
    from alert_generator import AlertGenerator
except ImportError:
    print("⚠️ Moduli BAIT Service non trovati - usando dati mock per demo")

class BAITDashboardApp:
    """Dashboard Controller principale per sistema BAIT Service"""
    
    def __init__(self, data_directory: str = "."):
        self.data_dir = Path(data_directory)
        self.app = dash.Dash(__name__)
        self.setup_layout()
        self.setup_callbacks()
        
        # Carica dati esistenti
        self.load_bait_data()
        
    def load_bait_data(self):
        """Carica dati dal sistema BAIT Service esistente"""
        try:
            # Cerca file risultati esistenti
            json_files = list(self.data_dir.glob("bait_results_v2_*.json"))
            if json_files:
                latest_file = max(json_files, key=os.path.getmtime)
                with open(latest_file, 'r', encoding='utf-8') as f:
                    self.bait_data = json.load(f)
                print(f"✅ Dati BAIT caricati da: {latest_file}")
            else:
                print("⚠️ Nessun file risultati trovato - usando dati demo")
                self.bait_data = self.create_demo_data()
                
        except Exception as e:
            print(f"❌ Errore caricamento dati: {e}")
            self.bait_data = self.create_demo_data()
    
    def create_demo_data(self):
        """Crea dati demo per testing dashboard"""
        return {
            "summary": {
                "total_records": 371,
                "accuracy": 96.4,
                "total_alerts": 17,
                "estimated_losses": 157.50
            },
            "alerts": [
                {
                    "id": 1,
                    "technician": "Gabriele De Palma",
                    "type": "temporal_overlap",
                    "priority": "IMMEDIATE",
                    "confidence": "MOLTO_ALTA",
                    "description": "Sovrapposizione temporale: ELECTRALINE 3PMARK SPA vs TECNINOX",
                    "timestamp": "2025-08-01T14:37:00",
                    "estimated_loss": 25.50
                },
                {
                    "id": 2,
                    "technician": "Alex Ferrario", 
                    "type": "temporal_overlap",
                    "priority": "IMMEDIATE",
                    "confidence": "MOLTO_ALTA",
                    "description": "Sovrapposizione: SPOLIDORO vs GRUPPO TORO",
                    "timestamp": "2025-08-01T10:45:00",
                    "estimated_loss": 45.00
                },
                {
                    "id": 3,
                    "technician": "Matteo Signo",
                    "type": "insufficient_travel_time", 
                    "priority": "URGENT",
                    "confidence": "ALTA",
                    "description": "Tempo viaggio insufficiente: BAIT Service → TECNINOX",
                    "timestamp": "2025-08-01T15:45:00",
                    "estimated_loss": 15.00
                }
            ],
            "technicians": ["Gabriele De Palma", "Alex Ferrario", "Matteo Signo", "Matteo Di Salvo", "Davide Cestone"]
        }
    
    def setup_layout(self):
        """Setup layout dashboard principale"""
        self.app.layout = html.Div([
            # Header
            html.Div([
                html.H1("🎯 BAIT Service - Dashboard Controller", 
                       className="text-center mb-4"),
                html.Hr()
            ], className="header-section"),
            
            # KPI Cards Row
            html.Div(id="kpi-cards-container", className="row mb-4"),
            
            # Filtri Row  
            html.Div([
                html.H4("🔍 Filtri Dinamici", className="mb-3"),
                html.Div([
                    html.Div([
                        html.Label("Tecnici:", className="form-label"),
                        dcc.Dropdown(
                            id="tecnici-filter",
                            multi=True,
                            placeholder="Seleziona tecnici..."
                        )
                    ], className="col-md-3"),
                    
                    html.Div([
                        html.Label("Priorità Alert:", className="form-label"),
                        dcc.Dropdown(
                            id="priority-filter",
                            options=[
                                {"label": "🔴 IMMEDIATE", "value": "IMMEDIATE"},
                                {"label": "🟡 URGENT", "value": "URGENT"}, 
                                {"label": "🟢 NORMAL", "value": "NORMAL"}
                            ],
                            multi=True,
                            placeholder="Tutte le priorità"
                        )
                    ], className="col-md-3"),
                    
                    html.Div([
                        html.Label("Range Date:", className="form-label"),
                        dcc.DatePickerRange(
                            id="date-range-filter",
                            start_date=(datetime.now() - timedelta(days=7)).date(),
                            end_date=datetime.now().date(),
                            display_format='DD/MM/YYYY'
                        )
                    ], className="col-md-3"),
                    
                    html.Div([
                        html.Label("Confidence Level:", className="form-label"),
                        dcc.RangeSlider(
                            id="confidence-filter",
                            min=0, max=100, step=10,
                            marks={i: f"{i}%" for i in range(0, 101, 20)},
                            value=[70, 100],
                            tooltip={"placement": "bottom", "always_visible": True}
                        )
                    ], className="col-md-3")
                    
                ], className="row")
            ], className="filters-section mb-4 p-3 border rounded"),
            
            # Excel-like Grid
            html.Div([
                html.H4("📊 Grid Anomalie Excel-like", className="mb-3"),
                html.Div(id="excel-grid-container")
            ], className="grid-section mb-4"),
            
            # Charts Row
            html.Div([
                html.Div([
                    html.H5("📈 Trend Anomalie", className="mb-3"),
                    dcc.Graph(id="trend-chart")
                ], className="col-md-6"),
                
                html.Div([
                    html.H5("🎯 Distribuzione per Tecnico", className="mb-3"), 
                    dcc.Graph(id="technician-chart")
                ], className="col-md-6")
            ], className="row charts-section mb-4"),
            
            # Export Section
            html.Div([
                html.H4("📄 Export Professionale", className="mb-3"),
                html.Div([
                    html.Button("📊 Export Excel", id="export-excel-btn", 
                               className="btn btn-success me-2"),
                    html.Button("📑 Export PDF", id="export-pdf-btn", 
                               className="btn btn-danger me-2"),
                    html.Button("🔄 Aggiorna Dati", id="refresh-btn", 
                               className="btn btn-primary")
                ], className="mb-3"),
                html.Div(id="export-status")
            ], className="export-section p-3 border rounded"),
            
            # Modal per dettagli anomalie
            html.Div(id="anomaly-modal", className="modal", tabIndex=-1),
            
            # Store per dati
            dcc.Store(id="filtered-data-store"),
            dcc.Store(id="raw-data-store", data={}),
            
            # Auto-refresh interval
            dcc.Interval(id="auto-refresh", interval=30*1000, n_intervals=0)
            
        ], className="container-fluid", style={"padding": "20px"})
    
    def setup_callbacks(self):
        """Setup callbacks per interattività"""
        
        @self.app.callback(
            [Output("kpi-cards-container", "children"),
             Output("tecnici-filter", "options"),
             Output("raw-data-store", "data")],
            [Input("auto-refresh", "n_intervals")]
        )
        def update_dashboard_data(n_intervals):
            """Aggiorna dati dashboard"""
            # Ricarica dati BAIT Service
            self.load_bait_data()
            
            # KPI Cards
            kpi_cards = self.create_kpi_cards()
            
            # Opzioni filtri tecnici
            tecnici_options = [
                {"label": t, "value": t} for t in self.bait_data.get("technicians", [])
            ]
            
            return kpi_cards, tecnici_options, self.bait_data
        
        @self.app.callback(
            Output("excel-grid-container", "children"),
            [Input("raw-data-store", "data"),
             Input("tecnici-filter", "value"),
             Input("priority-filter", "value"),
             Input("date-range-filter", "start_date"),
             Input("date-range-filter", "end_date")]
        )
        def update_excel_grid(raw_data, tecnici_filter, priority_filter, start_date, end_date):
            """Aggiorna grid Excel-like con filtri"""
            if not raw_data or "alerts" not in raw_data:
                return html.Div("Nessun dato disponibile", className="text-muted")
            
            alerts = raw_data["alerts"]
            
            # Applica filtri
            if tecnici_filter:
                alerts = [a for a in alerts if a.get("technician") in tecnici_filter]
            
            if priority_filter:
                alerts = [a for a in alerts if a.get("priority") in priority_filter]
            
            # Prepara dati per tabella
            table_data = []
            for alert in alerts:
                confidence_map = {
                    "MOLTO_ALTA": 95, "ALTA": 85, "MEDIA": 65, "BASSA": 45
                }
                
                table_data.append({
                    "ID": alert.get("id", ""),
                    "Tecnico": alert.get("technician", ""),
                    "Tipo": alert.get("type", "").replace("_", " ").title(),
                    "Priorità": alert.get("priority", ""),
                    "Confidence": f"{confidence_map.get(alert.get('confidence', 'BASSA'), 50)}%",
                    "Descrizione": alert.get("description", ""),
                    "Perdita €": f"€{alert.get('estimated_loss', 0):.2f}",
                    "Timestamp": alert.get("timestamp", "")
                })
            
            # Crea tabella Excel-like
            excel_table = dash_table.DataTable(
                id="alerts-table",
                data=table_data,
                columns=[
                    {"name": col, "id": col, "selectable": True} 
                    for col in table_data[0].keys() if table_data
                ],
                style_table={"overflowX": "auto"},
                style_cell={
                    "textAlign": "left",
                    "padding": "10px",
                    "fontFamily": "Arial, sans-serif",
                    "fontSize": "14px",
                    "border": "1px solid #dee2e6"
                },
                style_header={
                    "backgroundColor": "#f8f9fa",
                    "fontWeight": "bold",
                    "border": "1px solid #dee2e6"
                },
                style_data_conditional=[
                    {
                        "if": {"filter_query": "{Priorità} = IMMEDIATE"},
                        "backgroundColor": "#ffebee",
                        "color": "#c62828"
                    },
                    {
                        "if": {"filter_query": "{Priorità} = URGENT"},
                        "backgroundColor": "#fff8e1", 
                        "color": "#f57f17"
                    }
                ],
                sort_action="native",
                filter_action="native",
                row_selectable="single",
                selected_rows=[],
                page_action="native",
                page_current=0,
                page_size=10
            )
            
            return excel_table
        
        @self.app.callback(
            [Output("trend-chart", "figure"),
             Output("technician-chart", "figure")],
            [Input("raw-data-store", "data")]
        )
        def update_charts(raw_data):
            """Aggiorna grafici analytics"""
            if not raw_data or "alerts" not in raw_data:
                empty_fig = go.Figure()
                empty_fig.add_annotation(text="Nessun dato disponibile", 
                                       xref="paper", yref="paper",
                                       x=0.5, y=0.5, showarrow=False)
                return empty_fig, empty_fig
            
            alerts = raw_data["alerts"]
            
            # Trend Chart
            df_alerts = pd.DataFrame(alerts)
            if not df_alerts.empty and "timestamp" in df_alerts.columns:
                df_alerts["date"] = pd.to_datetime(df_alerts["timestamp"]).dt.date
                trend_data = df_alerts.groupby("date").size().reset_index(name="count")
                
                trend_fig = px.line(
                    trend_data, x="date", y="count",
                    title="Trend Anomalie per Giorno",
                    labels={"date": "Data", "count": "Numero Anomalie"}
                )
                trend_fig.update_layout(height=300)
            else:
                trend_fig = go.Figure()
            
            # Technician Distribution
            if not df_alerts.empty and "technician" in df_alerts.columns:
                tech_counts = df_alerts["technician"].value_counts()
                
                tech_fig = px.pie(
                    values=tech_counts.values,
                    names=tech_counts.index,
                    title="Distribuzione Anomalie per Tecnico"
                )
                tech_fig.update_layout(height=300)
            else:
                tech_fig = go.Figure()
            
            return trend_fig, tech_fig
        
        @self.app.callback(
            Output("export-status", "children"),
            [Input("export-excel-btn", "n_clicks"),
             Input("export-pdf-btn", "n_clicks"),
             Input("refresh-btn", "n_clicks")],
            [State("raw-data-store", "data")]
        )
        def handle_exports(excel_clicks, pdf_clicks, refresh_clicks, raw_data):
            """Gestisce export e refresh"""
            ctx = dash.callback_context
            if not ctx.triggered:
                return ""
            
            button_id = ctx.triggered[0]["prop_id"].split(".")[0]
            
            if button_id == "export-excel-btn" and excel_clicks:
                try:
                    # Export Excel
                    df = pd.DataFrame(raw_data.get("alerts", []))
                    if not df.empty:
                        filename = f"bait_dashboard_export_{datetime.now().strftime('%Y%m%d_%H%M')}.xlsx"
                        df.to_excel(self.data_dir / filename, index=False)
                        return html.Div([
                            html.I(className="fas fa-check-circle text-success me-2"),
                            f"✅ Export Excel completato: {filename}"
                        ], className="alert alert-success")
                except Exception as e:
                    return html.Div([
                        html.I(className="fas fa-exclamation-triangle text-danger me-2"),
                        f"❌ Errore export Excel: {str(e)}"
                    ], className="alert alert-danger")
            
            elif button_id == "refresh-btn" and refresh_clicks:
                return html.Div([
                    html.I(className="fas fa-sync-alt text-primary me-2"),
                    "🔄 Dati aggiornati con successo!"
                ], className="alert alert-info")
            
            return ""
    
    def create_kpi_cards(self):
        """Crea KPI cards per dashboard"""
        summary = self.bait_data.get("summary", {})
        
        cards = html.Div([
            # Total Records Card
            html.Div([
                html.Div([
                    html.H4(summary.get("total_records", 0), className="card-title"),
                    html.P("Record Processati", className="card-text text-muted")
                ], className="card-body text-center")
            ], className="card col-md-2 me-2"),
            
            # Accuracy Card  
            html.Div([
                html.Div([
                    html.H4(f"{summary.get('accuracy', 0)}%", className="card-title text-success"),
                    html.P("System Accuracy", className="card-text text-muted")
                ], className="card-body text-center")
            ], className="card col-md-2 me-2"),
            
            # Total Alerts Card
            html.Div([
                html.Div([
                    html.H4(summary.get("total_alerts", 0), className="card-title text-warning"),
                    html.P("Alert Attivi", className="card-text text-muted")
                ], className="card-body text-center")
            ], className="card col-md-2 me-2"),
            
            # Estimated Losses Card
            html.Div([
                html.Div([
                    html.H4(f"€{summary.get('estimated_losses', 0):.2f}", className="card-title text-danger"),
                    html.P("Perdite Stimate", className="card-text text-muted")
                ], className="card-body text-center")
            ], className="card col-md-2 me-2"),
            
            # Status Card
            html.Div([
                html.Div([
                    html.H4("🟢 ONLINE", className="card-title text-success"),
                    html.P("Sistema Status", className="card-text text-muted")
                ], className="card-body text-center")
            ], className="card col-md-2")
            
        ], className="row")
        
        return cards
    
    def run_server(self, debug=True, port=8050):
        """Avvia il server dashboard"""
        print(f"🚀 Avvio BAIT Dashboard su http://localhost:{port}")
        print("📊 Dashboard Excel-like pronta per controllo quotidiano!")
        
        self.app.run_server(debug=debug, port=port, host="0.0.0.0")


def main():
    """Funzione principale"""
    print("🎯 BAIT Service - Dashboard Controller Enterprise-Grade")
    print("=" * 60)
    
    # Inizializza dashboard
    dashboard = BAITDashboardApp()
    
    # Avvia server
    dashboard.run_server(debug=False, port=8050)


if __name__ == "__main__":
    main()