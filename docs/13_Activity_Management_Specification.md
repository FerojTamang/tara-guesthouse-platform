# Tara Guest House Activity Management Specification

## Document Information

* Status: Implemented and QA synchronized in Sprint 4 — Review Required
* Scope: Version 1 activity content and publication

## 1. Purpose and Actors

Guests may browse active, published activities. Administrators manage the content through PHP-rendered `/admin/activities...` pages and `/api/v1/admin/activities...` JSON endpoints.

## 2. Version 1 Data Contract

The `activities` table contains:

| Field | Rule |
|---|---|
| `id` | Primary key |
| `title` | Required display name |
| `slug` | Required and unique |
| `description` | Required activity content |
| `price` | Fixed-precision NPR value where applicable |
| `is_active` | Operational/business control |
| `is_published` | Separate publication control |
| `sort_order` | Public ordering |
| `created_at`, `updated_at` | Timestamps |

No activity category, featured-image, gallery-image, featured flag, duration, meeting-point or maximum-participants field/table is approved in Version 1. These fields must not appear in current forms, payloads or persistence instructions.

## 3. Public and Administrator Workflows

Public `GET /api/v1/activities` returns only active and published records in approved order. Administrator endpoints list all records, create/update content, and change active/publication controls separately. Removal deactivates/unpublishes the record; no hard delete is required by the current dictionary.

## 4. Validation and Security

* Title, unique slug and description are validated server-side.
* Price uses fixed-precision NPR and cannot be negative.
* `is_active` and `is_published` are separate booleans.
* Sort order is an allowed integer.
* Only an authenticated Administrator may mutate activity data.
* Activity Service owns rules/transactions; Repository owns PDO/SQL; Model/entity contains data only.
* Mutations are audited.

Version 1 has no activity image upload, so it defines no activity upload limit.

## 5. Acceptance Tests

* Public responses contain only active and published activities.
* Deactivating does not silently overwrite publication, and vice versa.
* Administrator mutations use `/api/v1/admin/activities...`.
* No unapproved category/image/featured/duration/location/participant fields are required.

## 6. Future Enhancements

Activity categories, featured images, galleries, featured ranking, duration, meeting point, maximum participants, online activity booking, packages, maps, calendars and reviews require a later decision and synchronized database/API/UI changes.
