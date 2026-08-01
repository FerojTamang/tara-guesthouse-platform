# Tara Guest House Management System

# 08. Database Design

## Document Control

| Field | Value |
|---|---|
| Version | 1.3 |
| Status | Implemented through Sprint 4 — Review required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Database | MariaDB/MySQL |
| Executable source of truth | Versioned database migrations |

# 1. Purpose

This document defines the approved Version 1 relational data design. The database dictionary and ER diagram must match the migrations. `database/schema.sql` is an optional installation snapshot and must not override migrations.

# 2. Standards

- Use InnoDB, foreign keys, indexes, and transactions.
- Use snake_case table and column names.
- Use unsigned integer primary keys unless an approved migration specifies another strategy.
- Use `DECIMAL(12,2)` or an approved equivalent for NPR financial values. Floating-point storage is prohibited.
- Store workflow statuses as short strings controlled by PHP domain constants and validated by Services.
- Store timestamps consistently and display them in `Asia/Kathmandu`.
- Use `created_at` and `updated_at`; add `deleted_at` only where soft deletion is approved.
- Records referenced by bookings, payments, invoices, reviews, or audits are not physically deleted through normal administration.

# 3. Version 1 Data Modules

| Module | Tables |
|---|---|
| Administrator authentication | `roles`, `users` |
| Rooms | `room_types`, `rooms`, `room_images`, `amenities`, `room_amenities` |
| Bookings | `bookings`, `booking_rooms`, `booking_events` |
| Payments and invoices | `payment_methods`, `payments`, `invoices`, `invoice_items` |
| Activities and gallery | `activities`, `gallery_categories`, `gallery` |
| Reviews | `reviews`, `review_invitations` |
| Contact and CMS | `contact_messages`, `settings`, `pages` |
| Operations | `notifications`, `audit_logs` |

# 4. Authentication and Users

Version 1 stores Administrator staff accounts only. Guest is an unauthenticated actor and is not stored as a role. The `roles` structure may remain future-ready, but Version 1 seed data requires only Administrator.

Customer registration and the customer portal are future enhancements. A nullable future customer/user relationship may exist on a booking, but Version 1 booking creation must not require it.

A `password_resets` table and staff reset workflow are not present in the 26 executable migrations. Any later reset implementation must be Administrator-only and must store only a hash, expiry, and single-use state.

# 5. Room Model

## 5.1 Operational Status

`rooms.operational_status` uses exactly:

- `active`
- `maintenance`
- `inactive`

## 5.2 Publication

`rooms.is_published` is separate from operational status. A room is publicly visible only when `operational_status = active` and `is_published = true`.

## 5.3 Calculated Availability and Occupancy

Available, reserved, and occupied are calculated conditions, not stored room operational statuses.

For a requested range, a room is unavailable when an unexpired booking with status `pending`, `confirmed`, or `checked_in` overlaps:

`existing.check_in < requested.check_out AND existing.check_out > requested.check_in`

Bookings with status `checked_out`, `cancelled`, or `rejected` do not block. Maintenance and inactive rooms always block.

# 6. Booking Model

## 6.1 Guest Snapshot

Each booking stores an immutable-at-booking-time guest and policy snapshot:

- guest full name;
- email;
- phone;
- adults and children or equivalent occupancy values;
- check-in and check-out dates;
- booking reference;
- special request where supplied;
- agreed room-price snapshot;
- terms/policy acceptance timestamp;
- optional policy/version identifier when available.

No registered customer account is required.

## 6.2 Cardinality

Version 1 permits exactly one room per booking. The `booking_rooms` junction remains future-ready, but a migration constraint where practical plus Request/Service validation and tests must enforce one `booking_rooms` row per booking in Version 1.

## 6.3 Booking Status

Canonical values:

- `pending`
- `confirmed`
- `checked_in`
- `checked_out`
- `cancelled`
- `rejected`

There is no Version 1 `completed` or workflow `archived` booking status.

## 6.4 Operational Timestamps and Deadline

The booking design includes:

- `confirmation_deadline_at` for the configurable payment/confirmation deadline;
- `confirmed_at`;
- `checked_in_at`;
- `checked_out_at`;
- `cancelled_at`;
- `cancellation_reason`;
- `rejected_at` and `rejection_reason` where rejected.

Expired pending bookings cease blocking availability and may be changed to `cancelled` with a recorded reason.

## 6.5 Permanent Transition History

Every booking transition is appended to `booking_events` with booking, from-status, to-status, event type, actor when applicable, reason/notes, and event timestamp. History is never updated into a different event and is not deleted through normal administration.

# 7. Payment Model

A booking has zero or more payment attempts. A payment record is not required at booking creation.

Canonical payment statuses:

- `pending`
- `submitted`
- `verified`
- `rejected`
- `refunded`

Each attempt stores booking, method, amount, transaction/reference code, proof path when required, guest notes, Administrator verification/rejection data, and timestamps. One verified payment is expected to cover the required Version 1 booking amount. Duplicate verification is prohibited.

Payment verification may allow the Booking Service to transition a booking from `pending` to `confirmed`. Rejecting one attempt does not reject the booking; a new attempt may be created before the booking deadline.

Automatic gateway callbacks and gateway refunds are future. The client policy for full versus partial manual refund recording remains **Architect/Client Confirmation Required**.

# 8. Invoice Model

- Invoice numbers are unique and stable.
- Monetary values are fixed-precision NPR snapshots derived from booking and verified-payment data.
- Later room-price changes do not alter issued invoices.
- Issued invoices are not deleted or edited through implemented Version 1 routes.
- Invoice issue is audited; a later approved correction policy must add separate audited behavior rather than rewriting issued snapshots.
- The implemented Version 1 invoice tax is exactly `NPR 0.00`; no service charge is stored or calculated.

# 9. CMS and Settings

Version 1 uses two structures:

1. `settings` for site-wide configuration such as contact information, social links, booking policies, and operational settings.
2. `pages` for structured About, policy, and other public content.

`pages.status` uses exactly `draft`, `published`, or `archived`. Only published pages are public. `settings` updates are limited to an environment-provided key/type/publicity allowlist, and public reads require `is_public = true`. Full version history and rollback are future enhancements.

# 10. Activities and Gallery

Activities and gallery items each have separate controls for business activity and publication:

- `is_active` controls operational use;
- `is_published` controls public visibility.

Only active and published records are public. These controls must not be collapsed into one ambiguous status. Gallery files use server-generated names and safe relative paths under private non-executable storage; the executable schema stores no public absolute path.

# 11. Reviews

A review is eligible only for a booking whose `booking_status = checked_out`.

- One review per booking.
- Status values: `pending`, `approved`, `rejected`.
- Only approved reviews are public.
- Customer login is not required.
- `review_invitations` stores one 64-character lowercase SHA-256 token hash, booking, expiry, use timestamp, and creation timestamp.
- The invitation is single-use and cannot create a second review for the same booking.

# 12. Notifications and Email

Version 1 stores retained in-app notifications owned by one Administrator `user_id`, with optional related entity data and a nullable `read_at`. Read actions are owner-scoped; normal administration provides no delete/archive workflow.

Transactional SMTP is not persisted as a queue table. One delivery attempt occurs only after the owning booking, payment, or checkout transaction commits. A delivery failure is sanitized and logged without rolling back committed domain state.

SMS, WhatsApp, push, WebSockets, and permanent queue-worker data are future enhancements.

# 13. Audit and Retention

Audit logs cover Administrator login/security events, booking transitions, payment verify/reject, invoice issue, room/pricing changes, CMS publication, review moderation, staff/settings changes, and important backup actions. A later approved refund implementation must also be audited, but no current refund fields or workflow are defined.

Audit and booking-event records are retained and are not editable through normal administration.

# 14. Migration and Seeder Requirements

Migrations must create all approved Version 1 tables, fields, foreign keys, indexes, and constraints in dependency order. The executable production seeder creates only the non-secret `Administrator` role prerequisite. The initial Administrator account is created through the protected single-use CLI bootstrap command, not a seeder. Settings and payment methods are configured through their approved authenticated/deployment workflows rather than invented default seed data.

# 15. Required Database Tests

- overlap boundary and blocking-status queries;
- expired pending-booking behavior;
- one-room-per-booking enforcement;
- concurrent booking creation protection;
- allowed/forbidden booking transitions and event history;
- multiple payment attempts and duplicate-verification prevention;
- checked-out review eligibility and single-use invitation;
- fixed-precision historical booking/invoice snapshots;
- operational/publication filtering;
- soft-deletion and audit-retention rules.

# 16. Unresolved Policies

The database implementation must not guess these values:

- full versus partial manual refund policy;
- any future non-zero tax/VAT, service-charge, credit-note, or invoice-correction policy;
- the deployment value for review-invitation expiry (required by configuration; no hardcoded fallback);
- contact-message retention duration.

They require an approved client/architect policy or implementation ticket before related constraints are finalized.
