-- =============================================================================
-- BAIT SERVICE ENTERPRISE - OPTIMIZED QUERIES FOR DASHBOARD
-- Production-ready queries optimized for sub-2 second response times
-- =============================================================================

USE bait_service_real;

-- =============================================================================
-- SECTION 1: OPTIMIZED DASHBOARD VIEWS
-- =============================================================================

-- 1.1 Main Dashboard View - Optimized for speed
DROP VIEW IF EXISTS view_dashboard_fast;
CREATE VIEW view_dashboard_fast AS
SELECT 
    aa.id,
    aa.alert_id,
    aa.categoria,
    aa.severita,
    aa.stato_risoluzione,
    aa.impatto_stimato_euro,
    aa.data_creazione,
    aa.data_risoluzione,
    COALESCE(ad.tipo_anomalia, 'NON_SPECIFICATO') as tipo_anomalia,
    COALESCE(ad.confidence_score, 0) as confidence_score,
    COALESCE(CONCAT(t.nome, ' ', t.cognome), 'SISTEMA') as tecnico_nome,
    COALESCE(ar.nome_azienda, 'NON_ASSEGNATO') as nome_azienda
FROM audit_alerts aa
LEFT JOIN alert_dettagliati ad ON aa.alert_id = ad.alert_id
LEFT JOIN tecnici t ON ad.tecnico_id = t.id  
LEFT JOIN aziende_reali ar ON ad.azienda_id = ar.id
WHERE aa.data_creazione >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY aa.data_creazione DESC, 
         CASE aa.severita 
             WHEN 'CRITICA' THEN 1
             WHEN 'WARNING' THEN 2  
             WHEN 'INFO' THEN 3
             ELSE 4
         END;

-- 1.2 KPI Summary View - Optimized for aggregations
DROP VIEW IF EXISTS view_kpi_summary_fast;
CREATE VIEW view_kpi_summary_fast AS
SELECT 
    'DASHBOARD_KPI' as report_type,
    DATE(aa.data_creazione) as data_ref,
    COUNT(*) as total_alerts,
    COUNT(CASE WHEN aa.severita = 'CRITICA' THEN 1 END) as alerts_critici,
    COUNT(CASE WHEN aa.severita = 'WARNING' THEN 1 END) as alerts_warning,
    COUNT(CASE WHEN aa.severita = 'INFO' THEN 1 END) as alerts_info,
    COUNT(CASE WHEN aa.stato_risoluzione = 'RISOLTO' THEN 1 END) as alerts_risolti,
    COUNT(CASE WHEN aa.stato_risoluzione IS NULL THEN 1 END) as alerts_pending,
    SUM(COALESCE(aa.impatto_stimato_euro, 0)) as impatto_totale_euro,
    AVG(COALESCE(ad.confidence_score, 0)) as avg_confidence_score
FROM audit_alerts aa
LEFT JOIN alert_dettagliati ad ON aa.alert_id = ad.alert_id
WHERE aa.data_creazione >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(aa.data_creazione)
ORDER BY data_ref DESC;

-- 1.3 Technician Performance View - Optimized for analysis
DROP VIEW IF EXISTS view_technician_performance_fast;
CREATE VIEW view_technician_performance_fast AS
SELECT 
    tda.tecnico_id,
    CONCAT(t.nome, ' ', t.cognome) as nome_completo,
    DATE(tda.data_analisi) as data_ref,
    COUNT(DISTINCT DATE(tda.data_analisi)) as giorni_lavorati,
    ROUND(AVG(tda.copertura_timeline_score), 2) as avg_copertura_timeline,
    ROUND(AVG(tda.coerenza_cross_validation_score), 2) as avg_coerenza,
    SUM(tda.ore_totali_dichiarate) as ore_totali,
    SUM(CASE WHEN tda.richiede_verifica = 1 THEN 1 ELSE 0 END) as giorni_con_anomalie,
    SUM(CASE WHEN tda.anomalie_critiche > 0 THEN 1 ELSE 0 END) as giorni_critici,
    ROUND((SUM(CASE WHEN tda.richiede_verifica = 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as percentuale_conformita
FROM technician_daily_analysis tda
JOIN tecnici t ON tda.tecnico_id = t.id
WHERE tda.data_analisi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND t.stato_attivo = 1
GROUP BY tda.tecnico_id, t.nome, t.cognome, DATE(tda.data_analisi)
ORDER BY avg_copertura_timeline DESC, ore_totali DESC;

-- =============================================================================
-- SECTION 2: HIGH-PERFORMANCE STORED PROCEDURES
-- =============================================================================

-- 2.1 Fast Dashboard Summary
DELIMITER //
DROP PROCEDURE IF EXISTS GetDashboardSummaryFast//
CREATE PROCEDURE GetDashboardSummaryFast(IN days_back INT)
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sql_state = RETURNED_SQLSTATE,
            @error_message = MESSAGE_TEXT;
        SELECT CONCAT('Error: ', @sql_state, ' - ', @error_message) as error_info;
        ROLLBACK;
    END;

    START TRANSACTION;
    
    -- Main KPI summary optimized
    SELECT 
        'BAIT_DASHBOARD_SUMMARY' as report_name,
        COUNT(*) as total_alerts,
        COUNT(CASE WHEN severita = 'CRITICA' THEN 1 END) as alerts_critici,
        COUNT(CASE WHEN severita = 'WARNING' THEN 1 END) as alerts_warning,  
        COUNT(CASE WHEN severita = 'INFO' THEN 1 END) as alerts_info,
        COUNT(CASE WHEN stato_risoluzione = 'RISOLTO' THEN 1 END) as alerts_risolti,
        COUNT(CASE WHEN stato_risoluzione IS NULL THEN 1 END) as alerts_pending,
        SUM(COALESCE(impatto_stimato_euro, 0)) as impatto_totale_euro,
        MAX(data_creazione) as ultimo_alert,
        MIN(data_creazione) as primo_alert,
        DATEDIFF(MAX(data_creazione), MIN(data_creazione)) + 1 as giorni_coperti
    FROM audit_alerts 
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY);
    
    -- Category breakdown for charts
    SELECT 
        COALESCE(categoria, 'NON_CATEGORIZZATO') as categoria,
        COUNT(*) as count,
        ROUND(AVG(COALESCE(impatto_stimato_euro, 0)), 2) as avg_impatto,
        COUNT(CASE WHEN severita = 'CRITICA' THEN 1 END) as critici_count
    FROM audit_alerts 
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
    GROUP BY categoria
    ORDER BY count DESC
    LIMIT 10;
    
    -- Recent critical alerts
    SELECT 
        alert_id,
        categoria,
        severita,
        stato_risoluzione,
        impatto_stimato_euro,
        data_creazione
    FROM audit_alerts
    WHERE severita = 'CRITICA' 
      AND data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
    ORDER BY data_creazione DESC
    LIMIT 5;
    
    COMMIT;
END//
DELIMITER ;

-- 2.2 Fast Technician Analysis  
DELIMITER //
DROP PROCEDURE IF EXISTS GetTechnicianAnalysisFast//
CREATE PROCEDURE GetTechnicianAnalysisFast(IN tecnico_id_filter INT, IN days_back INT)
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Technician performance summary
    SELECT 
        tda.tecnico_id,
        CONCAT(t.nome, ' ', t.cognome) as nome_completo,
        COUNT(DISTINCT DATE(tda.data_analisi)) as giorni_analizzati,
        ROUND(AVG(tda.copertura_timeline_score), 2) as performance_score,
        ROUND(AVG(tda.coerenza_cross_validation_score), 2) as coerenza_score,
        SUM(tda.ore_totali_dichiarate) as ore_totali,
        SUM(CASE WHEN tda.anomalie_critiche > 0 THEN 1 ELSE 0 END) as giorni_con_anomalie_critiche,
        ROUND((COUNT(CASE WHEN tda.richiede_verifica = 0 THEN 1 END) / COUNT(*)) * 100, 1) as percentuale_conformita,
        MAX(tda.data_analisi) as ultima_analisi
    FROM technician_daily_analysis tda
    JOIN tecnici t ON tda.tecnico_id = t.id
    WHERE (tecnico_id_filter IS NULL OR tda.tecnico_id = tecnico_id_filter)
      AND tda.data_analisi >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
      AND t.stato_attivo = 1
    GROUP BY tda.tecnico_id, t.nome, t.cognome
    ORDER BY performance_score DESC, ore_totali DESC;
    
    -- Daily trend for selected technician (if specified)
    IF tecnico_id_filter IS NOT NULL THEN
        SELECT 
            DATE(data_analisi) as data_ref,
            copertura_timeline_score,
            coerenza_cross_validation_score,
            ore_totali_dichiarate,
            anomalie_critiche,
            richiede_verifica
        FROM technician_daily_analysis
        WHERE tecnico_id = tecnico_id_filter
          AND data_analisi >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
        ORDER BY data_analisi DESC;
    END IF;
    
    COMMIT;
END//
DELIMITER ;

-- 2.3 Fast Alert Detail Query
DELIMITER //
DROP PROCEDURE IF EXISTS GetAlertDetailsFast//
CREATE PROCEDURE GetAlertDetailsFast(IN alert_id_param VARCHAR(50))
BEGIN
    -- Alert main information
    SELECT 
        aa.*,
        ad.tipo_anomalia,
        ad.confidence_score,
        CONCAT(t.nome, ' ', t.cognome) as tecnico_nome,
        ar.nome_azienda
    FROM audit_alerts aa
    LEFT JOIN alert_dettagliati ad ON aa.alert_id = ad.alert_id
    LEFT JOIN tecnici t ON ad.tecnico_id = t.id
    LEFT JOIN aziende_reali ar ON ad.azienda_id = ar.id
    WHERE aa.alert_id = alert_id_param;
    
    -- Related timeline events (if any)
    SELECT 
        te.*
    FROM timeline_events te
    WHERE te.alert_id = alert_id_param
    ORDER BY te.timestamp_evento;
END//
DELIMITER ;

-- =============================================================================
-- SECTION 3: PERFORMANCE MONITORING QUERIES
-- =============================================================================

-- 3.1 Query to check index usage efficiency
SELECT 
    s.TABLE_NAME,
    s.INDEX_NAME,
    s.COLUMN_NAME,
    s.CARDINALITY,
    t.TABLE_ROWS,
    CASE 
        WHEN s.CARDINALITY = 0 THEN 'UNUSED'
        WHEN (s.CARDINALITY / GREATEST(t.TABLE_ROWS, 1)) < 0.1 THEN 'LOW_SELECTIVITY'
        WHEN (s.CARDINALITY / GREATEST(t.TABLE_ROWS, 1)) > 0.8 THEN 'HIGH_SELECTIVITY'
        ELSE 'MEDIUM_SELECTIVITY'
    END as selectivity_rating
FROM information_schema.STATISTICS s
JOIN information_schema.TABLES t ON s.TABLE_NAME = t.TABLE_NAME
WHERE s.TABLE_SCHEMA = 'bait_service_real'
  AND t.TABLE_SCHEMA = 'bait_service_real'
  AND s.INDEX_NAME != 'PRIMARY'
ORDER BY s.TABLE_NAME, selectivity_rating, s.INDEX_NAME;

-- 3.2 Table size analysis for optimization planning
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Total_Size_MB',
    ROUND((DATA_LENGTH / 1024 / 1024), 2) AS 'Data_Size_MB',
    ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS 'Index_Size_MB',
    ROUND((INDEX_LENGTH / (DATA_LENGTH + INDEX_LENGTH)) * 100, 2) AS 'Index_Ratio_%'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'bait_service_real'
  AND TABLE_TYPE = 'BASE TABLE'
  AND TABLE_ROWS > 0
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- 3.3 Create performance test procedure
DELIMITER //
DROP PROCEDURE IF EXISTS RunPerformanceTest//
CREATE PROCEDURE RunPerformanceTest()
BEGIN
    DECLARE start_time TIMESTAMP DEFAULT NOW(6);
    DECLARE end_time TIMESTAMP;
    DECLARE query_duration_ms DECIMAL(10,3);
    DECLARE test_result VARCHAR(20);
    
    -- Test main dashboard query performance
    SELECT COUNT(*) INTO @record_count
    FROM view_dashboard_fast
    LIMIT 100;
    
    SET end_time = NOW(6);
    SET query_duration_ms = TIMESTAMPDIFF(MICROSECOND, start_time, end_time) / 1000;
    
    SET test_result = CASE 
        WHEN query_duration_ms < 500 THEN 'EXCELLENT'
        WHEN query_duration_ms < 1000 THEN 'GOOD'  
        WHEN query_duration_ms < 2000 THEN 'ACCEPTABLE'
        ELSE 'NEEDS_OPTIMIZATION'
    END;
    
    SELECT 
        'BAIT Dashboard Performance Test' as test_name,
        @record_count as records_processed,
        query_duration_ms as execution_time_ms,
        test_result as performance_rating,
        start_time as test_start,
        end_time as test_end;
        
    -- Log performance result
    INSERT INTO performance_monitoring 
    (query_time, lock_time, rows_sent, rows_examined, query_text)
    VALUES 
    (query_duration_ms/1000, 0, @record_count, @record_count, 'Dashboard Performance Test');
END//
DELIMITER ;

-- =============================================================================
-- VERIFICATION QUERIES - Run these to confirm optimization success
-- =============================================================================

-- Verify new indexes were created
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    CARDINALITY
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'bait_service_real'
  AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME;

-- Test dashboard query performance  
CALL RunPerformanceTest();

-- Verify procedures were created
SHOW PROCEDURE STATUS WHERE Db = 'bait_service_real' AND Name LIKE '%Fast%';

-- =============================================================================
-- END OF OPTIMIZED QUERIES
-- All queries optimized for <2 second response time target
-- =============================================================================