# Sprint 3 Payments and Invoices Implementation Plan

## 1. Document Control

| Field | Value |
|---|---|
| Ticket | S3-001 |
| Sprint | Sprint 3 — Payments and Invoices |
| Status | Approved for implementation |
| Branch | `feat/s3-payments-invoices` |
| Base commit | `0f2d33b` |
| Approved by | Project Owner |
| Approval date | 28 July 2026 |
| Highest authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Relevant module authority | `11_Booking_System_Specification.md`, `19_Payment_Management_System.md` |
| Executable database authority | Versioned migrations created during Sprint 3 |

## 2. Purpose

This plan records the approved Sprint 3 scope, previously unresolved business policies,
security requirements, route boundaries, implementation order, acceptance criteria, and
explicit exclusions for manual payments and invoices.

It authorizes implementation only within the approved Version 1 architecture:

`Route -> Middleware -> Controller -> Request Validation -> Service -> Repository -> Database`

This plan does not authorize customer accounts, JWT, payment gateways, automatic refunds,
multiple rooms per booking, specialized staff roles, or other future enhancements.

## 3. Repository Baseline

Sprint 3 begins from the merged and regression-tested Sprint 2 baseline:

- active branch: `feat/s3-payments-invoices`;
- base commit: `0f2d33b`;
- database migrations: 12 applied, 0 pending;
- automated baseline: 1,245 tests, 7,597 assertions, 10 skipped, 0 failures;
- existing booking deadline: 360 minutes;
- existing booking access: opaque scoped HMAC-SHA256 token;
- existing authenticated actor: Administrator through hardened PHP session;
- existing audit foundation: append-only `audit_logs`.

No payment-method, payment, invoice, or invoice-item migration exists at this baseline.

## 4. Sprint 3 Scope

Sprint 3 includes:

1. configurable manual payment methods;
2. active public payment-method reads;
3. secure Guest payment submission;
4. zero or more retained payment attempts per booking;
5. confidential payment-proof upload and protected retrieval;
6. Administrator payment listing and detail;
7. Administrator verification or rejection of submitted attempts;
8. transactional duplicate-verification prevention;
9. Payment Service coordination with Booking Service;
10. payment-driven `pending -> confirmed`;
11. append-only payment audit records;
12. one stable invoice per eligible booking;
13. authenticated invoice detail and print rendering;
14. automated regression coverage and synchronized closure documentation.

## 5. Canonical Statuses and Transitions

Payment statuses remain exactly:

- `pending`;
- `submitted`;
- `verified`;
- `rejected`;
- `refunded`.

Active Sprint 3 transitions are exactly:

- new complete Guest attempt -> `submitted`;
- `submitted -> verified`;
- `submitted -> rejected`.

Rules:

- the active Guest endpoint does not create an incomplete `pending` attempt;
- a rejected attempt remains rejected;
- resubmission creates a new payment row;
- a Guest cannot select a payment status;
- only Payment Service changes `payment_status`;
- only Booking Service changes `booking_status`;
- `refunded` remains canonical but has no active transition in Sprint 3.

## 6. Approved Payment Policies

### 6.1 Cardinality

- A booking may have zero or more payment attempts.
- Booking creation creates no payment row.
- Version 1 permits at most one qualifying verified payment per booking.
- Duplicate qualifying verification must be prevented inside a database transaction.

### 6.2 Currency and amount

- Currency is exactly `NPR`.
- Money is accepted and stored as fixed-precision decimal text/data, never float.
- A submitted payment amount must exactly equal `bookings.total_amount`.
- Partial payments, installments, overpayments, and split payments are not included.
- The Guest cannot submit an arbitrary currency.

### 6.3 Deadline

- Guest submission must occur before `confirmation_deadline_at`.
- Administrator verification must also complete before `confirmation_deadline_at`.
- An expired pending booking cannot be confirmed through payment verification.
- No payment operation extends or replaces the booking deadline.
- Expired pending bookings continue to follow the Sprint 2 availability rule and cease blocking.

### 6.4 Payment-method configuration

Administrator-configured methods may contain:

- name;
- Guest instructions;
- account/display reference;
- optional QR image path retained as nullable schema data;
- whether proof is required;
- active/inactive control;
- timestamps.

Sprint 3 does not commit real bank, wallet, account, credential, or QR data.

Inactive methods:

- remain readable for historical payment attempts;
- are excluded from public selection;
- cannot be used for a new submission;
- are not physically deleted through normal administration.

The optional QR-image upload workflow is not part of Sprint 3 unless a later approved ticket
defines its image-specific upload contract. The nullable column may remain unused.

## 7. Approved Payment-Proof Security Policy

### 7.1 Allowed files

Payment proof accepts only:

- JPEG: `.jpg` or `.jpeg`;
- PNG: `.png`;
- PDF: `.pdf`.

Maximum size:

- `5,242,880` bytes;
- exactly 5 MiB.

Validation must use server-side content inspection and an extension allowlist. Client filename
and client MIME type are untrusted.

### 7.2 Storage

Payment proofs must be stored under:

`storage/private/payment-proofs/`

Requirements:

- never under `public/`;
- random server-generated filename;
- original filename is not used as the storage filename;
- no PHP-executable extension;
- safe relative path stored in the database;
- atomic write/move behavior;
- cleanup after failed database transactions where applicable;
- proof files, paths, hashes, and access secrets are not logged.

### 7.3 Retrieval

Administrator proof access uses an authenticated protected route:

`GET /admin/payments/{id}/proof`

The response must:

- require an active Administrator session;
- return Not Found for a missing payment or missing proof;
- use `Cache-Control: no-store`;
- use a safe server-selected content type;
- use a safe download/display filename;
- prevent path traversal;
- never expose the private storage path.

## 8. Guest Access and Rate Limiting

### 8.1 JSON access

`POST /api/v1/payments` requires:

- booking reference;
- scoped booking-access token;
- configured active payment method;
- exact NPR amount;
- transaction/reference code where supplied/required by validation;
- optional Guest notes;
- proof upload where the selected method requires proof.

The booking reference alone never authorizes payment submission.

### 8.2 PHP-rendered access

The PHP-rendered payment workflow uses:

- `GET /booking/payment`;
- `POST /booking/payment`;
- `GET /booking/payment/success`.

For the immediate post-booking flow, the server retains the signed booking continuation in
the hardened PHP session and validates it against the booking and deadline. The token must
not appear in the URL, rendered HTML, browser storage, logs, or success page.

The JSON contract may carry the signed value in the request body for approved same-origin
clients. It is not a JWT and does not create a customer account.

### 8.3 Rate limit

Guest payment submission uses a separate limiter:

- 5 attempts per direct peer IP;
- 15-minute attempt window;
- 15-minute block;
- private hashed-IP state;
- locking and atomic writes;
- storage separate from public booking rate-limit state.

## 9. Guest Submission Transaction

A successful submission must:

1. validate request shape and uploaded file;
2. validate the signed booking access;
3. lock and re-read the booking;
4. require booking status `pending`;
5. require the current time to be before the confirmation deadline;
6. require exact currency and total amount;
7. require an active configured payment method;
8. require proof when the selected method requires it;
9. store the private proof safely;
10. insert a new payment attempt as `submitted`;
11. set `submitted_at`;
12. commit before notification;
13. return a safe success response.

A failed submission must not leave a payment row or orphaned proof file.

## 10. Administrator Payment Review

Administrator pages:

- `GET /admin/payments`;
- `GET /admin/payments/{id}`;
- `GET /admin/payments/{id}/proof`.

Administrator JSON mutation:

- `PATCH /api/v1/admin/payments/{id}/status`.

Only these target states are accepted:

- `verified`;
- `rejected`.

Only a current `submitted` attempt may transition. Authentication is evaluated before JSON
CSRF validation. The service records:

- Administrator actor;
- transition timestamp;
- verification/rejection note;
- append-only audit record;
- safe request metadata where approved.

A verification or rejection note is required and must be length-limited plain text.

## 11. Verification and Booking Confirmation Transaction

Verifying a payment is one coordinated transaction that must:

1. lock the payment attempt;
2. require current payment status `submitted`;
3. lock the associated booking;
4. require booking status `pending`;
5. require current time before `confirmation_deadline_at`;
6. verify the payment amount and currency against the booking snapshot;
7. prove no other qualifying verified payment exists for the booking;
8. set the payment to `verified`;
9. record verification actor, time, and note;
10. invoke the approved Booking Service confirmation operation;
11. set the booking to `confirmed`;
12. set `confirmed_at`;
13. append a `booking_events` confirmation event;
14. append payment-verification and booking-confirmation audit evidence;
15. commit all changes together.

If any step fails, payment verification and booking confirmation both roll back.

Rejecting a payment:

- changes only the submitted attempt to `rejected`;
- records actor, time, and note;
- writes an audit record;
- does not reject or cancel the booking;
- permits another Guest attempt before the deadline.

## 12. Invoice Policies

### 12.1 Eligibility and cardinality

- An invoice may be issued only when the booking is `confirmed`.
- The booking must have its one qualifying verified payment.
- Exactly one invoice is permitted per booking.
- Repeated issue requests return or redirect to the existing stable invoice rather than
  creating another invoice.

### 12.2 Numbering

Invoice numbers use:

`TGH-INV-YYYY-NNNNNN`

Rules:

- generated server-side;
- unique;
- stable after issue;
- sequence creation is concurrency-safe;
- never accepted from request data.

### 12.3 Financial snapshots

- Currency is `NPR`.
- Tax amount is `0.00` for Version 1 until a new approved tax/VAT policy exists.
- No service charge is added.
- Subtotal and total derive from booking and verified-payment snapshots.
- Totals are not editable request fields.
- The accommodation item uses booked nights and historical price-per-night.
- Later room-price edits do not change an issued invoice.

### 12.4 Routes

Administrator pages:

- `GET /admin/invoices`;
- `GET /admin/invoices/{id}`;
- `GET /admin/invoices/{id}/print`.

Administrator issue endpoint:

- `POST /api/v1/admin/bookings/{bookingId}/invoice`.

Invoice pages and issue actions require Administrator session authorization. State-changing
issue requests require CSRF. Print responses are authenticated and use `no-store`.

### 12.5 Retention

- Issued invoices are not deleted.
- Sprint 3 provides no invoice-delete route or action.
- Corrections that would change issued financial data require a later approved audited policy.

## 13. Explicitly Excluded

Sprint 3 does not implement:

- online payment gateways or callbacks;
- customer accounts or payment portal;
- JWT or bearer authentication;
- automatic payment verification;
- partial payment or installments;
- overpayment handling;
- automatic or manual refund mutation;
- refund amount, actor, reason, or accounting fields;
- QR-image upload management;
- specialized Accountant/Receptionist roles;
- arbitrary booking confirmation;
- arbitrary invoice totals;
- invoice deletion;
- tax/VAT other than the approved `0.00` snapshot;
- production payment credentials or real Guest proof files.

## 14. Ticket Plan

| Ticket | Deliverable |
|---|---|
| S3-001 | Scope, source analysis, and approved policy record |
| S3-002 | Payment-method and payment database foundation |
| S3-003 | Payment models and repositories |
| S3-004 | Administrator payment-method management |
| S3-005 | Public active payment-method API |
| S3-006 | Private payment-proof configuration and storage |
| S3-007 | Guest payment request and secure-access validation |
| S3-008 | Transactional Guest payment submission |
| S3-009 | PHP-rendered Guest payment and success workflow |
| S3-010 | Administrator payment list, detail, and proof access |
| S3-011 | Administrator verify/reject workflow and audit logging |
| S3-012 | Verified-payment and booking-confirmation coordination |
| S3-013 | Invoice migration, issue, detail, and print workflow |
| S3-014 | Full QA, documentation synchronization, and Sprint closure |

Each completed ticket receives focused tests, an exact-file staged review, a separate commit,
and a push to `feat/s3-payments-invoices`.

## 15. Required Test Coverage

At minimum, Sprint 3 tests must cover:

- payment migration constraints and rollback;
- zero/multiple attempts;
- exact NPR decimal validation;
- active/inactive payment-method selection;
- signed access and reference mismatch;
- deadline enforcement;
- upload size, MIME, extension, traversal, random naming, and cleanup;
- public rate limiting;
- separate retained resubmissions;
- absent Administrator session and invalid CSRF;
- only `submitted -> verified/rejected`;
- rejected attempt does not change booking;
- duplicate verification prevention;
- transaction rollback across payment, booking, event, and audit writes;
- verified payment confirms exactly one pending booking;
- invoice eligibility and one-per-booking constraint;
- stable unique invoice numbering;
- immutable historical invoice totals;
- protected proof and print responses;
- no refund endpoints;
- complete Sprint 0–2 regression safety.

## 16. Completion Rule

Sprint 3 is complete only when:

- all S3-001 through S3-014 deliverables are implemented or explicitly closed;
- database migrations are applied with zero pending migrations;
- focused, related, and complete automated suites pass;
- upload and financial security reviews pass;
- documentation matches executable migrations and routes;
- no private proof, credential, token, cache, or temporary artifact is tracked;
- pull-request review and remote CI pass.

S3-001 approval authorizes S3-002 implementation. It is not production approval.
