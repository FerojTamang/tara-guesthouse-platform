# Project Architecture

> **Approved Version 1 architecture:** PHP-rendered Bootstrap 5 views with vanilla JavaScript and selective Fetch/AJAX; server-side PHP sessions; normal page routes plus versioned same-origin `/api/v1/...` JSON endpoints; layered PHP flow `Route → Middleware → Controller → Request Validation → Service → Repository → Database`; migrations as executable database truth; and cPanel/shared hosting. JWT, SPA frameworks, Redis, WebSockets, Docker, cloud deployment, and public third-party APIs are future enhancements. See `00_V1_ARCHITECT_DECISIONS.md`.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Project Architecture |
| **Version** | 1.1 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the overall software architecture of the **Tara Guest House Management System**.

It establishes the architectural foundation, application layers, request lifecycle, security model, component interactions, and development principles used throughout the system.

The architecture has been designed to support maintainability, scalability, security, performance, and future expansion while following modern software engineering best practices.

This document acts as the primary architectural reference for all developers involved in the project.

---

# 2. Architecture Style

The Tara Guest House Management System follows a **Layered MVC-Inspired Architecture** with clear separation of responsibilities.

Each layer performs a single responsibility and communicates only with adjacent layers.

Business rules remain independent from presentation logic, making the application easier to maintain, test, and extend.

---

# 3. High-Level Architecture

```text
                    Client Browser
                           │
                           ▼
                   Apache Web Server
                           │
                           ▼
                    public/index.php
                           │
                           ▼
                     Route Dispatcher
                           │
                           ▼
                     Middleware Layer
                           │
                           ▼
                      Controller Layer
                           │
                           ▼
                 Request Validation Layer
                           │
                           ▼
                    Business Service Layer
                           │
                           ▼
                    Repository Layer
                           │
                           ▼
                  PDO Persistence Layer
                           │
                           ▼
                     MariaDB Database
```

---

# 4. Architectural Principles

The application follows the following software engineering principles.

- Separation of Concerns (SoC)
- Single Responsibility Principle (SRP)
- Don't Repeat Yourself (DRY)
- Keep It Simple (KISS)
- Security by Default
- Layered Architecture
- Reusable Components
- Low Coupling
- High Cohesion
- Scalability
- Maintainability
- Consistency

---

# 5. Application Layers

## 5.1 Presentation Layer

### Directory

```text
resources/views/
public/assets/
```

### Responsibilities

- Display HTML pages
- Render Bootstrap UI
- Display validation messages
- Display notifications
- Collect user input
- Submit forms using HTTP/AJAX

### Examples

- Homepage
- Room Listing
- Booking Page
- Gallery
- Activities
- Contact Page
- Login
- Dashboard

Presentation layer must never:

- Execute SQL
- Contain business rules
- Access the database directly

---

## 5.2 Routing Layer

### Directory

```text
routes/
```

### Responsibilities

- Define application endpoints
- Map URLs to Controllers
- Register middleware
- Separate Website, Admin and API routes

### Route Files

```text
web.php
admin.php
api.php
```

---

## 5.3 Middleware Layer

### Directory

```text
app/Middleware/
```

Middleware executes before Controllers.

### Responsibilities

- Authentication
- Authorization
- Session Validation
- CSRF Protection
- Request Filtering
- Rate Limiting (Future)
- Activity Logging

### Example Flow

```text
Browser
     │
     ▼
Route
     │
     ▼
Authentication Middleware
     │
     ▼
Controller
```

---

## 5.4 Controller Layer

### Directory

```text
app/Controllers/
```

Controllers coordinate application flow.

Controllers must remain lightweight.

### Responsibilities

- Receive HTTP requests
- Call Request Validators
- Invoke Services
- Return Views
- Return JSON Responses
- Redirect Users

Controllers must NOT

- Execute SQL
- Implement business rules
- Render HTML manually

---

## 5.5 Request Validation Layer

### Directory

```text
app/Requests/
```

Purpose

Validate and sanitize incoming user input before business processing begins.

### Responsibilities

- Validate required fields
- Validate data types
- Sanitize user input
- Return validation errors

### Examples

- LoginRequest
- BookingRequest
- PaymentRequest
- ReviewRequest

---

## 5.6 Business Service Layer

### Directory

```text
app/Services/
```

Services contain all business logic.

Business rules must never exist inside Controllers or Views.

### Examples

- BookingService
- PaymentService
- RoomService
- InvoiceService
- NotificationService
- CMSService

### Responsibilities

- Booking Validation
- Availability Checking
- Invoice Generation
- Payment Processing
- Notification Generation
- Business Rule Enforcement

---

## 5.7 Repository Layer

### Directory

```text
app/Repositories/
```

Repositories provide the persistence boundary used by Services and map database rows to Models/entities.

### Responsibilities

- Complex Database Queries
- Query Optimization
- Data Retrieval
- Data Persistence

Repositories allow future migration to different storage technologies without changing business logic.

---

## 5.8 Model Layer

### Directory

```text
app/Models/
```

Models/entities represent domain data and behavior that does not require persistence access. They do not execute SQL or use PDO.

### Responsibilities

- Represent validated domain data
- Expose domain-safe values to Services and views
- Support row-to-entity mapping performed by Repositories
- Contain no SQL, PDO or persistence operations

### Examples

- User
- Booking
- Room
- Gallery
- Activity
- Review
- Payment
- Invoice

Models must never contain presentation logic.

---

## 5.9 Database Layer

Database Engine

MariaDB / MySQL

### Primary Modules

- Authentication
- Rooms
- Bookings
- Payments
- Invoices
- Reviews
- Gallery
- Activities
- CMS
- Notifications
- Audit Logs

The database structure follows Third Normal Form (3NF) as documented in the Database Dictionary.

---

# 6. Request Lifecycle

Every request processed by the Tara Guest House Management System follows a standardized lifecycle to ensure consistency, validation, security, and maintainability.

```text
Client Browser
      │
      ▼
Apache Web Server
      │
      ▼
public/index.php
      │
      ▼
Route Dispatcher
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
Business Service
      │
      ▼
Repository
      │
      ▼
PDO Persistence
      │
      ▼
MariaDB Database
      │
      ▼
Response
      │
      ▼
View / JSON
```

Every request must successfully pass through each layer before a response is returned to the client.

---

# 7. Authentication Architecture

The authentication process protects administrative features from unauthorized access.

## Authentication Flow

```text
Login Form
      │
      ▼
Authentication Controller
      │
      ▼
Request Validation
      │
      ▼
User Repository
      │
      ▼
User Model
      │
      ▼
Password Verification
      │
      ▼
Session Creation
      │
      ▼
Dashboard
```

## Authentication Features

- Secure Login
- Logout
- Session Management
- Password Hashing
- Role Verification
- Account Status Validation
- Remember Me (Future)
- Two-Factor Authentication (Future)

---

# 8. Booking Architecture

The booking module coordinates room reservations while enforcing business rules.

## Booking Flow

```text
Customer
      │
      ▼
Select Room
      │
      ▼
Choose Dates
      │
      ▼
Booking Form
      │
      ▼
Request Validation
      │
      ▼
Booking Service
      │
      ▼
Availability Check
      │
      ▼
Booking Repository
      │
      ▼
Booking Model
      │
      ▼
Database
      │
      ▼
Pending Booking (no payment attempt is created until submission)
```

### Business Rules

- Room must exist.
- Room must be available.
- Check-in date must be before check-out.
- Guest limit cannot be exceeded.
- Booking reference must be unique.
- Booking status begins as **Pending** until confirmation.

---

# 9. Payment Architecture

The payment module records guest payments while maintaining financial integrity.

## Payment Flow

```text
Pending Booking
       │
       ▼
Payment Form
       │
       ▼
Validation
       │
       ▼
Payment Service
       │
       ▼
Payment Repository
       │
       ▼
Payment Model
       │
       ▼
Database
       │
       ▼
Invoice Generation
```

### Responsibilities

- Record each payment submission as a separate attempt.
- Permit only an Administrator to verify or reject a submitted attempt.
- On verification, coordinate with Booking Service so it alone may transition `pending` to `confirmed`.
- Preserve fixed-precision NPR snapshots and audit history.
- Notify administrator.

---

# 10. Notification Architecture

Notifications are generated automatically whenever important business events occur.

## Notification Flow

```text
Business Event
      │
      ▼
Notification Service
      │
      ▼
Notification Repository
      │
      ▼
Database
      │
      ▼
Administrator Dashboard
```

### Notification Examples

- Booking Received
- Booking Approved
- Booking Cancelled
- Payment Received
- Review Submitted
- Contact Message Received
- System Alert

The Notification System follows the specifications defined in **20_Notification_System.md**.

---

# 11. File Upload Architecture

Secure file management is required for room images, gallery images, profile pictures, and payment receipts.

## Upload Flow

```text
User Upload
      │
      ▼
Validate File Type
      │
      ▼
Validate File Size
      │
      ▼
Generate Unique Filename
      │
      ▼
Store File
      │
      ▼
Save File Path
      │
      ▼
Display File
```

### Upload Validation

- Allowed file types only.
- Maximum file size validation.
- Malware scanning (Future).
- Duplicate filename prevention.
- Secure storage location.
- File path stored in database.

---

# 12. Security Architecture

Security is integrated into every application layer.

## Security Controls

### Authentication

- Password Hashing
- Session Management
- Account Status Verification

### Authorization

- Role-Based Access Control
- Route Protection
- Middleware Authorization

### Input Security

- Input Validation
- Input Sanitization
- Prepared Statements (PDO)

### Session Security

- Session Regeneration
- Secure Session Cookies
- Session Timeout

### File Security

- File Type Validation
- File Size Validation
- Unique File Names

### Application Security

- CSRF Protection
- XSS Prevention
- SQL Injection Prevention
- Error Handling
- Audit Logging

---

# 13. Error Handling

The system follows centralized error management.

## Error Handling Principles

- Never expose database errors.
- Never display stack traces.
- Log all unexpected exceptions.
- Return user-friendly messages.
- Prevent sensitive information disclosure.

### Error Categories

- Validation Errors
- Authentication Errors
- Authorization Errors
- Database Errors
- File Upload Errors
- Payment Errors
- System Errors

---

# 14. Logging Architecture

Application activities are recorded for auditing and troubleshooting.

## Log Directory

```text
storage/logs/
```

## Log Categories

- Application Logs
- Authentication Logs
- Booking Logs
- Payment Logs
- Security Logs
- Error Logs

### Logged Events

- Login
- Logout
- Booking Created
- Booking Updated
- Booking Cancelled
- Payment Received
- Review Submitted
- Administrative Actions
- System Errors

---

# 15. Scalability

The architecture supports future system growth without major redesign.

## Planned Enhancements

- REST API Expansion
- Mobile Application
- Online Payment Gateway
- SMS, WhatsApp, and push notifications (transactional SMTP email is Version 1)
- SMS Notifications
- Multi-language Support
- Multiple Guest Houses
- Cloud Storage
- Analytics Dashboard
- Docker Deployment
- Redis Caching
- Queue Processing

---

# 16. Architectural Decisions

The following architectural decisions apply throughout the project.

- Controllers coordinate requests and responses.
- Request validation occurs before business processing.
- Services contain business rules.
- Repositories manage data access.
- Models/entities represent domain data and execute no SQL.
- Views display data only.
- Middleware protects routes.
- Business logic never belongs in Views.
- SQL queries and PDO calls remain inside Repositories only.
- Configuration values are stored inside configuration files or the database, never hardcoded.
- All database interactions use prepared statements.

---

# 17. Architecture Benefits

The architecture provides the following advantages.

- Clear separation of responsibilities.
- Easier maintenance.
- Improved readability.
- Better code reuse.
- Simplified testing.
- Strong security.
- Faster onboarding of developers.
- Scalability for future enhancements.
- Consistent project organization.
- Reduced technical debt.

---

# 18. Related Documents

This document should be read together with the following project documentation.

- 05_Folder_Structure.md
- 06_Coding_Standards.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- Numbered module and process specifications `11_Booking_System_Specification.md` through `22_System_State_Machine.md` (see `33_Documentation_Index.md` for each exact filename)
- 23_REST_API_Specification.md

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Development Team | Pending | Pending |


