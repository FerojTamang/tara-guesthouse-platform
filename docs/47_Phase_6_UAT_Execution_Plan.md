# Phase 6 UAT Execution Plan (S6-007)

## 1. Purpose

This document defines the reproducible User Acceptance Testing (UAT) gate for the Tara Guest House Version 1 application after the completed security, performance, backup/restore, and accessibility hardening work.

UAT verifies business workflows from the perspective of Guests and authenticated Administrators. It does not replace PHPUnit, browser/accessibility QA, security testing, SMTP-provider evidence, cPanel/staging checks, or production approval.

The UAT approval rules follow `27_Testing_Strategy.md`: business requirements must be satisfied, critical defects must be resolved, and stakeholder approval is required before production. The Sprint 5 hardening/deployment plan also requires reproducible test-data scenarios for public and Administrator room, booking, payment, invoice, activity, gallery, CMS, contact, review, notification, and mail workflows.

## 2. Evidence Rules

Record only evidence that was actually observed. Never infer a pass from automated tests.

Use one of these result values for every scenario:

- `PASS` — all acceptance checks were observed successfully.
- `FAIL` — one or more acceptance checks failed.
- `BLOCKED_EXTERNAL` — execution depends on unavailable external infrastructure such as a real SMTP provider or staging/cPanel capability.
- `NOT_RUN` — the scenario has not yet been executed.
- `N/A` — the scenario is not applicable to the approved environment, with a written reason.

Do not record passwords, session cookies, CSRF tokens, signed booking-access values, review tokens, SMTP credentials, private filesystem paths, payment-proof secrets, or real Guest personal data in UAT evidence.

## 3. Environment Preconditions

Before UAT:

1. Use a non-production environment with disposable/test business data.
2. Confirm the application is running from the committed S6-006 baseline or a later reviewed commit.
3. Confirm `composer database:status` reports the expected migration state for that commit.
4. Confirm no pending production-code changes are mixed into the UAT run.
5. Use a dedicated test Guest email address and test phone number.
6. Use a unique run label such as `UAT-RUN-001` in names, subjects, room descriptions, or special requests where the UI permits it.
7. Use a payment method and proof image created for testing. Never use a real payment credential or real Guest payment evidence.
8. Keep the Administrator credential private. Do not paste it into screenshots, notes, issue reports, or Git.
9. Do not reset, truncate, or manually edit the database to force a scenario to pass.

Suggested local base URL:

```text
http://127.0.0.1:8000
```

Use the environment's real HTTPS base URL for staging UAT.

## 4. Test Data Ledger

Fill this table during the run. Record references/IDs only when they are safe to retain.

| Item | UAT value/reference | Cleanup state |
|---|---|---|
| Run label | `UAT-RUN-___` | N/A |
| Test room type |  |  |
| Test amenity |  |  |
| Test room |  |  |
| Test payment method |  |  |
| Booking reference |  | Retain only if approved as test evidence |
| Payment attempt ID/reference |  | Retain only if approved as test evidence |
| Invoice number |  | Retain only if approved as test evidence |
| Test activity |  |  |
| Test gallery category/item |  |  |
| Test CMS page |  |  |
| Test navigation item |  |  |
| Test contact subject |  |  |
| Review record |  |  |

Do not place secure access tokens or raw review invitation tokens in this ledger.

## 5. Public Website UAT

### UAT-PUB-001 — Home, navigation, branding, and public settings

**Steps**

1. Open `/`.
2. Check the brand name/logo, public navigation, footer contact information, and configured social links.
3. Follow every visible primary navigation item.
4. Confirm hidden/unconfigured contact or social values are not rendered as broken links.

**Acceptance**

- Public navigation reaches the intended Version 1 pages.
- Branding/settings agree with the Administrator-configured public values.
- No broken navigation item or placeholder URL is exposed.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-002 — Rooms catalogue and room detail

**Steps**

1. Open `/rooms`.
2. Confirm only public-eligible rooms are shown.
3. Open one room detail page.
4. Confirm name, type, capacity, rate, amenities, primary image/gallery, and availability controls are coherent.
5. In Administrator room management, temporarily verify that an inactive or unpublished test room is absent publicly, then restore the intended test state.

**Acceptance**

- Operationally inactive/unpublished rooms are not publicly bookable.
- Public room data agrees with the Administrator record.
- No internal/private image path is exposed.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-003 — Availability validation and eligible-room filtering

**Steps**

1. Open `/availability`.
2. Submit an invalid date range.
3. Submit guest count outside an eligible room's capacity.
4. Submit a valid future date range and guest count.
5. Open the availability calendar on a returned room.
6. Compare the result with the Administrator booking state for the same dates.

**Acceptance**

- Invalid input is rejected without creating data.
- Only rooms eligible for the complete requested range and guest count are returned.
- Blocking bookings prevent overlapping availability.
- Availability does not change a room's operational status.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-004 — Guest booking creation

**Steps**

1. From a valid availability result, select one room.
2. Confirm dates and guest count.
3. Enter the designated UAT Guest contact details.
4. Add the run label to the special request when possible.
5. Accept the displayed booking terms/policy.
6. Submit once.
7. Save the booking reference in the Test Data Ledger; do not record the secure access token.

**Acceptance**

- Exactly one room is attached to the booking.
- Server-calculated rate/total agrees with the displayed booking snapshot.
- Booking starts as `pending`.
- The same room/date range is immediately blocked from another overlapping successful booking.
- The Administrator booking list/detail shows the same booking facts.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-005 — Payment submission

**Precondition:** `UAT-PUB-004` produced a pending booking and at least one enabled payment method is configured.

**Steps**

1. Open the secure booking-payment flow using the application's provided access mechanism.
2. Select an enabled payment method.
3. Enter the UAT payment reference and the expected amount.
4. Upload an approved test proof if the method requires proof.
5. Submit once.
6. Open the Administrator payment queue/detail.

**Acceptance**

- A submitted payment attempt is retained.
- Payment method/reference/amount/proof metadata agree with the Guest submission.
- The booking remains pending until Administrator verification.
- Confidential proof content is available only through the protected Administrator proof route.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-006 — Duplicate active payment is blocked

**Precondition:** the booking already has a `submitted` payment attempt.

**Steps**

1. Re-open the same secure payment flow.
2. Attempt another active payment submission for the same booking before the first attempt is rejected.

**Acceptance**

- A second active submission is rejected.
- No second active payment row/proof is created.
- The original submitted attempt remains intact.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-007 — Contact submission

**Steps**

1. Open `/contact`.
2. Submit invalid required data and confirm field-level validation.
3. Submit a valid UAT inquiry with the run label in the subject.
4. Open `/admin/contact-messages` and locate it.

**Acceptance**

- Invalid input creates no contact message.
- Valid submission appears once in the Administrator workflow.
- Stored Guest values agree with the submitted values.
- No sensitive implementation detail is exposed on failure.

Result: `NOT_RUN`

Evidence/notes:

### UAT-PUB-008 — Activities, gallery, reviews, news/notices, and CMS pages

**Steps**

1. Open `/activities`, `/gallery`, `/reviews`, `/news`, and `/notices`.
2. Open representative published CMS/news/notice detail pages.
3. Using Administrator screens, verify an inactive/unpublished/draft test record is not publicly visible.
4. Publish/activate the designated UAT record and confirm it becomes public.
5. Restore the intended post-UAT state.

**Acceptance**

- Only records meeting their public eligibility/publication rules appear.
- Draft/archived CMS content is not publicly available.
- Approved review content is public; pending/rejected review content is not.

Result: `NOT_RUN`

Evidence/notes:

## 6. Administrator UAT

### UAT-ADM-001 — Authentication and authorization

**Steps**

1. While signed out, request `/admin/dashboard` and at least one protected detail page.
2. Sign in through `/admin/login`.
3. Open `/admin/dashboard`.
4. Sign out.
5. Attempt the protected page again.

**Acceptance**

- Signed-out access does not expose protected Administrator content.
- Valid Administrator credentials create a working session.
- Logout destroys access to the protected session.
- Authentication errors do not expose secrets or session identifiers.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-002 — Dashboard projections

**Steps**

1. Open `/admin/dashboard`.
2. Compare representative totals/queues with the corresponding room, booking, payment, and recent-activity screens.
3. Refresh the page.

**Acceptance**

- Dashboard values are read-only projections.
- Viewing/refreshing the dashboard changes no booking, payment, room, or content state.
- No unnecessary Guest-sensitive data is displayed.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-003 — Room type, amenity, room, image, and publication workflow

**Steps**

1. Create or reuse a clearly labelled UAT room type.
2. Create or reuse a UAT amenity.
3. Create a UAT room with valid capacity/rate and attach the amenity.
4. Upload an approved UAT image and set the intended primary image.
5. Verify the room is not publicly offered while inactive/unpublished.
6. Make the room active and public using the approved controls.
7. Confirm it appears on `/rooms`.
8. Return the room to the intended cleanup state after dependent UAT scenarios complete.

**Acceptance**

- Administrator changes persist and render correctly.
- Public visibility follows operational/publication rules.
- Image handling does not expose server paths or executable uploads.
- Existing historical rooms/bookings are not deleted for cleanup.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-004 — Booking status workflow

**Precondition:** use a UAT booking created through the public flow.

**Steps**

1. Open `/admin/bookings` and the booking detail.
2. For a confirmed test booking, perform Check In.
3. Confirm status becomes `checked_in`.
4. Perform Check Out.
5. Confirm status becomes `checked_out`.
6. Separately verify that an allowed test pending/confirmed booking can be cancelled, without reusing the successful-stay booking.

**Acceptance**

- Only allowed transitions are offered/accepted.
- Each successful transition is reflected in booking history.
- Room operational status is not changed to Reserved/Occupied/Available by booking transitions.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-005 — Payment rejection and corrected resubmission

**Precondition:** create a separate pending UAT booking with one submitted payment.

**Steps**

1. Open the submitted payment.
2. Attempt rejection without the required reason.
3. Enter a valid UAT rejection reason and reject.
4. Confirm the booking remains pending.
5. Re-open the Guest payment flow.
6. Submit one corrected replacement attempt.

**Acceptance**

- Invalid rejection is refused.
- The original attempt becomes `rejected` and is retained.
- Rejected history does not block one corrected new submission.
- The replacement attempt becomes the active submitted attempt.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-006 — Payment verification drives booking confirmation

**Precondition:** a pending UAT booking has one submitted payment attempt.

**Steps**

1. Open the payment detail and protected proof when present.
2. Compare amount/reference/proof with the booking financial snapshot.
3. Verify the payment.
4. Refresh the payment and booking details.

**Acceptance**

- Payment becomes `verified`.
- The pending booking becomes `confirmed`.
- Repeating the decision does not create a second confirmation or duplicate verified payment.
- The retained event/audit history reflects the decision.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-007 — Invoice issue, view, and print

**Precondition:** use an eligible confirmed UAT booking.

**Steps**

1. Open the booking/payment/invoice workflow.
2. Issue the invoice once.
3. Open the invoice detail.
4. Open the protected print view.
5. Attempt to issue the invoice again.

**Acceptance**

- At most one stable invoice is issued for the booking.
- Accommodation values use the retained booking/payment snapshot.
- Print/detail routes remain Administrator-only.
- Re-issue does not create a duplicate invoice.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-008 — Activities

**Steps**

1. Create a UAT activity.
2. Edit its content.
3. Toggle active/publication state.
4. Check `/activities` after each relevant state change.
5. Return it to the intended cleanup state.

**Acceptance**

- Active and publication controls remain separate.
- Only active and published activities appear publicly.
- Administrator edits persist without changing unrelated activities.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-009 — Gallery

**Steps**

1. Create/reuse a UAT gallery category.
2. Upload a permitted UAT image.
3. Edit caption/category metadata.
4. Change ordering.
5. Toggle active/publication state.
6. Check `/gallery`.
7. Clean up using approved deactivate/unpublish/delete behavior only where the UI explicitly permits it.

**Acceptance**

- Upload validation accepts the approved test image and rejects an intentionally invalid test file.
- Public ordering/visibility matches Administrator state.
- Stored filenames/paths are server-controlled.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-010 — Settings, branding, media, CMS, and navigation

**Steps**

1. Open `/admin/settings` and record the current test values.
2. Change one harmless allowlisted UAT setting and verify the public effect.
3. Upload/use a UAT branding or media asset where appropriate.
4. Create a UAT CMS page as draft.
5. Confirm the draft page is not public.
6. Publish it and confirm its public route.
7. Add/reorder/toggle a UAT navigation item referencing approved content.
8. Restore the intended settings/navigation state.

**Acceptance**

- Settings accept only supported keys/types.
- Draft/archived pages are not public.
- Published page content is escaped/rendered safely.
- Public navigation follows Administrator ordering/active state.
- Existing navbar/footer structure remains intact.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-011 — Contact inquiry management

**Precondition:** `UAT-PUB-007` created a UAT inquiry.

**Steps**

1. Search for the UAT inquiry.
2. Open the detail page.
3. If the current approved environment exposes reply/status controls, send a harmless UAT reply and exercise close/reopen.
4. Confirm retained reply history and workflow state.
5. If SMTP is not configured/available, record the delivery portion as `BLOCKED_EXTERNAL` rather than forcing a pass.

**Acceptance**

- Search/detail show the correct inquiry.
- Any enabled reply is sent only to the stored Guest email and retained in history.
- Closing/reopening preserves the original inquiry and reply history.
- Delivery failure does not delete or corrupt the inquiry.

**Documentation note:** executable routes/controllers currently include contact reply/status behavior, while older Sprint 4 user/API documentation still describes the Administrator contact workflow as read-only pending policy. This mismatch must be reconciled during final documentation closure; UAT must record the behavior actually executed and must not silently reinterpret the approved policy.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-012 — Review invitation and moderation

**Precondition:** a UAT booking has reached `checked_out`.

**Steps**

1. Confirm a review invitation is issued only for the checked-out stay.
2. Use the delivered invitation once to submit a UAT rating/review.
3. Attempt to reuse the same invitation.
4. Open the pending review in `/admin/reviews`.
5. Approve the successful UAT review and confirm it becomes public.
6. Use a separate UAT review to verify rejection requires the configured moderation note.

**Acceptance**

- Invitation eligibility is tied to a checked-out booking.
- Invitation is single-use and expiry is enforced.
- Pending review is not public.
- Only allowed moderation transitions succeed.
- Approved review is public; rejected review is not.

If invitation delivery depends on unavailable SMTP/provider infrastructure, mark the delivery-dependent portion `BLOCKED_EXTERNAL`.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-013 — Notifications

**Steps**

1. Open `/admin/notifications`.
2. Trigger or locate a UAT workflow that creates an Administrator notification.
3. Mark one notification read.
4. Exercise Mark All Read when safe.

**Acceptance**

- Only the signed-in Administrator's notifications are shown.
- Newest-first ordering is sensible.
- Read state persists.
- No delete/archive action is exposed unless separately approved.

Result: `NOT_RUN`

Evidence/notes:

### UAT-ADM-014 — Session expiry/fail-closed behavior

**Steps**

1. Sign in and open a protected page.
2. Complete ordinary UAT work without recording session values.
3. Test logout as the primary deterministic session-end check.
4. Where a short-lived staging/test session configuration is explicitly available, allow it to expire and retry a protected action.

**Acceptance**

- Protected pages/actions fail closed after session end.
- Re-authentication returns the Administrator to a usable state.
- No protected JSON response leaks data after the session is invalid.

Result: `NOT_RUN`

Evidence/notes:

## 7. End-to-End Business Scenarios

### UAT-E2E-001 — Successful stay lifecycle

Execute one clean scenario without manual database edits:

```text
Published active room
    -> Guest availability search
    -> pending one-room booking
    -> submitted payment
    -> Administrator verifies payment
    -> confirmed booking
    -> invoice issued
    -> Administrator checks in
    -> Administrator checks out
    -> review invitation
    -> Guest submits review
    -> Administrator approves review
    -> approved review appears publicly
```

**Acceptance**

- Every step uses the state produced by the previous step.
- Public and Administrator views agree after every transition.
- No step requires direct database mutation.
- Retained booking/payment/invoice/review history is coherent.

Result: `NOT_RUN`

Evidence/notes:

### UAT-E2E-002 — Payment rejection and recovery

Execute:

```text
pending booking
    -> submitted payment
    -> Administrator rejects with reason
    -> booking remains pending
    -> Guest submits corrected payment
    -> Administrator verifies
    -> booking becomes confirmed
```

**Acceptance**

- Rejected history remains retained.
- Corrected resubmission is allowed.
- Duplicate active submission remains blocked.
- Only verification of the valid active attempt confirms the booking.

Result: `NOT_RUN`

Evidence/notes:

### UAT-E2E-003 — Public/Administrator consistency

For the same UAT run, compare:

- room active/publication state versus public catalogue;
- room capacity/rate/amenities/images versus public detail;
- availability versus blocking booking state;
- booking totals/status versus Administrator detail;
- payment status versus booking confirmation;
- invoice snapshot versus booking/payment snapshot;
- activity/gallery publication versus public pages;
- CMS/navigation/settings versus public rendering;
- review moderation versus public reviews;
- contact workflow state versus retained inquiry/reply history.

Result: `NOT_RUN`

Evidence/notes:

## 8. External/Environment-Gated Evidence

These checks must not be marked PASS from local PHPUnit alone:

| Gate | Required evidence | Result |
|---|---|---|
| Real SMTP delivery | Actual provider accepts a designated UAT transactional message without exposing credentials | `NOT_RUN` |
| Staging HTTPS/session cookie | Protected staging runs over HTTPS with production-style secure-cookie configuration | `NOT_RUN` |
| Staging/cPanel filesystem | Public document root and private/writable paths match deployment requirements | `NOT_RUN` |
| Backup/restore rehearsal | Previously hardened backup is restored to a disposable database and verified | `NOT_RUN` |
| Browser/device | S6-006 manual matrix evidence retained | `NOT_RUN` |
| Remote CI | Required repository checks complete successfully | `NOT_RUN` |

`BLOCKED_EXTERNAL` is an acceptable truthful result while the relevant environment is unavailable. It is not equivalent to approval.

## 9. Defect Handling

Classify each UAT defect before changing code:

| Severity | Meaning | Release effect |
|---|---|---|
| Critical | Security/privacy loss, corrupted financial/booking state, or core workflow cannot complete | Stop UAT/release; fix immediately |
| High | Major approved workflow fails with no reasonable safe workaround | Must be resolved before UAT approval |
| Medium | Workflow works but has a significant usability/consistency defect | Fix or explicitly accept before release |
| Low | Minor presentation/copy issue without business-state impact | May be scheduled with explicit acceptance |

For every code defect:

1. record the failing UAT scenario;
2. reproduce it before changing code;
3. make the smallest justified correction;
4. run the exact affected automated tests;
5. rerun the failed UAT scenario;
6. run the focused package suite;
7. run the complete PHPUnit suite once before committing if production code changed.

Do not create speculative hardening changes when no UAT defect is reproducible.

## 10. UAT Run Record

| Scenario | Result | Tester/date | Evidence reference | Defect ID |
|---|---|---|---|---|
| UAT-PUB-001 | `NOT_RUN` |  |  |  |
| UAT-PUB-002 | `NOT_RUN` |  |  |  |
| UAT-PUB-003 | `NOT_RUN` |  |  |  |
| UAT-PUB-004 | `NOT_RUN` |  |  |  |
| UAT-PUB-005 | `NOT_RUN` |  |  |  |
| UAT-PUB-006 | `NOT_RUN` |  |  |  |
| UAT-PUB-007 | `NOT_RUN` |  |  |  |
| UAT-PUB-008 | `NOT_RUN` |  |  |  |
| UAT-ADM-001 | `NOT_RUN` |  |  |  |
| UAT-ADM-002 | `NOT_RUN` |  |  |  |
| UAT-ADM-003 | `NOT_RUN` |  |  |  |
| UAT-ADM-004 | `NOT_RUN` |  |  |  |
| UAT-ADM-005 | `NOT_RUN` |  |  |  |
| UAT-ADM-006 | `NOT_RUN` |  |  |  |
| UAT-ADM-007 | `NOT_RUN` |  |  |  |
| UAT-ADM-008 | `NOT_RUN` |  |  |  |
| UAT-ADM-009 | `NOT_RUN` |  |  |  |
| UAT-ADM-010 | `NOT_RUN` |  |  |  |
| UAT-ADM-011 | `NOT_RUN` |  |  |  |
| UAT-ADM-012 | `NOT_RUN` |  |  |  |
| UAT-ADM-013 | `NOT_RUN` |  |  |  |
| UAT-ADM-014 | `NOT_RUN` |  |  |  |
| UAT-E2E-001 | `NOT_RUN` |  |  |  |
| UAT-E2E-002 | `NOT_RUN` |  |  |  |
| UAT-E2E-003 | `NOT_RUN` |  |  |  |

## 11. Approval Gate

UAT may be approved only when:

- every required local/staging scenario is `PASS` or truthfully `N/A`;
- Critical defects = 0;
- High defects = 0;
- any Medium/Low residual defect has an explicit disposition;
- public and Administrator state consistency has been checked;
- external dependencies are either evidenced or explicitly recorded as unresolved deployment gates;
- no real Guest/payment secrets were introduced into test evidence;
- Project Owner/authorized stakeholder acceptance is recorded.

### Sign-off

| Role | Name/identifier | Decision | Date | Notes |
|---|---|---|---|---|
| UAT executor |  |  |  |  |
| Technical reviewer |  |  |  |  |
| Project Owner / authorized stakeholder |  |  |  |  |

Production approval remains a later gate and must not be inferred from UAT approval alone.
