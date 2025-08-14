-- =====================================================
-- BAIT SERVICE STORED PROCEDURES & BUSINESS LOGIC
-- Database: bait_service_real  
-- Created: 2025-08-12
-- Purpose: Production stored procedures for overlap detection and business rules
-- =====================================================

USE bait_service_real;

DELIMITER //

-- =====================================================
-- 1. OVERLAP DETECTION PROCEDURES
-- =====================================================

-- Procedura principale per rilevamento sovrapposizioni
DROP PROCEDURE IF EXISTS DetectAllOverlaps//
CREATE PROCEDURE DetectAllOverlaps(
    IN target_date DATE DEFAULT NULL
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_tecnico_id INT;
    DECLARE v_data_analisi DATE;
    
    -- Se non specificata la data, usa oggi
    SET v_data_analisi = COALESCE(target_date, CURDATE());
    
    -- Cursor per tutti i tecnici attivi
    DECLARE tecnico_cursor CURSOR FOR 
        SELECT id FROM tecnici WHERE attivo = TRUE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Log inizio analisi
    INSERT INTO log_attivita_sistema (azione, dettagli, utente) 
    VALUES ('overlap_detection_start', JSON_OBJECT('data_analisi', v_data_analisi), 'system');
    
    -- Pulisci sovrapposizioni esistenti per la data
    DELETE FROM sovrapposizioni_rilevate WHERE data_conflitto = v_data_analisi;
    
    OPEN tecnico_cursor;
    
    read_loop: LOOP
        FETCH tecnico_cursor INTO v_tecnico_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Rileva sovrapposizioni per questo tecnico
        CALL DetectTechnicianOverlaps(v_tecnico_id, v_data_analisi);
        
    END LOOP;
    
    CLOSE tecnico_cursor;
    
    -- Log fine analisi
    INSERT INTO log_attivita_sistema (azione, dettagli, utente) 
    VALUES ('overlap_detection_complete', 
            JSON_OBJECT('data_analisi', v_data_analisi, 
                       'sovrapposizioni_trovate', (SELECT COUNT(*) FROM sovrapposizioni_rilevate WHERE data_conflitto = v_data_analisi)), 
            'system');
            
END//

-- Rilevamento sovrapposizioni per singolo tecnico
DROP PROCEDURE IF EXISTS DetectTechnicianOverlaps//
CREATE PROCEDURE DetectTechnicianOverlaps(
    IN tecnico_id INT,
    IN target_date DATE
)
BEGIN
    -- 1. Sovrapposizioni tra timbrature (stesso cliente = CRITICO)
    INSERT INTO sovrapposizioni_rilevate (
        tecnico_id, data_conflitto, tipo_conflitto, gravita,
        timbratura_1_id, timbratura_2_id, descrizione_conflitto, 
        ore_sovrapposte, fatturazione_impatto, stato_risoluzione
    )
    SELECT 
        tecnico_id,
        target_date,
        CASE 
            WHEN t1.cliente_id = t2.cliente_id THEN 'stesso_cliente'
            ELSE 'clienti_diversi'
        END as tipo_conflitto,
        CASE 
            WHEN t1.cliente_id = t2.cliente_id THEN 'critica'
            ELSE 'alta'
        END as gravita,
        t1.id as timbratura_1_id,
        t2.id as timbratura_2_id,
        CONCAT('Sovrapposizione timbrature: ', 
               (SELECT nome FROM clienti WHERE id = t1.cliente_id), ' vs ',
               (SELECT nome FROM clienti WHERE id = t2.cliente_id),
               ' dalle ', TIME(t1.ora_inizio), ' alle ', TIME(LEAST(t1.ora_fine, t2.ora_fine))) as descrizione_conflitto,
        ROUND(
            TIMESTAMPDIFF(MINUTE, 
                GREATEST(t1.ora_inizio, t2.ora_inizio),
                LEAST(COALESCE(t1.ora_fine, NOW()), COALESCE(t2.ora_fine, NOW()))
            ) / 60.0, 2
        ) as ore_sovrapposte,
        CASE 
            WHEN t1.cliente_id = t2.cliente_id THEN 'doppia_fatturazione'
            ELSE 'perdita_ricavi'
        END as fatturazione_impatto,
        'nuovo' as stato_risoluzione
    FROM timbrature t1
    JOIN timbrature t2 ON t1.tecnico_id = t2.tecnico_id 
        AND t1.id < t2.id  -- Evita duplicati
        AND DATE(t1.ora_inizio) = target_date
        AND DATE(t2.ora_inizio) = target_date
    WHERE t1.tecnico_id = tecnico_id
    AND t1.stato_timbratura = 'completata'
    AND t2.stato_timbratura = 'completata'
    AND (
        -- Controllo sovrapposizione temporale
        (t1.ora_inizio < COALESCE(t2.ora_fine, NOW()) AND COALESCE(t1.ora_fine, NOW()) > t2.ora_inizio)
    )
    AND TIMESTAMPDIFF(MINUTE, 
        GREATEST(t1.ora_inizio, t2.ora_inizio),
        LEAST(COALESCE(t1.ora_fine, NOW()), COALESCE(t2.ora_fine, NOW()))
    ) >= (SELECT CAST(valore AS UNSIGNED) FROM configurazioni_sistema WHERE chiave = 'soglia_sovrapposizione_minuti');

    -- 2. Sovrapposizioni timbrature vs TeamViewer
    INSERT INTO sovrapposizioni_rilevate (
        tecnico_id, data_conflitto, tipo_conflitto, gravita,
        timbratura_1_id, teamviewer_id, descrizione_conflitto, 
        ore_sovrapposte, fatturazione_impatto, stato_risoluzione
    )
    SELECT 
        tecnico_id,
        target_date,
        'timbratura_teamviewer' as tipo_conflitto,
        'media' as gravita,
        t.id as timbratura_1_id,
        tv.id as teamviewer_id,
        CONCAT('Sovrapposizione timbratura-TeamViewer: ', 
               (SELECT nome FROM clienti WHERE id = t.cliente_id), ' vs ',
               (SELECT nome FROM clienti WHERE id = tv.cliente_id),
               ' dalle ', TIME(t.ora_inizio), ' alle ', TIME(LEAST(COALESCE(t.ora_fine, NOW()), COALESCE(tv.fine, NOW())))) as descrizione_conflitto,
        ROUND(
            TIMESTAMPDIFF(MINUTE, 
                GREATEST(t.ora_inizio, tv.inizio),
                LEAST(COALESCE(t.ora_fine, NOW()), COALESCE(tv.fine, NOW()))
            ) / 60.0, 2
        ) as ore_sovrapposte,
        'nessun_impatto' as fatturazione_impatto,
        'nuovo' as stato_risoluzione
    FROM timbrature t
    JOIN teamviewer_sessioni tv ON t.tecnico_id = tv.tecnico_id 
        AND DATE(t.ora_inizio) = target_date
        AND DATE(tv.inizio) = target_date
    WHERE t.tecnico_id = tecnico_id
    AND t.stato_timbratura = 'completata'
    AND tv.stato = 'completata'
    AND (
        -- Controllo sovrapposizione temporale
        (t.ora_inizio < COALESCE(tv.fine, NOW()) AND COALESCE(t.ora_fine, NOW()) > tv.inizio)
    )
    AND TIMESTAMPDIFF(MINUTE, 
        GREATEST(t.ora_inizio, tv.inizio),
        LEAST(COALESCE(t.ora_fine, NOW()), COALESCE(tv.fine, NOW()))
    ) >= (SELECT CAST(valore AS UNSIGNED) FROM configurazioni_sistema WHERE chiave = 'soglia_sovrapposizione_minuti');

    -- 3. Sovrapposizioni timbrature vs utilizzo auto
    INSERT INTO sovrapposizioni_rilevate (
        tecnico_id, data_conflitto, tipo_conflitto, gravita,
        timbratura_1_id, auto_id, descrizione_conflitto, 
        ore_sovrapposte, fatturazione_impatto, stato_risoluzione
    )
    SELECT 
        tecnico_id,
        target_date,
        'timbratura_auto' as tipo_conflitto,
        'bassa' as gravita,
        t.id as timbratura_1_id,
        ua.id as auto_id,
        CONCAT('Timbratura durante utilizzo auto: ', 
               (SELECT nome FROM clienti WHERE id = t.cliente_id), ' con auto ',
               (SELECT CONCAT(marca, ' ', modello) FROM auto_aziendali WHERE id = ua.auto_id),
               ' dalle ', TIME(t.ora_inizio), ' alle ', TIME(LEAST(COALESCE(t.ora_fine, NOW()), COALESCE(ua.ora_riconsegna, NOW())))) as descrizione_conflitto,
        ROUND(
            TIMESTAMPDIFF(MINUTE, 
                GREATEST(t.ora_inizio, ua.ora_presa),
                LEAST(COALESCE(t.ora_fine, NOW()), COALESCE(ua.ora_riconsegna, NOW()))
            ) / 60.0, 2
        ) as ore_sovrapposte,
        'nessun_impatto' as fatturazione_impatto,
        'nuovo' as stato_risoluzione
    FROM timbrature t
    JOIN utilizzo_auto ua ON t.tecnico_id = ua.tecnico_id 
        AND DATE(t.ora_inizio) = target_date
        AND DATE(ua.ora_presa) = target_date
    WHERE t.tecnico_id = tecnico_id
    AND t.stato_timbratura = 'completata'
    AND (
        -- Controllo sovrapposizione temporale
        (t.ora_inizio < COALESCE(ua.ora_riconsegna, NOW()) AND COALESCE(t.ora_fine, NOW()) > ua.ora_presa)
    );

END//

-- =====================================================
-- 2. BUSINESS RULES VALIDATION
-- =====================================================

-- Validazione orari di lavoro standard
DROP PROCEDURE IF EXISTS ValidateWorkingHours//
CREATE PROCEDURE ValidateWorkingHours(
    IN tecnico_id INT,
    IN target_date DATE
)
BEGIN
    DECLARE std_inizio_mattino TIME;
    DECLARE std_fine_mattino TIME;
    DECLARE std_inizio_pomeriggio TIME;
    DECLARE std_fine_pomeriggio TIME;
    DECLARE ore_standard DECIMAL(4,2);
    
    -- Carica configurazioni orari standard
    SELECT 
        (SELECT valore FROM configurazioni_sistema WHERE chiave = 'orario_standard_inizio_mattino'),
        (SELECT valore FROM configurazioni_sistema WHERE chiave = 'orario_standard_fine_mattino'),
        (SELECT valore FROM configurazioni_sistema WHERE chiave = 'orario_standard_inizio_pomeriggio'),
        (SELECT valore FROM configurazioni_sistema WHERE chiave = 'orario_standard_fine_pomeriggio'),
        (SELECT CAST(valore AS DECIMAL(4,2)) FROM configurazioni_sistema WHERE chiave = 'ore_lavorative_giornaliere_standard')
    INTO std_inizio_mattino, std_fine_mattino, std_inizio_pomeriggio, std_fine_pomeriggio, ore_standard;
    
    -- Verifica orari anomali (troppo presto/tardi)
    INSERT INTO alert_notifiche (
        tipo, gravita, tecnico_id, titolo, messaggio, timbratura_id, stato, priorita
    )
    SELECT 
        'orario_anomalo' as tipo,
        CASE 
            WHEN TIME(ora_inizio) < '07:00' OR TIME(COALESCE(ora_fine, NOW())) > '20:00' THEN 'alta'
            WHEN TIME(ora_inizio) < std_inizio_mattino OR TIME(COALESCE(ora_fine, NOW())) > std_fine_pomeriggio THEN 'media'
            ELSE 'bassa'
        END as gravita,
        tecnico_id,
        CONCAT('Orario anomalo - ', (SELECT nome_completo FROM tecnici WHERE id = tecnico_id)) as titolo,
        CONCAT('Timbratura con orario anomalo: inizio ', TIME(ora_inizio), 
               CASE WHEN ora_fine IS NOT NULL THEN CONCAT(', fine ', TIME(ora_fine)) ELSE ' (in corso)' END,
               ' per cliente ', (SELECT nome FROM clienti WHERE id = t.cliente_id)) as messaggio,
        t.id as timbratura_id,
        'nuovo' as stato,
        CASE 
            WHEN TIME(ora_inizio) < '07:00' OR TIME(COALESCE(ora_fine, NOW())) > '20:00' THEN 2
            ELSE 4
        END as priorita
    FROM timbrature t
    WHERE t.tecnico_id = tecnico_id 
    AND t.data_timbratura = target_date
    AND t.stato_timbratura = 'completata'
    AND (
        TIME(ora_inizio) < '07:00' 
        OR TIME(COALESCE(ora_fine, NOW())) > '20:00'
        OR TIME(ora_inizio) < std_inizio_mattino
        OR TIME(COALESCE(ora_fine, NOW())) > std_fine_pomeriggio
    );
    
    -- Verifica ore totali eccessive
    INSERT INTO alert_notifiche (
        tipo, gravita, tecnico_id, titolo, messaggio, stato, priorita
    )
    SELECT 
        'orario_anomalo' as tipo,
        CASE 
            WHEN ore_totali > 12 THEN 'critica'
            WHEN ore_totali > 10 THEN 'alta'
            ELSE 'media'
        END as gravita,
        tecnico_id,
        CONCAT('Ore eccessive - ', (SELECT nome_completo FROM tecnici WHERE id = tecnico_id)) as titolo,
        CONCAT('Totale ore lavorate: ', ROUND(ore_totali, 2), 'h (soglia standard: ', ore_standard, 'h)') as messaggio,
        'nuovo' as stato,
        CASE 
            WHEN ore_totali > 12 THEN 1
            WHEN ore_totali > 10 THEN 2
            ELSE 3
        END as priorita
    FROM (
        SELECT 
            tecnico_id,
            SUM(COALESCE(ore_nette_pause, 0)) as ore_totali
        FROM timbrature 
        WHERE tecnico_id = tecnico_id 
        AND data_timbratura = target_date
        AND stato_timbratura = 'completata'
        GROUP BY tecnico_id
    ) ore_summary
    WHERE ore_totali > ore_standard;

END//

-- =====================================================
-- 3. KPI CALCULATION PROCEDURES
-- =====================================================

-- Calcolo KPI giornalieri per tecnico
DROP PROCEDURE IF EXISTS CalculateKPIForTechnician//
CREATE PROCEDURE CalculateKPIForTechnician(
    IN tecnico_id INT,
    IN target_date DATE
)
BEGIN
    DECLARE ore_timbrature DECIMAL(5,2) DEFAULT 0;
    DECLARE ore_teamviewer DECIMAL(5,2) DEFAULT 0;
    DECLARE ore_trasferta DECIMAL(5,2) DEFAULT 0;
    DECLARE clienti_serviti INT DEFAULT 0;
    DECLARE sovrapposizioni_count INT DEFAULT 0;
    DECLARE ore_standard DECIMAL(4,2);
    
    -- Carica ore standard
    SELECT CAST(valore AS DECIMAL(4,2)) 
    INTO ore_standard
    FROM configurazioni_sistema 
    WHERE chiave = 'ore_lavorative_giornaliere_standard';
    
    -- Calcola ore timbrature
    SELECT COALESCE(SUM(ore_nette_pause), 0), COUNT(DISTINCT cliente_id)
    INTO ore_timbrature, clienti_serviti
    FROM timbrature 
    WHERE tecnico_id = tecnico_id 
    AND data_timbratura = target_date
    AND stato_timbratura = 'completata';
    
    -- Calcola ore TeamViewer
    SELECT COALESCE(SUM(durata_ore), 0)
    INTO ore_teamviewer
    FROM teamviewer_sessioni 
    WHERE tecnico_id = tecnico_id 
    AND DATE(inizio) = target_date
    AND stato = 'completata';
    
    -- Calcola ore trasferta (utilizzo auto)
    SELECT COALESCE(SUM(
        TIMESTAMPDIFF(MINUTE, ora_presa, COALESCE(ora_riconsegna, NOW())) / 60.0
    ), 0)
    INTO ore_trasferta
    FROM utilizzo_auto 
    WHERE tecnico_id = tecnico_id 
    AND data_utilizzo = target_date;
    
    -- Conta sovrapposizioni
    SELECT COUNT(*)
    INTO sovrapposizioni_count
    FROM sovrapposizioni_rilevate 
    WHERE tecnico_id = tecnico_id 
    AND data_conflitto = target_date
    AND stato_risoluzione IN ('nuovo', 'in_verifica');
    
    -- Insert o update KPI
    INSERT INTO kpi_giornalieri (
        data_calcolo, tecnico_id, ore_lavorate, ore_fatturabili, ore_teamviewer, ore_trasferta,
        percentuale_produttivita, numero_clienti_serviti, numero_sovrapposizioni,
        tempo_medio_per_cliente
    )
    VALUES (
        target_date,
        tecnico_id,
        ore_timbrature,
        ore_timbrature,  -- Assumiamo tutte fatturabili per ora
        ore_teamviewer,
        ore_trasferta,
        CASE WHEN ore_standard > 0 THEN ROUND((ore_timbrature / ore_standard) * 100, 2) ELSE 0 END,
        clienti_serviti,
        sovrapposizioni_count,
        CASE WHEN clienti_serviti > 0 THEN ROUND(ore_timbrature / clienti_serviti, 2) ELSE 0 END
    )
    ON DUPLICATE KEY UPDATE
        ore_lavorate = VALUES(ore_lavorate),
        ore_fatturabili = VALUES(ore_fatturabili),
        ore_teamviewer = VALUES(ore_teamviewer),
        ore_trasferta = VALUES(ore_trasferta),
        percentuale_produttivita = VALUES(percentuale_produttivita),
        numero_clienti_serviti = VALUES(numero_clienti_serviti),
        numero_sovrapposizioni = VALUES(numero_sovrapposizioni),
        tempo_medio_per_cliente = VALUES(tempo_medio_per_cliente),
        calculated_at = CURRENT_TIMESTAMP;

END//

-- =====================================================
-- 4. DATA QUALITY PROCEDURES
-- =====================================================

-- Pulizia e normalizzazione dati
DROP PROCEDURE IF EXISTS CleanAndNormalizeData//
CREATE PROCEDURE CleanAndNormalizeData()
BEGIN
    -- Normalizza nomi clienti
    UPDATE clienti 
    SET nome = TRIM(nome),
        nome_normalizzato = UPPER(TRIM(nome))
    WHERE nome != TRIM(nome) OR nome_normalizzato != UPPER(TRIM(nome));
    
    -- Normalizza nomi tecnici
    UPDATE tecnici 
    SET nome = TRIM(nome),
        cognome = TRIM(cognome)
    WHERE nome != TRIM(nome) OR cognome != TRIM(cognome);
    
    -- Fix timbrature con ore negative o nulle
    UPDATE timbrature 
    SET ore_nette_pause = 0
    WHERE ore_nette_pause < 0 OR ore_nette_pause IS NULL;
    
    -- Fix durate TeamViewer
    UPDATE teamviewer_sessioni 
    SET durata_ore = TIMESTAMPDIFF(MINUTE, inizio, COALESCE(fine, NOW())) / 60.0
    WHERE durata_ore IS NULL OR durata_ore <= 0;
    
    INSERT INTO log_attivita_sistema (azione, dettagli, utente) 
    VALUES ('data_cleanup', JSON_OBJECT('timestamp', NOW()), 'system');

END//

-- =====================================================
-- 5. REPORTING PROCEDURES
-- =====================================================

-- Report sovrapposizioni per periodo
DROP PROCEDURE IF EXISTS GetOverlapReport//
CREATE PROCEDURE GetOverlapReport(
    IN start_date DATE,
    IN end_date DATE,
    IN tecnico_filter INT DEFAULT NULL
)
BEGIN
    SELECT 
        sr.data_conflitto,
        t.nome_completo as tecnico,
        sr.tipo_conflitto,
        sr.gravita,
        sr.descrizione_conflitto,
        sr.ore_sovrapposte,
        sr.fatturazione_impatto,
        sr.stato_risoluzione,
        sr.created_at as rilevato_il
    FROM sovrapposizioni_rilevate sr
    JOIN tecnici t ON sr.tecnico_id = t.id
    WHERE sr.data_conflitto BETWEEN start_date AND end_date
    AND (tecnico_filter IS NULL OR sr.tecnico_id = tecnico_filter)
    ORDER BY sr.data_conflitto DESC, sr.gravita DESC, sr.created_at DESC;
END//

-- Report KPI aggregati per periodo
DROP PROCEDURE IF EXISTS GetKPIReport//
CREATE PROCEDURE GetKPIReport(
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT 
        t.nome_completo as tecnico,
        COUNT(DISTINCT k.data_calcolo) as giorni_lavorati,
        ROUND(AVG(k.ore_lavorate), 2) as media_ore_giornaliere,
        ROUND(SUM(k.ore_lavorate), 2) as totale_ore_periodo,
        ROUND(AVG(k.percentuale_produttivita), 2) as media_produttivita,
        SUM(k.numero_clienti_serviti) as totale_clienti_serviti,
        SUM(k.numero_sovrapposizioni) as totale_sovrapposizioni,
        ROUND(AVG(k.tempo_medio_per_cliente), 2) as tempo_medio_cliente
    FROM kpi_giornalieri k
    JOIN tecnici t ON k.tecnico_id = t.id
    WHERE k.data_calcolo BETWEEN start_date AND end_date
    GROUP BY k.tecnico_id, t.nome_completo
    ORDER BY totale_ore_periodo DESC;
END//

-- =====================================================
-- 6. AUTOMATED JOBS PROCEDURES
-- =====================================================

-- Job giornaliero completo
DROP PROCEDURE IF EXISTS DailyProcessingJob//
CREATE PROCEDURE DailyProcessingJob(
    IN target_date DATE DEFAULT NULL
)
BEGIN
    DECLARE v_data_target DATE;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_tecnico_id INT;
    
    SET v_data_target = COALESCE(target_date, CURDATE());
    
    DECLARE tecnico_cursor CURSOR FOR 
        SELECT id FROM tecnici WHERE attivo = TRUE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Log inizio job
    INSERT INTO log_attivita_sistema (azione, dettagli, utente) 
    VALUES ('daily_job_start', JSON_OBJECT('data_target', v_data_target), 'system');
    
    -- 1. Pulizia dati
    CALL CleanAndNormalizeData();
    
    -- 2. Rilevamento sovrapposizioni
    CALL DetectAllOverlaps(v_data_target);
    
    -- 3. Calcolo KPI per ogni tecnico
    OPEN tecnico_cursor;
    
    kpi_loop: LOOP
        FETCH tecnico_cursor INTO v_tecnico_id;
        IF done THEN
            LEAVE kpi_loop;
        END IF;
        
        CALL CalculateKPIForTechnician(v_tecnico_id, v_data_target);
        CALL ValidateWorkingHours(v_tecnico_id, v_data_target);
        
    END LOOP;
    
    CLOSE tecnico_cursor;
    
    -- Log fine job
    INSERT INTO log_attivita_sistema (azione, dettagli, utente) 
    VALUES ('daily_job_complete', 
            JSON_OBJECT('data_target', v_data_target,
                       'sovrapposizioni', (SELECT COUNT(*) FROM sovrapposizioni_rilevate WHERE data_conflitto = v_data_target),
                       'alert', (SELECT COUNT(*) FROM alert_notifiche WHERE DATE(created_at) = v_data_target)), 
            'system');
            
END//

DELIMITER ;

-- =====================================================
-- 7. SETUP AUTOMATED EVENTS (Optional)
-- =====================================================

-- Event per job giornaliero automatico (decommentare se necessario)
-- SET GLOBAL event_scheduler = ON;
-- 
-- DROP EVENT IF EXISTS daily_processing_event;
-- CREATE EVENT daily_processing_event
-- ON SCHEDULE EVERY 1 DAY STARTS '2025-08-12 08:00:00'
-- DO
-- CALL DailyProcessingJob(CURDATE());

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================

SELECT 'Stored procedures BAIT Service create successfully!' as status;
SELECT 'Available procedures:' as info;
SHOW PROCEDURE STATUS WHERE Db = 'bait_service_real';