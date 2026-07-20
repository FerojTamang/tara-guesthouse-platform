> **Approved Version 1 backend:** PHP 8.x with Composer PSR-4 autoloading and the flow `Route → Middleware → Controller → Request Validation → Service → Repository → Database`. Administrator authentication uses hardened PHP sessions, not JWT. Migrations are executable database truth. The runtime target is Apache/MariaDB on cPanel/shared hosting; Redis, WebSockets, Docker, and cloud infrastructure are future enhancements.

Architecture Name:
Enterprise Layered MVC Architecture

Technology Stack:

- PHP 8.x
- PDO
- MariaDB
- Apache
- Custom MVC

# Backend Architecture

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Backend Architecture |
| **Document No.** | 25 |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document defines the backend architecture of the Tara Guest House Management System.

It establishes standards for:

- Request Processing
- Routing
- Middleware
- Request Validation
- Business Logic
- Data Access
- Database Communication
- Authentication
- Authorization
- Session Management
- Error Handling
- Logging
- Security
- Performance

The backend architecture has been designed to provide a secure, maintainable, scalable, and enterprise-grade foundation for the application.

All backend development activities must follow the standards defined in this document.

---

# 2. Backend Overview

The backend is responsible for:

- Processing HTTP Requests
- Managing Application Logic
- Managing Database Operations
- Authentication
- Authorization
- Session Management
- Payment Processing
- Notification Handling
- Audit Logging
- API Communication

The backend uses a custom Enterprise Layered MVC Architecture built with PHP 8.x.

---

# 3. Technology Stack

| Technology | Purpose |
|------------|---------|
| PHP 8.x | Backend Language |
| PDO | Database Abstraction |
| MariaDB | Database Engine |
| Apache | Web Server |
| Composer | Dependency Management |
| REST API | System Integration |
| PHP Sessions | Authentication |
| JSON | Data Exchange |

---

# 4. Backend Layers

The backend follows a layered architecture.

```text
Client Request
      │
      ▼
Apache Server
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
Service Layer
      │
      ▼
Repository Layer
      │
      ▼
PDO Persistence Layer
      │
      ▼
PDO
      │
      ▼
MariaDB
      │
      ▼
Response
```

---

# 5. Layer Responsibilities

| Layer | Responsibility |
|------|------|
| Apache | Serve Requests |
| Front Controller | Application Entry Point |
| Router | Route Resolution |
| Middleware | Security & Access Control |
| Controller | Request Handling |
| Request | Validation & Sanitization |
| Service | Business Logic |
| Repository | Data Access |
| Model/entity | Domain data representation; no SQL/PDO |
| PDO | Database Communication |
| MariaDB | Data Storage |

---

# 6. Backend Directory Structure

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

---

## Core Components

```text
app/Core/

Router.php
Database.php
Request.php
Response.php
Validator.php
Session.php
View.php
BaseController.php
```

---

# 7. Request Lifecycle

Every request follows a predefined lifecycle.

```text
Browser Request
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
PDO
      │
      ▼
MariaDB
      │
      ▼
Response
      │
      ▼
Browser
```

All requests must pass through each layer before a response is returned.

---

# 8. Routing Architecture

The Router is responsible for mapping incoming requests to their respective controllers.

## Route Files

```text
routes/

web.php
admin.php
api.php
```

---

## Example

```php
GET    /rooms                     (server-rendered page)
GET    /booking                              (server-rendered page)
POST   /api/v1/bookings                     (Guest JSON creation)
GET    /api/v1/admin/bookings/{id}          (protected JSON detail)
PATCH  /api/v1/admin/bookings/{id}/status   (protected transition)
```

---

## Responsibilities

- Match Routes
- Extract Parameters
- Execute Middleware
- Dispatch Controllers
- Return Responses

---

# 9. Middleware Architecture

Middleware executes before Controllers.

## Examples

```text
AuthenticationMiddleware
AdminMiddleware
GuestMiddleware
CSRFMiddleware
LoggingMiddleware
```

---

## Middleware Flow

```text
Request
   │
   ▼
Middleware
   │
   ▼
Controller
```

---

## Responsibilities

- Authentication
- Authorization
- Session Validation
- CSRF Protection
- Request Logging
- Access Control

---

# 10. Controller Architecture

Controllers coordinate application flow.

## Directory

```text
app/Controllers/
```

---

## Examples

```text
RoomController.php
BookingController.php
PaymentController.php
ReviewController.php
CMSController.php
```

---

## Responsibilities

- Receive Requests
- Invoke Services
- Return Responses
- Redirect Users

---

## Controllers Must NOT

- Execute SQL
- Contain Business Logic
- Handle Database Connections
- Generate HTML

---

# 11. Request Validation Architecture

Request classes validate incoming data.

## Directory

```text
app/Requests/
```

---

## Examples

```text
LoginRequest.php
BookingRequest.php
PaymentRequest.php
ReviewRequest.php
```

---

## Responsibilities

- Required Field Validation
- Input Sanitization
- Type Validation
- Error Messages

---

## Validation Flow

```text
Controller
     │
     ▼
Request Validator
     │
     ▼
Validated Data
     │
     ▼
Service
```

---

# 12. Service Architecture

Services contain all business rules.

## Directory

```text
app/Services/
```

---

## Examples

```text
BookingService.php
PaymentService.php
RoomService.php
NotificationService.php
```

---

## Responsibilities

- Booking Logic
- Payment Logic
- Invoice Generation
- Availability Checks
- Notification Handling

---

## Rule

> Business Logic MUST exist only in the Service Layer.

---

## End of Part 1

Part 2 will include:

- Repository Architecture
- Model Architecture
- Database Architecture
- Authentication
- Session Management
- Authorization
- Error Handling
- Logging
- Security
- Performance
- Scalability
- Related Documents
- Conclusion
- Approval

---

# 13. Repository Architecture

Repositories provide an abstraction layer between Services and Models.

## Directory

```text
app/Repositories/
```

## Examples

```text
BookingRepository.php
RoomRepository.php
PaymentRepository.php
UserRepository.php
ReviewRepository.php
```

## Responsibilities

- Execute complex queries
- Abstract database access
- Optimize queries
- Improve maintainability
- Reduce code duplication

## Repository Flow

```text
Service
   │
   ▼
Repository (PDO/SQL)
   │
   ├── MariaDB persistence
   └── maps rows to Models/entities
```

Repositories ensure Services remain independent of database implementation details.

---

# 14. Model Architecture

Models/entities represent domain data. They never interact with the database directly.

## Directory

```text
app/Models/
```

## Examples

```text
User.php
Room.php
Booking.php
Payment.php
Review.php
Invoice.php
Notification.php
```

## Responsibilities

- Represent domain fields and values
- Return domain-safe data to Services/views
- Contain persistence-independent behavior only
- Accept mapping/hydration from Repositories

## Models Must NOT

- Generate HTML
- Handle Sessions
- Perform Business Logic
- Redirect Users
- Execute SQL, prepared statements or PDO calls

---

# 15. Database Architecture

The backend communicates with MariaDB using PDO.

## Database Flow

```text
Model
   │
   ▼
PDO
   │
   ▼
MariaDB
```

---

## Database Principles

- Prepared Statements Only
- No Raw SQL Concatenation
- Transaction Support
- Foreign Key Constraints
- Third Normal Form (3NF)

---

## Primary Modules

- Users
- Rooms
- Bookings
- Payments
- Invoices
- Activities
- Gallery
- Reviews
- Notifications
- CMS
- Audit Logs

---

# 16. Authentication Architecture

Authentication is handled using PHP Sessions.

## Authentication Flow

```text
Login Form
      │
      ▼
Authentication Controller
      │
      ▼
LoginRequest
      │
      ▼
UserService
      │
      ▼
UserRepository
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

---

## Features

- Login
- Logout
- Session Management
- Password Hashing
- Account Status Validation

---

## Future Features

- Remember Me
- Two-Factor Authentication
- OAuth Integration

---

# 17. Session Management

Sessions maintain user authentication throughout the application.

## Session Responsibilities

- Store User Information
- Maintain Login State
- Session Regeneration
- Session Timeout
- Session Destruction

---

## Session Flow

```text
User Login
      │
      ▼
Create Session
      │
      ▼
Validate Session
      │
      ▼
Access Resources
      │
      ▼
Logout
      │
      ▼
Destroy Session
```

---

## Session Security

- Secure Cookies
- Session Regeneration
- Session Expiration
- HTTP Only Cookies

---

# 18. Authorization Architecture

Authorization determines access rights within the application.

## Roles

```text
Administrator
Guest (unauthenticated public actor)
Manager (Future)
Receptionist (Future)
Customer (Future)
```

---

## Authorization Flow

```text
Request
    │
    ▼
Authentication
    │
    ▼
Role Validation
    │
    ▼
Access Granted
```

---

## Examples

| Role | Access |
|------|------|
| Administrator | Full Access |
| Manager (Future) | Limited Administration |
| Receptionist (Future) | Booking Management |
| Customer (Future) | Personal Dashboard |

---

# 19. Error Handling

The application uses centralized error handling.

## Error Categories

- Validation Errors
- Authentication Errors
- Authorization Errors
- Database Errors
- File Upload Errors
- Payment Errors
- System Errors

---

## Error Principles

- Never expose stack traces.
- Never expose SQL errors.
- Log all exceptions.
- Return user-friendly messages.

---

## Error Flow

```text
Exception
    │
    ▼
Exception Handler
    │
    ▼
Log Error
    │
    ▼
Return Response
```

---

# 20. Logging Architecture

All critical activities are logged.

## Log Directory

```text
storage/logs/
```

---

## Logged Events

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

## Log Categories

```text
application.log
authentication.log
booking.log
payment.log
security.log
error.log
```

---

# 21. Security Architecture

Security is enforced across all backend layers.

## Security Measures

- Prepared Statements
- Password Hashing
- CSRF Protection
- XSS Prevention
- Input Validation
- Output Escaping
- Session Security
- File Upload Validation

---

## Security Principles

- Security by Default
- Least Privilege Access
- Never Trust User Input
- Validate Everything

---

# 22. Performance Optimization

The backend is optimized for performance.

## Techniques

- Query Optimization
- Database Indexing
- Lazy Loading
- Caching (Future)
- Connection Reuse
- Efficient Pagination

---

## Performance Goals

| Metric | Target |
|------|------|
| API Response | < 500 ms |
| Login | < 1 s |
| Dashboard Load | < 2 s |
| Booking Creation | < 1 s |

---

# 23. Future Scalability

The backend architecture supports future expansion.

## Planned Enhancements

- Redis Cache
- Docker Deployment
- Queue Workers
- WebSockets
- Multi-Hotel Support
- Mobile API
- Cloud Storage
- Microservices

---

# 24. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 05_Folder_Structure.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 10_Database_Dictionary.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md

---

# 25. Conclusion

The Backend Architecture document establishes the enterprise-grade foundation for the Tara Guest House Management System.

By following this architecture, the development team can ensure:

- Maintainability
- Scalability
- Security
- Performance
- Consistency
- Reusability
- Reliability

The Enterprise Layered MVC Architecture provides a robust foundation capable of supporting current business requirements while remaining flexible for future enhancements.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |

