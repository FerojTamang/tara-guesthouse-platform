# Tara Guest House Management System

# 21. Business Process Flow

## Document Control

| Field | Value |
|---|---|
| Version | 1.2 |
| Status | Synchronized — Review Required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |

# 1. Actors

- **Guest:** unauthenticated public visitor; may browse, check availability, create one-room bookings, submit payment attempts securely, and submit a token-authorized review after checkout.
- **Administrator:** authenticated staff user using a hardened PHP session; manages Version 1 administration.

Customer accounts/portal and specialized staff permission systems are future enhancements.

# 2. Route Categories

- Server-rendered pages use normal routes such as `/rooms`, `/booking`, and `/admin/bookings`.
- Public/Guest JSON actions use `/api/v1/...`.
- Protected Administrator JSON actions use `/api/v1/admin/...` with PHP-session authentication, authorization, and CSRF protection.

# 3. Public Content Flow

1. Guest requests a PHP-rendered page.
2. Controller requests published data through the appropriate Service/Repository.
3. Public rooms require `operational_status = active` and publication enabled.
4. Public activities/gallery require active and published controls.
5. Public CMS pages require `status = published`.
6. The server renders the Bootstrap view.
7. Selective Fetch requests use versioned public JSON endpoints.

# 4. Availability Flow

Endpoint: `GET /api/v1/availability`

1. Guest supplies check-in, check-out, and occupancy.
2. Request validation requires valid dates and `check_out > check_in`.
3. Availability Service excludes maintenance/inactive rooms and unpublished rooms for public results.
4. It excludes rooms with an unexpired overlapping booking in `pending`, `confirmed`, or `checked_in`.
5. It ignores `checked_out`, `cancelled`, and `rejected` bookings.
6. It applies:

   `existing.check_in < requested.check_out AND existing.check_out > requested.check_in`

7. It returns a calculated result. No stored room status changes.

# 5. Guest Booking Flow

Page route: `/booking`
JSON endpoint: `POST /api/v1/bookings`

1. Guest selects exactly one room.
2. Guest supplies full name, email, phone, occupancy, dates, special request where applicable, and policy acceptance.
3. Request class validates the payload and occupancy.
4. Booking Service opens a database transaction.
5. Availability is checked again inside the transaction.
6. Booking Service stores guest/contact and NPR price snapshots, policy acceptance timestamp, secure booking reference, and confirmation deadline.
7. Booking status is stored as `pending`.
8. Exactly one `booking_rooms` record is stored.
9. A permanent booking-created event is appended.
10. The transaction commits.
11. The system sends an in-app Administrator notification and attempts transactional SMTP booking-received email.
12. Email failure is logged and does not roll back the booking.

A payment record is not automatically required. The booking may have zero payment attempts until payment information is submitted.

# 6. Guest Payment-Submission Flow

JSON endpoint: `POST /api/v1/payments`

1. Guest uses the secure booking-access mechanism supplied by the booking flow.
2. Guest selects an active configured manual payment method.
3. Payment Service creates an attempt as `pending` if no attempt has yet been created for this submission flow.
4. Guest supplies amount, transaction/reference code, notes, and proof where required.
5. Upload and input validation run.
6. Payment Service changes that attempt to `submitted` and records `submitted_at`.
7. Administrator receives an in-app notification and transactional SMTP notification may be sent.

If an earlier attempt is `rejected`, resubmission creates a new attempt. It does not reset the rejected record.

# 7. Administrator Payment Verification Flow

Page route: `/admin/payments`
JSON endpoint: `PATCH /api/v1/admin/payments/{id}/status`

1. Administrator session, authorization, and CSRF are validated.
2. Administrator reviews a `submitted` payment attempt.
3. Administrator selects `verified` or `rejected` and supplies the required note.
4. Payment Service validates and records the transition with actor and timestamp.
5. When a payment becomes `verified`, Payment Service notifies Booking Service.
6. Booking Service may transition the booking from `pending` to `confirmed`, records `confirmed_at`, and appends the booking event.
7. Confirmation notifications/email are attempted after commit.
8. A rejected attempt leaves the booking `pending` until its deadline unless the Booking Service separately cancels/rejects it.

Payment status remains on payment records. It is never represented as Pending Payment or Payment Verified booking status.

# 8. Pending Booking Expiry

1. A supported scheduled process finds `pending` bookings beyond `confirmation_deadline_at`.
2. They stop blocking availability.
3. Booking Service may transition each to `cancelled` with an expiry reason and timestamp.
4. A booking event and applicable notification are recorded.

The exact deadline and cron interval require approved configuration.

# 9. Check-In Flow

Page route: `/admin/bookings/{id}`
JSON endpoint: `PATCH /api/v1/admin/bookings/{id}/status`

1. Administrator requests transition from `confirmed` to `checked_in`.
2. Booking Service validates the current state and check-in policy.
3. It stores `booking_status = checked_in` and `checked_in_at`.
4. It appends the booking event and commits.

The room operational status is not changed to occupied. Current occupancy is calculated from the checked-in booking.

# 10. Check-Out Flow

Page route: `/admin/bookings/{id}`
JSON endpoint: `PATCH /api/v1/admin/bookings/{id}/status`

1. Administrator requests transition from `checked_in` to `checked_out`.
2. Booking Service stores `booking_status = checked_out` and `checked_out_at`.
3. It appends the booking event and commits.
4. Review Service may create a secure single-use review invitation.
5. Review-invitation email is attempted and failures are logged.

`checked_out` is the terminal successful booking status. There is no Booking Completed state. The room operational status is not changed to available; date availability is recalculated from bookings.

# 11. Cancellation and Rejection

JSON endpoint: `PATCH /api/v1/admin/bookings/{id}/status`

- `pending → cancelled`: record `cancelled_at` and cancellation reason.
- `pending → rejected`: record `rejected_at` and rejection reason.
- `confirmed → cancelled`: record `cancelled_at` and reason subject to approved booking policy.

The change is made only by Booking Service, appended to booking history, and reflected automatically in calculated availability.

# 12. Invoice Flow

1. A verified payment allows confirmation.
2. Administrator requests invoice issue through the Invoice Service.
3. Invoice Service uses booking and verified-payment NPR snapshots.
4. It creates a stable unique invoice number and immutable issued values.
5. The invoice may be rendered/printed again after checkout without changing its number.
6. Corrections are audited; issued invoices are not deleted.

Client tax/VAT presentation remains **Architect/Client Confirmation Required**.

# 13. Review Flow

Public JSON endpoint: `POST /api/v1/reviews`
Administrator JSON endpoint: `PATCH /api/v1/admin/reviews/{id}/status`

1. Review Service hashes/validates the single-use token.
2. It verifies the related booking has `booking_status = checked_out` and has no existing review.
3. It creates one review as `pending` and consumes the invitation.
4. Administrator changes `pending` to `approved` or `rejected`.
5. Only approved reviews appear publicly.

# 14. CMS, Activity, and Gallery Flow

- Settings changes update site-wide configuration through protected Administrator endpoints.
- Structured pages move among `draft`, `published`, and `archived` through CMS Service.
- Activities and gallery items use separate active and publication controls.
- Only active and published activity/gallery records are public.
- All publication and important settings changes are audited.

# 15. Contact Flow

Public endpoint: `POST /api/v1/contact`
Administrator endpoints: `/api/v1/admin/contact-messages...`

1. Guest submits validated contact data.
2. Contact Service stores the message.
3. Administrator receives an in-app notification and optional transactional SMTP notice.
4. Administrator views/updates the message through protected endpoints.

Retention, assignment, and response-SLA policies remain **Architect/Client Confirmation Required**.

# 16. Authentication Flow

1. Administrator submits login to `POST /api/v1/admin/auth/login`.
2. Credentials and rate limit are validated.
3. Session ID is regenerated and a secure PHP session is created.
4. Protected page/API requests validate session, authorization, and CSRF where state changes occur.
5. Logout through `POST /api/v1/admin/auth/logout` destroys the session.

Version 1 does not issue JWT, access tokens, or refresh tokens.

# 17. Failure and Audit Rules

- Business transactions commit before non-critical email delivery.
- Validation failures do not change state.
- Services control all transitions.
- Booking events permanently record booking state changes.
- Audit logs record important Administrator/security changes.
- Public error messages are generic; private logs contain diagnostic detail.
