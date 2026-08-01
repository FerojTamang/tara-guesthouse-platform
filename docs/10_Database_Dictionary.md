# Tara Guest House Management System

# 10. Database Dictionary

## Document Control

| Field | Value |
|---|---|
| Version | 1.3 |
| Status | Implemented through Sprint 4 — Review Required |
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
| Authentication | `roles`, `users` |
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

## 4.3 Deferred staff password-reset storage

No `password_resets` table exists in the 25 executable migrations. Any later Administrator password-reset workflow requires a separately approved migration and route contract and must store only a token hash, expiry, and single-use evidence.

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
| `file_path` | VARCHAR(255) | No | Unique safe relative path |
| `alt_text` | VARCHAR(255) | Yes | Accessible description |
| `sort_order` | INT | No | Display order |
| `is_primary` | BOOLEAN | No | Primary-image flag |
| `created_at` | DATETIME | No | Created timestamp |
| `primary_room_id` | BIGINT UNSIGNED (VIRTUAL GENERATED) | Yes | Implementation-only constraint value: `room_id` for a primary image and `NULL` otherwise |

`primary_room_id` is generated by the database and is never accepted from user input or exposed as a `RoomImage` domain property. Its unique index permits multiple non-primary `NULL` values while enforcing at most one primary image per room. `file_path` is unique, and the (`room_id`, `sort_order`, `id`) index supports deterministic ordered room-image reads.

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
| `tax_amount` | DECIMAL(12,2) | No | Exactly `0.00` in the implemented Version 1 invoice contract |
| `total_amount` | DECIMAL(12,2) | No | `subtotal + tax_amount`; no service charge |
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

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `title` | VARCHAR(200) | No | Trimmed display title |
| `slug` | VARCHAR(200) | No | Unique lowercase slug |
| `description` | TEXT | No | Trimmed public description |
| `price` | DECIMAL(12,2) | No | Non-negative NPR amount |
| `is_active` | TINYINT(1) | No | Operational control |
| `is_published` | TINYINT(1) | No | Public visibility control |
| `sort_order` | INT UNSIGNED | No | Deterministic order; default 0 |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Activity categories, images, featured flag, duration, meeting point and maximum participants are not Version 1 fields/tables.

## 8.2 `gallery_categories`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `name` | VARCHAR(150) | No | Unique trimmed category name |
| `slug` | VARCHAR(200) | No | Unique lowercase slug |
| `is_active` | TINYINT(1) | No | Category eligibility control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

## 8.3 `gallery`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `gallery_category_id` | BIGINT UNSIGNED | Yes | Nullable FK; category deletion sets null |
| `title` | VARCHAR(200) | No | Trimmed display title |
| `caption` | TEXT | Yes | Optional trimmed caption |
| `file_path` | VARCHAR(500) | No | Server-controlled safe relative private path |
| `alt_text` | VARCHAR(500) | No | Accessible alternative text |
| `sort_order` | INT UNSIGNED | No | Deterministic order; default 0 |
| `is_active` | TINYINT(1) | No | Operational control |
| `is_published` | TINYINT(1) | No | Public visibility control |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

There is no `media_files` dependency. Featured, uploaded-by, soft-delete and generated-image fields are not part of Version 1; removal uses active/publication controls and audit history.

## 8.4 `settings`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `setting_key` | VARCHAR(150) | No | Unique dotted lower-case key; application allowlist required |
| `setting_value` | LONGTEXT | No | Typed value stored after validation |
| `value_type` | VARCHAR(30) | No | `string`, `integer`, `boolean`, `email`, `url`, `relative_path`, or `json` |
| `is_public` | TINYINT(1) | No | Public API eligibility |
| `updated_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Settings store allowlisted site-wide configuration, not arbitrary secrets or structured pages.

## 8.5 `pages`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `title` | VARCHAR(200) | No | Page title |
| `slug` | VARCHAR(200) | No | Unique public slug |
| `content` | LONGTEXT | No | Policy-validated page body |
| `meta_title` | VARCHAR(255) | Yes | SEO title |
| `meta_description` | VARCHAR(500) | Yes | SEO description |
| `status` | VARCHAR(20) | No | `draft`, `published`, `archived` |
| `published_at` | DATETIME | Yes | Required when published |
| `created_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `updated_by` | BIGINT UNSIGNED | No | FK to Administrator `users` |
| `created_at` | DATETIME | No | Created timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Full page version history, scheduling, deletion and rollback are future enhancements.

# 9. Review Tables

## 9.1 `review_invitations`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `booking_id` | BIGINT UNSIGNED | No | Unique FK to a `checked_out` booking |
| `token_hash` | VARCHAR(255) | No | Unique 64-character lowercase SHA-256 token hash |
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

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `name` | VARCHAR(100) | No | Trimmed sender name |
| `email` | VARCHAR(150) | No | Validated sender email |
| `phone` | VARCHAR(20) | Yes | Optional sender phone |
| `subject` | VARCHAR(200) | No | Trimmed subject |
| `message` | TEXT | No | Retained message body |
| `status` | VARCHAR(40) | Yes | Null until an approved workflow vocabulary exists |
| `created_at` | DATETIME | No | Submission timestamp |
| `updated_at` | DATETIME | No | Updated timestamp |

Sprint 4 provides public create and Administrator read-only list/detail. Retention, assignment, SLA and status mutation remain **Architect/Client Confirmation Required**.

## 10.2 `notifications`

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | No | Primary key |
| `user_id` | BIGINT UNSIGNED | No | Owning Administrator FK to `users` |
| `type` | VARCHAR(50) | No | Lowercase event type |
| `title` | VARCHAR(150) | No | Trimmed display title |
| `message` | TEXT | No | Trimmed display message |
| `related_type` | VARCHAR(50) | Yes | Optional lowercase related entity type |
| `related_id` | BIGINT UNSIGNED | Yes | Optional positive related entity identifier |
| `read_at` | DATETIME | Yes | Owner-scoped read timestamp |
| `created_at` | DATETIME | No | Creation timestamp |

Guests have no notification ownership or inbox in Version 1. Records are retained; no delete/archive route is registered.

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
