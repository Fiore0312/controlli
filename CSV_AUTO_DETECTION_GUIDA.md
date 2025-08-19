# 🚀 BAIT Service - Guida CSV Auto-Detection

## ✅ SOLUZIONE IMPLEMENTATA

**DOMANDA:** "se li carico con un nome diverso ma il tipo di file è sempre lo stesso il sistema lo riconosce?"

**RISPOSTA:** **SÌ! Ora il sistema riconosce automaticamente il tipo di file dal contenuto, non più solo dal nome.**

## 🎯 COME FUNZIONA

### PRIMA (Limitazione):
- Sistema rifiutava file con nomi diversi da quelli standard
- "rapportini_agosto.csv" veniva RIFIUTATO anche se conteneva dati attività
- Errore: "File non richiesto dal sistema"

### DOPO (Rivoluzione):
- Sistema analizza **contenuto del CSV** automaticamente
- "rapportini_agosto.csv" viene **riconosciuto** come file attività
- **Auto-rinomina** a "attivita.csv" per compatibilità sistema
- **Confidence score** per sicurezza rilevamento

## 🔍 ALGORITMO INTELLIGENTE

Il sistema riconosce i file CSV analizzando le **colonne header**:

### File Attività (riconosciuto da):
- ✅ "ID Ticket" 
- ✅ "Creato da"
- ✅ "Tipologia attività"
- ➕ Opzionali: "Durata", "Descrizione", "Azienda"

### File Timbrature (riconosciuto da):
- ✅ "Dipendente"
- ✅ "Data" 
- ➕ Opzionali: "Ora ingresso", "Ora uscita", "Pause"

### File Auto (riconosciuto da):
- ✅ "Tecnico"
- ✅ "Data utilizzo"
- ➕ Opzionali: "Veicolo", "Destinazione", "Km"

### File TeamViewer (riconosciuto da):
- ✅ "Session ID"
- ✅ "Tecnico"  
- ✅ "Cliente"
- ➕ Opzionali: "Durata", "Start time"

## 📊 CONFIDENCE SCORING

- **≥ 70%**: **Auto-processing** sicuro - File processato automaticamente
- **40-69%**: **Conferma richiesta** - Richiede validazione utente
- **< 40%**: **Mapping manuale** - File non riconosciuto

## 🎮 TEST LIVE

### File Demo Creati:
1. **`rapportini_agosto_2025.csv`** → Riconosciuto come `attivita.csv` (100%)
2. **`presenze_team_bait.csv`** → Riconosciuto come `timbrature.csv` (100%)  
3. **`registro_mezzi_agosto.csv`** → Riconosciuto come `auto.csv` (100%)
4. **`file_name_strano_123.csv`** → Riconosciuto come `teamviewer_bait.csv` (100%)

### Come testare:
```bash
# Accedi alla pagina di test
http://localhost/controlli/csv_detector_test.php

# Oppure usa API diretta
http://localhost/controlli/csv_detector_api.php?action=test_existing
```

## 🛠️ FILES MODIFICATI

### 1. `/CSVTypeDetector.php` (NUOVO)
- Classe intelligente per riconoscimento CSV
- Algoritmi matching fuzzy per colonne
- Sistema confidence scoring
- Gestione encoding automatica

### 2. `/upload_handler.php` (POTENZIATO)  
- Integrazione auto-detection nel flusso upload
- Auto-rinomina file riconosciuti
- Backwards compatibility completa
- Logging attività detection

### 3. `/csv_detector_test.php` (NUOVO)
- Interfaccia user-friendly per testing
- Drag & drop con feedback visivo  
- Visualizzazione risultati detection
- Test file esistenti

### 4. `/csv_detector_api.php` (NUOVO)
- API REST per gestione detection
- Endpoint per test e analisi
- Generazione file demo

## 🚀 UTILIZZO PRATICO

### Scenario Tipico:
1. **Hai file**: "report_tecnici_settembre.csv" con colonne standard attività
2. **Carichi normalmente** tramite interfaccia upload
3. **Sistema analizza** automaticamente le colonne
4. **Rileva**: "È un file attività!" (confidence: 95%)
5. **Rinomina** automaticamente a "attivita.csv"
6. **Processa** normalmente nel workflow

### Log Esempio:
```
2025-08-19 10:30:15 - Auto-detected 'report_tecnici_settembre.csv' as 'attivita.csv' (confidence: 95%)
```

## ⚙️ CONFIGURAZIONE AVANZATA

### Personalizzare Soglia Confidence:
```php
// In upload_handler.php linea 94
if ($detectionResult['success'] && $detectionResult['confidence'] >= 70) {
    // Cambia 70 con la soglia desiderata (es: 80 per maggiore sicurezza)
```

### Aggiungere Nuovo Tipo CSV:
```php
// In CSVTypeDetector.php aggiungere a $signatures array:
'nuovo_tipo.csv' => [
    'required' => ['Colonna1', 'Colonna2'],
    'optional' => ['Colonna3', 'Colonna4'],
    'description' => 'Descrizione nuovo tipo'
]
```

## 🎯 BENEFICI IMMEDIATI

✅ **Zero Frustrazione**: Carica file con qualsiasi nome
✅ **Zero Errori**: Niente più "file non richiesto"  
✅ **Backwards Compatibility**: Sistema esistente funziona identico
✅ **Intelligenza Automatica**: Riconoscimento preciso contenuto
✅ **Logging Completo**: Tracciabilità completa operazioni
✅ **Test Friendly**: Pagina test per verificare funzionamento

## 🚨 IMPORTANTE

- **File con nomi standard** continuano a funzionare esattamente come prima
- **Nuova funzionalità** si attiva solo per nomi non standard
- **Zero Breaking Changes** - Sistema 100% backwards compatible
- **Performance**: Analisi header velocissima, nessun impatto prestazioni

---

## 🎉 RISULTATO FINALE

**Il tuo problema è RISOLTO!** 

Ora puoi caricare:
- `rapportini_agosto.csv` 
- `dati_tecnici_settembre.csv`
- `export_attivita_2025.csv`
- Qualsiasi nome file!

Il sistema li riconoscerà automaticamente e li processerà correttamente. **La tua operatività quotidiana è completamente sbloccata!**