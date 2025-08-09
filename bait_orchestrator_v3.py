#!/usr/bin/env python3
"""
BAIT SERVICE - ORCHESTRATOR v3.0
Sistema completo orchestrazione Alert Generation & Notification System

Integra tutti i componenti della FASE 3:
- Alert Generator (trasformazione anomalie → notifiche actionable)  
- Email System (invio automatizzato email)
- Notification Workflows (gestione intelligente ciclo vita alert)
- Dashboard Feeds (API e dati real-time per management)

OBIETTIVO: Sistema produzione-ready per notifiche automatiche anomalie BAIT Service
"""

import json
import logging
import os
import time
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import threading

from alert_generator import BaitAlertGenerator, ActionableAlert
from email_system import EmailSystem, EmailConfig
from notification_workflows import NotificationWorkflowManager, AlertTracking
from dashboard_feeds import DashboardDataProvider, create_dashboard_feed_file

# Import condizionale per Dashboard API (richiede Flask)
try:
    from dashboard_feeds import DashboardAPI
    DASHBOARD_API_AVAILABLE = True
except ImportError:
    logger.warning("⚠️ Dashboard API non disponibile (Flask non installato)")
    DASHBOARD_API_AVAILABLE = False

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class BaitNotificationOrchestrator:
    """Orchestrator principale sistema notifiche BAIT"""
    
    def __init__(self, config: Dict = None):
        self.config = config or {}
        
        # Inizializza componenti core
        self.alert_generator = BaitAlertGenerator()
        
        # Configurazione email (modalità test di default)
        email_config = EmailConfig(
            smtp_server=self.config.get('smtp_server', 'smtp.gmail.com'),
            username=self.config.get('email_username', 'alerts@baitservice.com'),
            password=self.config.get('email_password', ''),  # Da configurare per produzione
            use_tls=self.config.get('email_tls', True)
        )
        self.email_system = EmailSystem(email_config)
        
        # Workflow manager per gestione ciclo vita alert
        self.workflow_manager = NotificationWorkflowManager(self.email_system)
        
        # Dashboard data provider
        self.dashboard_provider = DashboardDataProvider(self.workflow_manager)
        
        # Dashboard API (opzionale)
        self.dashboard_api = None
        if self.config.get('enable_api', False) and DASHBOARD_API_AVAILABLE:
            self.dashboard_api = DashboardAPI(self.dashboard_provider)
        elif self.config.get('enable_api', False):
            logger.warning("⚠️ Dashboard API richiesta ma non disponibile")
        
        # Statistiche esecuzione
        self.execution_stats = {
            'last_run': None,
            'total_runs': 0,
            'total_alerts_processed': 0,
            'total_emails_sent': 0,
            'avg_processing_time_seconds': 0,
            'errors_count': 0
        }
        
        # Modalità operativa
        self.test_mode = self.config.get('test_mode', True)
        self.auto_run = self.config.get('auto_run', False)
        self.run_interval_minutes = self.config.get('run_interval_minutes', 30)
        
    def process_business_rules_results(self, results_file: str) -> Dict:
        """
        Processo completo: Business Rules → Notifiche → Dashboard
        
        WORKFLOW:
        1. Carica risultati Business Rules Engine v2.0  
        2. Trasforma in alert actionable
        3. Processa workflow (grouping, scheduling)
        4. Invia email secondo priorità
        5. Aggiorna dashboard feeds
        6. Genera report esecuzione
        """
        
        start_time = time.time()
        logger.info("🚀 BAIT Notification Orchestrator v3.0 - Avvio processo completo")
        
        try:
            # FASE 1: Alert Generation
            logger.info("📋 FASE 1: Generazione alert actionable...")
            actionable_alerts = self.alert_generator.transform_business_rules_results(results_file)
            
            if not actionable_alerts:
                logger.warning("⚠️ Nessun alert actionable generato")
                return self._create_empty_result()
            
            logger.info(f"✅ Generati {len(actionable_alerts)} alert actionable")
            
            # FASE 2: Workflow Processing
            logger.info("⚙️ FASE 2: Processing workflow intelligente...")
            processed_alerts = self.workflow_manager.process_new_alerts(actionable_alerts)
            
            # FASE 3: Email Dispatch
            logger.info("📧 FASE 3: Dispatch email automatico...")
            
            # Invia alert immediati (IMMEDIATE priority)
            immediate_alerts = [
                t.alert for t in processed_alerts 
                if t.alert.send_immediately and not t.is_grouped
            ]
            
            if immediate_alerts:
                logger.info(f"🚨 Invio immediato {len(immediate_alerts)} alert critici...")
                email_results = self.email_system.send_batch_alerts(immediate_alerts, self.test_mode)
                self._update_sent_tracking(email_results, processed_alerts)
            
            # Gli alert raggruppati e schedulati verranno gestiti dal workflow manager in background
            
            # FASE 4: Dashboard Update
            logger.info("📊 FASE 4: Aggiornamento dashboard...")
            self._update_dashboard_feeds()
            
            # FASE 5: Report Generation
            execution_time = time.time() - start_time
            result = self._generate_execution_report(
                actionable_alerts, processed_alerts, execution_time
            )
            
            # Aggiorna statistiche
            self._update_execution_stats(len(actionable_alerts), execution_time)
            
            logger.info(f"✅ Processo completato in {execution_time:.2f}s")
            return result
            
        except Exception as e:
            logger.error(f"❌ Errore processo orchestrazione: {e}")
            self.execution_stats['errors_count'] += 1
            return {'success': False, 'error': str(e)}
    
    def _update_sent_tracking(self, email_results: List, processed_alerts: List[AlertTracking]):
        """Aggiorna tracking alert inviati"""
        
        # Crea mapping per aggiornare tracking
        sent_alert_ids = {r.alert_id for r in email_results if r.success}
        
        for tracking in processed_alerts:
            if tracking.alert.id in sent_alert_ids:
                tracking.sent_at = datetime.now()
                tracking.status = tracking.status.SENT if hasattr(tracking.status, 'SENT') else tracking.status
    
    def _update_dashboard_feeds(self):
        """Aggiorna dashboard feeds e dati"""
        try:
            # Forza refresh metriche
            metrics = self.dashboard_provider.get_current_metrics(force_refresh=True)
            
            # Genera feed file statico
            timestamp = datetime.now().strftime("%Y%m%d_%H%M")
            feed_file = f'/mnt/c/Users/Franco/Desktop/controlli/bait_dashboard_feed_{timestamp}.json'
            create_dashboard_feed_file(self.dashboard_provider, feed_file)
            
            logger.info(f"📄 Dashboard feed aggiornato: {metrics.total_alerts} alert totali")
            
        except Exception as e:
            logger.error(f"❌ Errore aggiornamento dashboard: {e}")
    
    def _generate_execution_report(self, actionable_alerts: List[ActionableAlert], 
                                 processed_alerts: List[AlertTracking], 
                                 execution_time: float) -> Dict:
        """Genera report esecuzione completo"""
        
        # Statistiche alert
        by_priority = {}
        by_category = {}
        immediate_sent = 0
        
        for alert in actionable_alerts:
            # Conta per priorità
            priority = alert.priority.name
            by_priority[priority] = by_priority.get(priority, 0) + 1
            
            # Conta per categoria
            category = alert.category
            by_category[category] = by_category.get(category, 0) + 1
            
            # Conta invii immediati
            if alert.send_immediately:
                immediate_sent += 1
        
        # Grouping stats
        grouped_count = len([t for t in processed_alerts if t.is_grouped])
        
        # Business impact
        total_estimated_loss = sum(
            a.estimated_loss or 0 for a in actionable_alerts
        )
        
        critical_count = len([
            a for a in actionable_alerts 
            if a.priority.name in ['IMMEDIATE', 'URGENT']
        ])
        
        report = {
            'success': True,
            'execution_metadata': {
                'timestamp': datetime.now().isoformat(),
                'execution_time_seconds': execution_time,
                'test_mode': self.test_mode
            },
            'alert_statistics': {
                'total_alerts_generated': len(actionable_alerts),
                'by_priority': by_priority,
                'by_category': by_category,
                'critical_alerts': critical_count,
                'immediate_sent': immediate_sent,
                'grouped_alerts': grouped_count
            },
            'business_impact': {
                'estimated_total_loss_euros': total_estimated_loss,
                'critical_alerts_requiring_attention': critical_count,
                'avg_confidence_score': sum(a.confidence_score for a in actionable_alerts) / len(actionable_alerts)
            },
            'system_performance': {
                'processing_time_seconds': execution_time,
                'alerts_per_second': len(actionable_alerts) / execution_time,
                'workflow_efficiency': 'GOOD' if execution_time < 60 else 'SLOW'
            },
            'next_steps': self._generate_next_steps(actionable_alerts)
        }
        
        return report
    
    def _generate_next_steps(self, alerts: List[ActionableAlert]) -> List[str]:
        """Genera next steps basati sui risultati"""
        steps = []
        
        critical_count = len([a for a in alerts if a.priority.name in ['IMMEDIATE', 'URGENT']])
        
        if critical_count > 0:
            steps.append(f"⚠️ ATTENZIONE: {critical_count} alert critici richiedono intervento immediato")
        
        if self.test_mode:
            steps.append("🧪 Sistema in modalità TEST - per produzione configurare SMTP e disabilitare test_mode")
        
        steps.append("📊 Monitora dashboard per stato correzioni alert")
        steps.append("📧 Verifica delivery email e response rate")
        
        return steps
    
    def _create_empty_result(self) -> Dict:
        """Crea risultato vuoto"""
        return {
            'success': True,
            'execution_metadata': {
                'timestamp': datetime.now().isoformat(),
                'test_mode': self.test_mode
            },
            'alert_statistics': {
                'total_alerts_generated': 0,
                'message': 'Nessun alert generato - sistema funzionante correttamente'
            }
        }
    
    def _update_execution_stats(self, alerts_count: int, execution_time: float):
        """Aggiorna statistiche esecuzione"""
        self.execution_stats['last_run'] = datetime.now()
        self.execution_stats['total_runs'] += 1
        self.execution_stats['total_alerts_processed'] += alerts_count
        
        # Calcola media mobile tempo esecuzione
        current_avg = self.execution_stats['avg_processing_time_seconds']
        total_runs = self.execution_stats['total_runs']
        
        new_avg = ((current_avg * (total_runs - 1)) + execution_time) / total_runs
        self.execution_stats['avg_processing_time_seconds'] = new_avg
    
    def start_workflow_manager(self):
        """Avvia workflow manager in background"""
        if not self.workflow_manager.is_running:
            self.workflow_manager.start_workflow_manager()
            logger.info("✅ Workflow manager avviato")
    
    def stop_workflow_manager(self):
        """Ferma workflow manager"""
        if self.workflow_manager.is_running:
            self.workflow_manager.stop_workflow_manager()
            logger.info("🛑 Workflow manager fermato")
    
    def start_dashboard_api(self, host: str = '0.0.0.0', port: int = 5000):
        """Avvia dashboard API server"""
        if self.dashboard_api:
            logger.info(f"🌐 Avvio Dashboard API su {host}:{port}...")
            api_thread = threading.Thread(
                target=self.dashboard_api.start_server,
                args=(host, port, False),  # Non debug mode
                daemon=True
            )
            api_thread.start()
        else:
            logger.warning("⚠️ Dashboard API non abilitata in configurazione")
    
    def run_continuous_mode(self):
        """Esegue sistema in modalità continua"""
        if not self.auto_run:
            logger.warning("⚠️ Auto-run non abilitato in configurazione")
            return
        
        logger.info(f"🔄 Avvio modalità continua - intervallo {self.run_interval_minutes} minuti")
        
        # Avvia workflow manager
        self.start_workflow_manager()
        
        # Loop principale
        try:
            while True:
                # Cerca file risultati Business Rules più recente
                results_pattern = '/mnt/c/Users/Franco/Desktop/controlli/bait_results_v2_*.json'
                import glob
                files = glob.glob(results_pattern)
                
                if files:
                    # Usa file più recente
                    latest_file = max(files, key=os.path.getmtime)
                    file_age_minutes = (time.time() - os.path.getmtime(latest_file)) / 60
                    
                    # Processa solo se file è sufficientemente recente
                    if file_age_minutes < self.run_interval_minutes * 2:
                        logger.info(f"📂 Processando {latest_file} (età: {file_age_minutes:.1f} min)")
                        self.process_business_rules_results(latest_file)
                    else:
                        logger.info(f"⏳ File risultati troppo vecchio ({file_age_minutes:.1f} min) - skip")
                else:
                    logger.info("📂 Nessun file risultati trovato - skip")
                
                # Attendi prossimo ciclo
                logger.info(f"⏰ Attesa prossimo ciclo in {self.run_interval_minutes} minuti...")
                time.sleep(self.run_interval_minutes * 60)
                
        except KeyboardInterrupt:
            logger.info("⌨️ Interruzione utente - stop modalità continua")
        finally:
            self.stop_workflow_manager()
    
    def get_system_status(self) -> Dict:
        """Ottieni stato sistema completo"""
        return {
            'orchestrator_status': 'running',
            'configuration': {
                'test_mode': self.test_mode,
                'auto_run': self.auto_run,
                'run_interval_minutes': self.run_interval_minutes,
                'dashboard_api_enabled': self.dashboard_api is not None
            },
            'execution_statistics': self.execution_stats,
            'workflow_manager_status': 'running' if self.workflow_manager.is_running else 'stopped',
            'workflow_statistics': self.workflow_manager.get_workflow_statistics() if self.workflow_manager.alert_tracking else {},
            'dashboard_metrics': self.dashboard_provider.get_current_metrics().__dict__ if self.dashboard_provider else {},
            'timestamp': datetime.now().isoformat()
        }
    
    def export_comprehensive_report(self, output_file: str):
        """Esporta report completo sistema"""
        try:
            comprehensive_data = {
                'system_status': self.get_system_status(),
                'recent_actionable_alerts': [
                    {
                        'id': alert.id,
                        'priority': alert.priority.name,
                        'subject': alert.subject,
                        'created_at': alert.created_at.isoformat()
                    }
                    for alert in self.alert_generator.generated_alerts[-10:]  # Ultimi 10
                ],
                'email_delivery_report': self.email_system.generate_delivery_report(),
                'workflow_data': self.workflow_manager.get_workflow_statistics(),
                'dashboard_summary': {
                    'metrics': self.dashboard_provider.get_current_metrics().__dict__,
                    'active_alerts': len(self.dashboard_provider.get_active_alerts()),
                    'recent_resolved': len(self.dashboard_provider.get_resolved_alerts(1))
                }
            }
            
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(comprehensive_data, f, indent=2, ensure_ascii=False, default=str)
            
            logger.info(f"📋 Report completo sistema esportato: {output_file}")
            
        except Exception as e:
            logger.error(f"❌ Errore export report completo: {e}")

def main():
    """Test orchestrator completo"""
    logger.info("🚀 Testing BAIT Notification Orchestrator v3.0...")
    
    # Configurazione test
    config = {
        'test_mode': True,
        'auto_run': False,
        'enable_api': False,  # Disabilitato per test
        'run_interval_minutes': 30
    }
    
    # Inizializza orchestrator
    orchestrator = BaitNotificationOrchestrator(config)
    
    # Test con file risultati esistente
    results_file = '/mnt/c/Users/Franco/Desktop/controlli/bait_results_v2_20250809_1347.json'
    
    if os.path.exists(results_file):
        # Avvia workflow manager
        orchestrator.start_workflow_manager()
        
        # Processa risultati
        result = orchestrator.process_business_rules_results(results_file)
        
        # Mostra risultati
        logger.info("📊 RISULTATI ORCHESTRAZIONE:")
        logger.info(f"• Alert generati: {result.get('alert_statistics', {}).get('total_alerts_generated', 0)}")
        logger.info(f"• Alert critici: {result.get('alert_statistics', {}).get('critical_alerts', 0)}")
        logger.info(f"• Tempo esecuzione: {result.get('execution_metadata', {}).get('execution_time_seconds', 0):.2f}s")
        
        # Export report completo
        timestamp = datetime.now().strftime("%Y%m%d_%H%M")
        report_file = f'/mnt/c/Users/Franco/Desktop/controlli/bait_orchestrator_report_{timestamp}.json'
        orchestrator.export_comprehensive_report(report_file)
        
        # Stato sistema
        status = orchestrator.get_system_status()
        logger.info(f"📈 Sistema: {status['execution_statistics']['total_alerts_processed']} alert processati totali")
        
        # Ferma workflow manager
        orchestrator.stop_workflow_manager()
        
        logger.info("✅ Test Orchestrator completato!")
    else:
        logger.warning(f"⚠️ File risultati non trovato: {results_file}")

if __name__ == "__main__":
    main()