#!/usr/bin/env python3
"""
BAIT SERVICE - NOTIFICATION WORKFLOWS SYSTEM v3.0
TASK 20: Sistema workflow management intelligente per alert e correzioni

Caratteristiche:
- Auto-escalation per anomalie non corrette
- Alert grouping per prevenire spam
- Tracking stato correzione
- Follow-up automatico
- Integration con calendar per reminder
- Feedback loop per continuous improvement
"""

import json
import logging
import time
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Set
from dataclasses import dataclass, field
from enum import Enum
import threading
from collections import defaultdict

from alert_generator import ActionableAlert, NotificationPriority
from email_system import EmailSystem, EmailConfig

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Import condizionale per scheduling
try:
    import schedule
    SCHEDULE_AVAILABLE = True
except ImportError:
    SCHEDULE_AVAILABLE = False
    logger.warning("⚠️ Scheduling automatico non disponibile (modulo schedule non installato)")

class AlertStatus(Enum):
    PENDING = "pending"           # Alert creato, non ancora inviato
    SENT = "sent"                 # Alert inviato
    ACKNOWLEDGED = "acknowledged"  # Alert ricevuto/letto
    IN_PROGRESS = "in_progress"   # Correzione in corso
    RESOLVED = "resolved"         # Problema risolto
    ESCALATED = "escalated"       # Escalated a supervisore
    CLOSED = "closed"             # Alert chiuso

class EscalationLevel(Enum):
    NONE = 0
    SUPERVISOR = 1    # Escalation a supervisore
    MANAGEMENT = 2    # Escalation a management
    CRITICAL = 3      # Escalation critica

@dataclass
class AlertTracking:
    """Tracking completo stato alert"""
    alert: ActionableAlert
    status: AlertStatus = AlertStatus.PENDING
    
    # Timing tracking
    created_at: datetime = field(default_factory=datetime.now)
    sent_at: Optional[datetime] = None
    acknowledged_at: Optional[datetime] = None
    resolved_at: Optional[datetime] = None
    
    # Escalation tracking  
    escalation_level: EscalationLevel = EscalationLevel.NONE
    escalated_at: Optional[datetime] = None
    escalation_reason: Optional[str] = None
    
    # Interaction tracking
    email_opens: int = 0
    email_clicks: int = 0
    followup_count: int = 0
    last_followup: Optional[datetime] = None
    
    # Resolution tracking
    resolution_notes: str = ""
    resolution_method: str = ""  # manual, automatic, escalation
    time_to_resolution_hours: Optional[float] = None
    
    # Grouping info
    group_id: Optional[str] = None
    is_grouped: bool = False
    group_size: int = 1

@dataclass 
class AlertGroup:
    """Gruppo alert simili per batch sending"""
    group_id: str
    alerts: List[AlertTracking]
    tecnico: str
    category: str
    created_at: datetime = field(default_factory=datetime.now)
    scheduled_send: Optional[datetime] = None
    sent_at: Optional[datetime] = None

class WorkflowRules:
    """Regole business per workflow"""
    
    # Timeframe per escalation (ore)
    ESCALATION_TIMEFRAMES = {
        NotificationPriority.IMMEDIATE: 2,    # 2 ore
        NotificationPriority.URGENT: 8,      # 8 ore  
        NotificationPriority.NORMAL: 24,     # 1 giorno
        NotificationPriority.INFO: 72        # 3 giorni
    }
    
    # Intervalli follow-up
    FOLLOWUP_INTERVALS = {
        NotificationPriority.IMMEDIATE: [1, 2, 4],     # 1h, 2h, 4h
        NotificationPriority.URGENT: [4, 12, 24],     # 4h, 12h, 24h
        NotificationPriority.NORMAL: [24, 72],        # 1g, 3g
        NotificationPriority.INFO: [168]               # 1 settimana
    }
    
    # Soglie per grouping alert
    GROUP_SAME_TECNICO_MINUTES = 30    # Alert stesso tecnico entro 30 min
    GROUP_SAME_CATEGORY_MINUTES = 60   # Alert stessa categoria entro 1h
    MAX_GROUP_SIZE = 5                 # Max 5 alert per gruppo

class NotificationWorkflowManager:
    """Manager principale workflow notifiche"""
    
    def __init__(self, email_system: EmailSystem = None):
        self.email_system = email_system or EmailSystem()
        
        # Storage in-memory (in produzione: database)
        self.alert_tracking: Dict[str, AlertTracking] = {}
        self.alert_groups: Dict[str, AlertGroup] = {}
        
        # Threading per background tasks
        self.background_thread = None
        self.is_running = False
        
        # Statistics
        self.stats = {
            'total_alerts': 0,
            'escalated_alerts': 0,
            'resolved_alerts': 0,
            'avg_resolution_time_hours': 0,
            'grouped_alerts': 0
        }
    
    def start_workflow_manager(self):
        """Avvia workflow manager in background"""
        if self.is_running:
            logger.warning("⚠️ Workflow manager già in esecuzione")
            return
        
        self.is_running = True
        logger.info("🚀 Avvio Notification Workflow Manager...")
        
        # Schedula task ricorrenti se disponibile
        if SCHEDULE_AVAILABLE:
            schedule.every(5).minutes.do(self._check_escalations)
            schedule.every(10).minutes.do(self._process_followups)
            schedule.every(30).minutes.do(self._send_grouped_alerts)
            schedule.every().hour.do(self._update_statistics)
        else:
            logger.warning("⚠️ Scheduling automatico disabilitato")
        
        # Avvia thread background
        self.background_thread = threading.Thread(target=self._background_worker, daemon=True)
        self.background_thread.start()
        
        logger.info("✅ Workflow manager avviato con successo")
    
    def stop_workflow_manager(self):
        """Ferma workflow manager"""
        self.is_running = False
        if self.background_thread:
            self.background_thread.join(timeout=5)
        logger.info("🛑 Workflow manager fermato")
    
    def _background_worker(self):
        """Worker background per task schedulati"""
        while self.is_running:
            try:
                if SCHEDULE_AVAILABLE:
                    schedule.run_pending()
                time.sleep(30)  # Check ogni 30 secondi
            except Exception as e:
                logger.error(f"❌ Errore background worker: {e}")
                time.sleep(60)
    
    def process_new_alerts(self, alerts: List[ActionableAlert]) -> List[AlertTracking]:
        """Processa nuovi alert dal generator"""
        logger.info(f"📥 Processando {len(alerts)} nuovi alert...")
        
        processed_alerts = []
        
        for alert in alerts:
            # Crea tracking
            tracking = AlertTracking(alert=alert)
            self.alert_tracking[alert.id] = tracking
            
            # Applica grouping logic
            group_id = self._apply_alert_grouping(tracking)
            if group_id:
                tracking.group_id = group_id
                tracking.is_grouped = True
            
            # Determina azione immediata
            if alert.send_immediately and not tracking.is_grouped:
                self._send_immediate_alert(tracking)
            
            processed_alerts.append(tracking)
            self.stats['total_alerts'] += 1
        
        logger.info(f"✅ Processati {len(processed_alerts)} alert")
        return processed_alerts
    
    def _apply_alert_grouping(self, new_tracking: AlertTracking) -> Optional[str]:
        """Applica logica grouping per prevenire spam"""
        alert = new_tracking.alert
        
        # Cerca alert simili recenti
        cutoff_time = datetime.now() - timedelta(minutes=WorkflowRules.GROUP_SAME_TECNICO_MINUTES)
        
        for tracking in self.alert_tracking.values():
            if tracking.created_at < cutoff_time:
                continue
            
            # Stesso tecnico + categoria simile
            if (tracking.alert.primary_recipient == alert.primary_recipient and
                tracking.alert.category == alert.category and
                tracking.status == AlertStatus.PENDING):
                
                # Trova o crea gruppo
                if tracking.group_id:
                    group = self.alert_groups[tracking.group_id]
                    if len(group.alerts) < WorkflowRules.MAX_GROUP_SIZE:
                        group.alerts.append(new_tracking)
                        logger.info(f"📦 Alert {alert.id} aggiunto a gruppo {tracking.group_id}")
                        return tracking.group_id
                else:
                    # Crea nuovo gruppo
                    group_id = f"GROUP_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{alert.primary_recipient.split('@')[0]}"
                    
                    group = AlertGroup(
                        group_id=group_id,
                        alerts=[tracking, new_tracking],
                        tecnico=alert.primary_recipient,
                        category=alert.category,
                        scheduled_send=datetime.now() + timedelta(minutes=30)
                    )
                    
                    self.alert_groups[group_id] = group
                    tracking.group_id = group_id
                    tracking.is_grouped = True
                    
                    logger.info(f"📦 Creato gruppo {group_id} con 2 alert")
                    self.stats['grouped_alerts'] += 2
                    
                    return group_id
        
        return None
    
    def _send_immediate_alert(self, tracking: AlertTracking):
        """Invia alert immediato"""
        try:
            result = self.email_system.send_alert_email(tracking.alert, test_mode=False)
            
            if result.success:
                tracking.status = AlertStatus.SENT
                tracking.sent_at = datetime.now()
                logger.info(f"✅ Alert immediato inviato: {tracking.alert.id}")
            else:
                logger.error(f"❌ Errore invio alert immediato {tracking.alert.id}: {result.error_message}")
                
        except Exception as e:
            logger.error(f"❌ Errore invio alert {tracking.alert.id}: {e}")
    
    def _send_grouped_alerts(self):
        """Invia alert raggruppati schedulati"""
        current_time = datetime.now()
        
        for group_id, group in list(self.alert_groups.items()):
            if group.sent_at or not group.scheduled_send:
                continue
            
            if current_time >= group.scheduled_send:
                try:
                    self._send_alert_group(group)
                except Exception as e:
                    logger.error(f"❌ Errore invio gruppo {group_id}: {e}")
    
    def _send_alert_group(self, group: AlertGroup):
        """Invia gruppo alert come digest"""
        logger.info(f"📨 Invio gruppo {group.group_id} con {len(group.alerts)} alert...")
        
        # Crea alert digest
        digest_alert = self._create_digest_alert(group)
        
        # Invia digest
        result = self.email_system.send_alert_email(digest_alert, test_mode=False)
        
        if result.success:
            group.sent_at = datetime.now()
            
            # Aggiorna tracking singoli alert
            for tracking in group.alerts:
                tracking.status = AlertStatus.SENT
                tracking.sent_at = group.sent_at
                tracking.group_size = len(group.alerts)
            
            logger.info(f"✅ Gruppo {group.group_id} inviato con successo")
        else:
            logger.error(f"❌ Errore invio gruppo {group.group_id}: {result.error_message}")
    
    def _create_digest_alert(self, group: AlertGroup) -> ActionableAlert:
        """Crea alert digest per gruppo"""
        
        # Combina informazioni alert
        categories = list(set(t.alert.category for t in group.alerts))
        max_priority = min(t.alert.priority for t in group.alerts)  # Min = più alta priorità
        
        # Crea messaggio digest
        subject = f"BAIT Alert Digest - {len(group.alerts)} notifiche per {group.tecnico.split('@')[0]}"
        
        message_parts = [
            f"Riepilogo {len(group.alerts)} alert per le tue attività:\n"
        ]
        
        for i, tracking in enumerate(group.alerts, 1):
            alert = tracking.alert
            message_parts.append(f"{i}. {alert.subject}")
            if alert.estimated_loss:
                message_parts.append(f"   💰 Perdita stimata: €{alert.estimated_loss:.2f}")
            message_parts.append("")
        
        message_parts.extend([
            "\nATTENZIONE: Alcuni alert richiedono azione immediata.",
            "Consulta i dettagli completi allegati o nel dashboard."
        ])
        
        # Crea alert digest usando primo alert come base
        first_alert = group.alerts[0].alert
        
        digest_alert = ActionableAlert(
            id=f"DIGEST_{group.group_id}",
            original_alert_id=group.group_id,
            priority=max_priority,
            channels=first_alert.channels,
            
            primary_recipient=first_alert.primary_recipient,
            cc_recipients=first_alert.cc_recipients,
            
            subject=subject,
            message_friendly="\n".join(message_parts),
            technical_details=f"Digest di {len(group.alerts)} alert",
            correction_steps=["Verificare singoli alert nel dashboard", "Prioritizzare azioni per alert critici"],
            
            business_impact="Multiple issues",
            urgency_reason=f"Digest {len(group.alerts)} alert",
            estimated_loss=sum(a.alert.estimated_loss or 0 for a in group.alerts),
            
            send_immediately=False,
            schedule_time=None,
            followup_required=True,
            followup_delay_hours=4,
            
            created_at=datetime.now(),
            category="digest",
            confidence_score=max(t.alert.confidence_score for t in group.alerts),
            data_sources=["digest"],
            
            metadata={
                'alert_count': len(group.alerts),
                'categories': categories,
                'tecnico_name': group.tecnico
            }
        )
        
        return digest_alert
    
    def _check_escalations(self):
        """Controlla alert che richiedono escalation"""
        current_time = datetime.now()
        escalated_count = 0
        
        for alert_id, tracking in self.alert_tracking.items():
            if tracking.status not in [AlertStatus.SENT, AlertStatus.ACKNOWLEDGED]:
                continue
            
            if tracking.escalation_level != EscalationLevel.NONE:
                continue  # Già escalated
                
            # Calcola tempo trascorso
            time_since_sent = current_time - (tracking.sent_at or tracking.created_at)
            hours_elapsed = time_since_sent.total_seconds() / 3600
            
            # Controlla se necessita escalation
            escalation_threshold = WorkflowRules.ESCALATION_TIMEFRAMES.get(
                tracking.alert.priority, 24
            )
            
            if hours_elapsed >= escalation_threshold:
                self._escalate_alert(tracking)
                escalated_count += 1
        
        if escalated_count > 0:
            logger.info(f"⬆️ Escalated {escalated_count} alert")
    
    def _escalate_alert(self, tracking: AlertTracking):
        """Escalate alert a supervisore/management"""
        try:
            # Determina livello escalation
            if tracking.alert.priority == NotificationPriority.IMMEDIATE:
                escalation_level = EscalationLevel.MANAGEMENT
            else:
                escalation_level = EscalationLevel.SUPERVISOR
            
            # Aggiorna tracking
            tracking.escalation_level = escalation_level
            tracking.escalated_at = datetime.now()
            tracking.status = AlertStatus.ESCALATED
            tracking.escalation_reason = "No response within timeframe"
            
            # Crea alert escalation
            escalation_alert = self._create_escalation_alert(tracking, escalation_level)
            
            # Invia escalation
            result = self.email_system.send_alert_email(escalation_alert, test_mode=False)
            
            if result.success:
                logger.info(f"⬆️ Alert {tracking.alert.id} escalated to {escalation_level.name}")
                self.stats['escalated_alerts'] += 1
            else:
                logger.error(f"❌ Errore escalation alert {tracking.alert.id}")
                
        except Exception as e:
            logger.error(f"❌ Errore escalation {tracking.alert.id}: {e}")
    
    def _create_escalation_alert(self, original_tracking: AlertTracking, level: EscalationLevel) -> ActionableAlert:
        """Crea alert per escalation"""
        
        original_alert = original_tracking.alert
        time_elapsed = datetime.now() - (original_tracking.sent_at or original_tracking.created_at)
        hours_elapsed = time_elapsed.total_seconds() / 3600
        
        if level == EscalationLevel.MANAGEMENT:
            recipient = "management@baitservice.com"
            subject = f"ESCALATION CRITICA: {original_alert.subject}"
        else:
            # TODO: Get supervisor email from team structure
            recipient = "supervisor@baitservice.com"  
            subject = f"ESCALATION: {original_alert.subject}"
        
        message = f"""
ESCALATION ALERT - NESSUNA RISPOSTA IN {hours_elapsed:.1f} ORE

Alert originale non risolto:
• ID: {original_alert.id}  
• Tecnico: {original_alert.primary_recipient}
• Priorità: {original_alert.priority.name}
• Creato: {original_tracking.created_at.strftime('%d/%m/%Y %H:%M')}
• Inviato: {original_tracking.sent_at.strftime('%d/%m/%Y %H:%M') if original_tracking.sent_at else 'N/A'}

MESSAGGIO ORIGINALE:
{original_alert.message_friendly}

AZIONE RICHIESTA: Contattare immediatamente il tecnico e verificare stato correzione.
        """.strip()
        
        return ActionableAlert(
            id=f"ESC_{original_alert.id}_{level.name}",
            original_alert_id=original_alert.id,
            priority=NotificationPriority.IMMEDIATE,
            channels=original_alert.channels,
            
            primary_recipient=recipient,
            cc_recipients=["management@baitservice.com"] if level != EscalationLevel.MANAGEMENT else [],
            
            subject=subject,
            message_friendly=message,
            technical_details=original_alert.technical_details,
            correction_steps=["Contattare tecnico immediato", "Verificare stato problema", "Implementare correzione"],
            
            business_impact="Escalation - No response",
            urgency_reason=f"No response in {hours_elapsed:.1f} hours",
            estimated_loss=original_alert.estimated_loss,
            
            send_immediately=True,
            schedule_time=None,
            followup_required=True,
            followup_delay_hours=2,
            
            created_at=datetime.now(),
            category="escalation",
            confidence_score=100,
            data_sources=["escalation"],
            
            metadata={
                'original_alert_id': original_alert.id,
                'escalation_level': level.name,
                'hours_since_original': hours_elapsed
            }
        )
    
    def _process_followups(self):
        """Processa follow-up alert se necessari"""
        current_time = datetime.now()
        followup_count = 0
        
        for alert_id, tracking in self.alert_tracking.items():
            if not tracking.alert.followup_required:
                continue
                
            if tracking.status not in [AlertStatus.SENT, AlertStatus.ACKNOWLEDGED]:
                continue
            
            # Calcola quando inviare prossimo follow-up
            intervals = WorkflowRules.FOLLOWUP_INTERVALS.get(tracking.alert.priority, [24])
            
            if tracking.followup_count >= len(intervals):
                continue  # Max followup raggiunti
            
            # Tempo per prossimo followup
            next_followup_hours = intervals[tracking.followup_count]
            next_followup_time = (tracking.sent_at or tracking.created_at) + timedelta(hours=next_followup_hours)
            
            if current_time >= next_followup_time:
                self._send_followup(tracking)
                followup_count += 1
        
        if followup_count > 0:
            logger.info(f"🔄 Inviati {followup_count} follow-up")
    
    def _send_followup(self, tracking: AlertTracking):
        """Invia follow-up per alert"""
        try:
            # Crea alert follow-up
            followup_alert = self._create_followup_alert(tracking)
            
            # Invia
            result = self.email_system.send_alert_email(followup_alert, test_mode=False)
            
            if result.success:
                tracking.followup_count += 1
                tracking.last_followup = datetime.now()
                logger.info(f"🔄 Follow-up {tracking.followup_count} inviato per {tracking.alert.id}")
            
        except Exception as e:
            logger.error(f"❌ Errore follow-up {tracking.alert.id}: {e}")
    
    def _create_followup_alert(self, original_tracking: AlertTracking) -> ActionableAlert:
        """Crea alert follow-up"""
        original_alert = original_tracking.alert
        followup_num = original_tracking.followup_count + 1
        
        subject = f"FOLLOW-UP #{followup_num}: {original_alert.subject}"
        
        message = f"""
FOLLOW-UP ALERT #{followup_num}

Il seguente alert richiede ancora la tua attenzione:

{original_alert.message_friendly}

STATO: Non ancora risolto dopo {followup_num} reminder
AZIONE RICHIESTA: Per favore conferma lo stato di correzione.
        """.strip()
        
        return ActionableAlert(
            id=f"FU_{original_alert.id}_{followup_num}",
            original_alert_id=original_alert.id,
            priority=original_alert.priority,
            channels=original_alert.channels,
            
            primary_recipient=original_alert.primary_recipient,
            cc_recipients=original_alert.cc_recipients,
            
            subject=subject,
            message_friendly=message,
            technical_details=original_alert.technical_details,
            correction_steps=original_alert.correction_steps,
            
            business_impact=original_alert.business_impact,
            urgency_reason=f"Follow-up #{followup_num}",
            estimated_loss=original_alert.estimated_loss,
            
            send_immediately=False,
            schedule_time=None,
            followup_required=False,
            followup_delay_hours=0,
            
            created_at=datetime.now(),
            category="followup",
            confidence_score=original_alert.confidence_score,
            data_sources=["followup"],
            
            metadata={
                'original_alert_id': original_alert.id,
                'followup_number': followup_num
            }
        )
    
    def _update_statistics(self):
        """Aggiorna statistiche workflow"""
        resolved = [t for t in self.alert_tracking.values() if t.status == AlertStatus.RESOLVED]
        
        if resolved:
            resolution_times = [t.time_to_resolution_hours for t in resolved if t.time_to_resolution_hours]
            if resolution_times:
                self.stats['avg_resolution_time_hours'] = sum(resolution_times) / len(resolution_times)
        
        self.stats['resolved_alerts'] = len(resolved)
        
        logger.info(f"📊 Stats update: {self.stats['resolved_alerts']}/{self.stats['total_alerts']} risolti")
    
    def mark_alert_resolved(self, alert_id: str, resolution_notes: str = "", method: str = "manual"):
        """Marca alert come risolto"""
        if alert_id not in self.alert_tracking:
            logger.warning(f"⚠️ Alert {alert_id} non trovato per risoluzione")
            return
        
        tracking = self.alert_tracking[alert_id]
        tracking.status = AlertStatus.RESOLVED
        tracking.resolved_at = datetime.now()
        tracking.resolution_notes = resolution_notes
        tracking.resolution_method = method
        
        # Calcola tempo risoluzione
        if tracking.sent_at:
            resolution_time = tracking.resolved_at - tracking.sent_at
            tracking.time_to_resolution_hours = resolution_time.total_seconds() / 3600
        
        logger.info(f"✅ Alert {alert_id} marcato come risolto")
    
    def get_workflow_statistics(self) -> Dict:
        """Ottieni statistiche workflow"""
        active_alerts = len([t for t in self.alert_tracking.values() 
                           if t.status not in [AlertStatus.RESOLVED, AlertStatus.CLOSED]])
        
        return {
            **self.stats,
            'active_alerts': active_alerts,
            'pending_escalations': len([t for t in self.alert_tracking.values() 
                                      if t.escalation_level != EscalationLevel.NONE])
        }
    
    def export_workflow_data(self, output_file: str):
        """Esporta dati workflow per analisi"""
        export_data = {
            'metadata': {
                'exported_at': datetime.now().isoformat(),
                'total_alerts_tracked': len(self.alert_tracking),
                'total_groups': len(self.alert_groups)
            },
            'statistics': self.get_workflow_statistics(),
            'alert_tracking': {
                alert_id: {
                    'status': tracking.status.value,
                    'created_at': tracking.created_at.isoformat(),
                    'sent_at': tracking.sent_at.isoformat() if tracking.sent_at else None,
                    'resolved_at': tracking.resolved_at.isoformat() if tracking.resolved_at else None,
                    'escalation_level': tracking.escalation_level.value,
                    'followup_count': tracking.followup_count,
                    'time_to_resolution_hours': tracking.time_to_resolution_hours
                }
                for alert_id, tracking in self.alert_tracking.items()
            }
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(export_data, f, indent=2, ensure_ascii=False, default=str)
        
        logger.info(f"📄 Dati workflow esportati: {output_file}")

def main():
    """Test sistema workflow"""
    logger.info("🚀 Testing BAIT Notification Workflows v3.0...")
    
    # Mock alert per test
    from alert_generator import ActionableAlert, NotificationChannel
    
    test_alerts = [
        ActionableAlert(
            id=f"TEST_WORKFLOW_{i}",
            original_alert_id=f"ORIG_{i}",
            priority=NotificationPriority.URGENT,
            channels=[NotificationChannel.EMAIL],
            primary_recipient=f"test{i}@baitservice.com",
            cc_recipients=[],
            subject=f"Test Alert {i}",
            message_friendly=f"Messaggio test {i}",
            technical_details="",
            correction_steps=["Test action"],
            business_impact="Test impact",
            urgency_reason="Test",
            estimated_loss=None,
            send_immediately=False,
            schedule_time=None,
            followup_required=True,
            followup_delay_hours=1,
            created_at=datetime.now(),
            category="test",
            confidence_score=80,
            data_sources=["test"],
            metadata={}
        )
        for i in range(3)
    ]
    
    # Test workflow manager
    workflow_manager = NotificationWorkflowManager()
    
    # Processa alert
    processed = workflow_manager.process_new_alerts(test_alerts)
    logger.info(f"✅ Processati {len(processed)} alert")
    
    # Statistiche
    stats = workflow_manager.get_workflow_statistics()
    logger.info(f"📊 Stats: {stats}")
    
    # Export test data
    timestamp = datetime.now().strftime("%Y%m%d_%H%M")
    export_file = f'/mnt/c/Users/Franco/Desktop/controlli/workflow_data_{timestamp}.json'
    workflow_manager.export_workflow_data(export_file)
    
    logger.info("✅ Test Notification Workflows completato!")

if __name__ == "__main__":
    main()