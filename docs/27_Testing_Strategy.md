# Testing Strategy

> **Approved Version 1 traceability:** Critical automated tests must cover Administrator session login/logout and authorization, CSRF, room CRUD/publication, overlap and concurrency protection, one-room booking creation, every allowed/forbidden canonical booking transition, multiple payment attempts and verification, invoices, check-in/check-out, verified-stay reviews, uploads, and `/api/v1/...` response contracts. A feature is not complete until these applicable tests pass and documentation is synchronized.

## Project Information

| Item | Details |
|------|---------|
| **Project** | Tara Guest House Management System |
| **Document** | Testing Strategy |
| **Document No.** | 27 |
| **Version** | 1.1 |
| **Status** | Sprint 4 synchronized — Review Required |
| **Prepared By** | fero.dev |
| **Project Owner** | Feroj |
| **Date** | 1 August 2026 |

---

# 1. Purpose

This document defines the testing standards, methodologies, environments, and quality assurance procedures for the Tara Guest House Management System.

It establishes standards for:

- Unit Testing
- Integration Testing
- Feature Testing
- System Testing
- User Acceptance Testing (UAT)
- Security Testing
- Performance Testing
- Regression Testing
- Browser Testing
- Mobile Testing

The objective of this document is to ensure that the application is reliable, secure, maintainable, and production-ready.

All testing activities must adhere to the standards defined in this document.

---

# 2. Testing Overview

Testing is performed throughout the software development lifecycle.

```text
Requirements
      │
      ▼
Development
      │
      ▼
Testing
      │
      ▼
Staging
      │
      ▼
Production
```

Testing ensures that:

- Functional requirements are met.
- Business rules are enforced.
- Security vulnerabilities are minimized.
- Performance targets are achieved.
- User expectations are satisfied.

---

# 3. Testing Objectives

The primary objectives of testing are:

- Verify system functionality.
- Validate business requirements.
- Ensure application stability.
- Identify defects early.
- Improve software quality.
- Reduce production failures.
- Verify security controls.
- Validate performance metrics.

---

# 4. Testing Principles

The project follows the following testing principles:

- Test Early
- Test Frequently
- Automate Where Possible
- Security by Default
- Performance Awareness
- Risk-Based Testing
- Continuous Improvement
- Complete Traceability

---

# 5. Testing Types

| Testing Type | Purpose |
|--------------|---------|
| Unit Testing | Individual Components |
| Integration Testing | Module Interaction |
| Feature Testing | Application Features |
| System Testing | Entire Application |
| UAT | Customer Validation |
| Security Testing | Vulnerability Assessment |
| Performance Testing | Speed & Stability |
| Regression Testing | Existing Functionality |
| Browser Testing | Cross Browser Support |
| Mobile Testing | Responsive Validation |

---

# 6. Testing Environment

The project uses separate testing environments.

| Environment | Purpose |
|-------------|---------|
| Local | Developer Testing |
| Testing | QA Testing |
| Staging | Pre-Production |
| Production | Live Environment |

---

## Environment Flow

```text
Local
  │
  ▼
Testing
  │
  ▼
Staging
  │
  ▼
Production
```

---

# 7. Unit Testing

Unit Testing verifies individual components.

## Examples

- BookingService
- PaymentService
- NotificationService
- UserService
- Validator

---

## Objectives

- Verify business logic.
- Validate calculations.
- Verify helper functions.
- Ensure predictable behavior.

---

## Example

```text
BookingService
     │
     ├── Test Availability
     ├── Test Date Validation
     └── Test Pricing Logic
```

---

# 8. Integration Testing

Integration Testing verifies communication between modules.

## Examples

```text
Booking → Payment
Booking → Notifications
Authentication → Sessions
Reviews → Rooms
CMS → Website
```

---

## Objectives

- Verify module interaction.
- Verify API communication.
- Verify database consistency.
- Verify event execution.

---

# 9. Feature Testing

Feature Testing validates complete user-facing features.

## Features

- Login
- Registration
- Room Booking
- Payments
- Reviews
- Contact Forms
- Notifications
- CMS Management

---

## Example

```text
Customer Books Room
        │
        ▼
Validate Dates
        │
        ▼
Create Booking
        │
        ▼
Generate Invoice
        │
        ▼
Send Notification
```

---

# 10. System Testing

System Testing validates the application as a whole.

## Scope

- Frontend
- Backend
- Database
- APIs
- Authentication
- Authorization
- Notifications

---

## Objectives

- Validate complete workflows.
- Verify integrations.
- Ensure system stability.
- Verify production readiness.

---

# 11. User Acceptance Testing (UAT)

User Acceptance Testing verifies that the system meets business requirements.

## Participants

- Project Owner
- Reception Staff
- Administrators
- Test Customers

---

## UAT Areas

- Room Reservations
- Payment Processing
- Dashboard Usage
- CMS Operations
- Report Generation

---

## UAT Approval Criteria

- Business requirements satisfied.
- Critical defects resolved.
- Stakeholder approval obtained.

---

# 12. Security Testing

Security testing validates the application's defenses.

## Security Tests

- SQL Injection
- Cross-Site Scripting (XSS)
- CSRF
- Session Security
- Authorization Checks
- File Upload Validation
- Password Security

---

## Objectives

- Prevent unauthorized access.
- Protect sensitive information.
- Validate security controls.
- Reduce attack surface.

---

## End of Part 1

Part 2 includes:

- Performance Testing
- Regression Testing
- Browser Testing
- Mobile Testing
- Test Data Management
- Bug Reporting Process
- Test Execution Workflow
- Exit Criteria
- Future Improvements
- Related Documents
- Conclusion
- Approval

---

# 13. Performance Testing

Performance Testing evaluates the speed, responsiveness, and stability of the application under normal and peak loads.

## Performance Targets

| Feature | Target |
|--------|--------|
| Homepage | < 2 Seconds |
| Login | < 1 Second |
| Dashboard | < 2 Seconds |
| Booking Creation | < 1 Second |
| API Response | < 500 ms |

---

## Performance Areas

- Database Queries
- API Responses
- Dashboard Loading
- Booking Processing
- Payment Processing
- File Uploads

---

## Objectives

- Identify bottlenecks.
- Improve response times.
- Ensure scalability.
- Validate production readiness.

---

# 14. Regression Testing

Regression Testing ensures that new changes do not break existing functionality.

## Regression Scope

- Authentication
- Bookings
- Payments
- Notifications
- CMS
- Reports
- Settings

---

## Regression Workflow

```text
New Feature
     │
     ▼
Execute Tests
     │
     ▼
Verify Existing Features
     │
     ▼
Approve Release
```

---

## Objectives

- Protect existing functionality.
- Reduce production defects.
- Improve release confidence.

---

# 15. Browser Testing

The application must support modern browsers.

## Supported Browsers

| Browser | Supported |
|--------|--------|
| Chrome | Yes |
| Firefox | Yes |
| Edge | Yes |
| Safari | Yes |
| Opera | Yes |

Internet Explorer is not supported.

---

## Browser Testing Areas

- Navigation
- Forms
- Responsive Layout
- JavaScript Features
- File Uploads
- Dashboard Components

---

# 16. Mobile Testing

The application must be fully responsive.

## Supported Devices

- Mobile Phones
- Tablets
- Laptops
- Desktops

---

## Mobile Test Cases

- Responsive Navigation
- Responsive Tables
- Booking Forms
- Payment Forms
- Gallery
- Dashboard

---

## Objectives

- Verify responsive behavior.
- Ensure usability across devices.
- Validate touch interactions.

---

# 17. Test Data Management

Test data must be managed separately from production data.

## Rules

- Never use production customer data.
- Use anonymized test data.
- Reset test environments regularly.
- Document test datasets.

---

## Example Test Users

```text
Administrator
Guest booking data (not an authenticated user)
Future role fixtures only when those roles are approved
```

---

## Test Data Categories

- Users
- Rooms
- Bookings
- Payments
- Reviews
- Notifications

---

# 18. Bug Reporting Process

All defects must be documented.

## Bug Lifecycle

```text
New
 │
 ▼
Assigned
 │
 ▼
In Progress
 │
 ▼
Resolved
 │
 ▼
Verified
 │
 ▼
Closed
```

---

## Bug Information

Each bug report should contain:

- Bug ID
- Title
- Description
- Steps to Reproduce
- Expected Result
- Actual Result
- Severity
- Assigned Developer
- Status

---

## Severity Levels

| Severity | Description |
|---------|------------|
| Critical | System Failure |
| High | Major Feature Impact |
| Medium | Partial Feature Impact |
| Low | Minor Issues |

---

# 19. Test Execution Workflow

The official testing workflow is:

```text
Requirements
      │
      ▼
Development
      │
      ▼
Unit Testing
      │
      ▼
Integration Testing
      │
      ▼
Feature Testing
      │
      ▼
System Testing
      │
      ▼
UAT
      │
      ▼
Production Approval
```

---

## Rules

- No feature proceeds without testing.
- Critical bugs must be resolved immediately.
- UAT approval is mandatory before production.

---

# 20. Exit Criteria

The application may only be deployed if all exit criteria are met.

## Mandatory Conditions

- Critical Bugs = 0
- High Severity Bugs = 0
- UAT Approved
- Security Tests Passed
- Performance Tests Passed
- Deployment Checklist Completed
- Project Manager Approval Obtained

---

## Release Approval Flow

```text
QA Approval
      │
      ▼
Technical Approval
      │
      ▼
Project Manager Approval
      │
      ▼
Production Deployment
```

## Sprint 4 executed regression evidence

The Operations and Content implementation baseline at `cdff525` completed the full local PHPUnit suite with **2,118 tests, 13,044 assertions, 14 skipped, and zero failures/errors**. The development database reported **25 applied migrations and 0 pending**.

Sprint 4 closure additionally requires:

- exact route inventory review against `routes/web.php`, `routes/admin.php`, and `routes/api.php`;
- isolated migration apply/rollback/reapply coverage for all nine Sprint 4 migrations;
- public active/published/public/approved eligibility tests;
- Administrator authorization and CSRF tests for every mutation;
- gallery upload path/MIME/extension/size/dimension/decodability tests;
- review-token hash, expiry, single-use, checked-out eligibility, and no-store tests;
- notification ownership and absent delete/archive route tests;
- SMTP TLS/configuration/template/post-commit/failure-isolation tests;
- static scans for secrets, raw tokens, private paths, and unapproved future routes;
- `git diff --check`, PHP syntax, Markdown link, database status, and full-suite gates before the S4-019 commit.

Manual browser/device, formal accessibility, SMTP-provider, remote CI, UAT, cPanel, performance, penetration, and production checks remain separate evidence and must not be inferred from local PHPUnit success.

See `45_Sprint_4_QA_Closure_Report.md` for the formal evidence inventory and remaining gates.

---

# 21. Future Improvements

The testing strategy supports future enhancements.

## Planned Improvements

- Automated Testing
- CI/CD Integration
- Load Testing
- Stress Testing
- Penetration Testing
- Selenium Automation
- API Testing Automation
- Performance Monitoring

---

# 22. Related Documents

This document should be used together with:

- 04_Project_Architecture.md
- 23_REST_API_Specification.md
- 24_Frontend_Architecture.md
- 25_Backend_Architecture.md
- 26_Deployment_Guide.md

---

# 23. Conclusion

The Testing Strategy document establishes the quality assurance standards for the Tara Guest House Management System.

By following this strategy, the development and QA teams can ensure:

- Reliability
- Security
- Stability
- Performance
- Maintainability
- Production Readiness

Testing is an essential component of the software development lifecycle and must be performed consistently throughout the project.

---

# Document Approval

| Role | Name | Status |
|------|------|--------|
| Project Manager | Feroj | Review Required |
| Technical Architect | fero.dev | Review Required |
| QA Engineer | Pallawi | Pending |
| Backend Developer | Rijan | Pending |
| Frontend Developer | Saroj | Pending |
