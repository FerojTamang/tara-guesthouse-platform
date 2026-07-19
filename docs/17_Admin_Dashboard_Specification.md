# Tara Guest House Management System

# Admin Dashboard Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Admin Dashboard Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Admin Dashboard Specification created |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. User Stories
5. Dashboard Objectives
6. Dashboard Layout
7. KPI Cards
8. Dashboard Widgets
9. Quick Actions
10. Reports
11. Global Search
12. Business Rules
13. Performance Requirements
14. Security Requirements
15. Accessibility
16. Acceptance Criteria
17. Future Enhancements
18. Related Modules

---

# 1. Purpose

The Admin Dashboard serves as the central control panel for the Tara Guest House Management System.

It provides administrators with a real-time overview of hotel operations, bookings, revenue, occupancy, customer activity, and website management.

The dashboard should enable staff to identify important business information within seconds.

---

# 2. Scope

The dashboard includes

- Business statistics
- Booking summary
- Revenue overview
- Room occupancy
- Recent bookings
- Recent reviews
- Notifications
- Reports
- Quick actions
- System status

Excluded

- Booking management logic
- Payment processing
- User management logic

---

# 3. Actors

## Administrator

Can

- View dashboard
- Access all modules
- Generate reports
- Monitor hotel operations

---

## Future Manager

Can

- View operational statistics
- Manage bookings
- Manage rooms

Limited administrative permissions.

---

# 4. User Stories

### As an Administrator

I want to immediately see today's hotel status

So I can make operational decisions.

---

### As an Administrator

I want quick access to frequently used actions

So I can save time.

---

### As an Administrator

I want business statistics

So I can understand hotel performance.

---

# 5. Dashboard Objectives

The dashboard shall

- Display important information immediately.
- Reduce navigation.
- Improve staff productivity.
- Support business decision-making.
- Provide real-time operational data.

---

# 6. Dashboard Layout

Recommended sections

Header

- Welcome message
- Current date & time
- Logged-in user

Top KPI Cards

Main Content

Left Column

- Booking Calendar
- Revenue Chart
- Occupancy Chart

Right Column

- Recent Bookings
- Notifications
- Recent Reviews

Bottom

- Quick Actions
- Reports
- Activity Log

---

# 7. KPI Cards

The dashboard should display

| KPI | Description |
|------|-------------|
| Total Rooms | Total rooms configured |
| Available Rooms | Currently available |
| Occupied Rooms | Currently occupied |
| Today's Bookings | New bookings today |
| Today's Check-ins | Guests arriving today |
| Today's Check-outs | Guests leaving today |
| Monthly Revenue | Revenue this month |
| Pending Payments | Awaiting verification |
| Pending Reviews | Reviews awaiting moderation |
| Unread Messages | Contact form submissions |

---

# 8. Dashboard Widgets

Widgets may include

- Revenue Chart
- Booking Trend
- Occupancy Rate
- Room Availability
- Popular Activities
- Recent Reviews
- Upcoming Reservations
- Calendar
- Weather (Future)
- Exchange Rate (Future)

Administrators may enable or disable widgets in future versions.

---

# 9. Quick Actions

Frequently used shortcuts

- Create Booking
- Add Room
- Add Activity
- Upload Gallery Image
- Edit Homepage
- View Reports
- Print Invoice
- Manage Reviews

---

# 10. Reports

Quick access to

- Booking Report
- Revenue Report
- Occupancy Report
- Room Report
- Activity Report
- Gallery Statistics
- Customer Review Report

Reports should support

- PDF export
- Excel export (Future)

---

# 11. Global Search

Search should support

- Booking Reference
- Guest Name
- Phone Number
- Email
- Room Number
- Invoice Number
- Activity Name

Search results should navigate directly to the relevant record.

---

# 12. Business Rules

Only authenticated users may access the dashboard.

Dashboard data should reflect current system information.

Unavailable modules should not be displayed.

Widgets should load independently to improve performance.

---

# 13. Performance Requirements

Dashboard initial load

Less than 3 seconds

Statistics refresh

Less than 2 seconds

Support at least

100 concurrent users

---

# 14. Security Requirements

Only authorized users may access dashboard data.

Role permissions must be enforced.

Sensitive financial information should only be visible to authorized roles.

All administrative actions should be logged.

---

# 15. Accessibility Requirements

The dashboard shall

- Support keyboard navigation.
- Meet WCAG 2.1 AA standards.
- Use clear colour contrast.
- Support screen readers.

---

# 16. Acceptance Criteria

The dashboard is complete when

- Administrators can monitor hotel operations.
- KPI cards display accurate data.
- Charts update correctly.
- Reports are accessible.
- Dashboard loads within performance targets.
- Access control is enforced.

---

# 17. Future Enhancements

- Drag-and-drop widgets
- Dashboard customization
- Dark mode
- Multi-language dashboard
- AI business insights
- Revenue forecasting
- Occupancy prediction
- Scheduled reports
- Mobile dashboard app
- Email summaries

---

# 18. Related Database Tables

- bookings
- booking_rooms
- rooms
- payments
- invoices
- reviews
- contact_messages
- notifications
- users
- audit_logs

---

# 19. Related API Endpoints

GET /dashboard

GET /dashboard/statistics

GET /dashboard/revenue

GET /dashboard/bookings

GET /dashboard/reviews

GET /dashboard/notifications

---

# 20. Related Modules

- Authentication
- Booking Management
- Room Management
- Gallery Management
- Activity Management
- CMS
- Review System
- Payment Management
- Notification System

---

# End of Document