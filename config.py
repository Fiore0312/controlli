"""
BAIT Activity Controller - Configuration
Sistema di controllo automatico attività tecnici
"""

from dataclasses import dataclass
from typing import List, Dict, Any
import logging

@dataclass
class SystemConfig:
    """Configurazione sistema BAIT Controller"""
    
    # File CSV da processare
    CSV_FILES = {
        'attivita': 'upload_csv/attivita.csv',
        'timbrature': 'upload_csv/timbrature.csv', 
        'teamviewer_bait': 'upload_csv/teamviewer_bait.csv',
        'teamviewer_gruppo': 'upload_csv/teamviewer_gruppo.csv',
        'permessi': 'upload_csv/permessi.csv',
        'auto': 'upload_csv/auto.csv',
        'calendario': 'upload_csv/calendario.csv'
    }
    
    # Encoding rilevamento
    ENCODINGS_TO_TRY = ['cp1252', 'utf-8', 'iso-8859-1']
    
    # Separatore CSV
    CSV_SEPARATOR = ';'
    
    # Formato date italiane
    DATE_FORMATS = ['%d/%m/%Y %H:%M', '%d/%m/%Y', '%Y-%m-%d %H:%M:%S']
    
    # Soglie business rules
    MAX_TRAVEL_TIME_MINUTES = 60  # Tempo massimo viaggio tra appuntamenti
    MIN_TEAMVIEWER_SESSION_MINUTES = 5  # Sessione minima TeamViewer per attività remota
    MAX_TIME_DISCREPANCY_MINUTES = 30  # Discrepanza massima calendario vs timbrature
    
    # Alert severity levels
    ALERT_SEVERITY = {
        'CRITICO': 1,   # Perdite di fatturazione sicure
        'ALTO': 2,      # Probabili problemi di fatturazione  
        'MEDIO': 3,     # Inefficienze operative
        'BASSO': 4      # Informazioni per ottimizzazione
    }
    
    # Template messaggi alert
    ALERT_TEMPLATES = {
        'missing_reports': '{tecnico} non ha rapportini oggi',
        'calendar_vs_tracking': '{tecnico}: calendario {calendario_ora} vs timbratura {timbratura_ora}',
        'vehicle_no_client': '{tecnico}: auto senza cliente',
        'remote_with_vehicle': '{tecnico}: attività remota con auto',
        'temporal_overlap': '{tecnico}: sovrapposizione temporale clienti {cliente_a} e {cliente_b}',
        'no_teamviewer': '{tecnico}: attività remota senza sessione TeamViewer per cliente {cliente}',
        'vehicle_time_mismatch': '{tecnico}: orari auto non coerenti con attività cliente {cliente}',
        'activity_during_permit': '{tecnico}: attività durante permesso approvato'
    }

# Setup logging
def setup_logging():
    """Configura sistema di logging"""
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        handlers=[
            logging.StreamHandler(),
            logging.FileHandler('bait_controller.log', encoding='utf-8')
        ]
    )
    return logging.getLogger(__name__)

# Istanza configurazione globale
CONFIG = SystemConfig()
LOGGER = setup_logging()