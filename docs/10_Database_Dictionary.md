# Tara Guest House Management System

# 10. Database Dictionary

## Document Control

| Field | Value |
|---|---|
| Version | 1.2 |
| Status | Synchronized — Review Required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Executable source of truth | Database migrations |

# 1. Dictionary Rules

- Migrations are authoritative and this dictionary and ERD must match them.
- All workflow statuses are short strings controlled by PHP domain constants and Service validation.
- Financial fields use fixed-precision decimal storage in NPR.
- `created_at` and `updated_at` are required unless a table is immutable by design.
- Guest is not a stored/authenticated role.
- Customer accounts and specialized staff roles are future enhancements.

# 2. Canonical Status Values

| Domain | Values |
|---|---|
| Booking | `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled`, `rejected` |
| Payment | `pending`, `submitted`, `verified`, `rejected`, `refunded` |
| Room operational | `active`, `maintenance`, `inactive` |
| Review | `pending`, `approved`, `rejected` |
| CMS page | `draft`, `published`, `archived` |

Available, reserved, and occupied are calculated room conditions. They are not stored room operational statuses. `completed` and workflow `archived` are not booking statuses.

# 3. Table Index

| Module | Tables |
|---|---|
| Authentication | `roles`, `users`, `password_resets` |
| Rooms | `room_types`, `rooms`, `room_images`, `amenities`, `room_amenities` |
| Bookings | `bookings`, `booking_rooms`, `booking_events` |
| Payments/invoices | `payment_methods`, `payments`, `invoices`, `invoice_items` |
| Activities/gallery | `activities`, `gallery_categories`, `gallery` |
| Reviews | `reviews`, `review_invitations` |
| Contact/CMS | `contact_messages`, `settings`, `pages` |
| Operations | `notifications`, `audit_logs` |

# 4. Authentication Tables

## 4.1 `roles`

Version 1 requires only Administrator. Future role rows must not be seeded or used until approved.

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `role_name` | VARCHAR(50) | No | Unique role name; `Administrator` in V1 |
| `description` | VARCHAR(255) | Yes | Human-readable description |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 4.2 `users`

Stores authenticated Administrator staff accounts. It does not store public Guests in Version 1.

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `role_id` | BIGINT UNSIGNED | No | FK to `roles` |
| `full_name` | VARCHAR(100) | No | Staff name |
| `username` | VARCHAR(50) | No | Unique login name |
| `email` | VARCHAR(150) | No | Unique staff email |
| `phone` | VARCHAR(20) | Yes | Contact number |
| `password` | VARCHAR(255) | No | Password hash |
| `profile_image` | VARCHAR(255) | Yes | Optional safe relative path |
| `last_login_at` | DATETIME | Yes | Last successful login |
| `account_status` | VARCHAR(20) | No | `active`, `inactive`, or `locked` |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |
| `deleted_at` | DATETIME | Yes | Soft-delete timestamp |

## 4.3 `password_resets`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `user_id` | BIGINT UNSIGNED | No | FK to Administrator `users` record |
| `token_hash` | VARCHAR(255) | No | Unique reset-token hash; raw token is never stored |
| `expires_at` | DATETIME | No | Expiry timestamp |
| `used_at` | DATETIME | Yes | Single-use marker |
| `created_at` | DATETIME | No | Created timestamp |

# 5. Room Tables

## 5.1 `room_types`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `name` | VARCHAR(100) | No | Type name |
| `slug` | VARCHAR(120) | No | Unique URL slug |
| `description` | TEXT | Yes | Public description |
| `base_price` | DECIMAL(12,2) | No | NPR reference price |
| `capacity_adults` | INT | No | Adult capacity |
| `capacity_children` | INT | No | Child capacity |
| `is_active` | BOOLEAN | No | Business control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 5.2 `rooms`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `room_type_id` | BIGINT UNSIGNED | No | FK to `room_types` |
| `room_number` | VARCHAR(30) | No | Unique physical-room identifier |
| `name` | VARCHAR(100) | No | Display name |
| `slug` | VARCHAR(120) | No | Unique public slug |
| `description` | TEXT | Yes | Room description |
| `price_per_night` | DECIMAL(12,2) | No | Current NPR price |
| `capacity_adults` | INT | No | Adult capacity |
| `capacity_children` | INT | No | Child capacity |
| `operational_status` | VARCHAR(20) | No | `active`, `maintenance`, `inactive` |
| `is_published` | BOOLEAN | No | Separate public visibility control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |
| `deleted_at` | DATETIME | Yes | Soft-delete timestamp |

No `available`, `reserved`, or `occupied` value is stored in `operational_status`.

Bed type, floor, room size and featured flag are not Version 1 room fields. Public thumbnail/primary selection uses `room_images.is_primary`; generated image variants are filesystem/cache artifacts rather than authoritative columns.

## 5.3 `room_images`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `room_id` | BIGINT UNSIGNED | No | FK to `rooms` |
| `file_path` | VARCHAR(255) | No | Safe relative path |
| `alt_text` | VARCHAR(255) | Yes | Accessible description |
| `sort_order` | INT | No | Display order |
| `is_primary` | BOOLEAN | No | Primary-image flag |
| `created_at` | DATETIME | No | Created timestamp |

## 5.4 `amenities`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `name` | VARCHAR(100) | No | Unique amenity name |
| `icon` | VARCHAR(100) | Yes | Approved icon identifier |
| `is_active` | BOOLEAN | No | Business control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 5.5 `room_amenities`

Composite uniqueness on (`room_id`, `amenity_id`).

| Column | Type | Null | Description |
|---|---|---|---|
| `room_id` | BIGINT UNSIGNED | No | FK to `rooms` |
| `amenity_id` | BIGINT UNSIGNED | No | FK to `amenities` |

# 6. Booking Tables

## 6.1 `bookings`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_reference` | VARCHAR(30) | No | Unique non-sequential public reference |
| `user_id` | BIGINT UNSIGNED | Yes | Reserved nullable future customer relationship; unused by V1 booking creation |
| `guest_full_name` | VARCHAR(100) | No | Guest snapshot |
| `guest_email` | VARCHAR(150) | No | Guest snapshot |
| `guest_phone` | VARCHAR(20) | No | Guest snapshot |
| `check_in` | DATE | No | Arrival date |
| `check_out` | DATE | No | Departure date; later than check-in |
| `adults` | INT | No | Adult occupancy |
| `children` | INT | No | Child occupancy |
| `currency` | CHAR(3) | No | `NPR` in Version 1 |
| `room_amount` | DECIMAL(12,2) | No | Agreed room-price snapshot |
| `total_amount` | DECIMAL(12,2) | No | Agreed booking total snapshot |
| `booking_status` | VARCHAR(20) | No | Canonical booking status |
| `special_request` | TEXT | Yes | Guest request |
| `terms_accepted_at` | DATETIME | No | Terms/policy acceptance timestamp |
| `policy_version` | VARCHAR(50) | Yes | Accepted policy identifier when available |
| `confirmation_deadline_at` | DATETIME | No | Configurable pending deadline |
| `confirmed_at` | DATETIME | Yes | Confirmation timestamp |
| `checked_in_at` | DATETIME | Yes | Operational check-in timestamp |
| `checked_out_at` | DATETIME | Yes | Operational checkout timestamp |
| `cancelled_at` | DATETIME | Yes | Cancellation timestamp |
| `cancellation_reason` | TEXT | Yes | Required when cancelled |
| `rejected_at` | DATETIME | Yes | Rejection timestamp |
| `rejection_reason` | TEXT | Yes | Required when rejected |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |
| `deleted_at` | DATETIME | Yes | Retention-only soft delete; not a workflow status |

Indexes cover reference, date range, status, deadline, email, and relevant FKs.

## 6.2 `booking_rooms`

Version 1 requires exactly one row per booking. `UNIQUE(booking_id)` or an equivalent migration constraint is used for Version 1 where compatible with the approved future migration plan; Service validation and automated tests are mandatory in all cases.

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | FK to `bookings` |
| `room_id` | BIGINT UNSIGNED | No | FK to `rooms` |
| `price_per_night` | DECIMAL(12,2) | No | Historical NPR snapshot |
| `nights` | INT | No | Positive night count |
| `subtotal` | DECIMAL(12,2) | No | Historical NPR snapshot |
| `created_at` | DATETIME | No | Created timestamp |

## 6.3 `booking_events`

Append-only booking status/event history.

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | FK to `bookings` |
| `event_type` | VARCHAR(50) | No | Transition or operational event identifier |
| `from_status` | VARCHAR(20) | Yes | Previous booking status |
| `to_status` | VARCHAR(20) | Yes | New booking status |
| `performed_by` | BIGINT UNSIGNED | Yes | FK to Administrator `users`; null for system/guest event |
| `reason` | TEXT | Yes | Required where policy requires a reason |
| `metadata_json` | JSON/TEXT | Yes | Non-authoritative supporting data |
| `created_at` | DATETIME | No | Event timestamp |

# 7. Payment and Invoice Tables

## 7.1 `payment_methods`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `name` | VARCHAR(100) | No | Configured manual method name |
| `instructions` | TEXT | Yes | Guest instructions |
| `account_reference` | VARCHAR(255) | Yes | Configured account/display reference |
| `qr_image_path` | VARCHAR(255) | Yes | Optional safe image path |
| `requires_proof` | BOOLEAN | No | Whether proof upload is required |
| `is_active` | BOOLEAN | No | Availability control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 7.2 `payments`

A booking has zero or more payment rows.

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | FK to `bookings` |
| `payment_method_id` | BIGINT UNSIGNED | No | FK to `payment_methods` |
| `currency` | CHAR(3) | No | `NPR` in V1 |
| `amount` | DECIMAL(12,2) | No | Submitted amount snapshot |
| `transaction_reference` | VARCHAR(100) | Yes | Guest-supplied transfer reference |
| `proof_path` | VARCHAR(255) | Yes | Safe relative proof path |
| `guest_notes` | TEXT | Yes | Guest notes |
| `payment_status` | VARCHAR(20) | No | Canonical payment status |
| `submitted_at` | DATETIME | Yes | Submission timestamp |
| `verified_at` | DATETIME | Yes | Verification timestamp |
| `verified_by` | BIGINT UNSIGNED | Yes | FK to Administrator `users` |
| `rejected_at` | DATETIME | Yes | Rejection timestamp |
| `rejected_by` | BIGINT UNSIGNED | Yes | FK to Administrator `users` |
| `verification_note` | TEXT | Yes | Verification/rejection note |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

`refunded` remains a canonical status, but no refund-specific fields are finalized. Refund amount, timestamps, actor, reason, approval and accounting fields require **Architect/Client Confirmation Required** and a later synchronized migration/API/UI update.

## 7.3 `invoices`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | FK to `bookings` |
| `invoice_number` | VARCHAR(50) | No | Unique stable number |
| `currency` | CHAR(3) | No | `NPR` |
| `subtotal` | DECIMAL(12,2) | No | Snapshot |
| `tax_amount` | DECIMAL(12,2) | No | Snapshot; policy requires confirmation |
| `total_amount` | DECIMAL(12,2) | No | Snapshot |
| `issued_at` | DATETIME | No | Issue timestamp |
| `created_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 7.4 `invoice_items`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `invoice_id` | BIGINT UNSIGNED | No | FK to `invoices` |
| `description` | VARCHAR(255) | No | Line description snapshot |
| `quantity` | DECIMAL(10,2) | No | Quantity |
| `unit_price` | DECIMAL(12,2) | No | NPR snapshot |
| `line_total` | DECIMAL(12,2) | No | NPR snapshot |

# 8. Content Tables

## 8.1 `activities`

Required fields include `id`, `title`, unique `slug`, `description`, fixed-precision `price` where applicable, `is_active`, `is_published`, `sort_order`, `created_at`, and `updated_at`.

Activity categories, images, featured flag, duration, meeting point and maximum participants are not Version 1 fields/tables. They require a future synchronized decision.

## 8.2 `gallery_categories`

Required fields include `id`, unique `name`, unique `slug`, `is_active`, `created_at`, and `updated_at`.

## 8.3 `gallery`

Required fields include `id`, nullable `gallery_category_id`, `title`, `caption`, `file_path`, `alt_text`, `sort_order`, `is_active`, `is_published`, `created_at`, and `updated_at`.

There is no `media_files` dependency. Featured, uploaded-by, soft-delete and generated-image fields are not part of Version 1; removal uses active/publication controls and audit history.

## 8.4 `settings`

Required fields include `id`, unique `setting_key`, `setting_value`, `value_type`, `is_public`, `updated_by`, `created_at`, and `updated_at`. Settings store site-wide configuration, not structured pages.

## 8.5 `pages`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `title` | VARCHAR(200) | No | Page title |
| `slug` | VARCHAR(200) | No | Unique public slug |
| `content` | LONGTEXT | No | Structured page body |
| `meta_title` | VARCHAR(255) | Yes | SEO title |
| `meta_description` | VARCHAR(500) | Yes | SEO description |
| `status` | VARCHAR(20) | No | `draft`, `published`, `archived` |
| `published_at` | DATETIME | Yes | Publication timestamp |
| `created_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `updated_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Full page version history and rollback are future enhancements.

# 9. Review Tables

## 9.1 `review_invitations`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | Unique FK to a `checked_out` booking |
| `token_hash` | VARCHAR(255) | No | Unique secure token hash |
| `expires_at` | DATETIME | No | Expiry; value requires approved policy |
| `used_at` | DATETIME | Yes | Single-use marker |
| `created_at` | DATETIME | No | Created timestamp |

## 9.2 `reviews`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | Unique FK to `bookings` |
| `review_invitation_id` | BIGINT UNSIGNED | No | Unique FK to invitation |
| `guest_name_snapshot` | VARCHAR(100) | No | Display snapshot |
| `rating` | TINYINT | No | 1 through 5 |
| `title` | VARCHAR(150) | Yes | Review title |
| `comment` | TEXT | No | Review text |
| `review_status` | VARCHAR(20) | No | `pending`, `approved`, `rejected` |
| `moderated_by` | BIGINT UNSIGNED | Yes | FK to Administrator `users` |
| `moderated_at` | DATETIME | Yes | Moderation timestamp |
| `moderation_note` | TEXT | Yes | Required when rejected by policy |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Service validation requires `bookings.booking_status = checked_out` and one review per booking.

# 10. Contact and Operations Tables

## 10.1 `contact_messages`

Required fields include `id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `created_at`, and `updated_at`. Retention, assignment, and SLA values remain **Architect/Client Confirmation Required**.

## 10.2 `notifications`

Stores in-app Administrator notifications with `id`, `user_id`, `type`, `title`, `message`, optional `related_type`/`related_id`, `read_at`, and `created_at`. Guest transactional communication is SMTP email, not a customer notification inbox in Version 1.

## 10.3 `audit_logs`

Append-only fields include `id`, nullable Administrator `user_id`, `event_type`, `entity_type`, nullable `entity_id`, `description`, safe metadata, IP/user-agent where appropriate, and `created_at`.

# 11. Availability Query Contract

A room is offered only when:

1. `rooms.operational_status = active`;
2. `rooms.is_published = true` for public results;
3. no unexpired `pending`, `confirmed`, or `checked_in` booking overlaps the requested dates;
4. requested occupancy is within capacity.

Overlap expression:

`existing.check_in < requested.check_out AND existing.check_out > requested.check_in`

# 12. Tables Not Used in Version 1

No customer-profile, customer-session, loyalty, coupon, gateway-callback, SMS, push, housekeeping-state, or CMS-version-history table belongs to Version 1. Such tables require a later approved decision and migration.

# 13. Implementation Artifacts Not Represented as Tables

- Migration files and migration execution history are implementation artifacts.
- PHP session storage may use the hosting-supported session mechanism and is not required to be a database table.
- cPanel backup files and application log files are filesystem/hosting artifacts.
- Route names, controller classes, Request classes, Services, Repositories, DTOs, and test IDs are code artifacts and are not database tables.
