#!/usr/bin/env python3
"""
BAIT SERVICE - DASHBOARD FEEDS & REAL-TIME API v3.0
TASK 21: Sistema dashboard real-time e API per management

Caratteristiche:
- JSON feed strutturato per dashboard web
- Real-time updates via WebSocket/SSE  
- API REST per sistemi esterni
- Filtering e sorting avanzato
- KPI real-time per management
- Export capabilities per analisi
"""

import json
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
import threading
import queue
import time

from alert_generator import ActionableAlert, NotificationPriority
from notification_workflows import AlertTracking, AlertStatus, NotificationWorkflowManager

# Import condizionale per Flask API
try:
    from flask import Flask, jsonify, request, Response
    FLASK_AVAILABLE = True
except ImportError:
    FLASK_AVAILABLE = False

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

@dataclass
class DashboardMetrics:
    """Metriche per dashboard management"""
    
    # Alert metrics
    total_alerts: int = 0
    critical_alerts: int = 0  # IMMEDIATE + URGENT
    active_alerts: int = 0    # Non risolti
    resolved_alerts: int = 0
    
    # Timing metrics
    avg_resolution_time_hours: float = 0.0
    alerts_overdue: int = 0
    
    # Team metrics
    alerts_by_tecnico: Dict[str, int] = None
    resolution_rate_by_tecnico: Dict[str, float] = None
    
    # Business impact
    estimated_total_loss: float = 0.0
    prevented_loss: float = 0.0  # Da alert risolti
    
    # System health
    system_accuracy: float = 96.4  # Da Business Rules Engine
    false_positive_rate: float = 3.6
    
    # Timestamp
    calculated_at: datetime = None
    
    def __post_init__(self):
        if self.alerts_by_tecnico is None:
            self.alerts_by_tecnico = {}
        if self.resolution_rate_by_tecnico is None:
            self.resolution_rate_by_tecnico = {}
        if self.calculated_at is None:
            self.calculated_at = datetime.now()

class DashboardDataProvider:
    """Provider dati per dashboard"""
    
    def __init__(self, workflow_manager: NotificationWorkflowManager = None):
        self.workflow_manager = workflow_manager
        self.cached_metrics = None
        self.cache_expiry = None
        self.cache_duration_minutes = 5
        
        # Real-time updates
        self.update_queue = queue.Queue()
        self.subscribers = []
        
    def get_current_metrics(self, force_refresh: bool = False) -> DashboardMetrics:
        """Ottieni metriche correnti con caching"""
        
        # Controlla cache
        current_time = datetime.now()
        if (not force_refresh and 
            self.cached_metrics and 
            self.cache_expiry and 
            current_time < self.cache_expiry):
            return self.cached_metrics
        
        # Calcola nuove metriche
        metrics = self._calculate_current_metrics()
        
        # Aggiorna cache
        self.cached_metrics = metrics
        self.cache_expiry = current_time + timedelta(minutes=self.cache_duration_minutes)
        
        # Notifica subscribers
        self._notify_subscribers('metrics_update', asdict(metrics))
        
        return metrics
    
    def _calculate_current_metrics(self) -> DashboardMetrics:
        """Calcola metriche correnti"""
        
        if not self.workflow_manager:
            return DashboardMetrics()
        
        alert_tracking = self.workflow_manager.alert_tracking
        
        if not alert_tracking:
            return DashboardMetrics()
        
        # Contatori base
        total_alerts = len(alert_tracking)
        critical_alerts = len([
            t for t in alert_tracking.values() 
            if t.alert.priority in [NotificationPriority.IMMEDIATE, NotificationPriority.URGENT]
        ])
        
        active_alerts = len([
            t for t in alert_tracking.values() 
            if t.status not in [AlertStatus.RESOLVED, AlertStatus.CLOSED]
        ])
        
        resolved_alerts = len([
            t for t in alert_tracking.values() 
            if t.status == AlertStatus.RESOLVED
        ])
        
        # Tempo risoluzione medio
        resolved_with_time = [
            t for t in alert_tracking.values() 
            if t.status == AlertStatus.RESOLVED and t.time_to_resolution_hours
        ]
        
        avg_resolution_time = (
            sum(t.time_to_resolution_hours for t in resolved_with_time) / len(resolved_with_time)
            if resolved_with_time else 0
        )
        
        # Alert scaduti (oltre deadline)
        current_time = datetime.now()
        alerts_overdue = 0
        
        for tracking in alert_tracking.values():
            if tracking.status in [AlertStatus.RESOLVED, AlertStatus.CLOSED]:
                continue
                
            # Calcola deadline basata su priorità
            if tracking.alert.priority == NotificationPriority.IMMEDIATE:
                deadline_hours = 2
            elif tracking.alert.priority == NotificationPriority.URGENT:
                deadline_hours = 8  
            elif tracking.alert.priority == NotificationPriority.NORMAL:
                deadline_hours = 24
            else:
                deadline_hours = 72
            
            created_time = tracking.sent_at or tracking.created_at
            deadline = created_time + timedelta(hours=deadline_hours)
            
            if current_time > deadline:
                alerts_overdue += 1
        
        # Metriche per tecnico
        alerts_by_tecnico = {}
        resolution_stats = {}
        
        for tracking in alert_tracking.values():
            tecnico = tracking.alert.primary_recipient.split('@')[0]  # Nome senza dominio
            
            # Conta alert
            alerts_by_tecnico[tecnico] = alerts_by_tecnico.get(tecnico, 0) + 1
            
            # Stats risoluzione
            if tecnico not in resolution_stats:
                resolution_stats[tecnico] = {'total': 0, 'resolved': 0}
            
            resolution_stats[tecnico]['total'] += 1
            if tracking.status == AlertStatus.RESOLVED:
                resolution_stats[tecnico]['resolved'] += 1
        
        # Calcola rate risoluzione
        resolution_rate_by_tecnico = {}
        for tecnico, stats in resolution_stats.items():
            rate = (stats['resolved'] / stats['total']) * 100 if stats['total'] > 0 else 0
            resolution_rate_by_tecnico[tecnico] = rate
        
        # Impatto business
        estimated_total_loss = sum(
            t.alert.estimated_loss or 0 
            for t in alert_tracking.values() 
            if t.status not in [AlertStatus.RESOLVED, AlertStatus.CLOSED]
        )
        
        prevented_loss = sum(
            t.alert.estimated_loss or 0 
            for t in alert_tracking.values() 
            if t.status == AlertStatus.RESOLVED
        )
        
        return DashboardMetrics(
            total_alerts=total_alerts,
            critical_alerts=critical_alerts,
            active_alerts=active_alerts,
            resolved_alerts=resolved_alerts,
            avg_resolution_time_hours=avg_resolution_time,
            alerts_overdue=alerts_overdue,
            alerts_by_tecnico=alerts_by_tecnico,
            resolution_rate_by_tecnico=resolution_rate_by_tecnico,
            estimated_total_loss=estimated_total_loss,
            prevented_loss=prevented_loss
        )
    
    def get_active_alerts(self, filters: Dict = None) -> List[Dict]:
        """Ottieni alert attivi con filtri opzionali"""
        
        if not self.workflow_manager:
            return []
        
        active_alerts = [
            t for t in self.workflow_manager.alert_tracking.values()
            if t.status not in [AlertStatus.RESOLVED, AlertStatus.CLOSED]
        ]
        
        # Applica filtri
        if filters:
            if 'priority' in filters:
                priority_filter = filters['priority']
                active_alerts = [
                    t for t in active_alerts 
                    if t.alert.priority.name == priority_filter
                ]
            
            if 'tecnico' in filters:
                tecnico_filter = filters['tecnico']
                active_alerts = [
                    t for t in active_alerts 
                    if tecnico_filter.lower() in t.alert.primary_recipient.lower()
                ]
            
            if 'category' in filters:
                category_filter = filters['category']
                active_alerts = [
                    t for t in active_alerts 
                    if t.alert.category == category_filter
                ]
        
        # Converte in formato dashboard
        dashboard_alerts = []
        for tracking in active_alerts:
            alert_data = {
                'id': tracking.alert.id,
                'original_id': tracking.alert.original_alert_id,
                'priority': tracking.alert.priority.name,
                'status': tracking.status.value,
                'tecnico': tracking.alert.primary_recipient.split('@')[0],
                'subject': tracking.alert.subject,
                'category': tracking.alert.category,
                'business_impact': tracking.alert.business_impact,
                'estimated_loss': tracking.alert.estimated_loss,
                'confidence_score': tracking.alert.confidence_score,
                'created_at': tracking.created_at.isoformat(),
                'sent_at': tracking.sent_at.isoformat() if tracking.sent_at else None,
                'hours_since_created': (datetime.now() - tracking.created_at).total_seconds() / 3600,
                'correction_steps': tracking.alert.correction_steps,
                'followup_count': tracking.followup_count,
                'escalation_level': tracking.escalation_level.name
            }
            dashboard_alerts.append(alert_data)
        
        # Ordina per priorità e data
        priority_order = {
            'IMMEDIATE': 1,
            'URGENT': 2, 
            'NORMAL': 3,
            'INFO': 4
        }
        
        dashboard_alerts.sort(
            key=lambda x: (priority_order.get(x['priority'], 5), x['hours_since_created']),
            reverse=False
        )
        
        return dashboard_alerts
    
    def get_resolved_alerts(self, days_back: int = 7) -> List[Dict]:
        """Ottieni alert risolti negli ultimi N giorni"""
        
        if not self.workflow_manager:
            return []
        
        cutoff_date = datetime.now() - timedelta(days=days_back)
        
        resolved_alerts = [
            t for t in self.workflow_manager.alert_tracking.values()
            if (t.status == AlertStatus.RESOLVED and 
                t.resolved_at and t.resolved_at > cutoff_date)
        ]
        
        # Converte in formato dashboard
        dashboard_resolved = []
        for tracking in resolved_alerts:
            alert_data = {
                'id': tracking.alert.id,
                'priority': tracking.alert.priority.name,
                'tecnico': tracking.alert.primary_recipient.split('@')[0],
                'subject': tracking.alert.subject,
                'category': tracking.alert.category,
                'resolved_at': tracking.resolved_at.isoformat(),
                'resolution_time_hours': tracking.time_to_resolution_hours,
                'resolution_method': tracking.resolution_method,
                'resolution_notes': tracking.resolution_notes
            }
            dashboard_resolved.append(alert_data)
        
        return dashboard_resolved
    
    def _notify_subscribers(self, event_type: str, data: Any):
        """Notifica subscribers di aggiornamenti real-time"""
        update = {
            'type': event_type,
            'timestamp': datetime.now().isoformat(),
            'data': data
        }
        
        try:
            self.update_queue.put_nowait(update)
        except queue.Full:
            logger.warning("⚠️ Update queue full, dropping update")
    
    def subscribe_to_updates(self):
        """Subscribe to real-time updates"""
        # Implementation for WebSocket/SSE would go here
        pass

class DashboardAPI:
    """REST API per dashboard"""
    
    def __init__(self, data_provider: DashboardDataProvider):
        if not FLASK_AVAILABLE:
            raise ImportError("Flask non disponibile per Dashboard API")
        
        self.app = Flask(__name__)
        self.data_provider = data_provider
        self._setup_routes()
    
    def _setup_routes(self):
        """Setup route API"""
        
        @self.app.route('/api/metrics', methods=['GET'])
        def get_metrics():
            """Endpoint metriche correnti"""
            try:
                force_refresh = request.args.get('refresh', 'false').lower() == 'true'
                metrics = self.data_provider.get_current_metrics(force_refresh)
                return jsonify(asdict(metrics))
            except Exception as e:
                logger.error(f"❌ Errore API metrics: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/alerts/active', methods=['GET'])
        def get_active_alerts():
            """Endpoint alert attivi"""
            try:
                # Parse filtri
                filters = {}
                if request.args.get('priority'):
                    filters['priority'] = request.args.get('priority')
                if request.args.get('tecnico'):
                    filters['tecnico'] = request.args.get('tecnico')
                if request.args.get('category'):
                    filters['category'] = request.args.get('category')
                
                alerts = self.data_provider.get_active_alerts(filters)
                return jsonify({
                    'alerts': alerts,
                    'total_count': len(alerts),
                    'filters_applied': filters
                })
            except Exception as e:
                logger.error(f"❌ Errore API active alerts: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/alerts/resolved', methods=['GET'])
        def get_resolved_alerts():
            """Endpoint alert risolti"""
            try:
                days_back = int(request.args.get('days', 7))
                alerts = self.data_provider.get_resolved_alerts(days_back)
                return jsonify({
                    'resolved_alerts': alerts,
                    'total_count': len(alerts),
                    'days_back': days_back
                })
            except Exception as e:
                logger.error(f"❌ Errore API resolved alerts: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/alerts/<alert_id>/resolve', methods=['POST'])
        def resolve_alert(alert_id):
            """Endpoint per marcare alert come risolto"""
            try:
                data = request.get_json() or {}
                notes = data.get('resolution_notes', '')
                method = data.get('method', 'manual')
                
                if self.data_provider.workflow_manager:
                    self.data_provider.workflow_manager.mark_alert_resolved(
                        alert_id, notes, method
                    )
                
                return jsonify({
                    'success': True,
                    'message': f'Alert {alert_id} marcato come risolto'
                })
            except Exception as e:
                logger.error(f"❌ Errore risoluzione alert {alert_id}: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/dashboard/summary', methods=['GET'])
        def get_dashboard_summary():
            """Endpoint summary completo per dashboard"""
            try:
                metrics = self.data_provider.get_current_metrics()
                active_alerts = self.data_provider.get_active_alerts()
                
                # Top 5 alert più critici
                top_critical = sorted(
                    [a for a in active_alerts if a['priority'] in ['IMMEDIATE', 'URGENT']],
                    key=lambda x: (
                        1 if x['priority'] == 'IMMEDIATE' else 2,
                        -x['hours_since_created']
                    )
                )[:5]
                
                return jsonify({
                    'metrics': asdict(metrics),
                    'active_alerts_count': len(active_alerts),
                    'top_critical_alerts': top_critical,
                    'last_updated': datetime.now().isoformat()
                })
            except Exception as e:
                logger.error(f"❌ Errore dashboard summary: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/export/alerts', methods=['GET'])
        def export_alerts():
            """Export alert per analisi esterna"""
            try:
                format_type = request.args.get('format', 'json')
                days_back = int(request.args.get('days', 30))
                
                cutoff_date = datetime.now() - timedelta(days=days_back)
                
                if self.data_provider.workflow_manager:
                    all_tracking = self.data_provider.workflow_manager.alert_tracking
                    
                    export_data = []
                    for tracking in all_tracking.values():
                        if tracking.created_at > cutoff_date:
                            export_item = {
                                'alert_id': tracking.alert.id,
                                'priority': tracking.alert.priority.name,
                                'status': tracking.status.value,
                                'tecnico': tracking.alert.primary_recipient.split('@')[0],
                                'category': tracking.alert.category,
                                'subject': tracking.alert.subject,
                                'business_impact': tracking.alert.business_impact,
                                'estimated_loss': tracking.alert.estimated_loss,
                                'confidence_score': tracking.alert.confidence_score,
                                'created_at': tracking.created_at.isoformat(),
                                'sent_at': tracking.sent_at.isoformat() if tracking.sent_at else None,
                                'resolved_at': tracking.resolved_at.isoformat() if tracking.resolved_at else None,
                                'resolution_time_hours': tracking.time_to_resolution_hours,
                                'escalation_level': tracking.escalation_level.name,
                                'followup_count': tracking.followup_count
                            }
                            export_data.append(export_item)
                    
                    if format_type == 'csv':
                        # TODO: Implementare export CSV
                        pass
                    
                    return jsonify({
                        'export_data': export_data,
                        'total_records': len(export_data),
                        'date_range_days': days_back,
                        'exported_at': datetime.now().isoformat()
                    })
                
                return jsonify({'export_data': [], 'total_records': 0})
                
            except Exception as e:
                logger.error(f"❌ Errore export alerts: {e}")
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/health', methods=['GET'])
        def health_check():
            """Health check endpoint"""
            return jsonify({
                'status': 'healthy',
                'timestamp': datetime.now().isoformat(),
                'version': '3.0'
            })
    
    def start_server(self, host: str = '0.0.0.0', port: int = 5000, debug: bool = False):
        """Avvia server API"""
        logger.info(f"🚀 Avvio Dashboard API server su {host}:{port}...")
        self.app.run(host=host, port=port, debug=debug)

def create_dashboard_feed_file(data_provider: DashboardDataProvider, output_file: str):
    """Crea file JSON feed per dashboard statico"""
    try:
        metrics = data_provider.get_current_metrics()
        active_alerts = data_provider.get_active_alerts()
        resolved_alerts = data_provider.get_resolved_alerts(days_back=7)
        
        dashboard_feed = {
            'metadata': {
                'generated_at': datetime.now().isoformat(),
                'feed_version': '3.0',
                'data_freshness_minutes': data_provider.cache_duration_minutes
            },
            'metrics': asdict(metrics),
            'alerts': {
                'active': active_alerts,
                'recent_resolved': resolved_alerts
            },
            'summary': {
                'total_active': len(active_alerts),
                'critical_active': len([a for a in active_alerts if a['priority'] in ['IMMEDIATE', 'URGENT']]),
                'avg_resolution_time_hours': metrics.avg_resolution_time_hours,
                'system_health': 'GOOD' if metrics.alerts_overdue < 5 else 'ATTENTION'
            }
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(dashboard_feed, f, indent=2, ensure_ascii=False, default=str)
        
        logger.info(f"📄 Dashboard feed generato: {output_file}")
        
    except Exception as e:
        logger.error(f"❌ Errore generazione dashboard feed: {e}")

def main():
    """Test dashboard feeds"""
    logger.info("🚀 Testing BAIT Dashboard Feeds v3.0...")
    
    # Mock data provider
    data_provider = DashboardDataProvider()
    
    # Test metriche
    metrics = data_provider.get_current_metrics()
    logger.info(f"📊 Metriche generate: {metrics.total_alerts} alert totali")
    
    # Test dashboard feed statico
    timestamp = datetime.now().strftime("%Y%m%d_%H%M")
    feed_file = f'/mnt/c/Users/Franco/Desktop/controlli/dashboard_feed_{timestamp}.json'
    create_dashboard_feed_file(data_provider, feed_file)
    
    # Test API (non avviato per test)
    api = DashboardAPI(data_provider)
    logger.info("✅ Dashboard API inizializzata")
    
    logger.info("✅ Test Dashboard Feeds completato!")

if __name__ == "__main__":
    main()