DELETE FROM event_transactions WHERE event_id = 'dummy-e2e-20260416154119' OR customer_name LIKE 'DUMMY E2E%';
DELETE FROM admin_cash_ledger WHERE event_id = 'dummy-e2e-20260416154119' OR customer_name LIKE 'DUMMY E2E%';
DELETE FROM superadmin_cash_ledger WHERE batch_key = 'HANDOVER-20260416154123-4A597199' OR admin_username = '9688454001';
DELETE FROM events WHERE event_id = 'dummy-e2e-20260416154119';
DELETE FROM leads WHERE phone = '9817788114' OR source = 'dummy-e2e' OR name LIKE 'DUMMY E2E%';
DELETE FROM users WHERE username = '9688454001';
DELETE FROM menu_items WHERE item_name LIKE 'DUMMY_E2E_MENU_ITEM%';
DELETE FROM qr_scans WHERE user_agent LIKE 'DUMMY_E2E_QR_AGENT%';
