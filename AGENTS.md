# Contributor and AI Instructions

## Authority and conflicts

`docs/00_V1_ARCHITECT_DECISIONS.md` is the highest authority for Version 1. When sources conflict, use this order:

1. `docs/00_V1_ARCHITECT_DECISIONS.md`
2. Approved client requirements and Version 1 scope
3. Approved module specification
4. Database migrations and route/API contracts
5. Architecture, security, and coding standards
6. Testing and deployment documentation
7. Sprint and readiness reports
8. Archived or future documents

Report unresolved conflicts instead of guessing. An approved architecture decision may be changed only through a new approved Architecture Decision Record.

Before modifying a module, read the architect decisions, relevant module specification, migrations and database dictionary, route/API contract, folder structure, coding standards, ticket, and acceptance criteria. Stop if the repository is not `C:\TaraGuestHouse`, the ticket's branch is not active, or the working-tree assumptions do not match.

## Approved architecture

Version 1 uses PHP 8.3+, Composer PSR-4 autoloading, a custom layered MVC-inspired architecture, MySQL/MariaDB through PDO, PHP-rendered views, Bootstrap 5, Vanilla JavaScript, PHPUnit, Apache, and cPanel/shared-hosting compatibility.

The required request flow is:

`Route -> Middleware -> Controller -> Request Validation -> Service -> Repository -> Database`

- Controllers coordinate requests and responses and remain lightweight.
- Request classes validate and normalize input.
- Services own business rules, workflows, state transitions, and transaction coordination.
- Repositories and migrations are the only locations for SQL and PDO operations.
- Models/entities represent domain data and contain no SQL or PDO access.
- Views contain presentation logic only and render context-escaped output.
- Migrations are the executable database source of truth.

## Security and private data

Every applicable change must use server-side validation, PDO prepared statements, context-aware output escaping, CSRF protection, hardened PHP sessions, authorization middleware, password hashing, login rate limiting, secure upload validation, randomized filenames, non-executable upload storage, environment-based secrets, HTTPS in production, private error logs, and audit logging.

Never commit credentials, a real `.env`, customer data, session files, logs, caches, backups, temporary files, payment proof files, or real uploads. Confidential payment proofs must be stored in approved private storage and must never be directly public files. Do not log passwords, session identifiers, reset secrets, payment-proof secrets, or review tokens.

## Testing and Version 1 scope

Add positive and negative PHPUnit coverage appropriate to every implemented feature. Critical rules include Administrator session authentication and authorization, CSRF, room publication, availability overlap and concurrency, one-room bookings, booking transitions, payment attempts and verification, invoices, check-in/check-out, verified-stay reviews, upload validation, and `/api/v1/...` contracts. Never claim a test passed unless it was executed successfully.

Version 1 has unauthenticated Guests and authenticated Administrators only. It uses PHP sessions, one room per booking, calculated availability, manual payment verification, PHP-rendered pages, and same-origin versioned JSON endpoints.

Do not invent tables, columns, fields, routes, endpoint payloads, statuses, roles, permissions, business policies, or configuration values. Do not introduce Laravel, Symfony, React, Vue, Angular, JWT, Redis, WebSockets, an ORM, another router or templating framework, unapproved Composer packages, or unapproved infrastructure. Do not implement deferred features such as customer accounts, specialized staff roles, multi-room booking, payment gateways, or additional notification channels.

Keep changes within the assigned ticket. Do not modify unrelated modules or bypass approved layers, validation, authorization, security controls, migrations, tests, or documentation review.
