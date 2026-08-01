# Sprint 5 Dashboard, Hardening, and Deployment Implementation Plan

## Document information

| Item | Value |
|---|---|
| Status | Proposed implementation plan — approval required |
| Baseline branch | `develop` |
| Feature branch | `feat/s5-dashboard-hardening-deployment` |
| Pull-request target | `develop` (never `main`) |
| Verified Sprint 4 baseline | 2,118 tests, 13,044 assertions, 14 skipped, 0 failures; 25 applied migrations, 0 pending |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |

## 1. Objective and boundaries

Sprint 5 completes the approved Version 1 operational-readiness phase: read-only Administrator dashboard projections, evidence-based hardening, backup/restore rehearsal, performance review, accessibility/browser QA, UAT, cPanel preparation, and closure evidence. It does not authorize customer accounts, new roles, payment gateways, multi-room booking, new notification channels, speculative infrastructure, or production deployment without external approval.

The executable Sprint 4 state is the starting point: 25 migrations in `database/migrations`, protected `GET /admin/dashboard` landing page, audit evidence written internally, and no registered dashboard JSON, audit-log browser, or backup endpoint. This plan does not describe any unregistered route as active.

## 2. Sequence, acceptance, and dependencies

| Ticket | Scope / acceptance criteria | Dependencies / likely areas | Verification and required documentation |
|---|---|---|---|
| S5-001 | Approve this plan and acceptance matrix; record gates and evidence ownership. | `docs/`, README/index only. | Link/path review, migration/test-baseline reconciliation. |
| S5-002 | Add bounded, read-only repository projections from authoritative tables; timezone/date-boundary behavior explicit; no invented revenue recognition. | repositories, services, tests; migrations only if evidence later justifies. | Unit/integration query tests, representative data and query-plan evidence. |
| S5-003 | Replace landing page with accessible protected dashboard UI using S5-002 projections; no mutation controls or unnecessary Guest data. | controller/views/layout, routes only if approved contract requires. | Feature authorization/no-store/escaping tests; keyboard/responsive manual check. |
| S5-004 | Audit trail and security review of existing append-only evidence and sensitive workflows; add only evidence-supported remediation. | audit repositories/services/tests/docs. | Negative security tests and review record; no claim of penetration test. |
| S5-005 | Review and harden authentication, sessions, CSRF, ownership, HTTP headers, uploads, tokens, secrets, logging and errors without weakening fail-closed controls. | core, middleware, config, tests. | Unit/feature security regressions; production config validation. |
| S5-006 | Define safe cPanel-compatible backup/restore tooling and rehearsal procedure without secrets in history/logs; restore verification mandatory. | `bin/`, docs, deployment configuration only after approval. | Test-safe command validation; manual isolated restore evidence required. |
| S5-007 | Capture query evidence and execution plans; add indexes only with migration-safe justification and rollback analysis. | repositories, migrations, database tests. | Before/after plan evidence and migration test; no speculative index. |
| S5-008 | Complete responsive, keyboard, focus, semantics, labels, contrast and browser QA. | views/assets/tests/docs. | Automated checks where available plus documented manual browser matrix; no unsupported WCAG claim. |
| S5-009 | Create reproducible UAT scenarios using test data for public and Administrator room, booking, payment, invoice, activity, gallery, CMS, contact, review, notification and mail flows. | docs/test data only unless defects found. | Signed scenario evidence, expected results, screenshots/records without private data. |
| S5-010 | Prepare cPanel deployment runbook: PHP/MariaDB compatibility, document root, writable private/public paths, environment, migrations, seed/bootstrap, SMTP, cron limits, smoke and rollback. | docs/config validation; no real secrets. | Staging/cPanel checklist and owner sign-off. |
| S5-011 | Production-readiness and rollback verification against actual staging/cPanel evidence; production remains gated. | docs/operational evidence. | Restore, rollback, smoke, ownership and approval records; never claim production ready without them. |
| S5-012 | Run closure QA, reconcile documentation/traceability, capture CI and external evidence, and issue closure verdict. | docs, tests, CI evidence. | Complete suite, link checks, PR/CI/staging/UAT evidence, final approvals. |

No ticket is merged, split, renamed, reordered, or removed: repository evidence supports the proposed 12-ticket order. S5-002 precedes the dashboard UI; audit/security precede deployment approval; backup/performance evidence precedes final readiness.

### Per-ticket delivery matrix

| Ticket | Acceptance / dependency | Expected areas | Local automated evidence | Manual evidence | Documentation / gate |
|---|---|---|---|---|---|
| S5-001 | Plan accepts all scope, boundaries, evidence types and sequence; precedes every other ticket. | `docs/46_*`, `docs/33_*`. | diff/link/path/migration-baseline checks. | PM/Architect plan review. | Index entry; approval to begin S5-002. |
| S5-002 | Metrics are read-only, bounded, authoritative and timezone-defined; depends on S5-001 and approved financial semantics. | dashboard repositories/services/models/tests; existing `bookings`, `payments`, `invoices`, `rooms`, `reviews`, `notifications`, `audit_logs`. | unit mappings, integration query/filter/boundary tests, measured explain evidence. | Administrator compares test projections with source records. | Metric definitions and query evidence; Architect/Client financial/date gate. |
| S5-003 | Protected PHP dashboard displays only S5-002 data, has no mutation path, escapes output and stays usable without a JSON endpoint unless separately approved. | controller, views/layout/assets, `routes/admin.php`, feature tests. | authorization, no-store, escaping, empty/error-state feature tests. | keyboard/focus, desktop/mobile rendering, privacy review. | UI notes/screenshots; S5-002 accepted first. |
| S5-004 | Existing audit evidence is append-only, ownership-safe and secret-safe; remediation is evidence-based. | audit migration/repository/services, logging, tests, docs. | negative audit/logging/tamper regressions. | reviewer traces sample events across workflows. | Security review and residual-risk record; no penetration-test claim. |
| S5-005 | Session/auth/CSRF/ownership/headers/uploads/tokens/secrets/errors stay fail-closed and backward compatible. | core, middleware, authentication, upload storage, config, tests. | unit/integration/feature attack-regression tests and production-config tests. | session expiry/login/logout/header checks in browser. | Hardening change record; security owner review. |
| S5-006 | Backup tooling avoids secret disclosure and restore procedure proves integrity in isolated environment; depends on hosting policy. | safe CLI/runbook/docs and ignored runtime backup location, if approved. | command/input validation tests only; no destructive restore on dev data. | operator performs test-data backup and isolated restore. | Backup/restore record; hosting/operator retention and access gate. |
| S5-007 | Indexes only follow measured query evidence and have migration/rollback safety; depends on S5-002/S5-006 evidence where relevant. | repositories, migrations, database tests, query documentation. | migration/index tests and before/after plans. | staging representative-load observation. | Query-plan evidence and technical approval; no speculative index. |
| S5-008 | Responsive, semantic, labelled, keyboard/focus/contrast behavior is documented; depends on implemented UI. | views/assets/JS/tests/docs. | applicable markup/feature regressions. | browser/device/assistive-technology matrix. | QA record; no WCAG conformance claim without evidence. |
| S5-009 | Reproducible test-data UAT covers public/Admin room, booking, payment, invoice, activity, gallery, CMS, contact, review, notification and mail workflows. | UAT docs and test fixtures only unless a defect is found. | scenario precondition/data setup validation where practical. | product/UAT execution and sanitized evidence capture. | Signed UAT outcomes; product-owner gate. |
| S5-010 | cPanel runbook covers compatible runtime, `public/` root, writable/private paths, env, migrations, seed/bootstrap, SMTP, cron, smoke and rollback. | deployment/maintenance docs, config validation only. | documentation/configuration checks. | cPanel/staging deployment checklist. | Hosting confirmation; no real credential in repository. |
| S5-011 | Readiness verdict follows actual S5-006/S5-008/S5-009/S5-010 evidence; production remains blocked absent approval. | operational docs/evidence. | final local configuration and smoke-script checks. | staging restore/rollback/smoke and access verification. | Architect/QA/product/operations production decision gate. |
| S5-012 | Closure reconciles all ticket evidence, docs, traceability, CI and known risks; depends on S5-001–011. | docs, QA reports, CI metadata. | complete PHPUnit suite when code changed, diff/link/migration checks. | closure review and external-evidence audit. | Closure report, PR review and final approval. |

## 3. Security, database, and deployment rules

### S5-002 implemented metric contract

S5-002 provides aggregate-only, Administrator-scoped metrics: active non-deleted rooms; active rooms with a `checked_in` booking-room association; calculated available active rooms (`active - occupied`); local-day arrivals/departures; non-deleted `pending` and `confirmed` bookings; `submitted` payment attempts awaiting review; `pending` reviews awaiting moderation; and unread notifications for the current Administrator only. No Guest fields or list records are returned. A reporting day is Asia/Kathmandu midnight inclusive through the following local midnight exclusive; the service carries those UTC instants and the repository converts them back to local date parameters for DATE columns. Financial/revenue, tax, refund, collected, invoiced, and verified-amount metrics are intentionally omitted pending owner/architect approval. Existing indexes include booking date/status, payment status, review moderation, and notification user/unread indexes; S5-002 adds no index or migration pending measured EXPLAIN evidence.

S5-002 verification evidence: `bookings.check_in` and `bookings.check_out` are DATE columns, so they represent the Kathmandu reporting date rather than UTC instants; the UTC range `[2026-07-31 18:15:00, 2026-08-01 18:15:00)` maps to local DATE parameters `[2026-08-01, 2026-08-02)`. MariaDB EXPLAIN on the local test database selected the existing notification unread, review public, payment status, booking status, booking-room unique, room primary-key, and booking date-range indexes where applicable. The active-room and departure subqueries reported `ALL` for one-row fixture tables; no temporary table or filesort appeared. This is not representative production-scale evidence and does not justify an index or migration; S5-007 remains the performance-validation gate. Local S5-002 evidence consists of aggregate-only model/service/provider tests, MariaDB repository integration tests, and focused existing domain regressions. No dashboard UI or JSON endpoint is implemented by this ticket.

S5-003 adds the protected, PHP-rendered `GET /admin/dashboard` overview. It consumes only the S5-002 aggregate metrics through lazy Administrator Bootstrap wiring and the authenticated session Administrator identity. The interface groups room status, today's operations, booking overview, and attention-required counts; valid zeroes remain visible. It has no financial metrics, Guest data, mutation behavior, or dashboard JSON endpoint. Protected responses are no-store; metrics failures render the established generic protected-page error rather than fabricated zeroes. Local feature and Bootstrap verification cover route protection, ownership isolation, generic failure sanitization, semantic headings, navigation state, and aggregate-only rendering. Responsive/accessibility foundations use the existing Bootstrap grid and semantic headings; formal browser, assistive-technology, and cross-browser certification remains deferred to S5-008.

S5-004 evidence confirms the existing audit design is append-only: `AuditLogWriteRepository` exposes `append()` only, `PdoAuditLogRepository` performs INSERT-only writes inside a caller-owned transaction, and failures are sanitized. The audited event/entity vocabulary is canonical lowercase snake_case and covers activity, booking status, payment review/proof access, invoice issuance, gallery, CMS/settings, contact submission, review invitation/submission/moderation, and verified-payment booking confirmation workflows. Metadata is JSON-object data limited to identifiers, status transitions, and bounded operational facts; focused tests cover malformed metadata, UTC timestamps, transaction requirement, and persistence. No audit UI/API/export, update/delete operation, migration, index, or production audit defect was introduced. `invoice_number` in invoice audit metadata remains an explicit Architect/privacy/retention decision; bounded IP/user-agent context is validated from direct request values, with no trusted-proxy/X-Forwarded-For policy (deferred to S5-005/S5-010). Database audit evidence remains distinct from security/application logs, notifications, and post-commit email logs.

- All dashboard reads remain Administrator-only, read-only, bounded/paginated where lists are needed, and derived from authoritative repositories. Reporting display uses `Asia/Kathmandu`; financial recognition, tax inclusion, and exact reporting boundaries remain Architect/Client approval gates.
- Preserve sessions, CSRF, authorization, ownership isolation, parameterized SQL, output escaping, upload validation, private proof storage, randomized filenames, token hashing, no-store behavior, and sanitized logging/errors.
- Migrations are the executable schema source. S5 adds no migration unless a reviewed query plan proves an index/schema change necessary; migration rollback and data safety must be tested.
- Backups/restores must use protected environment/cPanel facilities and test data where practical. Credentials, tokens, private paths, Guest data, and command secrets are never committed or logged. Restore success is an external manual gate.
- cPanel/staging deployment requires HTTPS, `public/` document root, correct writable paths, private logs/uploads/proofs, environment validation, migrations, production seed/bootstrap sequence, SMTP verification, cron capability confirmation, rollback plan, and smoke evidence.

## 4. Sprint-level acceptance matrix

| Ticket | Local automated evidence | Manual browser/UAT evidence | GitHub CI evidence | cPanel/staging evidence | Production approval evidence |
|---|---|---|---|---|---|
| S5-001 | document/link/baseline checks | plan review | PR documentation checks | N/A | N/A |
| S5-002 | query and boundary tests | source-record comparison | required suite | representative-data check | metric-semantics approval |
| S5-003 | feature/security tests | responsive/keyboard/privacy | required suite | dashboard smoke | owner acceptance |
| S5-004 | audit/security regressions | event trace review | required suite | log review | residual-risk acceptance |
| S5-005 | hardening regressions | session/header checks | required suite | HTTPS/config checks | security approval |
| S5-006 | safe tooling tests | isolated restore | required suite | backup/restore rehearsal | operations approval |
| S5-007 | plan/migration tests | representative load | required suite | query-plan validation | technical approval |
| S5-008 | applicable UI tests | browser/device/assistive QA | required suite | staging walkthrough | QA sign-off |
| S5-009 | scenario setup checks | UAT scenarios | required suite | staging UAT | product sign-off |
| S5-010 | config/doc checks | deployment runbook | required suite | cPanel checklist | operations sign-off |
| S5-011 | final local checks | rollback/smoke | green checks | readiness rehearsal | explicit release approval |
| S5-012 | closure suite/link checks | evidence review | merged-PR CI evidence | final smoke evidence | closure signatures |

| Area | Locally automated | Manual browser/UAT | GitHub CI | cPanel/staging | Production approval |
|---|---|---|---|---|---|
| Dashboard | repository/service/controller tests | desktop/mobile, keyboard, no data leak | required PR suite | representative data smoke | owner accepts metrics semantics |
| Security/audit | unit/integration/feature regressions | authenticated/unauthenticated checks | required PR suite | header/session/upload checks | security owner accepts residual risk |
| Backup/performance | safe command/migration/query tests | restore rehearsal | required PR suite | real backup/restore + plans | operations owner approval |
| Accessibility/UAT | applicable automated tests | browser/device/UAT matrix | required PR suite | staging walkthrough | product owner sign-off |
| Deployment/closure | configuration/document checks | smoke/rollback steps | green required checks | cPanel checklist evidence | explicit release approval |

## 5. Risks and decision gates

| Risk / unresolved decision | Control / owner gate |
|---|---|
| Revenue recognition, tax inclusion, date boundaries | Architect/Client approves before financial metric acceptance. |
| Query/index change may harm writes or migration safety | DBA/technical review with measured plan evidence. |
| cPanel capabilities, backup retention, SMTP deliverability, cron limits | Hosting/operator verifies in staging; no assumed values. |
| Restore, browser, UAT, CI and production results are external evidence | Record actual evidence; never substitute local tests. |
| Security review is not penetration testing | Document review/test scope and residual risks honestly. |

## 6. Definition of Done and closure evidence

Each ticket requires scoped code/docs, positive and negative tests, `git diff --check`, complete-suite regression where code changes, no secrets/private data, and PR review. Sprint closure additionally requires: linked ticket acceptance evidence; migration status; local automated result; GitHub CI result; manual browser/accessibility and UAT evidence; staging/cPanel backup/restore/deployment/rollback/smoke evidence; risk disposition; and Architect, QA, product-owner, and operations approvals as applicable.

## 7. Workflow and out of scope

Create this branch from `develop`, keep each ticket isolated, target PRs to `develop`, and merge releases to `main` only through the approved workflow. Do not stage unrelated local artifacts.

Out of scope: customer portal/accounts, specialized staff roles, multi-room bookings, payment gateways/refunds, SMS/WhatsApp/push, Redis/WebSockets/queues, Docker/Kubernetes/VPS/cloud deployment, analytics beyond approved dashboard projections, audit/backup APIs or UI unless separately approved, and any new dependency without necessity and approval.
