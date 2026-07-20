# Client Requirements Specification (CRS)

> **Version 1 synchronization:** The Version 1 actors are **Guest** (unauthenticated public visitor) and **Administrator** (authenticated staff user). Guests may book one room without registering. Registered-customer accounts, login, portal, saved booking history, and specialized staff roles are deferred to **Future Enhancements**. Transactional SMTP email and in-app administrator notifications remain in Version 1. See `00_V1_ARCHITECT_DECISIONS.md`, DEC-004 through DEC-006 and DEC-023.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Client Requirements Specification (CRS) |
| **Version** | 1.0 |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Project Goal

Develop a modern hotel website and management system for **Tara Guest House** that increases online reservations, improves customer experience, and enables hotel staff to manage website content, bookings, and business operations without requiring technical knowledge.

---

# 2. Business Goals

- Increase online bookings
- Modernize the hotel's online presence
- Make hotel information easy to access
- Reduce manual administrative work
- Allow the owner to update website content independently
- Improve customer trust and credibility

---

# 3. Target Audience

## Primary Audience

- International Tourists
- Families
- Couples
- Backpackers

## Secondary Audience

- Domestic Tourists
- Travel Agencies
- Tour Operators

---

# 4. User Types

## Guest

### Permissions

- View rooms
- Search rooms
- View room details
- Book a room
- Contact the hotel
- Leave reviews
- Upload payment voucher
- Track reservation status

---

## Registered Customer (Future Enhancement)

### Future Permissions

- Login
- View bookings
- Cancel reservations
- Update profile
- View invoices
- Submit reviews

---

## Administrator

### Permissions

- Access dashboard
- Manage rooms
- Manage gallery
- Manage bookings
- Manage reviews
- Manage activities
- Manage pricing
- Generate invoices
- Manage booking transitions; booking confirmation occurs only after verified payment through Booking Service.
- Upload and manage images

---

# 5. Functional Requirements

## 5.1 Homepage Module

The homepage shall include:

- Hero Section
- Room Search
- Room Preview
- Activities
- Customer Reviews
- Gallery
- Contact Information
- Google Map
- Footer

---

## 5.2 Room Module

The room management module shall support:

- Room Listing
- Room Details
- Multiple Images
- Amenities
- Room Price
- Capacity
- Availability Status
- Book Now functionality

---

## 5.3 Reservation Module

The reservation system shall include:

- Reservation Form
- Check-in Date
- Check-out Date
- Number of Adults
- Number of Children
- Special Requests
- Reservation Status Tracking

---

## 5.4 Payment Verification Module

As online payment gateways are not included in Version 1, customers shall be able to:

- Upload payment screenshot
- Enter transaction ID
- Select payment method
- Add payment notes

### Administrator Functions

- Verify payment
- Reject payment
- Request payment resubmission

---

## 5.5 Activities Module

The administrator shall be able to manage tourism activities including:

- Jeep Safari
- Canoeing
- Elephant Breeding Center
- Jungle Walk
- Bird Watching

Each activity shall contain:

- Price
- Description

Activity images, categories, duration, meeting point, participant limits and featured flags are Future Enhancements unless a later approved decision updates the database, API and UI together.

---

## 5.6 Gallery Module

Administrator capabilities:

- Upload images
- Edit captions
- Delete images
- Manage display order and separate active/publication controls

---

## 5.7 Review Module

### Guest

- Give rating
- Write review
- Use a secure single-use invitation linked to a checked-out booking

### Administrator

- Approve or reject pending reviews

---

## 5.8 Contact Module

### Customer

- Send inquiry

### Administrator

- Read inquiries
- Manage inquiries using the approved contact workflow after its status policy is confirmed

---

# 6. Non-Functional Requirements

## Performance

The system shall:

- Load quickly
- Use optimized images
- Support lazy loading

---

## Security

The application shall implement:

- Password Hashing
- Prepared Statements (PDO)
- CSRF Protection
- XSS Protection
- Secure Session Management
- Input Validation
- File Upload Validation

---

## Browser Compatibility

The system shall support:

- Google Chrome
- Mozilla Firefox
- Microsoft Edge
- Safari

---

## Responsive Design

The application shall function correctly on:

- Desktop
- Tablet
- Mobile

---

## Search Engine Optimization (SEO)

The website shall include:

- SEO-friendly URLs
- Page meta title and meta description
- Canonical URL derived from the current public route
- Open Graph title and description derived from page metadata
- Global/default Open Graph image stored in settings
- XML sitemap generated from public routes and published records
- Schema markup where appropriate

Meta keywords are not required in Version 1.

---

# 7. Future Features

The following features are planned for future releases and are **not included in Version 1**:

- Online Payment Gateway
- Marketing email campaigns (transactional SMTP email remains in Version 1)
- SMS Notifications
- Loyalty Program
- Coupons & Promotions
- Multi-language Support
- Travel Package Booking
- Airport Pickup Booking
- Blog System
- AI Chatbot

---

# 8. Out of Scope (Version 1)

The following features are explicitly excluded from Version 1:

- Mobile Application
- Multiple Hotel Branches
- Real-time Payment Gateway Integration
- Multi-currency Support
- Staff Attendance Management

---

# 9. Acceptance Criteria

The project shall be considered complete when:

- Customer can reserve a room successfully.
- Administrator can manage reservations.
- Administrator can manage room information.
- Gallery management is fully functional.
- Activities management is fully functional.
- Customer review system is operational.
- Website is fully responsive across supported devices.
- Security requirements have been implemented and tested successfully.

---

# 10. Architectural Recommendation

To support future business growth, the application shall be designed as a **reusable hotel management platform** rather than a system tied exclusively to Tara Guest House.

## Platform Design Principles

- Use generic entities such as:
  - `rooms`
  - `bookings`
  - `activities`
  - `reviews`
  - `gallery`
  - `settings`
  - `users`

- Store hotel-specific information in a configurable **Settings** module, including:
  - Hotel Name
  - Logo
  - Address
  - Contact Details
  - Social Media Links
  - Business Hours
  - SEO Configuration

This architecture enables the software to be reused for future hotel clients with minimal code changes by updating configuration data rather than rebuilding the application.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Pending |
| Client | Tara Guest House | Pending |
| Development Team | fero.dev | Pending |
