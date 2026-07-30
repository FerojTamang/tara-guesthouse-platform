# Tara Guest House Management System

# 23. REST API Specification

## Document Control

| Field | Value |
|---|---|
| Version | 1.2 |
| Status | Synchronized — Review Required |
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
| POST | `/api/v1/admin/auth/login` | Validate Administrator credentials and create PHP session |
| POST | `/api/v1/admin/auth/logout` | Destroy current PHP session |
| GET | `/api/v1/admin/auth/profile` | Return current Administrator profile |
| PUT | `/api/v1/admin/auth/password` | Change current Administrator password |
| POST | `/api/v1/admin/auth/forgot-password` | Request staff password-reset email |
| POST | `/api/v1/admin/auth/reset-password` | Consume staff reset token |

Login response contains Administrator profile data and session-authentication confirmation only. It contains no access token, refresh token, token expiry, or bearer-token type.

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "authentication": "php_session",
    "user": {
      "id": 1,
      "full_name": "Administrator",
      "email": "admin@example.com",
      "role": "Administrator"
    }
  }
}
```

## 3.2 CSRF

Every state-changing Administrator request includes the application CSRF token according to the PHP-rendered form/Fetch integration. Guest booking/contact/payment/review endpoints use their documented public security controls, server-side validation, rate limiting, and signed/single-use tokens where required.

# 4. Public Read Endpoints

| Method | Exact endpoint | Purpose | Tables |
|---|---|---|---|
| GET | `/api/v1/rooms` | Published active room list | `rooms`, `room_types`, `room_images`, `amenities`, `room_amenities` |
| GET | `/api/v1/rooms/{slug}` | Published active room detail | same as above |
| GET | `/api/v1/availability` | Calculated date-range availability | `rooms`, `bookings`, `booking_rooms` |
| GET | `/api/v1/activities` | Active and published activities | `activities` |
| GET | `/api/v1/gallery` | Active and published gallery items | `gallery`, `gallery_categories` |
| GET | `/api/v1/reviews` | Approved reviews | `reviews` |
| GET | `/api/v1/pages/{slug}` | Published CMS page | `pages` |
| GET | `/api/v1/settings/public` | Approved public settings | `settings` |

Public room responses expose calculated `is_available` only when dates are supplied. They never expose `available`, `reserved`, or `occupied` as stored room operational statuses.

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
| GET | `/api/v1/payment-methods` | Active configured manual methods | Public |
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

| Method | Exact endpoint | Purpose | Tables |
|---|---|---|---|
| GET | `/api/v1/admin/dashboard` | Canonical operational metrics | Approved module tables |

Metrics use canonical booking/payment statuses and calculated room availability. Dashboard endpoints do not mutate business data.

# 9. Administrator Room APIs

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/room-types` | List room types |
| POST | `/api/v1/admin/room-types` | Create room type |
| GET | `/api/v1/admin/room-types/{id}` | Room-type detail |
| PUT | `/api/v1/admin/room-types/{id}` | Update room type |
| DELETE | `/api/v1/admin/room-types/{id}` | Soft-delete/deactivate where history requires |
| GET | `/api/v1/admin/rooms` | List all rooms and calculated current conditions |
| POST | `/api/v1/admin/rooms` | Create room |
| GET | `/api/v1/admin/rooms/{id}` | Room detail |
| PUT | `/api/v1/admin/rooms/{id}` | Update room data |
| PATCH | `/api/v1/admin/rooms/{id}/operational-status` | Set `active`, `maintenance`, or `inactive` |
| PATCH | `/api/v1/admin/rooms/{id}/publication` | Set publication boolean |
| DELETE | `/api/v1/admin/rooms/{id}` | Soft-delete/deactivate subject to history |
| POST | `/api/v1/admin/rooms/{id}/images` | Upload validated room image |
| PATCH | `/api/v1/admin/rooms/{id}/images/{imageId}` | Update primary/order/alt data |
| DELETE | `/api/v1/admin/rooms/{id}/images/{imageId}` | Remove image when allowed |
| GET | `/api/v1/admin/amenities` | List amenities |
| POST | `/api/v1/admin/amenities` | Create amenity |
| PUT | `/api/v1/admin/amenities/{id}` | Update amenity |
| PUT | `/api/v1/admin/rooms/{id}/amenities` | Replace room amenity assignments |

No endpoint writes Available, Reserved, or Occupied to a room. Administrator list responses may include calculated `is_available_for_dates`, `has_current_checked_in_booking`, and blocking-booking summaries derived from bookings.

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

Gallery create/update fields map only to nullable `gallery_category_id`, `title`, `caption`, `file_path` supplied by the secure upload workflow, `alt_text`, `sort_order`, `is_active` and `is_published`. There is no `media_files`, featured, uploaded-by, deleted-at or generated-image field.

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
| GET | `/api/v1/admin/contact-messages` | List/search messages |
| GET | `/api/v1/admin/contact-messages/{id}` | Message detail |
| PATCH | `/api/v1/admin/contact-messages/{id}/status` | Update approved contact workflow status |

Final status vocabulary, assignment, retention, and SLA must be confirmed before this endpoint's allowed status values are implemented. **Architect/Client Confirmation Required.**

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

Version 1 user APIs manage Administrator staff accounts only. They do not create customers or specialized staff roles.

| Method | Exact endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/admin/users` | List Administrator accounts |
| POST | `/api/v1/admin/users` | Create Administrator account |
| GET | `/api/v1/admin/users/{id}` | Administrator account detail |
| PUT | `/api/v1/admin/users/{id}` | Update Administrator account |
| PATCH | `/api/v1/admin/users/{id}/status` | Set active/inactive/locked according to security rules |
| POST | `/api/v1/admin/users/{id}/password-reset` | Initiate staff reset process |

Guest is never returned as a stored role. Customer, Receptionist, Manager, Accountant, and Content Manager account/permission APIs are Future Enhancements and have no active Version 1 endpoints.

# 20. Administrator Backup and Audit APIs

| Method | Exact endpoint | Purpose | Data source |
|---|---|---|---|
| GET | `/api/v1/admin/audit-logs` | Search append-only audit records | `audit_logs` |
| GET | `/api/v1/admin/backups` | List approved cPanel/application backup artifacts | Filesystem/hosting implementation artifact |
| POST | `/api/v1/admin/backups` | Request supported backup operation | Hosting implementation artifact |

Restore is not exposed as a general JSON endpoint until a separately reviewed operational procedure defines authorization, confirmation, validation, and rollback. Backup capabilities depend on the selected cPanel host.

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
- future endpoints are absent.
