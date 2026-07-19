# Tara Guest House Management System

# Booking System Specification

---

## Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Booking System Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |
| Review Frequency | Every Sprint |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Booking System Specification created. |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. Business Objectives
5. Booking Workflow
6. Customer Journey
7. Booking States
8. Business Rules
9. Functional Requirements
10. Non-Functional Requirements
11. Future Enhancements

---

# 1. Purpose

The Booking System is the core functionality of the Tara Guest House Management System.

It enables customers to browse rooms, check availability, submit reservations, upload payment proof (if applicable), and receive booking confirmation after administrator verification.

The system is designed to minimize manual work for staff while providing guests with a smooth, secure, and modern reservation experience.

---

# 2. Scope

This specification covers:

- Room availability
- Reservation creation
- Customer information collection
- Payment submission
- Booking verification
- Booking confirmation
- Booking cancellation
- Invoice generation
- Notification process

This document does not cover:

- Online payment gateway integration (future version)
- Restaurant reservations
- Event bookings
- Multi-property reservations

---

# 3. Actors

## Guest

A visitor who wants to reserve a room.

Permissions:

- Browse rooms
- View room details
- Check availability
- Create booking
- Upload payment proof
- View booking status
- Cancel booking (before confirmation)

---

## Administrator

Responsible for managing reservations.

Permissions:

- View bookings
- Confirm bookings
- Reject bookings
- Edit bookings
- Print invoices
- Verify payments
- Assign rooms
- Change booking status

---

## Receptionist (Future)

Responsible for front desk operations.

Permissions may include:

- Walk-in bookings
- Guest check-in
- Guest check-out
- Invoice printing
- Booking modifications

---

# 4. Business Objectives

The booking system should:

- Reduce manual reservation management.
- Prevent double bookings.
- Provide a simple booking experience.
- Support manual payment verification.
- Generate professional invoices.
- Improve customer satisfaction.
- Support future online payment gateways.

---

# 5. Booking Workflow

```
Customer Visits Website
        │
        ▼
Browse Available Rooms
        │
        ▼
View Room Details
        │
        ▼
Select Check-in & Check-out Dates
        │
        ▼
System Checks Availability
        │
        ▼
Available?
        │
 ┌──────┴──────┐
 │             │
No            Yes
 │             │
 ▼             ▼
Show Message   Booking Form
               │
               ▼
Customer Enters Information
               │
               ▼
Select Payment Method
               │
               ▼
Upload Payment Voucher (Optional)
               │
               ▼
Booking Submitted
               │
               ▼
Status = Pending
               │
               ▼
Administrator Reviews Booking
               │
 ┌──────┴──────┐
 │             │
Reject      Approve
 │             │
 ▼             ▼
Cancelled   Confirmed
               │
               ▼
Invoice Generated
               │
               ▼
Customer Notification
```

---

# 6. Customer Journey

A typical customer experience:

1. Open the Tara Guest House website.
2. Browse available rooms.
3. View room photos, amenities, and pricing.
4. Choose check-in and check-out dates.
5. Select the desired room.
6. Complete the booking form.
7. Choose a payment method.
8. Upload payment proof if required.
9. Submit the reservation.
10. Receive confirmation that the booking is pending.
11. Wait for administrator verification.
12. Receive final confirmation.
13. Arrive at the guest house and check in.

---

# 7. Booking States

Every booking must have one of the following statuses:

| Status | Description |
|---------|-------------|
| Pending | Booking submitted and awaiting review |
| Confirmed | Booking approved |
| Cancelled | Booking cancelled |
| Checked In | Guest has arrived |
| Checked Out | Guest has departed |
| Rejected | Booking rejected by administrator |

The booking status must always be visible to administrators.

---

# 8. Business Rules

The following rules must always be enforced:

- A room cannot be double-booked.
- Check-out date must be later than check-in date.
- Guests must provide their full name.
- A valid phone number is required.
- Maximum room occupancy must not be exceeded.
- Only available rooms can be reserved.
- Cancelled bookings release room availability.
- Confirmed bookings generate an invoice.
- Every booking must have a unique booking reference.
- Payment verification must occur before confirmation when manual payment is used.
- All booking activities must be recorded in the audit log.

---

# 9. Functional Requirements

The system shall allow guests to:

- Search rooms
- View room details
- Check room availability
- Make reservations
- Upload payment vouchers
- Receive booking confirmation
- Cancel pending bookings

The system shall allow administrators to:

- View all bookings
- Search bookings
- Filter bookings by status
- Approve bookings
- Reject bookings
- Print invoices
- Update booking status
- Verify payments
- Export booking reports

---

# 10. Non-Functional Requirements

The booking system should:

- Be responsive on mobile devices.
- Complete booking submission within three seconds under normal conditions.
- Validate all user input.
- Protect against SQL Injection.
- Protect against Cross-Site Scripting (XSS).
- Protect against Cross-Site Request Forgery (CSRF).
- Use secure password hashing where authentication is involved.
- Maintain audit logs for important booking actions.

---

# 11. Future Enhancements

The booking system should be designed to support future improvements without major redesign.

Potential enhancements include:

- Online payments (eSewa, Khalti, FonePay, Stripe)
- OTP verification
- WhatsApp booking confirmation
- Email confirmation
- SMS notifications
- Booking modification by customers
- Seasonal pricing
- Promotional discounts
- Coupon codes
- Multi-room booking
- Group reservations
- Multi-language support
- Mobile application integration
- AI-powered room recommendations

---

---

# 12. Booking Form Specification

The booking form is the primary entry point for customers making reservations.

The form must be simple, responsive, and easy to complete within two minutes.

---

## Customer Information

| Field | Required | Validation | Example |
|---------|----------|------------|----------|
| Full Name | Yes | 3–100 characters | John Smith |
| Phone Number | Yes | Valid phone number | +9779812345678 |
| Email Address | Yes | Valid email format | john@example.com |
| Nationality | Yes | Select list | Nepal |
| Number of Adults | Yes | Minimum 1 | 2 |
| Number of Children | No | Minimum 0 | 1 |
| Special Requests | No | Maximum 500 characters | Extra pillow |

---

## Reservation Information

| Field | Required | Validation |
|---------|----------|------------|
| Check-in Date | Yes | Must be today or later |
| Check-out Date | Yes | Must be after check-in |
| Room Type | Yes | Must exist |
| Selected Room | Yes | Must be available |
| Number of Nights | Auto | Calculated |
| Total Guests | Auto | Adults + Children |

---

## Payment Information

| Field | Required | Validation |
|---------|----------|------------|
| Payment Method | Yes | Active payment methods only |
| Transaction Code | Optional | Required if manual transfer |
| Payment Voucher | Optional | JPG, PNG, WEBP, PDF |
| Payment Note | Optional | Maximum 500 characters |

---

## Validation Errors

Examples:

- Full name is required.
- Please enter a valid phone number.
- Check-out date must be after check-in.
- Selected room is unavailable.
- Maximum room capacity exceeded.
- Invalid payment voucher.
- Booking dates overlap an existing reservation.

---

# 13. Room Availability Logic

The system shall determine room availability using the following process.

1. Customer selects check-in date.
2. Customer selects check-out date.
3. Customer selects room type.
4. System searches existing bookings.
5. Rooms with overlapping confirmed bookings are excluded.
6. Rooms under maintenance are excluded.
7. Rooms marked inactive are excluded.
8. Remaining rooms are displayed.

---

## Availability Rules

A room is available when:

- Status = Available
- No overlapping confirmed booking
- No maintenance schedule

A room is unavailable when:

- Status = Maintenance
- Status = Inactive
- Already reserved during selected dates

---

# 14. Reservation Conflict Rules

The system must prevent double bookings.

Example

Existing Booking

```
Room 101

Check-in

10 July

Check-out

13 July
```

Customer tries

```
Room 101

Check-in

12 July

Check-out

15 July
```

Result

Booking rejected.

Reason

Date overlap detected.

---

## Conflict Detection

The following situations must be blocked:

- Partial overlap
- Full overlap
- Same-day overlap
- Duplicate booking attempt

---

# 15. Booking Reference Generation

Every booking shall receive a unique booking reference.

Example

```
TGH-2026-000001
TGH-2026-000002
TGH-2026-000003
```

Structure

```
Hotel Prefix

Year

Running Number
```

Benefits

- Easy searching
- Professional invoices
- Telephone support
- Customer identification

---

# 16. Booking Lifecycle

```
Pending

↓

Payment Verification

↓

Confirmed

↓

Checked In

↓

Checked Out

↓

Archived
```

Alternative Flow

```
Pending

↓

Rejected
```

Alternative Flow

```
Pending

↓

Cancelled by Customer
```

---

## Allowed Status Changes

| Current | Next |
|----------|------|
| Pending | Confirmed |
| Pending | Rejected |
| Pending | Cancelled |
| Confirmed | Checked In |
| Checked In | Checked Out |

Any other transition must be rejected.

---

# 17. Payment Verification Workflow

Customer

↓

Uploads voucher

↓

Booking Pending

↓

Administrator reviews payment

↓

Verified?

↓

Yes

↓

Booking Confirmed

↓

Invoice Generated

↓

Notification Sent

OR

↓

Rejected

↓

Customer Notified

↓

Booking Remains Pending

---

## Verification Checklist

Administrator should verify:

- Correct amount
- Correct payment method
- Valid transaction code
- Voucher image readable
- Payment date reasonable

---

# 18. Invoice Generation

Invoices are automatically generated after booking confirmation.

Invoice contains

- Invoice Number
- Booking Reference
- Guest Name
- Room Details
- Check-in Date
- Check-out Date
- Number of Nights
- Room Charges
- Taxes
- Discounts
- Total Amount
- Payment Status
- QR Code (Future)
- Hotel Contact Details

Invoices must be printable as PDF.

---

# 19. Cancellation Policy

Customer may cancel

Only while booking is Pending.

Administrator may cancel

Any booking with proper reason.

Cancellation Reasons

- Guest Request
- Payment Failure
- Room Unavailable
- Hotel Maintenance
- Force Majeure

Cancellation history should remain stored.

---

# 20. Notification Flow

Notifications should be generated for:

Customer

- Booking Submitted
- Booking Confirmed
- Booking Rejected
- Booking Cancelled

Administrator

- New Booking
- Payment Uploaded
- Booking Cancelled

Future Channels

- Email
- SMS
- WhatsApp
- Push Notification

---

# 21. Booking Dashboard

The administrator dashboard should display:

Summary Cards

- Pending Bookings
- Confirmed Bookings
- Today's Arrivals
- Today's Departures
- Monthly Revenue

Booking Table

Columns

- Booking Reference
- Guest Name
- Room
- Check-in
- Check-out
- Total Amount
- Payment Status
- Booking Status
- Actions

Actions

- View
- Edit
- Confirm
- Reject
- Print Invoice
- Delete (Soft Delete)

---

# 22. Security Requirements

The booking system must implement:

- SQL Injection prevention
- XSS prevention
- CSRF protection
- Server-side validation
- Input sanitization
- Output escaping
- Audit logging
- Role-based authorization
- Secure file upload validation
- Maximum upload limits
- Rate limiting

---

# 23. Future Improvements

Future versions may include:

- Online payment gateways
- Dynamic pricing
- Seasonal pricing
- Discount coupons
- Promo codes
- Loyalty program
- Referral rewards
- AI room recommendations
- Calendar synchronization
- Google Hotel integration
- Booking.com synchronization
- Airbnb synchronization
- Multi-property booking
- Mobile application
- Customer portal
- Live chat
- AI concierge

---

# End of Booking System Specification