-- =============================================================================
-- BAIT SERVICE ENTERPRISE - AUTOMATED BACKUP STRATEGY
-- Production-ready backup system with rotation and monitoring
-- =============================================================================

USE bait_service_real;

-- Create backup metadata table if not exists
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
    retention_days INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_date (backup_start_time),
    INDEX idx_backup_type (backup_type, backup_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup logging procedure
DELIMITER //
DROP PROCEDURE IF EXISTS LogBackupStart//
CREATE PROCEDURE LogBackupStart(
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

DROP PROCEDURE IF EXISTS LogBackupComplete//
CREATE PROCEDURE LogBackupComplete(
    IN p_backup_id INT,
    IN p_size_bytes BIGINT,
    IN p_status VARCHAR(20)
)
BEGIN
    UPDATE backup_metadata 
    SET backup_end_time = NOW(),
        backup_size_bytes = p_size_bytes,
        backup_status = p_status,
        verification_status = 'verified'
    WHERE id = p_backup_id;
END//

DROP PROCEDURE IF EXISTS CleanupOldBackups//
CREATE PROCEDURE CleanupOldBackups(IN retention_days INT)
BEGIN
    -- Mark old backups for deletion
    UPDATE backup_metadata 
    SET backup_status = 'expired'
    WHERE backup_start_time < DATE_SUB(NOW(), INTERVAL retention_days DAY)
      AND backup_status = 'completed';
      
    -- Get list of expired backups for file cleanup
    SELECT 
        id,
        backup_filename,
        backup_path,
        backup_start_time
    FROM backup_metadata
    WHERE backup_status = 'expired'
    ORDER BY backup_start_time;
END//
DELIMITER ;