-- ================================================================================
-- FIX CARATTERI CORROTTI NEL DATABASE BAIT SERVICE
-- ================================================================================
-- 
-- Script per correggere tutti i caratteri UTF-8 corrotti nel database
-- Problema: ├á invece di à, ├┤ invece di ì, etc.
--
-- ================================================================================

USE bait_service_real;

-- Fix alert messages con caratteri corrotti
UPDATE alerts SET message = REPLACE(message, 'attivit├á', 'attività') WHERE message LIKE '%attivit├á%';
UPDATE alerts SET message = REPLACE(message, 'attivit├┤', 'attività') WHERE message LIKE '%attivit├┤%';
UPDATE alerts SET message = REPLACE(message, '├á', 'à') WHERE message LIKE '%├á%';
UPDATE alerts SET message = REPLACE(message, '├┤', 'ì') WHERE message LIKE '%├┤%';
UPDATE alerts SET message = REPLACE(message, '├¿', 'ì') WHERE message LIKE '%├¿%';
UPDATE alerts SET message = REPLACE(message, '├', 'à') WHERE message LIKE '%├%';

-- Fix descrizioni attività
UPDATE attivita SET descrizione = REPLACE(descrizione, 'attivit├á', 'attività') WHERE descrizione LIKE '%attivit├á%';
UPDATE attivita SET descrizione = REPLACE(descrizione, 'attivit├┤', 'attività') WHERE descrizione LIKE '%attivit├┤%';
UPDATE attivita SET descrizione = REPLACE(descrizione, '├á', 'à') WHERE descrizione LIKE '%├á%';
UPDATE attivita SET descrizione = REPLACE(descrizione, '├┤', 'ì') WHERE descrizione LIKE '%├┤%';
UPDATE attivita SET descrizione = REPLACE(descrizione, '├¿', 'ì') WHERE descrizione LIKE '%├¿%';
UPDATE attivita SET descrizione = REPLACE(descrizione, '├', 'à') WHERE descrizione LIKE '%├%';

-- Fix tipo attività
UPDATE attivita SET tipo_attivita = REPLACE(tipo_attivita, '├á', 'à') WHERE tipo_attivita LIKE '%├á%';
UPDATE attivita SET tipo_attivita = REPLACE(tipo_attivita, '├┤', 'ì') WHERE tipo_attivita LIKE '%├┤%';
UPDATE attivita SET tipo_attivita = REPLACE(tipo_attivita, '├¿', 'ì') WHERE tipo_attivita LIKE '%├¿%';
UPDATE attivita SET tipo_attivita = REPLACE(tipo_attivita, '├', 'à') WHERE tipo_attivita LIKE '%├%';

-- Fix note interne
UPDATE attivita SET note_interne = REPLACE(note_interne, '├á', 'à') WHERE note_interne LIKE '%├á%';
UPDATE attivita SET note_interne = REPLACE(note_interne, '├┤', 'ì') WHERE note_interne LIKE '%├┤%';
UPDATE attivita SET note_interne = REPLACE(note_interne, '├¿', 'ì') WHERE note_interne LIKE '%├¿%';
UPDATE attivita SET note_interne = REPLACE(note_interne, '├', 'à') WHERE note_interne LIKE '%├%';

-- Fix teamviewer descriptions
UPDATE teamviewer_sessions SET descrizione = REPLACE(descrizione, '├á', 'à') WHERE descrizione LIKE '%├á%';
UPDATE teamviewer_sessions SET descrizione = REPLACE(descrizione, '├┤', 'ì') WHERE descrizione LIKE '%├┤%';
UPDATE teamviewer_sessions SET descrizione = REPLACE(descrizione, '├¿', 'ì') WHERE descrizione LIKE '%├¿%';
UPDATE teamviewer_sessions SET descrizione = REPLACE(descrizione, '├', 'à') WHERE descrizione LIKE '%├%';

-- Fix notes timbrature
UPDATE timbrature SET note = REPLACE(note, '├á', 'à') WHERE note LIKE '%├á%';
UPDATE timbrature SET note = REPLACE(note, '├┤', 'ì') WHERE note LIKE '%├┤%';
UPDATE timbrature SET note = REPLACE(note, '├¿', 'ì') WHERE note LIKE '%├¿%';
UPDATE timbrature SET note = REPLACE(note, '├', 'à') WHERE note LIKE '%├%';

-- Fix permessi notes
UPDATE permessi SET note = REPLACE(note, '├á', 'à') WHERE note LIKE '%├á%';
UPDATE permessi SET note = REPLACE(note, '├┤', 'ì') WHERE note LIKE '%├┤%';
UPDATE permessi SET note = REPLACE(note, '├¿', 'ì') WHERE note LIKE '%├¿%';
UPDATE permessi SET note = REPLACE(note, '├', 'à') WHERE note LIKE '%├%';

-- Fix clienti nomi
UPDATE clienti SET ragione_sociale = REPLACE(ragione_sociale, '├á', 'à') WHERE ragione_sociale LIKE '%├á%';
UPDATE clienti SET ragione_sociale = REPLACE(ragione_sociale, '├┤', 'ì') WHERE ragione_sociale LIKE '%├┤%';
UPDATE clienti SET ragione_sociale = REPLACE(ragione_sociale, '├¿', 'ì') WHERE ragione_sociale LIKE '%├¿%';
UPDATE clienti SET ragione_sociale = REPLACE(ragione_sociale, '├', 'à') WHERE ragione_sociale LIKE '%├%';

-- Fix utilizzi auto destinazione
UPDATE utilizzi_auto SET destinazione = REPLACE(destinazione, '├á', 'à') WHERE destinazione LIKE '%├á%';
UPDATE utilizzi_auto SET destinazione = REPLACE(destinazione, '├┤', 'ì') WHERE destinazione LIKE '%├┤%';
UPDATE utilizzi_auto SET destinazione = REPLACE(destinazione, '├¿', 'ì') WHERE destinazione LIKE '%├¿%';
UPDATE utilizzi_auto SET destinazione = REPLACE(destinazione, '├', 'à') WHERE destinazione LIKE '%├%';

-- Verifica risultati
SELECT 'Verifica Alert Messages:' as check_type;
SELECT id, message FROM alerts WHERE message LIKE '%├%' OR message LIKE '%attivit%';

SELECT 'Verifica Attivita Descrizioni:' as check_type;  
SELECT id, descrizione FROM attivita WHERE descrizione LIKE '%├%' OR descrizione LIKE '%attivit%' LIMIT 5;

SELECT 'Fix completato! Caratteri UTF-8 corretti.' as status;