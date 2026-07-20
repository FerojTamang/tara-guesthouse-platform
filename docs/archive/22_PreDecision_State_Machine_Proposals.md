# ARCHIVED — Tara Guest House Pre-Decision State-Machine Proposals

> **Historical warning:** This file contains pre-decision and superseded workflow proposals. It is not an implementation specification, must not be used for Version 1 coding, and may contain states, roles, and routes rejected by `../00_V1_ARCHITECT_DECISIONS.md`. The active contract is `../22_System_State_Machine.md`.

# 22. System State Machine

> **Authority:** The following Version 1 state models implement `00_V1_ARCHITECT_DECISIONS.md`. Later legacy diagrams in this document are retained only as historical/future design material where explicitly labeled and must not be implemented for Version 1.

## Version 1 Canonical State Models

### Booking

Statuses: `pending`, `confirmed`, `checked_in`, `checked_out`, `cancelled`, `rejected`.

Allowed transitions:

| Current | Next | Actor/service | JSON endpoint pattern |
|---|---|---|---|
| `pending` | `confirmed` | Booking Service after verified payment | `/api/v1/admin/bookings/{id}/status` |
| `pending` | `rejected` | Administrator through Booking Service | `/api/v1/admin/bookings/{id}/status` |
| `pending` | `cancelled` | Administrator/expiry rule through Booking Service | `/api/v1/admin/bookings/{id}/status` |
| `confirmed` | `checked_in` | Administrator through Booking Service | `/api/v1/admin/bookings/{id}/status` |
| `confirmed` | `cancelled` | Administrator through Booking Service | `/api/v1/admin/bookings/{id}/status` |
| `checked_in` | `checked_out` | Administrator through Booking Service | `/api/v1/admin/bookings/{id}/status` |

`checked_out`, `cancelled`, and `rejected` are terminal. There is no `completed` or workflow `archived` booking status. Pending, confirmed, and checked-in bookings block overlapping dates; expired pending bookings cease blocking and may be cancelled with a recorded reason.

### Payment

Statuses: `pending`, `submitted`, `verified`, `rejected`, `refunded`.

| Current | Next | Actor/service |
|---|---|---|
| `pending` | `submitted` | Guest submission through Payment Service |
| `submitted` | `verified` | Administrator through Payment Service |
| `submitted` | `rejected` | Administrator through Payment Service |
| `verified` | `refunded` | Administrator records a manual refund through Payment Service |

A rejected attempt does not reject the booking. A new payment attempt may be submitted before the booking deadline. Invoice generation is an event, not a payment status.

### Room operational and publication state

Operational status: `active`, `maintenance`, `inactive`. Publication is a separate boolean (`is_published` or equivalent). Available, reserved, and occupied are calculated for dates from booking records; they are not room operational statuses. Cleaning/housekeeping workflow is future.

### Reviews

Statuses: `pending`, `approved`, `rejected`. A secure review opportunity exists only for a `checked_out` booking, with one review per booking. Only approved reviews are public.

### CMS pages

Statuses: `draft`, `published`, `archived`. Settings remain direct site-wide configuration values. Full version history and rollback are future.

### Gallery and activities

Operational/business state and publication state are separate. Only active and published records appear publicly.

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | System State Machine |
| Version | 1.0 |
| Status | Synchronized — Review Required |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Enterprise State Machine Specification |

---

# Table of Contents

1. Purpose
2. State Machine Standards
3. Booking State Machine
4. Payment State Machine
5. Room State Machine
6. Review State Machine
7. Contact Message State Machine
8. Notification State Machine
9. Gallery State Machine
10. Activity State Machine
11. User Account State Machine
12. General Transition Rules
13. Audit Requirements
14. API Impact
15. Database Impact
16. Related Documents

---

# 1. Purpose

This document defines every valid state transition used throughout the Tara Guest House Management System.

The objective is to ensure that every developer implements identical business logic while preventing invalid workflow transitions.

This document acts as the primary reference for:

- Backend Developers
- Frontend Developers
- QA Engineers
- Project Managers
- Future Developers

---

# 2. State Machine Standards

Every state transition shall follow these principles.

- Every transition must be valid.
- Every transition must be logged.
- Invalid transitions shall be rejected.
- State history shall never be deleted.
- State changes shall record timestamp.
- State changes shall record responsible user.
- Automatic transitions shall record "System" as actor.

---

# Appendix A — Superseded Pre-Decision Booking State Proposal

## Purpose

Defines the lifecycle of a hotel reservation from creation until archival.

---

## Booking Lifecycle

Draft

↓

Pending Payment

↓

Payment Submitted

↓

Payment Verified

↓

Confirmed

↓

Checked In

↓

Checked Out

↓

Completed

↓

Archived

---

## State Definitions

| State | Description |
|--------|-------------|
| Draft | Reservation form started but not submitted |
| Pending Payment | Reservation created and awaiting payment |
| Payment Submitted | Guest uploaded payment proof |
| Payment Verified | Administrator approved payment |
| Confirmed | Reservation officially confirmed |
| Checked In | Guest has checked into hotel |
| Checked Out | Guest has checked out |
| Completed | Reservation successfully completed |
| Archived | Historical record retained |

---

## Valid State Transitions

| Current State | Next State | Actor | API | Database | Audit |
|---------------|------------|------|-----|----------|-------|
| Draft | Pending Payment | Guest | POST `/api/v1/bookings` | bookings | Yes |
| Pending Payment | Payment Submitted | Guest | POST `/api/v1/payments` | payments | Yes |
| Payment Submitted | Payment Verified | Admin | PATCH `/api/v1/admin/payments/{id}/status` | payments | Yes |
| Payment Verified | Confirmed | System | Internal | bookings | Yes |
| Confirmed | Checked In | Admin | PATCH `/api/v1/admin/bookings/{id}/status` | bookings, rooms | Yes |
| Checked In | Checked Out | Admin | PATCH `/api/v1/admin/bookings/{id}/status` | bookings, rooms | Yes |
| Checked Out | Completed | System | Internal | bookings | Yes |
| Completed | Archived | System | Scheduled Job | bookings | Yes |

---

## Invalid State Transitions

| Invalid Transition | Reason |
|--------------------|--------|
| Draft → Checked In | Reservation incomplete |
| Draft → Completed | Booking not created |
| Pending Payment → Checked Out | Payment missing |
| Confirmed → Pending Payment | Invalid rollback |
| Completed → Confirmed | Booking already closed |
| Archived → Checked In | Archived records are read-only |

---

## UI Behaviour

| State | Guest Actions | Admin Actions |
|--------|---------------|---------------|
| Pending Payment | Upload Payment | Cancel Booking |
| Payment Submitted | View Status | Verify / Reject |
| Confirmed | View Booking | Check-In |
| Checked In | View Booking | Check-Out |
| Completed | Leave Review | View History |
| Archived | View History | View History |

---

## Notification Triggers

| State Change | Notification |
|--------------|-------------|
| Pending Payment | Payment Instructions |
| Payment Submitted | Notify Administrator |
| Payment Verified | Payment Approved |
| Confirmed | Booking Confirmation |
| Checked In | Welcome Notification |
| Completed | Review Invitation |

---

## Business Rules

- Reservation reference must be unique.
- Booking cannot skip required states.
- Booking status history shall be preserved permanently.
- Every booking must reference an existing room.
- Only administrators may perform Check-In and Check-Out.
- Archived bookings are read-only.

---

# Appendix B — Superseded Pre-Decision Payment State Proposal

## Purpose

Defines every stage of payment verification.

---

## Payment Lifecycle

Awaiting Payment

↓

Payment Submitted

↓

Under Verification

↓

Verified

↓

Invoice Generated

↓

Completed

OR

Rejected

OR

Refunded

---

## State Definitions

| State | Description |
|--------|-------------|
| Awaiting Payment | Guest has not yet submitted payment |
| Payment Submitted | Voucher uploaded |
| Under Verification | Admin reviewing payment |
| Verified | Payment approved |
| Invoice Generated | Invoice created |
| Completed | Payment finished |
| Rejected | Payment rejected |
| Refunded | Payment refunded |

---

## Valid State Transitions

| Current State | Next State | Actor | API | Database | Audit |
|---------------|------------|------|-----|----------|-------|
| Awaiting Payment | Payment Submitted | Guest | POST `/api/v1/payments` | payments | Yes |
| Payment Submitted | Under Verification | System | Internal | payments | Yes |
| Under Verification | Verified | Admin | PATCH `/api/v1/admin/payments/{id}/status` | payments | Yes |
| Under Verification | Rejected | Admin | PATCH `/api/v1/admin/payments/{id}/status` | payments | Yes |
| Verified | Invoice Generated | System | Internal | invoices | Yes |
| Invoice Generated | Completed | System | Internal | payments | Yes |
| Completed | Refunded | Admin | POST /refund | refunds | Yes |

---

## Invalid Transitions

| Invalid Transition | Reason |
|--------------------|--------|
| Awaiting Payment → Verified | Verification skipped |
| Rejected → Verified | Requires new payment |
| Refunded → Verified | Closed transaction |
| Completed → Awaiting Payment | Invalid rollback |

---

## Business Rules

- Every payment belongs to one booking.
- Duplicate transaction IDs are prohibited.
- Payment proof cannot be modified after verification.
- All payment actions shall be audited.
- Refunds require administrator approval.

---

## Notification Triggers

| Event | Recipient |
|-------|-----------|
| Payment Submitted | Administrator |
| Payment Verified | Guest |
| Payment Rejected | Guest |
| Invoice Generated | Guest |
| Refund Completed | Guest |

---

# End of Part 1

The following sections continue in Part 2:

- Room State Machine
- Review State Machine
- Contact Message State Machine
- Notification State Machine
- Gallery State Machine
- Activity State Machine
- User Account State Machine
- General Transition Rules
- Audit Requirements
- API Impact
- Database Impact
- Related Documents

---

# Appendix C — Future Operational/Housekeeping State Proposal

## Purpose

Defines the lifecycle of a room from creation until retirement.

---

## Room Lifecycle

Draft

↓

Ready for Review

↓

Published

↓

Available

↓

Reserved

↓

Occupied

↓

Cleaning

↓

Maintenance (Optional)

↓

Available

OR

Retired

---

## State Definitions

| State | Description |
|--------|-------------|
| Draft | Room record created but incomplete |
| Ready for Review | Room information completed and awaiting approval |
| Published | Room is visible on the website |
| Available | Room can be booked |
| Reserved | Room is reserved by a guest |
| Occupied | Guest has checked in |
| Cleaning | Room is being prepared for the next guest |
| Maintenance | Room temporarily unavailable due to maintenance |
| Retired | Room permanently removed from service |

---

## Valid State Transitions

| Current State | Next State | Actor | API | Database | Audit |
|---------------|------------|------|-----|----------|-------|
| Draft | Ready for Review | Admin | PUT `/api/v1/admin/rooms/{id}` | rooms | Yes |
| Ready for Review | Published | Admin | PATCH `/api/v1/admin/rooms/{id}` | rooms | Yes |
| Published | Available | System | Internal | rooms | Yes |
| Available | Reserved | System | POST `/api/v1/bookings` | rooms | Yes |
| Reserved | Occupied | Admin | PATCH `/api/v1/admin/bookings/{id}/status` | rooms | Yes |
| Occupied | Cleaning | Admin | PATCH `/api/v1/admin/bookings/{id}/status` | rooms | Yes |
| Cleaning | Available | Housekeeping/Admin | PATCH `/api/v1/admin/rooms/{id}` | rooms | Yes |
| Available | Maintenance | Admin | PATCH `/api/v1/admin/rooms/{id}` | rooms | Yes |
| Maintenance | Available | Admin | PATCH `/api/v1/admin/rooms/{id}` | rooms | Yes |
| Available | Retired | Admin | DELETE `/api/v1/admin/rooms/{id}` (Soft Delete) | rooms | Yes |

---

## Invalid State Transitions

| Invalid Transition | Reason |
|--------------------|--------|
| Available → Occupied | Must be reserved first |
| Occupied → Available | Cleaning required |
| Maintenance → Reserved | Room unavailable |
| Retired → Available | Retired rooms are read-only |

---

## Business Rules

- Only available rooms may be booked.
- A room under maintenance cannot receive reservations.
- Cleaning is mandatory after check-out.
- Retired rooms remain in history for reporting.

---

## Notification Triggers

| Event | Recipient |
|-------|-----------|
| Room enters Maintenance | Administrator |
| Room ready after Cleaning | Administrator |

---

# 6. Review State Machine

## Purpose

Defines the lifecycle of guest reviews.

---

## Review Lifecycle

Draft

↓

Submitted

↓

Pending Moderation

↓

Approved

↓

Published

↓

Archived

OR

Rejected

---

## State Definitions

| State | Description |
|--------|-------------|
| Draft | Guest has started writing a review |
| Submitted | Review submitted by guest |
| Pending Moderation | Awaiting administrator review |
| Approved | Review approved |
| Published | Visible on website |
| Rejected | Review declined |
| Archived | Historical record |

---

## Valid State Transitions

| Current State | Next State | Actor | API | Database | Audit |
|---------------|------------|------|-----|----------|-------|
| Draft | Submitted | Guest | POST `/api/v1/reviews` | reviews | Yes |
| Submitted | Pending Moderation | System | Internal | reviews | Yes |
| Pending Moderation | Approved | Admin | PATCH `/api/v1/admin/reviews/{id}/status` | reviews | Yes |
| Pending Moderation | Rejected | Admin | PATCH `/api/v1/admin/reviews/{id}/status` | reviews | Yes |
| Approved | Published | System | Internal | reviews | Yes |
| Published | Archived | Admin | PATCH `/api/v1/admin/reviews/{id}/status` | reviews | Yes |

---

## Invalid State Transitions

| Invalid Transition | Reason |
|--------------------|--------|
| Draft → Published | Moderation required |
| Rejected → Published | Must be resubmitted |
| Archived → Published | Archived reviews are read-only |

---

## Business Rules

- Reviews must belong to completed bookings.
- Reviews cannot bypass moderation.
- Every moderation decision shall be audited.

---

## Notification Triggers

| Event | Recipient |
|-------|-----------|
| Review Submitted | Administrator |
| Review Approved | Guest |
| Review Rejected | Guest |

---

# 7. Contact Message State Machine

## Contact Lifecycle

New

↓

Assigned

↓

In Progress

↓

Resolved

↓

Closed

---

## Valid State Transitions

| Current State | Next State | Actor |
|---------------|------------|------|
| New | Assigned | Admin |
| Assigned | In Progress | Admin |
| In Progress | Resolved | Admin |
| Resolved | Closed | Admin |

---

## Business Rules

- Every contact message must be assigned to a staff member.
- Closed messages cannot be edited.
- Contact history shall be retained.

---

# 8. Notification State Machine

## Notification Lifecycle

Created

↓

Queued

↓

Sent

↓

Delivered

↓

Read

↓

Archived

---

## Valid State Transitions

| Current State | Next State | Actor |
|---------------|------------|------|
| Created | Queued | System |
| Queued | Sent | System |
| Sent | Delivered | System |
| Delivered | Read | User |
| Read | Archived | System |

---

## Business Rules

- Notifications cannot skip delivery stages.
- Read timestamps shall be recorded.
- Archived notifications remain searchable.

---

# 9. Gallery State Machine

## Gallery Lifecycle

Draft

↓

Uploaded

↓

Optimized

↓

Published

↓

Archived

---

## Valid State Transitions

| Current State | Next State | Actor |
|---------------|------------|------|
| Draft | Uploaded | Admin |
| Uploaded | Optimized | System |
| Optimized | Published | Admin |
| Published | Archived | Admin |

---

## Business Rules

- Only optimized images may be published.
- Archived images remain available for reporting.

---

# 10. Activity State Machine

## Activity Lifecycle

Draft

↓

Published

↓

Available

↓

Unavailable

↓

Archived

---

## Valid State Transitions

| Current State | Next State | Actor |
|---------------|------------|------|
| Draft | Published | Admin |
| Published | Available | System |
| Available | Unavailable | Admin |
| Unavailable | Available | Admin |
| Available | Archived | Admin |

---

## Business Rules

- Unavailable activities cannot be booked.
- Archived activities remain in historical reports.

---

# 11. User Account State Machine

## User Lifecycle

Invited

↓

Registered

↓

Active

↓

Locked

↓

Inactive

↓

Archived

---

## Valid State Transitions

| Current State | Next State | Actor |
|---------------|------------|------|
| Invited | Registered | User |
| Registered | Active | Admin |
| Active | Locked | Admin |
| Locked | Active | Admin |
| Active | Inactive | Admin |
| Inactive | Archived | Admin |

---

## Business Rules

- Only active users may log in.
- Locked users cannot authenticate.
- Archived accounts cannot be reactivated.

---

# 12. General Transition Rules

- Every state transition shall be validated.
- Invalid transitions shall return an error response.
- Every transition shall generate an audit log.
- Automatic transitions shall record the actor as **System**.
- State history shall never be deleted.

---

# 13. Audit Requirements

Every state transition shall record:

- Entity Name
- Entity ID
- Previous State
- New State
- Date & Time
- Performed By
- IP Address (where applicable)
- Notes (Optional)

Audit records are immutable and retained for compliance.

---

# 14. API Impact

All APIs that change an entity's status must validate the current state before allowing a transition.

If an invalid transition is requested, the API shall return an appropriate error (for example, HTTP 409 Conflict).

---

# 15. Database Impact

State values shall be stored using controlled enumerations or reference tables to ensure consistency.

Every status change shall update the audit log.

Foreign key relationships shall remain valid throughout all transitions.

---

# 16. Related Documents

- 11_Booking_System.md
- 12_Room_Management_System.md
- 13_Activity_Management_System.md
- 14_Gallery_Management_System.md
- 15_Content_Management_System.md
- 16_Authentication_Authorization.md
- 17_Admin_Dashboard.md
- 18_Review_Rating_System.md
- 19_Payment_Management_System.md
- 20_Notification_System.md
- 21_Business_Process_Flow.md
- 23_ER_Diagram.md

---

# End of Document
