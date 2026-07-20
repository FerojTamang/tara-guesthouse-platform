# Tara Guest House Project Implementation Plan

## Document Information

* Document number: 32
* Status: Synchronized — Review Required
* Scope: Approved Version 1 Phase 0–6 implementation order

## 1. Purpose

This plan defines the approved delivery order, vertical-slice method, responsibilities, gates and deliverables for a secure, maintainable, production-ready Tara Guest House application.

## 2. Version 1 Scope

Core Version 1 modules include:

* Administrator authentication and accounts;
* room types, rooms, amenities, publication and calculated availability;
* Guest one-room bookings and booking transitions;
* Payment Verification and Booking Confirmation through Booking Service;
* invoices;
* activities, gallery, settings and structured CMS pages;
* verified-stay reviews, contacts and notifications;
* dashboard projections, audit, backups, testing and cPanel deployment.

Customer accounts/portal, specialized staff roles, multi-room booking, payment gateways, additional notification channels, advanced infrastructure and other documented Future Enhancements are excluded.

## 3. Vertical-Slice Delivery Method

Each feature is implemented end to end as a vertical slice. A slice includes all applicable:

1. database migration and approved seed/update;
2. backend request/controller/service/repository/entity behavior;
3. PHP-rendered frontend and selective Fetch/AJAX behavior;
4. server/client validation;
5. authorization, CSRF, escaping, prepared statements, upload/session and other security controls;
6. automated unit, integration, feature and negative tests;
7. documentation and traceability update.

A slice is not complete when only its backend or frontend exists. Testing/security/documentation are performed within each slice, with regression and acceptance gates across phases.

Services own business rules/transactions. Repositories own PDO/SQL. Models/entities contain domain data and execute no SQL.

## 4. Approved Phase Order

Only the following Phase 0–6 order is active.

### Phase 0 — Documentation Synchronization

Deliverables:

* approved architect decisions;
* synchronized detailed requirements, schema, states, API, UI and deployment contracts;
* documented unresolved decisions;
* traceability baseline and reviewed Git diff.

Exit gate: Project Manager and Technical Architect approve the implementation baseline or record remaining blocked areas.

### Phase 1 — Foundation

Vertical slices:

* repository/folder bootstrap and environment configuration;
* Composer autoloading and shared framework/core flow;
* migration runner, database connection and domain constants;
* error handling/logging, response/view helpers and security foundations;
* baseline automated test harness and CI/local commands.

Exit gate: foundation runs in the approved local environment; migrations/test harness and security defaults pass review.

### Phase 2 — Administrator Authentication and Rooms

Vertical slices:

* Administrator role/user/password reset and PHP-session authentication;
* protected page/API middleware and CSRF;
* room types, rooms, amenities and room images;
* separate operational/publication controls;
* calculated room eligibility foundations.

Exit gate: only Administrator authenticates; room CRUD/publication/image/security tests pass; no stored Available/Reserved/Occupied status exists.

### Phase 3 — Availability and Booking

Vertical slices:

* date-range availability and concurrency protection;
* Guest and Administrator one-room booking creation;
* Guest contact/policy/financial snapshots and non-sequential references;
* booking deadlines and append-only events;
* reject/cancel/check-in/check-out transitions.

Exit gate: booking starts `pending`, creates zero payment attempts, prevents overlaps, allows adjacent checkout/check-in dates and retains history.

### Phase 4 — Payments and Invoices

Vertical slices:

* configured manual payment methods;
* secure Guest payment submission and multiple attempts;
* Administrator verification/rejection;
* Payment Verification and Booking Confirmation through Booking Service;
* invoice issue/print from fixed-precision snapshots.

Refund implementation remains excluded until its policy/data/API/UI decision. Tax/service-charge/rounding-dependent invoice behavior remains gated where unresolved.

Exit gate: payment verification cannot be bypassed to confirm a booking; duplicate verification is blocked; invoice snapshot tests pass for approved policy.

### Phase 5 — Operations and Content

Vertical slices:

* activities and gallery with separate active/publication controls;
* settings and structured CMS pages;
* contacts;
* checked-out review invitations, submission and moderation;
* Administrator in-app notifications and Guest transactional SMTP email;
* associated uploads/audit behavior.

Exit gate: public content uses only published eligible records; reviews require single-use checked-out invitations; Guests have no in-app notification centre.

### Phase 6 — Dashboard, Hardening and Deployment

Vertical slices/gates:

* read-only Administrator dashboard projections;
* cross-module regression, security, performance, accessibility/browser and UAT testing;
* audit/log/backup/restore operational verification;
* cPanel/shared-hosting release, migrations, HTTPS, permissions, SMTP and cron checks;
* final documentation/traceability/release notes and acceptance evidence.

Exit gate: approved acceptance tests pass, unresolved blocked functions remain absent, rollback/backup evidence exists, and stakeholders approve release.

## 5. Milestones

| Milestone | Completion evidence |
|---|---|
| Documentation baseline approved | Reviewed Phase 0 documents/decisions |
| Foundation accepted | Running migrations, test harness and architecture review |
| Authentication/rooms accepted | Phase 2 slice tests and demo |
| Booking accepted | Phase 3 availability/concurrency/state evidence |
| Payments/invoices accepted | Phase 4 verification/confirmation/invoice evidence |
| Operations/content accepted | Phase 5 publication/review/notification evidence |
| Production release accepted | Phase 6 UAT/security/deployment/rollback evidence |

Milestones are not marked complete merely because documentation exists. Status must reference implementation and passing test evidence.

## 6. Team Responsibilities

| Project responsibility | Owner duties |
|---|---|
| Project Manager | Scope, priorities, client decisions, phase gates and acceptance |
| Technical Architect | Architecture/data/API/security/deployment decisions and reviews |
| Backend development | Migrations, Services, Repositories, endpoints and integration tests within each slice |
| Frontend development | PHP views, Bootstrap/Vanilla interactions, accessibility and UI tests within each slice |
| Quality assurance | Acceptance criteria, automated/manual/regression/security evidence within each slice |

Team job titles do not create application roles.

## 7. Slice Definition of Done

A feature slice is complete only when:

* its approved migration/schema change is reviewed and reversible/recoverable;
* backend and frontend behavior agree with the API/UI contract;
* validation/security/authorization/transaction rules are implemented;
* automated positive and negative tests pass;
* accessibility/error/empty/loading behavior is verified where applicable;
* documentation and traceability identify the implementation and tests;
* no future feature or unresolved policy was guessed;
* responsible reviewers approve the slice.

## 8. Risk Management

| Risk | Impact | Mitigation |
|---|---|---|
| Unresolved client policy | Incorrect implementation | Gate affected slice; obtain recorded decision |
| Contract drift | Rework/defects | Update database/API/UI/tests/docs together in the slice |
| Double booking | High | Transactional overlap checks and concurrency tests |
| Security vulnerability | High | Threat-aware review and negative tests per slice |
| Shared-host limitation | Deployment failure | Confirm cPanel capabilities before dependent work |
| Schedule/resource pressure | Quality loss | Slice prioritization; do not defer tests/security/documentation |
| AI-generated scope drift | Unsupported architecture | Enforce DEC-032 human review and exact contracts |

## 9. Communication and Change Control

Weekly team/project updates report slice status, tests, decisions and risks. Client/architect decisions that change scope/data/API/UI require controlled documentation updates before implementation. Phase-gate evidence is reviewed with the Project Manager and Technical Architect.

## 10. Deliverables

Technical deliverables include reviewed source, migrations, configuration templates, automated tests and cPanel release artifacts. Documentation deliverables include requirements, architecture, dictionary/ERD, state/API/UI specifications, test/deployment/user/developer/maintenance guidance, traceability and acceptance/release reports.

## 11. Success Criteria

The project is successful when approved Version 1 requirements are implemented as tested vertical slices; critical security/functional defects are resolved; UAT is approved; backup/rollback and cPanel deployment are verified; and final documentation accurately matches the release.

Targets such as availability, response time or load time must be measured in an approved environment and cannot be marked achieved from documentation alone.

## 12. Future Roadmap

Later approved versions may add customer accounts/portal, specialized staff roles, multi-room bookings, gateways/refunds, additional channels, multi-language/mobile/multi-hotel features and VPS/cloud infrastructure. They are not inserted into Phase 0–6 Version 1 slices without a new decision.

## 13. Approval

| Role | Status |
|---|---|
| Project Manager | Review Required |
| Technical Architect | Review Required |
| Development/QA team | Pending approved phase/slice assignment |
