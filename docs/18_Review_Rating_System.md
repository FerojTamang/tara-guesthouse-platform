# Tara Guest House Review and Rating Specification

## Document Information

* Status: Implemented and QA synchronized in Sprint 4 — Review Required
* Scope: Version 1 verified-stay review invitation, submission, moderation and public display

## 1. Version 1 Contract

* A secure single-use invitation is linked to one booking where `booking_status = checked_out`.
* One booking may produce at most one invitation and one review.
* Review statuses are `pending`, `approved`, `rejected`.
* An Administrator moderates pending reviews.
* Only approved reviews display publicly.
* No customer account or direct user/room ownership is required.

## 2. Data Contract

`review_invitations` stores `booking_id`, unique `token_hash`, `expires_at`, nullable `used_at` and `created_at`.

`reviews` stores `booking_id`, `review_invitation_id`, Guest name snapshot, rating, optional title, comment, `review_status`, moderation actor/time/note and timestamps.

Eligibility is verified through the invitation and linked checked-out booking. The raw token is never stored.

## 3. Endpoints and Pages

Public:

* PHP page: `/reviews`
* PHP submission page: `/review/{token}`
* `GET /api/v1/reviews`
* `POST /api/v1/reviews`

Administrator:

* PHP pages: `/admin/reviews`, `/admin/reviews/{id}`
* `GET /api/v1/admin/reviews`
* `GET /api/v1/admin/reviews/{id}`
* `PATCH /api/v1/admin/reviews/{id}/status`

No Guest edit/delete endpoint exists. Version 1 has no Administrator reply, hide, delete, feature, export or unsupported review-statistics endpoint.

## 4. Invitation and Submission Flow

1. Booking Service completes check-out and triggers the invitation process.
2. Review Service confirms `booking_status = checked_out` and no existing invitation/review.
3. It creates a cryptographically random token, stores only its hash and configured expiry, and emails the raw link after commit.
4. Submission validates token hash, expiry, unused state, checked-out booking and uniqueness in a transaction.
5. It stores one `pending` review and marks the invitation used.
6. Reuse fails without revealing sensitive booking data.

Invitation lifetime is Architect/Security Configuration Required.

## 5. Moderation and Display

Only a pending review may become `approved` or `rejected`. Rejection follows the approved moderation-note policy. Moderator/time are retained and the action is audited. Public queries return only approved reviews and safe display fields.

## 6. Validation and Security

Rating is an integer 1–5. Title/comment use configured length and content rules. Output is escaped. Public submission is rate-limited. Tokens are sufficiently random, single-use, expiring and excluded from logs. Review Service owns eligibility/transactions; Review Repository owns PDO/SQL; entities execute no SQL.

## 7. Acceptance Tests

* Invitation cannot be created for a non-`checked_out` booking.
* A token is stored hashed and can be used once.
* One booking cannot receive a second review.
* New review is always `pending`.
* Only Administrator can moderate.
* Only approved reviews are public.
* Guest edit/delete and unsupported Administrator actions/routes are absent.

## 8. Future Enhancements

Administrator replies, review featuring, Guest edit/delete, media reviews, exports and statistics require approved database/API/UI/security contracts.
