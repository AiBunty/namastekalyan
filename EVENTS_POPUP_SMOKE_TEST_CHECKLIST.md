# Events Popup Smoke-Test Checklist

## Pre-check
- Confirm deployed Apps Script URL in data-config.js points to latest web app deployment.
- Confirm EVENTS sheet exists and has at least one active row.
- Confirm active row has valid Event ID, Is Active = Yes, and Popup Enabled = Yes.

## API sanity
- Open: `<APPS_SCRIPT_URL>?action=events_list&limit=4`
- Verify `ok: true` and at least one item in `items`.
- Open: `<APPS_SCRIPT_URL>?action=event_popup`
- Verify `ok: true`, `found: true`, and event object returned.
- Open: `<APPS_SCRIPT_URL>?action=event_detail&eventId=<EVENT_ID>`
- Verify `ok: true`, `found: true`, and full event payload returned.

## Homepage section behavior
- Load home page and scroll to Events section.
- Verify cards are rendered from sheet data (title/description/image).
- If CTA URL is empty, verify card opens `event.html?eventId=<id>` fallback.
- If Badge Text exists, verify badge is visible on card.

## Popup timing behavior
- Hard refresh homepage in a clean session.
- Wait ~8 seconds.
- Verify popup appears for active popup-enabled event.
- Close popup and confirm page remains usable.

## Spin-to-popup behavior
- Complete menu blocker flow through spin result.
- Verify popup check runs after spin completion.
- If cooldown/session allows, popup appears.

## Cooldown precedence behavior
- Set `popupDelayHours = 2`, `popupCooldownHours = 24` for same event.
- Trigger popup once.
- Refresh page immediately.
- Verify popup does NOT show again (24-hour cooldown wins).
- Set `popupCooldownHours = 0` and keep `popupDelayHours = 2`.
- Trigger popup once.
- Refresh page immediately.
- Verify popup does NOT show for 2 hours.

## Show once per session behavior
- Set `showOncePerSession = Yes`, cooldown fields = 0.
- Trigger popup once.
- Reload same tab/session.
- Verify popup does NOT show again.
- Open in new private/incognito session.
- Verify popup is eligible again.

## Event detail landing-page behavior
- Open `event.html?eventId=<EVENT_ID>`.
- Verify title, subtitle, description, badge, media render correctly.
- Verify CTA button appears only when CTA URL is provided.
- Test with invalid eventId.
- Verify graceful error message is shown.

## Quick reset tips for re-testing
- Clear localStorage keys starting with `nk_event_last_shown_v1_`.
- Clear sessionStorage keys starting with `nk_event_seen_session_v1_`.
- Use Incognito window for clean-session checks.
