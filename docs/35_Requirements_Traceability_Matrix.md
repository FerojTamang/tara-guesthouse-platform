# Requirements Traceability Matrix (RTM)

## Status

**Documentation synchronized — Sprint 1 Administrator authentication/Rooms, Sprint 2 Availability/Booking, and Sprint 3 Payments/Invoices are implemented and locally tested. Later modules pending.**

This matrix identifies the authoritative documentation path for Version 1. Administrator authentication, Rooms, Availability, Guest booking, payment methods and attempts, private proof handling, payment-driven booking confirmation, invoices, and related audit evidence point to concrete migrations, routes, services, screens, and passing tests. Future requirement areas remain documented and pending implementation.


| Requirement area | Decision authority | Module/data documents | API/UI documents | Required test coverage | Current readiness |
|---|---|---|---|---|---|
| Administrator authentication | DEC-004–006, DEC-028 | 10, 16, 25, 31 | 23, 34 | Session login/logout, CSRF, rate limit, authorization | Implementation complete; migrations (users, roles), services (AuthenticationService), controllers (AdministratorAuthController), requests (LoginRequest), repositories (UserRepository), views (login.php), and tests all verified passing |
| Rooms and publication | DEC-008–010, DEC-022 | 10, 12 | 23, 34 | CRUD, publication, upload validation, inactive/maintenance behavior | Implementation complete; migrations (room_types, rooms, amenities, room_amenities, room_images), services (RoomCatalogService, RoomImageService), controllers (AdministratorRoomController, PublicRoomController), requests (RoomUpsertRequest, RoomImageUploadRequest), repositories (RoomRepository, RoomTypeRepository, AmenityRepository, RoomImageRepository), storage (LocalRoomImageStorage), views (administrator room forms and public pages), and tests all verified passing |
| Availability | DEC-010, DEC-013 | 10, 11, 12, 22 | 23, 34 | Overlap boundaries, blocking states, expired pending, concurrency | Implementation complete for Sprint 2; booking migrations, `PdoAvailabilityRepository`, `AvailabilityService`, public page/API controllers and views, overlap boundary tests, expired-pending tests, and concurrency tests verified passing |
| Guest bookings | DEC-011–014 | 10, 11, 21, 22 | 23, 34 | Guest snapshot, one room, reference security, transitions | Implementation complete through Sprint 3; transactional one-room creation, immutable snapshots, signed follow-up access, Administrator read/status workflows, and the internal verified-payment `pending -> confirmed` operation are covered by unit/integration/feature tests |
| Payments | DEC-015–017 | 10, 19, 22 | 23, 34 | Zero/multiple attempts, submission, verify/reject, duplicate verification; refund blocked pending policy | Sprint 3 implementation complete: payment-method/payment migrations, public method reads, signed/rate-limited Guest submission, private proof validation/storage/retrieval, Administrator list/detail/verify/reject, duplicate-verification guard, coordinated booking confirmation, audit evidence, and passing tests; refund mutation remains blocked |
| Invoices | DEC-019, DEC-026–027 | 08, 10, 11, 19 | 23, 34 | Stable unique number, snapshot totals, one-per-booking, protected print, issue audit | Sprint 3 implementation complete: invoice/invoice-item migrations, idempotent issue, `TGH-INV-YYYY-NNNNNN` numbering, immutable NPR snapshots, `0.00` tax, no service charge, protected list/detail/print, audit evidence, and passing tests; corrections/credit notes remain deferred |
| Activities | DEC-022 | 10, 13 | 23, 34 | CRUD, active/published public filtering | Documentation synchronized; implementation pending |
| Gallery | DEC-022, DEC-028 | 10, 14 | 23, 34 | Upload safety, publication, ordering | Documentation synchronized; implementation pending |
| Reviews | DEC-020 | 10, 18, 22 | 23, 34 | Checked-out eligibility, token, one per booking, moderation | Documentation synchronized; implementation pending |
| Contact messages | Approved V1 scope, DEC-027–028 | 10, 21 | 23, 34 | Validation, spam/rate controls, admin access, audit where applicable | Mostly specified; status/retention/assignment policy requires review |
| CMS/settings | DEC-021–022 | 10, 15, 22 | 23, 34 | Draft/publish/archive, public visibility, settings authorization | Documentation synchronized; implementation pending |
| Notifications/email | DEC-023 | 10, 20, 21 | 23, 34 | In-app events, SMTP failure logging, non-rollback behavior | Documentation synchronized; SMTP details pending environment setup |
| Dashboard | Approved V1 scope | 17, 21 | 23, 34 | Canonical metrics and Administrator authorization | Mostly ready; depends on module implementation |
| Audit logging | DEC-027–029 | 10, 16, 19, 20 | 23, 34 | Required security and state-change events | Append-only audit foundation now covers booking transitions, payment verification/rejection, payment-driven booking confirmation, protected proof access, and invoice issue with transactional rollback tests; broader later-module audit coverage remains pending |
| Deployment | DEC-003, DEC-023–024, DEC-028 | 26, 30 | N/A | Migration, backup/restore, HTTPS, permissions, cron/email checks | Mostly ready; selected cPanel capability verification pending |

## Completion Rule

No row may be marked implemented or tested until it points to actual migrations, routes, implementation components, screen behavior, and passing test IDs. The prior “100% coverage” claim is withdrawn.
