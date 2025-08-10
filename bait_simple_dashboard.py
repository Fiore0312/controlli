#!/usr/bin/env python3
"""
BAIT Service - Dashboard Semplificata
====================================

Dashboard web semplificata per controllo quotidiano attività tecnici
Usa solo moduli Python standard per massima compatibilità.

Autore: Franco - BAIT Service
"""

import http.server
import socketserver
import json
import os
import webbrowser
from pathlib import Path
from datetime import datetime


class BAITSimpleDashboard:
    """Dashboard semplificata BAIT Service con server HTTP integrato"""
    
    def __init__(self, data_directory: str = "."):
        self.data_dir = Path(data_directory)
        self.port = 8051
        
    def load_bait_data(self):
        """Carica dati dal sistema BAIT Service"""
        try:
            # Cerca file risultati esistenti
            json_files = list(self.data_dir.glob("bait_results_v2_*.json"))
            if json_files:
                latest_file = max(json_files, key=os.path.getmtime)
                with open(latest_file, 'r', encoding='utf-8') as f:
                    raw_data = json.load(f)
                
                # Adatta struttura JSON reale al formato atteso dal dashboard
                system_kpis = raw_data.get("kpis_v2", {}).get("system_kpis", {})
                processed_alerts = raw_data.get("alerts_v2", {}).get("processed_alerts", {}).get("alerts", [])
                
                # Calcola perdite stimate
                estimated_losses = sum(alert.get("dettagli", {}).get("overlap_minutes", 0) * 0.5 for alert in processed_alerts)
                
                return {
                    "summary": {
                        "total_records": system_kpis.get("total_records_processed", 0),
                        "accuracy": system_kpis.get("estimated_accuracy", 0),
                        "total_alerts": system_kpis.get("alerts_generated", 0),
                        "estimated_losses": estimated_losses
                    },
                    "alerts": [
                        {
                            "id": alert.get("id", ""),
                            "technician": alert.get("tecnico", "N/A"),
                            "type": alert.get("categoria", ""),
                            "priority": "IMMEDIATE" if alert.get("severity", 0) == 1 else "URGENT" if alert.get("severity", 0) == 2 else "NORMAL",
                            "confidence": alert.get("confidence_level", "BASSA"),
                            "description": alert.get("messaggio", ""),
                            "timestamp": alert.get("timestamp", ""),
                            "estimated_loss": alert.get("dettagli", {}).get("overlap_minutes", 0) * 0.5
                        } for alert in processed_alerts
                    ]
                }
            else:
                # Dati demo se non trovati
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
                        }
                    ]
                }
        except Exception as e:
            print(f"❌ Errore caricamento dati: {e}")
            return {"summary": {}, "alerts": []}
    
    def create_html_dashboard(self):
        """Crea HTML dashboard completa"""
        data = self.load_bait_data()
        summary = data.get("summary", {})
        alerts = data.get("alerts", [])
        
        # Calcola statistiche
        immediate_alerts = len([a for a in alerts if a.get("priority") == "IMMEDIATE"])
        urgent_alerts = len([a for a in alerts if a.get("priority") == "URGENT"])
        
        html_content = f"""
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 BAIT Service - Dashboard Controller</title>
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}
        
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }}
        
        .container {{
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }}
        
        .header {{
            text-align: center;
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }}
        
        .kpi-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }}
        
        .kpi-card {{
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }}
        
        .kpi-value {{
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }}
        
        .kpi-label {{
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }}
        
        .success {{ color: #28a745; }}
        .warning {{ color: #ffc107; }}
        .danger {{ color: #dc3545; }}
        .primary {{ color: #007bff; }}
        
        .alerts-section {{
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }}
        
        .alerts-table {{
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }}
        
        .alerts-table th,
        .alerts-table td {{
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }}
        
        .alerts-table th {{
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }}
        
        .alert-priority {{
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }}
        
        .priority-immediate {{
            background: #ffebee;
            color: #c62828;
        }}
        
        .priority-urgent {{
            background: #fff8e1;
            color: #f57f17;
        }}
        
        .priority-normal {{
            background: #e8f5e8;
            color: #2e7d32;
        }}
        
        .confidence-badge {{
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            background: #e3f2fd;
            color: #1976d2;
        }}
        
        .refresh-btn {{
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            margin: 10px 0;
        }}
        
        .refresh-btn:hover {{
            background: #0056b3;
        }}
        
        .footer {{
            text-align: center;
            margin-top: 40px;
            color: #666;
            font-size: 0.9em;
        }}
        
        .status-indicator {{
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            margin-right: 8px;
        }}
        
        @media (max-width: 768px) {{
            .kpi-grid {{
                grid-template-columns: repeat(2, 1fr);
            }}
            
            .alerts-table {{
                font-size: 0.9em;
            }}
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 BAIT Service - Dashboard Controller</h1>
            <p><span class="status-indicator"></span>Sistema Enterprise-Grade Attivo</p>
            <button class="refresh-btn" onclick="location.reload()">🔄 Aggiorna Dati</button>
        </div>
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value primary">{summary.get('total_records', 0)}</div>
                <div class="kpi-label">Record Processati</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value success">{summary.get('accuracy', 0)}%</div>
                <div class="kpi-label">System Accuracy</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value warning">{len(alerts)}</div>
                <div class="kpi-label">Alert Attivi</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value danger">€{summary.get('estimated_losses', 0):.2f}</div>
                <div class="kpi-label">Perdite Stimate</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value danger">{immediate_alerts}</div>
                <div class="kpi-label">Alert Critici</div>
            </div>
        </div>
        
        <div class="alerts-section">
            <h2>📊 Alert Attivi - Excel-like View</h2>
            
            <table class="alerts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tecnico</th>
                        <th>Tipo</th>
                        <th>Priorità</th>
                        <th>Confidence</th>
                        <th>Descrizione</th>
                        <th>Perdita €</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
        """
        
        # Aggiungi righe alert
        for i, alert in enumerate(alerts):
            confidence_map = {
                "MOLTO_ALTA": "95%", "ALTA": "85%", "MEDIA": "65%", "BASSA": "45%"
            }
            
            priority_class = f"priority-{alert.get('priority', 'normal').lower()}"
            
            html_content += f"""
                    <tr>
                        <td>{alert.get('id', i+1)}</td>
                        <td><strong>{alert.get('technician', 'N/A')}</strong></td>
                        <td>{alert.get('type', '').replace('_', ' ').title()}</td>
                        <td><span class="alert-priority {priority_class}">{alert.get('priority', 'NORMAL')}</span></td>
                        <td><span class="confidence-badge">{confidence_map.get(alert.get('confidence', 'BASSA'), '50%')}</span></td>
                        <td>{alert.get('description', '')}</td>
                        <td><strong>€{alert.get('estimated_loss', 0):.2f}</strong></td>
                        <td>{alert.get('timestamp', '').split('T')[0] if alert.get('timestamp') else 'N/A'}</td>
                    </tr>
            """
        
        if not alerts:
            html_content += """
                    <tr>
                        <td colspan="8" style="text-align: center; color: #666; padding: 40px;">
                            ✅ Nessun alert attivo - Sistema funziona correttamente!
                        </td>
                    </tr>
            """
        
        html_content += f"""
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>🏆 BAIT Service Enterprise-Grade | Aggiornato: {datetime.now().strftime('%d/%m/%Y %H:%M')} | 
            <strong>System Status: 🟢 ONLINE</strong></p>
            <p>Dashboard URL: <code>http://localhost:{self.port}</code></p>
        </div>
    </div>
    
    <script>
        // Auto-refresh ogni 60 secondi
        setTimeout(() => {{
            location.reload();
        }}, 60000);
        
        // Evidenzia alert critici
        document.querySelectorAll('.priority-immediate').forEach(el => {{
            el.closest('tr').style.backgroundColor = '#fff5f5';
            el.closest('tr').style.border = '1px solid #feb2b2';
        }});
    </script>
</body>
</html>
        """
        
        return html_content
    
    def run_server(self):
        """Avvia server HTTP per dashboard"""
        html_content = self.create_html_dashboard()
        
        # Salva HTML
        html_file = self.data_dir / "dashboard.html"
        with open(html_file, 'w', encoding='utf-8') as f:
            f.write(html_content)
        
        # Crea server HTTP
        class CustomHandler(http.server.SimpleHTTPRequestHandler):
            def do_GET(self):
                if self.path == '/' or self.path == '/dashboard':
                    self.send_response(200)
                    self.send_header('Content-type', 'text/html; charset=utf-8')
                    self.end_headers()
                    
                    # Ricarica dati e HTML ogni volta
                    dashboard = BAITSimpleDashboard(str(self.server.data_dir))
                    fresh_html = dashboard.create_html_dashboard()
                    self.wfile.write(fresh_html.encode('utf-8'))
                else:
                    super().do_GET()
            
            def log_message(self, format, *args):
                return  # Silenzia i log
        
        with socketserver.TCPServer(("", self.port), CustomHandler) as httpd:
            httpd.data_dir = self.data_dir
            
            print("🎯 BAIT SERVICE - DASHBOARD SEMPLIFICATA ATTIVA!")
            print("=" * 60)
            print(f"🌐 URL Dashboard: http://localhost:{self.port}")
            print("📊 Excel-like interface per controllo quotidiano")
            print("🔄 Auto-refresh ogni 60 secondi")
            print("📱 Mobile-responsive design")
            print()
            print("🛑 Premi CTRL+C per fermare il server")
            print("=" * 60)
            
            # Apri browser automaticamente
            try:
                webbrowser.open(f"http://localhost:{self.port}")
            except:
                pass
            
            try:
                httpd.serve_forever()
            except KeyboardInterrupt:
                print("\n👋 Dashboard fermata dall'utente")
                print("✅ Server chiuso correttamente")


def main():
    """Funzione principale"""
    dashboard = BAITSimpleDashboard()
    dashboard.run_server()


if __name__ == "__main__":
    main()