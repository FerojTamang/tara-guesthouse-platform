# Tara Guest House Management System

# Authentication & Authorization System Specification

---

# Document Control

| Item | Value |
|------|--------|
| Project | Tara Guest House Management System |
| Document | Authentication & Authorization System Specification |
| Version | 1.0 |
| Status | Draft |
| Prepared By | fero.dev |
| Owner | Feroj |
| Last Updated | July 2026 |

---

# Change Log

| Version | Date | Author | Description |
|----------|------|---------|-------------|
| 1.0 | July 2026 | fero.dev | Initial Authentication & Authorization Specification |

---

# Table of Contents

1. Purpose
2. Scope
3. Actors
4. User Stories
5. Business Objectives
6. Functional Requirements
7. Non-Functional Requirements
8. Authentication Flow
9. Authorization Model
10. User Roles
11. Permission Matrix
12. Session Management
13. Password Policy
14. Security Controls
15. Validation Rules
16. Error Handling
17. Audit Logging
18. Performance Requirements
19. Accessibility Requirements
20. Acceptance Criteria
21. Future Enhancements
22. Related Modules

---

# 1. Purpose

This document defines how users authenticate to the Tara Guest House Management System and how authorization controls access to protected resources.

The objective is to ensure that only authorized users can access administrative features while protecting customer and business data.

---

# 2. Scope

Included

- Login
- Logout
- Session Management
- Password Management
- Authorization
- User Roles
- Permissions
- Account Status
- Login History
- Audit Logging

Excluded

- Customer Portal Authentication (Future)
- Google Login
- Two-Factor Authentication

---

# 3. Actors

## Administrator

Can

- Login
- Logout
- Change Password
- Manage website

---

## Future Manager

Can

- Login
- Manage bookings
- Manage rooms

Limited permissions.

---

## Future Receptionist

Can

- Check guests in
- Check guests out
- Manage reservations

Cannot change CMS settings.

---

# 4. User Stories

### As an Administrator

I want to securely log in

So that I can manage the hotel.

---

### As an Administrator

I want my password encrypted

So that it cannot be stolen from the database.

---

### As an Administrator

I want inactive accounts blocked

So unauthorized users cannot access the system.

---

# 5. Business Objectives

The authentication system shall

- Protect hotel information
- Prevent unauthorized access
- Record login history
- Support future expansion
- Follow OWASP security recommendations

---

# 6. Functional Requirements

The system shall provide

- Login
- Logout
- Password Change
- Session Timeout
- Password Hashing
- Account Lock
- Account Activation
- Role Checking
- Permission Checking
- Login Logging

---

# 7. Non-Functional Requirements

The authentication system shall be

- Secure
- Reliable
- Fast
- Maintainable
- Scalable

Average login response time

< 2 seconds

---

# 8. Authentication Flow

Administrator

↓

Open Login Page

↓

Enter Username or Email

↓

Enter Password

↓

Server Validation

↓

Password Verification

↓

Account Status Check

↓

Create Secure Session

↓

Redirect Dashboard

If login fails

↓

Display Generic Error

↓

Log Attempt

---

# 9. Authorization Model

Role-Based Access Control (RBAC)

Users

↓

Role

↓

Permissions

↓

Resources

---

# 10. User Roles

Current Version

| Role | Description |
|------|-------------|
| Administrator | Full system access |

Future

| Role | Description |
|------|-------------|
| Manager | Operational management |
| Receptionist | Booking management |
| Accountant | Financial reports |

---

# 11. Permission Matrix

| Module | Admin | Manager | Receptionist |
|---------|-------|----------|--------------|
| Dashboard | ✅ | ✅ | ✅ |
| Rooms | ✅ | ✅ | ❌ |
| Gallery | ✅ | ❌ | ❌ |
| Activities | ✅ | ✅ | ❌ |
| CMS | ✅ | ❌ | ❌ |
| Bookings | ✅ | ✅ | ✅ |
| Reviews | ✅ | ✅ | ❌ |
| Payments | ✅ | ✅ | ❌ |
| Settings | ✅ | ❌ | ❌ |
| Users | ✅ | ❌ | ❌ |

---

# 12. Session Management

Sessions shall

- Regenerate Session ID after login
- Expire after inactivity
- Be destroyed on logout
- Use Secure Cookies
- Use HttpOnly cookies
- Use SameSite protection

Default timeout

30 minutes

---

# 13. Password Policy

Minimum Length

12 characters

Must contain

- Uppercase letter
- Lowercase letter
- Number
- Special character

Passwords shall be hashed using

PHP

password_hash()

Algorithm

PASSWORD_DEFAULT

Passwords shall never be stored in plain text.

---

# 14. Security Controls

The authentication system shall implement

- CSRF Protection
- SQL Injection Prevention
- XSS Prevention
- Password Hashing
- Session Regeneration
- Secure Cookies
- HttpOnly Cookies
- SameSite Cookies
- Brute Force Protection
- Login Rate Limiting
- Server-side Validation
- Output Escaping

---

# 15. Validation Rules

Username

3–50 characters

Email

Valid email format

Password

12–128 characters

Only active users may log in.

---

# 16. Error Handling

Never reveal whether

- Username exists
- Email exists
- Password is incorrect

Display

Invalid username/email or password.

Do not expose system information.

---

# 17. Audit Logging

Record

- Successful Login
- Failed Login
- Logout
- Password Change
- Account Lock
- Account Unlock
- Role Changes
- Permission Changes

Audit Log Fields

- User
- IP Address
- Browser
- Date
- Time
- Action

---

# 18. Performance Requirements

Login response

Less than 2 seconds

Support

100 concurrent sessions

Session lookup

Less than 100 milliseconds

---

# 19. Accessibility Requirements

Authentication pages shall

- Support keyboard navigation
- Meet WCAG 2.1 AA
- Display clear error messages
- Provide visible focus indicators
- Support screen readers

---

# 20. Acceptance Criteria

The module is complete when

- Users can log in securely.
- Passwords are hashed.
- Sessions are protected.
- Unauthorized users are denied access.
- Audit logs are recorded.
- Permissions work correctly.

---

# 21. Future Enhancements

- Two-Factor Authentication (2FA)
- OTP Login
- Google Login
- Microsoft Login
- Passwordless Login
- Passkeys
- Face ID
- Fingerprint Login
- Security Dashboard
- Device Management

---

# 22. Related Database Tables

- users
- roles
- user_permissions (Future)
- audit_logs
- login_history (Future)

---

# 23. Related API Endpoints

POST /login

POST /logout

POST /change-password

GET /profile

PUT /profile

---

# 24. Related Modules

- Admin Dashboard
- CMS
- Booking Management
- Room Management
- Gallery Management
- Payment System
- Notification System

---

# End of Document