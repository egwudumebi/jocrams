# PostgreSQL performance tuning (applied on first init)
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '768MB';
ALTER SYSTEM SET maintenance_work_mem = '64MB';
ALTER SYSTEM SET work_mem = '8MB';
ALTER SYSTEM SET max_connections = '100';
SELECT pg_reload_conf();
