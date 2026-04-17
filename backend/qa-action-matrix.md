# Action Matrix (Generated)

| Action | Method | Classification | Auth | Required Params (observed) | Permission (expected) | Actual Response Keys | Frontend Modules |
|---|---|---|---|---|---|---|---|
| add_test_25_coupon | POST | partial parity | no/conditional | n/a or contextual | n/a |  | not reachable from frontend |
| add_test_lead | POST | full parity | no/conditional | n/a or contextual | n/a | crmSync,name,ok,phone,result,row | not reachable from frontend |
| add_test_qr_scan | POST | full parity | no/conditional | n/a or contextual | n/a | ok,result,scanNumber | qr-diagnostic.html |
| add-test-lead | POST | full parity | no/conditional | n/a or contextual | n/a | crmSync,name,ok,phone,result,row | not reachable from frontend |
| admin_batch_checkin_event_qr | POST | partial parity | yes | At least one QR scan is required. | eventScanner | error,message,ok | admin-event-entry-scanner.html |
| admin_cash_summary | GET | full parity | yes | n/a or contextual | cashier | action,ok,summary | not reachable from frontend |
| admin_create_event | POST | partial parity | yes | event_id and title are required. | eventManagement | error,message,ok | not reachable from frontend |
| admin_event_list | GET | partial parity | yes | n/a or contextual | n/a | action,count,items,ok | not reachable from frontend |
| admin_issue_cash_paid_pass | POST | partial parity | yes | eventId, customerName and customerPhone are required. | cashier | error,message,ok | admin-cashier.html |
| admin_list_events | GET | partial parity | yes | n/a or contextual | eventManagement | action,count,items,ok | not reachable from frontend |
| admin_menu_editor_add_row | POST | full parity | yes | n/a or contextual | menuEditor | action,id,message,ok | admin-menu-price-editor.html |
| admin_menu_editor_delete_rows | POST | full parity | yes | n/a or contextual | menuEditor | action,deletedCount,ok | admin-menu-price-editor.html |
| admin_menu_editor_load | POST | partial parity | yes | n/a or contextual | menuEditor | action,headers,ok,rowCount,rows,sheetName,sheetType | admin-menu-price-editor.html |
| admin_menu_editor_save_changes | POST | full parity | yes | changes array is required. | menuEditor | error,message,ok | admin-menu-price-editor.html |
| admin_menu_editor_set_visibility | POST | full parity | yes | n/a or contextual | menuEditor | action,ok,updatedCount | admin-menu-price-editor.html |
| admin_preview_event_qr | POST | partial parity | no/conditional | n/a or contextual | eventScanner | error,message,ok | not reachable from frontend |
| admin_request_cash_cancel | POST | partial parity | yes | transactionId and reason are required. | cashier | error,message,ok | admin-cashier.html |
| admin_request_cash_handover | POST | partial parity | yes | n/a or contextual | cashier | error,message,ok | admin-cashier.html |
| admin_toggle_event | POST | partial parity | yes | eventId is required. | eventManagement | error,message,ok | admin-event-management.html |
| admin_update_event | POST | partial parity | yes | eventId is required. | eventManagement | error,message,ok | not reachable from frontend |
| auth_bootstrap_status | GET | full parity | no/conditional | n/a or contextual | n/a | hasUsers,initialized,lockout,ok,sessionHours | not reachable from frontend |
| auth_change_password | POST | partial parity | yes | currentPassword and newPassword are required. | n/a | error,message,ok | admin-portal.html; admin-user-management.html |
| auth_create_user | POST | full parity | yes | username and password are required. | superadmin/admin route policy | error,message,ok | admin-user-management.html |
| auth_get_api_settings | POST | full parity | yes | n/a or contextual | superadmin/admin route policy | action,ok,settings | admin-user-management.html |
| auth_get_app_settings | POST | full parity | no/conditional | n/a or contextual | superadmin/admin route policy | action,ok,settings,updatedAt | admin_settings_standalone.html; admin_settings.html |
| auth_list_users | GET | full parity | yes | n/a or contextual | superadmin/admin route policy | action,items,ok,users | not reachable from frontend |
| auth_login | POST | full parity | no/conditional | username and password are required. | n/a | error,message,ok | admin-auth.js |
| auth_logout | POST | full parity | yes | n/a or contextual | n/a | action,message,ok | admin-auth.js |
| auth_me | POST | full parity | yes | n/a or contextual | n/a | error,message,ok | not reachable from frontend |
| auth_reset_password | POST | full parity | yes | n/a or contextual | superadmin/admin route policy | error,message,ok | admin-user-management.html |
| auth_set_api_settings | POST | partial parity | yes | n/a or contextual | superadmin/admin route policy | error,message,ok | admin-user-management.html |
| auth_set_app_settings | POST | full parity | yes | n/a or contextual | superadmin/admin route policy | error,message,ok | admin_settings_standalone.html; admin_settings.html |
| auth_set_user_permissions | POST | full parity | yes | n/a or contextual | superadmin/admin route policy | error,message,ok | admin-user-management.html |
| auth_set_user_status | POST | partial parity | yes | n/a or contextual | superadmin/admin route policy | error,message,ok | admin-user-management.html |
| confirm_event_payment | POST | partial parity | no/conditional | orderId, paymentId and signature are required. | n/a | error,message,ok | not reachable from frontend |
| counter | GET | full parity | no/conditional | n/a or contextual | n/a | count,ok | not reachable from frontend |
| create_event_order | POST | partial parity | no/conditional | eventId is required. | n/a | error,message,ok | not reachable from frontend |
| create_qr_sheet | GET | full parity | no/conditional | n/a or contextual | n/a | ok,result,sheetName,totalScans | not reachable from frontend |
| create_test_paid_tx | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| download_qr_code | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| ensure_qr_sheet | GET | full parity | no/conditional | n/a or contextual | n/a | ok,result,sheetName,totalScans | qr-code.html |
| event_detail | GET | partial parity | no/conditional | Event id is required. | n/a | error,message,ok | event.html |
| event_guest_report | GET | partial parity | yes | n/a or contextual | eventGuests | error,message,ok | not reachable from frontend |
| event_list | GET | partial parity | no/conditional | n/a or contextual | n/a | action,count,items,ok | not reachable from frontend |
| event_popup | GET | full parity | no/conditional | n/a or contextual | n/a | event,ok | not reachable from frontend |
| event_transactions_report | GET | partial parity | yes | n/a or contextual | n/a | error,message,ok | not reachable from frontend |
| events_list | GET | full parity | no/conditional | n/a or contextual | n/a | action,count,items,ok | event.html; events.html |
| init_qr_sheet | GET | full parity | no/conditional | n/a or contextual | n/a | ok,result,sheetName,totalScans | not reachable from frontend |
| init_schema | GET | full parity | no/conditional | n/a or contextual | n/a | headers,ok,result | not reachable from frontend |
| migrate_event_sheet_format | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| migrate_events_sheet_format | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| qr_report | GET | full parity | no/conditional | n/a or contextual | n/a | count,ok,recentScans,source,totalScans | qr-code.html; qr-diagnostic.html; qr-report.html |
| qr_scan_client | POST | full parity | no/conditional | n/a or contextual | n/a | action,emailTriggerInterval,ok,scanNumber | scan.html |
| qr_scan_report_html | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| qr-scan-report-html | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| razorpay_webhook | POST | partial parity | no/conditional | n/a or contextual | n/a | error,message,ok | not reachable from frontend |
| redeem | GET | full parity | yes | n/a or contextual | verification | error,message,ok | not reachable from frontend |
| regen_coupon | GET | full parity | yes | n/a or contextual | verification | error,message,ok | not reachable from frontend |
| regen-coupon | GET | partial parity | yes | n/a or contextual | verification | error,message,ok | not reachable from frontend |
| regenerate_coupon | GET | partial parity | yes | n/a or contextual | verification | error,message,ok | not reachable from frontend |
| register_free_event | POST | partial parity | no/conditional | eventId is required. | n/a | error,message,ok | not reachable from frontend |
| request_event_cancellation | POST | partial parity | no/conditional | transactionId is required. | n/a | error,message,ok | not reachable from frontend |
| resend_event_confirmation | POST | partial parity | no/conditional | transactionId is required. | n/a | error,message,ok | not reachable from frontend |
| reset_events_data | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| reset_events_sheet_format | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| schema | GET | full parity | no/conditional | n/a or contextual | n/a | headers,ok,result | not reachable from frontend |
| seed_dj_events | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_dj_events_apr_2026 | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_event_sample | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_events_sample | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_paid_event | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_paid_event_sample | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| seed_test_paid_tx | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| send_test_event_email | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| submit_lead | POST | partial parity | no/conditional | Valid name and 10-digit phone are required. | n/a | error,message,ok | menu-blocker.js |
| superadmin_approve_cash_handover | POST | partial parity | yes | n/a or contextual | superadmin | error,message,ok | superadmin-cash-approvals.html |
| superadmin_cash_dashboard | GET | full parity | yes | n/a or contextual | superadmin | error,message,ok | not reachable from frontend |
| superadmin_resolve_cash_cancel | POST | partial parity | yes | n/a or contextual | superadmin | error,message,ok | superadmin-cash-approvals.html |
| sync_crm_by_phone | POST | full parity | no/conditional | Valid 10-digit phone is required. | n/a | error,message,ok | not reachable from frontend |
| sync-crm-by-phone | POST | full parity | no/conditional | Valid 10-digit phone is required. | n/a | error,message,ok | not reachable from frontend |
| test_25_coupon | POST | partial parity | no/conditional | n/a or contextual | n/a |  | not reachable from frontend |
| test_event_email | GET | utility/test-only but implemented | no/conditional | n/a or contextual | n/a | action,error,message,ok,phpOnly | not reachable from frontend |
| test_qr_scan | POST | full parity | no/conditional | n/a or contextual | n/a | ok,result,scanNumber | not reachable from frontend |
| verify | GET | full parity | no/conditional | Valid 10-digit phone is required. | n/a | error,message,ok | not reachable from frontend |
| verify_event_qr | POST | partial parity | yes | n/a or contextual | n/a | error,message,ok | event-verification.html |
