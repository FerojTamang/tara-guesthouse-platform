# Tara Guest House Content Management System Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 site-wide settings and structured pages
* Related contracts: `10_Database_Dictionary.md`, `23_REST_API_Specification.md`, `34_UI_UX_Screen_Specification.md`

## 1. Purpose

The Version 1 CMS lets an authenticated Administrator manage approved site-wide configuration and structured public page content without editing application code. It does not duplicate records owned by the Rooms, Activities, Gallery, Reviews or Payment Method modules.

## 2. Version 1 CMS Components

Version 1 uses only two CMS data sources:

1. `settings` for site-wide values.
2. `pages` for structured public content.

## 3. Settings

Settings may store validated site-wide values such as:

* hotel name and branding identifiers;
* address, phone, email and other contact information;
* social links;
* business hours;
* global/default SEO values;
* global/default Open Graph image;
* homepage hero text and one configured hero image;
* homepage section visibility and approved configuration;
* footer/global presentation configuration.

The `settings` persistence contract uses `id`, unique `setting_key`, `setting_value`, `value_type`, `is_public`, `updated_by`, `created_at` and `updated_at`.

Secrets do not belong in public settings. `is_public` controls which approved values may be returned by the public settings endpoint.

## 4. Structured Pages

Pages manage structured content such as:

* About;
* booking/cancellation and other policies;
* terms and conditions;
* privacy;
* FAQ where required.

### Page fields

| Field | Rule |
|---|---|
| `id` | Primary key assigned by persistence |
| `title` | Required page title |
| `slug` | Required unique public slug |
| `content` | Required sanitized structured body |
| `meta_title` | Optional page SEO title |
| `meta_description` | Optional page SEO description |
| `status` | `draft`, `published`, `archived` |
| `published_at` | Set/updated by the approved publication transition |
| `created_by`, `updated_by` | Administrator actors |
| `created_at`, `updated_at` | Record timestamps |

These fields match `10_Database_Dictionary.md`. Version 1 defines no page soft-delete field. Content that is no longer current moves to `archived` through CMS Service.

## 5. Page Publishing Workflow

Canonical page statuses:

* `draft`
* `published`
* `archived`

New pages begin as `draft`. Only `published` pages are public. Allowed status changes are performed through CMS Service, validate required content/slug/metadata, record Administrator/time, and produce audit evidence. Archived pages are retained but are not publicly visible.

Version 1 has no content scheduling or content-version history/rollback workflow.

## 6. Homepage Composition

The homepage uses:

* settings for hero text, one configured hero image and section visibility/configuration;
* a Room Preview queried from the Rooms module;
* an Activities preview queried from active, published Activities records;
* a Gallery preview queried from active, published Gallery records;
* a Reviews preview queried from approved Reviews records;
* approved global contact/footer settings.

The CMS does not create duplicate room, activity, gallery or review records. It does not require featured-room or featured-activity flags. Preview selection uses approved module filtering/order/configuration rather than duplicate CMS content.

## 7. Payment Instructions

Payment instructions, account references, QR path and proof requirements belong to Payment Method Management in `payment_methods`. They are not generic CMS page/settings content.

## 8. SEO Contract

Version 1 SEO includes:

* SEO-friendly public routes/slugs;
* page `meta_title` and `meta_description`;
* a canonical URL derived from the current public route;
* Open Graph title and description derived from page metadata, with approved global defaults from settings where page values are absent;
* one global/default Open Graph image stored in settings;
* XML sitemap generation from public routes and published records;
* schema markup where appropriate to the public page/content type.

Meta keywords are not required. There are no per-page Open Graph image fields in Version 1.

## 9. Exact Routes and APIs

Public:

* `GET /api/v1/pages/{slug}` — return a published page only.
* `GET /api/v1/settings/public` — return allowlisted public settings only.

Administrator:

* `GET /api/v1/admin/pages`
* `POST /api/v1/admin/pages`
* `GET /api/v1/admin/pages/{id}`
* `PUT /api/v1/admin/pages/{id}`
* `PATCH /api/v1/admin/pages/{id}/status`
* `GET /api/v1/admin/settings`
* `PUT /api/v1/admin/settings`

PHP-rendered pages use their normal public and `/admin/pages` or `/admin/settings` routes. No additional CMS JSON endpoint is defined.

## 10. Validation and Security

* Only an authenticated Administrator may mutate settings/pages.
* State-changing requests require CSRF and authorization.
* Slugs and setting keys are unique and allowlisted/validated.
* Rich content is sanitized and output is contextually escaped.
* Public settings expose only approved `is_public` keys.
* Hero/default Open Graph image upload uses approved upload configuration and non-executable storage.
* CMS Service owns rules/transitions/transactions; Repositories own PDO/SQL; Models/entities contain data only.
* Mutations and publication actions are audited.

## 11. Acceptance Tests

1. Settings and pages use only dictionary fields.
2. New page begins `draft`; only `published` is public.
3. Archive removes public visibility without soft deletion.
4. Page API rejects unpublished/unknown slugs.
5. Public settings endpoint returns only approved public keys.
6. Homepage previews read their owning module records without duplication.
7. Booking confirmation/payment instructions are not controlled by CMS.
8. Canonical/OG/sitemap behavior follows the SEO contract.
9. No meta-keyword or per-page OG-image field is required.
10. Only the exact documented CMS/settings endpoints are used.

## 12. Future Enhancements

The following are outside Version 1 and require later synchronized decisions:

* featured-room or featured-activity database flags;
* multiple page images;
* separate hero-banner table;
* announcements module;
* meta keywords;
* per-page Open Graph image fields;
* content version history and rollback;
* page builder/drag-and-drop editor;
* content scheduling;
* multi-language content, blog/news, newsletters and AI content tools.
