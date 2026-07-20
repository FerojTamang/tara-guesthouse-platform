# Frontend Architecture

> **Approved Version 1 frontend:** PHP-rendered views use HTML5, CSS3, Bootstrap 5, and vanilla JavaScript ES6+. Fetch/AJAX is selective and same-origin through `/api/v1/...`; protected admin JSON calls use `/api/v1/admin/...` and the PHP session with CSRF. This is not an SPA. Customer-portal UI, WebSockets, and other advanced client applications are future enhancements.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Frontend Architecture |
| **Document No.** | 24 |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the frontend architecture of the Tara Guest House Management System.

It establishes standards for:

- User Interface Design
- Page Organization
- Component Reusability
- JavaScript Architecture
- Asset Management
- Responsive Design
- Frontend Security
- Performance Optimization

The frontend architecture has been designed to provide a consistent, responsive, secure, and maintainable user experience across both the public website and administrative dashboard.

All frontend development activities must adhere to the standards defined in this document.

---

# 2. Frontend Overview

The frontend consists of two major applications:

1. Public Website
2. Administrative Dashboard

Both applications share a common architecture while maintaining separate layouts and responsibilities.

## Public Website

- Homepage
- About
- Rooms
- Activities
- Gallery
- Reviews
- Contact
- Booking

## Administrative Dashboard

- Dashboard
- Room Management
- Booking Management
- Payment Management
- CMS
- Users
- Notifications
- Reports
- Settings

---

# 3. Technology Stack

The frontend uses the following technologies.

| Technology | Purpose |
|------------|---------|
| HTML5 | Page Structure |
| CSS3 | Styling |
| Bootstrap 5 | Responsive Framework |
| JavaScript ES6 | Client Logic |
| AJAX | Asynchronous Requests |
| Fetch API | Backend Communication |

Chart.js, SweetAlert2, Flatpickr and Font Awesome are not approved Version 1 dependencies. Dashboard data uses accessible HTML/CSS/Bootstrap presentation; confirmations/alerts use approved Bootstrap/native patterns; date fields use accessible native inputs unless a later dependency decision is recorded.

The frontend architecture intentionally avoids heavy JavaScript frameworks to maintain simplicity, performance, and compatibility with the project's PHP MVC backend.

---

# 4. Frontend Layers

The frontend follows a layered architecture.

```text
Client Browser
      │
      ▼
HTML Templates
      │
      ▼
Bootstrap Components
      │
      ▼
JavaScript Modules
      │
      ▼
AJAX / Fetch API
      │
      ▼
REST API
      │
      ▼
PHP Backend
      │
      ▼
MariaDB
```

## Layer Responsibilities

| Layer | Responsibility |
|------|------|
| HTML | Structure |
| Bootstrap | Responsive UI |
| JavaScript | Interactivity |
| Fetch API | API Communication |
| REST API | Data Exchange |
| PHP | Business Logic |
| MariaDB | Data Storage |

---

# 5. Frontend Directory Structure

```text
resources/
│
└── views/
    │
    ├── layouts/
    │   ├── main.php
    │   └── admin.php
    │
    ├── website/
    │   ├── home/
    │   ├── rooms/
    │   ├── activities/
    │   ├── gallery/
    │   ├── reviews/
    │   ├── contact/
    │   └── booking/
    │
    └── admin/
        ├── dashboard/
        ├── rooms/
        ├── bookings/
        ├── payments/
        ├── cms/
        ├── users/
        ├── reports/
        └── settings/
```

This structure aligns with:

- Project Architecture
- Folder Structure
- REST API Specification
- Module Specifications

---

# 6. Public Website Architecture

The public website is designed to provide visitors with information about the guest house and facilitate online reservations.

## Website Pages

- Home
- About Us
- Rooms
- Room Details
- Activities
- Gallery
- Reviews
- Contact
- Booking
- Booking Confirmation

## Website Navigation

```text
Home
│
├── About
├── Rooms
│    └── Room Details
├── Activities
├── Gallery
├── Reviews
├── Contact
└── Booking
```

## Public Website Responsibilities

- Display guest house information.
- Display room listings.
- Allow online reservations.
- Display activities and gallery.
- Collect customer inquiries.
- Display customer reviews.
- Provide contact information.

---

# 7. Admin Dashboard Architecture

The administrative dashboard provides centralized management of the entire system.

## Dashboard Modules

- Dashboard
- Users
- Rooms
- Bookings
- Payments
- Notifications
- Reviews
- CMS
- Reports
- Settings

## Dashboard Navigation

```text
Dashboard
│
├── Users
├── Rooms
├── Bookings
├── Payments
├── Reviews
├── CMS
├── Reports
└── Settings
```

## Dashboard Responsibilities

- Manage users.
- Manage rooms.
- Manage reservations.
- Manage payments.
- Manage CMS content.
- Review system notifications.
- Generate reports.
- Configure application settings.

---

# 8. Frontend Design Principles

The frontend follows the following principles:

- Responsive by Default
- Mobile First Design
- Reusable Components
- Consistent User Experience
- Accessibility
- Performance Optimization
- Security by Default
- Simplicity
- Maintainability
- Scalability

---

# 9. Frontend Goals

The primary goals of the frontend architecture are:

- Provide an excellent user experience.
- Ensure responsive layouts across all devices.
- Maintain a consistent design language.
- Support future feature expansion.
- Minimize frontend complexity.
- Improve performance and loading times.
- Reduce maintenance costs.
- Simplify developer onboarding.

---

# 10. Alignment with Other Documents

This document has been designed to align with:

- 04_Project_Architecture.md
- 05_Folder_Structure.md
- 06_Coding_Standards.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- Numbered module and process specifications `11_Booking_System_Specification.md` through `22_System_State_Machine.md` (see `33_Documentation_Index.md` for each exact filename)
- 23_REST_API_Specification.md

All frontend implementation must remain consistent with these documents.

---

## End of Part 1

The following sections will be completed in Part 2:

- Layout Architecture
- Component Architecture
- JavaScript Architecture
- Asset Management
- Responsive Design Strategy
- Frontend Security
- Performance Optimization
- Accessibility Standards
- Browser Compatibility
- Future Scalability
- Related Documents
- Conclusion
- Document Approval

---

# 11. Layout Architecture

The application uses separate layouts for the public website and the administrative dashboard.

## Website Layout

```text
+--------------------------------------+
| Header / Navbar                      |
+--------------------------------------+
| Hero Section                         |
+--------------------------------------+
| Main Content                         |
+--------------------------------------+
| Footer                               |
+--------------------------------------+
```

### Components

- Top Navigation
- Hero Banner
- Main Content Area
- Footer
- Notification Area

---

## Admin Layout

```text
+----------------+----------------------+
| Sidebar        | Header               |
|                +----------------------+
|                | Main Content         |
|                |                      |
|                |                      |
|                +----------------------+
|                | Footer               |
+----------------+----------------------+
```

### Components

- Sidebar Navigation
- Top Navigation
- Dashboard Widgets
- Data Tables
- Notification Panel
- Footer

---

# 12. Component Architecture

The frontend uses reusable UI components.

## Shared Components

- Navbar
- Footer
- Sidebar
- Breadcrumbs
- Alerts
- Cards
- Tables
- Modals
- Pagination
- Forms
- Buttons
- Loaders
- Empty States

## Example Component Structure

```text
resources/views/components/

navbar.php
footer.php
sidebar.php
alert.php
modal.php
pagination.php
```

### Benefits

- Reusability
- Consistency
- Easier Maintenance
- Faster Development

---

# 13. JavaScript Architecture

JavaScript is organized into feature-based modules.

## Directory

```text
public/assets/js/

booking.js
room.js
gallery.js
dashboard.js
payment.js
notification.js
review.js
cms.js
```

---

## JavaScript Responsibilities

| Module | Responsibility |
|-------|------|
| booking.js | Booking Operations |
| room.js | Room Interactions |
| gallery.js | Gallery Features |
| dashboard.js | Dashboard Widgets |
| payment.js | Payment Handling |
| notification.js | Notifications |
| review.js | Review Management |
| cms.js | CMS Operations |

---

## JavaScript Flow

```text
User Action
      │
      ▼
JavaScript Event
      │
      ▼
Validation
      │
      ▼
Fetch API
      │
      ▼
REST API
      │
      ▼
PHP Backend
      │
      ▼
Response
      │
      ▼
Update UI
```

---

# 14. Asset Management

All static assets are stored under:

```text
public/assets/
```

## Structure

```text
assets/
│
├── css/
├── js/
├── images/
├── fonts/
├── icons/
└── vendor/
```

---

## CSS Files

```text
main.css
admin.css
responsive.css
utilities.css
```

---

## Approved Vendor Libraries

- Bootstrap 5

Any additional frontend library requires explicit technical-architect approval, a pinned/versioned dependency declaration, setup instructions, license/security review and tests before generated or handwritten code may use it.

---

# 15. Responsive Design Strategy

The frontend follows a Mobile First approach.

## Supported Breakpoints

| Device | Width |
|------|------|
| Mobile | 320px+ |
| Tablet | 768px+ |
| Laptop | 992px+ |
| Desktop | 1200px+ |
| Large Desktop | 1400px+ |

---

## Responsive Goals

- Fully responsive layouts.
- Optimized navigation.
- Mobile-friendly forms.
- Responsive tables.
- Flexible images.
- Consistent spacing.

---

## Responsive Rules

- Avoid fixed widths.
- Use Bootstrap Grid System.
- Use responsive typography.
- Use fluid images.
- Test on multiple devices.

---

# 16. Frontend Security

Security is implemented at every frontend layer.

## Security Measures

- CSRF Protection
- XSS Prevention
- Input Validation
- Output Escaping
- Secure Cookies
- Session Validation

---

## File Upload Security

- Validate file type.
- Validate file size.
- Restrict executable files.
- Generate unique filenames.
- Sanitize filenames.

---

## Security Principles

- Never trust client input.
- Validate everything.
- Escape all output.
- Minimize exposed information.

---

# 17. Performance Optimization

The frontend is optimized for performance.

## Optimization Techniques

- Minified CSS
- Minified JavaScript
- Image Compression
- Lazy Loading
- Browser Caching
- Asset Bundling (Future)
- CDN Support (Future)

---

## Performance Goals

| Metric | Target |
|------|------|
| First Contentful Paint | < 2s |
| Largest Contentful Paint | < 2.5s |
| Time to Interactive | < 3s |
| Lighthouse Score | 90+ |

---

# 18. Accessibility Standards

The frontend aims to meet accessibility best practices.

## Standards

- Semantic HTML
- Keyboard Navigation
- Accessible Forms
- Proper Labels
- Alt Text for Images
- Sufficient Color Contrast
- Focus Indicators

---

## Accessibility Goals

- WCAG Compliance (Future)
- Screen Reader Compatibility
- Improved User Experience
- Inclusive Design

---

# 19. Browser Compatibility

The application supports modern browsers.

| Browser | Supported |
|------|------|
| Chrome | Yes |
| Firefox | Yes |
| Edge | Yes |
| Safari | Yes |
| Opera | Yes |

Internet Explorer is not supported.

---

# 20. Future Scalability

The frontend architecture supports future enhancements.

## Planned Features

- Progressive Web App (PWA)
- Multi-language Support
- Dark Mode
- Real-time Notifications
- WebSockets
- Mobile Application
- Theme Management
- Offline Support

---

# 21. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 05_Folder_Structure.md
- 06_Coding_Standards.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- 23_REST_API_Specification.md

---

# 22. Conclusion

The Frontend Architecture document establishes a standardized approach for building and maintaining the user interface of the Tara Guest House Management System.

By following this architecture, the development team can ensure:

- Consistency
- Maintainability
- Scalability
- Security
- Performance
- Accessibility
- Reusability

The architecture provides a strong foundation for both the public website and administrative dashboard while supporting future growth and technological enhancements.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Frontend Developer | Saroj | Pending |
| Backend Developer | Rijan | Pending |
| QA Engineer | Pallawi | Pending |
