"""
BAIT Activity Controller - KPI Calculator & Business Intelligence
Sistema di calcolo KPI e analisi business intelligence per attività tecnici
"""

from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional, Tuple
from collections import defaultdict, Counter
from dataclasses import dataclass
import statistics

from models import (
    AttivitaTecnico, TimbraturaTecnico, SessioneTeamViewer,
    UtilizzoVeicolo, PermessoTecnico, Alert, AlertSeverity, TipologiaAttivita
)
from config import CONFIG, LOGGER

@dataclass
class TechnicianKPI:
    """KPI per singolo tecnico"""
    nome: str
    ore_reportate: float
    ore_tracciate: float
    efficienza_percentuale: float
    attivita_remote: int
    attivita_onsite: int
    utilizzo_veicoli: int
    alert_critici: int
    alert_totali: int
    sessioni_teamviewer: int
    durata_media_sessioni: float
    score_qualita: float  # 0-100

@dataclass
class SystemKPI:
    """KPI di sistema aggregati"""
    data_calcolo: datetime
    tecnici_totali: int
    attivita_totali: int
    ore_lavorate_totali: float
    efficienza_media: float
    accuracy_billing: float
    utilizzo_risorse: float
    alert_critici_totali: int
    problemi_fatturazione: int
    trend_efficienza: Optional[float] = None

class KPICalculator:
    """Calcolatore KPI e Business Intelligence"""
    
    def __init__(self):
        self.kpi_history: List[SystemKPI] = []
    
    def calculate_technician_efficiency(self, 
                                      attivita: List[AttivitaTecnico],
                                      timbrature: List[TimbraturaTecnico],
                                      tecnico: str) -> float:
        """Calcola efficienza tecnico (ore reportate / ore tracciate)"""
        
        # Ore reportate dalle attività
        ore_reportate = sum(
            att.durata_ore for att in attivita 
            if att.tecnico == tecnico and att.durata_ore
        )
        
        # Ore tracciate da timbrature
        ore_tracciate = sum(
            tim.ore_lavorate for tim in timbrature 
            if tim.nome_completo == tecnico and tim.ore_lavorate
        )
        
        if ore_tracciate == 0:
            return 0.0
        
        efficienza = (ore_reportate / ore_tracciate) * 100
        return min(efficienza, 150.0)  # Cap al 150% per valori anomali
    
    def calculate_billing_accuracy(self, 
                                  attivita: List[AttivitaTecnico],
                                  alerts: List[Alert]) -> float:
        """Calcola accuracy fatturazione (% attività validate senza alert critici)"""
        
        if not attivita:
            return 100.0
        
        # Alert critici che impattano la fatturazione
        billing_critical_categories = [
            'missing_remote_session',
            'temporal_overlap', 
            'remote_activity_with_vehicle',
            'activity_during_permit'
        ]
        
        billing_alerts = [
            alert for alert in alerts 
            if alert.severity == AlertSeverity.CRITICO and 
               alert.categoria in billing_critical_categories
        ]
        
        # Stima attività impattate (conservativa: 1 alert = 1 attività problematica)
        attivita_problematiche = len(billing_alerts)
        attivita_validate = max(0, len(attivita) - attivita_problematiche)
        
        accuracy = (attivita_validate / len(attivita)) * 100
        return max(0.0, accuracy)
    
    def calculate_resource_utilization(self,
                                     utilizzo_veicoli: List[UtilizzoVeicolo],
                                     sessioni_tv: List[SessioneTeamViewer]) -> float:
        """Calcola utilizzo risorse (veicoli + TeamViewer)"""
        
        # Utilizzo veicoli (ore utilizzate vs ore disponibili lavorative)
        if utilizzo_veicoli:
            ore_veicoli_utilizzate = sum(
                veicolo.durata_minuti / 60 for veicolo in utilizzo_veicoli 
                if veicolo.durata_minuti
            )
            # Assumiamo 8 ore lavorative per veicolo per giorno
            ore_veicoli_disponibili = len(set(v.auto for v in utilizzo_veicoli if v.auto)) * 8
            utilizzo_veicoli_pct = (ore_veicoli_utilizzate / ore_veicoli_disponibili) * 100 if ore_veicoli_disponibili > 0 else 0
        else:
            utilizzo_veicoli_pct = 0
        
        # Utilizzo TeamViewer (sessioni attive)
        if sessioni_tv:
            sessioni_produttive = [
                s for s in sessioni_tv 
                if s.durata_minuti and s.durata_minuti >= CONFIG.MIN_TEAMVIEWER_SESSION_MINUTES
            ]
            utilizzo_tv_pct = (len(sessioni_produttive) / len(sessioni_tv)) * 100
        else:
            utilizzo_tv_pct = 100  # Nessun TeamViewer = utilizzo ottimale
        
        # Media ponderata
        utilizzo_totale = (utilizzo_veicoli_pct * 0.4) + (utilizzo_tv_pct * 0.6)
        return min(100.0, utilizzo_totale)
    
    def calculate_technician_quality_score(self,
                                          tecnico_alerts: List[Alert],
                                          attivita_count: int,
                                          ore_efficienza: float) -> float:
        """Calcola score qualità tecnico (0-100)"""
        
        if attivita_count == 0:
            return 0.0
        
        # Peso alert per gravità
        alert_penalties = {
            AlertSeverity.CRITICO: 20,
            AlertSeverity.ALTO: 10,
            AlertSeverity.MEDIO: 5,
            AlertSeverity.BASSO: 2
        }
        
        # Calcola penalità alert
        penalita_totale = sum(
            alert_penalties.get(alert.severity, 0) 
            for alert in tecnico_alerts
        )
        
        # Score base da efficienza (peso 60%)
        efficienza_score = min(100, ore_efficienza) * 0.6
        
        # Bonus per zero alert (peso 40%)
        alert_score = max(0, 100 - penalita_totale) * 0.4
        
        quality_score = efficienza_score + alert_score
        return max(0.0, min(100.0, quality_score))
    
    def calculate_technician_kpis(self,
                                 tecnico: str,
                                 attivita: List[AttivitaTecnico],
                                 timbrature: List[TimbraturaTecnico],
                                 sessioni_tv: List[SessioneTeamViewer],
                                 utilizzo_veicoli: List[UtilizzoVeicolo],
                                 alerts: List[Alert]) -> TechnicianKPI:
        """Calcola KPI completi per singolo tecnico"""
        
        # Filtra dati per tecnico
        tech_attivita = [a for a in attivita if a.tecnico == tecnico]
        tech_timbrature = [t for t in timbrature if t.nome_completo == tecnico]
        tech_sessioni = [s for s in sessioni_tv if s.tecnico == tecnico]
        tech_veicoli = [v for v in utilizzo_veicoli if v.dipendente == tecnico]
        tech_alerts = [al for al in alerts if al.tecnico == tecnico]
        
        # Calcoli base
        ore_reportate = sum(a.durata_ore for a in tech_attivita if a.durata_ore)
        ore_tracciate = sum(t.ore_lavorate for t in tech_timbrature if t.ore_lavorate)
        efficienza = self.calculate_technician_efficiency(attivita, timbrature, tecnico)
        
        # Conteggi attività
        attivita_remote = sum(1 for a in tech_attivita if a.tipologia == TipologiaAttivita.REMOTO)
        attivita_onsite = sum(1 for a in tech_attivita if a.tipologia == TipologiaAttivita.ONSITE)
        
        # Alert
        alert_critici = sum(1 for al in tech_alerts if al.severity == AlertSeverity.CRITICO)
        
        # Sessioni TeamViewer
        durate_sessioni = [s.durata_minuti for s in tech_sessioni if s.durata_minuti]
        durata_media = statistics.mean(durate_sessioni) if durate_sessioni else 0.0
        
        # Quality score
        quality_score = self.calculate_technician_quality_score(
            tech_alerts, len(tech_attivita), efficienza
        )
        
        return TechnicianKPI(
            nome=tecnico,
            ore_reportate=ore_reportate,
            ore_tracciate=ore_tracciate,
            efficienza_percentuale=efficienza,
            attivita_remote=attivita_remote,
            attivita_onsite=attivita_onsite,
            utilizzo_veicoli=len(tech_veicoli),
            alert_critici=alert_critici,
            alert_totali=len(tech_alerts),
            sessioni_teamviewer=len(tech_sessioni),
            durata_media_sessioni=durata_media,
            score_qualita=quality_score
        )
    
    def calculate_system_kpis(self,
                             attivita: List[AttivitaTecnico],
                             timbrature: List[TimbraturaTecnico],
                             sessioni_tv: List[SessioneTeamViewer],
                             utilizzo_veicoli: List[UtilizzoVeicolo],
                             alerts: List[Alert]) -> SystemKPI:
        """Calcola KPI di sistema aggregati"""
        
        # Tecnici unici
        tecnici = set()
        tecnici.update(a.tecnico for a in attivita if a.tecnico)
        tecnici.update(t.nome_completo for t in timbrature if t.nome_completo != "Unknown")
        tecnici_totali = len(tecnici)
        
        # Ore totali
        ore_totali = sum(a.durata_ore for a in attivita if a.durata_ore)
        
        # Efficienza media
        efficienze = []
        for tecnico in tecnici:
            eff = self.calculate_technician_efficiency(attivita, timbrature, tecnico)
            if eff > 0:
                efficienze.append(eff)
        efficienza_media = statistics.mean(efficienze) if efficienze else 0.0
        
        # Accuracy billing
        accuracy = self.calculate_billing_accuracy(attivita, alerts)
        
        # Utilizzo risorse
        utilizzo_risorse = self.calculate_resource_utilization(utilizzo_veicoli, sessioni_tv)
        
        # Alert critici
        alert_critici = sum(1 for al in alerts if al.severity == AlertSeverity.CRITICO)
        
        # Problemi fatturazione (stima conservativa)
        problemi_fatturazione = sum(
            1 for al in alerts 
            if al.severity in [AlertSeverity.CRITICO, AlertSeverity.ALTO] and
               al.categoria in ['missing_remote_session', 'temporal_overlap', 
                               'remote_activity_with_vehicle', 'activity_during_permit']
        )
        
        system_kpi = SystemKPI(
            data_calcolo=datetime.now(),
            tecnici_totali=tecnici_totali,
            attivita_totali=len(attivita),
            ore_lavorate_totali=ore_totali,
            efficienza_media=efficienza_media,
            accuracy_billing=accuracy,
            utilizzo_risorse=utilizzo_risorse,
            alert_critici_totali=alert_critici,
            problemi_fatturazione=problemi_fatturazione
        )
        
        # Calcola trend efficienza se abbiamo storico
        if self.kpi_history:
            last_kpi = self.kpi_history[-1]
            trend = system_kpi.efficienza_media - last_kpi.efficienza_media
            system_kpi.trend_efficienza = trend
        
        # Aggiungi allo storico
        self.kpi_history.append(system_kpi)
        
        return system_kpi
    
    def generate_kpi_report(self, system_kpi: SystemKPI, 
                           technician_kpis: List[TechnicianKPI]) -> str:
        """Genera report KPI testuale"""
        
        report_lines = [
            "📊 BAIT ACTIVITY CONTROLLER - KPI REPORT",
            "=" * 55,
            f"🕐 Data Calcolo: {system_kpi.data_calcolo.strftime('%d/%m/%Y %H:%M')}",
            "",
            "🎯 KPI DI SISTEMA",
            "-" * 20,
            f"👥 Tecnici Attivi: {system_kpi.tecnici_totali}",
            f"📋 Attività Totali: {system_kpi.attivita_totali}",
            f"⏱️  Ore Lavorate: {system_kpi.ore_lavorate_totali:.1f}h",
            f"⚡ Efficienza Media: {system_kpi.efficienza_media:.1f}%",
            f"💰 Accuracy Fatturazione: {system_kpi.accuracy_billing:.1f}%",
            f"🔧 Utilizzo Risorse: {system_kpi.utilizzo_risorse:.1f}%",
            f"🚨 Alert Critici: {system_kpi.alert_critici_totali}",
            f"💸 Problemi Fatturazione: {system_kpi.problemi_fatturazione}",
        ]
        
        if system_kpi.trend_efficienza is not None:
            trend_icon = "📈" if system_kpi.trend_efficienza > 0 else "📉"
            report_lines.append(f"{trend_icon} Trend Efficienza: {system_kpi.trend_efficienza:+.1f}%")
        
        # Top tecnici per qualità
        top_tecnici = sorted(technician_kpis, key=lambda x: x.score_qualita, reverse=True)[:5]
        if top_tecnici:
            report_lines.extend([
                "",
                "🏆 TOP 5 TECNICI PER QUALITÀ",
                "-" * 30
            ])
            
            for i, tech in enumerate(top_tecnici, 1):
                quality_icon = "🟢" if tech.score_qualita >= 90 else "🟡" if tech.score_qualita >= 70 else "🔴"
                report_lines.append(
                    f"{i}. {quality_icon} {tech.nome}: {tech.score_qualita:.1f}/100 "
                    f"(Eff: {tech.efficienza_percentuale:.1f}%, Alert: {tech.alert_totali})"
                )
        
        # Tecnici con problemi
        problem_tecnici = [t for t in technician_kpis if t.alert_critici > 0]
        if problem_tecnici:
            report_lines.extend([
                "",
                "⚠️  TECNICI CON ALERT CRITICI",
                "-" * 32
            ])
            
            problem_tecnici.sort(key=lambda x: x.alert_critici, reverse=True)
            for tech in problem_tecnici[:10]:
                report_lines.append(
                    f"🔴 {tech.nome}: {tech.alert_critici} alert critici, "
                    f"{tech.alert_totali} totali (Quality: {tech.score_qualita:.1f})"
                )
        
        # Riepilogo operativo
        report_lines.extend([
            "",
            "📋 RIEPILOGO OPERATIVO",
            "-" * 22,
            f"• Efficienza Sistema: {system_kpi.efficienza_media:.1f}% "
            f"({'Ottima' if system_kpi.efficienza_media >= 85 else 'Buona' if system_kpi.efficienza_media >= 70 else 'Critica'})",
            f"• Qualità Fatturazione: {system_kpi.accuracy_billing:.1f}% "
            f"({'Ottima' if system_kpi.accuracy_billing >= 95 else 'Buona' if system_kpi.accuracy_billing >= 85 else 'Critica'})",
            f"• Gestione Risorse: {system_kpi.utilizzo_risorse:.1f}% "
            f"({'Ottimale' if system_kpi.utilizzo_risorse >= 80 else 'Buona' if system_kpi.utilizzo_risorse >= 60 else 'Migliorabile'})"
        ])
        
        report_lines.extend([
            "",
            "=" * 55,
            "🤖 Generato da BAIT Activity Controller v1.0"
        ])
        
        return "\n".join(report_lines)
    
    def export_kpis_json(self, system_kpi: SystemKPI, 
                        technician_kpis: List[TechnicianKPI]) -> Dict[str, Any]:
        """Esporta KPI in formato JSON per dashboard"""
        
        return {
            'metadata': {
                'generation_time': datetime.now().isoformat(),
                'system_version': '1.0',
                'kpi_count': len(technician_kpis)
            },
            'system_kpis': {
                'data_calcolo': system_kpi.data_calcolo.isoformat(),
                'tecnici_totali': system_kpi.tecnici_totali,
                'attivita_totali': system_kpi.attivita_totali,
                'ore_lavorate_totali': system_kpi.ore_lavorate_totali,
                'efficienza_media': system_kpi.efficienza_media,
                'accuracy_billing': system_kpi.accuracy_billing,
                'utilizzo_risorse': system_kpi.utilizzo_risorse,
                'alert_critici_totali': system_kpi.alert_critici_totali,
                'problemi_fatturazione': system_kpi.problemi_fatturazione,
                'trend_efficienza': system_kpi.trend_efficienza
            },
            'technician_kpis': [
                {
                    'nome': tech.nome,
                    'ore_reportate': tech.ore_reportate,
                    'ore_tracciate': tech.ore_tracciate,
                    'efficienza_percentuale': tech.efficienza_percentuale,
                    'attivita_remote': tech.attivita_remote,
                    'attivita_onsite': tech.attivita_onsite,
                    'utilizzo_veicoli': tech.utilizzo_veicoli,
                    'alert_critici': tech.alert_critici,
                    'alert_totali': tech.alert_totali,
                    'sessioni_teamviewer': tech.sessioni_teamviewer,
                    'durata_media_sessioni': tech.durata_media_sessioni,
                    'score_qualita': tech.score_qualita
                }
                for tech in technician_kpis
            ]
        }

if __name__ == "__main__":
    # Test del KPI Calculator
    calculator = KPICalculator()
    print("KPI Calculator & Business Intelligence implementato con successo!")
    print("Funzionalità disponibili:")
    print("- Calcolo efficienza tecnici")
    print("- Accuracy fatturazione")
    print("- Utilizzo risorse")
    print("- Quality score tecnici")
    print("- KPI aggregati di sistema")
    print("- Report testuali e JSON")
    print("- Trend analysis")