# Tara Guest House Payment Management Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 manual payment methods, submissions and Administrator verification

## 1. Core Rules

* Booking creation creates no payment record.
* A payment attempt begins only when payment information is submitted.
* One booking has zero or more retained payment attempts.
* Payment statuses are `pending`, `submitted`, `verified`, `rejected`, `refunded`.
* Only Administrator verifies/rejects.
* Verification permits Booking Service to confirm a pending booking.
* A rejected attempt does not reject/cancel the booking and is never overwritten.
* Gateway callbacks and automatic processing are Future Enhancements.

## 2. Data Contract

### Payment methods

`payment_methods` stores configured manual method name, instructions, account reference, optional QR image path, proof requirement, active control and timestamps.

### Payment attempts

| Field | Purpose |
|---|---|
| `booking_id`, `payment_method_id` | Required relationships |
| `currency`, `amount` | Fixed-precision NPR submission snapshot |
| `transaction_reference`, `proof_path`, `guest_notes` | Submitted evidence |
| `payment_status` | Canonical state |
| `submitted_at` | Submission time |
| `verified_at`, `verified_by` | Verification evidence |
| `rejected_at`, `rejected_by` | Rejection evidence |
| `verification_note` | Verification/rejection note |
| timestamps | Record history |

No payment-event table is required. Submission/verification/rejection history is retained through the attempt's status/actor/timestamp/note fields plus append-only `audit_logs`. Each resubmission is a new attempt, preserving prior states.

Refund amount and final refund-specific fields are not approved until the refund policy decision.

## 3. Submission Workflow

* Page: `/booking/payment`
* `GET /api/v1/payment-methods`
* `POST /api/v1/payments`

The Guest proves secure booking access and submits `booking_reference`, signed access value, `payment_method_id`, NPR `amount`, transaction reference, optional notes and proof where configured. Payment Service validates the booking/deadline/method/amount/evidence, stores a new attempt as `submitted`, and commits before notification.

There is no automatically created `pending` attempt during booking. `pending` remains a canonical value only for an explicitly created-but-not-submitted attempt if a later approved service flow uses it; the active Guest endpoint creates `submitted` after complete validation.

## 4. Administrator Verification

* Pages: `/admin/payments`, `/admin/payments/{id}`
* `GET /api/v1/admin/payments`
* `GET /api/v1/admin/payments/{id}`
* `PATCH /api/v1/admin/payments/{id}/status`

Only a `submitted` attempt may transition to `verified` or `rejected`. Payment Service records Administrator, time and note. Verification coordinates with Booking Service so only it may transition a currently `pending` booking to `confirmed`. Duplicate qualifying verification is prevented transactionally.

## 5. Refund Boundary

`refunded` is a canonical status for later approved manual recording, but implementation is blocked. Full-versus-partial behavior, amount representation, approval authority, accounting treatment and audit procedure require Architect/Client Confirmation.

Version 1 documentation defines no refund button, request payload, mutation endpoint or completed refund acceptance criterion. Developers must not infer one.

## 6. Proof Upload

Proof uploads use approved configuration for size, MIME, extension and storage. Filenames are randomized, content is validated, and storage cannot execute PHP. No numeric maximum is hardcoded here.

## 7. Security and Responsibilities

Payment Service owns validation, transitions, transactions and Booking Service coordination. Payment Repository owns PDO/SQL. Payment entities contain data and execute no SQL. Controllers/views never query the database. Guest submission is signed/rate-limited; Administrator mutations require session, authorization and CSRF. Sensitive evidence access is protected and audited.

## 8. Notifications

Guests receive transactional SMTP email for submitted/verified/rejected events. Administrators receive in-app notifications and may also receive configured transactional email. Delivery failures do not roll back committed payment/booking transitions.

## 9. Acceptance Tests

* Booking creation leaves zero payment rows.
* Each submission creates a distinct `submitted` attempt.
* Rejected attempts remain retained and resubmission creates another row.
* Guest cannot set verified/rejected/refunded.
* Only Administrator can verify/reject a submitted attempt.
* Verification permits Booking Service confirmation; rejection does not mutate booking state.
* Duplicate qualifying verification is blocked.
* Upload rules come from configuration.
* No refund implementation is accepted until policy approval.

## 10. Future Enhancements

Gateway checkout/callbacks, automatic verification/refunds, installment/partial-payment policy, advanced reconciliation and refund implementation require later approved contracts.
