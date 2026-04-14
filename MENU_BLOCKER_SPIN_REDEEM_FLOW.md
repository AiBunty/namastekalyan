# Namaste Kalyan - Full Owner Flow (Menu Blocker, Spin Wheel, Coupon, Redemption)

## 1. Business Objective
This system ensures three outcomes:
1. Collect every walk-in lead before menu access.
2. Run fair and predictable reward milestones.
3. Redeem rewards in a controlled, verifiable process using coupon codes.

## 2. Full Journey Overview
1. Customer opens menu page.
2. Menu blocker asks for details.
3. Backend validates whether the customer can spin (24-hour rule).
4. Backend assigns prize by global counter rules.
5. If winner, backend creates coupon code and stores it.
6. Spin wheel animation lands on backend-selected prize.
7. Customer can:
- Copy coupon code.
- Send redemption request to hotel admin on WhatsApp with full prefilled details.
8. Staff verifies customer in admin page.
9. Staff redeems coupon once.

## 3. Menu Blocker Input Stage
Customer form fields:
1. Name
2. Country code + mobile
3. Date of Birth (DD/MM/YYYY)
4. Date of Anniversary (optional, DD/MM/YYYY)

Validation:
1. Name required.
2. Mobile required (India: 10 digits, other countries: 6-15 digits).
3. Date format validation for DOB and anniversary.

## 4. Spin Eligibility Rule (24-Hour Lock)
Logic:
1. If same phone already spun within last 24 hours, no new spin is granted.
2. If last spin is older than 24 hours, customer can spin again.

Enforced in both places:
1. Frontend local cooldown state.
2. Backend timestamp check (source of truth).

## 5. Prize Calculation Logic
Prize is selected by global lead row counter with strict priority.

Priority order (top to bottom):
1. Every 500th customer: 25% OFF
2. Every 300th customer: 20% OFF
3. Every 125th customer: 15% OFF
4. Every 51st customer: 10% OFF (rows 51, 101, 151, 201, ...)
5. Every 49th customer: Starter on the House (rows 49, 99, 149, 199, ...)
6. Every 18th customer (alternating):
   - Dessert on the House (rows 18, 38, 58, 78, ...)
   - Aerated Drink on the House (rows 28, 48, 68, 88, ...)
7. Every 10th customer: Mocktail on the House (rows 10, 20, 30, 40, ...)
8. All other customers: Try Again

**Note on Every 18th Alternation:**
- Starts at row 18 (Dessert)
- Alternates every 10 rows (row 28 = Aerated, row 38 = Dessert, row 48 = Aerated, etc.)
- Pattern: even cycle index = Dessert, odd cycle index = Aerated

Important guarantee:
1. One customer receives only one reward per spin.

## 6. Coupon Code Generation
Coupon code is generated only for winners (not for Try Again).

Format:
NK-<TYPE>-<ROW>-<PHONE_LAST4>

Examples:
1. NK-DESS-00120-9999
2. NK-MOCK-00010-4321
3. NK-OFF20-00300-5512

Type mapping:
1. Dessert on the House -> DESS
2. Mocktail on the House -> MOCK
3. Aerated Drink on the House -> AERA
4. Starter on the House -> STRT
5. 10% OFF -> OFF10
6. 15% OFF -> OFF15
7. 20% OFF -> OFF20
8. 25% OFF -> OFF25

## 7. Customer Result and WhatsApp Redemption Request
After a winning result is displayed, customer gets two actions:
1. Copy Coupon Code
2. Send To Admin WhatsApp

Send To Admin WhatsApp action:
1. Opens WhatsApp chat to hotel admin number.
2. Prefills full redemption message including:
- Customer name
- Customer mobile number (with country code)
- DOB
- Anniversary
- Prize won
- Coupon code
- Request timestamp

Admin number source:
1. `hotelWhatsappNo` in configuration.
2. Fallback to footer contact number if config is unavailable.

## 8. Staff Verification and Redemption Panel
Admin page actions:
1. Check Offer
2. Redeem Coupon
3. Generate Missing Coupon (for old winner rows without code)
4. Copy Coupon Code
5. Send to Hotel WhatsApp

### 8.1 Check Offer
Staff enters customer mobile and clicks Check Offer.

Panel displays:
1. Name
2. Mobile
3. Prize
4. Status (Unredeemed/Redeemed)
5. Coupon code
6. DOB
7. Anniversary
8. Source
9. Timestamp

### 8.2 Redeem Coupon
1. If status is Unredeemed and prize is winner, staff can redeem.
2. Status changes to Redeemed.
3. Further attempts return Already Redeemed.

### 8.3 Generate Missing Coupon
Used for historical winner rows created before coupon feature.

Behavior:
1. If customer not found: not_found.
2. If prize is Try Again: not_winner.
3. If winner without code: generated and stored in sheet.
4. If winner with existing code: returns already_exists.

## 9. Google Sheet Data Model
Leads sheet columns:
1. Timestamp
2. Name
3. Phone
4. Prize
5. Status
6. Date Of Birth
7. Date Of Anniversary
8. Source
9. Visit Count
10. CRM Sync Status
11. CRM Sync Code
12. CRM Sync Message
13. Coupon Code

## 10. CRM Sync Flow
On each new lead:
1. Lead is posted to CRM endpoint.
2. If first call fails, one retry is executed.
3. Result is logged in CRM Sync Status/Code/Message columns.

## 11. Fraud and Control Safeguards
1. 24-hour per-phone spin lock.
2. One-time coupon redemption status.
3. Coupon codes are deterministic and traceable to row and phone suffix.
4. Staff verification is mandatory before redemption.

## 12. Configuration Values
In configuration file:
1. appsScriptUrl: active Apps Script Web App endpoint.
2. hotelWhatsappNo: admin WhatsApp destination for customer and staff send actions.

Current hotel admin WhatsApp:
1. 919371519999

## 13. Owner Summary
This implementation is now production-ready for dine-in campaigns because it combines:
1. Lead capture before menu access.
2. Transparent reward math.
3. Coupon-backed redemption.
4. Customer-to-admin WhatsApp handoff with complete details.
5. Staff-side verification and one-click operational controls.
