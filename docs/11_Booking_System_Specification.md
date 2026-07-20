# Tara Guest House Booking System Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 Guest and Administrator booking workflows
* Related contracts: `10_Database_Dictionary.md`, `22_System_State_Machine.md`, `23_REST_API_Specification.md`, `34_UI_UX_Screen_Specification.md`

## 1. Purpose

The Booking System creates and manages one-room Guest bookings while preventing overlapping stays, preserving Guest and financial snapshots, and enforcing the approved state machine.

## 2. Actors

### Guest

An unauthenticated Guest may search availability, create a booking without an account, securely submit payment information later, and receive transactional SMTP email.

### Administrator

An authenticated Administrator may view bookings, create a booking from the same Guest snapshot, reject/cancel eligible bookings, verify payment through Payment Service, and perform permitted check-in/check-out actions.

Guest is not a stored role. Customer accounts and specialized staff roles are Future Enhancements.

## 3. Separate Version 1 Processes

The following are distinct use cases and must not be merged into one form or transaction:

1. Booking creation.
2. Payment submission.
3. Administrator payment verification.
4. Booking confirmation through Booking Service after verification.

Booking creation never creates a payment row. Payment submission never lets the Guest select a booking state. Administrator verification changes the payment state and permits Booking Service—not frontend code or Payment Service—to confirm the booking.

## 4. Booking Creation

### 4.1 Routes

* PHP-rendered page: `/booking`
* Guest JSON endpoint: `POST /api/v1/bookings`
* Administrator page: `/admin/bookings/create`
* Administrator JSON endpoint: `POST /api/v1/admin/bookings`

### 4.2 Input Fields

| API field | Database destination | Required | Rule |
|---|---|---:|---|
| `room_id` | exactly one `booking_rooms.room_id` | Yes | Scalar; room must be eligible and available |
| `guest_full_name` | `bookings.guest_full_name` | Yes | Guest contact snapshot |
| `guest_email` | `bookings.guest_email` | Yes | Valid normalized email |
| `guest_phone` | `bookings.guest_phone` | Yes | Valid configured phone format |
| `check_in` | `bookings.check_in` | Yes | Valid arrival date |
| `check_out` | `bookings.check_out` | Yes | Later than check-in |
| `adults` | `bookings.adults` | Yes | Positive and within capacity |
| `children` | `bookings.children` | Yes | Non-negative and within capacity |
| `special_request` | `bookings.special_request` | No | Length-limited plain text |
| `terms_accepted` | `bookings.terms_accepted_at` | Yes | Must be true; server records timestamp |
| `policy_version` | `bookings.policy_version` | No | Approved displayed policy identifier |

Nationality, passport data and address are not approved required Version 1 fields. If the client or law requires them, an explicit decision must update the dictionary, API, UI, privacy/retention rules and tests together.

Payment method, transaction reference and payment voucher/proof are not booking-form fields. They belong only to the separate payment-submission process.

### 4.3 Service Behavior

Booking Service must:

1. Validate dates, occupancy, Guest snapshot and policy acceptance.
2. Lock/recheck room eligibility and overlap in a database transaction.
3. Accept exactly one room.
4. Calculate nights and fixed-precision NPR snapshots.
5. Generate a secure, non-sequential public booking reference with sufficient randomness.
6. Set `booking_status = pending`.
7. Set the configurable `confirmation_deadline_at`.
8. Create the booking and exactly one `booking_rooms` row.
9. Append the booking-created event.
10. Commit before sending notifications.
11. Create no payment row automatically.

Predictable running references such as `TGH-2026-000001` are not approved public-reference designs. A reference alone must never authorize public booking access.

## 5. Availability and Conflict Detection

A public room is bookable only when it is operationally `active`, published, within capacity, and has no unexpired overlapping blocking booking.

Blocking booking statuses are `pending`, `confirmed` and `checked_in`. Non-blocking statuses are `checked_out`, `cancelled` and `rejected`.

Approved overlap expression:

`existing.check_in < requested.check_out AND existing.check_out > requested.check_in`

Because both comparisons are strict, a new booking may start on the previous booking's checkout date. For example, an existing stay ending on 12 August does not overlap a new stay beginning on 12 August.

Availability checking and booking creation run transactionally to reduce double-booking risk. Maintenance and inactive rooms always fail eligibility.

## 6. Payment Submission

### 6.1 Routes

* PHP-rendered secure page: `/booking/payment`
* Payment-method endpoint: `GET /api/v1/payment-methods`
* Submission endpoint: `POST /api/v1/payments`

### 6.2 Fields

Payment submission accepts the secure booking-access proof, booking reference, configured `payment_method_id`, fixed-precision NPR `amount`, transaction/reference code, optional Guest notes and proof upload where the selected method requires it.

Each successful submission creates a new payment attempt with `payment_status = submitted`. A booking may have zero attempts and later multiple retained attempts. A rejected attempt is never overwritten or reset.

Upload MIME, extension and size limits come from approved configuration. This specification does not hardcode a limit.

## 7. Administrator Payment Verification

* Page: `/admin/payments/{id}`
* JSON endpoint: `PATCH /api/v1/admin/payments/{id}/status`

Only a `submitted` attempt may be changed to `verified` or `rejected`, and only by an authenticated Administrator through Payment Service. Amount, reference and proof are reviewed against the booking snapshot. Actor, time and note are retained in payment fields and `audit_logs`.

Rejecting a payment attempt does not reject or cancel the booking. The Guest may submit another attempt before the confirmation deadline.

## 8. Booking Confirmation

When Payment Service verifies the qualifying payment, it coordinates a transaction/use case with Booking Service. Booking Service rechecks current booking state and payment rules, then may transition `pending` to `confirmed`, set `confirmed_at`, append `booking_events` and commit. Transactional email/in-app Administrator notifications occur after commit.

The Administrator booking form and generic status UI must not offer arbitrary `confirmed` selection. There is no separate Approve Booking shortcut that bypasses payment verification.

## 9. Booking Statuses and Transitions

Canonical statuses:

* `pending`
* `confirmed`
* `checked_in`
* `checked_out`
* `cancelled`
* `rejected`

| Current | Next | Trigger |
|---|---|---|
| new | `pending` | Booking Service creates valid booking |
| `pending` | `confirmed` | Booking Service after verified payment |
| `pending` | `rejected` | Administrator rejection through Booking Service |
| `pending` | `cancelled` | Approved cancellation/deadline rule |
| `confirmed` | `checked_in` | Administrator check-in through Booking Service |
| `confirmed` | `cancelled` | Approved cancellation rule |
| `checked_in` | `checked_out` | Administrator check-out through Booking Service |

`checked_out`, `cancelled` and `rejected` are terminal. A rejected booking becomes `rejected`, never `cancelled`. There is no `completed`, booking `archived` or booking `expired` state.

An overdue pending booking ceases blocking availability and may be changed to `cancelled` with a recorded deadline-expiry reason. Deadline duration, cancellation window/fees and no-show handling require Architect/Client Confirmation.

## 10. Check-In and Check-Out

The Administrator requests explicit actions through `PATCH /api/v1/admin/bookings/{id}/status`.

* Check-in is allowed only from `confirmed`; Booking Service records `checked_in_at` and an event.
* Check-out is allowed only from `checked_in`; Booking Service records `checked_out_at`, changes the final successful status to `checked_out`, and records an event.

These actions do not write Available, Reserved or Occupied to the room. Current occupancy and future-date availability are recalculated from bookings.

## 11. Booking Editing and Retention

`PUT /api/v1/admin/bookings/{id}` may update only documented non-state fields when business rules permit. Status changes use the explicit transition endpoint/service.

Normal booking-delete actions are not part of Version 1. Historical bookings, events, payments and invoices are retained according to approved retention/soft-deletion policy. Neither Guest nor Administrator UI presents a Delete Booking action.

## 12. Invoice Relationship

An invoice may be generated after a booking has a verified payment and is `confirmed`. Invoice creation is a separate Invoice Service use case and does not occur automatically merely because a booking or payment record exists. The invoice number remains stable when printed or regenerated after checkout.

Tax/service-charge/rounding policy requires Architect/Client Confirmation.

## 13. Notifications

After successful commits, relevant events may create Administrator in-app notifications and transactional SMTP email to the Guest, including booking received, confirmed, cancelled/rejected, and payment submitted/verified/rejected. Guests do not have a notification centre.

Email failure is logged/retried and never rolls back a committed booking or payment transaction.

## 14. Validation and Security

* Server-side validation is authoritative.
* Guest endpoints use rate limiting, anti-abuse/CSRF public-form controls and signed scoped access where needed.
* Administrator endpoints require a valid PHP session, authorization and CSRF for state changes.
* Controllers, views and Models/entities execute no SQL; Repositories use PDO/prepared statements.
* Output is contextually escaped.
* Uploads use approved MIME/extension/size configuration, randomized names and non-executable storage.
* Booking references and signed access values are not logged as authorization secrets.
* Sensitive changes create permanent events/audit records.

## 15. Acceptance Tests

1. Guest booking succeeds without an account and stores the required snapshot.
2. Booking accepts exactly one room and rejects arrays/multiple rooms.
3. Booking begins at `pending` and creates zero payment rows.
4. Adjacent stays sharing the prior checkout/new check-in date do not overlap.
5. Each payment submission creates a separate retained attempt.
6. Only Administrator can verify/reject a submitted payment.
7. Payment verification permits Booking Service to confirm; rejection does not reject the booking.
8. Invalid booking transitions fail and do not mutate records.
9. Check-out ends at `checked_out` and creates no stored room occupancy state.
10. Rejected booking transition uses `rejected`, not `cancelled`.
11. Public reference alone cannot expose booking details.
12. No normal booking-delete endpoint/action exists.

## 16. Future Enhancements

* Customer accounts, Customer Portal and self-service booking history;
* multiple rooms per booking;
* payment gateways and automatic verification;
* specialized staff roles;
* SMS, push and WhatsApp notifications;
* booking modification/cancellation self-service after an approved policy;
* nationality/passport/address collection only if later legally/client-approved with full data-contract updates.
