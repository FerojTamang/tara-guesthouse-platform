# Tara Guest House Authentication and Authorization Specification

## Document Information

* Status: Synchronized — Review Required
* Scope: Version 1 Administrator authentication and authorization

## 1. Actors and Roles

Administrator is the only stored/authenticated Version 1 role. Guest is an unauthenticated actor and is not stored in `roles`. Customer accounts and Receptionist, Manager, Accountant and Content Manager roles/permissions are Future Enhancements.

## 2. Authentication Mechanism

Version 1 uses a server-side PHP session. It does not issue or accept JWT access/refresh tokens.

Exact JSON endpoints:

* `POST /api/v1/admin/auth/login`
* `POST /api/v1/admin/auth/logout`
* `GET /api/v1/admin/auth/profile`
* `PUT /api/v1/admin/auth/password`
* `POST /api/v1/admin/auth/forgot-password`
* `POST /api/v1/admin/auth/reset-password`

Normal page routes include `/admin/login` and protected `/admin/...` pages.

## 3. Login Flow

1. Administrator submits normalized email/username, password and CSRF/public-login controls.
2. Controller validates syntax and rate limits.
3. Authentication Service loads the Administrator through User Repository.
4. The service verifies active/lock state and `password_hash` using PHP password APIs.
5. On success, PHP regenerates the session ID and stores the minimum Administrator identity/role data.
6. The action is audited without logging credentials/session identifiers.
7. The PHP-rendered flow redirects to `/admin/dashboard`; JSON returns session/authenticated profile data only.

Failure messages do not reveal whether an account exists.

## 4. Session Lifetime

Session controls use configuration placeholders:

* `SESSION_INACTIVITY_TIMEOUT` — Architect/Security Configuration Required;
* `SESSION_ABSOLUTE_LIFETIME` — Architect/Security Configuration Required.

Each protected request checks creation time and last activity. A session exceeding either configured limit is destroyed and rejected. Logout destroys server-side state and expires the cookie. No hardcoded 30-minute value is approved.

Production cookies are Secure, HttpOnly and use an appropriate SameSite setting. Session IDs regenerate after login and any authorization-sensitive identity change.

## 5. Authorization

Every protected Administrator page/API requires a valid session and Administrator role. Unauthorized JSON returns `401`; authenticated but disallowed actions return `403`. State-changing session requests require CSRF validation.

Version 1 does not expose role creation, permission assignment or specialized staff matrices. The `roles` table is future-ready, but only Administrator is seeded/used.

## 6. Password and Reset Rules

Passwords use PHP `password_hash()`/`password_verify()` with an approved current algorithm. Minimum/complexity/breach-check rules require security configuration and must be consistently validated. Password reset tokens are random, stored only as hashes, single-use and expiring. Reset lifetime is an approved security configuration value rather than a hardcoded document value.

## 7. Responsibilities and Persistence

* Controllers validate requests and format responses.
* Authentication Service applies login/session/password/reset rules and transactions.
* User Repository contains PDO/SQL.
* User/Role entities represent data and execute no SQL.
* Views render escaped fields and execute no SQL.

## 8. Security and Audit

Use TLS, prepared statements, generic errors, rate limiting/lock controls, CSRF, session fixation protection, safe logging and output escaping. Audit successful/failed login, logout, password/reset events, lock/state changes and Administrator-account mutations without secrets.

## 9. Acceptance Tests

* Only active Administrator accounts authenticate.
* Login regenerates the session and returns no bearer tokens.
* Missing/expired sessions cannot access `/api/v1/admin/...`.
* Both configured session lifetime limits are enforced.
* Logout invalidates the session.
* CSRF protects state changes.
* Guest/specialized roles cannot authenticate in Version 1.
* Password/reset secrets are hashed and never logged.

## 10. Future Enhancements

Customer authentication/portal, specialized staff RBAC, two-factor authentication, social login, passkeys, device management and bearer-token APIs require later approval.
