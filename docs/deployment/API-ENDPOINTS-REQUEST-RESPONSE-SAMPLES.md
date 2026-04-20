# 9 ENDPOINTS - REQUEST/RESPONSE SAMPLES
## Based on Verified PHP Implementation (Pre-Deployment)

---

## SETUP
- **API Base**: `https://namastekalyan.asianwokandgrill.in/backend/index.php`
- **Method**: POST (5 actions) + GET (4 actions)
- **Auth**: JWT token in request body (POST) or query parameter (GET)
- **Content-Type**: `application/json`

---

## TEST 1: `admin_issue_cash_paid_pass` (POST)
**Purpose**: Admin creates a cash-paid pass for event attendee

### REQUEST
```json
{
  "action": "admin_issue_cash_paid_pass",
  "token": "eyJhbGci...(JWT token)",
  "eventId": "dj-raj-2026-apr",
  "customerName": "John Doe",
  "customerEmail": "john@example.com",
  "qty": 2,
  "amount": 500,
  "currency": "INR"
}
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "admin_issue_cash_paid_pass",
  "message": "Cash paid pass issued successfully",
  "summary": {
    "ledgerDate": "2026-04-16",
    "totals": {
      "totalAmount": 5000,
      "issuedAmount": 5000,
      "pendingAmount": 0,
      "handoverApprovedAmount": 0,
      "cancelledAmount": 0
    },
    "recentTransactions": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "eventId": "dj-raj-2026-apr",
        "customerName": "John Doe",
        "qty": 2,
        "amount": 500,
        "status": "issued",
        "createdAt": "2026-04-16T14:30:22+05:30",
        "qrUrl": "https://namastekalyan.com/verify?txn=CASH-TXN-20260416143022-A1B2C3D4&sig=..."
      }
    ],
    "handoverHistory": []
  }
}
```

### RESPONSE (Failure - Missing Auth)
```json
{
  "ok": false,
  "error": "UNAUTHORIZED",
  "message": "Invalid or missing authentication token"
}
```

---

## TEST 2: `admin_request_cash_handover` (POST)
**Purpose**: Admin requests to hand over collected cash to superadmin

### REQUEST
```json
{
  "action": "admin_request_cash_handover",
  "token": "eyJhbGci...(JWT token)",
  "ledgerDate": "2026-04-16"
}
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "admin_request_cash_handover",
  "message": "Cash handover request submitted",
  "summary": {
    "ledgerDate": "2026-04-16",
    "totals": {
      "totalAmount": 5000,
      "issuedAmount": 5000,
      "pendingAmount": 500,
      "handoverApprovedAmount": 0,
      "cancelledAmount": 0
    },
    "recentTransactions": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "amount": 500,
        "status": "issued",
        "createdAt": "2026-04-16T14:30:22+05:30"
      }
    ],
    "handoverHistory": [
      {
        "batchKey": "BATCH-20260416-9000000001-001",
        "ledgerDate": "2026-04-16",
        "adminUsername": "9000000001",
        "totalAmount": 5000,
        "entryCount": 1,
        "status": "pending",
        "requestedAt": "2026-04-16T14:35:00+05:30",
        "approvedAt": null
      }
    ]
  }
}
```

---

## TEST 3: `admin_request_cash_cancel` (POST)
**Purpose**: Admin requests cancellation of an issued cash pass

### REQUEST
```json
{
  "action": "admin_request_cash_cancel",
  "token": "eyJhbGci...(JWT token)",
  "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
  "reason": "Customer requested refund"
}
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "admin_request_cash_cancel",
  "message": "Cancellation request recorded",
  "summary": {
    "ledgerDate": "2026-04-16",
    "totals": {
      "totalAmount": 5000,
      "issuedAmount": 4500,
      "pendingAmount": 500,
      "handoverApprovedAmount": 0,
      "cancelledAmount": 500
    },
    "recentTransactions": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "amount": 500,
        "status": "cancel_requested",
        "createdAt": "2026-04-16T14:30:22+05:30"
      }
    ]
  }
}
```

### RESPONSE (Failure - Transaction Not Found)
```json
{
  "ok": false,
  "error": "TRANSACTION_NOT_FOUND",
  "message": "Transaction not found in system"
}
```

---

## TEST 4: `admin_cash_summary` (GET)
**Purpose**: Get admin's daily cash collection summary

### REQUEST
```
GET /backend/index.php?action=admin_cash_summary&token=eyJhbGci...&ledgerDate=2026-04-16
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "admin_cash_summary",
  "ledgerDate": "2026-04-16",
  "adminUsername": "9000000001",
  "summary": {
    "totals": {
      "totalAmount": 5000,
      "issuedAmount": 4500,
      "pendingAmount": 500,
      "handoverApprovedAmount": 0,
      "cancelledAmount": 500
    },
    "recentTransactions": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "eventId": "dj-raj-2026-apr",
        "customerName": "John Doe",
        "amount": 500,
        "status": "cancel_requested",
        "createdAt": "2026-04-16T14:30:22+05:30"
      },
      {
        "transactionId": "CASH-TXN-20260416140500-X9Y8Z7W6",
        "eventId": "dj-raj-2026-apr",
        "customerName": "Jane Smith",
        "amount": 4500,
        "status": "issued",
        "createdAt": "2026-04-16T14:05:00+05:30"
      }
    ],
    "handoverHistory": [
      {
        "batchKey": "BATCH-20260416-9000000001-001",
        "totalAmount": 5000,
        "status": "pending",
        "requestedAt": "2026-04-16T14:35:00+05:30"
      }
    ]
  }
}
```

### RESPONSE (Unauthorized)
```json
{
  "ok": false,
  "error": "UNAUTHORIZED",
  "message": "Permission denied: eventGuests or cashier role required"
}
```

---

## TEST 5: `superadmin_approve_cash_handover` (POST)
**Purpose**: Superadmin approves pending cash handover

### REQUEST
```json
{
  "action": "superadmin_approve_cash_handover",
  "token": "eyJhbGci...(superadmin JWT token)",
  "batchKey": "BATCH-20260416-9000000001-001"
}
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "superadmin_approve_cash_handover",
  "message": "Cash handover approved successfully",
  "dashboard": {
    "pendingHandovers": [],
    "cancelRequests": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "adminUsername": "9000000001",
        "amount": 500,
        "reason": "Customer requested refund",
        "status": "pending",
        "requestedAt": "2026-04-16T14:32:00+05:30"
      }
    ],
    "recentApprovals": [
      {
        "batchKey": "BATCH-20260416-9000000001-001",
        "adminUsername": "9000000001",
        "totalAmount": 5000,
        "status": "handover_approved",
        "approvedAt": "2026-04-16T14:40:00+05:30"
      }
    ]
  }
}
```

### RESPONSE (Batch Not Found)
```json
{
  "ok": false,
  "error": "BATCH_NOT_FOUND",
  "message": "Batch not found or already processed"
}
```

---

## TEST 6: `superadmin_resolve_cash_cancel` (POST)
**Purpose**: Superadmin approves or rejects cancel request

### REQUEST (Approve)
```json
{
  "action": "superadmin_resolve_cash_cancel",
  "token": "eyJhbGci...(superadmin JWT token)",
  "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
  "decision": "approve"
}
```

### RESPONSE (Approved)
```json
{
  "ok": true,
  "action": "superadmin_resolve_cash_cancel",
  "message": "Cancel request approved",
  "result": "cancel_approved",
  "dashboard": {
    "pendingHandovers": [],
    "cancelRequests": [],
    "recentApprovals": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "adminUsername": "9000000001",
        "amount": 500,
        "decision": "approve",
        "approvedAt": "2026-04-16T14:42:00+05:30"
      }
    ]
  }
}
```

### REQUEST (Reject)
```json
{
  "action": "superadmin_resolve_cash_cancel",
  "token": "eyJhbGci...(superadmin JWT token)",
  "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
  "decision": "reject"
}
```

### RESPONSE (Rejected)
```json
{
  "ok": true,
  "action": "superadmin_resolve_cash_cancel",
  "message": "Cancel request rejected",
  "result": "cancel_rejected",
  "dashboard": {
    "pendingHandovers": [],
    "cancelRequests": [],
    "recentApprovals": [
      {
        "transactionId": "CASH-TXN-20260416143022-A1B2C3D4",
        "adminUsername": "9000000001",
        "amount": 500,
        "decision": "reject",
        "approvedAt": "2026-04-16T14:42:00+05:30"
      }
    ]
  }
}
```

---

## TEST 7: `superadmin_cash_dashboard` (GET)
**Purpose**: Superadmin sees all pending handovers and cancels

### REQUEST
```
GET /backend/index.php?action=superadmin_cash_dashboard&token=eyJhbGci...
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "superadmin_cash_dashboard",
  "dashboard": {
    "pendingHandovers": [
      {
        "batchKey": "BATCH-20260416-9000000002-001",
        "ledgerDate": "2026-04-16",
        "adminUsername": "9000000002",
        "totalAmount": 3500,
        "entryCount": 2,
        "status": "pending",
        "requestedAt": "2026-04-16T15:00:00+05:30"
      }
    ],
    "cancelRequests": [
      {
        "transactionId": "CASH-TXN-20260416135000-Q1W2E3R4",
        "adminUsername": "9000000002",
        "amount": 750,
        "reason": "Duplicate entry",
        "status": "pending",
        "requestedAt": "2026-04-16T13:52:00+05:30"
      }
    ],
    "recentApprovals": [
      {
        "batchKey": "BATCH-20260415-9000000001-003",
        "adminUsername": "9000000001",
        "totalAmount": 8500,
        "status": "handover_approved",
        "approvedAt": "2026-04-15T18:30:00+05:30"
      }
    ]
  }
}
```

---

## TEST 8: `event_guest_report` (GET)
**Purpose**: Get detailed guest list and payment breakdown for event

### REQUEST
```
GET /backend/index.php?action=event_guest_report&token=eyJhbGci...&eventId=dj-raj-2026-apr
```

### RESPONSE (Success)
```json
{
  "ok": true,
  "action": "event_guest_report",
  "report": {
    "eventSummary": [
      {
        "eventId": "dj-raj-2026-apr",
        "eventTitle": "DJ Raj Spring 2026",
        "totalRegistrations": 250,
        "freeRegistrations": 80,
        "paidRegistrations": 170,
        "totalGuests": 520
      },
      {
        "eventId": "holi-2026",
        "eventTitle": "Holi Celebration",
        "totalRegistrations": 180,
        "freeRegistrations": 60,
        "paidRegistrations": 120,
        "totalGuests": 380
      }
    ],
    "totals": {
      "registrations": 430,
      "guests": 900,
      "razorpayCollectedAmount": 95500,
      "razorpayPendingAmount": 5000,
      "cashCollectedAmount": 12000,
      "freeEventAmount": 0
    },
    "guests": [
      {
        "transactionId": "TXN-20260416-001",
        "eventId": "dj-raj-2026-apr",
        "eventTitle": "DJ Raj Spring 2026",
        "customerName": "John Doe",
        "customerEmail": "john@example.com",
        "attendeeNames": "John Doe, Jane Doe",
        "qty": 2,
        "bookingType": "Paid",
        "collectionType": "Razorpay",
        "amount": 500,
        "currency": "INR",
        "status": "paid",
        "paymentId": "pay_JB5d8wX69z0F8b",
        "orderId": "order_JB5d8wW54z0F8a",
        "checkedInAt": "2026-04-16T19:30:00+05:30",
        "createdAt": "2026-04-15T10:20:00+05:30"
      },
      {
        "transactionId": "TXN-20260416-002",
        "eventId": "dj-raj-2026-apr",
        "eventTitle": "DJ Raj Spring 2026",
        "customerName": "Alice Johnson",
        "customerEmail": "alice@example.com",
        "attendeeNames": "Alice Johnson",
        "qty": 1,
        "bookingType": "Free",
        "collectionType": "Free",
        "amount": 0,
        "currency": "INR",
        "status": "confirmed",
        "paymentId": null,
        "orderId": null,
        "checkedInAt": "2026-04-16T20:15:00+05:30",
        "createdAt": "2026-04-16T08:00:00+05:30"
      }
    ],
    "razorpayReconciliation": {
      "totals": {
        "collectedAmount": 95500,
        "pendingAmount": 5000
      },
      "entries": [
        {
          "orderId": "order_JB5d8wW54z0F8a",
          "paymentId": "pay_JB5d8wX69z0F8b",
          "transactionId": "TXN-20260416-001",
          "eventId": "dj-raj-2026-apr",
          "customerName": "John Doe",
          "amount": 500,
          "currency": "INR",
          "status": "paid",
          "createdAt": "2026-04-15T10:20:00+05:30",
          "paidAt": "2026-04-15T10:25:00+05:30"
        }
      ]
    }
  }
}
```

### RESPONSE (Unauthorized)
```json
{
  "ok": false,
  "error": "UNAUTHORIZED",
  "message": "Permission denied: eventGuests role required"
}
```

---

## TEST 9: `event_transactions_report` (GET)
**Purpose**: Get transaction-focused view with Razorpay reconciliation

### REQUEST
```
GET /backend/index.php?action=event_transactions_report&token=eyJhbGci...&eventId=dj-raj-2026-apr
```

### RESPONSE (Success - Same structure as event_guest_report)
```json
{
  "ok": true,
  "action": "event_transactions_report",
  "report": {
    "eventSummary": [...],
    "totals": {...},
    "guests": [...],
    "razorpayReconciliation": {...}
  },
  "items": [
    {
      "transactionId": "TXN-20260416-001",
      "eventId": "dj-raj-2026-apr",
      "customerName": "John Doe",
      "amount": 500,
      "status": "paid"
    }
  ]
}
```

---

## ERROR RESPONSES (All Endpoints)

### 400 - Missing Required Parameter
```json
{
  "ok": false,
  "error": "BAD_REQUEST",
  "message": "Missing required parameter: eventId"
}
```

### 401 - Invalid Token
```json
{
  "ok": false,
  "error": "INVALID_TOKEN",
  "message": "Token is invalid or expired"
}
```

### 403 - Permission Denied
```json
{
  "ok": false,
  "error": "FORBIDDEN",
  "message": "User role does not have permission for this action"
}
```

### 500 - Database Error
```json
{
  "ok": false,
  "error": "DATABASE_ERROR",
  "message": "Failed to save transaction"
}
```

---

## KEY NOTES FOR DEPLOYMENT

1. **Auth Headers**: All endpoints except public reads require valid JWT token
2. **Permissions Required**:
   - Cashier endpoints (1-3): `cashier` role
   - Summary endpoints (4): `eventGuests` or `cashier` role
   - Superadmin endpoint (5-6): `superadmin` role
   - Reports (7-9): `eventGuests` role

3. **Database Tables Used**:
   - `event_transactions` - stores all transaction records
   - `admin_cash_ledger` - tracks admin cash issuance/handover
   - `superadmin_cash_ledger` - tracks superadmin approvals
   - `events` - event details

4. **QR Code Format**:
   - Generated as: `{transaction-id}|{event-id}|{payment-id}|sig={hmac-sha256}`
   - Signed with: `EVENT_QR_SIGNING_SECRET` env variable

5. **Transaction IDs**:
   - Format: `CASH-TXN-{YYYYMMDDHHMMSS}-{random-hex8}`
   - Example: `CASH-TXN-20260416143022-A1B2C3D4`

6. **Batch Keys**:
   - Format: `BATCH-{YYYYMMDD}-{admin-username}-{sequence}`
   - Example: `BATCH-20260416-9000000001-001`

---

## NEXT STEPS: DEPLOYMENT

To make these endpoints live:

1. Copy updated controller files to production backend:
   - `src/Controllers/CashierController.php`
   - `src/Controllers/EventController.php`

2. Run tests with same script against production URL

3. Verify all 9 endpoints return proper data (not NOT_IMPLEMENTED)

4. Monitor logs for any database connection issues

