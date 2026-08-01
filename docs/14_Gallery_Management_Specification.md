# Tara Guest House Gallery Management Specification

## Document Information

* Status: Implemented and QA synchronized in Sprint 4 — Review Required
* Scope: Version 1 gallery categories, images and publication

## 1. Purpose and Actors

Guests browse active, published gallery items. Administrators manage categories and images through protected PHP-rendered pages and `/api/v1/admin/gallery...` endpoints.

## 2. Version 1 Data Contract

### 2.1 Gallery categories

`gallery_categories` contains `id`, unique `name`, unique `slug`, `is_active`, `created_at` and `updated_at`.

### 2.2 Gallery items

| Field | Rule |
|---|---|
| `id` | Primary key |
| `gallery_category_id` | Optional category relationship |
| `title` | Required display title |
| `caption` | Optional text |
| `file_path` | Required safe relative image path |
| `alt_text` | Accessible description |
| `sort_order` | Display order |
| `is_active` | Business/removal control |
| `is_published` | Separate public-visibility control |
| `created_at`, `updated_at` | Timestamps |

There is no `media_files` table in Version 1. Featured, uploaded-by, soft-delete timestamp and generated-image database fields are not approved. Removal is represented by deactivation/unpublication; audit records preserve the Administrator action.

Generated thumbnails/resized variants may be filesystem/cache artifacts created according to hosting capability. They are not authoritative database records and must be reproducible from the stored source file where the selected workflow permits.

## 3. Exact Endpoints

Public:

* `GET /api/v1/gallery`

Administrator:

* `GET /api/v1/admin/gallery-categories`
* `POST /api/v1/admin/gallery-categories`
* `PUT /api/v1/admin/gallery-categories/{id}`
* `GET /api/v1/admin/gallery`
* `POST /api/v1/admin/gallery`
* `PUT /api/v1/admin/gallery/{id}`
* `PATCH /api/v1/admin/gallery/{id}/active`
* `PATCH /api/v1/admin/gallery/{id}/publication`
* `PATCH /api/v1/admin/gallery/reorder`
* `DELETE /api/v1/admin/gallery/{id}` — implemented as deactivation/unpublication, not hard deletion

## 4. Shared-Hosting Image Workflow

1. Administrator uploads one supported image through the protected form/endpoint.
2. Server validates configured size, extension, MIME and decodability.
3. Server generates a random filename and stores the file in a non-executable upload location supported by cPanel hosting.
4. Optional resized variants are created only when the host's PHP image extension/resources support them.
5. Repository records the safe relative `file_path` and metadata in `gallery`.
6. On failure, partial files are cleaned up and the error is logged without exposing server paths.

No numeric maximum is hardcoded. The approved upload configuration and host limits control the maximum.

## 5. Display Rules

Public gallery results require both `is_active = true` and `is_published = true`, an active category where a category is assigned, and accessible alt text. Ordering uses `sort_order`. Public UI may provide responsive grid/lightbox behavior using approved project dependencies only.

## 6. Security and Persistence

Only authenticated Administrators mutate gallery data. Gallery Service coordinates filesystem/database operations and transactions/compensation. Gallery Repository contains PDO/SQL; Model/entity contains data only. Executable content, unsafe paths and client filenames are rejected. Mutations are audited.

## 7. Acceptance Tests

* No `media_files` dependency exists.
* Public results include only active/published items.
* Active and publication controls remain independent.
* Upload validation reads approved configuration.
* Database failure does not leave an authoritative orphan record/file.
* DELETE route deactivates/unpublishes rather than destroying history.
* No featured/uploaded-by/deleted-at/generated-image field is required.

## 8. Future Enhancements

Featured gallery ranking, uploader ownership, recoverable soft-delete fields, dedicated media library, generated-variant records, bulk upload, video/360 media, cloud storage/CDN, AI processing and customer uploads require later approved contracts.
