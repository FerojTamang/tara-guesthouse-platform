# Tara Guest House Room Management Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 room catalogue, media, amenities and calculated availability

## 1. Actors and Routes

Guests may browse published rooms and calculate date availability. Administrators manage room records through PHP-rendered `/admin/rooms...` pages and the exact `/api/v1/admin/rooms...`, `/api/v1/admin/room-types...` and `/api/v1/admin/amenities...` endpoints in `23_REST_API_Specification.md`.

## 2. Version 1 Data Contract

### 2.1 Room types

`room_types` fields are `id`, `name`, `slug`, `description`, `base_price`, `capacity_adults`, `capacity_children`, `is_active`, `created_at` and `updated_at`.

### 2.2 Rooms

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `room_type_id` | Required room-type relationship |
| `room_number` | Unique physical identifier |
| `name`, `slug`, `description` | Public content |
| `price_per_night` | Current fixed-precision NPR rate |
| `capacity_adults`, `capacity_children` | Occupancy limits |
| `operational_status` | `active`, `maintenance`, `inactive` |
| `is_published` | Separate public-visibility control |
| timestamps/`deleted_at` | History and soft-deactivation support |

Bed type, floor, room size and featured flag are not approved Version 1 room columns. They are Future Enhancements unless an explicit decision updates the dictionary, migrations, API, UI and tests together.

### 2.3 Room images

`room_images` stores `room_id`, `file_path`, optional `alt_text`, `sort_order`, `is_primary` and `created_at`. `is_primary` selects the thumbnail/primary display image. Generated thumbnail variants may be filesystem/cache implementation artifacts, but no thumbnail database column/table is required.

### 2.4 Amenities

`amenities` stores name, optional icon identifier, active control and timestamps. `room_amenities` provides the many-to-many assignment.

## 3. Operational Status, Publication and Availability

Operational status values are only:

* `active`
* `maintenance`
* `inactive`

Publication is stored separately in `is_published`. Available, Reserved and Occupied are calculated conditions, not room operational statuses.

A public room is eligible only when it is `active`, published, within capacity and has no unexpired overlapping booking in `pending`, `confirmed` or `checked_in`. Current occupancy is derived from `checked_in` bookings.

## 4. Administrator Workflows

Administrators may create/update room types and rooms, set operational status, set publication, upload/reorder/remove images, select one primary image and assign amenities. Rooms referenced by historical bookings are deactivated/soft-deleted rather than physically removed.

Room forms do not offer Featured, Bed Type, Floor or Room Size fields in Version 1. Lists show operational status, publication and calculated availability/occupancy in separate columns.

## 5. Validation

* Room number and slug are unique.
* Prices are non-negative fixed-precision NPR values.
* Capacities are non-negative and total permitted occupancy is positive.
* Operational/publication values use their exact domains.
* A primary image must belong to the same room; at most one image is primary.
* Image MIME, extension, dimensions and size use approved upload configuration, not a hardcoded limit.
* Image filenames are randomized and stored outside executable behavior.

## 6. Security and Persistence

Only authenticated Administrators may mutate room data. Controllers validate requests; Room Service enforces rules/transactions; Repositories contain PDO/SQL; Models/entities contain domain data only. All sensitive changes are audited.

## 7. Acceptance Tests

* Only active/published rooms appear publicly.
* Maintenance/inactive rooms cannot be booked.
* Availability overlap includes pending, confirmed and checked-in bookings.
* Adjacent checkout/check-in dates do not overlap.
* Operational status rejects Available/Reserved/Occupied.
* Publication changes do not rewrite operational status.
* Primary-image uniqueness and amenity relationships are enforced.
* Historical booking relationships prevent destructive room deletion.

## 8. Future Enhancements

Bed type, floor, room size, featured-room ranking, seasonal/dynamic pricing, videos, 360-degree media, room comparison, wishlists and advanced housekeeping require later approved contracts.
