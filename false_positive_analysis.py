#!/usr/bin/env python3
"""
BAIT SERVICE - BUSINESS RULES ENGINE v2.0
TASK 11: Analisi Falsi Positivi e Pattern Problematici

Analizza i 38 alert generati per identificare:
1. Pattern dei 31 alert "insufficient_travel_time"
2. Problemi parsing nomi tecnici ("nan", "00:45")
3. Casistiche business BAIT Service specifiche
4. Accuracy degli alert critici
"""

import pandas as pd
import json
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional
import logging

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class FalsePositiveAnalyzer:
    def __init__(self):
        self.analysis_results = {
            'travel_time_patterns': [],
            'parsing_issues': [],
            'critical_alerts_accuracy': [],
            'business_exceptions': [],
            'recommendations': []
        }
    
    def analyze_dashboard_data(self, json_file: str) -> Dict:
        """Analizza i dati del dashboard per identificare falsi positivi"""
        logger.info("🔍 Inizio analisi falsi positivi...")
        
        with open(json_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        alerts = data['alerts']['alerts']
        
        # 1. ANALISI ALERT TRAVEL TIME
        travel_alerts = [a for a in alerts if a['categoria'] == 'insufficient_travel_time']
        self._analyze_travel_time_patterns(travel_alerts)
        
        # 2. ANALISI PROBLEMI PARSING
        self._analyze_parsing_issues(alerts)
        
        # 3. ANALISI ACCURACY ALERT CRITICI
        critical_alerts = [a for a in alerts if a['severity'] == 1]
        self._analyze_critical_accuracy(critical_alerts)
        
        # 4. IDENTIFICAZIONE CASI BUSINESS SPECIFICI
        self._identify_business_exceptions(alerts)
        
        # 5. GENERAZIONE RACCOMANDAZIONI
        self._generate_recommendations()
        
        return self.analysis_results
    
    def _analyze_travel_time_patterns(self, travel_alerts: List[Dict]):
        """Analizza i pattern negli alert di tempo viaggio insufficiente"""
        logger.info(f"📊 Analizzando {len(travel_alerts)} alert travel time...")
        
        # Pattern 1: Alert con 0 minuti (possibili attività consecutive)
        zero_min_alerts = [a for a in travel_alerts 
                          if a['dettagli']['tempo_viaggio_minuti'] == 0]
        
        # Pattern 2: Alert con tempo < 30min (possibili ragionevoli)
        short_time_alerts = [a for a in travel_alerts 
                           if 0 < a['dettagli']['tempo_viaggio_minuti'] <= 30]
        
        # Pattern 3: Analisi clienti ricorrenti
        client_patterns = {}
        for alert in travel_alerts:
            client_pair = f"{alert['dettagli']['attivita_precedente']['cliente']} -> {alert['dettagli']['attivita_successiva']['cliente']}"
            if client_pair not in client_patterns:
                client_patterns[client_pair] = 0
            client_patterns[client_pair] += 1
        
        # Pattern 4: BAIT Service interno (probabilmente non richiede viaggio)
        bait_internal_alerts = [a for a in travel_alerts
                               if 'BAIT Service' in str(a['dettagli'])]
        
        self.analysis_results['travel_time_patterns'] = {
            'total_alerts': len(travel_alerts),
            'zero_minute_alerts': {
                'count': len(zero_min_alerts),
                'percentage': (len(zero_min_alerts) / len(travel_alerts)) * 100,
                'likely_false_positives': True,
                'reason': 'Attività consecutive nello stesso luogo o remote'
            },
            'short_time_alerts': {
                'count': len(short_time_alerts),
                'percentage': (len(short_time_alerts) / len(travel_alerts)) * 100,
                'likely_false_positives': True,
                'reason': 'Tempi viaggio ragionevoli per Milano/Lombardia'
            },
            'client_patterns': dict(list(sorted(client_patterns.items(), 
                                         key=lambda x: x[1], reverse=True))[:10]),
            'bait_internal_count': len(bait_internal_alerts),
            'false_positive_estimate': {
                'count': len(zero_min_alerts) + len(short_time_alerts),
                'percentage': ((len(zero_min_alerts) + len(short_time_alerts)) / len(travel_alerts)) * 100
            }
        }
    
    def _analyze_parsing_issues(self, alerts: List[Dict]):
        """Analizza problemi di parsing nei dati"""
        logger.info("🔧 Analizzando problemi parsing...")
        
        # Tecnici con nomi problematici
        problematic_technicians = set()
        for alert in alerts:
            tecnico = alert['tecnico']
            if tecnico in ['nan', '00:45'] or pd.isna(tecnico):
                problematic_technicians.add(tecnico)
        
        # Alert con tecnici problematici
        problematic_alerts = [a for a in alerts 
                             if a['tecnico'] in problematic_technicians]
        
        self.analysis_results['parsing_issues'] = {
            'problematic_technicians': list(problematic_technicians),
            'affected_alerts': len(problematic_alerts),
            'total_alerts': len(alerts),
            'impact_percentage': (len(problematic_alerts) / len(alerts)) * 100,
            'main_issues': [
                "Nomi tecnici 'nan' indicano problemi nel parsing CSV",
                "Valori '00:45' suggeriscono problemi nella normalizzazione orari",
                "Necessario miglioramento data ingestion engine"
            ]
        }
    
    def _analyze_critical_accuracy(self, critical_alerts: List[Dict]):
        """Analizza l'accuracy degli alert critici"""
        logger.info(f"⚠️  Analizzando {len(critical_alerts)} alert critici...")
        
        for alert in critical_alerts:
            # Analisi sovrapposizione temporale
            details = alert['dettagli']
            att1 = details['attivita_1']
            att2 = details['attivita_2']
            
            # Parsing orari
            orario1_parts = att1['orario'].split(' - ')
            orario2_parts = att2['orario'].split(' - ')
            
            start1 = datetime.strptime(orario1_parts[0], '%Y-%m-%d %H:%M:%S')
            end1 = datetime.strptime(orario1_parts[1], '%Y-%m-%d %H:%M:%S')
            start2 = datetime.strptime(orario2_parts[0], '%Y-%m-%d %H:%M:%S')
            end2 = datetime.strptime(orario2_parts[1], '%Y-%m-%d %H:%M:%S')
            
            # Verifica sovrapposizione reale
            overlap_real = not (end1 <= start2 or end2 <= start1)
            
            # Calcolo minuti sovrapposizione
            if overlap_real:
                overlap_start = max(start1, start2)
                overlap_end = min(end1, end2)
                overlap_minutes = (overlap_end - overlap_start).total_seconds() / 60
            else:
                overlap_minutes = 0
            
            accuracy_assessment = {
                'alert_id': alert['id'],
                'tecnico': alert['tecnico'],
                'cliente_1': att1['cliente'],
                'cliente_2': att2['cliente'],
                'overlap_confirmed': overlap_real,
                'overlap_minutes': overlap_minutes,
                'severity_justified': overlap_minutes > 15,  # >15min = problematico
                'likely_accuracy': 'HIGH' if overlap_minutes > 15 else 'MEDIUM'
            }
            
            self.analysis_results['critical_alerts_accuracy'].append(accuracy_assessment)
    
    def _identify_business_exceptions(self, alerts: List[Dict]):
        """Identifica eccezioni specifiche del business BAIT Service"""
        logger.info("🏢 Identificando eccezioni business BAIT Service...")
        
        business_exceptions = []
        
        for alert in alerts:
            # Eccezione 1: Attività BAIT Service interne (stesso ufficio)
            if 'BAIT Service' in str(alert.get('dettagli', {})):
                business_exceptions.append({
                    'type': 'BAIT_INTERNAL',
                    'alert_id': alert['id'],
                    'reason': 'Attività interna BAIT Service - probabile stesso ufficio',
                    'suggested_action': 'WHITELIST per attività consecutive BAIT Service'
                })
            
            # Eccezione 2: Clienti gruppo (stesso indirizzo/sede)
            dettagli = alert.get('dettagli', {})
            if alert['categoria'] == 'insufficient_travel_time':
                client_prev = dettagli.get('attivita_precedente', {}).get('cliente', '')
                client_next = dettagli.get('attivita_successiva', {}).get('cliente', '')
                
                # Pattern clienti stesso gruppo
                same_group_patterns = [
                    ('ELECTRALINE', 'ELECTRALINE'),
                    ('SPOLIDORO', 'SPOLIDORO'),
                    ('ISOTERMA', 'GARIBALDINA'),  # Possibile stesso gruppo
                ]
                
                for pattern1, pattern2 in same_group_patterns:
                    if (pattern1 in client_prev and pattern2 in client_next) or \
                       (pattern2 in client_prev and pattern1 in client_next):
                        business_exceptions.append({
                            'type': 'SAME_GROUP',
                            'alert_id': alert['id'],
                            'clients': f"{client_prev} -> {client_next}",
                            'reason': 'Possibili sedi multiple stesso gruppo',
                            'suggested_action': 'VERIFY clienti stesso gruppo'
                        })
        
        self.analysis_results['business_exceptions'] = business_exceptions
    
    def _generate_recommendations(self):
        """Genera raccomandazioni per migliorare accuracy"""
        logger.info("💡 Generando raccomandazioni...")
        
        recommendations = [
            {
                'priority': 'HIGH',
                'category': 'Data Quality',
                'title': 'Risoluzione Parsing Tecnici "nan"',
                'description': 'Implementare fallback intelligente per nomi tecnici corrotti',
                'implementation': 'Mapping ID attività -> tecnico da fonti alternative'
            },
            {
                'priority': 'HIGH', 
                'category': 'Business Rules',
                'title': 'Whitelist BAIT Service Interno',
                'description': 'Escludere controlli travel time per attività consecutive BAIT Service',
                'implementation': 'Regola business specifica per cliente "BAIT Service S.r.l."'
            },
            {
                'priority': 'MEDIUM',
                'category': 'Geo Intelligence',
                'title': 'Database Distanze Clienti',
                'description': 'Creare mappa distanze realistiche tra clienti frequenti',
                'implementation': 'Calcolo georeferenziato + cache intelligente'
            },
            {
                'priority': 'MEDIUM',
                'category': 'Confidence Scoring',
                'title': 'Scoring Dinamico Travel Time',
                'description': 'Tolleranze intelligenti basate su tipologia cliente e orario',
                'implementation': 'Algoritmo ML-like con pattern storici'
            },
            {
                'priority': 'LOW',
                'category': 'User Experience',
                'title': 'Feedback System',
                'description': 'Permettere marking alert come veri/falsi positivi',
                'implementation': 'Dashboard interattiva + learning automatico'
            }
        ]
        
        self.analysis_results['recommendations'] = recommendations
    
    def generate_report(self) -> str:
        """Genera report dettagliato dell'analisi"""
        
        report = f"""
🔍 BAIT SERVICE - ANALISI FALSI POSITIVI
=====================================
📅 Data Analisi: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

📊 SINTESI PROBLEMI RILEVATI:
-----------------------------

🚨 TRAVEL TIME ALERTS (31 total):
• Zero minuti: {self.analysis_results['travel_time_patterns']['zero_minute_alerts']['count']} alert ({self.analysis_results['travel_time_patterns']['zero_minute_alerts']['percentage']:.1f}%)
• < 30 minuti: {self.analysis_results['travel_time_patterns']['short_time_alerts']['count']} alert ({self.analysis_results['travel_time_patterns']['short_time_alerts']['percentage']:.1f}%)
• FALSI POSITIVI STIMATI: {self.analysis_results['travel_time_patterns']['false_positive_estimate']['count']} ({self.analysis_results['travel_time_patterns']['false_positive_estimate']['percentage']:.1f}%)

🔧 PROBLEMI PARSING:
• Tecnici problematici: {', '.join(self.analysis_results['parsing_issues']['problematic_technicians'])}
• Alert impattati: {self.analysis_results['parsing_issues']['affected_alerts']} ({self.analysis_results['parsing_issues']['impact_percentage']:.1f}%)

⚠️  ALERT CRITICI:
• Totale: {len(self.analysis_results['critical_alerts_accuracy'])}
• Accuracy ALTA: {len([a for a in self.analysis_results['critical_alerts_accuracy'] if a['likely_accuracy'] == 'HIGH'])}
• Accuracy MEDIA: {len([a for a in self.analysis_results['critical_alerts_accuracy'] if a['likely_accuracy'] == 'MEDIUM'])}

🏢 ECCEZIONI BUSINESS:
• Totale identificate: {len(self.analysis_results['business_exceptions'])}
• BAIT Service interne: {len([e for e in self.analysis_results['business_exceptions'] if e['type'] == 'BAIT_INTERNAL'])}
• Stesso gruppo: {len([e for e in self.analysis_results['business_exceptions'] if e['type'] == 'SAME_GROUP'])}

💡 TOP 5 RACCOMANDAZIONI:
------------------------
"""
        
        for i, rec in enumerate(self.analysis_results['recommendations'][:5], 1):
            report += f"{i}. [{rec['priority']}] {rec['title']}\n   → {rec['description']}\n\n"
        
        report += f"""
🎯 IMPACT MIGLIORAMENTO STIMATO:
-------------------------------
• Accuracy attuale: 93.5%
• Falsi positivi ridotti: ~{self.analysis_results['travel_time_patterns']['false_positive_estimate']['percentage']:.0f}%
• Accuracy target: >95%
• Alert ridotti da 38 a ~15-20 (eliminando falsi positivi)

📋 PROSSIMI PASSI:
-----------------
1. Implementare Business Rules Engine v2.0 con confidence scoring
2. Risolvere problemi parsing "nan"/"00:45" 
3. Creare whitelist BAIT Service interno
4. Implementare geo-intelligence per travel time
5. Testing su accuracy migliorata
"""
        
        return report

def main():
    """Esegue analisi completa falsi positivi"""
    analyzer = FalsePositiveAnalyzer()
    
    # Analizza dati dashboard
    results = analyzer.analyze_dashboard_data('bait_dashboard_data_20250809_1331.json')
    
    # Genera report
    report = analyzer.generate_report()
    
    # Salva risultati
    timestamp = datetime.now().strftime('%Y%m%d_%H%M')
    
    # Report testuale
    with open(f'false_positive_analysis_{timestamp}.txt', 'w', encoding='utf-8') as f:
        f.write(report)
    
    # Dati strutturati JSON
    with open(f'false_positive_data_{timestamp}.json', 'w', encoding='utf-8') as f:
        json.dump(results, f, indent=2, ensure_ascii=False, default=str)
    
    print(report)
    logger.info(f"✅ Analisi completata! File salvati con timestamp {timestamp}")
    
    return results

if __name__ == "__main__":
    main()