# Tara Guest House Management System — Version 1 Architect Decisions

## Document Information

| Field | Value |
|---|---|
| Document ID | ARCH-V1-DECISIONS |
| Status | Approved |
| Version | 1.0 |
| Owner | Project Manager and Technical Architect |
| Project | Tara Guest House Management System |
| Effective date | 20 July 2026 |
| Authority | This document overrides conflicting Version 1 statements in other project documents |
| Change rule | Any later change to an approved decision must be recorded through a new Architecture Decision Record before code or documentation is changed |

## 1. Purpose

This document records the final technical and product decisions for Version 1 of the Tara Guest House Management System.

The existing 38 project documents remain valuable and must be preserved. They must be synchronized with this document where contradictions exist. Content clearly marked as a future enhancement may remain in the original documents, but it must not be implemented as part of Version 1.

A modern system does not require unnecessary complexity. For this project, “modern” means:

- secure;
- responsive;
- fast;
- easy for hotel staff and guests to use;
- maintainable by the current team;
- compatible with the selected hosting environment;
- clearly structured;
- testable;
- ready for future expansion without forcing future features into Version 1.

---

# 2. Approved Version 1 Scope

## 2.1 Included

Version 1 includes:

1. Public website:
   - Home
   - About
   - Rooms
   - Room details
   - Availability checking
   - Activities
   - Gallery
   - Reviews
   - Contact
   - Policies and other CMS-managed pages

2. Guest booking:
   - Booking without creating an account
   - One room per booking
   - Guest contact details
   - Booking reference
   - Availability validation
   - Booking confirmation and status management

3. Administration:
   - Administrator login
   - Dashboard
   - Room management
   - Booking management
   - Manual payment verification
   - Invoice generation
   - Activity management
   - Gallery management
   - Review moderation
   - Contact-message management
   - CMS and settings management
   - Basic notification management
   - Audit logging for important administrative actions

4. Quality and operations:
   - Responsive Bootstrap interface
   - Security controls
   - Validation
   - Automated tests for critical business rules
   - cPanel/shared-hosting deployment
   - Database backups and application logging

## 2.2 Deferred to Future Versions

The following are not part of Version 1:

- Customer registration and login
- Customer dashboard or customer portal
- JWT authentication
- Separate mobile application
- Public third-party API
- Online payment-gateway callbacks
- Automatic gateway refunds
- Multiple rooms in one booking
- Multiple hotel branches
- Loyalty and reward programs
- Coupons and promotions
- SMS, WhatsApp and push notifications
- Redis, WebSockets and permanent queue workers
- Docker/Kubernetes production deployment
- Full CMS version-history and rollback
- Advanced housekeeping workflow
- Manager, Accountant, Content Manager and Receptionist permission systems
- Social login and two-factor authentication

---

# 3. Approved Technology Stack

## DEC-001 — Frontend Technology

**Decision: Approved**

The frontend shall use:

- HTML5
- CSS3
- Bootstrap 5
- Vanilla JavaScript ES6+
- Fetch/AJAX only for interactions that benefit from asynchronous updates
- PHP-rendered views

No React, Vue, Angular or single-page-application framework shall be introduced in Version 1.

### Reason

This approach matches the existing documentation, team skills, shared-hosting target and project size. It provides a modern responsive interface without unnecessary frontend complexity.

---

## DEC-002 — Backend Technology and Architecture

**Decision: Approved**

The backend shall use:

- PHP 8.x
- Composer PSR-4 autoloading
- Custom layered MVC-inspired architecture
- Routes
- Middleware
- Controllers
- Request-validation classes
- Services
- Repositories
- Models/entities
- PDO prepared statements
- MySQL or MariaDB

The required flow is:

`Route → Middleware → Controller → Request Validation → Service → Repository → Database`

### Responsibility rules

- Controllers coordinate requests and responses.
- Request classes validate and normalize input.
- Services contain business rules and transactions.
- Repositories contain SQL and persistence logic.
- Models/entities represent domain data.
- Views contain presentation logic only.
- Controllers and views must not contain SQL.
- Repositories must not decide business workflows.

No full PHP framework shall be introduced during Version 1 unless a later approved architecture decision explicitly changes this decision.

---

## DEC-003 — Hosting and Runtime

**Decision: Approved**

The Version 1 production target is:

- Apache
- PHP 8.x
- MariaDB/MySQL
- cPanel/shared hosting
- HTTPS
- Composer installation
- Environment-based configuration

Development may use XAMPP or another compatible local PHP/Apache/MySQL environment.

Ubuntu VPS, Docker, Redis and cloud-native deployment are future alternatives, not Version 1 requirements.

---

# 4. Authentication, Users and Authorization

## DEC-004 — Authentication Strategy

**Decision: Approved**

Version 1 shall use server-side PHP session authentication.

Required controls:

- `password_hash()` and `password_verify()`
- Secure, HTTP-only session cookies
- SameSite cookie configuration
- Session ID regeneration after login
- Session timeout
- Logout with session destruction
- CSRF protection for state-changing requests
- Login rate limiting
- Authorization middleware

JWT access tokens and refresh tokens shall not be implemented in Version 1.

### API authentication

Same-origin protected JSON endpoints shall use the authenticated PHP session and CSRF protection. They shall not require bearer tokens.

---

## DEC-005 — Version 1 Users and Roles

**Decision: Approved**

Version 1 shall have:

1. **Guest**
   - Public visitor
   - Not an authenticated application user
   - May browse content, check availability and submit a booking

2. **Administrator**
   - Authenticated staff user
   - May access the administration panel according to the documented permissions

The `roles` structure may remain future-ready, but only the Administrator staff role is required in Version 1.

Receptionist, Manager, Accountant and Content Manager are deferred.

---

## DEC-006 — Customer Accounts

**Decision: Approved**

Customer registration, customer login and the customer portal are deferred to a future version.

A guest can create a booking without an account.

Version 1 booking records shall store a snapshot of the guest’s required contact information. A future nullable user/customer relationship may be retained in the schema, but Version 1 booking creation must not depend on it.

Password-reset functionality in Version 1 applies only to authenticated staff accounts.

---

# 5. Routing and API Strategy

## DEC-007 — Page Routes and JSON Endpoints

**Decision: Approved**

The application shall use two route categories.

### Server-rendered page routes

Examples:

- `/`
- `/rooms`
- `/rooms/{slug}`
- `/booking`
- `/booking/success`
- `/contact`
- `/admin/login`
- `/admin/dashboard`
- `/admin/rooms`
- `/admin/bookings`
- `/admin/payments`

### Versioned JSON endpoints

All Fetch/AJAX JSON endpoints shall use:

- `/api/v1/...`
- `/api/v1/admin/...` for protected administration endpoints

Examples:

- `GET /api/v1/availability`
- `POST /api/v1/bookings`
- `GET /api/v1/admin/bookings`
- `PATCH /api/v1/admin/bookings/{id}/status`

Unversioned `/api/...` alternatives in existing documents are superseded for Version 1.

### API purpose

The Version 1 API is a same-origin application interface used by the PHP-rendered website and admin panel. It is not a separately supported public API.

---

# 6. Room and Availability Model

## DEC-008 — Room Operational Status

**Decision: Approved**

Room operational status shall use:

- `active`
- `maintenance`
- `inactive`

Meaning:

- `active`: the room may be offered when no blocking booking overlaps the requested dates.
- `maintenance`: the room cannot be booked.
- `inactive`: the room is disabled but retained for history.

The word `available` shall describe a calculated result for a date range, not a permanent room status.

---

## DEC-009 — Room Publication Status

**Decision: Approved**

Public visibility shall be separate from operational status.

Version 1 shall use an `is_published` boolean or equivalent publication field.

A room is shown publicly only when it is:

- operationally `active`; and
- published.

Draft/published/archived content workflows may be used for CMS pages, but they must not be mixed into the room operational-status field.

---

## DEC-010 — Occupancy and Reservation State

**Decision: Approved**

`reserved` and `occupied` shall not be stored as the room’s main operational status.

They are calculated from active booking records and the requested/current dates.

Availability must be calculated using date-overlap logic and blocking booking statuses.

The core overlap rule is:

`existing.check_in < requested.check_out AND existing.check_out > requested.check_in`

Maintenance and inactive rooms are always unavailable.

---

# 7. Booking Architecture

## DEC-011 — Booking Cardinality

**Decision: Approved**

Version 1 permits exactly one room per booking.

The database may retain a `booking_rooms` junction structure for future expansion, but application validation, UI and tests must enforce one room per booking in Version 1.

Multiple-room booking is a future enhancement.

---

## DEC-012 — Booking Statuses

**Decision: Approved**

The canonical Version 1 booking statuses are:

- `pending`
- `confirmed`
- `checked_in`
- `checked_out`
- `cancelled`
- `rejected`

### Allowed transitions

- `pending → confirmed`
- `pending → rejected`
- `pending → cancelled`
- `confirmed → checked_in`
- `confirmed → cancelled`
- `checked_in → checked_out`

The following are terminal statuses:

- `checked_out`
- `cancelled`
- `rejected`

`completed` shall not be a separate Version 1 status. A successfully finished stay is represented by `checked_out`.

`archived` shall not be a workflow status. Historical records remain available through retention and soft-deletion policies.

Payment progress shall be represented by payment statuses, not additional booking statuses.

---

## DEC-013 — Booking and Availability Blocking

**Decision: Approved**

The statuses that block another booking for the same room and overlapping dates are:

- `pending`
- `confirmed`
- `checked_in`

The statuses that do not block availability are:

- `checked_out`
- `cancelled`
- `rejected`

Pending bookings must have a configurable payment or confirmation deadline. Expired pending bookings shall no longer block availability and may be changed to `cancelled` with a recorded cancellation reason.

Availability checks and booking creation must run inside a database transaction to reduce double-booking risk.

---

## DEC-014 — Booking Ownership and Guest Management

**Decision: Approved**

A Version 1 booking belongs to a guest contact record or booking contact snapshot, not to a required authenticated customer account.

At minimum, the booking must store:

- guest full name;
- email;
- phone;
- number of guests;
- check-in date;
- check-out date;
- selected room;
- agreed price snapshot;
- booking reference;
- terms/policy acceptance timestamp.

The public system shall not expose booking details using the booking reference alone. Any public lookup must also verify additional contact information or use a secure signed access token.

---

# 8. Payment Architecture

## DEC-015 — Payment Scope

**Decision: Approved**

Version 1 uses manual payment processing.

Supported payment methods shall be configurable by the administrator and may include bank transfer, digital-wallet transfer recorded manually, or another client-approved method.

Version 1 shall support:

- selection of a configured payment method;
- transaction/reference code;
- payment proof upload where required;
- payment notes;
- administrator verification or rejection;
- payment resubmission;
- manual refund recording where necessary.

Automatic payment-gateway callbacks and gateway-controlled refunds are deferred.

---

## DEC-016 — Payment Cardinality

**Decision: Approved**

One booking may have multiple payment attempts or records.

This supports rejected proof, corrected transaction information and resubmission.

Business rule:

- A booking may have zero or more payment records.
- Only approved business logic may mark a payment as verified.
- Version 1 expects one verified payment covering the required booking amount.
- Duplicate verification must be prevented.
- Payment records must never be created directly by frontend code.

The strict one-booking-to-one-payment rule in conflicting documents is superseded.

---

## DEC-017 — Payment Statuses

**Decision: Approved**

The canonical Version 1 payment statuses are:

- `pending`
- `submitted`
- `verified`
- `rejected`
- `refunded`

### Meaning

- `pending`: payment record created but proof/details not yet submitted.
- `submitted`: guest has submitted payment details or proof.
- `verified`: administrator confirmed the payment.
- `rejected`: submitted payment was not accepted.
- `refunded`: an earlier verified payment was manually recorded as refunded.

### Relationship to booking

- A new booking starts as `pending`.
- Successful payment verification allows the Booking Service to confirm the booking.
- Rejecting one payment attempt does not automatically reject the booking; the guest may resubmit before the booking deadline.
- Only the Booking Service may change the booking status.
- Only the Payment Service may verify or reject a payment.

---

# 9. Check-In, Check-Out and Invoice

## DEC-018 — Check-Out Final State

**Decision: Approved**

A successfully departed guest ends in booking status `checked_out`.

There is no separate Version 1 `completed` status.

---

## DEC-019 — Invoice Rules

**Decision: Approved**

An invoice may be generated after a booking has a verified payment and is confirmed.

The final invoice may be printed or regenerated after checkout when necessary, but the invoice number must remain stable.

Required rules:

- invoice numbers are unique;
- invoice totals come from booking and verified-payment data;
- historical invoice values must not change when room prices are edited later;
- invoice financial calculations use decimal values;
- Version 1 base currency is NPR;
- invoice deletion is prohibited after issue; corrections must be audited.

---

# 10. Reviews

## DEC-020 — Review Eligibility

**Decision: Approved**

Only a guest connected to a `checked_out` booking may submit a review.

Customer login is not required.

The system may use a unique, secure, single-use review token associated with the booking.

Rules:

- one review per booking;
- new reviews start as `pending`;
- administrator approval is required before publication;
- review statuses are `pending`, `approved`, and `rejected`;
- public anonymous reviews not connected to a completed stay are not permitted.

---

# 11. CMS, Gallery and Activities

## DEC-021 — CMS Model

**Decision: Approved**

Use two content approaches:

1. **Settings**
   - site-wide values such as contact information, social links, booking policies and configuration.

2. **Pages/content records**
   - structured public content such as About and policy pages.

CMS page publication statuses are:

- `draft`
- `published`
- `archived`

Full content-version history and rollback are future enhancements.

---

## DEC-022 — Gallery and Activity Publishing

**Decision: Approved**

Gallery items and activities must have separate publication/visibility controls.

Only active and published records appear publicly.

Operational/business status and publication status must not be combined into one ambiguous field.

---

# 12. Notifications and Email

## DEC-023 — Version 1 Notification Channels

**Decision: Approved**

Version 1 includes:

- in-application administrator notifications;
- transactional email through configured SMTP.

Required transactional email events may include:

- booking received;
- booking confirmed;
- booking cancelled or rejected;
- payment submitted;
- payment verified or rejected;
- staff password reset;
- review invitation after checkout.

Email failures must be logged. A failed email must not roll back a successfully committed booking or payment transaction.

SMS, WhatsApp, push notifications and real-time WebSocket delivery are future enhancements.

Permanent background queue workers are not required for Version 1. A cPanel cron-based retry mechanism may be added if supported.

---

# 13. Database Standards

## DEC-024 — Database Source of Truth

**Decision: Approved**

Database migrations are the executable source of truth.

The database dictionary and ER diagram must match the migrations.

`database/schema.sql` may be generated or maintained as an installation snapshot, but it must not contradict migrations.

Seeders shall create required roles, the initial administrator and essential configuration data.

---

## DEC-025 — Status Storage

**Decision: Approved**

Workflow statuses shall be stored as short string values and controlled through PHP constants or equivalent domain definitions.

Do not duplicate different status lists across controllers, views and documents.

Database ENUM types should be avoided where they make future migrations difficult. Validation, service rules and database constraints must work together.

---

## DEC-026 — Financial and Time Data

**Decision: Approved**

- Currency for Version 1 is NPR.
- Financial values use fixed-precision decimal storage; floating-point storage is prohibited.
- Booking prices and invoice values are stored as historical snapshots.
- Application timestamps are stored consistently and displayed using the Asia/Kathmandu timezone.
- All important administrative state changes include `created_at`, `updated_at` and audit information where appropriate.

---

## DEC-027 — Deletion and History

**Decision: Approved**

Records referenced by bookings, payments, invoices or audits must not be physically deleted through normal administration.

Use inactive flags or soft deletion where appropriate.

Important state changes must be recorded in audit logs, including:

- administrator login and security events;
- booking status changes;
- payment verification/rejection/refund;
- invoice issue;
- room and pricing changes;
- CMS publication;
- review approval/rejection;
- user and settings changes.

---

# 14. Security Standards

## DEC-028 — Required Security Controls

**Decision: Approved**

Version 1 requires:

- PDO prepared statements;
- server-side validation;
- context-aware output escaping;
- CSRF protection;
- session hardening;
- authorization middleware;
- password hashing;
- login rate limiting;
- secure upload validation;
- random server-generated filenames;
- allowed MIME-type and extension checks;
- file-size limits;
- uploads that cannot execute PHP;
- environment secrets excluded from Git;
- HTTPS in production;
- security and audit logging;
- generic public error messages and detailed private logs.

No AI-generated code may bypass these controls.

---

# 15. Testing and Quality

## DEC-029 — Testing Strategy

**Decision: Approved**

The project shall use automated tests appropriate to the custom PHP stack.

At minimum, tests must cover:

- administrator login/logout and authorization;
- CSRF and protected routes;
- room CRUD and publication;
- availability overlap logic;
- concurrent/double-booking protection;
- booking creation;
- allowed and forbidden booking-status transitions;
- payment submission and verification;
- invoice generation;
- check-in and checkout;
- verified-stay review eligibility;
- upload validation;
- important API response formats.

A feature is not complete until:

- implementation matches approved documentation;
- validation is present;
- authorization is present;
- critical tests pass;
- code review is complete;
- related documentation is updated.

---

# 16. Documentation Governance

## DEC-030 — Existing 38 Documents

**Decision: Approved**

The 38 existing documents shall be preserved and synchronized. They shall not be discarded merely because inconsistencies were discovered.

Required handling:

- keep useful requirements, workflows and guidance;
- correct statements that conflict with this decision document;
- label future functionality clearly;
- repair filenames, indexes and broken references;
- remove unsupported claims of 100% readiness until synchronization and review are complete;
- retain historical information through Git history;
- do not create a second competing architecture.

---

## DEC-031 — Authority Order

**Decision: Approved**

When project sources conflict, use this order:

1. This Version 1 Architect Decisions document
2. Approved client requirements and Version 1 scope
3. Approved module specification
4. Database migrations and route/API contracts
5. Architecture, security and coding standards
6. Testing and deployment documentation
7. Sprint/readiness reports
8. Archived or future documents

AI tools and developers must report unresolved conflicts instead of silently inventing an answer.

---

## DEC-032 — AI/Codex Development Rule

**Decision: Approved**

Before generating code, an AI coding tool must read:

1. this document;
2. the relevant module specification;
3. relevant migrations/database dictionary;
4. relevant routes/API contract;
5. folder structure and coding standards;
6. the specific ticket and acceptance criteria.

AI tools must not:

- invent columns, statuses, routes or roles;
- introduce a new framework;
- change an approved architecture decision;
- add future functionality to Version 1;
- place SQL in controllers;
- place business logic in views;
- modify unrelated modules without being asked.

When documentation conflicts, the AI must follow this document and report the outdated source.

---

# 17. Approved Implementation Order

Development shall proceed in vertical slices.

## Phase 0 — Documentation Synchronization

- Apply this decision document to the existing 38 documents.
- Correct the documentation index and broken references.
- Create a canonical route map, permission matrix and status glossary.
- Re-run the consistency audit.
- Mark synchronized documents Approved.

## Phase 1 — Foundation

- Composer/autoloading
- Environment configuration
- Front controller
- Router
- Request/response handling
- Database connection
- View renderer
- Sessions
- CSRF
- Error handling
- Logging
- Migration and seeder runner
- Base test setup

## Phase 2 — Administrator Authentication and Rooms

- Initial administrator
- Login/logout
- Authorization middleware
- Admin layout
- Room CRUD
- Images
- Publication
- Public room listing and details

## Phase 3 — Availability and Booking

- Date validation
- Availability service
- Booking transaction
- Unique booking reference
- Guest booking form
- Admin booking management
- Booking state transitions
- Confirmation communication

## Phase 4 — Payments and Invoices

- Configurable manual payment methods
- Proof/reference submission
- Payment verification/rejection
- Booking confirmation
- Invoice generation and printing

## Phase 5 — Operations and Content

- Check-in/check-out
- Activities
- Gallery
- CMS/settings
- Contact messages
- Reviews
- Notifications

## Phase 6 — Dashboard, Hardening and Deployment

- Dashboard metrics
- Audit review
- Security testing
- Responsive/browser testing
- Performance optimization
- Backup and restoration test
- cPanel deployment
- Client UAT

---

# 18. Final Architecture Summary

Version 1 is a secure, server-rendered PHP hotel website and administration system.

It uses:

- PHP 8.x custom layered MVC architecture;
- Bootstrap 5 and vanilla JavaScript;
- selective same-origin Fetch/AJAX endpoints under `/api/v1`;
- PHP sessions instead of JWT;
- guest booking without customer accounts;
- administrator-only authenticated access;
- one room per booking;
- manually verified payments with multiple submission attempts;
- calculated room availability;
- verified-stay reviews;
- SMTP transactional email;
- MySQL/MariaDB migrations;
- cPanel/shared-hosting deployment;
- automated tests for critical workflows.

This architecture is the approved baseline for synchronizing documentation and beginning implementation.