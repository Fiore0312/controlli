#!/usr/bin/env python3
"""
BAIT SERVICE - ALERT GENERATOR & NOTIFICATION SYSTEM v3.0
TASK 18: Core Alert Generator per trasformazione anomalie in notifiche actionable

Caratteristiche:
- Trasformazione JSON anomalies in alert user-friendly
- Template specializzati per ogni tipologia anomalia
- Prioritizzazione intelligente IMMEDIATE/URGENT/NORMAL/INFO
- Messaggi business-friendly in italiano con action steps
- Integration con sistema email e dashboard feeds
"""

import json
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
from enum import Enum
import pandas as pd

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class NotificationPriority(Enum):
    IMMEDIATE = 1    # Invio immediato + SMS
    URGENT = 2       # Invio entro 1 ora + CC supervisore
    NORMAL = 3       # Invio giornaliero batch
    INFO = 4         # Weekly digest

class NotificationChannel(Enum):
    EMAIL = "email"
    SMS = "sms"
    DASHBOARD = "dashboard"
    SLACK = "slack"

@dataclass
class ActionableAlert:
    """Alert trasformato per sistema notifiche"""
    id: str
    original_alert_id: str
    priority: NotificationPriority
    channels: List[NotificationChannel]
    
    # Recipient targeting
    primary_recipient: str  # Tecnico interessato
    cc_recipients: List[str]  # Supervisori, management
    
    # Message content
    subject: str
    message_friendly: str
    technical_details: str
    correction_steps: List[str]
    
    # Business context
    business_impact: str
    urgency_reason: str
    estimated_loss: Optional[float]  # Euro stimati di perdita
    
    # Scheduling
    send_immediately: bool
    schedule_time: Optional[datetime]
    followup_required: bool
    followup_delay_hours: int
    
    # Tracking
    created_at: datetime
    category: str
    confidence_score: float
    data_sources: List[str]
    
    # Metadata
    metadata: Dict[str, Any]

class BaitAlertGenerator:
    """Core Alert Generator per BAIT Service"""
    
    def __init__(self):
        self.generated_alerts = []
        
        # Database tecnici e supervisori
        self.team_structure = {
            'Alex Ferrario': {
                'email': 'alex.ferrario@baitservice.com',
                'role': 'tecnico_senior',
                'supervisor': 'Franco Bait',
                'specialization': ['networking', 'sistemi']
            },
            'Gabriele De Palma': {
                'email': 'gabriele.depalma@baitservice.com', 
                'role': 'tecnico_senior',
                'supervisor': 'Franco Bait',
                'specialization': ['hardware', 'manutenzione']
            },
            'Matteo Signo': {
                'email': 'matteo.signo@baitservice.com',
                'role': 'tecnico',
                'supervisor': 'Alex Ferrario',
                'specialization': ['software', 'assistenza']
            },
            'Matteo Di Salvo': {
                'email': 'matteo.disalvo@baitservice.com',
                'role': 'tecnico',
                'supervisor': 'Gabriele De Palma',
                'specialization': ['installazioni', 'configurazioni']
            },
            'Davide Cestone': {
                'email': 'davide.cestone@baitservice.com',
                'role': 'tecnico',
                'supervisor': 'Alex Ferrario',
                'specialization': ['networking', 'sicurezza']
            }
        }
        
        self.supervisors = {
            'Franco Bait': 'franco.bait@baitservice.com',
            'Management': 'management@baitservice.com'
        }
        
        # Template messaggi per tipologia
        self.message_templates = {
            'temporal_overlap': {
                'subject': 'URGENTE: Sovrapposizione temporale cliente {cliente1} / {cliente2}',
                'message': 'Rilevata sovrapposizione di {overlap_minutes} minuti tra attività cliente {cliente1} e {cliente2} il {data}.',
                'urgency': 'Rischio doppia fatturazione',
                'actions': [
                    'Verificare immediatamente la correttezza degli orari dichiarati',
                    'Contattare i clienti per conferma orari effettivi',
                    'Correggere la fatturazione se necessario',
                    'Aggiornare planning per evitare future sovrapposizioni'
                ]
            },
            'insufficient_travel_time': {
                'subject': 'ATTENZIONE: Tempi viaggio insufficienti {cliente_partenza} → {cliente_arrivo}',
                'message': 'Rilevato tempo insufficiente ({tempo_disponibile} min) per spostamento da {cliente_partenza} a {cliente_arrivo}. Richiesti almeno {tempo_richiesto} min.',
                'urgency': 'Rischio ritardi e insoddisfazione cliente',
                'actions': [
                    'Verificare fattibilità orari pianificati',
                    'Considerare ottimizzazione percorso',
                    'Valutare rischeduling attività',
                    'Informare clienti di eventuali ritardi'
                ]
            },
            'missing_report': {
                'subject': 'MANCANTE: Rapportino attività del {data}',
                'message': 'Non risulta rapportino per il {data}. Attività rilevate: {attivita_count} interventi.',
                'urgency': 'Perdita tracciabilità fatturazione',
                'actions': [
                    'Compilare immediatamente rapportino mancante',
                    'Verificare completezza dati attività',
                    'Controllare timbrature corrispondenti',
                    'Informare supervisore se problemi tecnici'
                ]
            },
            'vehicle_inconsistency': {
                'subject': 'ANOMALIA: Utilizzo veicolo senza attività cliente corrispondente',
                'message': 'Rilevato utilizzo veicolo {veicolo} del {data} senza attività cliente registrata.',
                'urgency': 'Possibile uso non autorizzato',
                'actions': [
                    'Verificare destinazione e scopo utilizzo',
                    'Registrare attività cliente mancante',
                    'Documentare uso veicolo per attività interne',
                    'Contattare amministrazione per chiarimenti'
                ]
            }
        }
        
        # Configurazione priorità per confidence level
        self.priority_mapping = {
            'MOLTO_ALTA': NotificationPriority.IMMEDIATE,
            'ALTA': NotificationPriority.URGENT, 
            'MEDIA': NotificationPriority.NORMAL,
            'BASSA': NotificationPriority.INFO,
            'MOLTO_BASSA': NotificationPriority.INFO
        }
    
    def transform_business_rules_results(self, bait_results_file: str) -> List[ActionableAlert]:
        """Trasforma risultati Business Rules Engine in alert actionable"""
        logger.info("🔄 Avvio trasformazione alert Business Rules → Notifiche actionable...")
        
        try:
            # Carica risultati dal Business Rules Engine v2.0
            with open(bait_results_file, 'r', encoding='utf-8') as f:
                results_data = json.load(f)
            
            # Estrae alert processati
            raw_alerts = results_data.get('alerts_v2', {}).get('processed_alerts', {}).get('alerts', [])
            
            logger.info(f"📥 Caricati {len(raw_alerts)} alert dal Business Rules Engine")
            
            # Trasforma ogni alert
            actionable_alerts = []
            for raw_alert in raw_alerts:
                try:
                    actionable_alert = self._transform_single_alert(raw_alert)
                    if actionable_alert:
                        actionable_alerts.append(actionable_alert)
                except Exception as e:
                    logger.warning(f"⚠️ Errore trasformazione alert {raw_alert.get('id', 'unknown')}: {e}")
                    continue
            
            self.generated_alerts = actionable_alerts
            logger.info(f"✅ Generati {len(actionable_alerts)} alert actionable")
            
            return actionable_alerts
            
        except Exception as e:
            logger.error(f"❌ Errore trasformazione alert: {e}")
            return []
    
    def _transform_single_alert(self, raw_alert: Dict) -> Optional[ActionableAlert]:
        """Trasforma singolo alert da Business Rules in formato actionable"""
        
        # Estrae informazioni base
        alert_id = raw_alert.get('id', '')
        category = raw_alert.get('categoria', 'unknown')
        tecnico = raw_alert.get('tecnico', 'Unknown')
        confidence_level = raw_alert.get('confidence_level', 'MEDIA')
        confidence_score = raw_alert.get('confidence_score', 50)
        
        # Determina priorità basata su confidence
        priority = self.priority_mapping.get(confidence_level, NotificationPriority.NORMAL)
        
        # Estrae dettagli specifici
        details = raw_alert.get('dettagli', {})
        
        # Genera messaggi personalizzati per categoria
        if category == 'temporal_overlap':
            return self._create_temporal_overlap_alert(raw_alert, priority)
        elif category == 'insufficient_travel_time':
            return self._create_travel_time_alert(raw_alert, priority)
        elif category == 'activity_type_mismatch':
            return self._create_activity_type_alert(raw_alert, priority)
        else:
            # Alert generico
            return self._create_generic_alert(raw_alert, priority)
    
    def _create_temporal_overlap_alert(self, raw_alert: Dict, priority: NotificationPriority) -> ActionableAlert:
        """Crea alert specifico per sovrapposizione temporale"""
        
        details = raw_alert.get('dettagli', {})
        att1 = details.get('attivita_1', {})
        att2 = details.get('attivita_2', {})
        overlap_minutes = details.get('overlap_minutes', 0)
        
        cliente1 = att1.get('cliente', 'N/A')
        cliente2 = att2.get('cliente', 'N/A')
        data = att1.get('orario', '').split(' ')[0] if att1.get('orario') else 'N/A'
        
        tecnico = raw_alert.get('tecnico', 'Unknown')
        alert_id = raw_alert.get('id', 'UNKNOWN')
        
        # Stima perdita economica (€30/ora media)
        estimated_loss = (overlap_minutes / 60) * 30.0 if overlap_minutes > 0 else None
        
        # Genera messaggi
        template = self.message_templates['temporal_overlap']
        subject = template['subject'].format(cliente1=cliente1, cliente2=cliente2)
        
        message = f"""
ALERT CRITICO: {template['message'].format(
    overlap_minutes=int(overlap_minutes), 
    cliente1=cliente1, 
    cliente2=cliente2,
    data=data
)}

IMPATTO BUSINESS: {template['urgency']}
PERDITA STIMATA: €{estimated_loss:.2f} se non corretta

DETTAGLI TECNICI:
• Attività 1: {cliente1} - {att1.get('orario', 'N/A')} (ID: {att1.get('id', 'N/A')})
• Attività 2: {cliente2} - {att2.get('orario', 'N/A')} (ID: {att2.get('id', 'N/A')})
• Sovrapposizione: {overlap_minutes:.0f} minuti
• Confidence: {raw_alert.get('confidence_score', 0)}%
        """.strip()
        
        # Determina destinatari
        primary_email = self.team_structure.get(tecnico, {}).get('email', f'{tecnico.lower().replace(" ", ".")}@baitservice.com')
        supervisor = self.team_structure.get(tecnico, {}).get('supervisor', 'Franco Bait')
        cc_emails = [self.supervisors.get(supervisor, '')]
        
        # Per alert critici, aggiungi management
        if priority == NotificationPriority.IMMEDIATE:
            cc_emails.append(self.supervisors.get('Management', ''))
        
        return ActionableAlert(
            id=f"ACTIONABLE_{alert_id}",
            original_alert_id=alert_id,
            priority=priority,
            channels=[NotificationChannel.EMAIL, NotificationChannel.DASHBOARD],
            
            primary_recipient=primary_email,
            cc_recipients=list(filter(None, cc_emails)),
            
            subject=subject,
            message_friendly=message,
            technical_details=json.dumps(details, indent=2),
            correction_steps=template['actions'],
            
            business_impact=template['urgency'],
            urgency_reason=f"Sovrapposizione {overlap_minutes:.0f} min - Rischio doppia fatturazione",
            estimated_loss=estimated_loss,
            
            send_immediately=(priority == NotificationPriority.IMMEDIATE),
            schedule_time=None,
            followup_required=True,
            followup_delay_hours=4,
            
            created_at=datetime.now(),
            category=raw_alert.get('categoria', ''),
            confidence_score=raw_alert.get('confidence_score', 0),
            data_sources=raw_alert.get('data_sources', []),
            
            metadata={
                'cliente1': cliente1,
                'cliente2': cliente2,
                'overlap_minutes': overlap_minutes,
                'data_attivita': data,
                'tecnico_name': tecnico
            }
        )
    
    def _create_travel_time_alert(self, raw_alert: Dict, priority: NotificationPriority) -> ActionableAlert:
        """Crea alert specifico per tempi viaggio insufficienti"""
        
        details = raw_alert.get('dettagli', {})
        att_prev = details.get('attivita_precedente', {})
        att_next = details.get('attivita_successiva', {})
        
        cliente_partenza = att_prev.get('cliente', 'N/A')
        cliente_arrivo = att_next.get('cliente', 'N/A')
        tempo_disponibile = details.get('tempo_viaggio_minuti', 0)
        tempo_richiesto = details.get('tempo_richiesto_minuti', 0)
        
        tecnico = raw_alert.get('tecnico', 'Unknown')
        alert_id = raw_alert.get('id', 'UNKNOWN')
        
        # Genera messaggi
        template = self.message_templates['insufficient_travel_time']
        subject = template['subject'].format(
            cliente_partenza=cliente_partenza, 
            cliente_arrivo=cliente_arrivo
        )
        
        message = f"""
ALERT OPERATIVO: {template['message'].format(
    tempo_disponibile=int(abs(tempo_disponibile)),
    cliente_partenza=cliente_partenza,
    cliente_arrivo=cliente_arrivo,
    tempo_richiesto=int(tempo_richiesto)
)}

IMPATTO BUSINESS: {template['urgency']}

DETTAGLI TECNICI:
• Da: {cliente_partenza} - Fine: {att_prev.get('fine', 'N/A')} (ID: {att_prev.get('id', 'N/A')})
• A: {cliente_arrivo} - Inizio: {att_next.get('inizio', 'N/A')} (ID: {att_next.get('id', 'N/A')})
• Tempo disponibile: {tempo_disponibile:.0f} minuti
• Tempo richiesto: {tempo_richiesto:.0f} minuti  
• Distanza stimata: {details.get('distanza_stimata_km', 'N/A')} km
• Confidence: {raw_alert.get('confidence_score', 0)}%
        """.strip()
        
        # Destinatari (solo tecnico per alert operativi)
        primary_email = self.team_structure.get(tecnico, {}).get('email', f'{tecnico.lower().replace(" ", ".")}@baitservice.com')
        
        return ActionableAlert(
            id=f"ACTIONABLE_{alert_id}",
            original_alert_id=alert_id,
            priority=priority,
            channels=[NotificationChannel.EMAIL, NotificationChannel.DASHBOARD],
            
            primary_recipient=primary_email,
            cc_recipients=[],  # Solo tecnico per alert operativi
            
            subject=subject,
            message_friendly=message,
            technical_details=json.dumps(details, indent=2),
            correction_steps=template['actions'],
            
            business_impact=template['urgency'],
            urgency_reason=f"Tempo insufficiente per spostamento",
            estimated_loss=None,
            
            send_immediately=False,
            schedule_time=None,
            followup_required=False,
            followup_delay_hours=0,
            
            created_at=datetime.now(),
            category=raw_alert.get('categoria', ''),
            confidence_score=raw_alert.get('confidence_score', 0),
            data_sources=raw_alert.get('data_sources', []),
            
            metadata={
                'cliente_partenza': cliente_partenza,
                'cliente_arrivo': cliente_arrivo,
                'tempo_gap': tempo_richiesto - tempo_disponibile,
                'tecnico_name': tecnico
            }
        )
    
    def _create_activity_type_alert(self, raw_alert: Dict, priority: NotificationPriority) -> ActionableAlert:
        """Crea alert per discrepanza tipo attività"""
        
        details = raw_alert.get('dettagli', {})
        cliente = details.get('cliente', 'N/A')
        tipo_dichiarato = details.get('tipo_dichiarato', 'N/A')
        
        tecnico = raw_alert.get('tecnico', 'Unknown')
        
        subject = f"VERIFICA RICHIESTA: Tipo attività {tipo_dichiarato} - {cliente}"
        
        message = f"""
ALERT COMPLIANCE: Rilevata possibile discrepanza nel tipo di attività dichiarata.

Cliente: {cliente}
Tipo dichiarato: {tipo_dichiarato}
Orario: {details.get('orario', 'N/A')}

IMPATTO BUSINESS: Rischio compliance e audit
        """.strip()
        
        primary_email = self.team_structure.get(tecnico, {}).get('email', f'{tecnico.lower().replace(" ", ".")}@baitservice.com')
        
        return ActionableAlert(
            id=f"ACTIONABLE_{alert_id}",
            original_alert_id=alert_id,
            priority=priority,
            channels=[NotificationChannel.EMAIL, NotificationChannel.DASHBOARD],
            
            primary_recipient=primary_email,
            cc_recipients=[],
            
            subject=subject,
            message_friendly=message,
            technical_details=json.dumps(details, indent=2),
            correction_steps=[
                "Verificare tipo attività corretto",
                "Controllare sessioni TeamViewer",
                "Aggiornare dichiarazione se necessario"
            ],
            
            business_impact="Compliance aziendale",
            urgency_reason="Verifica tipo attività",
            estimated_loss=None,
            
            send_immediately=False,
            schedule_time=None,
            followup_required=True,
            followup_delay_hours=24,
            
            created_at=datetime.now(),
            category=raw_alert.get('categoria', ''),
            confidence_score=raw_alert.get('confidence_score', 0),
            data_sources=raw_alert.get('data_sources', []),
            
            metadata={
                'cliente': cliente,
                'tipo_attivita': tipo_dichiarato,
                'tecnico_name': tecnico
            }
        )
    
    def _create_generic_alert(self, raw_alert: Dict, priority: NotificationPriority) -> ActionableAlert:
        """Crea alert generico per categorie non specifiche"""
        
        tecnico = raw_alert.get('tecnico', 'Unknown')
        message = raw_alert.get('messaggio', 'Alert generico')
        
        subject = f"BAIT Alert: {message[:50]}..."
        
        primary_email = self.team_structure.get(tecnico, {}).get('email', f'{tecnico.lower().replace(" ", ".")}@baitservice.com')
        
        return ActionableAlert(
            id=f"ACTIONABLE_{alert_id}",
            original_alert_id=alert_id,
            priority=priority,
            channels=[NotificationChannel.EMAIL, NotificationChannel.DASHBOARD],
            
            primary_recipient=primary_email,
            cc_recipients=[],
            
            subject=subject,
            message_friendly=message,
            technical_details=json.dumps(raw_alert.get('dettagli', {}), indent=2),
            correction_steps=["Verificare dettagli alert", "Contattare supervisore se necessario"],
            
            business_impact=raw_alert.get('business_impact', 'Da valutare'),
            urgency_reason="Alert generico",
            estimated_loss=None,
            
            send_immediately=False,
            schedule_time=None,
            followup_required=False,
            followup_delay_hours=0,
            
            created_at=datetime.now(),
            category=raw_alert.get('categoria', 'generic'),
            confidence_score=raw_alert.get('confidence_score', 0),
            data_sources=raw_alert.get('data_sources', []),
            
            metadata={'tecnico_name': tecnico}
        )
    
    def get_alerts_by_priority(self, priority: NotificationPriority) -> List[ActionableAlert]:
        """Filtra alert per priorità"""
        return [alert for alert in self.generated_alerts if alert.priority == priority]
    
    def get_alerts_for_recipient(self, email: str) -> List[ActionableAlert]:
        """Filtra alert per destinatario"""
        return [alert for alert in self.generated_alerts 
                if alert.primary_recipient == email or email in alert.cc_recipients]
    
    def export_actionable_alerts(self, output_file: str) -> None:
        """Esporta alert actionable in formato JSON"""
        try:
            export_data = {
                'metadata': {
                    'generated_at': datetime.now().isoformat(),
                    'total_alerts': len(self.generated_alerts),
                    'by_priority': {
                        priority.name: len([a for a in self.generated_alerts if a.priority == priority])
                        for priority in NotificationPriority
                    }
                },
                'actionable_alerts': [asdict(alert) for alert in self.generated_alerts]
            }
            
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(export_data, f, indent=2, ensure_ascii=False, default=str)
            
            logger.info(f"✅ Esportati {len(self.generated_alerts)} alert actionable → {output_file}")
            
        except Exception as e:
            logger.error(f"❌ Errore export alert actionable: {e}")

def main():
    """Test del sistema Alert Generator"""
    logger.info("🚀 Testing BAIT Alert Generator v3.0...")
    
    # Inizializza generator
    generator = BaitAlertGenerator()
    
    # Trasforma risultati Business Rules (se file esiste)
    import os
    results_file = '/mnt/c/Users/Franco/Desktop/controlli/bait_results_v2_20250809_1347.json'
    
    if os.path.exists(results_file):
        actionable_alerts = generator.transform_business_rules_results(results_file)
        
        # Statistics
        logger.info(f"📊 RISULTATI:")
        logger.info(f"• Alert actionable generati: {len(actionable_alerts)}")
        logger.info(f"• IMMEDIATE: {len(generator.get_alerts_by_priority(NotificationPriority.IMMEDIATE))}")
        logger.info(f"• URGENT: {len(generator.get_alerts_by_priority(NotificationPriority.URGENT))}")
        logger.info(f"• NORMAL: {len(generator.get_alerts_by_priority(NotificationPriority.NORMAL))}")
        logger.info(f"• INFO: {len(generator.get_alerts_by_priority(NotificationPriority.INFO))}")
        
        # Export results
        timestamp = datetime.now().strftime("%Y%m%d_%H%M")
        output_file = f'/mnt/c/Users/Franco/Desktop/controlli/bait_actionable_alerts_{timestamp}.json'
        generator.export_actionable_alerts(output_file)
        
        logger.info("✅ Test Alert Generator completato!")
    else:
        logger.warning(f"⚠️ File risultati non trovato: {results_file}")

if __name__ == "__main__":
    main()