-- Fix per trigger audit_sessions che causa errore 1442
-- Rimuove il trigger problematico e ne crea uno corretto

USE bait_service_real;

-- Remove existing problematic trigger
DROP TRIGGER IF EXISTS audit_monthly_reset;

-- Create new trigger that works correctly
-- This avoids updating the same table during INSERT by using AFTER instead of BEFORE
-- and by excluding the current record being inserted
DELIMITER //
CREATE TRIGGER audit_monthly_reset_fixed 
AFTER INSERT ON audit_sessions 
FOR EACH ROW 
BEGIN 
    -- Archive previous active sessions when a new active session is inserted
    IF NEW.session_status = 'active' THEN 
        UPDATE audit_sessions 
        SET session_status = 'archived' 
        WHERE session_status = 'active' 
        AND id != NEW.id; 
    END IF; 
END//
DELIMITER ;