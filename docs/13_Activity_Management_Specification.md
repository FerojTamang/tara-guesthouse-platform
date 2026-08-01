# Tara Guest House Activity Management Specification

## Document Information

* Status: Implemented and QA synchronized in Sprint 4 — Review Required
* Scope: Version 1 activity content and publication
* Current extension: Sprint 5 UI prerequisite structured cards, secure images and homepage curation

## 1. Purpose and Actors

Guests may browse active, published activities. Administrators manage the content through PHP-rendered `/admin/activities...` pages and `/api/v1/admin/activities...` JSON endpoints.

## 2. Version 1 Data Contract

The `activities` table contains:

| Field | Rule |
|---|---|
| `id` | Primary key |
| `title` | Required display name |
| `slug` | Required and unique |
| `summary` | Required bounded card summary |
| `description` | Required activity content |
| `featured_image_path` | Optional server-controlled private image path |
| `price` | Nullable fixed-precision NPR value |
| `price_unit` | Required when a price is published; otherwise null |
| `duration_label`, `location` | Optional bounded card labels |
| `is_active` | Operational/business control |
| `is_published` | Separate publication control |
| `is_homepage_featured` | Explicit homepage selection |
| `homepage_sort_order` | Homepage ordering |
| `sort_order` | Public ordering |
| `created_at`, `updated_at` | Timestamps |

No activity category, separate activity-image table, meeting-point or maximum-participants field/table is approved in Version 1.

## 3. Public and Administrator Workflows

Public `GET /api/v1/activities` returns only active and published records in approved order. Homepage composition additionally requires explicit selection, orders by homepage order/global order/id, and limits output to four. Administrator endpoints list all records, create/update content, and change active/publication controls separately. Removal deactivates/unpublishes the record; no hard delete is required by the current dictionary.

## 4. Validation and Security

* Title, unique slug and description are validated server-side.
* Price is nullable, uses fixed-precision NPR, cannot be negative, and requires a rate unit when present.
* `is_active` and `is_published` are separate booleans.
* Sort order is an allowed integer.
* Only an authenticated Administrator may mutate activity data.
* Activity Service owns rules/transactions; Repository owns PDO/SQL; Model/entity contains data only.
* Mutations are audited.

Activity images reuse the existing configured gallery image validation, randomized private storage, containment and cleanup controls. Create may omit an image; edit retains the current image unless a valid replacement is supplied.

## 5. Acceptance Tests

* Public responses contain only active and published activities.
* Deactivating does not silently overwrite publication, and vice versa.
* Administrator mutations use `/api/v1/admin/activities...`.
* Homepage selection does not bypass active/publication eligibility and public null prices are omitted rather than shown as NPR 0.

## 6. Future Enhancements

Activity categories, separate activity galleries, meeting point, maximum participants, online activity booking, packages, maps, calendars and reviews require a later decision and synchronized database/API/UI changes.
