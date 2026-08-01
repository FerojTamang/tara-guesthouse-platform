# Sprint 4 Operations and Content Implementation Plan

## Document Information

| Item | Value |
|---|---|
| Status | Implementation executed locally — S4-019 closure pending |
| Baseline branch | `develop` |
| Baseline commit | `b67df4d` |
| Feature branch | `feat/s4-operations-content` |
| Database baseline | 16 applied migrations, 0 pending |
| Automated baseline | 1,558 tests, 9,631 assertions, 11 skipped, 0 failures |
| Target branch | `develop` |

## Execution Outcome

S4-001 through S4-018 were implemented and pushed on `feat/s4-operations-content`. The implementation baseline before S4-019 is `cdff525 feat(mail): add transactional email delivery`, with 25 applied migrations, 0 pending migrations, and a complete local regression result of 2,118 tests, 13,044 assertions, 14 skipped, and zero failures/errors.

S4-019 synchronizes documentation and prepares the formal local QA record in `45_Sprint_4_QA_Closure_Report.md`. The Sprint is not finally complete until the remaining pull-request, remote CI, review, merge, post-merge verification, and branch-cleanup rules in section 11 pass.

## 1. Purpose

Sprint 4 implements the approved Version 1 **Operations and Content** phase after the merged Payments and Invoices sprint. It delivers activities, gallery, settings and structured CMS pages, contact messages, checked-out-stay reviews, Administrator in-application notifications, and transactional SMTP email.

Check-in and check-out are already implemented through the canonical Booking Service transition endpoint. Sprint 4 may integrate post-checkout review invitation behavior, but it does not introduce a new stay status, a housekeeping state machine, a Receptionist role, or a separate check-in/check-out subsystem.

## 2. Authoritative Sources

Implementation must follow this precedence:

1. `00_V1_ARCHITECT_DECISIONS.md`;
2. approved Version 1 client scope;
3. module specifications `13`, `14`, `15`, `18`, and `20`;
4. executable migrations and `10_Database_Dictionary.md`;
5. `22_System_State_Machine.md`, `23_REST_API_Specification.md`, and `34_UI_UX_Screen_Specification.md`;
6. architecture, security, coding, testing, deployment, and Git workflow documents;
7. this Sprint 4 plan and ticket acceptance criteria.

No ticket may invent routes, columns, statuses, roles, permissions, upload fields, token lifetime, contact workflow vocabulary, retry policy, or other unresolved configuration.

## 3. Approved Scope

### 3.1 Activities

- `activities` migration and fixed-precision NPR model;
- authenticated Administrator list/create/edit and separate active/publication controls;
- deactivate/unpublish removal behavior without hard deletion;
- public PHP page and `GET /api/v1/activities` containing active and published records only;
- mutation audit evidence.

No category, image, featured flag, duration, meeting point, maximum participants, activity booking, package, map, or calendar feature is included.

### 3.2 Gallery

- `gallery_categories` and `gallery` migrations;
- protected category and item management;
- one approved image per item with validated extension, MIME, decodability, size, randomized name, safe relative path, and non-executable storage;
- independent active/publication controls and ordering;
- public PHP page and `GET /api/v1/gallery` containing only eligible items;
- compensation cleanup and audit evidence.

There is no media library, video, 360 media, cloud storage, AI processing, featured field, uploader field, soft-delete timestamp, or authoritative generated-variant table.

### 3.3 Settings and Structured Pages

- `settings` and `pages` migrations;
- allowlisted site-wide settings with typed validation and public/private separation;
- page draft/published/archived workflow;
- authenticated Administrator settings and page screens;
- public published page route and `GET /api/v1/pages/{slug}`;
- homepage composition from approved settings plus eligible Rooms, Activities, Gallery, and Reviews projections;
- contextual escaping, approved content sanitization, SEO fields, and audit evidence.

Secrets, arbitrary setting keys, content scheduling, page deletion, content version history, and rollback are excluded.

### 3.4 Contact Messages

- `contact_messages` migration;
- public PHP contact form and `POST /api/v1/contact` with CSRF, rate limiting, exact allowlisting, validation, PRG, safe responses, and audit/notification integration where approved;
- authenticated Administrator list/detail pages.

The contact status vocabulary, assignment, retention, SLA, and mutation endpoint remain blocked pending an approved decision. Sprint 4 must not guess those values. Until approved, the Administrator workflow is read-only and the unapproved `PATCH /api/v1/admin/contact-messages/{id}/status` route remains absent.

### 3.5 Verified-Stay Reviews

- `review_invitations` and `reviews` migrations;
- secure random invitation token with only a hash stored;
- one invitation and one review per checked-out booking;
- invitation creation after successful checkout without rolling back checkout if later email delivery fails;
- public invitation page, `POST /api/v1/reviews`, and approved-review listing;
- pending-only Administrator moderation to approved/rejected;
- public display of approved reviews only;
- single-use, expiry, rate limiting, safe token handling, and audit evidence.

The invitation lifetime is configuration-required and must not be hardcoded. Issuing fails closed when required configuration is absent. Guest edit/delete, Administrator reply/delete/feature/export, media reviews, and review statistics are excluded.

### 3.6 Administrator Notifications

- `notifications` migration;
- Administrator-only in-application notification records;
- unread count and paginated list in the PHP-rendered interface;
- exact APIs:
  - `GET /api/v1/admin/notifications`;
  - `PATCH /api/v1/admin/notifications/{id}/read`;
  - `PATCH /api/v1/admin/notifications/read-all`;
- ownership enforcement, CSRF on mutations, no delete/archive route, and tests.

Guests receive no notification centre, notification table ownership, or notification API.

### 3.7 Transactional SMTP Email

- environment-driven SMTP configuration using the approved existing mail contract;
- safe templates for approved booking, payment, and review-invitation events;
- sending occurs only after the owning domain transaction commits;
- email failure is safely logged and never rolls back committed domain state;
- secrets, raw review tokens, session identifiers, and private paths are excluded from logs;
- host/deployment verification is documented.

Permanent queue workers, SMS, WhatsApp, push, WebSockets, and hardcoded retry/backoff/retention values are excluded. A retry or cron workflow may be implemented only after its configuration is approved.

## 4. Decision Gate Register

The following values are intentionally not invented by this plan. The named ticket cannot begin until the decision is recorded in synchronized documentation or represented as required environment configuration with fail-closed behavior:

| Gate | Required before | Rule |
|---|---|---|
| Exact public/private settings key allowlist and value validation | S4-009 | Settings are not an arbitrary key/value editor. |
| CMS body sanitization/allowed-format contract | S4-010 | No unapproved rich-text HTML or new sanitization dependency may be introduced. |
| Gallery upload extensions, MIME types, byte limit, dimensions/decodability policy | S4-006 | Values come from approved configuration; absence fails closed. |
| Contact-message status vocabulary/default, retention, assignment and SLA policy | S4-012 mutation work | Without approval, Sprint 4 implements submission and Administrator read-only list/detail only; no status mutation route is registered. |
| Review invitation lifetime | S4-013/S4-014 | Configured value is required; no hardcoded lifetime. |
| Review rejection moderation-note rule and input limits | S4-016 | Approval/rejection validation must follow an explicit policy. |
| SMTP retry count/backoff/retention and cron schedule | Any retry extension after S4-018 | Immediate post-commit send and safe failure logging may proceed; retry/cron remains absent until configured. |

A blocked gate does not authorize a reduced-security shortcut. The ticket is split or paused, and already approved independent work may continue.

## 5. Explicitly Excluded Scope

Sprint 4 must not add:

- customer accounts or a Customer Portal;
- Receptionist, Manager, Accountant, Content Manager, or Housekeeping roles;
- advanced housekeeping/cleaning room states;
- multi-room booking;
- payment gateways or refund implementation;
- invoice correction/credit-note behavior;
- activity booking;
- gallery media library/video/cloud processing;
- review replies, edits, deletes, media, exports, featuring, or analytics;
- contact assignment/status/SLA behavior without an approved policy;
- notification archive/delete or Guest notification inbox;
- dashboard completion, audit-log administration, backup/restore UI, UAT, or production deployment, which remain Phase 6 work.

## 6. Incremental GitHub Delivery Workflow

Sprint 4 uses one feature branch and one early Draft PR, but every approved ticket is committed and pushed immediately after its focused and full tests pass.

### Required sequence for every ticket

1. Start from synchronized `feat/s4-operations-content`.
2. Apply only the reviewed ticket patch.
3. Run `git diff --check`.
4. Run the ticket-focused PHPUnit suite.
5. Run `composer test` with Composer timeout disabled where required.
6. Apply and verify ticket migrations where applicable.
7. Stage only the exact ticket files.
8. Review `git diff --cached --check` and `git diff --cached --name-only`.
9. Commit with the approved ticket message.
10. Push immediately to `origin/feat/s4-operations-content`.
11. Confirm local and remote branch synchronization before starting the next ticket.

A Draft PR targeting `develop` is opened after S4-001 and remains open throughout Sprint 4. Each pushed ticket updates that PR, enabling continuous GitHub review and CI instead of one end-of-sprint upload.

GitHub profile commit credit depends on the commit author email being connected to the GitHub account and on the commits reaching the repository default branch. The Draft PR itself records visible repository activity while the feature branch is in progress.

## 7. Ticket Sequence

### S4-001 — Sprint 4 scope, decision gates, and delivery workflow

- add this implementation plan;
- update the documentation index and README status;
- verify Git author identity and branch baseline;
- create and push `feat/s4-operations-content`;
- open the Draft PR to `develop`.

Commit: `docs(sprint): approve operations and content plan`

### S4-002 — Activities database and domain foundation

- add `activities` migration;
- add model, repository contracts, PDO implementation, service foundation, fixtures, and migration/repository tests;
- enforce unique slug, NPR decimal rules, separate active/publication flags, sort order, and audit-ready writes.

Commit: `feat(activities): add activity data foundation`

### S4-003 — Administrator activity management

- add protected PHP list/create/edit flows;
- add exact Administrator JSON mutations;
- implement independent active/publication transitions and deactivate/unpublish removal;
- add CSRF, validation, audit, feature, and service tests.

Commit: `feat(activities): add administrator management`

### S4-004 — Public activities experience

- add public activities page and `GET /api/v1/activities`;
- expose only active and published records in approved order;
- add navigation, responsive Bootstrap presentation, escaping, caching policy, and feature tests.

Commit: `feat(activities): add public activity pages`

### S4-005 — Gallery category and item database foundation

- add `gallery_categories` and `gallery` migrations;
- add models/repositories/services and migration/repository tests;
- enforce category eligibility, safe relative paths, ordering, and independent active/publication state.

Commit: `feat(gallery): add gallery data foundation`

### S4-006 — Secure gallery image storage

- add configured image validation, randomized filenames, decodability checks, non-executable storage, cleanup/compensation, and provider wiring;
- add upload security, filesystem, configuration, and failure-path tests.

Commit: `feat(gallery): add secure image storage`

### S4-007 — Administrator gallery management

- add category and gallery item PHP screens;
- add exact protected category/item APIs, metadata updates, active/publication controls, reorder, and deactivate/unpublish removal;
- add audit and feature/integration tests.

Commit: `feat(gallery): add administrator management`

### S4-008 — Public gallery experience

- add public gallery page and `GET /api/v1/gallery`;
- return only active/published items with eligible active categories;
- add responsive accessible gallery presentation and tests.

Commit: `feat(gallery): add public gallery pages`

### S4-009 — Settings and pages database foundation

- add `settings` and `pages` migrations;
- add typed models/repositories/services;
- define an exact setting-key allowlist from approved public configuration;
- implement draft/published/archived transition rules and audit-ready writes;
- add migration, repository, and service tests.

Commit: `feat(cms): add settings and page foundation`

### S4-010 — Administrator CMS and settings management

- add protected settings and page list/create/edit/detail screens;
- add exact settings/page APIs;
- enforce typed validation, public/private separation, approved content sanitization, status workflow, actor attribution, and audit records;
- add feature and integration tests.

Commit: `feat(cms): add administrator content management`

### S4-011 — Public CMS pages and homepage composition

- add published-page lookup and `GET /api/v1/pages/{slug}`;
- compose the public homepage from approved settings and eligible module projections;
- add public navigation/footer/contact data, SEO output, safe not-found behavior, responsive UI, and tests.

Commit: `feat(cms): add public content pages`

### S4-012 — Contact-message submission and Administrator read workflow

- add `contact_messages` migration;
- add public contact page and `POST /api/v1/contact` with CSRF, rate limiting, validation, PRG, and safe responses;
- add protected Administrator list/detail pages;
- keep status/assignment mutation absent until policy approval;
- add migration, repository, security, feature, and retention-boundary tests.

Commit: `feat(contact): add inquiry workflow`

### S4-013 — Review invitation and review database foundation

- add `review_invitations` and `reviews` migrations;
- add secure token hashing, configurable expiry, one-per-booking constraints, models/repositories/services, and audit-ready writes;
- add migration, repository, token, expiry, uniqueness, and transaction tests.

Commit: `feat(reviews): add verified stay foundation`

### S4-014 — Checkout review-invitation coordination

- coordinate checked-out transition with idempotent invitation creation through Review Service;
- preserve checkout when post-commit notification/email delivery fails;
- prevent invitations for non-checked-out bookings and duplicate invitations;
- add service/integration/feature tests.

Commit: `feat(reviews): create checkout invitations`

### S4-015 — Public reviews and invitation submission

- add `/reviews`, `/review/{token}`, `GET /api/v1/reviews`, and `POST /api/v1/reviews`;
- validate token hash, expiry, unused state, checked-out booking, uniqueness, rating, title, and comment;
- store one pending review and consume the invitation transactionally;
- add rate limiting, no-store token pages, escaping, and tests.

Commit: `feat(reviews): add guest review workflow`

### S4-016 — Administrator review moderation

- add protected review list/detail screens and exact review APIs;
- permit pending-only approval/rejection with approved moderation-note rules;
- retain moderator/time/note and append audit evidence;
- expose only approved reviews publicly;
- add feature, service, repository, and security tests.

Commit: `feat(reviews): add administrator moderation`

### S4-017 — Administrator in-app notifications

- add `notifications` migration, model, repository, and service;
- add unread count, list page, ownership-safe read-one/read-all endpoints, header integration, and tests;
- prohibit Guest notification APIs and delete/archive behavior.

Commit: `feat(notifications): add administrator inbox`

### S4-018 — Transactional SMTP delivery

- add SMTP transport/service/provider and safe templates;
- integrate approved booking, payment, and review-invitation email events after commit;
- ensure failures are sanitized, logged, and non-transactional;
- add configuration, transport-double, template, event, and failure-path tests.

Commit: `feat(mail): add transactional email delivery`

### S4-019 — Sprint 4 QA and documentation closure

- run the complete automated suite and database status;
- audit routes, migrations, uploads, tokens, SMTP secrets, public eligibility filters, audit behavior, and absent future features;
- synchronize README, database/ERD/dictionary, API/UI, user/developer/deployment/testing/traceability documents;
- add the Sprint 4 QA closure report;
- convert the Draft PR to Ready for review only after all closure gates pass.

Commit: `docs(sprint): close operations and content`

## 8. Migration Plan

The provisional migration order is:

1. `activities`;
2. `gallery_categories`;
3. `gallery`;
4. `settings`;
5. `pages`;
6. `contact_messages`;
7. `review_invitations`;
8. `reviews`;
9. `notifications`.

Exact timestamped filenames are assigned per ticket. Migrations are executable truth, use foreign keys/indexes/check constraints supported by the project, and receive isolated apply/rollback/reapply tests. Production rollback remains prohibited.

Expected migration count after the approved Sprint 4 database tickets: **25 applied migrations, 0 pending**.

## 9. Security Requirements

Every applicable ticket must prove:

- Administrator session authorization before protected behavior;
- CSRF before state mutation;
- exact request-field allowlisting and server-side validation;
- PDO prepared statements only in repositories/migrations;
- contextual output escaping;
- public rate limiting and safe error responses;
- random upload/token names and no client-controlled authoritative paths;
- non-executable upload storage;
- raw review tokens, SMTP secrets, session identifiers, and private paths absent from logs;
- public eligibility filters at repository/service boundaries;
- append-only audit evidence for Administrator mutations;
- committed domain state is not rolled back by email failure;
- no unapproved routes, statuses, roles, fields, or future behavior.

## 10. Test and Quality Gates

Each ticket requires focused unit, integration, feature, and negative tests appropriate to its scope plus the full PHPUnit suite before commit. Database tickets additionally require migration status and isolated rollback/reapplication coverage.

Sprint closure additionally requires:

- all migrations applied with zero pending;
- `git diff --check` clean;
- PHP syntax checks clean;
- complete PHPUnit suite with zero failures/errors;
- route-contract inventory;
- private-artifact, credential, token, and upload-path scans;
- public active/published/approved eligibility verification;
- local Markdown link and documentation consistency checks;
- browser/responsive/accessibility evidence for new screens where locally possible;
- remote CI success and review approval.

## 11. Completion Rule

Sprint 4 is complete only when all S4-001 through S4-019 tickets are committed and pushed, the Draft PR has been converted to Ready, remote CI succeeds, the PR targets `develop`, review is approved, the PR is merged, post-merge database/test checks pass on `develop`, and the local/remote Sprint 4 feature branch is removed.

Sprint 4 never merges directly into `main`.
