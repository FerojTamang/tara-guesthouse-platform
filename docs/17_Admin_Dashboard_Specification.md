# Tara Guest House Administrator Dashboard Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 read-only Administrator dashboard projections

## 1. Routes and Access

* PHP-rendered page: `/admin/dashboard`
* JSON endpoint: `GET /api/v1/admin/dashboard`

Both require a valid Administrator PHP session. There are no Version 1 `/dashboard/statistics`, `/dashboard/revenue`, `/dashboard/bookings`, `/dashboard/reviews` or `/dashboard/notifications` endpoints.

## 2. Purpose

The dashboard provides read-only operational summaries and navigation. It never changes booking, payment, room, review or publication state. Actions occur in the owning module through its exact protected route.

## 3. Canonical Metrics

Permitted projections include:

* bookings by `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled`, `rejected`;
* arrivals/departures for a date;
* payment attempts by `pending`, `submitted`, `verified`, `rejected`, `refunded`;
* verified-payment NPR totals using fixed-precision data and approved reporting dates;
* rooms by operational `active`, `maintenance`, `inactive`;
* calculated rooms available for a supplied date range;
* calculated current occupancy from `checked_in` bookings;
* pending reviews, contact messages using the approved status policy when defined, and unread Administrator notifications;
* recent audit activity safe for dashboard display.

Available and Occupied are labels for calculated projections, never stored room operational statuses.

## 4. UI

The PHP-rendered Bootstrap page may use summary cards, tables and accessible charts. Vanilla JavaScript and selective Fetch refresh the single dashboard JSON resource. Any chart library must first be approved and recorded in dependency/setup documentation; otherwise use accessible HTML/CSS/Bootstrap presentation.

Widgets link to pages such as `/admin/bookings`, `/admin/payments`, `/admin/reviews` and `/admin/contact-messages`. Dashboard controls do not directly invoke state transitions.

## 5. Query and Layering Rules

Dashboard Service defines metric semantics and reporting date/time rules. Dashboard Repository contains optimized PDO/SQL aggregate queries. Models/entities or projection DTOs carry returned data and execute no SQL. Controller/view never queries the database.

Queries use indexes, bounded result sets and no per-row N+1 access. Cache may be added only if supported by the approved shared-hosting design and cannot become an authoritative state store.

## 6. Security and Privacy

Only Administrator may access dashboard data. Responses expose only required summary/detail fields and escape displayed Guest data. Financial totals and audit activity use authorized views. Errors do not expose SQL or server details.

## 7. Acceptance Tests

* Only `/admin/dashboard` and `GET /api/v1/admin/dashboard` implement the current dashboard.
* Metrics use canonical booking/payment statuses.
* Availability/occupancy are calculated.
* GET is read-only and creates no domain mutation.
* Unauthorized requests fail.
* Aggregate totals reconcile to authoritative module records under defined test dates.

## 8. Policy/Configuration Required

Reporting timezone is Asia/Kathmandu. Exact revenue recognition, tax inclusion and date-boundary definitions require Architect/Client Confirmation before financial dashboard acceptance.
