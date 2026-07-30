# Sprint 3 QA Closure Report

## 1. QA Purpose

This report records formal local Quality Assurance for the Sprint 3 Manual Payments and Invoices slice. It verifies configurable manual payment methods, public method reads, secure Guest payment submission, retained payment attempts, confidential payment-proof handling, Administrator payment review and decisions, payment-driven booking confirmation, stable invoice issue/detail/print workflows, database migrations, audit evidence, regression safety, and documentation synchronization.

This report does not approve production deployment, payment gateways, refunds, invoice corrections, notification delivery, or later Version 1 modules. Final Sprint 3 closure remains subject to pull-request review, remote Linux/MariaDB CI, Technical Architect approval, and merge into `develop`.

## 2. Sprint 3 Ticket Inventory

| Ticket | Feature | Status | QA result |
|---|---|---|---|
| S3-001 | Scope, source analysis, and approved policy record | Completed | Passed |
| S3-002 | Payment-method and payment database foundation | Implemented | Passed |
| S3-003 | Payment models and repositories | Implemented | Passed |
| S3-004 | Administrator payment-method management | Implemented | Passed |
| S3-005 | Public active payment-method API | Implemented | Passed |
| S3-006 | Private payment-proof configuration and storage | Implemented | Passed |
| S3-007 | Guest payment request and secure-access validation | Implemented | Passed |
| S3-008 | Transactional Guest payment submission | Implemented | Passed |
| S3-009 | PHP-rendered Guest payment and success workflow | Implemented | Passed |
| S3-010 | Administrator payment list, detail, and proof access | Implemented | Passed |
| S3-011 | Administrator verify/reject workflow and audit logging | Implemented | Passed |
| S3-012 | Verified-payment and booking-confirmation coordination | Implemented | Passed |
| S3-013 | Invoice migration, issue, detail, and print workflow | Implemented | Passed |
| S3-014 | Full QA, documentation synchronization, and Sprint closure | Executed | Passed locally; external review gates remain |

## 3. Environment and Baseline

- Repository: `C:\TaraGuestHouse`
- Branch: `feat/s3-payments-invoices`
- Sprint 3 implementation baseline before S3-014: `b43c6b9 feat(invoices): add issue and print workflow`
- Operating system: Microsoft Windows 10 build `26200.8973`
- PHP: `8.3.32`
- PHPUnit: `12.5.31`
- Database: MariaDB/MySQL through PDO on the configured isolated development/test connections
- Local development port: `3307` (environment-specific, not a project-wide requirement)
- Exact MariaDB server version: not recaptured during S3-014
- Application timezone: `Asia/Kathmandu`
- Database migration status: 16 applied, 0 pending

No credential, DSN, password, session identifier, CSRF token, booking-access token, payment proof, private proof path, transaction reference, or Guest contact value is recorded in this report.

## 4. Automated QA Evidence

| Evidence | Tests | Assertions | Skipped | Result |
|---|---:|---:|---:|---|
| S3-008 focused payment-submission suite | 88 | 609 | 0 | Passed |
| S3-009 focused payment-page suite | 110 | 709 | 0 | Passed |
| S3-010 focused Administrator payment-read suite | 81 | 575 | 1 | Passed |
| S3-012 focused coordinated-confirmation suite | 99 | 847 | 0 | Passed |
| S3-013 migration/command regression suite | 36 | 436 | 0 | Passed |
| Final complete regression suite (`composer test`) | 1,558 | 9,631 | 11 | Passed |

The final complete suite finished with zero failures and zero errors. The 11 skipped tests are established platform-dependent symlink, filesystem-permission, or isolated-environment guards; their assertions remain active for compatible Linux/CI environments.

Expected sanitized diagnostics appeared during negative/fallback tests:

- `Application exception logging failed.`
- `Administrator bootstrap security logging failed.`
- `Application file logging is unavailable; using the PHP error log.`

These diagnostics are expected test evidence and did not fail the suite.

## 5. Database and Migration Evidence

Sprint 3 adds these executable migrations:

- `20260728000100_create_payment_methods_table.php`
- `20260728000200_create_payments_table.php`
- `20260730000100_create_invoices_table.php`
- `20260730000200_create_invoice_items_table.php`

The development database was migrated from 14 applied/2 pending to 16 applied/0 pending. Automated migration coverage verifies application, rollback in isolated test databases, reapplication, constraints, foreign-key retention, payment status vocabulary, safe proof paths, one invoice per booking, unique invoice numbers, fixed NPR currency, zero tax, balanced totals, and invoice-item arithmetic.

Command-test cleanup was updated so isolated migration cycles drop `invoice_items` before `invoices` and do not leave stale Sprint 3 tables after removing migration history.

## 6. Payment-Method and Guest Submission Evidence

Implemented behavior includes:

- Administrator list/create/edit/activate/deactivate workflows for manual payment methods;
- inactive methods retained for historical reads but excluded from new public submissions;
- public `GET /api/v1/payment-methods` exposing only active safe method data;
- no QR-image upload workflow despite the nullable future-ready schema column;
- `POST /api/v1/payments` plus PHP-rendered `/booking/payment` workflow;
- hardened session continuation for the immediate post-booking flow;
- opaque signed booking access for the JSON contract;
- exact `NPR` amount equality with the booking total;
- fixed-precision money processing without floats;
- separate payment rate limiting at five attempts per direct peer IP per 15-minute window with a 15-minute block;
- one new retained `submitted` row per successful submission;
- rejected attempts retained and resubmission stored as a new row;
- no automatically created incomplete payment row at booking creation;
- transaction rollback and proof cleanup on failed submission.

## 7. Confidential Payment-Proof Security Evidence

Payment proofs are restricted to server-inspected JPEG, PNG, or PDF content up to exactly 5,242,880 bytes. Storage uses randomized 64-hex filenames under `storage/private/payment-proofs/`, outside `public/`, with safe relative database paths.

Protected proof retrieval:

- requires an active Administrator session;
- uses `GET /admin/payments/{id}/proof`;
- returns Not Found for absent payment/proof or invalid storage state;
- rejects traversal, malformed paths, symlinks, MIME/extension mismatch, oversized files, and replacement races;
- returns a server-selected media type and filename;
- uses `Cache-Control: no-store` and `X-Content-Type-Options: nosniff`;
- never exposes the physical storage path;
- appends a safe `payment_proof_accessed` audit record before returning bytes;
- fails closed if audit persistence fails.

The repository snapshot contains no real payment proof, runtime session, log, cache, backup, credential, `.env`, or uploaded Guest artifact. `.gitignore` preserves only approved placeholder/protection files in private/runtime directories.

## 8. Administrator Payment Decision and Booking Coordination Evidence

The implemented JSON mutation is exactly:

`PATCH /api/v1/admin/payments/{id}/status`

Verified controls include:

- Administrator authentication before JSON CSRF validation;
- strict content type and payload allowlisting;
- required length-limited plain-text review note;
- only `submitted -> verified` or `submitted -> rejected`;
- payment row locking and caller-owned transactions;
- duplicate qualifying verification prevention;
- exact booking/payment `NPR` amount validation;
- deadline enforcement;
- safe conflict responses;
- rejection changes only the payment attempt;
- no refund target or endpoint.

Verification coordinates one transaction that locks payment then booking, marks the payment verified, invokes the internal Booking Service operation, changes the booking from `pending` to `confirmed`, sets `confirmed_at`, appends a payment-linked booking event, appends separate payment and booking audit evidence, and commits all writes together. Any failure rolls back the payment, booking, event, and audits.

The generic Administrator booking endpoint still rejects arbitrary `confirmed`; payment verification is the only active confirmation authority.

## 9. Invoice Evidence

Implemented invoice behavior includes:

- one invoice per eligible confirmed booking;
- exactly one qualifying verified payment;
- idempotent repeat issue returning the existing invoice;
- stable unique `TGH-INV-YYYY-NNNNNN` numbering;
- immutable accommodation line item from booking-room snapshots;
- exact `NPR` currency;
- `tax_amount = 0.00`;
- no service charge;
- total equal to subtotal plus zero tax;
- issue timestamp not earlier than payment verification or booking confirmation;
- transactional invoice, item, and `invoice_issued` audit persistence;
- rollback on repository, item, audit, or commit failure;
- protected Administrator list/detail pages;
- protected no-store, noindex invoice print route;
- no invoice edit/delete endpoint or control;
- no correction, credit-note, or refund implementation.

The implemented JSON issue endpoint is exactly:

`POST /api/v1/admin/bookings/{bookingId}/invoice`

It accepts only an empty JSON object and never accepts client-controlled totals, currency, tax, service charge, or invoice number.

## 10. Route and Architecture Review

Sprint 3 follows the required flow:

`Route -> Middleware -> Controller -> Request Validation -> Service -> Repository -> Database`

Implemented Sprint 3 route boundaries are:

### Public/PHP routes

- `GET /booking/payment`
- `POST /booking/payment`
- `GET /booking/payment/success`

### Public JSON routes

- `GET /api/v1/payment-methods`
- `POST /api/v1/payments`

### Administrator PHP routes

- payment-method management under `/admin/payment-methods...`
- `GET /admin/payments`
- `GET /admin/payments/{id}`
- `GET /admin/payments/{id}/proof`
- `GET /admin/invoices`
- `GET /admin/invoices/{id}`
- `GET /admin/invoices/{id}/print`

### Administrator JSON mutations

- `PATCH /api/v1/admin/payments/{id}/status`
- `POST /api/v1/admin/bookings/{bookingId}/invoice`

Payment-driven confirmation calls the Booking Service internally and does not expose a new arbitrary confirmation route.

No customer account, JWT, specialized staff role, multi-room booking, payment gateway, refund endpoint, invoice deletion, framework, ORM, SPA, Redis, WebSocket, or unapproved package was introduced.

## 11. Documentation Synchronization

S3-014 synchronizes:

- `README.md` — records implemented payments/invoices, 16 migrations, and the final local test baseline;
- `docs/08_Database_Design.md` — records zero tax, no service charge, and immutable invoice policy;
- `docs/09_ER_Diagram.md` — removes an unimplemented refund-actor relationship claim;
- `docs/10_Database_Dictionary.md` — aligns invoice tax/total descriptions to executable constraints;
- `docs/19_Payment_Management_System.md` — aligns route boundaries, exact proof policy, and deferred notifications;
- `docs/21_Business_Process_Flow.md` — removes the nonexistent intermediate pending-attempt write;
- `docs/23_REST_API_Specification.md` — removes unimplemented Administrator read JSON endpoints and records exact issue/decision contracts;
- `docs/28_User_Manual.md` — blocks inferred refunds and records implemented invoice policy;
- `docs/33_Documentation_Index.md` — registers Sprint 3 implementation and this closure report;
- `docs/34_UI_UX_Screen_Specification.md` — aligns screen/API boundaries and invoice presentation;
- `docs/35_Requirements_Traceability_Matrix.md` — traces payment, confirmation, invoice, proof, and audit implementation/tests;
- `docs/42_Sprint_3_Payments_Invoices_Implementation_Plan.md` — records the local execution outcome;
- `docs/43_Sprint_3_QA_Closure_Report.md` — records this formal QA evidence and verdict.

Executable migrations and registered routes remain the source of truth where older planning material describes later or future capabilities.

## 12. Known Deferred or Partial Functionality

The following remain intentionally outside Sprint 3 or require later approved work:

- payment gateways, callbacks, and automatic verification;
- any refund mutation, partial/full refund accounting, or refund-specific fields;
- partial payments, installments, overpayments, and split tender;
- payment-method QR image upload management;
- invoice edits, deletion, corrections, credit notes, or non-zero tax/service-charge policy;
- transactional SMTP and Administrator notification delivery;
- automatic scheduled cancellation of expired pending bookings;
- remaining operations/content modules, dashboard completion, and audit-log administration;
- browser/device matrix evidence, manual UAT, performance/load testing, penetration testing, and accessibility audit;
- dependency vulnerability review when network access permits;
- backup/restore rehearsal, cPanel capability verification, production hardening, and deployment approval.

## 13. Manual and External Gates

Automated feature tests cover rendered payment and invoice pages, JSON contracts, proof responses, state decisions, transaction rollback, migrations, and security boundaries. A separate manual browser/device QA session was not recorded during S3-014 and must not be claimed as completed.

Before Sprint 3 is merged, the following gates remain:

- pull request from `feat/s3-payments-invoices` into `develop`;
- review of the exact S3-014 documentation diff and complete Sprint 3 branch diff;
- remote GitHub Actions checks on PHP 8.3 and MariaDB 11.8;
- Technical Architect approval;
- Project Manager functional review where required;
- resolution of any merge conflict against the current `develop` branch;
- no direct push or merge to `main`.

After an approved merge into `develop`, delete the feature branch only after confirming the remote merge commit and retained CI evidence.

## 14. Git Evidence

Sprint 3 implementation commits after the S3-007 baseline include:

- `c29b7e4 feat(payments): add transactional guest payment submission`
- `ebf547e feat(payments): add guest payment pages`
- `ae5b2bd feat(payments): add administrator payment review`
- `ed72fa3 feat(payments): add administrator payment decisions`
- `b9546ce feat(payments): coordinate booking confirmation`
- `b43c6b9 feat(invoices): add issue and print workflow`

Each ticket used an exact-file staged review and was pushed to `origin/feat/s3-payments-invoices`. S3-014 must be committed separately after its documentation/static/full-suite checks pass.

Local helper documents, downloaded review patches, generated source archives, `artifacts/`, and `build-chatgpt-review-package.php` are not project deliverables and must remain untracked or be removed before merge.

## 15. Final Acceptance Verdict

**All locally executable blocking Sprint 3 implementation and automated regression checks passed.**

The Manual Payments and Invoices slice is complete within approved Sprint 3 scope. Its migrations, payment methods, Guest submissions, private proof handling, Administrator reads and decisions, payment-driven booking confirmation, invoices, audit evidence, security controls, routes, pages, JSON contracts, and synchronized documentation are ready for pull-request review into `develop`.

This is not production approval. Pull-request review, remote CI, Technical Architect approval, merge into `develop`, later UAT/hardening, and deployment gates remain required.

**Status: READY FOR PULL-REQUEST REVIEW INTO `develop`.**
