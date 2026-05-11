# Menu Blocker + Home Spin Wheel Implementation Prompt

Use this as a full transfer prompt for another project to implement the same system behavior as this project.

## How To Use

1. Copy the prompt block below into the other workspace.
2. Ask the agent to implement end-to-end with parity, not partial UI mockups.
3. Keep mobile compatibility as the highest-priority non-functional requirement.

## Prompt To Give In Other Workspace

```md
Implement a complete Menu Blocker + Spin Wheel system for the Home page, matching production behavior from the reference project.

This is mandatory-gate UX:
- Spin wheel overlay appears on Home page.
- User cannot close/remove/dismiss blocker until form is submitted and spin flow is complete.
- Menu/content behind blocker stays locked until gate is completed.

System must include:
1. Form-gated spin entry.
2. 24-hour cooldown logic.
3. Backend-selected prize, frontend animation only.
4. Winner coupon flow.
5. Try-again surprise request flow.
6. CRM push on lead submit.
7. Admin control panel for blocker placement/settings and spin operations.
8. Mobile-first responsive UX.

Use PHP + MySQL backend, JS frontend, and action-based APIs.

------------------------------------------------------------

# 1) Core UX Requirements (Home Page)

Implement a full-screen overlay on Home page with 3 steps:

Step 1: Form
- Top heading text (configurable from settings).
- Subtext (configurable).
- Inputs:
  - Name (required)
  - Country code + phone (required)
  - DOB (optional/required as per setting)
  - Anniversary (optional)
- Submit button label: Submit and Continue (configurable)
- Staff bypass toggle (optional, controlled by settings)

Step 2: Wheel
- Wheel canvas with segments.
- Spin button once only.
- Prize is already decided by backend submit response.
- Wheel animation lands on backend prize.

Step 3: Result
- Show result text.
- If winner: show coupon code + copy + send-to-admin actions.
- If try again: show WhatsApp surprise-request action.
- Continue button unlocks page.

Hard rule:
- Overlay cannot be removed by close icon, ESC, or outside click before valid flow completion.

------------------------------------------------------------

# 2) Form And Validation Contract

Required validation:
- Name: non-empty, 2+ chars.
- Phone: digits only, local format validation by country.
- Date fields: DD/MM/YYYY display with valid date parsing.

Store normalized values:
- countryCode digits (e.g. 91)
- phone digits only
- dateOfBirth ISO (YYYY-MM-DD) when available
- dateOfAnniversary ISO when available

UI error states:
- Inline field error text.
- Form status message area.
- Disable submit while request is in flight.

------------------------------------------------------------

# 3) Backend Logic (Source of Truth)

Lead submit API action: submit_lead
- Validates input.
- Checks latest completed spin for phone.
- Enforces 24-hour cooldown.
- Picks prize server-side.
- Creates lead record.
- Creates coupon only for winning prize.
- Sets CRM sync status pending.
- Upserts canonical contact.
- Pushes to CRM and logs attempts.

Complete spin action: complete_spin
- Requires leadId + phone.
- Sets spin_completed_at if not already set.
- Returns retryAfter timestamp.
- Triggers winner notification if configured.

Never trust frontend prize selection.
Frontend only animates using backend result.

------------------------------------------------------------

# 4) Prize + Coupon Rules

Winner prize list example:
- Dessert on the House
- Mocktail on the House
- Aerated Drink on the House
- Starter on the House
- 10% OFF
- 15% OFF
- 20% OFF
- 25% OFF

Try again prize:
- Try Again

Coupon behavior:
- Generate only for winner prizes.
- Persist to coupon_code.
- Do not generate for Try Again unless admin issues surprise reward later.

# 4.1) Scheme Logic (Multi-Tier Priority-Based Coupon Distribution)

Implement multi-tier coupon scheme based on customer milestone tiers.

**The Milestone Structure (Priority Order):**

| Priority | Rule | Example Customer #s | Prize |
|----------|------|---------------------|-------|
| 1 | Every 500th | 500, 1000, 1500, ... | 25% OFF |
| 2 | Every 300th | 300, 600, 900, ... | 20% OFF |
| 3 | Every 125th | 125, 250, 375, ... | 15% OFF |
| 4 | Every 51st | 51, 101, 151, ... | 10% OFF |
| 5 | Every 49th | 49, 99, 149, ... | Starter on the House |
| 6a | Every 18th (Alternating A) | 18, 38, 58, 78, ... | Dessert on the House |
| 6b | Every 18th (Alternating B) | 28, 48, 68, 88, ... | Aerated Drink on the House |
| 7 | Every 10th | 10, 20, 30, 40, ... | Mocktail on the House |
| 8 | All others | — | Try Again |

**Rules:**

1. Customer index is 1-based (first customer = 1, second = 2, etc.).
2. Check priorities in strict order (highest priority first).
3. On 500th customer: check if divisible by 500 → assign 25% OFF.
4. On 300th customer: check if divisible by 300 → assign 20% OFF.
5. Continue down the priority chain.
6. For every 18th milestone family (priority 6): use alternating pattern.
  - If (customerIndex - 18) % 20 === 0 → Dessert on the House.
  - Else if (customerIndex - 28) % 20 === 0 → Aerated Drink on the House.
7. For every 10th: if divisible by 10 → Mocktail on the House.
8. If no tier matches → Try Again.
9. Only generate coupon for non-"Try Again" prizes.
10. Scheme logic must execute server-side only.

**Admin Settings for Scheme:**

Store in app settings:
```
spinMilestoneScheme: {
  tiers: [
    { priority: 1, interval: 500, prize: '25% OFF' },
    { priority: 2, interval: 300, prize: '20% OFF' },
    { priority: 3, interval: 125, prize: '15% OFF' },
    { priority: 4, interval: 51, prize: '10% OFF' },
    { priority: 5, interval: 49, prize: 'Starter on the House' },
    { priority: 6, interval: 18, type: 'alternating', prizes: ['Dessert on the House', 'Aerated Drink on the House'] },
    { priority: 7, interval: 10, prize: 'Mocktail on the House' }
  ],
  enabled: true,
  dailyCap: null,
  tryAgainLabel: 'Try Again'
}
```

**Reference Pseudocode (Server-Side):**

```php
private function decidePrizeByMilestoneScheme(int $customerIndex, array $scheme): string
{
    $tryAgain = (string)($scheme['tryAgainLabel'] ?? 'Try Again');
    $tiers = $scheme['tiers'] ?? [];

    // Sort by priority ascending (highest priority = check first)
    usort($tiers, fn($a, $b) => ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999));

    foreach ($tiers as $tier) {
        $priority = (int)($tier['priority'] ?? 999);
        $interval = (int)($tier['interval'] ?? 0);
        
        if ($interval <= 0) continue;

        // Priority 1-5, 7: Simple interval check
        if ($priority !== 6) {
            if ($customerIndex % $interval === 0) {
                return (string)($tier['prize'] ?? $tryAgain);
            }
        }
        
        // Priority 6: Alternating pattern (18, 28, 38, 48...)
        if ($priority === 6) {
            $prizes = $tier['prizes'] ?? ['Dessert on the House', 'Aerated Drink on the House'];
            
            // Pattern: 18, 38, 58, 78... (first prize)
            //          28, 48, 68, 88... (second prize)
            $offset1 = 18; // First alternation
            $offset2 = 28; // Second alternation
          $cycle = 20;   // Full alternation cycle
            
            if (($customerIndex - $offset1) % $cycle === 0) {
                return (string)$prizes[0];
            }
            if (($customerIndex - $offset2) % $cycle === 0) {
                return (string)$prizes[1];
            }
        }
    }

    return $tryAgain;
}

// Usage in submitLead:
$customerIndex = $this->leads->countTotalLeads() + 1;
$scheme = $this->settings->get('spinMilestoneScheme', []);
$prize = $this->decidePrizeByMilestoneScheme($customerIndex, $scheme);
$couponCode = ($prize !== 'Try Again') ? $this->generateCouponCode($phone) : '';
```

**Examples from Scheme:**

- Customer #500 → Check 500 % 500 = 0 ✓ → **25% OFF**
- Customer #300 → Check 300 % 300 = 0 ✓ → **20% OFF**
- Customer #125 → Check 125 % 125 = 0 ✓ → **15% OFF**
- Customer #51 → Check 51 % 51 = 0 ✓ → **10% OFF**
- Customer #49 → Check 49 % 49 = 0 ✓ → **Starter on the House**
- Customer #18 → Check (18 - 18) % 20 = 0 ✓ → **Dessert on the House**
- Customer #28 → Check (28 - 28) % 20 = 0 ✓ → **Aerated Drink on the House**
- Customer #38 → Check (38 - 18) % 20 = 0 ✓ → **Dessert on the House**
- Customer #48 → Check (48 - 28) % 20 = 0 ✓ → **Aerated Drink on the House**
- Customer #40 → Check 40 % 10 = 0 ✓ → **Mocktail on the House**
- Customer #1-9, 11-18, 21-27, etc. → → **Try Again**

**Admin Panel for Scheme Configuration:**

In admin panel, allow editing tiers:
- Edit interval for each priority.
- Edit prize label for each priority.
- Enable/disable specific tiers.
- Set daily winner cap (if needed).
- Preview customer examples matching each tier.
- Audit trail for scheme changes.

**Advanced Options (Optional):**

- **Campaign Mode:** Temporarily boost lower tiers (e.g., Every 10th gives 20% OFF instead of Mocktail for 1 week).
- **Daily Cap:** Stop issuing high-value coupons after N winners/day (store in daily audit).
- **Seasonal Adjustments:** Different schemes for different periods (restaurant opening hours, holidays, etc.).


------------------------------------------------------------

# 5) Cooldown Rules

- Cooldown duration: 24 hours from spin_completed_at.
- If cooldown active:
  - submit_lead returns COOLDOWN_ACTIVE with retryAfter and retryAfterEpochMs.
  - frontend must block new spin and unlock page or show cooldown state per product decision.

Client-side cooldown cache:
- Keep local state to reduce repeat prompts.
- Backend remains source of truth.

------------------------------------------------------------

# 6) CRM Push Logic (Mandatory)

CRM push must happen on lead submission path only.

Flow:
1. Build CRM payload from normalized lead.
2. Include API token + endpoint from secure backend config.
3. Execute attempt.
4. Retry once on failure.
5. Save status/code/message to leads table.
6. Save detailed attempt logs in crm_push_logs.
7. Update canonical contact sync fields.

Do not trigger CRM push from:
- coupon redemption
- coupon regeneration
- surprise issuance

------------------------------------------------------------

# 7) Admin Panel Control System

Implement admin controls for blocker + spin operations.

## 7.1 Blocker Placement Control (Admin Routing)

Module: Routing and QR Center style module
Admin can enable/disable blocker on any page dynamically.

### Available Pages for Blocker Placement:
- home
- menu
- cocktail
- events
- reservations
- custom-page-1 (extendable)

### Settings Storage:
Save blocker page configuration in app settings store under key:
```
spinBlockerConfig: {
  enabledPages: ['home', 'menu'],
  disabledPages: ['cocktail', 'events'],
  defaultEnabled: true,
  globalDisable: false
}
```

### Admin UI for Routing Control:

In admin panel (routing module), display:
- Toggle list for each page.
- Checkbox for "Enable on Home".
- Checkbox for "Enable on Menu".
- Checkbox for "Enable on Cocktails".
- Checkbox for "Enable on Events".
- Checkbox for "Enable on Reservations".
- Save and apply button.

When admin clicks Save:
1. Collect checked pages.
2. POST action: `admin_update_blocker_pages` with page list.
3. Backend updates settings in app settings store.
4. Frontend clears cached settings and reloads on next navigation.

### Frontend Page Detection and Blocker Initialization:

```javascript
async function initMenuBlockerIfEnabled() {
  // Detect current page
  const currentPage = detectCurrentPage();
  
  // Load settings (cached or fresh)
  const settings = await loadBlockerSettings();
  
  // Check if blocker is globally disabled
  if (settings.globalDisable === true) {
    return; // Don't load blocker
  }
  
  // Check if current page is in enabled list
  const enabledPages = settings.enabledPages || [];
  if (!enabledPages.includes(currentPage)) {
    return; // Page not configured for blocker
  }
  
  // Initialize and mount blocker overlay
  initializeMenuBlocker(settings);
}

function detectCurrentPage() {
  const path = window.location.pathname.toLowerCase();
  
  if (path === '/' || path === '/index.html' || path === '') {
    return 'home';
  }
  if (path.startsWith('/menu')) {
    return 'menu';
  }
  if (path.startsWith('/cocktail')) {
    return 'cocktail';
  }
  if (path.startsWith('/events')) {
    return 'events';
  }
  if (path.startsWith('/reservations')) {
    return 'reservations';
  }
  
  return 'other'; // Unmapped page
}

async function loadBlockerSettings() {
  // Try cache first
  const cached = sessionStorage.getItem('blocker-settings-cache');
  if (cached) {
    return JSON.parse(cached);
  }
  
  // Fetch from API
  const res = await fetch('/?action=get_blocker_settings');
  const data = await res.json();
  
  // Cache for session
  sessionStorage.setItem('blocker-settings-cache', JSON.stringify(data));
  return data;
}
```

### Backend Routing Update Action:

```php
// In LeadController or SettingsController
public function adminUpdateBlockerPages(array $data): array
{
    // Verify admin role
    $this->auth->requireRole('admin');
    
    $enabledPages = array_map('strtolower', $data['enabledPages'] ?? []);
    
    // Validate page names (whitelist)
    $validPages = ['home', 'menu', 'cocktail', 'events', 'reservations'];
    $enabledPages = array_intersect($enabledPages, $validPages);
    
    // Update settings
    $this->settings->set('spinBlockerConfig', [
        'enabledPages' => $enabledPages,
        'disabledPages' => array_diff($validPages, $enabledPages),
        'defaultEnabled' => (bool)($data['defaultEnabled'] ?? false),
        'globalDisable' => (bool)($data['globalDisable'] ?? false),
        'updatedAt' => time(),
        'updatedBy' => $this->auth->currentUserId()
    ]);
    
    return [
        'ok' => true,
        'message' => 'Blocker pages updated successfully',
        'config' => $this->settings->get('spinBlockerConfig')
    ];
}

// In LeadController
public function getBlockerSettings(array $data): array
{
    $config = $this->settings->get('spinBlockerConfig') ?? [
        'enabledPages' => ['home'],
        'disabledPages' => [],
        'defaultEnabled' => true,
        'globalDisable' => false
    ];
    
    return [
        'ok' => true,
        'config' => $config,
        'formTitle' => $this->settings->get('blocker_form_title'),
        'formSubtitle' => $this->settings->get('blocker_form_subtitle'),
        // ... other blocker text settings
    ];
}
```

### Admin Audit Log for Page Changes:

Store routing changes in audit table:
```
blocker_routing_audit:
- id
- changed_at
- changed_by
- old_enabled_pages (JSON)
- new_enabled_pages (JSON)
- action_type (enable | disable | global_toggle)
- notes
```

### How Blocker State Persists Across Page Navigation:

1. User fills form on home page → gets spin result.
2. User navigates to menu page.
3. Menu page checks if blocker is enabled and if user already completed spin today.
4. If already completed (24h cooldown active), blocker does NOT show on menu.
5. If admin disables blocker on menu during user session, blocker unmounts on next page load.

### Mobile Routing Behavior:

On mobile, page detection must account for:
- Mobile menu navigation (hamburger).
- Touch-based page transitions.
- Hash-based routing (#/menu, #/home).

```javascript
function detectCurrentPageMobile() {
  const hash = window.location.hash.substring(1).toLowerCase();
  if (hash) return hash.split('/')[0] || 'home';
  
  return detectCurrentPage(); // Fallback to pathname
}
```

### Extension: Custom Pages

Admin can register custom pages for blocker placement:
```php
$this->settings->set('spinBlockerCustomPages', [
  'promo-page' => 'Special Promo',
  'booking-landing' => 'Booking Landing'
]);
```

Frontend detects and loads:
```javascript
const customPages = settings.customPages || {};
const allValidPages = ['home', 'menu', 'cocktail', 'events', 'reservations', ...Object.keys(customPages)];
```

## 7.2 Blocker content control
Admin editable fields:
- formTitle
- formSubtitle
- submitButtonText
- spinTitle
- spinSubtitle
- resultTitle
- continueButtonText
- winnerHintText
- tryAgainHintText
- staffBypassEnabled
- staffSecretCode
- hotelWhatsappNo

## 7.3 Spin ops in admin
- Verify lead by phone.
- Redeem active reward.
- Regenerate winner coupon.
- Issue surprise reward for try-again user.
- Dashboard stats.

## 7.4 CRM admin workspace
- status endpoint check
- test CRM sync
- list contacts
- list push logs
- backfill contacts
- exports

------------------------------------------------------------

# 8) Mobile Compatibility (Prime Task)

Must be excellent on mobile first.

Required:
- Overlay card fits 320px width devices.
- Inputs and buttons minimum 44px tap targets.
- Date inputs usable on mobile.
- Wheel renders without clipping.
- No horizontal scroll in blocker.
- Safe-area support for notched devices.
- Good contrast and readable text in sunlight.

Performance:
- avoid large blocking JS on initial paint
- lazy-init wheel canvas when step 2 opens
- keep animation smooth on low-end Android

Accessibility:
- aria labels on controls
- focus trap inside overlay
- live regions for status/errors

------------------------------------------------------------

# 9) API Action Contract

Implement or map these actions:
- submit_lead
- complete_spin
- verify
- redeem
- regen_coupon
- admin_dashboard_stats
- admin_crm_panel_status
- admin_test_crm_sync
- admin_list_crm_contacts
- admin_list_crm_push_logs
- admin_backfill_crm_contacts
- admin_export_crm_contacts
- admin_crm_leads_status
- admin_list_crm_leads
- admin_export_crm_leads

All admin actions require:
- token auth
- role checks
- permission checks

------------------------------------------------------------

# 10) Data Model Requirements

leads table minimum:
- id
- created_at
- spin_completed_at
- name
- phone
- prize
- status
- date_of_birth
- date_of_anniversary
- source
- visit_count
- coupon_code
- surprise_reward_label
- surprise_coupon_code
- surprise_issued_at
- surprise_issued_by
- surprise_redeemed_at
- crm_sync_status
- crm_sync_code
- crm_sync_message
- redeemed_at

crm_contacts table minimum:
- phone unique
- name
- first_seen_at
- last_seen_at
- latest_source
- latest_lead_id
- total_submissions
- latest_crm_sync_status
- latest_crm_sync_code
- latest_crm_sync_message
- last_crm_attempted_at
- last_crm_pushed_at

crm_push_logs table minimum:
- lead_id
- contact_id
- phone
- trigger_source
- attempted
- success
- http_code
- attempt_count
- response_message
- request_payload_json
- attempts_json

------------------------------------------------------------

# 11) Frontend Reference Snippet (Menu Blocker)

```javascript
async function submitLeadAndOpenWheel(formData) {
  const payload = {
    action: 'submit_lead',
    name: formData.name,
    phone: formData.phone,
    countryCode: formData.countryCode,
    dateOfBirth: formData.dateOfBirth,
    dateOfAnniversary: formData.dateOfAnniversary,
    source: 'menu-blocker-web'
  };

  const res = await apiPost(payload);
  if (!res.ok) {
    if (res.error === 'COOLDOWN_ACTIVE') {
      showCooldownState(res.retryAfter);
      return;
    }
    throw new Error(res.message || 'Submit failed');
  }

  state.leadId = Number(res.leadId || 0);
  state.phone = formData.phone;
  state.prize = String(res.prize || 'Try Again');
  state.couponCode = String(res.couponCode || '');

  openSpinStep();
}

async function finalizeSpin() {
  const done = await apiPost({
    action: 'complete_spin',
    leadId: state.leadId,
    phone: state.phone
  });

  if (!done.ok) {
    throw new Error(done.message || 'Could not complete spin');
  }

  renderResult(state.prize, state.couponCode);
}
```

------------------------------------------------------------

# 12) Backend Reference Snippet (Lead submit + CRM)

```php
public function submitLead(array $data): array
{
    $prepared = $this->prepareLeadInput($data);

    if ($prepared['name'] === '' || !Validator::phone($prepared['phone'])) {
        return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Valid name and phone required.'];
    }

    $cooldownLead = $this->leads->findLatestCompletedByPhone($prepared['phone']);
    $cooldown = $this->buildSpinCooldownState($cooldownLead);
    if ($cooldown['active']) {
        return [
            'ok' => false,
            'error' => 'COOLDOWN_ACTIVE',
            'retryAfter' => $cooldown['retryAfter'],
            'retryAfterEpochMs' => $cooldown['retryAfterEpochMs']
        ];
    }

    $prize = $this->pickPrizeByLeadNumber($this->leads->countRows() + 1);
    $couponCode = $this->isWinningPrize($prize) ? $this->generateCouponCode($prepared['phone']) : '';

    $leadId = $this->leads->create([
        'name' => $prepared['name'],
        'phone' => $prepared['phone'],
        'prize' => $prize,
        'status' => 'Unredeemed',
        'coupon_code' => $couponCode,
        'crm_sync_status' => 'Pending'
    ]);

    $contact = $this->upsertCanonicalContact($leadId, [
        'name' => $prepared['name'],
        'phone' => $prepared['phone'],
        'source' => $prepared['source'],
        'crm_sync_status' => 'Pending'
    ]);

    $crmSync = $this->syncLeadToCrm($leadId, [
        'name' => $prepared['name'],
        'phone' => $prepared['phone'],
        'country_code' => $prepared['countryCode'],
        'prize' => $prize,
        'status' => 'Unredeemed',
        'source' => $prepared['source']
    ], $contact);

    return [
        'ok' => true,
        'result' => 'success',
        'leadId' => $leadId,
        'prize' => $prize,
        'couponCode' => $couponCode,
        'crmSync' => $crmSync
    ];
}
```

------------------------------------------------------------

# 13) Home Page Integration Rules

- Initialize settings loader script before blocker script.
- Blocker script reads:
  - page enablement
  - WhatsApp admin number
  - staff bypass code
  - blocker text content
- Blocker must activate only on configured pages.
- For this task, home page enablement is mandatory by default.

------------------------------------------------------------

# 14) Acceptance Checklist

Do not mark done unless all pass:

1. On Home page, blocker always appears for new users.
2. User cannot bypass blocker before valid form submit + spin completion.
3. Backend cooldown works (24h).
4. Prize comes from backend and wheel lands on same prize.
5. Winner gets coupon code; try again has no winner coupon.
6. CRM push runs on submit_lead and logs attempts.
7. Admin can control blocker placement and text/settings.
8. Admin spin operations (verify/redeem/regen/surprise) work.
9. Mobile UX is smooth and fully usable.
10. No regressions in menu/home loading performance.

------------------------------------------------------------

# 15) Deliverables

Provide:
- frontend blocker module
- settings initializer module
- backend lead/spin endpoints
- CRM sync service integration
- admin control UI for blocker and spin
- migration updates
- docs with request/response examples
- test checklist with mobile device matrix

Implement complete production behavior with parity to reference system.
```

## Reference Source Mapping In This Workspace

Frontend blocker and initialization:
- public/js/menu-blocker.js
- public/js/menu-blocker-init.js

Admin control module:
- public/js/admin-modules/landing-routing.js

Lead and spin backend:
- app/Controllers/LeadController.php
- app/Services/LeadService.php
- app/Routes/ActionRouter.php

CRM backend:
- app/Services/CrmService.php
- app/Services/LeadService.php
- public/js/admin-modules/crm-panel.js
- public/js/admin-modules/crm-leads.js

Existing reference documentation:
- docs/admin/MENU_BLOCKER_SPIN_REDEEM_FLOW.md
