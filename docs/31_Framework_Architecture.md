# Framework Architecture

> **Approved Version 1 framework boundary:** This custom PHP foundation uses Composer, routes, middleware, controllers, request validation, services, repositories, models/entities, PDO, PHP-rendered views, PHP sessions, CSRF, and `/api/v1/...` JSON responses. It is not a full third-party framework and does not include JWT, Redis, WebSockets, or SPA infrastructure in Version 1.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Framework Architecture |
| **Document No.** | 31 |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the architecture, components, and internal workings of the custom PHP framework used by the Tara Guest House Management System.

The framework has been designed to provide:

- Maintainability
- Scalability
- Security
- Reusability
- Simplicity
- Consistency

This document serves as the official reference for understanding and extending the framework.

---

# 2. Introduction

The Tara Guest House Management System is built on top of a custom Layered MVC-inspired PHP framework.

Unlike third-party frameworks, this framework has been designed specifically to:

- Support hospitality applications.
- Encourage clean architecture.
- Promote separation of concerns.
- Simplify maintenance.
- Provide a lightweight development experience.

---

# 3. Framework Overview

The framework consists of:

```text
Presentation Layer
        │
        ▼
Routing Layer
        │
        ▼
Middleware Layer
        │
        ▼
Controller Layer
        │
        ▼
Service Layer
        │
        ▼
Repository Layer
        │
        ▼
PDO Persistence Layer
        │
        ▼
Database Layer
```

---

## Framework Characteristics

| Characteristic | Description |
|---------------|-------------|
| Lightweight | Minimal overhead |
| Modular | Easily extendable |
| Secure | Security by default |
| Reusable | Shared components |
| Maintainable | Clear responsibilities |

---

# 4. Design Principles

The framework follows these engineering principles:

- Separation of Concerns (SoC)
- Single Responsibility Principle (SRP)
- Don't Repeat Yourself (DRY)
- Keep It Simple (KISS)
- Security by Default
- Reusability
- Scalability

---

# 5. Core Components

The framework core resides in:

```text
app/Core/
```

---

## Core Structure

```text
app/Core/
│
├── Router.php
├── Request.php
├── Response.php
├── Database.php
├── Session.php
├── Validator.php
├── View.php
├── Controller.php
├── Middleware.php
└── Logger.php
```

---

## Component Responsibilities

| Component | Responsibility |
|----------|----------------|
| Router | Route Resolution |
| Request | HTTP Request Handling |
| Response | HTTP Responses |
| Database | Database Connectivity |
| Session | Session Management |
| Validator | Input Validation |
| View | Template Rendering |
| Controller | Base Controller |
| Middleware | Request Filtering |
| Logger | Application Logging |

---

# 6. Request Lifecycle

Every request follows the same lifecycle.

```text
Browser
   │
   ▼
Apache
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
   │
   ▼
Response
```

---

## Lifecycle Objectives

- Maintain consistency.
- Improve debugging.
- Simplify maintenance.
- Enforce architectural rules.

---

# 7. Router Architecture

The Router is responsible for matching incoming requests to their respective controllers.

---

## Responsibilities

- Route Registration
- Route Matching
- Parameter Extraction
- Middleware Execution

---

## Example

```php
GET    /rooms                              (server-rendered page)
POST   /api/v1/bookings                    (JSON)
PATCH  /api/v1/admin/bookings/{id}/status  (JSON)
GET    /api/v1/admin/reviews/{id}           (protected JSON detail)
PATCH  /api/v1/admin/reviews/{id}/status    (protected moderation)
```

---

## Router Workflow

```text
Incoming Request
        │
        ▼
Match Route
        │
        ▼
Execute Middleware
        │
        ▼
Invoke Controller
```

---

# 8. Controller Architecture

Controllers coordinate application flow.

---

## Responsibilities

- Receive Requests
- Validate Input
- Call Services
- Return Responses

---

## Rules

Controllers must NEVER:

- Execute SQL
- Contain Business Logic
- Render HTML directly

---

## Example

```text
RoomController
BookingController
PaymentController
ReviewController
```

---

# 9. Service Architecture

Services contain business logic.

---

## Examples

```text
BookingService
PaymentService
ReviewService
NotificationService
```

---

## Responsibilities

- Validate Business Rules
- Coordinate Workflows
- Generate Invoices
- Verify Availability
- Process Payments

---

## Service Workflow

```text
Controller
     │
     ▼
Service
     │
     ▼
Repository
```

---

# 10. Repository Architecture

Repositories provide an abstraction layer for data access.

---

## Responsibilities

- Execute Complex Queries
- Optimize Data Retrieval
- Abstract Persistence Logic

---

## Examples

```text
BookingRepository
RoomRepository
UserRepository
```

---

# 11. Model Architecture

Models represent database entities.

---

## Responsibilities

- Map Database Tables
- Represent domain fields and values
- Return domain-safe data to Services/views
- Never execute SQL or use PDO

---

## Examples

```text
User
Room
Booking
Payment
Invoice
```

---

## End of Part 1

Part 2 includes:

- View Architecture
- Session Management
- Validation System
- Middleware Architecture
- Error Handling
- Logging
- Security Components
- Future Enhancements
- Conclusion
- Approval

---

# 12. View Architecture

The View component is responsible for rendering the user interface.

## Responsibilities

- Render HTML templates
- Display application data
- Maintain presentation logic
- Support reusable layouts

---

## Directory Structure

```text
resources/views/
│
├── home/
├── rooms/
├── bookings/
├── gallery/
├── admin/
└── layouts/
```

---

## View Rules

Views must NEVER:

- Execute SQL queries
- Contain business logic
- Handle authentication
- Manage sessions

---

## View Workflow

```text
Controller
     │
     ▼
Pass Data
     │
     ▼
View
     │
     ▼
Render HTML
     │
     ▼
Browser
```

---

# 13. Session Management

The Session component manages user state throughout the application.

---

## Responsibilities

- User Authentication
- Session Creation
- Session Validation
- Session Destruction

---

## Session Workflow

```text
User Login
     │
     ▼
Validate Credentials
     │
     ▼
Create Session
     │
     ▼
Store User Information
     │
     ▼
Access Protected Routes
```

---

## Session Rules

- Regenerate Session IDs.
- Destroy sessions on logout.
- Set secure cookie flags.
- Expire inactive sessions.

---

# 14. Validation System

The Validator component ensures data integrity.

---

## Responsibilities

- Required Field Validation
- Email Validation
- Length Validation
- Numeric Validation
- File Validation

---

## Example Rules

```php
required
email
min:8
max:255
numeric
image
```

---

## Validation Workflow

```text
Receive Input
      │
      ▼
Apply Rules
      │
      ▼
Validation Passed?
      │
   Yes │ No
      ▼
 Continue    Return Errors
```

---

# 15. Middleware Architecture

Middleware executes before controllers.

---

## Responsibilities

- Authentication
- Authorization
- CSRF Protection
- Request Filtering
- Rate Limiting (Future)

---

## Example Middleware

```text
AuthenticationMiddleware
AdminOnlyMiddleware
GuestOnlyMiddleware
CSRFMiddleware
```

---

## Middleware Workflow

```text
Request
   │
   ▼
Middleware
   │
   ▼
Pass?
 │     │
Yes    No
 │     │
 ▼     ▼
Controller
      Return Error
```

---

# 16. Error Handling

The framework uses centralized error handling.

---

## Error Handling Objectives

- Hide sensitive information
- Log exceptions
- Display friendly messages
- Simplify debugging

---

## Workflow

```text
Exception Thrown
        │
        ▼
Global Handler
        │
        ▼
Log Error
        │
        ▼
Display Error Page
```

---

## Error Types

| Type | Example |
|------|---------|
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 500 | Server Error |

---

# 17. Logging

The Logger component records application events.

---

## Log Categories

- Application Logs
- Error Logs
- Security Logs
- Authentication Logs
- Audit Logs

---

## Storage Location

```text
storage/logs/
```

---

## Logging Rules

- Never log passwords.
- Include timestamps.
- Include severity levels.
- Protect sensitive information.

---

## Severity Levels

```text
INFO
WARNING
ERROR
CRITICAL
```

---

# 18. Security Components

Security is implemented throughout the framework.

---

## Security Features

- Prepared Statements
- Password Hashing
- CSRF Protection
- XSS Prevention
- Session Security
- Authorization Checks
- File Upload Validation

---

## Security Workflow

```text
Request
   │
   ▼
Validate Input
   │
   ▼
Authorize User
   │
   ▼
Execute Action
   │
   ▼
Log Event
```

---

# 19. Future Enhancements

The framework has been designed for future expansion.

---

## Planned Features

```text
Version 2.0
-----------
- Dependency Injection Container
- Event Dispatcher
- Queue System
- CLI Commands
- REST Framework
- WebSocket Support
- Package Management
- Multi-Tenant Support
```

---

## Scalability Goals

- Support Multiple Applications
- Improve Performance
- Enhance Extensibility
- Reduce Development Time

---

# 20. Conclusion

The Framework Architecture document defines the internal design of the custom PHP framework used by the Tara Guest House Management System.

This framework provides:

- Clear Separation of Concerns
- Security by Default
- Maintainability
- Scalability
- Reusability
- Consistent Development Practices

The framework serves as the foundation for all current and future applications developed by **fero.dev**.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Backend Developer | Rijan | Pending |
| System Architect | Pending | Pending |
| Development Team | Pending | Pending |
