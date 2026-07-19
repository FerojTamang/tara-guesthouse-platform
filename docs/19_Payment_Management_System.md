# Tara Guest House Management System

# Payment Management System Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Payment Management System Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Payment Management System Specification created |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. User Stories
5. Business Objectives
6. Payment Workflow
7. Payment Methods
8. Payment Verification
9. Invoice Management
10. Refund Management
11. Functional Requirements
12. Business Rules
13. Validation Rules
14. Security Requirements
15. Performance Requirements
16. Accessibility Requirements
17. Acceptance Criteria
18. Future Enhancements
19. Related Database Tables
20. Related API Endpoints
21. Related Modules

---

# 1. Purpose

The Payment Management System records, verifies, and manages guest payments for reservations.

Version 1 focuses on manual payment verification while providing a foundation for future online payment gateway integration.

---

# 2. Scope

Included

- QR Payment
- Bank Transfer
- Cash Payment
- Payment Verification
- Payment History
- Invoice Generation
- Refund Recording

Excluded

- Online Gateway Integration
- Automatic Refund Processing

---

# 3. Actors

## Guest

Can

- View payment instructions
- Upload payment proof
- Submit transaction reference
- View payment status
- Download invoice

Cannot

- Approve payments

---

## Administrator

Can

- Verify payments
- Reject payments
- Record cash payments
- Generate invoices
- Record refunds
- View payment reports

---

# 4. User Stories

### As a Guest

I want to upload my payment proof

So that the hotel can verify my reservation.

---

### As an Administrator

I want to verify guest payments

So that only valid reservations are confirmed.

---

### As an Administrator

I want to generate invoices

So guests receive proper payment records.

---

# 5. Business Objectives

The payment system shall

- Track every payment
- Prevent fraud
- Maintain complete financial records
- Support future gateway integration
- Simplify payment verification

---

# 6. Payment Workflow

Reservation Created

↓

Pending Payment

↓

Guest Selects Payment Method

↓

Guest Uploads Payment Proof

↓

Administrator Reviews Payment

↓

Approve or Reject

↓

Booking Confirmed

↓

Invoice Generated

---

# 7. Payment Methods

Version 1

- QR Code
- Bank Transfer
- Cash

Future

- eSewa
- Khalti
- Fonepay
- Stripe
- PayPal
- Visa / Mastercard

---

# 8. Payment Verification

Guest submits

- Transaction ID
- Amount
- Payment Date
- Payment Screenshot

Administrator reviews

- Amount
- Transaction ID
- Screenshot
- Booking

Status

- Pending
- Verified
- Rejected
- Refunded

---

# 8.1 Payment Status Timeline

Every payment shall progress through a predefined status timeline.

Workflow

Reservation Created

↓

Awaiting Payment

↓

Payment Submitted

↓

Payment Under Verification

↓

Payment Verified

↓

Booking Confirmed

↓

Invoice Generated

↓

Stay Completed

↓

Archived

The current payment status shall be visible to both the guest and administrator.

Each status transition shall record:

- Date
- Time
- Performed By
- Notes (Optional)

Status history shall never be deleted and must be available for auditing.

---

# 9. Invoice Management

Each invoice shall include

- Invoice Number
- Booking Reference
- Guest Name
- Payment Method
- Amount
- Tax
- Discount
- Total
- Payment Status
- Issue Date

Invoices shall be printable as PDF.

---

# 10. Refund Management

Administrator may record

- Refund Amount
- Refund Reason
- Refund Date
- Refund Method
- Notes

Refund history shall be retained.

---

# 11. Functional Requirements

The system shall provide

- Payment submission
- Payment verification
- Payment history
- Invoice generation
- Refund recording
- Payment search
- Payment reports

---

# 12. Business Rules

Payments must be linked to bookings.

Only verified payments confirm reservations.

Every payment must have a status.

Invoices cannot be deleted.

Refunds must reference an existing payment.

---

# 13. Validation Rules

Amount

Greater than zero

Transaction Reference

Maximum 100 characters

Payment Screenshot

JPG

PNG

WEBP

Maximum 10 MB

---

# 14. Security Requirements

Only administrators may verify payments.

Payment uploads must be validated.

All financial actions shall be recorded in the audit log.

Prevent duplicate payment submissions.

Sensitive payment data shall be protected.

---

# 15. Performance Requirements

Payment submission

Less than 3 seconds

Invoice generation

Less than 5 seconds

Payment search

Less than 2 seconds

---

# 16. Accessibility Requirements

The payment module shall

- Support keyboard navigation
- Meet WCAG 2.1 AA
- Display accessible validation messages
- Support screen readers

---

# 17. Acceptance Criteria

The module is complete when

- Guests can submit payment information.
- Administrators can verify payments.
- Payment status updates correctly.
- Invoices are generated successfully.
- Refunds are recorded.
- Audit logs capture all payment actions.

---

# 18. Future Enhancements

- eSewa Integration
- Khalti Integration
- Fonepay Integration
- Stripe Integration
- PayPal Integration
- Automated Refunds
- Multi-Currency Support
- Tax Calculation Engine
- Payment Analytics Dashboard
- Scheduled Payment Reminders

---

# 19. Related Database Tables

- payments
- payment_methods
- invoices
- invoice_items
- bookings
- users
- audit_logs

---

# 20. Related API Endpoints

GET /payments

GET /payments/{id}

POST /payments

PUT /payments/{id}

POST /payments/{id}/verify

POST /payments/{id}/reject

GET /invoices/{id}

GET /payments/report

---

# 21. Related Modules

- Booking System
- Authentication
- Admin Dashboard
- Notification System
- Invoice Management

---

# End of Document