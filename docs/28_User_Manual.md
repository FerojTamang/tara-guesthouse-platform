# User Manual

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | User Manual |
| **Document No.** | 28 |
| **Version** | 1.0 |
| **Status** | Approved |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document provides instructions for using the Tara Guest House Management System.

It is intended for:

- Administrators
- Managers
- Receptionists
- Customers

The objective of this manual is to help users understand and operate the system efficiently and effectively.

---

# 2. Introduction

The Tara Guest House Management System is a web-based application designed to manage guest house operations.

The system provides functionality for:

- Room Management
- Booking Management
- Payment Management
- Invoice Management
- Review Management
- Gallery Management
- Activity Management
- CMS Management
- Notifications
- Reports
- User Management

---

# 3. System Overview

The system consists of two primary areas:

```text
Public Website
      │
      ▼
- Homepage
- Rooms
- Activities
- Gallery
- Reviews
- Contact
```

```text
Administration Panel
      │
      ▼
- Dashboard
- Users
- Bookings
- Payments
- Reports
- CMS
- Settings
```

---

# 4. User Roles

The system supports the following roles.

| Role | Description |
|------|-------------|
| Administrator | Full system access |
| Manager | Operational management |
| Receptionist | Booking and payment management |
| Customer | Booking and profile management |

---

## Role Responsibilities

### Administrator

- Manage Users
- Manage CMS
- View Reports
- Configure Settings
- Monitor System Activity

### Manager

- Monitor Operations
- Manage Rooms
- Review Reports
- Approve Activities

### Receptionist

- Manage Bookings
- Verify Payments
- Generate Invoices
- Assist Customers

### Customer

- View Rooms
- Create Bookings
- Submit Reviews
- Manage Profile

---

# 5. Login

To access the administration panel:

1. Open the application URL.
2. Navigate to the Login page.
3. Enter your email address.
4. Enter your password.
5. Click the "Login" button.

---

## Login Flow

```text
Open Website
      │
      ▼
Login Page
      │
      ▼
Enter Credentials
      │
      ▼
Authentication
      │
      ▼
Dashboard
```

---

## Login Errors

| Error | Description |
|------|-------------|
| Invalid Credentials | Email or password incorrect |
| Account Disabled | Contact Administrator |
| Session Expired | Login again |

---

# 6. Dashboard

The Dashboard provides an overview of system activities.

## Dashboard Widgets

- Total Bookings
- Available Rooms
- Pending Payments
- Recent Reviews
- Notifications
- Revenue Summary

---

## Example Dashboard

```text
---------------------------------
Total Rooms         : 25
Available Rooms     : 10
Today's Bookings    : 8
Pending Payments    : 2
Monthly Revenue     : NPR 250,000
---------------------------------
```

---

# 7. Room Management

Room Management allows authorized users to manage room information.

## Available Actions

- Create Room
- Update Room
- Delete Room
- Upload Images
- Assign Amenities

---

## Create Room

1. Navigate to Rooms.
2. Click "Add Room."
3. Enter room details.
4. Upload images.
5. Save the room.

---

## Room Information

- Room Name
- Room Type
- Price
- Capacity
- Description
- Amenities
- Images

---

# 8. Booking Management

Booking Management allows users to manage reservations.

## Booking Actions

- Create Booking
- Edit Booking
- Cancel Booking
- View Booking History

---

## Booking Workflow

```text
Select Room
      │
      ▼
Choose Dates
      │
      ▼
Confirm Booking
      │
      ▼
Payment
      │
      ▼
Booking Confirmation
```

---

## Booking Status

| Status | Description |
|------|-------------|
| Pending | Awaiting Approval |
| Confirmed | Reservation Approved |
| Completed | Stay Completed |
| Cancelled | Reservation Cancelled |

---

# 9. Payment Management

The system supports payment tracking and verification.

## Payment Actions

- View Payments
- Verify Payments
- Update Payment Status
- Generate Receipts

---

## Payment Status

| Status | Description |
|------|-------------|
| Pending | Awaiting Verification |
| Paid | Payment Received |
| Failed | Payment Failed |
| Refunded | Payment Returned |

---

## Payment Workflow

```text
Booking
   │
   ▼
Payment
   │
   ▼
Verification
   │
   ▼
Confirmation
```

---

# 10. Invoice Management

Invoices are automatically generated after successful payment.

## Invoice Information

- Invoice Number
- Booking ID
- Customer Name
- Payment Details
- Total Amount
- Invoice Date

---

## Invoice Actions

- View Invoice
- Download Invoice
- Print Invoice

---

## End of Part 1

Part 2 includes:

- Gallery Management
- Activity Management
- CMS Management
- Review Management
- Notification Management
- Reports
- Settings
- Logout
- FAQ
- Troubleshooting
- Conclusion
- Approval

---

# 11. Gallery Management

Gallery Management allows administrators to manage images displayed on the website.

## Available Actions

- Upload Images
- Edit Gallery Items
- Delete Images
- Assign Categories

---

## Gallery Categories

- Rooms
- Activities
- Events
- Guest Experiences
- Facilities

---

## Upload Process

```text
Select Image
      │
      ▼
Validate File
      │
      ▼
Upload
      │
      ▼
Save Information
      │
      ▼
Display on Website
```

---

# 12. Activity Management

Activities represent services or experiences offered by the guest house.

## Examples

- Jungle Safari
- Canoeing
- Cultural Programs
- Cycling
- Local Tours

---

## Activity Actions

- Add Activity
- Update Activity
- Delete Activity
- Upload Images

---

## Activity Information

- Activity Name
- Description
- Price
- Duration
- Availability
- Images

---

# 13. CMS Management

The Content Management System (CMS) allows administrators to manage website content without modifying source code.

## Editable Sections

- Homepage Banner
- About Us
- Contact Information
- Social Media Links
- SEO Settings
- Footer Content

---

## CMS Workflow

```text
Administrator
       │
       ▼
Edit Content
       │
       ▼
Save Changes
       │
       ▼
Database Updated
       │
       ▼
Website Updated
```

---

# 14. Review Management

Customers may submit reviews after completing their stay.

## Review Actions

- Submit Review
- View Reviews
- Approve Reviews
- Delete Reviews

---

## Review Workflow

```text
Completed Booking
        │
        ▼
Submit Review
        │
        ▼
Admin Approval
        │
        ▼
Display on Website
```

---

## Review Information

- Customer Name
- Rating
- Comment
- Review Date

---

# 15. Notification Management

Notifications inform users about important events.

## Notification Examples

- Booking Confirmed
- Payment Received
- Booking Cancelled
- New Review Submitted
- System Updates

---

## Notification Flow

```text
Event Occurs
      │
      ▼
Create Notification
      │
      ▼
Store Notification
      │
      ▼
Display to User
```

---

# 16. Reports

The Reports module provides operational insights.

## Available Reports

- Booking Reports
- Revenue Reports
- Payment Reports
- Customer Reports
- Room Occupancy Reports

---

## Report Actions

- View Reports
- Filter Reports
- Export Reports
- Print Reports

---

# 17. Settings

The Settings module allows administrators to configure system preferences.

## Settings Categories

- General Settings
- Contact Information
- Email Settings
- SEO Settings
- System Configuration

---

## Examples

```text
Guest House Name
Address
Phone Number
Email Address
Logo
Timezone
Currency
```

---

# 18. Logout

Users should always logout after completing their work.

## Logout Procedure

1. Click the user profile menu.
2. Select "Logout."
3. Wait for confirmation.
4. Close the browser if using a shared device.

---

## Logout Flow

```text
Dashboard
    │
    ▼
Logout Button
    │
    ▼
Destroy Session
    │
    ▼
Login Page
```

---

# 19. Frequently Asked Questions (FAQ)

### Q1. I forgot my password.

Contact the system administrator to reset your password.

---

### Q2. Why can't I access certain pages?

Your user role may not have sufficient permissions.

---

### Q3. My booking is not visible.

Refresh the page and verify the booking status. If the issue persists, contact support.

---

### Q4. I cannot upload images.

Verify:

- File size
- File type
- Internet connection

---

### Q5. Why am I automatically logged out?

Your session may have expired due to inactivity.

---

# 20. Troubleshooting

| Problem | Solution |
|--------|---------|
| Cannot Login | Verify credentials |
| Booking Failure | Verify room availability |
| Payment Issue | Contact Administrator |
| Missing Images | Re-upload images |
| Slow Performance | Refresh the application |
| Access Denied | Verify permissions |

---

## Support Procedure

```text
User Problem
      │
      ▼
Check User Manual
      │
      ▼
Contact Reception
      │
      ▼
Contact Administrator
      │
      ▼
Technical Support
```

---

# 21. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md
- 27_Testing_Strategy.md
- 29_Developer_Guide.md

---

# 22. Conclusion

The User Manual provides guidance for operating the Tara Guest House Management System effectively.

By following this manual, users can:

- Navigate the system confidently.
- Perform daily tasks efficiently.
- Resolve common issues.
- Understand system workflows.
- Use the application securely.

This manual should be updated whenever significant functionality is added or modified.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Approved |
| Technical Architect | fero.dev | Approved |
| Administrator | Pending | Pending |
| Receptionist | Pending | Pending |
| Customer Representative | Pending | Pending |