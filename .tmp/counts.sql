SELECT 'menu_items' AS t, COUNT(*) AS c FROM menu_items
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'leads', COUNT(*) FROM leads
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'api_settings', COUNT(*) FROM api_settings;
