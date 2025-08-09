"""
BAIT Activity Controller - Main Controller
Sistema completo di controllo automatico attività tecnici
Controller principale che orchestrera tutti i moduli del sistema
"""

import os
import json
from datetime import datetime
from typing import Dict, List, Any, Optional

# Import dei moduli del sistema
from config import CONFIG, LOGGER
from data_ingestion import DataIngestionEngine
from models import DataModelFactory
from business_rules import BusinessRulesEngine
from business_rules_advanced import AdvancedBusinessRulesEngine
from alert_system import AlertManager, AlertFormatter
from kpi_calculator import KPICalculator

class BAITActivityController:
    """Controller principale sistema BAIT"""
    
    def __init__(self, base_path: str = '.'):
        self.base_path = base_path
        self.ingestion_engine = DataIngestionEngine(base_path)
        self.business_rules = BusinessRulesEngine()
        self.advanced_rules = AdvancedBusinessRulesEngine()
        self.alert_manager = AlertManager()
        self.kpi_calculator = KPICalculator()
        
        # Contenitori per dati processati
        self.raw_data: Dict[str, Any] = {}
        self.processed_data: Dict[str, List] = {}
        self.validation_results: List = []
        self.system_kpi = None
        self.technician_kpis: List = []
        
        LOGGER.info("BAIT Activity Controller inizializzato")
    
    def load_and_process_data(self) -> bool:
        """Carica e processa tutti i dati CSV"""
        try:
            LOGGER.info("🚀 Avvio caricamento dati...")
            
            # Caricamento dati raw
            self.raw_data = self.ingestion_engine.load_all_data()
            
            if not self.raw_data:
                LOGGER.error("❌ Nessun dato caricato, impossibile continuare")
                return False
            
            # Validazione integrità dati
            validation_report = self.ingestion_engine.validate_data_integrity(self.raw_data)
            LOGGER.info(f"📊 Validazione dati: {validation_report['total_records']} record totali")
            
            # Conversione in modelli tipizzati
            LOGGER.info("🔄 Conversione dati in modelli...")
            
            # Attività
            if 'attivita' in self.raw_data:
                self.processed_data['attivita'] = DataModelFactory.create_attivita_from_df(
                    self.raw_data['attivita']
                )
            
            # Timbrature
            if 'timbrature' in self.raw_data:
                self.processed_data['timbrature'] = DataModelFactory.create_timbrature_from_df(
                    self.raw_data['timbrature']
                )
            
            # TeamViewer BAIT
            if 'teamviewer_bait' in self.raw_data:
                self.processed_data['sessioni_bait'] = DataModelFactory.create_teamviewer_from_df(
                    self.raw_data['teamviewer_bait'], is_bait=True
                )
            
            # TeamViewer Gruppo
            if 'teamviewer_gruppo' in self.raw_data:
                self.processed_data['sessioni_gruppo'] = DataModelFactory.create_teamviewer_from_df(
                    self.raw_data['teamviewer_gruppo'], is_bait=False
                )
            
            # Altri dati (implementazione semplificata per ora)
            self.processed_data['permessi'] = []
            self.processed_data['utilizzo_veicoli'] = []
            self.processed_data['calendario'] = []
            
            # Statistiche caricamento
            stats = {
                'attivita': len(self.processed_data.get('attivita', [])),
                'timbrature': len(self.processed_data.get('timbrature', [])),
                'sessioni_bait': len(self.processed_data.get('sessioni_bait', [])),
                'sessioni_gruppo': len(self.processed_data.get('sessioni_gruppo', []))
            }
            
            LOGGER.info("✅ Dati processati con successo:")
            for tipo, count in stats.items():
                LOGGER.info(f"  - {tipo}: {count} record")
            
            return True
            
        except Exception as e:
            LOGGER.error(f"❌ Errore nel caricamento dati: {e}")
            return False
    
    def execute_business_validations(self) -> bool:
        """Esegue tutte le validazioni business rules"""
        try:
            LOGGER.info("🔍 Avvio validazioni Business Rules...")
            
            # Validazioni core (Regole 1-4)
            core_results = self.business_rules.execute_core_validations(
                self.processed_data.get('attivita', []),
                self.processed_data.get('timbrature', []),
                self.processed_data.get('sessioni_bait', []),
                self.processed_data.get('sessioni_gruppo', []),
                self.processed_data.get('permessi', [])
            )
            
            # Validazioni avanzate (Regole 5-7)
            advanced_results = self.advanced_rules.execute_advanced_validations(
                self.processed_data.get('attivita', []),
                self.processed_data.get('timbrature', []),
                self.processed_data.get('calendario', []),
                self.processed_data.get('utilizzo_veicoli', []),
                self.processed_data.get('permessi', [])
            )
            
            # Combina risultati
            self.validation_results = core_results + advanced_results
            
            # Raccoglie tutti gli alert
            all_alerts = []
            for result in self.validation_results:
                all_alerts.extend(result.alerts)
                
            # Aggiunge alert al manager
            self.alert_manager.add_alerts(all_alerts)
            
            # Statistiche validazioni
            total_alerts = len(all_alerts)
            total_time = sum(r.execution_time_ms for r in self.validation_results)
            
            LOGGER.info(f"✅ Validazioni completate: {total_alerts} alert generati in {total_time}ms")
            
            # Log per regola
            for result in self.validation_results:
                LOGGER.info(f"  - {result.rule_name}: {len(result.alerts)} alert")
            
            return True
            
        except Exception as e:
            LOGGER.error(f"❌ Errore nelle validazioni: {e}")
            return False
    
    def calculate_kpis(self) -> bool:
        """Calcola KPI e metriche business intelligence"""
        try:
            LOGGER.info("📊 Calcolo KPI e Business Intelligence...")
            
            # KPI di sistema
            self.system_kpi = self.kpi_calculator.calculate_system_kpis(
                self.processed_data.get('attivita', []),
                self.processed_data.get('timbrature', []),
                self.processed_data.get('sessioni_bait', []) + self.processed_data.get('sessioni_gruppo', []),
                self.processed_data.get('utilizzo_veicoli', []),
                self.alert_manager.alerts
            )
            
            # KPI per tecnico
            tecnici = set()
            tecnici.update(a.tecnico for a in self.processed_data.get('attivita', []) if a.tecnico)
            tecnici.update(t.nome_completo for t in self.processed_data.get('timbrature', []) if t.nome_completo != "Unknown")
            
            self.technician_kpis = []
            for tecnico in tecnici:
                tech_kpi = self.kpi_calculator.calculate_technician_kpis(
                    tecnico,
                    self.processed_data.get('attivita', []),
                    self.processed_data.get('timbrature', []),
                    self.processed_data.get('sessioni_bait', []) + self.processed_data.get('sessioni_gruppo', []),
                    self.processed_data.get('utilizzo_veicoli', []),
                    self.alert_manager.alerts
                )
                self.technician_kpis.append(tech_kpi)
            
            LOGGER.info(f"✅ KPI calcolati per {len(self.technician_kpis)} tecnici")
            LOGGER.info(f"  - Efficienza media sistema: {self.system_kpi.efficienza_media:.1f}%")
            LOGGER.info(f"  - Accuracy fatturazione: {self.system_kpi.accuracy_billing:.1f}%")
            
            return True
            
        except Exception as e:
            LOGGER.error(f"❌ Errore nel calcolo KPI: {e}")
            return False
    
    def generate_reports(self, output_dir: str = None) -> Dict[str, str]:
        """Genera tutti i report del sistema"""
        try:
            LOGGER.info("📄 Generazione report...")
            
            if output_dir is None:
                output_dir = self.base_path
            
            reports_generated = {}
            
            # 1. Alert Summary (console e file)
            alert_summary = self.alert_manager.generate_alert_summary()
            
            summary_file = os.path.join(output_dir, f"alert_summary_{datetime.now().strftime('%Y%m%d_%H%M')}.txt")
            with open(summary_file, 'w', encoding='utf-8') as f:
                f.write(alert_summary)
            
            reports_generated['alert_summary'] = summary_file
            LOGGER.info(f"✅ Alert summary salvato: {summary_file}")
            
            # 2. KPI Report
            if self.system_kpi and self.technician_kpis:
                kpi_report = self.kpi_calculator.generate_kpi_report(self.system_kpi, self.technician_kpis)
                
                kpi_file = os.path.join(output_dir, f"kpi_report_{datetime.now().strftime('%Y%m%d_%H%M')}.txt")
                with open(kpi_file, 'w', encoding='utf-8') as f:
                    f.write(kpi_report)
                
                reports_generated['kpi_report'] = kpi_file
                LOGGER.info(f"✅ KPI report salvato: {kpi_file}")
            
            # 3. JSON Export per Dashboard
            json_export = {
                'alerts': self.alert_manager.export_alerts_json(),
                'kpis': self.kpi_calculator.export_kpis_json(self.system_kpi, self.technician_kpis) if self.system_kpi else {},
                'system_metadata': {
                    'generation_time': datetime.now().isoformat(),
                    'data_files_processed': list(self.raw_data.keys()),
                    'validation_rules_executed': len(self.validation_results),
                    'total_processing_time_ms': sum(r.execution_time_ms for r in self.validation_results)
                }
            }
            
            json_file = os.path.join(output_dir, f"bait_dashboard_data_{datetime.now().strftime('%Y%m%d_%H%M')}.json")
            with open(json_file, 'w', encoding='utf-8') as f:
                json.dump(json_export, f, indent=2, ensure_ascii=False, default=str)
            
            reports_generated['json_export'] = json_file
            LOGGER.info(f"✅ JSON dashboard export salvato: {json_file}")
            
            # 4. HTML Report (se ci sono alert)
            if self.alert_manager.alerts:
                html_report = AlertFormatter.format_html_report(self.alert_manager)
                
                html_file = os.path.join(output_dir, f"alert_report_{datetime.now().strftime('%Y%m%d_%H%M')}.html")
                with open(html_file, 'w', encoding='utf-8') as f:
                    f.write(html_report)
                
                reports_generated['html_report'] = html_file
                LOGGER.info(f"✅ HTML report salvato: {html_file}")
            
            return reports_generated
            
        except Exception as e:
            LOGGER.error(f"❌ Errore nella generazione report: {e}")
            return {}
    
    def print_executive_summary(self):
        """Stampa riepilogo esecutivo a console"""
        print("\n" + "=" * 60)
        print("🤖 BAIT ACTIVITY CONTROLLER - RIEPILOGO ESECUTIVO")
        print("=" * 60)
        
        # Dati processati
        if self.raw_data:
            print(f"📊 DATI PROCESSATI:")
            for file_type, df in self.raw_data.items():
                print(f"  • {file_type}: {len(df)} record")
        
        # Alert
        if self.alert_manager.alerts:
            stats = self.alert_manager.calculate_alert_statistics()
            print(f"\n🚨 ALERT RILEVATI: {stats['total_alerts']}")
            
            for severity, count in stats['by_severity'].items():
                severity_icon = {'CRITICO': '🔴', 'ALTO': '🟠', 'MEDIO': '🟡', 'BASSO': '🟢'}.get(severity, '⚪')
                print(f"  {severity_icon} {severity}: {count}")
            
            if stats['critical_technicians']:
                print(f"  ⚠️  Tecnici con alert critici: {', '.join(stats['critical_technicians'])}")
        else:
            print("\n✅ Nessun alert rilevato - Tutte le attività sono conformi")
        
        # KPI
        if self.system_kpi:
            print(f"\n📈 KPI SISTEMA:")
            print(f"  • Tecnici Attivi: {self.system_kpi.tecnici_totali}")
            print(f"  • Efficienza Media: {self.system_kpi.efficienza_media:.1f}%")
            print(f"  • Accuracy Fatturazione: {self.system_kpi.accuracy_billing:.1f}%")
            print(f"  • Utilizzo Risorse: {self.system_kpi.utilizzo_risorse:.1f}%")
            
            if self.system_kpi.problemi_fatturazione > 0:
                print(f"  💸 Problemi Fatturazione: {self.system_kpi.problemi_fatturazione}")
        
        # Validazioni eseguite
        if self.validation_results:
            print(f"\n🔍 VALIDAZIONI ESEGUITE: {len(self.validation_results)}")
            total_time = sum(r.execution_time_ms for r in self.validation_results)
            print(f"  • Tempo totale: {total_time}ms")
        
        print(f"\n🕐 Elaborazione completata: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}")
        print("=" * 60)
    
    def run_full_analysis(self) -> bool:
        """Esegue analisi completa del sistema"""
        try:
            start_time = datetime.now()
            LOGGER.info("🚀 AVVIO ANALISI COMPLETA BAIT ACTIVITY CONTROLLER")
            
            # 1. Caricamento dati
            if not self.load_and_process_data():
                return False
            
            # 2. Validazioni business rules
            if not self.execute_business_validations():
                return False
            
            # 3. Calcolo KPI
            if not self.calculate_kpis():
                return False
            
            # 4. Generazione report
            reports = self.generate_reports()
            
            # 5. Riepilogo
            self.print_executive_summary()
            
            # 6. Statistiche finali
            end_time = datetime.now()
            total_time = (end_time - start_time).total_seconds()
            
            LOGGER.info(f"✅ ANALISI COMPLETA TERMINATA in {total_time:.2f}s")
            LOGGER.info(f"📄 Report generati: {len(reports)}")
            
            for report_type, file_path in reports.items():
                print(f"  • {report_type}: {file_path}")
            
            return True
            
        except Exception as e:
            LOGGER.error(f"❌ ERRORE CRITICO nell'analisi: {e}")
            return False

def main():
    """Funzione principale per esecuzione standalone"""
    print("🤖 BAIT Activity Controller v1.0")
    print("Sistema di controllo automatico attività tecnici")
    print("-" * 50)
    
    # Inizializza controller
    controller = BAITActivityController()
    
    # Esegui analisi completa
    success = controller.run_full_analysis()
    
    if success:
        print("\n✅ Analisi completata con successo!")
        print("📊 Controlla i file di report generati per i dettagli completi.")
    else:
        print("\n❌ Analisi fallita. Controlla i log per dettagli.")
    
    return success

if __name__ == "__main__":
    main()