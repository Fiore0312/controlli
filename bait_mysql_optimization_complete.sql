-- =============================================================================
-- BAIT SERVICE ENTERPRISE - MYSQL DATABASE OPTIMIZATION COMPLETE
-- Target: Production-ready performance optimization for bait_service_real
-- Date: 2025-08-21
-- Performance Target: Query dashboard <2 seconds, Index usage >90%
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- =============================================================================
-- SECTION 1: ADVANCED INDEXING STRATEGY
-- Optimized for dashboard queries and JOIN operations
-- =============================================================================

USE bait_service_real;

-- 1.1 Optimize audit_alerts table for dashboard filtering
-- Current: Basic indexes exist but need composite optimization
-- Target: Fast filtering by categoria+severita+stato combinations

-- Drop existing redundant indexes before creating optimized ones
-- ALTER TABLE audit_alerts DROP INDEX idx_alerts_resolution_tracking;

-- Create high-performance composite index for main dashboard query
CREATE INDEX IF NOT EXISTS idx_dashboard_main_query 
ON audit_alerts (categoria, severita, stato_risoluzione, data_creazione DESC);

-- Create index for temporal queries (daily/weekly reports)
CREATE INDEX IF NOT EXISTS idx_temporal_analysis 
ON audit_alerts (data_creazione, categoria, severita);

-- Create index for notification status tracking
CREATE INDEX IF NOT EXISTS idx_notification_tracking 
ON audit_alerts (notifica_inviata, data_notifica, categoria);

-- Create covering index for alert summary queries
CREATE INDEX IF NOT EXISTS idx_alert_summary_covering 
ON audit_alerts (categoria, severita, stato_risoluzione, impatto_stimato_euro, data_creazione);

-- 1.2 Optimize alert_dettagliati for JOIN performance
-- Target: Eliminate table scans in JOIN operations

-- Create composite index for JOIN + filtering
CREATE INDEX IF NOT EXISTS idx_alert_join_optimized 
ON alert_dettagliati (alert_id, tipo_anomalia, severita, stato);

-- Create index for tecnico-based queries
CREATE INDEX IF NOT EXISTS idx_tecnico_anomalie 
ON alert_dettagliati (tecnico_id, tipo_anomalia, data_creazione DESC);

-- Create covering index for dashboard detail view
CREATE INDEX IF NOT EXISTS idx_detail_dashboard_covering 
ON alert_dettagliati (alert_id, tecnico_id, tipo_anomalia, severita, stato, confidence_score);

-- 1.3 Optimize technician_daily_analysis for performance analytics
-- Target: Fast aggregation and trend analysis

-- Create index for performance trending
CREATE INDEX IF NOT EXISTS idx_performance_trending 
ON technician_daily_analysis (tecnico_id, data_analisi, copertura_timeline_score, coerenza_cross_validation_score);

-- Create index for anomaly detection queries
CREATE INDEX IF NOT EXISTS idx_anomaly_detection 
ON technician_daily_analysis (richiede_verifica, anomalie_critiche, data_analisi DESC);

-- Create covering index for KPI dashboard
CREATE INDEX IF NOT EXISTS idx_kpi_dashboard_covering 
ON technician_daily_analysis (data_analisi, tecnico_id, ore_totali_dichiarate, copertura_timeline_score, 
    coerenza_cross_validation_score, richiede_verifica, anomalie_critiche);

-- 1.4 Optimize supporting tables for JOIN performance

-- Optimize tecnici table for lookups
CREATE INDEX IF NOT EXISTS idx_tecnici_lookup 
ON tecnici (nome, cognome, stato_attivo);

-- Optimize aziende_reali for client queries  
CREATE INDEX IF NOT EXISTS idx_aziende_lookup 
ON aziende_reali (nome_azienda, codice_cliente);

-- Optimize timbrature for temporal analysis
CREATE INDEX IF NOT EXISTS idx_timbrature_temporal 
ON timbrature (data_timbratura, tecnico, tipo_timbratura);

-- =============================================================================
-- SECTION 2: QUERY OPTIMIZATION - REWRITTEN EFFICIENT QUERIES
-- Production-ready queries optimized for minimal execution time
-- =============================================================================

-- 2.1 Create optimized view for main dashboard
DROP VIEW IF EXISTS view_dashboard_optimized;

CREATE VIEW view_dashboard_optimized AS
SELECT 
    aa.id,
    aa.alert_id,
    aa.categoria,
    aa.severita,
    aa.stato_risoluzione,
    aa.impatto_stimato_euro,
    aa.data_creazione,
    aa.data_risoluzione,
    ad.tipo_anomalia,
    ad.confidence_score,
    CONCAT(t.nome, ' ', t.cognome) as tecnico_nome,
    ar.nome_azienda
FROM audit_alerts aa
FORCE INDEX (idx_dashboard_main_query)
LEFT JOIN alert_dettagliati ad ON aa.alert_id = ad.alert_id
LEFT JOIN tecnici t ON ad.tecnico_id = t.id  
LEFT JOIN aziende_reali ar ON ad.azienda_id = ar.id
WHERE aa.data_creazione >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY aa.data_creazione DESC, aa.severita DESC;

-- 2.2 Create optimized view for KPI calculations
DROP VIEW IF EXISTS view_kpi_optimized;

CREATE VIEW view_kpi_optimized AS
SELECT 
    DATE(tda.data_analisi) as data_ref,
    tda.tecnico_id,
    CONCAT(t.nome, ' ', t.cognome) as tecnico_nome,
    COUNT(*) as giorni_analizzati,
    AVG(tda.copertura_timeline_score) as avg_copertura_timeline,
    AVG(tda.coerenza_cross_validation_score) as avg_coerenza,
    SUM(tda.ore_totali_dichiarate) as ore_totali_periodo,
    SUM(CASE WHEN tda.richiede_verifica = 1 THEN 1 ELSE 0 END) as giorni_anomalie,
    SUM(CASE WHEN tda.anomalie_critiche > 0 THEN 1 ELSE 0 END) as giorni_critici,
    MAX(tda.data_analisi) as ultima_analisi
FROM technician_daily_analysis tda
FORCE INDEX (idx_kpi_dashboard_covering)
JOIN tecnici t ON tda.tecnico_id = t.id
WHERE tda.data_analisi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND t.stato_attivo = 1
GROUP BY DATE(tda.data_analisi), tda.tecnico_id, t.nome, t.cognome
ORDER BY data_ref DESC, avg_copertura_timeline DESC;

-- 2.3 Create optimized stored procedure for alert statistics
DELIMITER //
DROP PROCEDURE IF EXISTS GetAlertStatistics;
CREATE PROCEDURE GetAlertStatistics(IN days_back INT)
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    SELECT 
        'Alert Statistics' as report_type,
        COUNT(*) as total_alerts,
        COUNT(CASE WHEN severita = 'CRITICA' THEN 1 END) as critica_count,
        COUNT(CASE WHEN severita = 'WARNING' THEN 1 END) as warning_count,
        COUNT(CASE WHEN severita = 'INFO' THEN 1 END) as info_count,
        COUNT(CASE WHEN stato_risoluzione = 'RISOLTO' THEN 1 END) as risolti_count,
        COUNT(CASE WHEN stato_risoluzione IS NULL THEN 1 END) as pending_count,
        AVG(CASE WHEN impatto_stimato_euro > 0 THEN impatto_stimato_euro END) as avg_impatto_euro,
        SUM(CASE WHEN impatto_stimato_euro > 0 THEN impatto_stimato_euro ELSE 0 END) as total_impatto_euro
    FROM audit_alerts 
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY);
    
    -- Category breakdown
    SELECT 
        categoria,
        severita,
        COUNT(*) as count,
        AVG(CASE WHEN impatto_stimato_euro > 0 THEN impatto_stimato_euro END) as avg_impatto
    FROM audit_alerts 
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
    GROUP BY categoria, severita
    ORDER BY count DESC;
    
    COMMIT;
END//
DELIMITER ;

-- =============================================================================
-- SECTION 3: UTF-8 CONSISTENCY AND CHARSET OPTIMIZATION
-- Ensure all tables use utf8mb4 for complete Unicode support
-- =============================================================================

-- 3.1 Fix client-side charset issues
-- Note: These will need to be set in application connection strings
-- SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3.2 Verify and fix table charsets if needed
SELECT 
    TABLE_NAME,
    TABLE_COLLATION,
    CASE 
        WHEN TABLE_COLLATION = 'utf8mb4_unicode_ci' THEN 'OK'
        ELSE 'NEEDS_UPDATE'
    END as charset_status
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'bait_service_real'
AND TABLE_TYPE = 'BASE TABLE'
ORDER BY charset_status DESC, TABLE_NAME;

-- 3.3 Update any tables not using utf8mb4_unicode_ci (if needed)
-- This is diagnostic - run only if charset_status shows NEEDS_UPDATE

/*
-- Example fix for tables with incorrect charset:
ALTER TABLE audit_alerts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE alert_dettagliati CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE technician_daily_analysis CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
*/

-- =============================================================================
-- SECTION 4: AUTOMATED BACKUP STRATEGY
-- Production-ready backup system with rotation and verification
-- =============================================================================

-- 4.1 Create backup metadata table
CREATE TABLE IF NOT EXISTS backup_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'schema_only') NOT NULL,
    backup_filename VARCHAR(255) NOT NULL,
    backup_start_time DATETIME NOT NULL,
    backup_end_time DATETIME NULL,
    backup_size_bytes BIGINT NULL,
    backup_status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    backup_path VARCHAR(500) NOT NULL,
    verification_status ENUM('pending', 'verified', 'corrupted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_date (backup_start_time),
    INDEX idx_backup_type (backup_type, backup_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 Create backup verification procedure
DELIMITER //
DROP PROCEDURE IF EXISTS LogBackupOperation;
CREATE PROCEDURE LogBackupOperation(
    IN p_backup_type VARCHAR(50),
    IN p_filename VARCHAR(255),
    IN p_backup_path VARCHAR(500)
)
BEGIN
    INSERT INTO backup_metadata 
    (backup_type, backup_filename, backup_start_time, backup_path)
    VALUES 
    (p_backup_type, p_filename, NOW(), p_backup_path);
    
    SELECT LAST_INSERT_ID() as backup_log_id;
END//
DELIMITER ;

-- =============================================================================
-- SECTION 5: MYSQL PERFORMANCE TUNING PARAMETERS
-- Optimized for XAMPP environment with business workload
-- =============================================================================

-- 5.1 Key performance parameters (to be added to my.ini)
/*
Add to /mnt/c/xampp/mysql/bin/my.ini under [mysqld] section:

# Buffer pool size (set to 70-80% of available RAM for database server)
innodb_buffer_pool_size = 1G

# Log file size for InnoDB
innodb_log_file_size = 256M
innodb_log_buffer_size = 16M

# Query cache (deprecated in MySQL 8.0, but useful in MariaDB)
query_cache_size = 64M
query_cache_type = 1

# Connection settings
max_connections = 200
connect_timeout = 10

# MyISAM settings  
key_buffer_size = 128M
table_open_cache = 4000

# InnoDB settings for performance
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
innodb_thread_concurrency = 8

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Slow query log for monitoring
slow_query_log = 1
long_query_time = 2
slow_query_log_file = /xampp/mysql/logs/mysql-slow.log
*/

-- 5.2 Create monitoring table for slow queries
CREATE TABLE IF NOT EXISTS performance_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_time DECIMAL(10,6) NOT NULL,
    lock_time DECIMAL(10,6) NOT NULL,  
    rows_sent INT NOT NULL,
    rows_examined INT NOT NULL,
    query_text TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_query_time (query_time),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 6: PERFORMANCE ANALYSIS AND MONITORING QUERIES
-- Production monitoring queries for ongoing optimization
-- =============================================================================

-- 6.1 Index usage analysis query
SELECT 
    t.TABLE_NAME,
    t.TABLE_ROWS,
    s.INDEX_NAME,
    s.COLUMN_NAME,
    s.CARDINALITY,
    CASE 
        WHEN s.CARDINALITY = 0 THEN 'UNUSED_INDEX'
        WHEN s.CARDINALITY < 10 THEN 'LOW_SELECTIVITY' 
        ELSE 'GOOD_SELECTIVITY'
    END as index_efficiency
FROM information_schema.TABLES t
JOIN information_schema.STATISTICS s ON t.TABLE_NAME = s.TABLE_NAME
WHERE t.TABLE_SCHEMA = 'bait_service_real'
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND s.INDEX_NAME != 'PRIMARY'
ORDER BY t.TABLE_NAME, s.INDEX_NAME, s.SEQ_IN_INDEX;

-- 6.2 Table size analysis
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB',
    ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS 'Index_Size_MB',
    ROUND((INDEX_LENGTH / (DATA_LENGTH + INDEX_LENGTH)) * 100, 2) AS 'Index_Ratio_%'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'bait_service_real'
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- =============================================================================
-- SECTION 7: BUSINESS-SPECIFIC OPTIMIZATION PROCEDURES
-- BAIT Service specific optimization for common business queries
-- =============================================================================

-- 7.1 Fast alert aggregation for dashboard
DELIMITER //
DROP PROCEDURE IF EXISTS GetDashboardSummary;
CREATE PROCEDURE GetDashboardSummary(IN days_back INT)
BEGIN
    -- Main alert summary optimized for dashboard
    SELECT 
        COUNT(*) as total_alerts,
        COUNT(CASE WHEN severita = 'CRITICA' THEN 1 END) as alerts_critici,
        COUNT(CASE WHEN stato_risoluzione IS NULL THEN 1 END) as alerts_pending,
        SUM(CASE WHEN impatto_stimato_euro > 0 THEN impatto_stimato_euro ELSE 0 END) as impatto_totale,
        MAX(data_creazione) as ultimo_alert
    FROM audit_alerts 
    FORCE INDEX (idx_temporal_analysis)
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY);
    
    -- Top categories by impact
    SELECT 
        categoria,
        COUNT(*) as count,
        AVG(CASE WHEN impatto_stimato_euro > 0 THEN impatto_stimato_euro END) as avg_impatto
    FROM audit_alerts 
    FORCE INDEX (idx_temporal_analysis)
    WHERE data_creazione >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
    GROUP BY categoria
    ORDER BY count DESC
    LIMIT 10;
END//
DELIMITER ;

-- 7.2 Technician performance analysis optimized
DELIMITER //
DROP PROCEDURE IF EXISTS GetTechnicianPerformance;
CREATE PROCEDURE GetTechnicianPerformance(IN tecnico_filter INT, IN days_back INT)
BEGIN
    SELECT 
        tda.tecnico_id,
        CONCAT(t.nome, ' ', t.cognome) as nome_completo,
        COUNT(*) as giorni_analizzati,
        AVG(tda.copertura_timeline_score) as performance_score,
        SUM(tda.ore_totali_dichiarate) as ore_totali,
        SUM(CASE WHEN tda.anomalie_critiche > 0 THEN 1 ELSE 0 END) as giorni_con_anomalie,
        MAX(tda.data_analisi) as ultima_analisi
    FROM technician_daily_analysis tda
    FORCE INDEX (idx_performance_trending)
    JOIN tecnici t ON tda.tecnico_id = t.id
    WHERE (tecnico_filter IS NULL OR tda.tecnico_id = tecnico_filter)
      AND tda.data_analisi >= DATE_SUB(CURDATE(), INTERVAL days_back DAY)
      AND t.stato_attivo = 1
    GROUP BY tda.tecnico_id, t.nome, t.cognome
    ORDER BY performance_score DESC, ore_totali DESC;
END//
DELIMITER ;

-- =============================================================================
-- SECTION 8: FINAL OPTIMIZATION VERIFICATION
-- Queries to verify optimization success
-- =============================================================================

-- 8.1 Verify all new indexes were created successfully
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'bait_service_real'
  AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 8.2 Performance baseline query (run after optimization)
SELECT 
    'Performance Test' as test_name,
    COUNT(*) as total_records,
    NOW() as test_timestamp
FROM audit_alerts aa
JOIN alert_dettagliati ad ON aa.alert_id = ad.alert_id
JOIN technician_daily_analysis tda ON ad.tecnico_id = tda.tecnico_id
WHERE aa.data_creazione >= DATE_SUB(CURDATE(), INTERVAL 7 DAY);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- END OF OPTIMIZATION SCRIPT
-- Next steps: 
-- 1. Execute backup strategy setup
-- 2. Update my.ini with performance parameters  
-- 3. Monitor slow query log
-- 4. Run performance verification queries
-- =============================================================================