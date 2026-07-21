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
| Database | MariaDB/MySQL; migrations are executable source of truth |
| Authentication | Hardened server-side PHP sessions and CSRF protection |
| Routes | Normal page routes plus `/api/v1/...` same-origin JSON endpoints |
| Web server | Apache |
| Production | HTTPS on cPanel/shared hosting |
| Development | XAMPP or another compatible PHP/Apache/MySQL environment |

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

Run the genuine core test suite with:

```bash
composer test
```

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
