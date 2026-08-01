# Tara Guest House Management System

# 34. UI/UX Screen Specification

## Document Control

| Field | Value |
|---|---|
| Version | 1.3 |
| Status | Implemented route/screen inventory through Sprint 4 — Review Required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Rendering | PHP-rendered Bootstrap 5 views with vanilla JavaScript |

# 1. UI Architecture

- Pages are rendered by PHP.
- Bootstrap 5 provides responsive layout/components.
- Vanilla JavaScript ES6+ provides interaction.
- Fetch/AJAX is selective and same-origin.
- Public JSON calls use `/api/v1/...`.
- protected Administrator JSON calls use `/api/v1/admin/...` with PHP-session authentication and CSRF.
- Version 1 is not an SPA and does not use JWT.

# 2. Version 1 Actors

| Actor | UI access |
|---|---|
| Guest | Public pages, availability, one-room booking, secure payment submission, contact, token-authorized review |
| Administrator | Login and protected administration pages |

Guest is not a stored role. Customer accounts/portal and specialized staff roles are Future Enhancements.

# 3. Shared UI Standards

- Mobile-first responsive layout.
- Accessible labels, keyboard operation, focus states, alt text, and sufficient contrast.
- Server-side validation is authoritative; client validation improves usability only.
- Form errors appear next to fields and in an accessible summary.
- Destructive/state-changing actions require clear confirmation where appropriate.
- Currency is displayed as NPR with two decimal places.
- Dates/times are displayed consistently in Asia/Kathmandu.
- Status badges use exact canonical lower-case values in data and human-readable labels in UI.

# 4. Public Page Register

| Page | Page route | Selective JSON endpoint(s) | Data tables |
|---|---|---|---|
| Home | `/` | none required; server-composed | approved public content tables |
| About/managed page | `/{slug}` when published | optional `/api/v1/pages/{slug}` | `pages`, `settings` |
| Rooms | `/rooms` | optional `/api/v1/availability` | room and booking tables |
| Room detail | `/rooms/{slug}` | optional `/api/v1/availability` | room and booking tables |
| Booking | `/booking` | `POST /api/v1/bookings` | `bookings`, `booking_rooms`, `booking_events` |
| Booking success | `/booking/success` | none required | no direct table access from view |
| Payment submission | `/booking/payment` | `/api/v1/payment-methods`, `POST /api/v1/payments` | `payment_methods`, `payments`, `bookings` |
| Activities | `/activities` | `/api/v1/activities` | `activities` |
| Gallery | `/gallery` | `/api/v1/gallery` | `gallery`, `gallery_categories` |
| Reviews | `/reviews` | `/api/v1/reviews` | `reviews` |
| Submit review | `/review/{token}` | `POST /api/v1/reviews` | `review_invitations`, `reviews`, `bookings` |
| Contact | `/contact` | `POST /api/v1/contact` | `contact_messages`, `settings` |
| CMS page | `/{slug}` for approved pages | `/api/v1/pages/{slug}` | `pages` |

# 5. Public Rooms and Availability

## 5.1 Rooms List

Displays only rooms that are operationally `active` and published. Filters may include dates and occupancy.

Each card displays name, image, capacity, amenities, NPR price, and a Book button. The UI may display **Available for selected dates** only after the Availability Service calculates it.

## 5.2 Room Detail

Displays image gallery, description, amenities, capacity, NPR price, policy summary, date fields, and Book button.

The UI must not present Available, Reserved, or Occupied as stored room operational statuses. Public users do not see internal maintenance/inactive records.

## 5.3 Availability Interaction

Inputs:

- check-in date;
- check-out date;
- adults;
- children.

Fetch endpoint: `GET /api/v1/availability`.

Results are calculated for the requested range. A result may show `is_available: true/false`; this is not persisted as room status.

# 6. Guest Booking Screen

## 6.1 Purpose

Allow a Guest to create one booking for exactly one room without registration.

## 6.2 Fields

| Field | Required | Rule |
|---|---:|---|
| Selected room | Yes | One `room_id`; not a multi-select |
| Full name | Yes | Guest contact snapshot |
| Email | Yes | Valid email |
| Phone | Yes | Valid configured format |
| Check-in | Yes | Valid date |
| Check-out | Yes | Later than check-in |
| Adults | Yes | Positive and within room capacity |
| Children | Yes | Non-negative and within capacity |
| Special request | No | Length-limited text |
| Terms/policy acceptance | Yes | Explicit checkbox; timestamp stored |

There is no login, registration, customer selector, `user_id`, booking-status selector, payment-status selector, or multiple-room input.

## 6.3 Submission

Endpoint: `POST /api/v1/bookings`.

On success:

- show booking reference;
- show `pending` status;
- show confirmation/payment deadline;
- show NPR total snapshot;
- show next manual-payment instructions;
- do not imply confirmation until payment is verified;
- do not create or display a payment row unless payment information has been submitted.

# 7. Guest Payment Screen

The Guest reaches this screen using the signed booking-access mechanism, not a customer account.

Fields:

- booking reference (display/read-only after secure validation);
- configured payment method;
- NPR amount;
- transaction/reference code;
- proof upload where required;
- notes.

Endpoint: `POST /api/v1/payments`.

Success shows payment status `submitted`. The UI never offers `verified`, `rejected`, or `refunded` controls to a Guest. A rejected attempt may be followed by a new submission; previous attempts remain historical.

# 8. Review Submission Screen

Route: `/review/{token}`
Endpoint: `POST /api/v1/reviews`

The screen is available only when the secure single-use invitation maps to a booking with `booking_status = checked_out` and no prior review.

Fields: rating 1–5, optional title, comment. New review status is always `pending`. The Guest cannot select moderation status.

# 9. Administrator Authentication

## 9.1 Login

Page: `/admin/login`
Endpoint: `POST /api/v1/admin/auth/login`

Fields: username/email, password, CSRF. Success creates a PHP session and redirects to `/admin/dashboard`. No access/refresh token is displayed or stored.

## 9.2 Password Reset

No staff or customer password-reset screen/API is registered through Sprint 4. Any later Administrator reset workflow requires a separate approved migration, route contract, and security review.

# 10. Administrator Screen Register

Only the JSON endpoints explicitly listed below are registered. Room, room-type, amenity, payment-method, and authentication forms use protected PHP routes rather than invented JSON equivalents.

| Screen | Page route | JSON endpoint(s) | Tables |
|---|---|---|---|
| Dashboard | `/admin/dashboard` | none | approved module projections |
| Rooms | `/admin/rooms` | none; protected PHP forms | room tables |
| Room types | `/admin/room-types` | none; protected PHP forms | `room_types` |
| Amenities | `/admin/amenities` | none; protected PHP forms | `amenities`, `room_amenities` |
| Bookings | `/admin/bookings` | `PATCH /api/v1/admin/bookings/{id}/status` | booking tables |
| Payments | `/admin/payments` | `PATCH /api/v1/admin/payments/{id}/status` | payment tables |
| Invoices | `/admin/invoices` | `POST /api/v1/admin/bookings/{bookingId}/invoice` | invoice tables |
| Activities | `/admin/activities` | `/api/v1/admin/activities...` | `activities` |
| Gallery | `/admin/gallery` | `/api/v1/admin/gallery...`, `/api/v1/admin/gallery-categories...` | gallery tables |
| Reviews | `/admin/reviews` | `/api/v1/admin/reviews...` | review tables |
| Contact messages | `/admin/contact-messages` | GET list/detail only | `contact_messages` |
| CMS pages | `/admin/pages` | `/api/v1/admin/pages...` | `pages` |
| Settings | `/admin/settings` | `GET/PUT /api/v1/admin/settings` | `settings` |
| Notifications | `/admin/notifications` | `/api/v1/admin/notifications...` | `notifications` |
| Payment methods | `/admin/payment-methods` | none; protected PHP forms | `payment_methods` |

No Administrator account, password-reset, audit-log browser, backup, or Customer Management screen/API is registered through Sprint 4.

# 11. Administrator Dashboard

The protected `/admin/dashboard` page is a PHP-rendered landing screen. It has no active dashboard JSON endpoint and must not be documented as proof of completed analytics/reporting widgets. Module pages provide the implemented operational lists and actions.

# 12. Administrator Room Management

## 12.1 List

Separate columns:

- Operational Status: Active, Maintenance, Inactive.
- Published: Yes/No.
- Current Occupancy: calculated from checked-in booking.
- Availability for selected dates: calculated when dates are supplied.

## 12.2 Create/Edit

Fields map exactly to `room_types`, `rooms`, `room_images`, and amenities. Room inputs are room type, room number, name, slug, description, fixed-precision NPR price, adult/child capacity, operational status and publication. Image inputs manage file, alt text, order and one primary image. Administrator may set operational status and publication separately. The form never offers Available, Reserved, Occupied, Cleaning or Retired as operational status. Bed type, floor, room size, featured and a separate thumbnail field are not Version 1 inputs.

# 13. Administrator Booking Management

## 13.1 List

Filters use canonical booking statuses only:

- Pending
- Confirmed
- Checked In
- Checked Out
- Cancelled
- Rejected

Payment information is displayed from payment attempts using canonical payment statuses. No denormalized/arbitrary payment-status edit is offered on the booking form.

## 13.2 Booking Creation Boundary

No `/admin/bookings/create` page or `POST /api/v1/admin/bookings` route is registered through Sprint 4. New Version 1 bookings use the public one-room Guest flow. Administrator booking screens are list/detail plus allowed status actions.

## 13.3 Booking Detail

Displays guest snapshot, one room, price/policy snapshots, canonical booking state, operational timestamps, deadline, event history, payment attempts, and invoice where issued.

Allowed action buttons are derived from the current state:

- Pending: reject or cancel; confirmation occurs through verified payment/Booking Service.
- Confirmed: check in or cancel.
- Checked In: check out.
- Checked Out/Cancelled/Rejected: read-only state.

Endpoint: `PATCH /api/v1/admin/bookings/{id}/status`. The UI submits transition intent; it never writes status directly.

# 14. Administrator Payment Management

List/detail displays every attempt separately with `pending`, `submitted`, `verified`, `rejected`, or `refunded` status.

Only a `submitted` attempt shows Verify/Reject controls. Endpoint: `PATCH /api/v1/admin/payments/{id}/status`.

Full versus partial manual-refund UI is not defined until **Architect/Client Confirmation Required** is resolved. No final refund button/amount behavior may be implemented from this document.

# 15. Administrator Invoice Management

Invoice issue action uses `POST /api/v1/admin/bookings/{bookingId}/invoice`. Totals are derived snapshots and are not editable arbitrary fields. One stable invoice number is retained on repeated issue and print. The implemented Version 1 invoice UI displays `NPR 0.00` tax, no service charge, and no edit/delete control.

# 16. Administrator Content Screens

## 16.1 Activities and Gallery

Activity fields are title, slug, required card summary, full description, optional validated featured-image upload, nullable fixed-precision NPR price and rate unit, optional duration/location, Active, Published, global sort order, homepage selection and homepage order. Edit retains the current image when no replacement is selected. Homepage help states that only active/published selections qualify and at most four are shown. Activity category/meeting-point/maximum-participant controls remain Future Enhancements.

Gallery-category fields are name, slug and Active. Gallery-item fields are optional category, title, caption, image upload/path managed by the server, alt text, sort order, Active, Published, homepage selection and homepage order. There is no media-library selector, Uploaded By, Deleted At or generated-image field. Public preview reflects only active and published records; the homepage uses only selected eligible items and displays at most six.

## 16.2 Reviews

List uses Pending/Approved/Rejected. Detail shows linked checked-out booking and invitation validation. Administrator may approve or reject a pending review only.

## 16.3 Contact Messages

List/detail are current Version 1 screens. Final assignment, retention, SLA, and allowed contact-status vocabulary remain **Architect/Client Confirmation Required**.

## 16.4 CMS Pages and Settings

Page editor uses Draft/Published/Archived. Settings screen manages site-wide values. Full version history/rollback UI is future.

# 17. Notifications, Users, Audit, and Backups

- Notifications are Administrator-only in-app records with read actions.
- Administrator account-management and password-reset screens are not registered through Sprint 4; initial account creation uses the CLI bootstrap command.
- Audit records are appended internally; no audit-log browser screen is registered through Sprint 4.
- Backup/restore remains a cPanel/deployment procedure; no backup screen is registered.

# 18. Future Enhancements — Not Version 1 Screens

The following are retained only as future scope and have no active routes, API dependencies, or Version 1 database requirements:

- Customer registration and login;
- Customer dashboard/portal;
- My Bookings, customer payments, notifications, reviews, profile, and password screens;
- Customer Management administration;
- Manager, Receptionist, Accountant, and Content Manager permission-specific screens;
- multi-room booking UI;
- gateway checkout/callback/refund UI;
- SMS/WhatsApp/push controls;
- advanced housekeeping workflow;
- full CMS version history/rollback;
- mobile/SPA UI.

# 19. Required UI Tests

- Guest booking works without account/customer selection.
- No unregistered Administrator booking-create page/API is presented.
- No booking creation automatically requires a payment record.
- All current Administrator Fetch calls use `/api/v1/admin/...`.
- Public Fetch calls use `/api/v1/...`.
- Canonical statuses only.
- Room operational/publication/calculated-availability concepts stay separate.
- Checked-out review eligibility.
- PHP-session expiry redirects Administrator to login without JWT behavior.
- responsive, keyboard, validation, and error-state behavior.
