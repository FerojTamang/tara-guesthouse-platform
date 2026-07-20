# Folder Structure & Development Standards

> **Version 1 synchronization:** This structure supports PHP-rendered views, PHP sessions, `/api/v1/...` JSON endpoints, migrations/seeders, and cPanel/shared hosting. Future-ready folders do not place JWT, customer accounts, multi-room UI, Redis, WebSockets, Docker, or cloud services in Version 1 scope. Database migrations are the executable source of truth; `schema.sql` is an installation snapshot only.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Folder Structure & Development Standards |
| **Document ID** | TGH-SDS-005 |
| **Version** | 2.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# Architecture Freeze Notice

This document defines the official project folder structure for the **Tara Guest House Management System**.

As of **Version 2.0**, the folder structure is considered **frozen**.

Any modification to the project architecture, root directory, application layers, or folder organization requires approval from the **Project Manager** and **Technical Architect**.

This policy ensures consistency across all project documentation, source code, development workflow, deployment procedures, and future project maintenance.

---

# 1. Purpose

This document establishes the official folder structure, directory responsibilities, development organization, and file placement standards for the Tara Guest House Management System.

The primary objectives are to:

- Maintain a consistent project organization.
- Improve collaboration among development team members.
- Separate application responsibilities.
- Support scalable software architecture.
- Simplify future maintenance.
- Reduce technical debt.
- Encourage reusable code.
- Provide a standardized development environment.

This document shall be followed throughout the entire software development lifecycle.

---

# 2. Architecture Overview

The project follows a **Layered MVC-inspired Architecture**.

```text
Browser
      │
      ▼
Apache Web Server
      │
      ▼
public/index.php
      │
      ▼
Router
      │
      ▼
Middleware
      │
      ▼
Controller
      │
      ▼
Request Validation
      │
      ▼
Service
      │
      ▼
Repository
      │
      ▼
Model
      │
      ▼
MariaDB Database
```

This directory structure directly supports the architectural principles defined in:

- 04_Project_Architecture.md
- 25_Backend_Architecture.md
- 31_Framework_Architecture.md

---

# 3. Project Root Structure

The official project directory is organized as follows.

```text
tara-guest-house/
|-- app/
|-- config/
|-- database/
|-- public/
|-- resources/
|-- routes/
|-- storage/
|-- tests/
|-- client-assets/
|-- design/
|-- meetings/
|-- vendor/
|-- docs/
|-- .env.example
|-- .gitignore
|-- composer.json
|-- composer.lock
`-- README.md
```

Every top-level directory has a dedicated responsibility.

Developers must not introduce new root-level folders without architectural approval.

---

# 4. Root Directory Responsibilities

| Directory | Responsibility |
|------------|---------------|
| `app/` | Core application source code |
| `client-assets/` | Client-provided images, videos, documents, branding assets |
| `config/` | Application configuration files |
| `database/` | Database schema, migrations, seeders, backups |
| `design/` | UI/UX mockups, wireframes, prototypes |
| `docs/` | Project documentation |
| `meetings/` | Meeting minutes, sprint notes, planning documents |
| `public/` | Public web root and static assets |
| `resources/` | HTML views and presentation templates |
| `routes/` | Application route definitions |
| `storage/` | Runtime-generated files |
| `tests/` | Automated testing |
| `vendor/` | Composer dependencies |

---

# 5. Application Structure

All application logic resides inside the **app/** directory.

```text
app/
│
├── Controllers/
├── Requests/
├── Services/
├── Repositories/
├── Models/
├── Middleware/
├── Core/
├── Helpers/
└── Exceptions/
```

Each directory has a single responsibility and communicates only with the appropriate architectural layer.

---

# 6. Application Layer Workflow

Every application feature must follow the same processing pipeline.

```text
HTTP Request
      │
      ▼
Router
      │
      ▼
Middleware
      │
      ▼
Controller
      │
      ▼
Request Validation
      │
      ▼
Service
      │
      ▼
Repository
      │
      ▼
Model
      │
      ▼
MariaDB Database
      │
      ▼
Repository
      │
      ▼
Service
      │
      ▼
Controller
      │
      ▼
View / JSON Response
```

This workflow enforces:

- Separation of Concerns
- Single Responsibility Principle
- Maintainability
- Security
- Scalability

Business logic must never bypass these layers.

---

# 7. Application Layer Responsibilities

| Layer | Primary Responsibility |
|---------|------------------------|
| Controllers | Handle HTTP requests and responses |
| Requests | Validate and sanitize incoming data |
| Services | Execute business rules |
| Repositories | Handle data access operations |
| Models/entities | Represent domain data; no SQL/PDO |
| Middleware | Filter requests before Controllers |
| Core | Framework infrastructure |
| Helpers | Shared utility functions |
| Exceptions | Custom application exception handling |

Each layer communicates only with its adjacent architectural layer.

For example:

```text
Controller
    ↓
Service
```

is valid.

However,

```text
Controller
    ↓
Database
```

is prohibited.

---

# 8. Development Principles

The folder structure has been designed around the following software engineering principles.

- Separation of Concerns (SoC)
- Single Responsibility Principle (SRP)
- Don't Repeat Yourself (DRY)
- Keep It Simple (KISS)
- Security by Default
- Maintainability
- Scalability
- Reusability
- Consistency
- Readability

Every directory exists to support one or more of these principles.

---

# 9. General Folder Rules

All project members must comply with the following standards.

## Always

- Place files inside their designated directory.
- Follow project naming conventions.
- Keep source code modular.
- Separate presentation from business logic.
- Document significant architectural changes.
- Remove unused files before merging.

## Never

- Create unnecessary top-level folders.
- Store business logic inside Views.
- Execute SQL inside Controllers.
- Commit temporary files.
- Commit sensitive credentials.
- Duplicate source code across directories.
- Mix frontend assets with backend source code.

---

**End of Part 1**

# 10. Application Directory Responsibilities

The `app/` directory contains the complete backend application logic. Each subdirectory has a clearly defined responsibility and developers must follow the layered architecture when implementing new features.

---

# 10.1 Controllers

## Directory

```text
app/Controllers/
```

### Purpose

Controllers act as the entry point for all HTTP requests. They coordinate application flow by receiving requests, invoking the appropriate Services, and returning responses.

### Responsibilities

- Receive HTTP requests
- Call Request Validators
- Invoke Services
- Return Views
- Return JSON Responses
- Handle Redirects

### Controllers Must NOT

- Execute SQL queries
- Contain business logic
- Access the database directly
- Generate HTML
- Validate complex business rules

### Examples

```text
HomeController.php
RoomController.php
BookingController.php
GalleryController.php
ActivityController.php
ReviewController.php
PaymentController.php
AuthenticationController.php
DashboardController.php
```

---

# 10.2 Requests

## Directory

```text
app/Requests/
```

### Purpose

Validate and sanitize incoming user data before it reaches the Service layer.

### Responsibilities

- Required field validation
- Data type validation
- Input sanitization
- Validation error handling
- Custom validation rules

### Examples

```text
LoginRequest.php
BookingRequest.php
RoomRequest.php
GalleryRequest.php
ReviewRequest.php
PaymentRequest.php
```

### Request Classes Must NOT

- Access database
- Execute business logic
- Generate responses

---

# 10.3 Services

## Directory

```text
app/Services/
```

### Purpose

Services contain all business rules and application logic.

Every feature must implement its business logic inside the Service layer.

### Responsibilities

- Room availability checking
- Reservation processing
- Invoice generation
- Payment verification
- Review approval
- Email preparation
- Booking cancellation
- Dashboard statistics

### Examples

```text
BookingService.php
RoomService.php
GalleryService.php
PaymentService.php
ReviewService.php
NotificationService.php
DashboardService.php
```

### Services Must NOT

- Return HTML
- Execute raw SQL
- Read user input directly

---

# 10.4 Repositories

## Directory

```text
app/Repositories/
```

### Purpose

Repositories provide an abstraction layer between Services and Models by managing data access.

### Responsibilities

- Retrieve records
- Save records
- Update records
- Delete records
- Optimize database queries
- Reuse common queries

### Examples

```text
BookingRepository.php
RoomRepository.php
GalleryRepository.php
UserRepository.php
ReviewRepository.php
```

### Repository Benefits

- Cleaner Services
- Easier maintenance
- Improved testability
- Centralized data access

---

# 10.5 Models

## Directory

```text
app/Models/
```

### Purpose

Models/entities represent domain data and do not communicate with MariaDB or execute SQL. Repositories use PDO prepared statements and map rows to/from entities.

### Responsibilities

- Represent domain fields and values
- Return domain-safe application data
- Support persistence-independent behavior
- Contain no SQL, PDO or CRUD persistence code

### Examples

```text
User.php
Room.php
Booking.php
Activity.php
Gallery.php
Review.php
Payment.php
Setting.php
```

### Models Must NOT

- Generate HTML
- Handle sessions
- Perform business validation
- Redirect users

---

# 10.6 Middleware

## Directory

```text
app/Middleware/
```

### Purpose

Middleware executes before Controllers and filters incoming requests.

### Responsibilities

- Authentication
- Authorization
- CSRF Protection
- Session validation
- Access control
- Activity logging
- Request filtering

### Examples

```text
AuthenticationMiddleware.php
AdminMiddleware.php
GuestMiddleware.php
CSRFMiddleware.php
```

---

# 10.7 Core

## Directory

```text
app/Core/
```

### Purpose

Contains the framework infrastructure required by the application.

### Components

```text
Router.php
Database.php
Request.php
Response.php
Validator.php
View.php
Session.php
BaseController.php
Application.php
```

### Responsibilities

- HTTP routing
- Database connection
- Request lifecycle
- View rendering
- Session management
- Validation utilities

The Core directory should contain framework-level classes only.

---

# 10.8 Helpers

## Directory

```text
app/Helpers/
```

### Purpose

Provide reusable utility functions shared across the application.

### Examples

```text
DateHelper.php
CurrencyHelper.php
ImageHelper.php
ValidationHelper.php
StringHelper.php
UploadHelper.php
```

### Helper Functions

Examples include:

- Date formatting
- Currency formatting
- Image resizing
- String manipulation
- File upload utilities

Helpers should remain stateless and reusable.

---

# 10.9 Exceptions

## Directory

```text
app/Exceptions/
```

### Purpose

Contains custom exception classes used throughout the application.

### Examples

```text
ValidationException.php
BookingException.php
PaymentException.php
AuthenticationException.php
UploadException.php
```

### Responsibilities

- Standardize error handling
- Improve debugging
- Provide meaningful exception types

---

# 11. Configuration Directory

## Directory

```text
config/
```

### Purpose

Stores centralized application configuration.

### Structure

```text
config/
│
├── app.php
├── database.php
├── mail.php
├── security.php
├── session.php
└── upload.php
```

### Responsibilities

- Database configuration
- Session configuration
- Mail configuration
- Security settings
- Upload settings
- Global application settings

### Rules

- Never hardcode credentials.
- Use environment variables where applicable.
- Keep configuration separate from application logic.

---

# 12. Database Directory

## Directory

```text
database/
```

### Structure

```text
database/
│
├── schema.sql
├── migrations/
├── seeders/
└── backups/
```

### Responsibilities

- Store executable migrations and an optional installation snapshot
- Version database changes
- Populate development data
- Maintain backup copies

### Folder Descriptions

#### schema.sql

Contains a generated or maintained installation snapshot. It must not contradict migrations.

#### migrations/

Contains executable database changes and is the database source of truth.

#### seeders/

Contains sample development data.

#### backups/

Contains exported database backups created during development.

Application source code must never be stored inside the database directory.

---

**End of Part 2**
# 13. Public Directory

## Directory

```text
public/
```

### Purpose

The `public/` directory serves as the application's web root. Only files intended to be accessed directly by web browsers shall be placed inside this directory.

Application source code must never be stored in this directory.

---

## Structure

```text
public/
│
├── index.php
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   ├── fonts/
│   ├── icons/
│   └── vendor/
│
└── uploads/
    ├── rooms/
    ├── gallery/
    ├── activities/ (future-ready; unused in Version 1)
    ├── profiles/
    └── payments/
```

---

### index.php

Acts as the application's front controller.

Responsibilities include:

- Bootstrapping the application
- Loading configuration
- Initializing the Router
- Processing incoming HTTP requests

---

### assets/

Contains static frontend resources.

Examples:

- CSS
- JavaScript
- Images
- Fonts
- Icons
- Third-party frontend libraries

Business logic must never be placed inside this directory.

---

### uploads/

Stores user-uploaded files.

Uploads include:

- Room photographs
- Gallery images
- The existing `activities/` upload directory is future-ready and unused in Version 1; Activity Images are not a current upload type.
- User profile images
- Payment receipts

---

### Upload Rules

Every uploaded file shall:

- Be validated before storage.
- Have its MIME type verified.
- Have its file extension validated.
- Have its maximum file size validated.
- Receive a unique filename.
- Be protected against executable uploads.
- Have its storage path recorded in the database.

---

# 14. Resources Directory

## Directory

```text
resources/
```

### Purpose

Contains all presentation templates displayed to users.

Only presentation-related files belong inside this directory.

---

## Structure

```text
resources/
└── views/
    ├── layouts/
    ├── website/
    ├── admin/
    ├── auth/
    ├── components/
    ├── emails/
    └── errors/
```

---

### layouts/

Contains reusable page layouts.

Examples

- Header
- Footer
- Navigation
- Sidebar
- Master Layout

---

### website/

Contains all public website pages.

Examples

- Home
- Rooms
- Activities
- Gallery
- Contact
- About
- Booking

---

### admin/

Contains administrator dashboard pages.

Examples

- Dashboard
- Room Management
- Booking Management
- Gallery Management
- User Management
- Reports

---

### auth/

Contains authentication views.

Examples

- Login
- Forgot Password
- Reset Password

---

### components/

Contains reusable UI components.

Examples

- Cards
- Tables
- Buttons
- Forms
- Alerts
- Pagination

---

### emails/

Contains email templates.

Examples

- Booking Confirmation
- Password Reset
- Payment Confirmation

---

### errors/

Contains error pages.

Examples

- 403
- 404
- 500

---

### View Rules

Views must never:

- Execute SQL
- Contain business logic
- Process payments
- Validate application rules

Views should only display data supplied by Controllers.

---

# 15. Routes Directory

## Directory

```text
routes/
```

---

## Structure

```text
routes/
│
├── web.php
├── admin.php
└── api.php
```

---

### web.php

Defines all public website routes.

Examples

- Homepage
- Rooms
- Booking
- Gallery
- Activities
- Contact

---

### admin.php

Defines administrator routes.

Examples

- Dashboard
- Room Management
- Booking Management
- Reports

Administrative routes must always be protected by authentication middleware.

---

### api.php

Defines REST API endpoints.

Examples

- Room API
- Booking API
- Gallery API
- Review API

All API responses should return JSON.

---

# 16. Storage Directory

## Directory

```text
storage/
```

---

## Structure

```text
storage/
│
├── backups/
├── cache/
├── exports/
├── logs/
├── sessions/
└── temp/
```

---

### logs/

Contains application logs.

Examples

- PHP Errors
- Authentication Events
- Booking Events
- Payment Logs
- System Logs

---

### cache/

Stores cached application data.

---

### sessions/

Stores session files when file-based sessions are used.

---

### exports/

Contains exported reports.

Examples

- PDF Reports
- Excel Reports
- CSV Files

---

### backups/

Contains generated backup files.

---

### temp/

Stores temporary application files.

Temporary files should be removed automatically after processing.

---

# 17. Testing Directory

## Directory

```text
tests/
```

---

## Structure

```text
tests/
│
├── Unit/
├── Feature/
└── Integration/
```

---

### Unit

Tests individual classes and methods.

Examples

- Service Tests
- Helper Tests
- Validation Tests

---

### Feature

Tests complete application features.

Examples

- Booking Flow
- Authentication
- Gallery Upload
- Payment Processing

---

### Integration

Tests communication between multiple application components.

Examples

- Controller → Service
- Service → Repository
- Repository → Database

---

Future testing may include:

- Performance Testing
- Security Testing
- Load Testing

---

# 18. Documentation Directory

## Directory

```text
docs/
```

### Purpose

Contains all official project documentation.

Examples include:

- Requirements
- Architecture
- Database Design
- API Specification
- Testing Strategy
- Deployment Guide
- User Manual

Only documentation files belong in this directory.

---

# 19. Client Assets Directory

## Directory

```text
client-assets/
```

### Purpose

Stores files received directly from the client during the project lifecycle.

Examples include:

- Company Logos
- Room Images
- Activity reference material (not a Version 1 application-upload requirement)
- Videos
- Pricing Documents
- Policies
- Promotional Materials

These files serve as reference material and should not be modified directly.

---

# 20. Design Directory

## Directory

```text
design/
```

### Purpose

Stores design resources produced during UI/UX planning.

Examples

- Wireframes
- Mockups
- Prototypes
- Color Palettes
- Design References

Design assets should remain separate from production source code.

---

# 21. Meetings Directory

## Directory

```text
meetings/
```

### Purpose

Stores project management documentation generated during meetings.

Examples

- Sprint Planning
- Daily Stand-ups
- Weekly Progress Reports
- Retrospectives
- Client Meeting Notes

Meeting records provide historical documentation for project decisions.

---

**End of Part 3**

# 22. Naming Conventions

To maintain consistency throughout the project, all developers shall follow the naming conventions defined below.

| Item | Convention | Example |
|------|------------|---------|
| Project Folders | lowercase | `config`, `database`, `storage` |
| Application Folders | PascalCase | `Controllers`, `Services` |
| PHP Classes | PascalCase | `BookingController.php` |
| Controllers | PascalCase | `RoomController.php` |
| Services | PascalCase | `BookingService.php` |
| Repositories | PascalCase | `RoomRepository.php` |
| Models | PascalCase | `Booking.php` |
| Middleware | PascalCase | `AuthenticationMiddleware.php` |
| Requests | PascalCase | `BookingRequest.php` |
| Exceptions | PascalCase | `ValidationException.php` |
| Variables | camelCase | `$bookingDate` |
| Methods | camelCase | `createBooking()` |
| Database Tables | snake_case (plural) | `booking_rooms` |
| Database Columns | snake_case | `created_at` |
| CSS Classes | kebab-case | `booking-card` |
| JavaScript Functions | camelCase | `submitBooking()` |

Developers must follow these conventions throughout the project lifecycle.

---

# 23. Development Standards

The project follows a layered architecture. Every feature shall follow the workflow below.

```text
Route
   │
   ▼
Middleware
   │
   ▼
Controller
   │
   ▼
Request Validation
   │
   ▼
Service
   │
   ▼
Repository
   │
   ▼
Model
   │
   ▼
MariaDB
```

Business logic shall never bypass any architectural layer.

---

## General Development Rules

### Always

- Follow the project architecture.
- Write reusable code.
- Keep Controllers lightweight.
- Store business rules inside Services.
- Access data through Repositories.
- Use Prepared Statements.
- Validate all user input.
- Write meaningful commit messages.
- Keep methods short and focused.
- Remove unused code before committing.
- Document significant architectural changes.

---

### Never

- Write SQL inside Controllers.
- Place business logic inside Views.
- Generate HTML inside Models.
- Hardcode credentials.
- Commit sensitive information.
- Duplicate business logic.
- Mix frontend and backend responsibilities.
- Modify the project architecture without approval.

---

# 24. Security Standards

The folder structure has been designed with security as a primary concern.

Every developer shall follow these practices.

## Authentication

- Protect administrator routes using Middleware.
- Store passwords using secure hashing.
- Validate every authenticated request.

---

## Database

- Use PDO Prepared Statements.
- Never concatenate SQL queries.
- Validate all database input.

---

## File Uploads

Every uploaded file must be:

- Type validated.
- Extension validated.
- Size validated.
- Renamed using a unique filename.
- Stored securely.
- Recorded in the database.

Executable files must never be accepted.

---

## Configuration

Sensitive information shall never be committed to version control.

Examples include:

- Database passwords
- SMTP credentials
- API keys
- Secret tokens

Use environment variables whenever applicable.

---

# 25. GitHub Repository Standards

The project follows the Git workflow defined in:

**07_Git_Workflow.md**

## Primary Branches

```text
main
develop
```

---

## Feature Branches

Each developer shall create feature branches using the following format.

```text
feature/authentication
feature/booking
feature/gallery
feature/payment
feature/dashboard
```

---

## Bug Fix Branches

```text
bugfix/login-validation
bugfix/payment-error
```

---

## Hotfix Branches

```text
hotfix/security-patch
```

---

## Branch Workflow

```text
main
   │
   ▼
develop
   │
   ▼
feature/*
   │
   ▼
Pull Request
   │
   ▼
Code Review
   │
   ▼
Merge into develop
   │
   ▼
Testing
   │
   ▼
Merge into main
```

Direct commits to the **main** branch are prohibited.

---

# 26. Team Responsibilities

| Team Member | Primary Responsibility |
|-------------|------------------------|
| Project Manager | Planning, architecture, approvals |
| Backend Developer | Business logic, database, APIs |
| Frontend Developer | User interface and client-side functionality |
| QA Engineer | Testing, bug reporting, verification |

Every team member shall work only within their assigned responsibilities unless approved by the Project Manager.

---

# 27. Feature Development Workflow

Every new feature shall follow the same development lifecycle.

```text
Requirement
      │
      ▼
Task Assignment
      │
      ▼
Feature Branch
      │
      ▼
Development
      │
      ▼
Self Testing
      │
      ▼
Pull Request
      │
      ▼
Code Review
      │
      ▼
QA Testing
      │
      ▼
Merge into Develop
      │
      ▼
Release
      │
      ▼
Merge into Main
```

This workflow ensures consistency, quality, and maintainability.

---

# 28. Future Scalability

The project folder structure has been designed to support future enhancements without major restructuring.

Potential future enhancements include:

- REST API Expansion
- Mobile Application
- Multi-Hotel Support
- Online Payment Gateway
- SMS, WhatsApp, and push notifications (transactional SMTP email is Version 1)
- SMS Notifications
- Cloud Storage
- Docker Deployment
- Redis Caching
- Queue Workers
- Third-Party Integrations

The existing architecture is capable of supporting these additions while maintaining the established folder organization.

---

# 29. Related Documents

This document should be used together with the following project documentation.

- 04_Project_Architecture.md
- 06_Coding_Standards.md
- 07_Git_Workflow.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md
- 31_Framework_Architecture.md

---

# 30. Conclusion

A well-organized folder structure is essential for building maintainable, secure, and scalable software.

By following the standards defined in this document, the Tara Guest House Management System will maintain a consistent architecture throughout development, simplify collaboration among team members, reduce technical debt, and provide a strong foundation for future enhancements.

This folder structure is considered the official standard for the project and shall be followed by all contributors.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |

---

**End of Document**
