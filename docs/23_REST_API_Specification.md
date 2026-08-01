# Tara Guest House Management System

# 23. REST API Specification

## Document Control

| Field | Value |
|---|---|
| Version | 1.3 |
| Status | Implemented route inventory through Sprint 4 — Review Required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| API type | Same-origin JSON interface for PHP-rendered application |
| Base prefix | `/api/v1` |

# 1. Contract Rules

1. Public and secure Guest JSON endpoints use `/api/v1/...`.
2. Every protected Administrator JSON endpoint explicitly uses `/api/v1/admin/...`.
3. Administrator endpoints use the hardened PHP session, authorization middleware, and CSRF protection for state-changing requests.
4. Version 1 does not issue or accept JWT, bearer access tokens, or refresh tokens.
5. Normal page routes such as `/rooms` and `/admin/bookings` are server-rendered routes and are not defined in this JSON contract.
6. Public third-party API support is not included.
7. Request classes validate input; Services authorize and execute business rules; Repositories persist approved operations.

# 2. Response Format

## 2.1 Success

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

## 2.2 Validation Error

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "field": ["Validation message."]
  }
}
```

## 2.3 Status Codes

| Code | Use |
|---:|---|
| 200 | Successful read/update |
| 201 | Resource created |
| 204 | Successful response without a body |
| 400 | Malformed request |
| 401 | Administrator session absent/expired |
| 403 | Authorization or CSRF failure |
| 404 | Resource not found or not publicly visible |
| 409 | State/availability/concurrency conflict |
| 422 | Validation failure |
| 429 | Rate limit exceeded |
| 500 | Private server error; public message remains generic |

# 3. Authentication and Security

## 3.1 Active Administrator Authentication Endpoints

| Method | Exact endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/admin/auth/login` | Validate Administrator credentials and create the PHP session |
| POST | `/api/v1/admin/auth/logout` | Destroy the current PHP session |

Login returns session-authentication confirmation and safe Administrator profile data. No JWT, bearer token, refresh token, profile-update, password-change, forgot-password, or reset-password JSON route is registered through Sprint 4.

## 3.2 CSRF

Every state-changing Administrator request includes the application CSRF token according to the PHP-rendered form/Fetch integration. Guest booking/contact/payment/review endpoints use their documented public security controls, server-side validation, rate limiting, and signed/single-use tokens where required.

# 4. Public Read Endpoints

| Method | Exact endpoint | Purpose | Tables |
|---|---|---|---|
| GET | `/api/v1/availability` | Calculated date-range room availability | `rooms`, `bookings`, `booking_rooms` |
| GET | `/api/v1/payment-methods` | Active configured manual payment methods | `payment_methods` |
| GET | `/api/v1/activities` | Active and published activities | `activities` |
| GET | `/api/v1/gallery` | Active and published gallery items | `gallery`, `gallery_categories` |
| GET | `/api/v1/reviews` | Approved verified-stay reviews | `reviews` |
| GET | `/api/v1/pages/{slug}` | Published CMS page | `pages` |
| GET | `/api/v1/settings/public` | Allowlisted public settings | `settings` |

Room list/detail are PHP-rendered routes at `/rooms` and `/rooms/{slug}`; no `/api/v1/rooms` route is registered. Public repositories enforce active/published/approved/public eligibility before controllers serialize responses.

## 4.1 Availability Request

`GET /api/v1/availability?check_in=2026-08-10&check_out=2026-08-12&adults=2&children=0`

Rules:

- `check_out` is later than `check_in`;
- operational status must be `active`;
- public results must be published;
- occupancy must fit;
- no unexpired `pending`, `confirmed`, or `checked_in` booking may overlap;
- `checked_out`, `cancelled`, and `rejected` do not block.

```json
{
  "success": true,
  "data": [
    {
      "room_id": 12,
      "name": "Deluxe Room",
      "slug": "deluxe-room-12",
      "operational_status": "active",
      "is_published": true,
      "is_available": true,
      "currency": "NPR",
      "price_per_night": "4500.00"
    }
  ]
}
```

# 5. Guest Booking Endpoint

| Method | Exact endpoint | Purpose | Authentication |
|---|---|---|---|
| POST | `/api/v1/bookings` | Create one-room Guest booking | Public, rate-limited, CSRF/public-form controls |

## 5.1 Request

```json
{
  "room_id": 12,
  "guest_full_name": "Asha Sharma",
  "guest_email": "asha@example.com",
  "guest_phone": "+9779800000000",
  "check_in": "2026-08-10",
  "check_out": "2026-08-12",
  "adults": 2,
  "children": 0,
  "special_request": "Late arrival",
  "terms_accepted": true,
  "policy_version": "booking-policy-v1"
}
```

Exactly one `room_id` is accepted. Arrays such as `room_ids` or multiple room objects are rejected in Version 1. No `user_id`, customer account, booking status, payment status, or payment record is accepted from the Guest.

## 5.2 Service Behavior

- revalidate availability inside a transaction;
- generate a secure, non-sequential public booking reference with sufficient randomness;
- store guest, policy, room-price, total, and NPR snapshots;
- set booking status to `pending`;
- set the configurable `confirmation_deadline_at`;
- create exactly one `booking_rooms` row;
- append booking-created history;
- do not automatically create a payment record.

## 5.3 Response

```json
{
  "success": true,
  "message": "Booking received.",
  "data": {
    "booking_reference": "TGH-2026-8F4K2M",
    "booking_status": "pending",
    "confirmation_deadline_at": "2026-08-01T18:00:00+05:45",
    "currency": "NPR",
    "total_amount": "9000.00",
    "booking_access_token": "SIGNED_VALUE"
  }
}
```

`booking_access_token` is a signed, scoped application token used only for permitted Guest follow-up actions. It is an implementation artifact, not an authenticated customer account, JWT access token, or database table. Its signing/expiry parameters require the approved security configuration.

# 6. Guest Payment Submission

| Method | Exact endpoint | Purpose | Authentication |
|---|---|---|---|
| POST | `/api/v1/payments` | Create and submit one payment attempt | Signed booking access plus rate/upload controls |

## 6.1 Request

```json
{
  "booking_reference": "TGH-2026-8F4K2M",
  "booking_access_token": "SIGNED_VALUE",
  "payment_method_id": 2,
  "amount": "9000.00",
  "transaction_reference": "WALLET-12345",
  "guest_notes": "Transferred today",
  "proof_upload": "multipart file where required"
}
```

The Payment Service creates a new attempt and records it as `submitted` after required details/proof pass validation. A booking may have zero payment attempts before this call and multiple retained attempts over time. The request cannot set `verified`, `rejected`, or `refunded`.

```json
{
  "success": true,
  "message": "Payment submitted for review.",
  "data": {
    "payment_id": 44,
    "payment_status": "submitted",
    "booking_reference": "TGH-2026-8F4K2M"
  }
}
```

# 7. Guest Review and Contact Endpoints

| Method | Exact endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/reviews` | Submit one checked-out-stay review using invitation token |
| POST | `/api/v1/contact` | Submit contact message |

Review request fields are `review_token`, `rating`, optional `title`, and `comment`. Review Service validates the token hash, unused invitation, `booking_status = checked_out`, and one-review-per-booking constraint. New status is always `pending`.

Contact request fields are `name`, `email`, optional `phone`, `subject`, and `message`. Retention, assignment, and SLA are **Architect/Client Confirmation Required**.

# 8. Administrator Dashboard

The implemented Dashboard is the protected PHP-rendered route `GET /admin/dashboard`. No `/api/v1/admin/dashboard` JSON route is registered through Sprint 4. Viewing the dashboard does not mutate business data.

# 9. Administrator Room Management Route Boundary

Room, room-type, amenity, and room-image management is implemented through protected PHP page/form routes under `/admin/rooms`, `/admin/room-types`, and `/admin/amenities`. These forms use Administrator session authentication and CSRF protection.

No `/api/v1/admin/rooms`, `/api/v1/admin/room-types`, `/api/v1/admin/amenities`, or room-image JSON route is registered through Sprint 4. No route writes Available, Reserved, or Occupied as a stored room operational status.

# 10. Administrator Booking API

Administrator booking list and detail reads are protected PHP-rendered routes at `/admin/bookings` and `/admin/bookings/{id}`. The implemented same-origin JSON contract contains only the state mutation below:

| Method | Exact endpoint | Purpose |
|---|---|---|
| PATCH | `/api/v1/admin/bookings/{id}/status` | Request an allowed Booking Service transition |

Sprint 3 does not add Administrator booking-create, booking-edit, or booking-read JSON endpoints.

## 10.1 Status Request

```json
{
  "status": "checked_in",
  "reason": null
}
```

Accepted target values depend on the current state and are limited to the transitions in document 22. Cancellation/rejection require a reason. Payment verification does not call this endpoint from frontend code; Payment Service notifies Booking Service internally to allow `pending → confirmed`.

The frontend must not submit `confirmed` as an arbitrary Administrator choice. In Version 1, that transition occurs only through the Payment Service verification use case coordinated with Booking Service.

# 11. Administrator Payment API

Administrator payment list/detail/proof reads are protected PHP routes at `/admin/payments`, `/admin/payments/{id}`, and `/admin/payments/{id}/proof`. The implemented same-origin JSON contract contains only:

| Method | Exact endpoint | Purpose |
|---|---|---|
| PATCH | `/api/v1/admin/payments/{id}/status` | Verify or reject a submitted attempt |

```json
{
  "status": "verified",
  "verification_note": "Reference and amount confirmed."
}
```

Only `submitted → verified` or `submitted → rejected` is accepted by this endpoint. Only Administrator may perform it. A rejected attempt remains immutable and resubmission creates a new attempt.

The exact full-versus-partial manual-refund policy is unresolved. No Version 1 refund mutation endpoint is implementable until **Architect/Client Confirmation Required** is approved. `refunded` remains a canonical data status for the later approved manual recording rule.

# 12. Administrator Invoice API

Invoice list, detail, and print reads are protected PHP routes at `/admin/invoices`, `/admin/invoices/{id}`, and `/admin/invoices/{id}/print`. The implemented same-origin JSON contract contains only:

| Method | Exact endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/admin/bookings/{bookingId}/invoice` | Issue or return the stable invoice derived from snapshots |

The request accepts only an empty JSON object and never accepts arbitrary totals, currency, tax, service charge, or invoice number. Invoice Service derives immutable NPR totals from the confirmed booking and its one verified payment. Sprint 3 stores `tax_amount = 0.00` and no service charge. Printing uses the authenticated PHP-rendered route with no-store and anti-indexing headers.

# 13. Administrator Activity APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/activities` | List all activities |
| POST | `/api/v1/admin/activities` | Create activity |
| GET | `/api/v1/admin/activities/{id}` | Activity detail |
| PUT | `/api/v1/admin/activities/{id}` | Update activity |
| PATCH | `/api/v1/admin/activities/{id}/active` | Set active control |
| PATCH | `/api/v1/admin/activities/{id}/publication` | Set publication control |
| DELETE | `/api/v1/admin/activities/{id}` | Deactivate/unpublish; no hard delete |

Activity create/update fields are limited to `title`, `slug`, `description`, fixed-precision NPR `price`, `is_active`, `is_published` and `sort_order`. Categories, images, featured, duration, meeting point and maximum participants are Future Enhancements.

# 14. Administrator Gallery APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/gallery-categories` | List categories |
| POST | `/api/v1/admin/gallery-categories` | Create category |
| PUT | `/api/v1/admin/gallery-categories/{id}` | Update category |
| GET | `/api/v1/admin/gallery` | List all items |
| POST | `/api/v1/admin/gallery` | Upload/create item |
| PUT | `/api/v1/admin/gallery/{id}` | Update metadata |
| PATCH | `/api/v1/admin/gallery/{id}/active` | Set active control |
| PATCH | `/api/v1/admin/gallery/{id}/publication` | Set publication control |
| PATCH | `/api/v1/admin/gallery/reorder` | Update ordering |
| DELETE | `/api/v1/admin/gallery/{id}` | Deactivate/unpublish; no hard delete |

Gallery creation accepts nullable `gallery_category_id`, `title`, `caption`, `alt_text`, `sort_order`, `is_active`, `is_published`, and exactly one multipart `image` upload. The server validates and stores the file, then owns `file_path`; clients cannot submit an authoritative path. Metadata updates do not replace the image. There is no `media_files`, featured, uploaded-by, deleted-at or generated-image field.

# 15. Administrator Review APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/reviews` | List pending/approved/rejected reviews |
| GET | `/api/v1/admin/reviews/{id}` | Review and booking eligibility detail |
| PATCH | `/api/v1/admin/reviews/{id}/status` | Change pending review to approved/rejected |

The endpoint cannot create eligibility, change the linked booking, or accept a review without `booking_status = checked_out`.

# 16. Administrator Contact APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/contact-messages` | List retained messages |
| GET | `/api/v1/admin/contact-messages/{id}` | Message detail |

The Sprint 4 workflow is read-only for Administrators. Final status vocabulary, assignment, retention, and SLA remain **Architect/Client Confirmation Required**, so `/api/v1/admin/contact-messages/{id}/status` is intentionally absent.

# 17. Administrator CMS and Settings APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/pages` | List structured pages |
| POST | `/api/v1/admin/pages` | Create page as `draft` |
| GET | `/api/v1/admin/pages/{id}` | Page detail |
| PUT | `/api/v1/admin/pages/{id}` | Update page content |
| PATCH | `/api/v1/admin/pages/{id}/status` | Set `draft`, `published`, or `archived` through allowed CMS flow |
| GET | `/api/v1/admin/settings` | List site-wide settings |
| PUT | `/api/v1/admin/settings` | Update validated settings |

Full CMS version history/rollback endpoints are future enhancements.

# 18. Administrator Notification APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/notifications` | List Administrator notifications |
| PATCH | `/api/v1/admin/notifications/{id}/read` | Set `read_at` |
| PATCH | `/api/v1/admin/notifications/read-all` | Mark current Administrator notifications read |

There is no customer notification inbox in Version 1. SMS, WhatsApp, push, and WebSocket endpoints are future.

# 19. Administrator User APIs

No Administrator-account management JSON route is registered through Sprint 4. Initial Administrator creation remains a protected CLI bootstrap workflow. Guest/customer and specialized staff account APIs are Future Enhancements.

# 20. Administrator Backup and Audit APIs

No `/api/v1/admin/audit-logs` or `/api/v1/admin/backups` route is registered through Sprint 4. Audit evidence is appended internally by approved workflows. Backup/restore remains an operational cPanel/deployment procedure until a separately approved authenticated interface exists.

# 21. Upload Contract

Room, gallery, and payment-proof uploads require:

- server-side MIME and extension allowlists;
- configured file-size limit;
- random server-generated filename;
- storage outside executable PHP behavior;
- image decoding/validation where applicable;
- no trust in client filename or MIME;
- audit/error logging.

Exact size/MIME values require the approved upload configuration/ticket and must not be guessed from conflicting legacy examples.

# 22. Pagination, Filtering, and Sorting

List endpoints accept only documented/allowlisted filters. Canonical status filters use the exact lower-case status values. Sort columns and directions are allowlisted; raw client SQL fragments are prohibited.

# 23. Future Enhancements — No Active Version 1 Endpoints

- customer registration/login/profile/portal APIs;
- Administrator account-management, password-reset, audit-log, backup, and dashboard JSON APIs;
- Administrator room/room-type/amenity JSON APIs;
- contact-message status mutation;
- Role Management and permission-assignment APIs;
- Receptionist, Manager, Accountant, and Content Manager APIs;
- JWT/access-token/refresh-token APIs;
- public third-party API support;
- multi-room booking payloads;
- gateway callbacks and automatic refunds;
- SMS, WhatsApp, push, WebSocket APIs;
- full CMS version history/rollback;
- advanced housekeeping workflow.

No route in this section may be registered in Version 1 without a later approved decision.

# 24. Required Contract Tests

- every protected endpoint rejects absent/expired sessions;
- state-changing Administrator endpoints reject invalid CSRF;
- no protected endpoint is registered outside `/api/v1/admin/...`;
- Guest booking accepts exactly one room and no customer/status fields;
- canonical booking/payment values only;
- availability overlap and expired-pending behavior;
- multiple payment attempts and Administrator-only verification;
- room operational status never accepts Available/Reserved/Occupied;
- review submission requires checked-out booking and single-use token;
- public publication filters;
- response envelope and validation errors;
- upload security;
- future and documentation-only endpoints are absent.
- the static inventory matches the 48 registered routes in `routes/api.php`.
