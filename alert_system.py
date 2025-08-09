"""
BAIT Activity Controller - Alert System
Sistema di gestione e prioritizzazione alert per controllo attività tecnici
"""

from datetime import datetime
from typing import List, Dict, Any, Optional
from collections import defaultdict, Counter
import json

from models import Alert, AlertSeverity
from config import CONFIG, LOGGER

class AlertManager:
    """Gestione centralizzata alert sistema BAIT"""
    
    def __init__(self):
        self.alerts: List[Alert] = []
        self.alert_stats: Dict[str, Any] = {}
    
    def add_alerts(self, new_alerts: List[Alert]):
        """Aggiunge nuovi alert al sistema"""
        self.alerts.extend(new_alerts)
        LOGGER.info(f"Aggiunti {len(new_alerts)} alert al sistema")
    
    def get_alerts_by_severity(self, severity: AlertSeverity) -> List[Alert]:
        """Filtra alert per severity"""
        return [alert for alert in self.alerts if alert.severity == severity]
    
    def get_alerts_by_technician(self, tecnico: str) -> List[Alert]:
        """Filtra alert per tecnico"""
        return [alert for alert in self.alerts if alert.tecnico == tecnico]
    
    def get_alerts_by_category(self, categoria: str) -> List[Alert]:
        """Filtra alert per categoria"""
        return [alert for alert in self.alerts if alert.categoria == categoria]
    
    def calculate_alert_statistics(self) -> Dict[str, Any]:
        """Calcola statistiche aggregate degli alert"""
        if not self.alerts:
            return {
                'total_alerts': 0,
                'by_severity': {},
                'by_technician': {},
                'by_category': {},
                'critical_technicians': [],
                'most_common_issues': []
            }
        
        # Conteggio per severity
        severity_counts = Counter(alert.severity.name for alert in self.alerts)
        
        # Conteggio per tecnico
        technician_counts = Counter(alert.tecnico for alert in self.alerts)
        
        # Conteggio per categoria
        category_counts = Counter(alert.categoria for alert in self.alerts)
        
        # Tecnici con alert critici
        critical_technicians = list(set(
            alert.tecnico for alert in self.alerts 
            if alert.severity == AlertSeverity.CRITICO
        ))
        
        # Problemi più comuni
        most_common_issues = category_counts.most_common(5)
        
        stats = {
            'total_alerts': len(self.alerts),
            'by_severity': dict(severity_counts),
            'by_technician': dict(technician_counts),
            'by_category': dict(category_counts),
            'critical_technicians': critical_technicians,
            'most_common_issues': most_common_issues,
            'generation_time': datetime.now().isoformat()
        }
        
        self.alert_stats = stats
        return stats
    
    def get_priority_alerts(self, limit: int = 20) -> List[Alert]:
        """Restituisce alert prioritari ordinati per severity e timestamp"""
        # Ordina per severity (critico prima) poi per timestamp
        sorted_alerts = sorted(
            self.alerts,
            key=lambda a: (a.severity.value, a.timestamp),
            reverse=False  # Severity crescente (1=critico), timestamp crescente (recenti prima)
        )
        return sorted_alerts[:limit]
    
    def generate_alert_summary(self) -> str:
        """Genera summary testuale degli alert per management"""
        if not self.alerts:
            return "✅ Nessun alert rilevato - Tutte le attività sono conformi"
        
        stats = self.calculate_alert_statistics()
        
        summary_lines = [
            "🚨 BAIT ACTIVITY CONTROLLER - ALERT SUMMARY",
            "=" * 50,
            f"📊 ALERT TOTALI: {stats['total_alerts']}",
            ""
        ]
        
        # Breakdown per severity
        if stats['by_severity']:
            summary_lines.append("📈 BREAKDOWN PER GRAVITÀ:")
            for severity, count in sorted(stats['by_severity'].items(), 
                                        key=lambda x: getattr(AlertSeverity, x[0]).value):
                severity_icon = {
                    'CRITICO': '🔴',
                    'ALTO': '🟠',
                    'MEDIO': '🟡', 
                    'BASSO': '🟢'
                }.get(severity, '⚪')
                summary_lines.append(f"  {severity_icon} {severity}: {count}")
            summary_lines.append("")
        
        # Tecnici con più problemi
        if stats['by_technician']:
            top_technicians = sorted(stats['by_technician'].items(), 
                                   key=lambda x: x[1], reverse=True)[:5]
            summary_lines.append("👥 TECNICI CON PIÙ ALERT:")
            for tech, count in top_technicians:
                summary_lines.append(f"  • {tech}: {count} alert")
            summary_lines.append("")
        
        # Problemi più comuni
        if stats['most_common_issues']:
            summary_lines.append("🔍 PROBLEMI PIÙ COMUNI:")
            for issue, count in stats['most_common_issues']:
                issue_desc = {
                    'missing_remote_session': 'Attività remote senza TeamViewer',
                    'temporal_overlap': 'Sovrapposizioni temporali',
                    'missing_daily_report': 'Report giornalieri mancanti',
                    'vehicle_no_client': 'Veicoli senza cliente',
                    'remote_activity_with_vehicle': 'Attività remote con veicolo',
                    'schedule_discrepancy': 'Discrepanze orari calendario',
                    'activity_during_permit': 'Attività durante permessi'
                }.get(issue, issue)
                summary_lines.append(f"  • {issue_desc}: {count}")
            summary_lines.append("")
        
        # Alert prioritari
        priority_alerts = self.get_priority_alerts(10)
        if priority_alerts:
            summary_lines.append("⚠️  TOP 10 ALERT PRIORITARI:")
            for i, alert in enumerate(priority_alerts, 1):
                severity_icon = {
                    AlertSeverity.CRITICO: '🔴',
                    AlertSeverity.ALTO: '🟠', 
                    AlertSeverity.MEDIO: '🟡',
                    AlertSeverity.BASSO: '🟢'
                }.get(alert.severity, '⚪')
                
                timestamp_str = alert.timestamp.strftime('%H:%M')
                summary_lines.append(f"  {i:2d}. {severity_icon} [{timestamp_str}] {alert.messaggio}")
        
        summary_lines.extend([
            "",
            "=" * 50,
            f"🕐 Generato: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}",
            "🤖 BAIT Activity Controller v1.0"
        ])
        
        return "\n".join(summary_lines)
    
    def export_alerts_json(self) -> Dict[str, Any]:
        """Esporta alert in formato JSON per dashboard/API"""
        return {
            'metadata': {
                'total_alerts': len(self.alerts),
                'generation_time': datetime.now().isoformat(),
                'system_version': '1.0'
            },
            'statistics': self.calculate_alert_statistics(),
            'alerts': [alert.to_dict() for alert in self.alerts],
            'priority_alerts': [alert.to_dict() for alert in self.get_priority_alerts()]
        }
    
    def generate_technician_report(self, tecnico: str) -> Dict[str, Any]:
        """Genera report dettagliato per singolo tecnico"""
        tech_alerts = self.get_alerts_by_technician(tecnico)
        
        if not tech_alerts:
            return {
                'tecnico': tecnico,
                'total_alerts': 0,
                'status': 'OK',
                'message': f'✅ {tecnico}: Nessun alert rilevato'
            }
        
        # Statistiche tecnico
        severity_counts = Counter(alert.severity.name for alert in tech_alerts)
        category_counts = Counter(alert.categoria for alert in tech_alerts)
        
        # Determina status generale
        has_critical = any(alert.severity == AlertSeverity.CRITICO for alert in tech_alerts)
        has_high = any(alert.severity == AlertSeverity.ALTO for alert in tech_alerts)
        
        if has_critical:
            status = 'CRITICAL'
            status_icon = '🔴'
        elif has_high:
            status = 'HIGH'
            status_icon = '🟠'
        elif len(tech_alerts) > 5:
            status = 'MEDIUM'
            status_icon = '🟡'
        else:
            status = 'LOW'
            status_icon = '🟢'
        
        return {
            'tecnico': tecnico,
            'total_alerts': len(tech_alerts),
            'status': status,
            'status_icon': status_icon,
            'by_severity': dict(severity_counts),
            'by_category': dict(category_counts),
            'alerts': [alert.to_dict() for alert in tech_alerts],
            'message': f'{status_icon} {tecnico}: {len(tech_alerts)} alert rilevati ({status})'
        }
    
    def clear_alerts(self):
        """Pulisce tutti gli alert dal sistema"""
        self.alerts.clear()
        self.alert_stats.clear()
        LOGGER.info("Alert system pulito")

class AlertFormatter:
    """Formatter per output alert in diversi formati"""
    
    @staticmethod
    def format_console_output(alerts: List[Alert]) -> str:
        """Formatta alert per output console"""
        if not alerts:
            return "✅ Nessun alert da mostrare"
        
        lines = []
        for alert in alerts:
            severity_icon = {
                AlertSeverity.CRITICO: '🔴 CRITICO',
                AlertSeverity.ALTO: '🟠 ALTO',
                AlertSeverity.MEDIO: '🟡 MEDIO',  
                AlertSeverity.BASSO: '🟢 BASSO'
            }.get(alert.severity, '⚪ UNKNOWN')
            
            timestamp = alert.timestamp.strftime('%H:%M:%S')
            lines.append(f"[{timestamp}] {severity_icon} - {alert.messaggio}")
            
            if alert.dettagli:
                for key, value in alert.dettagli.items():
                    if key not in ['timestamp', 'id']:
                        lines.append(f"    └─ {key}: {value}")
        
        return "\n".join(lines)
    
    @staticmethod
    def format_html_report(alert_manager: AlertManager) -> str:
        """Genera report HTML per visualizzazione web"""
        stats = alert_manager.calculate_alert_statistics()
        
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <title>BAIT Activity Controller - Alert Report</title>
            <meta charset="utf-8">
            <style>
                body {{ font-family: Arial, sans-serif; margin: 20px; }}
                .header {{ background: #f4f4f4; padding: 15px; border-radius: 5px; }}
                .alert-critical {{ background: #ffebee; border-left: 5px solid #f44336; padding: 10px; margin: 5px 0; }}
                .alert-high {{ background: #fff3e0; border-left: 5px solid #ff9800; padding: 10px; margin: 5px 0; }}
                .alert-medium {{ background: #fffde7; border-left: 5px solid #ffeb3b; padding: 10px; margin: 5px 0; }}
                .alert-low {{ background: #e8f5e8; border-left: 5px solid #4caf50; padding: 10px; margin: 5px 0; }}
                .stats {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }}
                .stat-card {{ background: white; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }}
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🤖 BAIT Activity Controller</h1>
                <h2>Alert Report - {datetime.now().strftime('%d/%m/%Y %H:%M')}</h2>
                <p><strong>Alert Totali:</strong> {stats['total_alerts']}</p>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>📈 Per Gravità</h3>
                    <ul>
        """
        
        for severity, count in stats.get('by_severity', {}).items():
            html += f"<li>{severity}: {count}</li>"
        
        html += """
                    </ul>
                </div>
                <div class="stat-card">
                    <h3>👥 Per Tecnico</h3>
                    <ul>
        """
        
        for tech, count in list(stats.get('by_technician', {}).items())[:10]:
            html += f"<li>{tech}: {count}</li>"
        
        html += """
                    </ul>
                </div>
            </div>
            
            <h3>⚠️ Alert Dettagliati</h3>
        """
        
        for alert in alert_manager.get_priority_alerts(50):
            css_class = {
                AlertSeverity.CRITICO: 'alert-critical',
                AlertSeverity.ALTO: 'alert-high',
                AlertSeverity.MEDIO: 'alert-medium',
                AlertSeverity.BASSO: 'alert-low'
            }.get(alert.severity, 'alert-low')
            
            html += f"""
            <div class="{css_class}">
                <strong>{alert.severity.name}</strong> - {alert.messaggio}
                <br><small>Tecnico: {alert.tecnico} | {alert.timestamp.strftime('%H:%M:%S')}</small>
            </div>
            """
        
        html += """
        </body>
        </html>
        """
        
        return html

if __name__ == "__main__":
    # Test del sistema di alert
    manager = AlertManager()
    print("Alert System implementato con successo!")
    print("Funzionalità disponibili:")
    print("- Gestione centralizzata alert")
    print("- Prioritizzazione automatica")
    print("- Report testuali e JSON")
    print("- Statistiche aggregate")
    print("- Export HTML per dashboard")