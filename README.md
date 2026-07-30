# Tara Guest House Management System

A responsive public website and administration system for Tara Guest House, Sauraha, Chitwan, Nepal, developed by the project team as a commercial hospitality application.

## Documentation Authority

Before implementation, read [Version 1 Architect Decisions](docs/00_V1_ARCHITECT_DECISIONS.md). It overrides conflicting Version 1 statements in other documents. The preserved 38-document suite is indexed in [Documentation Index](docs/33_Documentation_Index.md).

## Version 1 Objectives

- Modernize the guest house's online presence.
- Increase direct booking enquiries and reservations.
- Provide clear room, activity, gallery, review, policy, and contact content.
- Reduce manual administrative work.
- Provide secure room, booking, payment, invoice, review, CMS, notification, and audit workflows.
- Deliver a responsive, maintainable application compatible with cPanel/shared hosting.

## Version 1 Scope

### Public website

- Home and About
- Rooms, room details, and date-range availability
- Activities and Gallery
- Verified-stay reviews
- Contact and CMS-managed policies/pages
- Guest booking without an account, one room per booking
- Manual payment-detail/proof submission

### Administration

- Administrator session login/logout
- Dashboard
- Rooms and publication
- Availability and bookings
- Manual payment verification and invoices
- Activities, gallery, reviews, contact messages, CMS/settings
- In-app notifications, transactional SMTP email, and audit logging

Customer accounts/portal, JWT, specialized staff roles, multi-room booking, payment gateways, SMS/WhatsApp/push, mobile applications, multiple hotels, Redis, WebSockets, Docker/Kubernetes, and VPS/cloud production infrastructure are Future Enhancements.

## Approved Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, vanilla JavaScript ES6+ |
| Rendering | PHP-rendered views with selective Fetch/AJAX |
| Backend | PHP 8.x, Composer PSR-4, custom layered MVC-inspired architecture |
| Data access | Repositories using PDO prepared statements |
| Database | MariaDB 11.8 (primary tested engine) through `pdo_mysql`; migrations are executable source of truth |
| Authentication | Hardened server-side PHP sessions and CSRF protection |
| Routes | Normal page routes plus `/api/v1/...` same-origin JSON endpoints |
| Web server | Apache |
| Production | HTTPS on cPanel/shared hosting |
| Development | PHP/Apache with the tested MariaDB 11.8 environment |

The required backend flow is:

```text
Route -> Middleware -> Controller -> Request Validation
      -> Service -> Repository -> Database
```

## Repository Setup

The development environment requires PHP 8.3 or newer, Composer 2, and the PHP extensions declared in `composer.json`.

```bash
composer install
cp .env.example .env
composer test
```

On Windows PowerShell, copy the environment template with:

```powershell
Copy-Item .env.example .env
```

Review every local `.env` value before running later application tickets. Never commit `.env`, credentials, logs, sessions, backups, confidential payment proofs, or real uploaded files. Payment proofs belong in approved private runtime storage and must not be directly public.

Available test commands are `composer test`, `composer test:unit`, `composer test:integration`, `composer test:feature`, and `composer test:database`. The suite covers the foundation, Administrator authentication, room management, public room pages, date-range availability, transactional one-room Guest booking, concurrency protection, secure booking references and follow-up tokens, public rate limiting, configurable payment methods, private proof storage, retained Guest payment attempts, Administrator payment review and decisions, payment-driven booking confirmation, invoice issue/detail/print workflows, and append-only audit evidence.

The core HTTP bootstrap is executable. After configuring the required local environment values, start PHP's development server from the repository root:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080/` to verify the temporary infrastructure welcome response. It confirms only that the bootstrap is running; it is not the production homepage. The production Apache/cPanel document root must point to `public/` so application source, configuration, logs, and private files remain outside direct web access.

## View Foundation

The application includes a secure PHP view renderer for trusted project templates under `resources/views/`. Application code selects views through strict lowercase dot names such as `infrastructure.welcome`; request values must never select template names. Layouts use the `layouts.*` prefix, reusable components use `components.*`, and components receive only explicitly supplied presentation data.

Templates must escape HTML text and quoted attribute values through the provided `$e` helper. There is no raw HTML helper; renderer-owned layout content is the only approved unescaped composition value. The HTML helper is not a JavaScript, CSS, JSON, URL, or URL-scheme sanitizer. Templates must not access PDO, repositories, services, sessions, environment values, or configuration, and must not send headers, terminate execution, query data, or contain business logic.

The current `/` page remains infrastructure-only. The secure view foundation now also renders the Administrator layout, room-management pages, public room, availability, booking and payment pages, Administrator booking/payment/invoice pages, protected proof responses, and authenticated invoice printing. Final homepage/content design and later Version 1 modules remain deferred to their approved tickets.

Run the genuine core test suite with:

```bash
composer test
```

Version 1 uses hardened native PHP sessions. The approved local settings use a 30-minute inactivity timeout, a 12-hour absolute lifetime, `SameSite=Lax`, `HttpOnly`, `Path=/`, and a non-Secure cookie only for local HTTP development. Staging and production require HTTPS with `SESSION_SECURE_COOKIE=true`. Private session files are stored in `storage/sessions/`, outside the public document root.

The session-backed CSRF token system is enforced through narrowly scoped middleware on implemented state-changing HTML and JSON routes, including Administrator authentication, room and payment-method management, public booking/payment submission, Administrator booking/payment decisions, and invoice issue. Session identifiers, cookie contents, session values, signed booking-access values, and CSRF tokens must never be logged.

## Database Foundation

S0-004 provides strict environment-driven PDO configuration, ordered migrations, checksummed migration history, last-batch rollback, advisory locking, grouped seeders, and a small CLI. Applied domain migrations now include Administrator authentication, rooms, amenities, room images, bookings, one-room snapshots, booking events, audit logs, payment methods, retained payment attempts, invoices, and invoice items. The Sprint 3 local database baseline is 16 applied migrations with zero pending. Migrations remain the executable schema source of truth.

MariaDB 11.8 is the primary tested database engine. Connections use `utf8mb4`, `utf8mb4_unicode_ci`, UTC session time, native prepared statements, and a five-second timeout. Application display dates remain configured through `APP_TIMEZONE=Asia/Kathmandu`. HTTP bootstrap validates database settings but does not eagerly connect.

Configure both the development and isolated test values shown in `.env.example`. Port `3307` is the current developer's local MariaDB setting, not a project-wide requirement; other developers and production must set the host-provided port through `.env` or protected environment configuration. Database credentials belong only in those private environment sources and must never be committed. The test database must differ from the development database and end in `_test` or `_testing`. Never run test or acceptance commands against the development database.

Available commands are:

```bash
composer database:status
composer database:migrate
composer database:rollback
composer database:seed -- --group=testing
composer test:database
```

The seed command requires an explicit `--group=production`, `--group=development`, `--group=testing`, or `--group=demo`. Production migration requires `--force`; production rollback is disabled; and production refuses development, testing, and demo seeders. Migration files use `YYYYMMDDHHMMSS_lowercase_description.php`, must return one `App\Core\Migration` instance, and must never be edited after application. Seeder files use lowercase snake case, return one `App\Core\Seeder` instance, and declare their group.

## Administrator Authentication Foundation

Sprint 1 includes executable `roles` and `users` migrations, passive immutable Role/User models, read-only Administrator repository lookups, and a protected CLI mechanism for creating the initial Administrator account. The production seeder creates only the non-secret `Administrator` role prerequisite. Guest remains an unauthenticated public actor and is not stored as a database role; no customer or future staff roles are seeded.

Run the deployment steps in this order:

```bash
composer database:migrate
composer database:seed -- --group=production
php bin/admin.php bootstrap
```

The interactive command prompts for identity values and reads the password twice through protected terminal input. Interactive identity prompts are preferred because identity flags may expose personal data in shell history or process listings. Controlled deployment tooling may instead supply all identity options and pipe exactly two newline-delimited password values to protected, non-terminal standard input:

```bash
php bin/admin.php bootstrap --full-name "<full name>" --username "<username>" --email "<email>" --password-stdin
```

An optional `--phone` identity value is supported. Staging and production require the exact `--force` flag; it does not bypass validation, prerequisite, conflict, database-account, or transaction safeguards. There is no password command-line flag, password-file option, or password environment-variable contract.

Bootstrap is single-use. It never modifies, resets, activates, unlocks, restores, or replaces an existing account. Passwords and hashes must never be placed in migrations, seeders, tracked environment files, commands, or documentation. Administrator login/logout, session identity, protected middleware, login rate limiting, CSRF, and authentication views are implemented. Staff password reset and audit-log administration remain deferred to later approved tickets; booking-status changes already write append-only audit records.

Create task branches from `develop`, open a pull request back to `develop`, and merge approved releases to `main` according to `docs/07_Git_Workflow.md`. Direct commits to `main` and `develop` are prohibited.

## Repository Structure

```text
tara-guest-house/
|-- app/
|   |-- Controllers/
|   |-- Requests/
|   |-- Services/
|   |-- Repositories/
|   |-- Models/
|   |-- Middleware/
|   |-- Core/
|   |-- Helpers/
|   `-- Exceptions/
|-- config/
|-- database/
|   |-- migrations/
|   |-- seeders/
|   |-- backups/
|   `-- schema.sql
|-- public/
|   |-- assets/
|   `-- uploads/
|-- resources/
|   `-- views/
|-- routes/
|-- storage/
|-- tests/
|-- client-assets/
|-- design/
|-- meetings/
|-- vendor/
|-- docs/
|-- .env.example
|-- .gitignore
|-- composer.json
|-- composer.lock
`-- README.md
```

## Security Baseline

- Password hashing and verification
- Secure PHP sessions and cookies
- CSRF protection
- Authorization middleware
- Server-side validation and context-aware output escaping
- PDO prepared statements
- Login rate limiting
- Secure upload validation and non-executable uploads
- Environment secrets excluded from Git
- HTTPS, private error logs, and audit logging

AI-generated code is subject to the same controls and may not invent routes, roles, statuses, columns, frameworks, or future features.

## Testing

The required baseline includes unit, integration, feature, security, responsive/browser, and UAT coverage. Critical automated tests cover authentication/authorization, CSRF, rooms/publication, availability overlap and concurrency, booking transitions, payment attempts/verification, invoices, check-in/check-out, verified-stay reviews, uploads, and API response contracts.

## Approved Implementation Order

1. Documentation synchronization
2. Foundation
3. Administrator authentication and rooms
4. Availability and booking
5. Payments and invoices
6. Operations and content
7. Dashboard, hardening, and cPanel deployment

## Current Status

**Sprint 0** — Repository, HTTP, session/CSRF, database-runner, and view-renderer foundations are implemented, formally approved, and merged. See [Sprint 0 QA Closure Report](docs/39_Sprint_0_QA_Closure_Report.md).

**Sprint 1 Phase 2** — Administrator authentication and Rooms Phase 2 is implemented and tested:
- Administrator bootstrap, login/logout, protected administration routes
- Room types, amenities, room CRUD, soft deletion
- Operational status (active, maintenance, inactive) and publication controls
- Room image upload, metadata management, deletion
- Public room listing and detail pages with safe Not Found responses
- Calculated eligibility filtering (active, published, capacity, unexpired bookings)

Sprint 1 automated baseline: **970 tests, 6088 assertions, 10 skipped, 0 failures**. See [Sprint 1 QA Closure Report](docs/40_Sprint_1_QA_Closure_Report.md).

**Sprint 2 Phase 3 slice** — Availability and Booking is implemented and tested:
- Public date-range availability page and `GET /api/v1/availability`
- Strict overlap boundaries, canonical blocking statuses, and expired-pending behavior
- Transactional one-room Guest booking with room locking and concurrency protection
- Secure non-sequential booking references and opaque signed follow-up tokens
- Public JSON and PHP-rendered booking forms with CSRF, rate limiting, PRG success flow, and no-store responses
- Administrator booking list/detail pages and canonical status filtering
- Protected `PATCH /api/v1/admin/bookings/{id}/status` actions with transition enforcement
- Append-only booking events and audit records for Administrator status changes

Sprint 2 automated baseline: **1245 tests, 7597 assertions, 10 skipped, 0 failures**. See [Sprint 2 QA Closure Report](docs/41_Sprint_2_QA_Closure_Report.md).

**Sprint 3 Phase 4 slice** — Manual Payments and Invoices is implemented and locally regression-tested:
- Administrator-configured active/inactive manual payment methods
- Public active-method reads and secure PHP/JSON Guest payment submission
- Private JPEG/PNG/PDF proof storage with protected audited Administrator retrieval
- Retained payment attempts, submitted-only verify/reject decisions, and duplicate-verification prevention
- One coordinated transaction for payment verification and `pending -> confirmed` booking confirmation
- Stable one-per-booking `TGH-INV-YYYY-NNNNNN` invoices with immutable NPR snapshots, `NPR 0.00` tax, no service charge, authenticated detail, and protected printing
- Append-only payment, booking-confirmation, proof-access, and invoice-issue audit evidence

Sprint 3 local automated baseline: **1558 tests, 9631 assertions, 11 skipped, 0 failures** with **16 applied migrations and 0 pending**. See [Sprint 3 QA Closure Report](docs/43_Sprint_3_QA_Closure_Report.md).

**Sprint 4 Phase 5 plan** — Operations and Content is approved for incremental implementation on `feat/s4-operations-content`. The plan covers activities, gallery, settings/CMS pages, contact messages, verified-stay reviews, Administrator notifications, and transactional SMTP email. Every ticket is committed and pushed after tests pass, with an early Draft PR targeting `develop`. See [Sprint 4 Operations and Content Implementation Plan](docs/44_Sprint_4_Operations_Content_Implementation_Plan.md).

**Later phases** — Dashboard completion, browser/UAT evidence, hardening, cPanel verification, and production deployment remain unimplemented or externally gated.

## Ownership

The repository is a private commercial project. Team responsibilities and review rules are defined in `docs/03_Team_Organization.md` and `docs/07_Git_Workflow.md`.
