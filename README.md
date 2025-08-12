# 🚀 BAIT Service Control System

**Sistema Controllo Automatico Attività Tecnici**  
Rilevamento intelligente sovrapposizioni, anomalie e notifiche automatiche.

## 🎯 Obiettivo

Eliminare **fatturazioni doppie**, rilevare **anomalie orari** e automatizzare il controllo quotidiano delle attività tecnici BAIT Service con **zero perdite di fatturato**.

## ⚡ Funzionalità Principali

### 🤖 Agenti Specializzati
- **Data Cleaner**: Pulizia CSV con encoding misti italiani
- **Overlap Detector**: Rilevamento sovrapposizioni critiche
- **Validation Engine**: Controlli business rules BAIT Service  
- **Notification Agent**: Email automatiche personalizzate

### 🔍 Rilevamento Automatico
- **CRITICO**: Fatturazione doppia stesso cliente
- **ALTO**: Sovrapposizioni clienti diversi (impossibilità fisica)
- **MEDIO**: Discrepanze cross-validation dati
- **BASSO**: Anomalie orari e logistica

### 📊 Dashboard Real-Time
- KPI efficienza tecnici
- Alert visivi anomalie critiche
- Grafici sovrapposizioni e trend
- Export report automatici

## 🚀 Quick Start

### Installazione
```bash
# Clone repository
git clone [repository-url]
cd controlli

# Install dependencies
pip install -r requirements.txt

# Verifica sistema
python3 main.py --mode test
```

### Uso Quotidiano
```bash
# Elaborazione giornaliera automatica
python3 main.py --mode daily

# Elaborazione data specifica
python3 main.py --mode daily --date 01/08/2025

# Avvia dashboard
python3 main.py --mode dashboard
```

### Dashboard Web
```bash
# Accesso dashboard
http://localhost:8050

# Dashboard con porta personalizzata
python3 main.py --mode dashboard --port 8080
```

## 📂 Struttura Progetto

```
controlli/
├── agents/                 # Agenti specializzati
│   ├── data_cleaner.py        # Pulizia CSV
│   ├── overlap_detector.py    # Rilevamento sovrapposizioni  
│   ├── validation_engine.py   # Controlli business
│   └── notification_agent.py  # Sistema email
├── models/                 # Modelli dati e business rules
│   ├── schemas.py            # Pydantic models
│   └── business_rules.py     # Regole BAIT Service
├── dashboard/              # Interface web
│   ├── app.py               # Dashboard principale
│   └── components/         # Componenti UI
├── config/                 # Configurazioni
│   ├── settings.py          # Configurazioni sistema
│   └── email_templates/    # Template email
├── utils/                  # Utilities
│   ├── csv_handler.py       # Gestione CSV avanzata
│   └── time_utils.py       # Parsing date/orari
├── data/                   # Directory dati
│   ├── input/              # CSV giornalieri
│   ├── processed/          # Dati puliti
│   └── reports/            # Report generati
└── main.py                 # Entry point principale
```

## 📋 File CSV Supportati

### Input Giornalieri (data/input/)
- `timbrature.csv` - Timbrature ingresso/uscita
- `teamviewer_bait.csv` - Sessioni remote individuali  
- `teamviewer_gruppo.csv` - Sessioni remote gruppo
- `permessi.csv` - Ferie/permessi approvati
- `auto.csv` - Utilizzo auto aziendale
- `attivita.csv` - Attività tecnici
- `calendario.csv` - Eventi calendario

### Elaborazione Automatica
✅ **Rilevamento encoding** automatico (UTF-8, Latin1, CP1252)  
✅ **Separatori multipli** (virgola, punto-virgola, tab)  
✅ **Pulizia dati** (BOM, spazi, caratteri speciali)  
✅ **Normalizzazione** nomi tecnici/clienti  

## 🚨 Alert Automatici

### Priorità CRITICA (Azione Immediata)
- **Fatturazione doppia stesso cliente**
- Email immediata + CC supervisore
- Escalation automatica dopo 2 ore

### Priorità ALTA (Verifica Urgente)  
- **Sovrapposizioni clienti diversi**
- **Ore eccessive giornaliere**
- Email immediata tecnico interessato

### Priorità MEDIA/BASSA (Monitoraggio)
- **Discrepanze dati tra fonti**
- **Anomalie orari standard**
- Report giornaliero aggregato

## ⚙️ Configurazione

### Email SMTP
```python
# config/settings.py
smtp_server = "smtp.gmail.com"
smtp_port = 587
username = "bait.control@gmail.com"  
password = "app_specific_password"
```

### Business Rules
```python
# models/business_rules.py
ORARIO_STANDARD = {
    "mattino": (9, 0) - (13, 0),
    "pomeriggio": (14, 0) - (18, 0), 
    "ore_giornaliere": 8.0
}

TECNICI_ATTIVI = [
    "Arlind Hoxha",
    "Davide Cestone", 
    "Gabriele De Palma"
]
```

## 📊 KPI Dashboard

### Metriche Tempo Reale
- **Anomalie Critiche**: Contatore alert immediati
- **Ore Lavorate**: Totale giornaliero vs target
- **Efficienza %**: Ore fatturabili / ore totali  
- **Sessioni TeamViewer**: Contatore sessioni remote

### Grafici Interattivi
- **Sovrapposizioni per Tecnico**: Bar chart anomalie
- **Ore Lavorate vs Fatturabili**: Confronto efficienza
- **Timeline Anomalie**: Distribuzione temporale

## 🔧 Troubleshooting

### Errori Comuni
```bash
# Errore encoding CSV
# Soluzione: Il sistema rileva automaticamente encoding

# File CSV non trovato
# Verifica: ls data/input/*.csv

# Dashboard non si avvia  
# Installa: pip install dash plotly

# Email non inviate
# Configura: SMTP username/password in config/settings.py
```

### Logging Dettagliato
```bash
# Debug completo
python3 main.py --mode daily --debug

# Log file location
tail -f logs/bait_control_YYYYMMDD.log
```

## 🧪 Test e Sviluppo

### Test Automatici
```bash
# Test con dati sintetici
python3 main.py --mode test

# Test singoli componenti
python3 -m pytest tests/

# Coverage report
python3 -m pytest --cov=agents tests/
```

### Modalità Debug
```bash
# Debug dettagliato
python3 main.py --mode daily --debug

# Test dashboard locale
python3 main.py --mode dashboard --debug --port 8050
```

## 🔒 Sicurezza

### Dati Sensibili
- ✅ Password SMTP in variabili ambiente
- ✅ Log senza informazioni personali  
- ✅ Template email senza dati sensibili
- ✅ Backup automatici con retention

### Accesso Dashboard
- 🌐 Dashboard locale (localhost only)
- 🔐 Per produzione: reverse proxy + autenticazione
- 📊 Dati tempo reale senza persistenza browser

## 📈 ROI Atteso

### Benefici Misurabili
- **Eliminazione fatturazioni doppie**: €X,XXX/anno
- **Riduzione tempo controlli**: 4h → 15min/giorno
- **Ottimizzazione risorse**: Visibilità real-time efficienza
- **Compliance**: Zero errori contrattuali

### Metriche Successo
- **Confidence Score >9/10**: Accuratezza rilevamento anomalie
- **Response Time <2h**: Correzione anomalie critiche  
- **Zero False Positive**: CRITICI confermati al 100%
- **Automazione >95%**: Controlli manuali ridotti

## 🚀 Deployment Produzione

### Server Requirements
- **OS**: Ubuntu 20.04+ / CentOS 8+ / Windows Server 2019+
- **Python**: 3.9+
- **RAM**: 2GB minimo, 4GB consigliato
- **Storage**: 10GB per logs/backup
- **Network**: SMTP outbound, HTTP dashboard

### Automazione Quotidiana
```bash
# Crontab entry (7:30 AM daily)
30 7 * * 1-5 /usr/bin/python3 /path/to/controlli/main.py --mode daily

# Systemd service per dashboard
sudo systemctl enable bait-dashboard
sudo systemctl start bait-dashboard
```

---

## 📞 Supporto

**Sviluppo**: Sistema automatizzato BAIT Service  
**Versione**: 1.0.0  
**Compatibilità**: Python 3.9+, Linux/Windows/macOS

### Contatti Tecnici
- **Admin Sistema**: admin@baitservice.com
- **Dashboard Live**: http://localhost:8050
- **Documentation**: README.md + CLAUDE.md

---

**🎯 OBIETTIVO RAGGIUNTO: Zero perdite di fatturato, massima efficienza operativa, controllo totale automatizzato!** 🚀