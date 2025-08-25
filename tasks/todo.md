# Task Plan: Integrazione Sistema LLM Gratuito BAIT Service Enterprise

## OBIETTIVO
Integrare un sistema LLM gratuito nel progetto BAIT Service Enterprise utilizzando OpenRouter API con il modello "z-ai/glm-4.5-air:free" per fornire un sistema di chat intelligente per interrogare i file del progetto.

## ANALISI PRELIMINARE
✅ **Completato** - Analizzato il progetto esistente:
- Sistema BAIT Service con design system CSS professionale
- Database MySQL (bait_service_real) 
- Framework PHP standalone + Bootstrap 5
- Tabelle: alert_dettagliati, audit_alerts, tecnici, aziende_reali
- File CSV: attività, timbrature, calendario, auto, teamviewer
- Design coerente con gradient blue (#667eea to #764ba2)

## TASK LIST

### 1. Database Setup per Sistema LLM
- [ ] Creare tabelle per chat history e file indexing
- [ ] Aggiungere tabella per API key management sicuro
- [ ] Implementare struttura per context building
- [ ] Setup indici per performance ottimali

### 2. API Integration Layer
- [ ] Creare classe OpenRouterClient.php per comunicazione API
- [ ] Implementare rate limiting e error handling
- [ ] Sistema di fallback per resilienza
- [ ] Caching intelligente delle risposte
- [ ] Encryption sicura delle API keys

### 3. File Analysis Engine
- [ ] Sviluppare FileAnalyzer.php per indicizzazione file
- [ ] Sistema di analisi CSV automatico
- [ ] Estrazione metadata da file di progetto
- [ ] Context builder per query specifiche
- [ ] Indicizzazione contenuti database

### 4. Chat Interface UI/UX
- [ ] Design interfaccia chat professionale coerente BAIT
- [ ] Real-time typing indicators
- [ ] History conversazioni persistente
- [ ] UI responsive per tutti i dispositivi
- [ ] Integration con existing dashboard

### 5. Configuration Panel
- [ ] Pannello sicuro per gestione API keys
- [ ] Settings per personalizzazione sistema
- [ ] Monitoring usage e costs
- [ ] Backup/restore configurazioni
- [ ] Admin panel per gestione avanzata

### 6. Business Intelligence Integration
- [ ] Query predefinite per analisi business
- [ ] Integration con tabelle esistenti BAIT
- [ ] Sistema di suggerimenti intelligenti
- [ ] Report automatici tramite AI
- [ ] Dashboard AI-powered insights

### 7. Security & Performance
- [ ] Implementare validation sicura input utente
- [ ] Rate limiting per prevenire abuse
- [ ] Logging completo per audit
- [ ] Performance monitoring
- [ ] Backup system per continuità

### 8. Testing & Documentation
- [ ] Unit tests per tutti i componenti
- [ ] Integration tests con API OpenRouter
- [ ] Performance tests under load
- [ ] User acceptance testing
- [ ] Documentazione tecnica completa

## DELIVERABLE FINALI
1. **OpenRouterClient.php** - API integration robusta
2. **FileAnalyzer.php** - Engine di analisi file intelligente  
3. **Chat Interface** - UI professionale integrata
4. **Configuration Panel** - Gestione sicura configurazioni
5. **Database Schema** - Strutture ottimizzate per LLM
6. **Documentation** - Setup e usage completi

## PRIORITÀ IMPLEMENTAZIONE
1. **FASE 1** (Settimana 1): Database setup + API integration base
2. **FASE 2** (Settimana 2): File analyzer + basic chat interface
3. **FASE 3** (Settimana 3): Advanced features + security
4. **FASE 4** (Settimana 4): Testing + optimization + documentation

## ESEMPI QUERY ATTESE
- "Mostra gli alert critici dell'ultima settimana"
- "Analizza le timbrature di Alex Ferrario del 18/08" 
- "Quali sono le anomalie più frequenti nei dati TeamViewer?"
- "Confronta l'attività di Davide vs Gabriele questo mese"

## REQUISITI TECNICI
- **Model**: z-ai/glm-4.5-air:free (completamente gratuito)
- **API**: OpenRouter (https://openrouter.ai/)
- **Security**: Encryption API keys, input validation
- **Performance**: Caching, rate limiting, optimization
- **Design**: Coerente con sistema BAIT esistente
- **ROI**: Focus su business intelligence immediata

---

# AGGIORNAMENTO SISTEMA - UNIFICAZIONE DATA SOURCES COMPLETATA ✅

## Problema Risolto (2025-08-25)
**Identificata e risolta inconsistenza nelle sorgenti dati**: Alcuni file dashboard leggevano da `data/input/` mentre altri da `upload_csv/`, causando confusione utenti e possibili inconsistenze dati.

### Files Unificati (8/8 completati):
✅ calendario.php → upload_csv/calendario.csv  
✅ audit_monthly_manager.php → upload_csv/  
✅ bait_incongruenze_manager.php → upload_csv/  
✅ timbrature.php → upload_csv/timbrature.csv  
✅ utilizzo_auto.php → upload_csv/auto.csv  
✅ richieste_permessi.php → upload_csv/permessi.csv  
✅ attivita_deepser.php → upload_csv/attivita.csv  
✅ sessioni_teamviewer.php → upload_csv/teamviewer_*.csv  

### Risultato Test Automatico:
**Status**: ✅ SISTEMA UNIFICATO COMPLETAMENTE  
**Test**: `test_unificazione_completa.php`  
**Confidence**: 10/10 - Nessun file principale usa ancora data/input/

### Benefici Immediati:
- **Coerenza**: Tutti i dashboard usano upload_csv/ come sorgente unica
- **UX migliorata**: Comportamento uniforme caricamento CSV
- **Manutenzione**: Punto singolo gestione dati
- **AI Chat**: Già allineato con sistema unificato

---

**STATUS**: Sistema LLM completato + Unificazione data sources completata ✅
**LAST UPDATE**: 2025-08-25  
**BUSINESS VALUE**: Alto - Sistema AI + Coerenza dati ottimizzati