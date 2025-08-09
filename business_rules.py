"""
BAIT Activity Controller - Business Rules Engine
Sistema di validazione regole business per controllo attività tecnici
"""

from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional, Tuple
from dataclasses import dataclass
import pandas as pd
from collections import defaultdict

from models import (
    AttivitaTecnico, TimbraturaTecnico, SessioneTeamViewer, 
    PermessoTecnico, UtilizzoVeicolo, AppuntamentoCalendario,
    Alert, AlertSeverity, TipologiaAttivita, StatoPermesso
)
from config import CONFIG, LOGGER

@dataclass
class ValidationResult:
    """Risultato validazione regola business"""
    rule_id: str
    rule_name: str
    alerts: List[Alert]
    stats: Dict[str, Any]
    execution_time_ms: int

class BusinessRulesEngine:
    """Engine per validazione regole business BAIT"""
    
    def __init__(self):
        self.alert_counter = 0
    
    def generate_alert_id(self) -> str:
        """Genera ID univoco per alert"""
        self.alert_counter += 1
        return f"BAIT_{datetime.now().strftime('%Y%m%d')}_{self.alert_counter:04d}"
    
    def create_alert(self, severity: AlertSeverity, tecnico: str, messaggio: str, 
                    categoria: str, dettagli: Dict[str, Any] = None) -> Alert:
        """Factory per creazione alert"""
        return Alert(
            id_alert=self.generate_alert_id(),
            severity=severity,
            tecnico=tecnico,
            messaggio=messaggio,
            timestamp=datetime.now(),
            dettagli=dettagli or {},
            categoria=categoria
        )
    
    # REGOLA 1: Validazione Tipo Attività vs Sessioni TeamViewer
    def validate_activity_type_vs_teamviewer(self, 
                                           attivita: List[AttivitaTecnico],
                                           sessioni_bait: List[SessioneTeamViewer],
                                           sessioni_gruppo: List[SessioneTeamViewer]) -> ValidationResult:
        """
        Regola 1: Attività Remote devono avere sessioni TeamViewer corrispondenti
        Attività On-Site NON dovrebbero avere sessioni remote eccessive
        """
        start_time = datetime.now()
        alerts = []
        stats = {'remote_activities': 0, 'onsite_activities': 0, 'missing_teamviewer': 0, 'excess_teamviewer': 0}
        
        # Combina sessioni TeamViewer
        all_sessioni = sessioni_bait + sessioni_gruppo
        
        # Raggruppa sessioni per tecnico e data
        sessioni_per_tecnico = defaultdict(list)
        for sessione in all_sessioni:
            if sessione.tecnico and sessione.data_sessione:
                key = (sessione.tecnico, sessione.data_sessione.date())
                sessioni_per_tecnico[key].append(sessione)
        
        for attivita_item in attivita:
            if not attivita_item.tecnico or not attivita_item.iniziata_il:
                continue
                
            tecnico = attivita_item.tecnico
            data_attivita = attivita_item.iniziata_il.date()
            
            if attivita_item.tipologia == TipologiaAttivita.REMOTO:
                stats['remote_activities'] += 1
                
                # Cerca sessioni TeamViewer corrispondenti
                key = (tecnico, data_attivita)
                sessioni_giorno = sessioni_per_tecnico.get(key, [])
                
                # Filtra sessioni nell'intervallo attività (±30 min)
                sessioni_correlate = []
                if attivita_item.iniziata_il and attivita_item.conclusa_il:
                    start_window = attivita_item.iniziata_il - timedelta(minutes=30)
                    end_window = attivita_item.conclusa_il + timedelta(minutes=30)
                    
                    for sessione in sessioni_giorno:
                        if sessione.data_sessione:
                            if start_window <= sessione.data_sessione <= end_window:
                                sessioni_correlate.append(sessione)
                
                # Verifica durata minima sessioni
                durata_totale = sum(s.durata_minuti for s in sessioni_correlate if s.durata_minuti)
                
                if not sessioni_correlate or durata_totale < CONFIG.MIN_TEAMVIEWER_SESSION_MINUTES:
                    stats['missing_teamviewer'] += 1
                    
                    alert = self.create_alert(
                        AlertSeverity.ALTO,
                        tecnico,
                        CONFIG.ALERT_TEMPLATES['no_teamviewer'].format(
                            tecnico=tecnico,
                            cliente=attivita_item.azienda or 'Unknown'
                        ),
                        'missing_remote_session',
                        {
                            'attivita_id': attivita_item.id_ticket,
                            'cliente': attivita_item.azienda,
                            'orario_attivita': f"{attivita_item.iniziata_il} - {attivita_item.conclusa_il}",
                            'sessioni_trovate': len(sessioni_correlate),
                            'durata_totale_minuti': durata_totale
                        }
                    )
                    alerts.append(alert)
            
            elif attivita_item.tipologia == TipologiaAttivita.ONSITE:
                stats['onsite_activities'] += 1
                # Per ora non implementiamo controllo eccesso TeamViewer per On-Site
                # Può essere aggiunto se richiesto dal business
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR001",
            rule_name="Validazione Tipo Attività vs TeamViewer",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    # REGOLA 2: Rilevamento Sovrapposizioni Temporali
    def detect_temporal_overlaps(self, attivita: List[AttivitaTecnico]) -> ValidationResult:
        """
        Regola 2: Rileva attività sovrapposte dello stesso tecnico con clienti diversi
        """
        start_time = datetime.now()
        alerts = []
        stats = {'total_activities': len(attivita), 'overlaps_detected': 0, 'technicians_checked': 0}
        
        # Raggruppa attività per tecnico
        attivita_per_tecnico = defaultdict(list)
        for att in attivita:
            if att.tecnico and att.iniziata_il and att.conclusa_il:
                attivita_per_tecnico[att.tecnico].append(att)
        
        stats['technicians_checked'] = len(attivita_per_tecnico)
        
        for tecnico, attivita_tecnico in attivita_per_tecnico.items():
            # Ordina attività per orario inizio
            attivita_tecnico.sort(key=lambda x: x.iniziata_il)
            
            # Confronta ogni attività con le successive
            for i, att1 in enumerate(attivita_tecnico):
                for att2 in attivita_tecnico[i+1:]:
                    # Verifica sovrapposizione temporale
                    if (att1.iniziata_il < att2.conclusa_il and 
                        att2.iniziata_il < att1.conclusa_il):
                        
                        # Verifica clienti diversi
                        if att1.azienda != att2.azienda:
                            stats['overlaps_detected'] += 1
                            
                            alert = self.create_alert(
                                AlertSeverity.CRITICO,
                                tecnico,
                                CONFIG.ALERT_TEMPLATES['temporal_overlap'].format(
                                    tecnico=tecnico,
                                    cliente_a=att1.azienda or 'Unknown',
                                    cliente_b=att2.azienda or 'Unknown'
                                ),
                                'temporal_overlap',
                                {
                                    'attivita_1': {
                                        'id': att1.id_ticket,
                                        'cliente': att1.azienda,
                                        'orario': f"{att1.iniziata_il} - {att1.conclusa_il}"
                                    },
                                    'attivita_2': {
                                        'id': att2.id_ticket,
                                        'cliente': att2.azienda,
                                        'orario': f"{att2.iniziata_il} - {att2.conclusa_il}"
                                    }
                                }
                            )
                            alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR002",
            rule_name="Rilevamento Sovrapposizioni Temporali",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    # REGOLA 3: Coerenza Geografica e Tempi di Viaggio
    def validate_travel_times(self, attivita: List[AttivitaTecnico], 
                             timbrature: List[TimbraturaTecnico]) -> ValidationResult:
        """
        Regola 3: Valida coerenza geografica e tempi di viaggio tra appuntamenti
        """
        start_time = datetime.now()
        alerts = []
        stats = {'travel_validations': 0, 'impossible_travels': 0}
        
        # Raggruppa attività per tecnico e data
        attivita_per_tecnico_data = defaultdict(list)
        for att in attivita:
            if att.tecnico and att.iniziata_il:
                key = (att.tecnico, att.iniziata_il.date())
                attivita_per_tecnico_data[key].append(att)
        
        for (tecnico, data), attivita_giorno in attivita_per_tecnico_data.items():
            # Ordina attività per orario
            attivita_giorno.sort(key=lambda x: x.iniziata_il)
            
            # Controlla tempi di viaggio tra attività consecutive
            for i in range(len(attivita_giorno) - 1):
                att_corrente = attivita_giorno[i]
                att_successiva = attivita_giorno[i + 1]
                
                if (att_corrente.conclusa_il and att_successiva.iniziata_il and
                    att_corrente.azienda != att_successiva.azienda):
                    
                    # Calcola tempo disponibile per viaggio
                    tempo_viaggio = att_successiva.iniziata_il - att_corrente.conclusa_il
                    tempo_viaggio_minuti = int(tempo_viaggio.total_seconds() / 60)
                    
                    stats['travel_validations'] += 1
                    
                    # Se tempo viaggio < soglia configurata, genera alert
                    if tempo_viaggio_minuti < CONFIG.MAX_TRAVEL_TIME_MINUTES and tempo_viaggio_minuti >= 0:
                        stats['impossible_travels'] += 1
                        
                        alert = self.create_alert(
                            AlertSeverity.MEDIO,
                            tecnico,
                            f"{tecnico}: tempo viaggio insufficiente tra {att_corrente.azienda} e {att_successiva.azienda} ({tempo_viaggio_minuti} min)",
                            'insufficient_travel_time',
                            {
                                'attivita_precedente': {
                                    'cliente': att_corrente.azienda,
                                    'fine': att_corrente.conclusa_il.isoformat(),
                                    'id': att_corrente.id_ticket
                                },
                                'attivita_successiva': {
                                    'cliente': att_successiva.azienda,
                                    'inizio': att_successiva.iniziata_il.isoformat(),
                                    'id': att_successiva.id_ticket
                                },
                                'tempo_viaggio_minuti': tempo_viaggio_minuti
                            }
                        )
                        alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR003", 
            rule_name="Validazione Tempi di Viaggio",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    # REGOLA 4: Rilevamento Report Mancanti
    def detect_missing_reports(self, attivita: List[AttivitaTecnico],
                              timbrature: List[TimbraturaTecnico],
                              permessi: List[PermessoTecnico],
                              target_date: datetime = None) -> ValidationResult:
        """
        Regola 4: Rileva tecnici attivi senza rapportini giornalieri
        """
        start_time = datetime.now()
        alerts = []
        
        if target_date is None:
            target_date = datetime.now().date()
        else:
            target_date = target_date.date()
        
        stats = {'target_date': target_date.isoformat(), 'active_technicians': 0, 'missing_reports': 0}
        
        # Trova tecnici con timbrature nella data target
        tecnici_attivi = set()
        for timbratura in timbrature:
            if (timbratura.ora_inizio and 
                timbratura.ora_inizio.date() == target_date and
                timbratura.nome_completo != "Unknown"):
                tecnici_attivi.add(timbratura.nome_completo)
        
        stats['active_technicians'] = len(tecnici_attivi)
        
        # Trova tecnici con attività reportate nella data target
        tecnici_con_report = set()
        for att in attivita:
            if (att.tecnico and att.iniziata_il and 
                att.iniziata_il.date() == target_date):
                tecnici_con_report.add(att.tecnico)
        
        # Trova tecnici in permesso nella data target
        tecnici_in_permesso = set()
        for permesso in permessi:
            if (permesso.stato == StatoPermesso.APPROVATO and 
                permesso.data_inizio and permesso.data_fine):
                
                if permesso.data_inizio.date() <= target_date <= permesso.data_fine.date():
                    if permesso.dipendente:
                        tecnici_in_permesso.add(permesso.dipendente)
        
        # Identifica tecnici attivi senza report (escludendo quelli in permesso)
        tecnici_senza_report = tecnici_attivi - tecnici_con_report - tecnici_in_permesso
        
        stats['missing_reports'] = len(tecnici_senza_report)
        
        for tecnico in tecnici_senza_report:
            alert = self.create_alert(
                AlertSeverity.ALTO,
                tecnico,
                CONFIG.ALERT_TEMPLATES['missing_reports'].format(tecnico=tecnico),
                'missing_daily_report',
                {
                    'data_controllo': target_date.isoformat(),
                    'ha_timbrature': True,
                    'ha_permessi': False
                }
            )
            alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR004",
            rule_name="Rilevamento Report Mancanti",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    def execute_core_validations(self,
                                attivita: List[AttivitaTecnico],
                                timbrature: List[TimbraturaTecnico],
                                sessioni_bait: List[SessioneTeamViewer],
                                sessioni_gruppo: List[SessioneTeamViewer],
                                permessi: List[PermessoTecnico]) -> List[ValidationResult]:
        """Esegue le prime 4 validazioni core del sistema"""
        
        LOGGER.info("Avvio validazioni core Business Rules Engine...")
        
        results = []
        
        # Regola 1: Validazione tipo attività vs TeamViewer
        result1 = self.validate_activity_type_vs_teamviewer(attivita, sessioni_bait, sessioni_gruppo)
        results.append(result1)
        LOGGER.info(f"✓ {result1.rule_name}: {len(result1.alerts)} alert generati ({result1.execution_time_ms}ms)")
        
        # Regola 2: Sovrapposizioni temporali
        result2 = self.detect_temporal_overlaps(attivita)
        results.append(result2)
        LOGGER.info(f"✓ {result2.rule_name}: {len(result2.alerts)} alert generati ({result2.execution_time_ms}ms)")
        
        # Regola 3: Tempi di viaggio
        result3 = self.validate_travel_times(attivita, timbrature)
        results.append(result3)
        LOGGER.info(f"✓ {result3.rule_name}: {len(result3.alerts)} alert generati ({result3.execution_time_ms}ms)")
        
        # Regola 4: Report mancanti
        result4 = self.detect_missing_reports(attivita, timbrature, permessi)
        results.append(result4)
        LOGGER.info(f"✓ {result4.rule_name}: {len(result4.alerts)} alert generati ({result4.execution_time_ms}ms)")
        
        total_alerts = sum(len(r.alerts) for r in results)
        total_time = sum(r.execution_time_ms for r in results)
        
        LOGGER.info(f"Validazioni core completate: {total_alerts} alert totali in {total_time}ms")
        
        return results

if __name__ == "__main__":
    # Test del Business Rules Engine
    engine = BusinessRulesEngine()
    print("Business Rules Engine (Core) implementato con successo!")
    print("Regole implementate:")
    print("- BR001: Validazione Tipo Attività vs TeamViewer")  
    print("- BR002: Rilevamento Sovrapposizioni Temporali")
    print("- BR003: Validazione Tempi di Viaggio")
    print("- BR004: Rilevamento Report Mancanti")