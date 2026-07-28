# Sprint 2 QA Closure Report

## 1. QA Purpose

This report records formal Quality Assurance for the Sprint 2 Availability and Booking slice. It verifies approved date-range availability, transactional one-room Guest booking, double-booking protection, secure booking references and follow-up tokens, public booking interfaces, Administrator booking read pages, canonical booking-status actions, booking-event history, booking-status audit records, regression safety, and documentation synchronization.

This report does not approve manual payments, invoices, production deployment, or later Version 1 modules. Final Sprint 2 closure remains subject to pull-request review, remote CI, and Technical Architect approval.

## 2. Sprint 2 Ticket Inventory

| Ticket | Feature | Status | QA result |
|---|---|---|---|
| S2-001 | Availability and booking scope/source analysis | Completed | Passed |
| S2-002 | Booking database foundation | Implemented | Passed |
| S2-003 | Booking models and read repositories | Implemented | Passed |
| S2-004 | Stay-date and booking-domain validation | Implemented | Passed |
| S2-005 | Overlap repository queries | Implemented | Passed |
| S2-006 | Availability service | Implemented | Passed |
| S2-007 | Public availability page and JSON workflow | Implemented | Passed |
| S2-008 | Guest booking workflow | Implemented | Passed |
| S2-009 | Transaction and concurrency protection | Implemented | Passed |
| S2-010 | Secure reference, follow-up token, and success workflow | Implemented | Passed |
| S2-011 | Administrator booking list/detail pages | Implemented | Passed |
| S2-012 | Approved Administrator booking-status actions and audit foundation | Implemented | Passed |
| S2-013 | Sprint QA, documentation synchronization, and closure | Executed | Passed locally; external review gates remain |

## 3. Environment Tested

- Repository: `C:\TaraGuestHouse`
- Branch: `feat/s2-availability-booking`
- Operating system: Windows 10 build 26200.8894
- PHP: 8.3.32
- PHPUnit: 12.5.31
- Database engine: MariaDB/MySQL through the configured isolated development and test connections; exact server version was not recaptured during S2-013
- Application timezone: Asia/Kathmandu
- Database migration status after S2-012: 12 applied, 0 pending
- Development and test databases remain separate; write-capable automated tests use the isolated `_test`/`_testing` database contract

No credential, DSN, password, session identifier, CSRF token, booking-access token, or Guest contact data is recorded in this report.

## 4. Automated QA Evidence

| Evidence | Tests | Assertions | Skipped | Result |
|---|---:|---:|---:|---|
| S2-010 related booking suite | 38 | 235 | 0 | Passed |
| S2-011 focused Administrator booking read suite | 20 | 161 | 0 | Passed |
| S2-011 related Administrator suite | 53 | 531 | 0 | Passed |
| S2-012 focused status/action/audit suite | 65 | 560 | 0 | Passed |
| Final complete regression suite (`composer test`) | 1245 | 7597 | 10 | Passed |

The final complete suite finished with zero failures. The 10 skipped tests are established platform-dependent filesystem security checks that cannot run under the local Windows symlink/permission model; their assertions remain enabled for compatible Linux CI environments.

## 5. Database and Transaction Evidence

The following executable migrations are part of Sprint 2:

- `20260726000100_create_bookings_table.php`
- `20260726000200_create_booking_rooms_table.php`
- `20260726000300_create_booking_events_table.php`
- `20260727000100_create_audit_logs_table.php`

Verified behavior includes:

- one booking stores exactly one room snapshot in Version 1;
- booking references are unique and non-sequential;
- fixed-precision NPR price and total snapshots are retained;
- booking creation starts at `pending`;
- confirmation deadline is stored;
- booking creation and overlap recheck execute in one transaction;
- the room row is locked before the final overlap decision;
- rollback prevents partial booking, room-snapshot, and event persistence;
- concurrent requests cannot both create overlapping bookings for the same room;
- Administrator status transitions lock and re-read the current booking state;
- booking update, booking event, and audit-log insert commit or roll back together.

The development database was migrated from 11 applied/1 pending to 12 applied/0 pending before the final regression run.

## 6. Availability Rules Verified

Automated tests verify:

- check-out must be later than check-in;
- occupancy must be valid and fit room capacity;
- only operationally active and publicly published rooms are returned publicly;
- maintenance, inactive, unpublished, and soft-deleted rooms are excluded;
- overlap uses `existing.check_in < requested.check_out AND existing.check_out > requested.check_in`;
- adjacent stays sharing a checkout/check-in boundary are permitted;
- unexpired `pending`, `confirmed`, and `checked_in` bookings block;
- `checked_out`, `cancelled`, and `rejected` bookings do not block;
- expired pending bookings no longer block availability;
- availability is calculated and never written as a room operational status.

## 7. Guest Booking and Public Security Verified

Automated tests verify:

- Guests book without an account;
- exactly one scalar `room_id` is accepted;
- Guest-controlled user IDs, booking statuses, payment statuses, and multiple-room payloads are rejected;
- Guest contact, stay, occupancy, policy, and price snapshots are validated server-side;
- public booking references use secure randomness and are not sufficient public authorization;
- follow-up access uses an opaque scoped HMAC-SHA256 token rather than JWT;
- token payloads expose no readable Guest PII or booking reference;
- public submission routes use CSRF and the approved direct-peer IP rate limit;
- the limiter enforces five attempts per IP per 15-minute window and a 15-minute block;
- rate-limit state uses private storage, locking, and atomic writes;
- HTML booking submission uses PRG with a 303 redirect;
- success data is one-time session flash data;
- success URLs contain no token, Guest PII, or booking reference;
- responses use `Cache-Control: no-store`;
- booking success wording does not imply payment verification or confirmation.

## 8. Administrator Booking Management Verified

Implemented Administrator capability includes:

- authenticated booking list and detail pages;
- canonical status filtering;
- escaped Guest, stay, policy, price, timestamp, and event presentation;
- protected booking detail routes with safe Not Found responses;
- protected `PATCH /api/v1/admin/bookings/{id}/status`;
- PHP-session authentication before JSON CSRF validation;
- exact JSON content-type and payload validation;
- canonical allowed transitions:
  - `pending -> rejected`
  - `pending -> cancelled`
  - `confirmed -> checked_in`
  - `confirmed -> cancelled`
  - `checked_in -> checked_out`
- required plain-text reason for cancellation and rejection;
- no reason accepted for check-in or check-out;
- terminal immutability for `checked_out`, `cancelled`, and `rejected`;
- no arbitrary Administrator `pending -> confirmed` control;
- timestamps and reasons written to their approved booking columns;
- append-only booking events and Administrator actor IDs;
- append-only audit records for status changes;
- generic failure responses without SQL or internal disclosure.

Payment verification remains the only approved future authority that permits Booking Service to perform `pending -> confirmed`.

## 9. Architecture and Scope Review

Sprint 2 follows the required flow:

`Route -> Middleware -> Controller -> Request Validation -> Service -> Repository -> Database`

Verified separation includes:

- routes register exact HTTP methods and middleware;
- middleware owns authentication, CSRF, and public rate limiting;
- controllers coordinate request/response formatting;
- request classes validate and normalize payloads;
- services own overlap rules, state transitions, and transaction coordination;
- repositories and migrations contain SQL/PDO;
- models contain immutable domain data;
- PHP views contain escaped presentation logic only.

No customer account, JWT, multiple-room booking, online payment gateway, specialized staff role, framework, ORM, SPA, Redis, WebSocket, or unrelated future module was introduced.

## 10. Security Review

Demonstrated controls include:

- PDO prepared statements;
- transaction rollback on persistence failures;
- room and booking row locking for conflict-sensitive writes;
- server-side allowlisted payload validation;
- context-aware HTML escaping;
- Administrator session authorization;
- CSRF on implemented state-changing routes;
- public booking rate limiting;
- opaque signed access tokens with a dedicated secret;
- private rate-limit storage;
- no-store responses for booking and Administrator data;
- safe internal redirects;
- generic 404/409/422/500 responses;
- no normal booking-delete workflow;
- append-only event and audit history;
- test coverage for malformed JSON, unsupported media types, missing authentication, invalid CSRF, transition conflicts, concurrency, and rollback.

## 11. Expected Test Diagnostics

The final suite intentionally emitted these sanitized diagnostics from negative/fallback tests:

- `Application exception logging failed.`
- `Administrator bootstrap security logging failed.`
- `Application file logging is unavailable; using the PHP error log.`

The suite completed successfully; these messages are expected evidence of fallback/error-path tests and are not failures.

## 12. Documentation Synchronization

S2-013 updates:

- `README.md` — removes stale foundation-only statements and records the implemented Sprint 2 slice and final automated baseline;
- `docs/33_Documentation_Index.md` — records Sprint 2 implementation evidence and registers this closure report;
- `docs/35_Requirements_Traceability_Matrix.md` — connects Availability, Guest booking, Administrator booking management, and booking-status audit requirements to implementation and tests;
- `docs/41_Sprint_2_QA_Closure_Report.md` — records this formal QA evidence and closure verdict.

The canonical status, API, UI, and booking specification documents already describe the implemented rules and therefore require no business-contract change in this ticket.

## 13. Known Deferred or Partial Functionality

The following are intentionally outside the completed Sprint 2 scope or require later approved slices:

- manual payment methods, payment attempts, proof upload, verification, rejection, and resubmission;
- payment-driven `pending -> confirmed`;
- invoice issue and printing;
- automatic scheduled cancellation of expired pending bookings;
- transactional email and Administrator notification delivery;
- Administrator-created bookings;
- general Administrator booking list/detail JSON read endpoints;
- booking non-state editing;
- audit-log search/read administration;
- activities, gallery, reviews, contact messages, CMS/settings, and remaining operations/content;
- dashboard completion;
- browser/device matrix testing, UAT, performance/load testing, penetration testing, backup/restore rehearsal, cPanel verification, and production deployment.

Expired pending bookings already cease blocking availability; only the optional scheduled transition to `cancelled` remains deferred.

## 14. Manual and External Gates

Automated feature tests cover rendered availability, booking, success, Administrator list/detail, and status-action behavior. A separate manual browser/device QA session was not recorded during S2-013 and must not be claimed as completed.

Before merge or deployment, the following external gates remain:

- pull-request review into `develop`;
- remote GitHub Actions validation on Linux/MariaDB;
- Technical Architect approval;
- Project Manager functional review where required;
- later staging UAT and responsive/browser verification;
- dependency vulnerability review when network access permits;
- production hardening, cPanel capability verification, backup/restore test, and deployment approval.

## 15. Git and Branch Evidence

At the start of S2-013:

- branch: `feat/s2-availability-booking`;
- local branch synchronized with `origin/feat/s2-availability-booking`;
- latest implementation commit: `7edb71f feat(booking): add administrator status actions`;
- branch difference from `develop`: 117 files, 15,591 insertions, 23 deletions;
- only the three approved local helper items plus S2-013 collector files were untracked.

Temporary collectors are not project deliverables and must remain untracked or be removed before the closure commit.

## 16. Final Acceptance Verdict

**All locally executable blocking Sprint 2 automated QA checks passed.**

The Availability and Booking slice is complete within the approved Sprint 2 scope. Its migrations, services, repositories, routes, pages, JSON contracts, transaction and concurrency protections, secure public-access controls, Administrator read workflows, approved status actions, booking events, audit foundation, and regression tests are ready for pull-request review into `develop`.

This is not production approval. Manual payments, invoices, remaining Version 1 modules, browser/UAT, hardening, and deployment remain later work.

**Status: READY FOR PULL-REQUEST REVIEW INTO `develop`.**
