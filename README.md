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

Available test commands are `composer test`, `composer test:unit`, `composer test:integration`, and `composer test:feature`. S0-002 includes genuine unit, integration, and feature coverage for the bootstrap and HTTP core.

The core HTTP bootstrap is executable. After configuring the required local environment values, start PHP's development server from the repository root:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open `http://127.0.0.1:8080/` to verify the temporary infrastructure welcome response. It confirms only that the bootstrap is running; it is not the production homepage. The production Apache/cPanel document root must point to `public/` so application source, configuration, logs, and private files remain outside direct web access.

## View Foundation

The application includes a secure PHP view renderer for trusted project templates under `resources/views/`. Application code selects views through strict lowercase dot names such as `infrastructure.welcome`; request values must never select template names. Layouts use the `layouts.*` prefix, reusable components use `components.*`, and components receive only explicitly supplied presentation data.

Templates must escape HTML text and quoted attribute values through the provided `$e` helper. There is no raw HTML helper; renderer-owned layout content is the only approved unescaped composition value. The HTML helper is not a JavaScript, CSS, JSON, URL, or URL-scheme sanitizer. Templates must not access PDO, repositories, services, sessions, environment values, or configuration, and must not send headers, terminate execution, query data, or contain business logic.

The current `/` page is infrastructure-only and confirms that the application and renderer are operational. Final Bootstrap styling, frontend assets, the public website layout, and the Administrator layout remain deferred to later approved tickets.

Run the genuine core test suite with:

```bash
composer test
```

Version 1 uses hardened native PHP sessions. The approved local settings use a 30-minute inactivity timeout, a 12-hour absolute lifetime, `SameSite=Lax`, `HttpOnly`, `Path=/`, and a non-Secure cookie only for local HTTP development. Staging and production require HTTPS with `SESSION_SECURE_COOKIE=true`. Private session files are stored in `storage/sessions/`, outside the public document root.

The session-backed CSRF token primitives are available for future forms, but route-level enforcement is intentionally deferred until the first real state-changing form and its narrowly scoped middleware are implemented. Session identifiers, cookie contents, session values, and CSRF tokens must never be logged.

## Database Foundation

S0-004 provides strict environment-driven PDO configuration, ordered migrations, checksummed migration history, last-batch rollback, advisory locking, grouped seeders, and a small CLI. It creates only the infrastructure-owned `schema_migrations` table; guest-house domain tables and seed data belong to later approved tickets.

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

The seed command requires an explicit `--group=production`, `--group=development`, `--group=testing`, or `--group=demo`. Production migration requires `--force`; production rollback is disabled; and production refuses development, testing, and demo seeders. Migration files use `YYYYMMDDHHMMSS_lowercase_description.php`, must return one `App\Core\Migration` instance, and must never be edited after application. Seeder files use lowercase snake case, return one `App\Core\Seeder` instance, and declare their group. No production or domain seeder is included in this foundation.

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

Documentation has been synchronized to the approved Version 1 decisions and awaits Project Manager/Technical Architect review. This status does not claim that application implementation, automated tests, UAT, or deployment are complete.

## Ownership

The repository is a private commercial project. Team responsibilities and review rules are defined in `docs/03_Team_Organization.md` and `docs/07_Git_Workflow.md`.
