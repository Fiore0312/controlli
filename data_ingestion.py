"""
BAIT Activity Controller - Data Ingestion Engine
Sistema robusto di parsing CSV con rilevamento automatico encoding
"""

import pandas as pd
import chardet
from datetime import datetime
from typing import Dict, Optional, List, Any
import os
from config import CONFIG, LOGGER

class DataIngestionEngine:
    """Engine per l'ingestion robusta dei file CSV con encoding misto"""
    
    def __init__(self, base_path: str = '.'):
        self.base_path = base_path
        self.data_cache: Dict[str, pd.DataFrame] = {}
        
    def detect_encoding(self, file_path: str) -> str:
        """Rileva automaticamente l'encoding del file"""
        try:
            # Leggi un campione del file per rilevare encoding
            with open(file_path, 'rb') as file:
                raw_data = file.read(10000)  # Primi 10KB
                
            # Usa chardet per rilevare encoding
            detection = chardet.detect(raw_data)
            detected_encoding = detection['encoding']
            confidence = detection['confidence']
            
            LOGGER.info(f"File {file_path}: encoding rilevato {detected_encoding} (confidence: {confidence:.2f})")
            
            # Se confidence è bassa, prova encodings comuni per file italiani
            if confidence < 0.7:
                LOGGER.warning(f"Confidence bassa per {file_path}, provo encodings standard")
                for encoding in CONFIG.ENCODINGS_TO_TRY:
                    try:
                        with open(file_path, 'r', encoding=encoding) as f:
                            f.read(1000)  # Test lettura
                        LOGGER.info(f"Encoding {encoding} funziona per {file_path}")
                        return encoding
                    except UnicodeDecodeError:
                        continue
                        
            return detected_encoding or 'cp1252'  # Default per file Windows italiani
            
        except Exception as e:
            LOGGER.error(f"Errore rilevamento encoding per {file_path}: {e}")
            return 'cp1252'  # Fallback encoding
    
    def parse_italian_date(self, date_str: str) -> Optional[datetime]:
        """Parse date in formato italiano con multiple possibilità"""
        if not date_str or pd.isna(date_str) or date_str.strip() == '':
            return None
            
        date_str = str(date_str).strip()
        
        # Prova tutti i formati date configurati
        for date_format in CONFIG.DATE_FORMATS:
            try:
                return datetime.strptime(date_str, date_format)
            except ValueError:
                continue
                
        LOGGER.warning(f"Impossibile parsare data: {date_str}")
        return None
    
    def clean_csv_data(self, df: pd.DataFrame, file_type: str) -> pd.DataFrame:
        """Pulizia dati CSV specifica per tipo file"""
        
        # Rimozione righe completamente vuote
        df = df.dropna(how='all')
        
        # Pulizia specifica per timbrature.csv (dati corrotti)
        if file_type == 'timbrature':
            # Rimuovi righe con caratteri di controllo malformati
            df = df[~df.iloc[:, 0].astype(str).str.contains(r'[-]{10,}', regex=True, na=False)]
            
            # Pulizia colonne numeriche con valori corrotti
            numeric_columns = ['ore', 'ore in centesimi', 'ore arrotondate', 'centesimi al netto delle pause']
            for col in numeric_columns:
                if col in df.columns:
                    # Sostituisci valori numerici anomali (es. 501.666.666.666.672)
                    df[col] = pd.to_numeric(df[col], errors='coerce')
                    df[col] = df[col].apply(lambda x: x if pd.isna(x) or x < 24 else None)
        
        # Pulizia generale caratteri speciali malformati
        for col in df.select_dtypes(include=['object']).columns:
            df[col] = df[col].astype(str).str.replace(r'[^\w\s\/:;,.()-]', '', regex=True)
            
        return df
    
    def load_csv_file(self, file_name: str, file_type: str) -> Optional[pd.DataFrame]:
        """Carica singolo file CSV con gestione robusta errori"""
        file_path = os.path.join(self.base_path, file_name)
        
        if not os.path.exists(file_path):
            LOGGER.error(f"File non trovato: {file_path}")
            return None
            
        try:
            # Rileva encoding automaticamente
            encoding = self.detect_encoding(file_path)
            
            # Carica CSV con parametri robusti
            df = pd.read_csv(
                file_path,
                sep=CONFIG.CSV_SEPARATOR,
                encoding=encoding,
                on_bad_lines='skip',  # Salta righe malformate
                engine='python',  # Engine più robusto
                dtype=str  # Carica tutto come string per pulizia manuale
            )
            
            LOGGER.info(f"Caricato {file_name}: {len(df)} righe, {len(df.columns)} colonne")
            
            # Pulizia dati specifica per file type
            df = self.clean_csv_data(df, file_type)
            
            # Cache per performance
            self.data_cache[file_type] = df
            
            return df
            
        except Exception as e:
            LOGGER.error(f"Errore caricamento {file_name}: {e}")
            return None
    
    def load_all_data(self) -> Dict[str, pd.DataFrame]:
        """Carica tutti i file CSV configurati"""
        LOGGER.info("Avvio caricamento dati CSV...")
        
        loaded_data = {}
        
        for file_type, file_name in CONFIG.CSV_FILES.items():
            df = self.load_csv_file(file_name, file_type)
            if df is not None:
                loaded_data[file_type] = df
                LOGGER.info(f"✓ {file_type}: {len(df)} record caricati")
            else:
                LOGGER.error(f"✗ {file_type}: caricamento fallito")
        
        LOGGER.info(f"Caricamento completato: {len(loaded_data)}/{len(CONFIG.CSV_FILES)} file caricati")
        return loaded_data
    
    def validate_data_integrity(self, data: Dict[str, pd.DataFrame]) -> Dict[str, Any]:
        """Valida integrità dei dati caricati"""
        validation_report = {
            'files_loaded': len(data),
            'total_records': sum(len(df) for df in data.values()),
            'files_status': {},
            'data_issues': []
        }
        
        for file_type, df in data.items():
            file_status = {
                'records': len(df),
                'columns': len(df.columns),
                'empty_records': df.isnull().all(axis=1).sum(),
                'duplicate_records': df.duplicated().sum()
            }
            
            # Check specifici per tipo file
            if file_type == 'attivita':
                missing_tickets = df['Id Ticket'].isnull().sum()
                if missing_tickets > 0:
                    validation_report['data_issues'].append(f"attivita.csv: {missing_tickets} record senza ID Ticket")
            
            elif file_type == 'timbrature':
                missing_times = df[['ora inizio', 'ora fine']].isnull().any(axis=1).sum()
                if missing_times > 0:
                    validation_report['data_issues'].append(f"timbrature.csv: {missing_times} record senza orari")
            
            validation_report['files_status'][file_type] = file_status
        
        LOGGER.info(f"Validazione completata: {validation_report['total_records']} record totali")
        
        if validation_report['data_issues']:
            for issue in validation_report['data_issues']:
                LOGGER.warning(f"Problema dati: {issue}")
        
        return validation_report

# Funzione di utilità per uso standalone
def load_bait_data(base_path: str = '.') -> Dict[str, pd.DataFrame]:
    """Funzione di convenienza per caricare tutti i dati BAIT"""
    engine = DataIngestionEngine(base_path)
    return engine.load_all_data()

if __name__ == "__main__":
    # Test del sistema di ingestion
    engine = DataIngestionEngine()
    data = engine.load_all_data()
    validation = engine.validate_data_integrity(data)
    
    print(f"Dati caricati: {validation['files_loaded']} file, {validation['total_records']} record totali")
    for file_type, df in data.items():
        print(f"- {file_type}: {len(df)} record")