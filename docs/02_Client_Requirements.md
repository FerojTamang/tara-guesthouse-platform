# Client Requirements Specification (CRS)

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

## Registered Customer

### Permissions

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
- Approve reservations
- Upload and manage images

---

# 5. Functional Requirements

## 5.1 Homepage Module

The homepage shall include:

- Hero Section
- Room Search
- Featured Rooms
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

- Images
- Price
- Description
- Duration

---

## 5.6 Gallery Module

Administrator capabilities:

- Upload images
- Edit captions
- Delete images
- Feature images

---

## 5.7 Review Module

### Customer

- Give rating
- Write review
- Upload optional image

### Administrator

- Reply to reviews
- Hide inappropriate reviews
- Delete spam reviews

---

## 5.8 Contact Module

### Customer

- Send inquiry

### Administrator

- Read inquiries
- Reply to inquiries
- Archive messages

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
- Meta Tags
- Open Graph Metadata
- Schema Markup
- XML Sitemap

---

# 7. Future Features

The following features are planned for future releases and are **not included in Version 1**:

- Online Payment Gateway
- Email Notifications
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