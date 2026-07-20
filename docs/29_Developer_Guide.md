# Developer Guide

> **Version 1 authority:** Before implementation, developers and AI tools must read `00_V1_ARCHITECT_DECISIONS.md`, the relevant module specification, migrations/dictionary, route/API contract, folder/coding standards, ticket, and acceptance criteria. Do not invent columns, statuses, routes, roles, frameworks, or future features; use PHP sessions and canonical `/api/v1/...` routes.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Developer Guide |
| **Document No.** | 29 |
| **Version** | 1.0 |
| **Status** | Synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | July 2026 |

---

# 1. Purpose

This document serves as the official development handbook for the Tara Guest House Management System.

It provides standards, guidelines, and procedures for all developers contributing to the project.

The objectives of this document are to:

- Standardize development practices.
- Improve maintainability.
- Reduce technical debt.
- Simplify onboarding.
- Ensure code consistency.
- Promote security and quality.

All developers must comply with the standards defined in this document.

---

# 2. Introduction

The Tara Guest House Management System is an enterprise-grade web application developed using a layered MVC-inspired architecture.

The application provides:

- Room Management
- Booking Management
- Payment Management
- Invoice Management
- Gallery Management
- Review Management
- Notification Management
- Reporting
- CMS Management

The system is designed to be maintainable, scalable, secure, and reusable for future hospitality projects.

---

# 3. Developer Responsibilities

All developers are responsible for:

- Writing clean code.
- Following coding standards.
- Maintaining documentation.
- Writing tests.
- Performing code reviews.
- Following security guidelines.
- Maintaining application quality.

---

## Developer Roles

| Role | Responsibilities |
|------|------------------|
| Backend Developer | PHP, MariaDB, APIs |
| Frontend Developer | HTML, Bootstrap, JavaScript |
| QA Engineer | Testing |
| Technical Architect | System Design |
| Project Manager | Planning & Delivery |

---

# 4. Development Environment Setup

## Required Software

| Software | Version |
|---------|--------|
| PHP | 8.3+ |
| Apache | 2.4+ |
| MariaDB | 11+ |
| Composer | Latest |
| Git | Latest |
| VS Code | Recommended |

---

## Setup Steps

### Clone Repository

```bash
git clone <repository-url>
```

---

### Navigate to Project

```bash
cd tara-guesthouse-platform
```

---

### Install Dependencies

```bash
composer install
```

---

### Configure Environment

```bash
cp .env.example .env
```

---

### Start the local environment

Start Apache and MariaDB through XAMPP or another approved local PHP environment. Production service control is performed by the cPanel/shared-hosting provider; Version 1 development instructions do not require root service commands.

---

# 5. Project Structure

```text
tara-guesthouse-platform/
│
├── app/
├── config/
├── database/
├── docs/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
│
├── composer.json
├── .env
└── README.md
```

---

# 6. Architecture Overview

The project follows a Layered MVC-inspired architecture.

```text
Browser
   │
   ▼
Routes
   │
   ▼
Controllers
   │
   ▼
Services
   │
   ▼
Models
   │
   ▼
MariaDB
```

---

## Architecture Rules

- Controllers remain lightweight.
- Services contain business logic.
- Repositories communicate with the database through PDO; Models/entities represent domain data only.
- Views handle presentation only.

---

# 7. Folder Structure

```text
app/
├── Controllers/
├── Models/
├── Services/
├── Middleware/
├── Core/
├── Helpers/
└── Repositories/
```

---

## Folder Responsibilities

| Folder | Responsibility |
|------|----------------|
| Controllers | Request Handling |
| Models/entities | Domain data representation; no SQL/PDO |
| Services | Business Logic |
| Middleware | Authorization |
| Core | Framework Components |
| Helpers | Utility Functions |
| Repositories | Data Access Layer |

---

# 8. Coding Standards

All developers must adhere to the following standards.

## General Rules

- Write readable code.
- Avoid duplication.
- Use meaningful names.
- Keep functions small.
- Comment complex logic.
- Follow SOLID principles.

---

## Code Quality Principles

- DRY
- KISS
- SRP
- Separation of Concerns
- Security by Default

---

# 9. Naming Conventions

## PHP Classes

```php
BookingController
RoomService
UserRepository
PaymentModel
```

---

## Methods

```php
createBooking()
updateRoom()
sendNotification()
```

---

## Variables

```php
$bookingDate
$totalAmount
$roomPrice
```

---

## Database

```text
Tables      : users
Columns     : created_at
Foreign Key : user_id
```

---

# 10. Git Workflow

The project follows Git Flow.

```text
main
 │
 ├── develop
 │      │
 │      ├── feature/bookings
 │      ├── feature/payments
 │      └── feature/reviews
 │
 └── hotfix/*
```

---

## Git Rules

- Never commit directly to main.
- Use descriptive commit messages.
- Create pull requests.
- Perform code reviews.

---

## Example Commit Messages

```text
feat: add booking functionality
fix: resolve payment validation issue
refactor: improve room service
docs: update API documentation
```

---

# 11. Database Standards

Database rules:

- Follow Third Normal Form.
- Use foreign keys.
- Use snake_case.
- Include timestamps.

---

## Required Columns

```text
id
created_at
updated_at
```

---

## Database Rules

- No duplicate data.
- No hardcoded values.
- Use indexes where necessary.
- Enforce referential integrity.

---

# 12. API Development Standards

All APIs must follow REST principles.

## Example

```http
GET    /api/v1/rooms
POST   /api/v1/bookings
GET    /api/v1/admin/bookings/{id}
PATCH  /api/v1/admin/bookings/{id}/status
```

---

## API Rules

- Return JSON.
- Use HTTP status codes.
- Validate requests.
- Handle exceptions properly.

---

## End of Part 1

Part 2 includes:

- Frontend Development Standards
- Backend Development Standards
- Security Guidelines
- Logging Standards
- Error Handling
- Testing Responsibilities
- Deployment Responsibilities
- Contribution Guidelines
- Conclusion
- Approval

---

# 13. Frontend Development Standards

The frontend is developed using:

- HTML5
- Bootstrap 5
- JavaScript (ES6+)

---

## Frontend Rules

- Keep views simple.
- Use reusable components where possible.
- Follow responsive design principles.
- Avoid inline styles.
- Minimize JavaScript duplication.

---

## CSS Standards

Use `kebab-case` for CSS classes.

Examples:

```text
hero-banner
room-card
booking-form
dashboard-widget
```

---

## JavaScript Standards

Use `camelCase` for functions.

Examples:

```javascript
loadRooms()
submitBooking()
calculateTotal()
```

---

## Responsive Design Requirements

| Device | Supported |
|-------|------------|
| Mobile | Yes |
| Tablet | Yes |
| Laptop | Yes |
| Desktop | Yes |

---

# 14. Backend Development Standards

The backend is developed using PHP 8.3 and MariaDB.

---

## Backend Rules

- Controllers must remain lightweight.
- Services contain business logic.
- Repositories contain PDO/SQL persistence logic; Models/entities contain no database access.
- Use Prepared Statements exclusively.
- Validate all user input.

---

## Request Flow

```text
Request
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
Database
```

---

## Backend Principles

- Security by Default
- Separation of Concerns
- Single Responsibility Principle
- Reusability
- Maintainability

---

# 15. Security Guidelines

Security is the responsibility of every developer.

## Mandatory Security Controls

- Password Hashing
- Prepared Statements
- CSRF Protection
- XSS Prevention
- Session Validation
- Authorization Checks
- File Upload Validation

---

## Secure Coding Checklist

```text
□ Validate Input
□ Escape Output
□ Hash Passwords
□ Use HTTPS
□ Verify User Permissions
□ Protect Sessions
□ Log Security Events
```

---

# 16. Logging Standards

Application logs are stored in:

```text
storage/logs/
```

---

## Log Categories

- Application Logs
- Error Logs
- Security Logs
- Authentication Logs
- Booking Logs

---

## Logging Rules

- Never log passwords.
- Never log sensitive information.
- Include timestamps.
- Include severity levels.

---

## Severity Levels

| Level | Description |
|------|-------------|
| INFO | Informational Events |
| WARNING | Potential Issues |
| ERROR | Application Errors |
| CRITICAL | System Failure |

---

# 17. Error Handling

Errors must be handled consistently throughout the application.

---

## Error Handling Rules

- Never display stack traces to users.
- Log all unexpected exceptions.
- Return user-friendly messages.
- Use centralized error handling.

---

## Example Workflow

```text
Exception
    │
    ▼
Log Error
    │
    ▼
Display Friendly Message
    │
    ▼
Continue Application
```

---

# 18. Testing Responsibilities

Every developer is responsible for testing their code.

---

## Minimum Requirements

- Unit Tests
- Integration Tests
- Feature Tests

---

## Developer Checklist

```text
□ Feature Implemented
□ Tests Written
□ Code Reviewed
□ Documentation Updated
□ No Critical Bugs
□ Pull Request Created
```

---

# 19. Deployment Responsibilities

Only approved releases may be deployed.

---

## Deployment Process

```text
Feature Complete
        │
        ▼
Testing Complete
        │
        ▼
QA Approval
        │
        ▼
Project Manager Approval
        │
        ▼
Deployment
```

---

## Developer Responsibilities

- Verify code quality.
- Verify tests.
- Verify documentation.
- Verify migrations.
- Verify deployment scripts.

---

# 20. Contribution Guidelines

All contributions must follow established project standards.

---

## Contribution Workflow

```text
Fork Repository
      │
      ▼
Create Feature Branch
      │
      ▼
Implement Changes
      │
      ▼
Write Tests
      │
      ▼
Submit Pull Request
      │
      ▼
Code Review
      │
      ▼
Merge
```

---

## Pull Request Requirements

- Description included.
- Tests completed.
- Documentation updated.
- No merge conflicts.
- Approval obtained.

---

# 21. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 05_Folder_Structure.md
- 08_Database_Design.md
- 09_ER_Diagram.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md
- 26_Deployment_Guide.md
- 27_Testing_Strategy.md

---

# 22. Conclusion

The Developer Guide establishes the standards, responsibilities, and expectations for all developers contributing to the Tara Guest House Management System.

By following this guide, developers can ensure:

- Consistent Code Quality
- Improved Maintainability
- Enhanced Security
- Reliable Deployments
- Easier Collaboration
- Faster Onboarding

This document serves as the official handbook for all current and future development team members.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
| QA Engineer | Pallawi | Pending |
