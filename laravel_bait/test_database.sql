-- Test database creation
USE bait_service_real;
SHOW TABLES;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'bait_service_real';
SELECT 'Database test completed' as status;