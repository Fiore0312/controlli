-- ================================================================================
-- FIX CARATTERI CORROTTI USANDO HEX - METODO AVANZATO
-- ================================================================================
-- 
-- Fix basato sui valori HEX reali trovati nel database
-- E2949CC3A1 = caratteri corrotti che dovrebbero essere C3A0 (à)
--
-- ================================================================================

USE bait_service_real;

-- Fix caratteri corrotti usando UNHEX/HEX per massima precisione
-- E2949CC3A1 → C3A0 (à corretto)

UPDATE alerts SET message = REPLACE(message, UNHEX('E2949CC3A1'), 'à') WHERE message LIKE BINARY '%' + UNHEX('E2949CC3A1') + '%';

-- Alternativamente, fix diretto con i caratteri visibili
UPDATE alerts SET message = REPLACE(message, '├á', 'à') WHERE message LIKE '%├á%';
UPDATE alerts SET message = REPLACE(message, 'attivit├á', 'attività') WHERE message LIKE '%attivit├á%';

-- Fix altri caratteri corrotti comuni  
UPDATE alerts SET message = REPLACE(message, '├┤', 'ì') WHERE message LIKE '%├┤%';
UPDATE alerts SET message = REPLACE(message, '├¿', 'ì') WHERE message LIKE '%├¿%';
UPDATE alerts SET message = REPLACE(message, '├¨', 'è') WHERE message LIKE '%├¨%';
UPDATE alerts SET message = REPLACE(message, '├┤', 'ì') WHERE message LIKE '%├┤%';
UPDATE alerts SET message = REPLACE(message, '├╣', 'ù') WHERE message LIKE '%├╣%';

-- Verifica risultato
SELECT 'Verifica Fix HEX:' as status;
SELECT id, message, HEX(message) as hex_after FROM alerts WHERE message LIKE '%attivit%';

-- Se ancora corrotto, ricreiamo completamente i dati demo
DELETE FROM alerts;

INSERT INTO alerts (alert_id, tecnico_id, cliente_id, severity, category, message, confidence_score, estimated_cost, details) VALUES
('DEMO_001', 1, 1, 'CRITICO', 'temporal_overlap', 'Sovrapposizione temporale critica rilevata - stesso cliente fatturato due volte', 95.0, 200.00, '{"overlap_minutes": 120, "same_client": true, "date": "2024-08-14"}'),
('DEMO_002', 2, 2, 'ALTO', 'travel_time', 'Tempo viaggio insufficiente tra clienti consecutivi', 80.0, 75.00, '{"travel_minutes": 10, "distance_km": 25, "realistic_time": 45}'),
('DEMO_003', 3, 3, 'MEDIO', 'duration_anomaly', 'Durata attività anomala rispetto alla media', 70.0, 50.00, '{"actual_duration": 540, "average_duration": 180, "variance": 200}'),
('DEMO_004', 1, 4, 'BASSO', 'missing_timesheet', 'Timbratura mancante per attività registrata', 60.0, 25.00, '{"activity_date": "2024-08-13", "missing_entry": "uscita"}'),
('DEMO_005', 2, 1, 'ALTO', 'teamviewer_mismatch', 'Sessione TeamViewer senza attività corrispondente', 85.0, 100.00, '{"session_duration": 90, "no_matching_activity": true}');

SELECT 'Alert ricreati con UTF-8 corretto!' as status;