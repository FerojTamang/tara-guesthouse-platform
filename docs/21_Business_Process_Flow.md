# Tara Guest House Management System

# Business Process Flow

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Business Process Flow |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Business Process Flow created |

---

# Table of Contents

1. Purpose
2. Business Objectives
3. Booking Process
4. Payment Process
5. Check-in Process
6. Check-out Process
7. Room Management Process
8. Activity Management Process
9. Gallery Management Process
10. Content Management Process
11. Review Process
12. Contact Process
13. Authentication Process
14. Notification Process
15. Related Modules

---

# 1. Purpose

This document defines every major business process within the Tara Guest House Management System.

Its purpose is to ensure that developers, designers, testers, and project managers have a shared understanding of how the hotel operates before implementation begins.

This document serves as the bridge between business requirements and technical design.

---

# 2. Business Objectives

The system shall:

- Standardize hotel operations.
- Improve customer experience.
- Reduce manual work.
- Improve booking management.
- Improve payment verification.
- Support future scalability.
- Maintain complete audit history.

---

# 3. Booking Process

## Workflow

Guest

↓

Browse Rooms

↓

View Room Details

↓

Check Availability

↓

Select Room

↓

Choose Check-in & Check-out Dates

↓

Enter Guest Information

↓

Submit Reservation

↓

Booking Created (Pending Payment)

↓

Payment Process Begins

↓

Payment Verified

↓

Booking Confirmed

↓

Guest Receives Confirmation

↓

Check-in

↓

Stay

↓

Check-out

↓

Review Invitation

---

## Business Rules

- Guest cannot reserve unavailable rooms.
- Check-out date must be after check-in.
- Reservation expires if payment is not received within the configured time limit.
- Booking reference must be unique.
- Booking status shall be tracked throughout its lifecycle.

---

## Database Tables

- bookings
- booking_rooms
- rooms
- payments
- users
- notifications

---

## APIs

POST /bookings

GET /rooms

GET /availability

PUT /bookings/{id}

---

# 4. Payment Process

## Workflow

Booking Created

↓

Awaiting Payment

↓

Guest Selects Payment Method

↓

Uploads Payment Proof

↓

Payment Submitted

↓

Admin Verification

↓

Payment Approved

↓

Invoice Generated

↓

Booking Confirmed

---

## Business Rules

- Every payment belongs to one booking.
- Duplicate transaction references are not allowed.
- Every verification action shall be logged.
- Payment timeline shall be preserved permanently.

---

## Database Tables

- payments
- payment_methods
- invoices
- bookings
- audit_logs

---

## APIs

POST /payments

POST /payments/{id}/verify

GET /payments

---

# 5. Check-in Process

## Workflow

Booking Confirmed

↓

Guest Arrives

↓

Identity Verification

↓

Assign Room

↓

Booking Status = Checked In

↓

Room Status = Occupied

---

## Business Rules

- Only confirmed bookings may check in.
- Room must be available.
- Check-in date cannot be before reservation date.
- Identity verification should be completed by staff.

---

# 6. Check-out Process

## Workflow

Guest Requests Check-out

↓

Room Inspection

↓

Outstanding Balance Check

↓

Invoice Finalization

↓

Payment Verification

↓

Booking Completed

↓

Room Status = Available

↓

Review Invitation

---

## Business Rules

- Outstanding payments must be resolved before completion.
- Room status changes back to available after successful check-out.
- Completed bookings become read-only except for administrative updates.

---

# 7. Room Management Process

## Workflow

Administrator

↓

Create Room

↓

Upload Images

↓

Assign Room Type

↓

Assign Amenities

↓

Set Pricing

↓

Publish Room

↓

Visible on Website

↓

Customer Booking

---

## Business Rules

- Room number must be unique.
- Inactive rooms are hidden from guests.
- Room prices are managed only by administrators.

---

# 8. Activity Management Process

## Workflow

Administrator

↓

Create Activity

↓

Set Description

↓

Upload Images

↓

Set Price

↓

Publish

↓

Visible on Website

↓

Customer Includes Activity During Booking

---

## Business Rules

- Activities can be enabled or disabled.
- Activity prices are managed through the admin panel.
- Activities may be booked independently or together with room reservations (future enhancement).

---

# 9. Gallery Management Process

## Workflow

Administrator

↓

Upload Images

↓

Optimize Image

↓

Assign Category

↓

Publish

↓

Visible on Website

---

## Business Rules

- Only administrators can manage gallery content.
- Images shall support captions and alt text for accessibility.
- Gallery categories should be configurable.

---

# 10. Content Management Process

## Workflow

Administrator

↓

Select Website Page

↓

Edit Content

↓

Preview Changes

↓

Publish

↓

Website Updated

---

## Business Rules

- Draft changes shall not appear publicly until published.
- Previous versions may be retained for rollback (future enhancement).
- Only authorized administrators may publish content.

---

# 11. Review Process

## Workflow

Guest Completes Stay

↓

Review Invitation

↓

Guest Submits Review

↓

Review Status = Pending

↓

Administrator Moderation

↓

Approved

↓

Published

↓

Admin Reply (Optional)

---

## Business Rules

- Reviews should be linked to completed bookings.
- Only approved reviews are publicly visible.
- All moderation actions are recorded in the audit log.

---

# 12. Contact Process

## Workflow

Guest Submits Contact Form

↓

System Stores Message

↓

Administrator Notification

↓

Administrator Reviews Message

↓

Reply Sent

↓

Message Closed

---

## Business Rules

- Contact messages shall be retained for reference.
- Administrators may mark messages as resolved.

---

# 13. Authentication Process

## Workflow

User Login

↓

Credential Validation

↓

Permission Check

↓

Dashboard Access

↓

Session Created

↓

Logout

↓

Session Destroyed

---

## Business Rules

- Only active users may log in.
- Passwords shall be securely hashed.
- Failed login attempts shall be logged.
- Sessions expire after inactivity.

---

# 14. Notification Process

## Workflow

Business Event

↓

Notification Created

↓

Stored in Notification Center

↓

Email Sent

↓

Recipient Reads Notification

↓

Notification Marked as Read

---

## Business Rules

- Notifications shall respect user permissions.
- Critical notifications require acknowledgement.
- Notification history shall be retained.

---

# 15. Related Modules

- Booking Management
- Room Management
- Payment Management
- Review System
- CMS
- Gallery Management
- Notification System
- Authentication
- Admin Dashboard

---

# End of Document