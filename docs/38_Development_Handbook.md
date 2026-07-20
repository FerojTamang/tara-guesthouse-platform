# Tara Guest House Development Handbook

## Document Information

* Status: Synchronized — Review Required
* Authority: `00_V1_ARCHITECT_DECISIONS.md`
* Audience: Project manager, technical architect, developers, reviewers, testers and AI-assisted coding tools
* Scope: Version 1 implementation rules

## 1. Version 1 Contract

Version 1 is a PHP server-rendered application using Bootstrap views, Vanilla JavaScript and selective Fetch/AJAX. Normal page requests use PHP page routes. JSON requests use `/api/v1/...`; every protected Administrator JSON endpoint uses `/api/v1/admin/...`.

The executable database source of truth is the ordered migration set. Schema descriptions, examples and diagrams must agree with those migrations before implementation is accepted.

## 2. Actors, Accounts and Authentication

### 2.1 Version 1 actors

* **Guest** is an unauthenticated public actor. Guest is not a stored role and does not have an account in Version 1.
* **Administrator** is the only authenticated and stored Version 1 role.

Receptionist, Manager, Accountant and Content Manager are Future Enhancements. They must not be seeded, authorized or exposed by current screens or APIs.

### 2.2 Administrator authentication

Administrator authentication uses a server-side PHP session. A successful login regenerates the session identifier and stores only the minimum Administrator identity and authorization data needed by the application.

Protected page requests that lack a valid session redirect to the Administrator login page. Protected JSON requests that lack a valid session return `401 Unauthorized`. Authorization failures return `403 Forbidden`.

### 2.3 Session expiration

Session expiration is controlled by server configuration and the approved security settings:

1. Record the session creation time and last activity time.
2. Reject a session that exceeds either its configured inactivity timeout or absolute lifetime.
3. Destroy expired server-side session data and remove the session cookie.
4. Regenerate the session identifier after login and any authorization-sensitive identity change.
5. Use secure, HTTP-only and appropriate SameSite cookie settings in production.

The exact inactivity timeout and absolute lifetime require security configuration approval before deployment. Version 1 has no JWT access-token or refresh-token expiration rules.

## 3. Layer Responsibilities

Controllers validate the request boundary and coordinate a use case. Services enforce business rules and state transitions. Repositories perform persistence operations. Views render escaped output. Domain constants define the canonical string-controlled workflow values.

Transactions are required when one use case writes multiple dependent records, including payment verification plus booking confirmation, check-in/check-out plus booking events, and invoice creation plus invoice items.

## 4. Architecture Decision Records

### ADR-001: Preserve Every Payment Attempt

**Decision:** A booking has zero or more payment attempts. Each submission creates a new immutable-at-submission payment record; rejected attempts are retained for audit.

### ADR-002: Payment Verification Permits Booking Confirmation

**Decision:** Only an authenticated Administrator can verify or reject a submitted payment. Verification changes the payment to `verified` and permits the Booking Service to transition the booking from `pending` to `confirmed` in the same coordinated use case. Payment Service does not assign arbitrary booking states.

### ADR-003: Booking Service Controls Booking Transitions and Calculated Availability

**Decision:** Booking Service validates booking transitions and calculates occupancy and date availability from booking records and requested dates. It does not change room operational status. Room Service manages only `active`, `maintenance` and `inactive`, plus the separate publication control.

### ADR-004: Emit Notifications from Successful Domain Events

**Decision:** In-app and SMTP email notifications are generated only after the corresponding transaction succeeds. Notification failure must be logged and retried without rolling back a completed domain transaction.

### ADR-005: Reviews Require a Verified Stay

**Decision:** A review invitation may be issued only when `booking_status = checked_out`. The invitation mechanism must be secure, single-use and expiring. Public reviews remain moderated.

### ADR-006: CMS Uses Settings and Structured Content

**Decision:** Simple configuration stays in `settings`; structured pages/content use CMS records with `draft`, `published` and `archived` statuses.

### ADR-007: Dashboard Metrics Are Read-Only Projections

**Decision:** Dashboard values are calculated from authoritative module records. A dashboard request never changes booking, payment, room or publication state.

### ADR-008: Workflow Values Are String-Controlled Domain Constants

**Decision:** Database workflow fields store strings constrained by PHP domain constants, validation and transition services. Migrations, services, API responses and tests must use the exact canonical values.

### ADR-009: Repository Boundaries Follow Modules

**Decision:** Repository queries must not bypass Booking Service, Payment Service or review-eligibility rules when a state transition or authorization decision is involved.

### ADR-010: Route Families Are Explicit

**Decision:** PHP page routes render views. Public JSON endpoints use `/api/v1/...`. Protected Administrator JSON endpoints explicitly use `/api/v1/admin/...` in route declarations, documentation and tests.

## 5. Business Rules

### BR-001: Administrator session

Every protected Administrator page and JSON action requires a valid PHP session and Administrator authorization.

### BR-002: Password handling

Administrator passwords are stored only as PHP-supported password hashes and verified with the matching secure password API. Passwords, session identifiers and reset secrets must never be logged.

### BR-003: Session expiry

An expired session is invalid immediately. The application destroys it and requires a fresh Administrator login; it does not issue or refresh a bearer token.

### BR-004: Administrator identity

Administrator email addresses are normalized and unique. Disabled Administrator accounts cannot create new sessions.

### BR-005: Version 1 roles

Administrator is the only stored/authenticated role. Guest is a public actor, not a role record. Specialized staff roles are not active Version 1 authorization choices.

### BR-006: Room eligibility

A room is selectable for a stay only when its operational status is `active`, it is published for the relevant public flow, and no blocking booking overlaps the requested dates. Administrator screens display operational status, publication status and calculated date availability separately.

### BR-007: Calculated availability and occupancy

`available`, `reserved` and `occupied` are calculated conditions, not room operational-status values. Availability is calculated from stay dates and blocking bookings; current occupancy is derived from `checked_in` bookings. Code must not persist those conditions in `rooms.operational_status`.

### BR-008: Room history

Rooms referenced by historical bookings must not be hard-deleted. Use `inactive` when a room is retired from operations and the separate publication control when it should disappear from public pages.

### BR-009: Guest booking ownership

A Version 1 booking stores a guest contact snapshot and does not require customer registration. It contains exactly one booking-room association. The nullable future customer reference, if present, remains unused by current flows.

### BR-010: Booking creation

Every new booking starts as `pending`. Booking creation does not accept an arbitrary booking status and does not automatically create a payment attempt.

### BR-011: Booking transitions

Allowed transitions are:

* `pending` to `confirmed` after Administrator payment verification;
* `pending` to `cancelled` or `rejected`;
* `confirmed` to `checked_in` or `cancelled`;
* `checked_in` to `checked_out`.

`checked_out`, `cancelled` and `rejected` are final. `completed`, `expired` and booking workflow `archived` are not Version 1 statuses.

### BR-012: Confirmation deadline

Each pending booking records its payment/confirmation deadline. After rechecking the current state in a transaction, a scheduled or request-driven Booking Service operation stops an overdue pending booking from blocking availability and may move it to `cancelled` with a recorded deadline-expiry reason.

### BR-013: Check-in

Only a `confirmed` booking may be checked in. Booking Service records `checked_in_at`, changes the booking to `checked_in`, and appends the transition event.

### BR-014: Check-out

Only a `checked_in` booking may be checked out. Booking Service records `checked_out_at`, changes the booking to the final successful status `checked_out`, and appends the transition event.

### BR-015: Payment attempts

A booking may have zero payment attempts until payment information is submitted. Payment statuses are `pending`, `submitted`, `verified`, `rejected` and `refunded`. Every attempt has its own reference and audit history.

### BR-016: Manual payment verification

Payment submission is available through the secure guest booking flow. Verification and rejection are Administrator-only Version 1 actions. No payment gateway or automatic verification is part of Version 1.

### BR-017: Refund boundary

The data model can record `refunded`, but full-versus-partial refund behavior, authority and accounting treatment require Architect/Client Confirmation. Do not implement a final refund workflow until approved.

### BR-018: Financial precision

Money is stored as fixed-precision NPR values. Bookings, payments and invoice items preserve financial snapshots so later room-rate or tax changes do not rewrite history.

### BR-019: Review eligibility

Review Service verifies `booking_status = checked_out` before issuing or redeeming a review invitation. A token can be redeemed once, must expire, and must not expose a sequential booking identifier as proof of eligibility.

### BR-020: Publishing controls

Rooms, activities and gallery items keep operational/active controls separate from publication controls. CMS page statuses are `draft`, `published` and `archived`; only published content is public.

### BR-021: Auditability

Booking transitions, payment verification/rejection, CMS publication and other sensitive Administrator actions produce permanent audit records with actor, action, timestamp and relevant before/after context.

## 6. Input, Output and File Security

Validate all input at the controller boundary and again at domain boundaries where state or authorization is involved. Use parameterized database access, CSRF protection for state-changing session-authenticated requests, output escaping, restrictive upload MIME/extension/size checks, randomized stored filenames, and storage locations that cannot execute uploaded content.

API errors must not expose stack traces, SQL or local paths. Logs may contain correlation identifiers and non-secret domain identifiers but not passwords, session IDs, payment proof secrets or review tokens.

## 7. Routes and API Conventions

* PHP-rendered pages use normal page routes such as `/rooms`, `/booking` and `/admin/bookings`.
* Public and secure guest JSON calls use `/api/v1/...`.
* Every protected Administrator JSON call uses `/api/v1/admin/...`.
* A protected endpoint outside `/api/v1/admin/...` is a contract defect.
* API payloads use canonical status strings and one room per booking.

The exact endpoint contract is in `23_REST_API_Specification.md`.

## 8. Testing Requirements

Unit tests cover status constants, transition guards, overlap detection, money calculations and review-token rules. Integration tests cover migrations, transactions, session authorization, one-room validation, zero/multiple payment attempts and permanent event history. Feature tests cover guest booking, Administrator verification, confirmation, check-in, check-out, verified-stay review, CMS publication and route protection.

At minimum, negative tests prove that:

* Guest cannot access any `/api/v1/admin/...` action.
* Guest booking does not require or create a customer account.
* A booking cannot skip its approved transition sequence.
* Payment rejection does not confirm a booking.
* Room operational status never becomes `available`, `reserved` or `occupied`.
* Overlapping blocking bookings cannot reserve the same room and dates.
* A non-`checked_out` booking cannot generate or redeem a review invitation.
* A review invitation cannot be reused.

## 9. AI-Generated-Code Restrictions

AI-generated code is an untrusted draft. It must be reviewed by a responsible developer and must not:

* invent roles, statuses, endpoints, tables, columns, modules or features;
* replace PHP sessions with JWT or another authentication mechanism;
* add a customer-account dependency to Version 1;
* bypass migrations, services, authorization, CSRF protection or validation;
* persist calculated availability/occupancy as room operational status;
* change the one-room Version 1 rule;
* add gateway integration or define unresolved refund policy;
* introduce packages or infrastructure without approved documentation.

Generated migrations, security-sensitive code, financial calculations and state transitions require line-by-line human review and relevant automated tests.

## 10. Approved Implementation Order

1. Confirm migrations, domain constants and seed data.
2. Implement shared infrastructure, PHP sessions and Administrator authorization.
3. Implement room types, rooms, amenities, publication and calculated availability.
4. Implement guest booking, one-room validation, deadlines and booking events.
5. Implement payment submission, multiple attempts and manual Administrator verification.
6. Implement invoices and financial snapshots.
7. Implement activities, gallery, settings and structured CMS content.
8. Implement verified-stay reviews, contact messages and notifications.
9. Implement dashboard projections, audit views, backup operations and deployment controls.
10. Complete traceability, security, regression, deployment and acceptance testing.

## 11. Future Enhancements

The following are outside Version 1:

* Customer accounts, customer authentication and customer portal;
* Receptionist, Manager, Accountant and Content Manager roles and permissions;
* Multiple rooms per booking in the active service/UI contract;
* Payment gateway integration and automatic payment verification;
* SMS, push and WhatsApp notifications;
* VPS/cloud deployment and infrastructure that exceeds the approved cPanel/shared-hosting target.

Database design may be future-ready where approved, but no current route, screen or business rule may depend on these features.

## 12. Frequently Asked Questions

### Q1. Does a Guest need an account to book?

No. The booking stores the Guest contact snapshot. Customer accounts are a Future Enhancement.

### Q2. How does the application update room availability?

It does not update a stored availability status. Booking Service calculates availability for requested dates from blocking bookings and calculates current occupancy from `checked_in` bookings. Room operational status remains one of `active`, `maintenance` or `inactive` and is managed separately.

### Q3. When is a payment record created?

Only when payment information is submitted. A new booking can have zero payment attempts, and later submissions create additional attempt records.

### Q4. Who verifies payment?

An authenticated Administrator. Version 1 has no gateway verification option.

### Q5. What is the final successful booking status?

`checked_out`.

### Q6. When may a Guest submit a review?

Only through a valid single-use invitation associated with a booking where `booking_status = checked_out`.

## 13. Architect/Client Confirmation Required

The following policies remain unresolved and must not be guessed during implementation:

* PHP-session inactivity timeout and absolute lifetime;
* pending-booking payment/confirmation deadline duration and reminder timing;
* cancellation window, cancellation fees and no-show treatment;
* full-versus-partial refund policy, approval authority and accounting handling;
* tax/service-charge rates and rounding rules;
* SMTP sender identity, provider limits and retry thresholds;
* review-invitation lifetime;
* CMS content-retention/version-retention policy;
* shared-hosting capability confirmation and production backup retention.
