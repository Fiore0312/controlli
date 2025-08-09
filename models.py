"""
BAIT Activity Controller - Data Models
Modelli dati con validazione per sistema di controllo attività
"""

from dataclasses import dataclass, field
from datetime import datetime
from typing import Optional, List, Dict, Any
from enum import Enum
import pandas as pd
from config import LOGGER

class TipologiaAttivita(Enum):
    """Tipologie di attività tecnico"""
    REMOTO = "Remoto"
    ONSITE = "On-Site"
    UNKNOWN = "Unknown"

class StatoPermesso(Enum):
    """Stati permessi/ferie"""
    APPROVATO = "Approvato"
    RICHIESTO = "Richiesto" 
    RIFIUTATO = "Rifiutato"
    UNKNOWN = "Unknown"

class AlertSeverity(Enum):
    """Livelli di gravità alert"""
    CRITICO = 1
    ALTO = 2
    MEDIO = 3
    BASSO = 4

@dataclass
class AttivitaTecnico:
    """Modello per attività dichiarata da tecnico"""
    contratto: Optional[str] = None
    id_ticket: Optional[str] = None
    iniziata_il: Optional[datetime] = None
    conclusa_il: Optional[datetime] = None
    azienda: Optional[str] = None
    tipologia: TipologiaAttivita = TipologiaAttivita.UNKNOWN
    descrizione: Optional[str] = None
    durata_ore: Optional[float] = None
    creato_da: Optional[str] = None
    
    @property
    def durata_minuti(self) -> Optional[int]:
        """Calcola durata in minuti"""
        if self.iniziata_il and self.conclusa_il:
            delta = self.conclusa_il - self.iniziata_il
            return int(delta.total_seconds() / 60)
        return None
    
    @property
    def tecnico(self) -> Optional[str]:
        """Estrae nome tecnico"""
        return self.creato_da

@dataclass 
class TimbraturaTecnico:
    """Modello per timbratura GPS tecnico"""
    dipendente_nome: Optional[str] = None
    dipendente_cognome: Optional[str] = None
    cliente_nome: Optional[str] = None
    cliente_indirizzo: Optional[str] = None
    cliente_citta: Optional[str] = None
    ora_inizio: Optional[datetime] = None
    ora_fine: Optional[datetime] = None
    ore_lavorate: Optional[float] = None
    indirizzo_start: Optional[str] = None
    indirizzo_end: Optional[str] = None
    descrizione: Optional[str] = None
    
    @property
    def nome_completo(self) -> str:
        """Nome completo tecnico"""
        if self.dipendente_nome and self.dipendente_cognome:
            return f"{self.dipendente_nome} {self.dipendente_cognome}"
        return "Unknown"
    
    @property
    def durata_minuti(self) -> Optional[int]:
        """Durata in minuti"""
        if self.ora_inizio and self.ora_fine:
            delta = self.ora_fine - self.ora_inizio
            return int(delta.total_seconds() / 60)
        return None

@dataclass
class SessioneTeamViewer:
    """Modello per sessione TeamViewer"""
    data_sessione: Optional[datetime] = None
    durata_minuti: Optional[int] = None
    assegnatario: Optional[str] = None
    computer: Optional[str] = None
    utente: Optional[str] = None
    codice_sessione: Optional[str] = None
    cliente: Optional[str] = None
    
    @property
    def tecnico(self) -> Optional[str]:
        """Estrae tecnico da assegnatario o utente"""
        return self.assegnatario or self.utente

@dataclass
class PermessoTecnico:
    """Modello per permesso/ferie tecnico"""
    dipendente: Optional[str] = None
    tipo_permesso: Optional[str] = None
    data_inizio: Optional[datetime] = None
    data_fine: Optional[datetime] = None
    stato: StatoPermesso = StatoPermesso.UNKNOWN
    ore_permesso: Optional[float] = None

@dataclass
class UtilizzoVeicolo:
    """Modello per utilizzo veicolo aziendale"""
    dipendente: Optional[str] = None
    auto: Optional[str] = None
    ora_presa: Optional[datetime] = None
    ora_riconsegna: Optional[datetime] = None
    cliente: Optional[str] = None
    
    @property
    def durata_minuti(self) -> Optional[int]:
        """Durata utilizzo veicolo"""
        if self.ora_presa and self.ora_riconsegna:
            delta = self.ora_riconsegna - self.ora_presa
            return int(delta.total_seconds() / 60)
        return None

@dataclass
class AppuntamentoCalendario:
    """Modello per appuntamento calendario"""
    cliente: Optional[str] = None
    luogo: Optional[str] = None
    data_inizio: Optional[datetime] = None
    data_fine: Optional[datetime] = None
    tecnico: Optional[str] = None
    descrizione: Optional[str] = None

@dataclass
class Alert:
    """Modello per alert sistema"""
    id_alert: str
    severity: AlertSeverity
    tecnico: str
    messaggio: str
    timestamp: datetime
    dettagli: Dict[str, Any] = field(default_factory=dict)
    categoria: Optional[str] = None
    
    def to_dict(self) -> Dict[str, Any]:
        """Converte alert in dizionario per export"""
        return {
            'id': self.id_alert,
            'severity': self.severity.value,
            'severity_name': self.severity.name,
            'tecnico': self.tecnico,
            'messaggio': self.messaggio,
            'timestamp': self.timestamp.isoformat(),
            'categoria': self.categoria,
            'dettagli': self.dettagli
        }

class DataModelFactory:
    """Factory per creare modelli dati da DataFrame"""
    
    @staticmethod
    def parse_italian_datetime(date_str: str) -> Optional[datetime]:
        """Parse datetime formato italiano"""
        if not date_str or pd.isna(date_str):
            return None
            
        date_str = str(date_str).strip()
        
        # Formati data italiani
        formats = ['%d/%m/%Y %H:%M', '%d/%m/%Y', '%Y-%m-%d %H:%M:%S']
        
        for fmt in formats:
            try:
                return datetime.strptime(date_str, fmt)
            except ValueError:
                continue
                
        LOGGER.warning(f"Impossibile parsare data: {date_str}")
        return None
    
    @staticmethod
    def create_attivita_from_df(df: pd.DataFrame) -> List[AttivitaTecnico]:
        """Crea lista AttivitaTecnico da DataFrame"""
        attivita_list = []
        
        for _, row in df.iterrows():
            try:
                # Determina tipologia attività
                tipologia = TipologiaAttivita.UNKNOWN
                if 'Tipologia Attivit' in row and pd.notna(row['Tipologia Attivit']):
                    tipo_str = str(row['Tipologia Attivit']).strip()
                    if 'Remoto' in tipo_str:
                        tipologia = TipologiaAttivita.REMOTO
                    elif 'On-Site' in tipo_str:
                        tipologia = TipologiaAttivita.ONSITE
                
                # Parse durata
                durata_ore = None
                if 'Durata' in row and pd.notna(row['Durata']):
                    durata_str = str(row['Durata']).replace(',', '.')
                    try:
                        # Converti formato HH:MM in ore decimali
                        if ':' in durata_str:
                            hours, minutes = durata_str.split(':')
                            durata_ore = float(hours) + float(minutes) / 60
                        else:
                            durata_ore = float(durata_str)
                    except ValueError:
                        pass
                
                attivita = AttivitaTecnico(
                    contratto=row.get('Contratto'),
                    id_ticket=row.get('Id Ticket'),
                    iniziata_il=DataModelFactory.parse_italian_datetime(row.get('Iniziata il')),
                    conclusa_il=DataModelFactory.parse_italian_datetime(row.get('Conclusa il')),
                    azienda=row.get('Azienda'),
                    tipologia=tipologia,
                    descrizione=row.get('Descrizione'),
                    durata_ore=durata_ore,
                    creato_da=row.get('Creato da')
                )
                
                attivita_list.append(attivita)
                
            except Exception as e:
                LOGGER.error(f"Errore creazione AttivitaTecnico: {e}")
                continue
        
        LOGGER.info(f"Creati {len(attivita_list)} record AttivitaTecnico")
        return attivita_list
    
    @staticmethod
    def create_timbrature_from_df(df: pd.DataFrame) -> List[TimbraturaTecnico]:
        """Crea lista TimbraturaTecnico da DataFrame"""
        timbrature_list = []
        
        for _, row in df.iterrows():
            try:
                # Parse ore lavorate
                ore_lavorate = None
                if 'ore' in row and pd.notna(row['ore']):
                    try:
                        ore_str = str(row['ore'])
                        # Gestione formato time Excel malformato
                        if 'AM' in ore_str or 'PM' in ore_str:
                            # Estrai ore dalla rappresentazione Excel
                            parts = ore_str.split()
                            if len(parts) >= 2:
                                time_part = parts[1]
                                if ':' in time_part:
                                    hours, minutes = time_part.split(':')[:2]
                                    ore_lavorate = float(hours) + float(minutes) / 60
                        else:
                            ore_lavorate = float(ore_str.replace(',', '.'))
                    except ValueError:
                        pass
                
                timbratura = TimbraturaTecnico(
                    dipendente_nome=row.get('dipendente nome'),
                    dipendente_cognome=row.get('dipendente cognome'),
                    cliente_nome=row.get('cliente nome'),
                    cliente_indirizzo=row.get('cliente indirizzo'),
                    cliente_citta=row.get('cliente citt'),
                    ora_inizio=DataModelFactory.parse_italian_datetime(row.get('ora inizio')),
                    ora_fine=DataModelFactory.parse_italian_datetime(row.get('ora fine')),
                    ore_lavorate=ore_lavorate,
                    indirizzo_start=row.get('indirizzo start'),
                    indirizzo_end=row.get('indirizzo end'),
                    descrizione=row.get('descrizione attivit')
                )
                
                timbrature_list.append(timbratura)
                
            except Exception as e:
                LOGGER.error(f"Errore creazione TimbraturaTecnico: {e}")
                continue
        
        LOGGER.info(f"Creati {len(timbrature_list)} record TimbraturaTecnico")
        return timbrature_list
    
    @staticmethod
    def create_teamviewer_from_df(df: pd.DataFrame, is_bait: bool = True) -> List[SessioneTeamViewer]:
        """Crea lista SessioneTeamViewer da DataFrame"""
        sessioni_list = []
        
        for _, row in df.iterrows():
            try:
                # Parse durata
                durata_minuti = None
                if 'durata' in row and pd.notna(row['durata']):
                    try:
                        durata_str = str(row['durata']).replace(',', '.')
                        durata_minuti = int(float(durata_str))
                    except ValueError:
                        pass
                
                sessione = SessioneTeamViewer(
                    data_sessione=DataModelFactory.parse_italian_datetime(row.get('data sessione')),
                    durata_minuti=durata_minuti,
                    assegnatario=row.get('assegnatario') if is_bait else None,
                    computer=row.get('computer'),
                    utente=row.get('utente') if not is_bait else None,
                    codice_sessione=row.get('codice sessione'),
                    cliente=row.get('cliente')
                )
                
                sessioni_list.append(sessione)
                
            except Exception as e:
                LOGGER.error(f"Errore creazione SessioneTeamViewer: {e}")
                continue
        
        LOGGER.info(f"Creati {len(sessioni_list)} record SessioneTeamViewer")
        return sessioni_list

if __name__ == "__main__":
    # Test dei modelli
    print("Modelli dati BAIT Controller implementati con successo!")
    
    # Test alert
    alert = Alert(
        id_alert="TEST001",
        severity=AlertSeverity.CRITICO,
        tecnico="Mario Rossi",
        messaggio="Test alert",
        timestamp=datetime.now(),
        categoria="test"
    )
    
    print(f"Alert test: {alert.messaggio}")