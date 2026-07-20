# Tara Guest House Management System

# 22. System State Machine

## Document Control

| Field | Value |
|---|---|
| Version | 1.2 |
| Status | Synchronized — Review Required |
| Authority | `00_V1_ARCHITECT_DECISIONS.md` |
| Historical proposals | `archive/22_PreDecision_State_Machine_Proposals.md` |

# 1. Purpose

This document contains the only active Version 1 state models. Payment progress is not represented as booking states. Room availability and occupancy are calculated conditions, not room operational states.

# 2. Booking State Machine

## 2.1 States

| State | Meaning | Blocks overlapping dates |
|---|---|---|
| `pending` | Booking created and awaiting payment verification or Administrator decision | Yes, until `confirmation_deadline_at` expires |
| `confirmed` | Required payment verified and reservation confirmed | Yes |
| `checked_in` | Guest has arrived | Yes |
| `checked_out` | Guest has departed successfully | No |
| `cancelled` | Booking cancelled with timestamp and reason | No |
| `rejected` | Booking rejected with timestamp and reason | No |

`checked_out`, `cancelled`, and `rejected` are terminal. Version 1 does not use `completed` or workflow `archived` as booking states.

## 2.2 Allowed Transitions

| Current | Next | Authority | Required data/effect |
|---|---|---|---|
| new record | `pending` | Booking Service | One room, guest snapshot, policy acceptance, deadline, event history |
| `pending` | `confirmed` | Booking Service after Payment Service reports a verified payment | `confirmed_at`; append booking event |
| `pending` | `rejected` | Administrator through Booking Service | `rejected_at`, reason; append event |
| `pending` | `cancelled` | Administrator or expiry rule through Booking Service | `cancelled_at`, reason; append event |
| `confirmed` | `checked_in` | Administrator through Booking Service | `checked_in_at`; append event |
| `confirmed` | `cancelled` | Administrator through Booking Service | `cancelled_at`, reason; append event |
| `checked_in` | `checked_out` | Administrator through Booking Service | `checked_out_at`; append event; create review invitation event/process |

All other booking transitions are forbidden. Only the Booking Service changes `booking_status`.

## 2.3 Expired Pending Bookings

When `confirmation_deadline_at` passes, the pending booking no longer blocks availability. The approved expiry process may transition it to `cancelled` with a recorded expiry reason. The exact deadline and execution interval require an approved configuration/ticket.

# 3. Payment State Machine

## 3.1 States

| State | Meaning |
|---|---|
| `pending` | Canonical reserved value; no active V1 endpoint creates an incomplete attempt |
| `submitted` | Guest supplied details/proof for Administrator review |
| `verified` | Administrator accepted the attempt |
| `rejected` | Administrator did not accept the submitted attempt |
| `refunded` | Canonical reserved state; implementation is blocked until refund policy/data fields are approved |

## 3.2 Allowed Transitions

| Current | Next | Authority | Required data/effect |
|---|---|---|---|
| new attempt | `submitted` | Payment Service after complete secure Guest submission | Booking/method/amount/reference/proof validation; `submitted_at` |
| `submitted` | `verified` | Administrator through Payment Service | Verification actor/time/note; notify Booking Service |
| `submitted` | `rejected` | Administrator through Payment Service | Rejection actor/time/note |

A rejected payment record remains rejected. Resubmission creates a new attempt; it does not reset the rejected row. Rejecting an attempt does not reject the booking. Only the Payment Service changes `payment_status`.

There is no active Version 1 transition into `refunded`. Full-versus-partial behavior, authority, accounting treatment and persistence fields are **Architect/Client Confirmation Required**; a later approved update must add the transition and exact data contract together.

# 4. Room Operational State

## 4.1 Stored Values

- `active`
- `maintenance`
- `inactive`

| Transition | Authority | Effect |
|---|---|---|
| `active` → `maintenance` | Administrator through Room Service | Room unavailable for all requested dates |
| `maintenance` → `active` | Administrator through Room Service | Room may be offered if published and no booking blocks dates |
| `active` → `inactive` | Administrator through Room Service | Room disabled but retained for history |
| `maintenance` → `inactive` | Administrator through Room Service | Room disabled but retained for history |
| `inactive` → `active` | Administrator through Room Service | Room may be offered after publication and availability checks |
| `inactive` → `maintenance` | Administrator through Room Service | Room remains unavailable |

## 4.2 Calculated Conditions

- **Available for a date range:** active, published for public search, within capacity, and no unexpired blocking booking overlaps.
- **Reserved for a date range:** a `pending` or `confirmed` booking blocks the range.
- **Currently occupied:** a `checked_in` booking covers the current stay.

These are query results and must not be written to `rooms.operational_status`. Check-in and checkout update the booking only.

# 5. Room Publication

Publication is a boolean independent of operational status:

| Current | Next | Authority |
|---|---|---|
| unpublished | published | Administrator through Room Service |
| published | unpublished | Administrator through Room Service |

An active but unpublished room is not shown publicly.

# 6. Review State Machine

Eligibility requires `booking_status = checked_out` and a secure unused review invitation.

| Current | Next | Authority |
|---|---|---|
| new review | `pending` | Review Service after token/booking validation |
| `pending` | `approved` | Administrator through Review Service |
| `pending` | `rejected` | Administrator through Review Service |

Only approved reviews are public. One review is permitted per booking. Approved/rejected moderation is audited.

# 7. CMS Page State Machine

| Current | Next | Authority |
|---|---|---|
| new page | `draft` | CMS Service |
| `draft` | `published` | Administrator through CMS Service |
| `published` | `archived` | Administrator through CMS Service |
| `archived` | `published` | Administrator through CMS Service |

`archived` is a CMS page state only; it is not a booking state. Full page-version history and rollback are future enhancements.

# 8. Activities and Gallery Visibility

Activities and gallery items have two independent booleans:

- active/inactive business control;
- published/unpublished public visibility.

Only active and published records are returned publicly. These controls are not booking or room states.

# 9. Notification Read State

In-app Administrator notifications may be unread or read using `read_at`. Delivery channels are not workflow statuses. SMTP failures are logged and do not roll back committed business transactions.

# 10. State-Change Enforcement

- Request classes validate shape and canonical values.
- Services authorize and enforce transitions inside transactions.
- Repositories persist approved changes; they do not decide transitions.
- Booking changes append `booking_events`.
- Important administrative changes append `audit_logs`.
- Views and frontend JavaScript never set domain states directly.
- API endpoints accept transition intent only where documented in `23_REST_API_Specification.md`.

# 11. Required Tests

- every allowed and forbidden booking transition;
- terminal booking-state immutability;
- pending deadline and availability blocking;
- payment attempt resubmission as a new row;
- Administrator-only verification/rejection;
- no room operational-status change during booking/check-in/checkout;
- room operational/publication combinations;
- checked-out review eligibility and one review per booking;
- CMS page transitions;
- permanent booking-event and audit history.
