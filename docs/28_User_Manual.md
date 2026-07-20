# Tara Guest House User Manual

## Document Information

* Status: Synchronized — Review Required
* Authority: `00_V1_ARCHITECT_DECISIONS.md`
* Scope: Version 1 Guest and Administrator workflows

## 1. Version 1 Users

Version 1 supports two user-facing actors:

* **Guest** uses public pages without registration or login.
* **Administrator** signs in with an Administrator account and a PHP session.

Guest is not a stored/authenticated role. Customer accounts, a Customer Portal, Receptionist, Manager, Accountant and Content Manager access are Future Enhancements.

## 2. Guest Website

### 2.1 Browse rooms

Use the Rooms page to view published rooms. Operational status, publication status and booking availability are separate concepts. Only rooms that are operationally `active`, publicly published and available for the requested dates can be selected for a public booking.

Availability is calculated from bookings and dates. The system does not store Available, Reserved or Occupied as room operational statuses.

### 2.2 Check availability

1. Enter the check-in date.
2. Enter a check-out date later than the check-in date.
3. Enter the guest count.
4. Submit the search.
5. Review the rooms that are available for the complete date range.

A room is unavailable when a blocking booking overlaps the requested stay. Results can change until a booking is successfully created.

### 2.3 Create a booking

Version 1 creates one room per booking and does not require a customer account.

1. Select one available room.
2. Confirm the stay dates and guest count.
3. Enter the Guest contact snapshot: full name, email and phone.
4. Enter any permitted special request.
5. Read and accept the displayed booking terms/policy.
6. Submit the booking.

The booking starts as `pending`. Save the booking reference and secure access link/token shown by the application. The booking is subject to a payment/confirmation deadline displayed in the confirmation instructions.

### 2.4 Submit payment information

A new booking does not automatically create a payment record. When payment information is available:

1. Open the secure booking-payment flow.
2. Select an enabled payment method.
3. Enter the payment reference and amount.
4. Upload permitted proof when required.
5. Submit the payment information.

The submission creates a payment attempt. Payment attempts use:

* `pending`
* `submitted`
* `verified`
* `rejected`
* `refunded`

The Guest may submit another attempt when the flow permits it; earlier attempts remain in the audit history. Only an Administrator verifies or rejects payment in Version 1. A verified payment permits Booking Service to move the booking from `pending` to `confirmed`.

### 2.5 Booking status meaning

* `pending`: booking created and awaiting confirmation requirements.
* `confirmed`: payment has been verified and the stay is confirmed.
* `checked_in`: the Guest has been checked in.
* `checked_out`: the stay has ended successfully; this is the final successful status.
* `cancelled`: the booking was cancelled according to the applicable policy.
An overdue `pending` booking no longer blocks availability and may be changed to `cancelled` with a recorded deadline-expiry reason. `expired` is not a booking status.

There is no Version 1 booking status named Completed or Archived.

### 2.6 Submit a review

Reviews are verified-stay reviews. The system may issue a secure, single-use invitation only when `booking_status = checked_out`.

1. Open the review invitation before it expires.
2. Enter the rating and permitted review details.
3. Submit once.

The review remains subject to moderation before public publication. A used, invalid or expired invitation cannot be reused.

### 2.7 Contact the guest house

Use the public contact form to submit a name, email, phone when requested, subject and message. The system records the message and may send an SMTP email notification. Do not include payment secrets or other highly sensitive data in a contact message.

## 3. Administrator Access

### 3.1 Sign in

1. Open the Administrator login page.
2. Enter the Administrator email and password.
3. Submit the form.

The application creates a server-side PHP session. If the session expires because of configured inactivity or absolute lifetime, sign in again. Version 1 does not use JWT access or refresh tokens.

### 3.2 Sign out

Use the Sign Out action when work is complete, especially on a shared device. Signing out destroys the active server-side session.

## 4. Administrator Dashboard

The dashboard summarizes authoritative records, such as current stays, pending bookings, submitted payments and recent activity. Dashboard metrics are read-only projections; viewing the dashboard does not change a booking, payment or room status.

## 5. Room Management

### 5.1 Operational status

Administrators may manage these room operational statuses:

* `active`
* `maintenance`
* `inactive`

Operational status describes whether the room can be offered operationally. It is not booking availability.

### 5.2 Publication and availability

Publication is controlled separately from operational status. Administrator screens must display:

* operational status;
* publication status;
* calculated current occupancy;
* calculated availability for selected dates.

Reserved and Occupied are derived booking conditions and must not be selected as room operational statuses.

### 5.3 Room records

Administrators may create and update room details, room type, capacity, NPR rate snapshots for future bookings, amenities and images. A room referenced by booking history should be made `inactive` rather than hard-deleted.

## 6. Booking Management

### 6.1 Create a booking for a Guest

The Administrator Create Booking screen uses Guest contact snapshot fields. It does not search for or require a registered customer.

1. Select dates and check calculated availability.
2. Select exactly one eligible room.
3. Enter the Guest name, email and phone.
4. Enter guest count and any permitted special request.
5. Record terms/policy acceptance as required by the workflow.
6. Create the booking.

The new booking always starts as `pending`. The form does not allow an arbitrary booking or payment status to be selected and does not always create a payment attempt.

### 6.2 Booking actions

Administrators use explicit actions rather than editing the status field directly:

* Cancel a `pending` or `confirmed` booking according to policy.
* Check in a `confirmed` booking; it becomes `checked_in`.
* Check out a `checked_in` booking; it becomes `checked_out`.

Every transition is validated and retained in permanent booking event history. The system never changes a room operational status to Reserved, Occupied or Available during these actions.

## 7. Payment Management

The payment queue shows individual submissions, including multiple attempts for the same booking.

### 7.1 Verify payment

1. Open a `submitted` payment attempt.
2. Compare the reference, proof, amount and booking financial snapshot.
3. Choose Verify only when the manual evidence is sufficient.
4. Confirm the action.

The attempt becomes `verified`. Payment verification permits Booking Service to confirm a `pending` booking. Verification is Administrator-only and no payment gateway is used in Version 1.

### 7.2 Reject payment

1. Open the submitted attempt.
2. Enter the required rejection reason.
3. Confirm rejection.

The attempt becomes `rejected`; the booking is not confirmed by that attempt. Do not overwrite or delete the rejected record.

### 7.3 Refunds

The system can record a `refunded` payment status, but the full-versus-partial refund policy, approval authority and accounting treatment require Architect/Client Confirmation. Administrators must follow an approved operational policy and must not infer an unapproved refund workflow from this manual.

## 8. Invoice Management

Invoices preserve fixed-precision NPR snapshots and line items. Administrators may view and generate invoices only through the documented booking/invoice workflow. Later room-rate changes must not change an existing booking or invoice snapshot. Tax, service-charge and rounding policy require approval before final financial behavior is implemented.

## 9. Activities and Gallery

Activities and gallery items keep their active control separate from their publication control. An item can be operationally retained but not publicly published. Administrators should review content, media and publication settings before making an item public.

## 10. CMS and Settings

Use Settings for simple site configuration. Use structured CMS page/content records for managed pages.

CMS content uses:

* `draft`
* `published`
* `archived`

Only `published` content appears publicly. CMS `archived` is a content status and must not be confused with a booking status.

## 11. Review Moderation

Administrators review verified-stay submissions and apply the documented moderation action. Eligibility remains tied to a checked-out booking and a valid invitation; moderation cannot make an ineligible submission valid.

## 12. Contacts and Notifications

Administrators may review contact messages and in-app notifications. Version 1 notification channels are in-app notifications and SMTP email. SMS, push and WhatsApp are Future Enhancements.

Failed email delivery does not reverse a successful booking, payment or check-out transaction. Delivery failures are recorded for operational follow-up.

## 13. Administrator Accounts, Backups and Audit

Version 1 Administrator-account management must not offer Guest or specialized staff roles. Administrators with the authorized account-management capability may manage other Administrator records according to the approved security rules.

Backup and audit actions are protected Administrator functions. A backup operation must report success or failure honestly. Audit records must not be edited to hide an action.

## 14. Common Problems

### Session expired

Sign in again. If sessions expire unexpectedly often, report the time and page to the technical team; do not request or paste session cookie values.

### Room does not appear as available

Confirm that the room is `active`, published, suitable for the guest count and free of blocking booking overlaps for the complete date range.

### Booking remains pending after payment submission

Submission alone does not confirm a booking. An Administrator must manually verify a submitted attempt, after which Booking Service may transition the booking to `confirmed`.

### Review link does not work

Confirm that the booking is `checked_out`, the invitation has not expired and it has not already been used. Contact the guest house without sending the full token in an insecure channel.

## 15. Future Enhancements

The following are not Version 1 user workflows:

* Customer registration, login, profile and Customer Portal;
* Customer Management screens;
* Receptionist, Manager, Accountant and Content Manager access;
* multi-room booking;
* payment gateway processing;
* SMS, push and WhatsApp notifications.
