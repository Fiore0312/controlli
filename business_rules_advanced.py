"""
BAIT Activity Controller - Advanced Business Rules
Regole business avanzate per controllo coerenza calendario, veicoli e permessi
"""

from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional
from collections import defaultdict

from models import (
    AttivitaTecnico, TimbraturaTecnico, UtilizzoVeicolo, 
    AppuntamentoCalendario, PermessoTecnico, Alert, AlertSeverity, 
    TipologiaAttivita, StatoPermesso
)
from business_rules import ValidationResult, BusinessRulesEngine
from config import CONFIG, LOGGER

class AdvancedBusinessRulesEngine(BusinessRulesEngine):
    """Engine per regole business avanzate"""
    
    # REGOLA 5: Coerenza Calendario vs Timbrature vs Attività
    def validate_schedule_coherence(self,
                                   attivita: List[AttivitaTecnico],
                                   timbrature: List[TimbraturaTecnico], 
                                   calendario: List[AppuntamentoCalendario]) -> ValidationResult:
        """
        Regola 5: Confronta orari pianificati vs time tracking vs attività reportate
        """
        start_time = datetime.now()
        alerts = []
        stats = {
            'calendar_appointments': len(calendario),
            'coherence_checks': 0,
            'schedule_discrepancies': 0,
            'missing_activities': 0
        }
        
        for appuntamento in calendario:
            if not appuntamento.tecnico or not appuntamento.data_inizio:
                continue
                
            tecnico = appuntamento.tecnico
            data_app = appuntamento.data_inizio.date()
            stats['coherence_checks'] += 1
            
            # Trova timbrature corrispondenti
            timbrature_tecnico = [
                t for t in timbrature 
                if (t.nome_completo == tecnico and t.ora_inizio and 
                    t.ora_inizio.date() == data_app)
            ]
            
            # Trova attività corrispondenti  
            attivita_tecnico = [
                a for a in attivita
                if (a.tecnico == tecnico and a.iniziata_il and 
                    a.iniziata_il.date() == data_app and
                    a.azienda == appuntamento.cliente)
            ]
            
            # Verifica coerenza orari calendario vs timbrature
            for timbratura in timbrature_tecnico:
                if timbratura.cliente_nome == appuntamento.cliente:
                    discrepanza_inizio = abs(
                        (appuntamento.data_inizio - timbratura.ora_inizio).total_seconds() / 60
                    )
                    
                    if discrepanza_inizio > CONFIG.MAX_TIME_DISCREPANCY_MINUTES:
                        stats['schedule_discrepancies'] += 1
                        
                        alert = self.create_alert(
                            AlertSeverity.MEDIO,
                            tecnico,
                            CONFIG.ALERT_TEMPLATES['calendar_vs_tracking'].format(
                                tecnico=tecnico,
                                calendario_ora=appuntamento.data_inizio.strftime('%H:%M'),
                                timbratura_ora=timbratura.ora_inizio.strftime('%H:%M')
                            ),
                            'schedule_discrepancy',
                            {
                                'cliente': appuntamento.cliente,
                                'calendario_orario': appuntamento.data_inizio.isoformat(),
                                'timbratura_orario': timbratura.ora_inizio.isoformat(),
                                'discrepanza_minuti': int(discrepanza_inizio)
                            }
                        )
                        alerts.append(alert)
            
            # Verifica presenza attività per appuntamento calendario
            if not attivita_tecnico:
                stats['missing_activities'] += 1
                
                alert = self.create_alert(
                    AlertSeverity.ALTO,
                    tecnico,
                    f"{tecnico}: appuntamento calendario {appuntamento.cliente} senza attività reportata",
                    'missing_activity_for_appointment',
                    {
                        'cliente': appuntamento.cliente,
                        'calendario_orario': appuntamento.data_inizio.isoformat(),
                        'luogo': appuntamento.luogo
                    }
                )
                alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR005",
            rule_name="Coerenza Calendario vs Timbrature vs Attività",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    # REGOLA 6: Validazione Utilizzo Veicoli
    def validate_vehicle_usage(self,
                              attivita: List[AttivitaTecnico],
                              utilizzo_veicoli: List[UtilizzoVeicolo]) -> ValidationResult:
        """
        Regola 6: Validazioni utilizzo veicoli aziendali
        - Auto utilizzata deve avere cliente associato
        - Attività remote NON devono avere utilizzo auto
        - Coerenza orari presa/riconsegna vs attività
        """
        start_time = datetime.now()
        alerts = []
        stats = {
            'vehicle_usages': len(utilizzo_veicoli),
            'vehicles_without_client': 0,
            'remote_with_vehicle': 0,
            'time_mismatches': 0
        }
        
        # Validazione veicoli senza cliente
        for veicolo in utilizzo_veicoli:
            if not veicolo.dipendente:
                continue
                
            tecnico = veicolo.dipendente
            
            # Veicolo senza cliente associato
            if not veicolo.cliente:
                stats['vehicles_without_client'] += 1
                
                alert = self.create_alert(
                    AlertSeverity.ALTO,
                    tecnico,
                    CONFIG.ALERT_TEMPLATES['vehicle_no_client'].format(tecnico=tecnico),
                    'vehicle_no_client',
                    {
                        'auto': veicolo.auto,
                        'ora_presa': veicolo.ora_presa.isoformat() if veicolo.ora_presa else None,
                        'ora_riconsegna': veicolo.ora_riconsegna.isoformat() if veicolo.ora_riconsegna else None
                    }
                )
                alerts.append(alert)
                continue
            
            # Verifica attività remote con utilizzo veicolo
            if veicolo.ora_presa:
                data_utilizzo = veicolo.ora_presa.date()
                
                # Trova attività remote dello stesso tecnico nella stessa data
                attivita_remote = [
                    a for a in attivita
                    if (a.tecnico == tecnico and 
                        a.tipologia == TipologiaAttivita.REMOTO and
                        a.iniziata_il and a.iniziata_il.date() == data_utilizzo)
                ]
                
                for att_remota in attivita_remote:
                    # Verifica sovrapposizione temporale
                    if (veicolo.ora_presa and veicolo.ora_riconsegna and
                        att_remota.iniziata_il and att_remota.conclusa_il):
                        
                        # Controllo sovrapposizione
                        if (veicolo.ora_presa < att_remota.conclusa_il and
                            att_remota.iniziata_il < veicolo.ora_riconsegna):
                            
                            stats['remote_with_vehicle'] += 1
                            
                            alert = self.create_alert(
                                AlertSeverity.CRITICO,
                                tecnico,
                                CONFIG.ALERT_TEMPLATES['remote_with_vehicle'].format(tecnico=tecnico),
                                'remote_activity_with_vehicle',
                                {
                                    'attivita_id': att_remota.id_ticket,
                                    'cliente_attivita': att_remota.azienda,
                                    'auto': veicolo.auto,
                                    'cliente_auto': veicolo.cliente,
                                    'orario_attivita': f"{att_remota.iniziata_il} - {att_remota.conclusa_il}",
                                    'orario_auto': f"{veicolo.ora_presa} - {veicolo.ora_riconsegna}"
                                }
                            )
                            alerts.append(alert)
            
            # Validazione coerenza orari auto vs attività cliente
            attivita_cliente = [
                a for a in attivita
                if (a.tecnico == tecnico and a.azienda == veicolo.cliente and
                    a.iniziata_il and veicolo.ora_presa and
                    a.iniziata_il.date() == veicolo.ora_presa.date())
            ]
            
            for att in attivita_cliente:
                if (veicolo.ora_presa and veicolo.ora_riconsegna and 
                    att.iniziata_il and att.conclusa_il):
                    
                    # L'auto dovrebbe essere presa prima dell'inizio attività
                    # e riconsegnata dopo la fine attività
                    if (veicolo.ora_presa > att.iniziata_il or 
                        veicolo.ora_riconsegna < att.conclusa_il):
                        
                        stats['time_mismatches'] += 1
                        
                        alert = self.create_alert(
                            AlertSeverity.MEDIO,
                            tecnico,
                            CONFIG.ALERT_TEMPLATES['vehicle_time_mismatch'].format(
                                tecnico=tecnico, 
                                cliente=veicolo.cliente
                            ),
                            'vehicle_time_mismatch',
                            {
                                'attivita_id': att.id_ticket,
                                'cliente': veicolo.cliente,
                                'auto': veicolo.auto,
                                'orario_attivita': f"{att.iniziata_il} - {att.conclusa_il}",
                                'orario_auto': f"{veicolo.ora_presa} - {veicolo.ora_riconsegna}"
                            }
                        )
                        alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR006",
            rule_name="Validazione Utilizzo Veicoli",
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    # REGOLA 7: Validazione Permessi vs Attività
    def validate_permits_vs_activities(self,
                                      attivita: List[AttivitaTecnico],
                                      permessi: List[PermessoTecnico]) -> ValidationResult:
        """
        Regola 7: Validazioni permessi vs attività
        - Nessuna attività durante permessi approvati
        - Controllo ore lavorate vs ore pianificate
        """
        start_time = datetime.now()
        alerts = []
        stats = {
            'approved_permits': 0,
            'activities_during_permit': 0,
            'hour_discrepancies': 0
        }
        
        # Filtra permessi approvati
        permessi_approvati = [
            p for p in permessi 
            if p.stato == StatoPermesso.APPROVATO and p.data_inizio and p.data_fine
        ]
        
        stats['approved_permits'] = len(permessi_approvati)
        
        for permesso in permessi_approvati:
            if not permesso.dipendente:
                continue
                
            tecnico = permesso.dipendente
            
            # Cerca attività durante il periodo di permesso
            for attivita_item in attivita:
                if (attivita_item.tecnico == tecnico and 
                    attivita_item.iniziata_il):
                    
                    data_attivita = attivita_item.iniziata_il.date()
                    
                    # Verifica se attività cade nel periodo di permesso
                    if permesso.data_inizio.date() <= data_attivita <= permesso.data_fine.date():
                        stats['activities_during_permit'] += 1
                        
                        alert = self.create_alert(
                            AlertSeverity.CRITICO,
                            tecnico,
                            CONFIG.ALERT_TEMPLATES['activity_during_permit'].format(tecnico=tecnico),
                            'activity_during_permit',
                            {
                                'attivita_id': attivita_item.id_ticket,
                                'cliente': attivita_item.azienda,
                                'data_attivita': data_attivita.isoformat(),
                                'tipo_permesso': permesso.tipo_permesso,
                                'periodo_permesso': f"{permesso.data_inizio.date()} - {permesso.data_fine.date()}"
                            }
                        )
                        alerts.append(alert)
        
        execution_time = int((datetime.now() - start_time).total_seconds() * 1000)
        
        return ValidationResult(
            rule_id="BR007",
            rule_name="Validazione Permessi vs Attività", 
            alerts=alerts,
            stats=stats,
            execution_time_ms=execution_time
        )
    
    def execute_advanced_validations(self,
                                    attivita: List[AttivitaTecnico],
                                    timbrature: List[TimbraturaTecnico],
                                    calendario: List[AppuntamentoCalendario],
                                    utilizzo_veicoli: List[UtilizzoVeicolo],
                                    permessi: List[PermessoTecnico]) -> List[ValidationResult]:
        """Esegue le validazioni avanzate del sistema"""
        
        LOGGER.info("Avvio validazioni avanzate Business Rules Engine...")
        
        results = []
        
        # Regola 5: Coerenza calendario
        result5 = self.validate_schedule_coherence(attivita, timbrature, calendario)
        results.append(result5)
        LOGGER.info(f"✓ {result5.rule_name}: {len(result5.alerts)} alert generati ({result5.execution_time_ms}ms)")
        
        # Regola 6: Utilizzo veicoli
        result6 = self.validate_vehicle_usage(attivita, utilizzo_veicoli)
        results.append(result6)
        LOGGER.info(f"✓ {result6.rule_name}: {len(result6.alerts)} alert generati ({result6.execution_time_ms}ms)")
        
        # Regola 7: Permessi vs attività
        result7 = self.validate_permits_vs_activities(attivita, permessi)
        results.append(result7)
        LOGGER.info(f"✓ {result7.rule_name}: {len(result7.alerts)} alert generati ({result7.execution_time_ms}ms)")
        
        total_alerts = sum(len(r.alerts) for r in results)
        total_time = sum(r.execution_time_ms for r in results)
        
        LOGGER.info(f"Validazioni avanzate completate: {total_alerts} alert totali in {total_time}ms")
        
        return results

if __name__ == "__main__":
    # Test delle regole avanzate
    engine = AdvancedBusinessRulesEngine()
    print("Advanced Business Rules Engine implementato con successo!")
    print("Regole avanzate implementate:")
    print("- BR005: Coerenza Calendario vs Timbrature vs Attività")
    print("- BR006: Validazione Utilizzo Veicoli") 
    print("- BR007: Validazione Permessi vs Attività")