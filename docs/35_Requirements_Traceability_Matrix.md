# Requirements Traceability Matrix (RTM)

## Status

**Documentation synchronized — implementation and executable-test traceability pending.**

This matrix identifies the authoritative documentation path for Version 1. It does not claim that migrations, routes, implementation, or tests already exist. Requirement IDs must be connected to concrete migration, route, class, screen, and test IDs during implementation.

| Requirement area | Decision authority | Module/data documents | API/UI documents | Required test coverage | Current readiness |
|---|---|---|---|---|---|
| Administrator authentication | DEC-004–006, DEC-028 | 10, 16, 25, 31 | 23, 34 | Session login/logout, CSRF, rate limit, authorization | Documentation synchronized; implementation pending |
| Rooms and publication | DEC-008–010, DEC-022 | 10, 12 | 23, 34 | CRUD, publication, upload validation, inactive/maintenance behavior | Documentation synchronized; migration verification pending |
| Availability | DEC-010, DEC-013 | 10, 11, 12, 22 | 23, 34 | Overlap boundaries, blocking states, expired pending, concurrency | Documentation synchronized; implementation pending |
| Guest bookings | DEC-011–014 | 10, 11, 21, 22 | 23, 34 | Guest snapshot, one room, reference security, transitions | Documentation synchronized; implementation pending |
| Payments | DEC-015–017 | 10, 19, 22 | 23, 34 | Zero/multiple attempts, submission, verify/reject, duplicate verification; refund blocked pending policy | Core documented; refund Decision required |
| Invoices | DEC-019, DEC-026–027 | 10, 11, 19 | 23, 34 | Stable unique number, snapshot totals, issue/correction audit | Mostly specified; tax presentation requires client confirmation if applicable |
| Activities | DEC-022 | 10, 13 | 23, 34 | CRUD, active/published public filtering | Documentation synchronized; implementation pending |
| Gallery | DEC-022, DEC-028 | 10, 14 | 23, 34 | Upload safety, publication, ordering | Documentation synchronized; implementation pending |
| Reviews | DEC-020 | 10, 18, 22 | 23, 34 | Checked-out eligibility, token, one per booking, moderation | Documentation synchronized; implementation pending |
| Contact messages | Approved V1 scope, DEC-027–028 | 10, 21 | 23, 34 | Validation, spam/rate controls, admin access, audit where applicable | Mostly specified; status/retention/assignment policy requires review |
| CMS/settings | DEC-021–022 | 10, 15, 22 | 23, 34 | Draft/publish/archive, public visibility, settings authorization | Documentation synchronized; implementation pending |
| Notifications/email | DEC-023 | 10, 20, 21 | 23, 34 | In-app events, SMTP failure logging, non-rollback behavior | Documentation synchronized; SMTP details pending environment setup |
| Dashboard | Approved V1 scope | 17, 21 | 23, 34 | Canonical metrics and Administrator authorization | Mostly ready; depends on module implementation |
| Audit logging | DEC-027–029 | 10, 16, 20 | 23, 34 | Required security and state-change events | Documentation synchronized; implementation pending |
| Deployment | DEC-003, DEC-023–024, DEC-028 | 26, 30 | N/A | Migration, backup/restore, HTTPS, permissions, cron/email checks | Mostly ready; selected cPanel capability verification pending |

## Completion Rule

No row may be marked implemented or tested until it points to actual migrations, routes, implementation components, screen behavior, and passing test IDs. The prior “100% coverage” claim is withdrawn.
